<?php
/**
 * ScreenBites: Checkout Controller
 * 
 * Handles multi-item transactions, ticket finalization, inventory deduction,
 * split-tender payments, and consolidated receipt display (FR8, FR9, FR10).
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use Core\Database;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\Product;
use App\Models\Combo;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\Screening;
use Throwable;

class CheckoutController extends Controller
{
    private Database $db;
    private Order $orderModel;
    private Payment $paymentModel;
    private Ticket $ticketModel;
    private Product $productModel;
    private Combo $comboModel;
    private Seat $seatModel;
    private SeatHold $seatHoldModel;
    private Screening $screeningModel;

    public function __construct()
    {
        Auth::requireRole([ROLE_ADMIN, ROLE_TICKETING, ROLE_CASHIER]);

        $this->db             = Database::getInstance();
        $this->orderModel     = new Order();
        $this->paymentModel   = new Payment();
        $this->ticketModel    = new Ticket();
        $this->productModel   = new Product();
        $this->comboModel     = new Combo();
        $this->seatModel      = new Seat();
        $this->seatHoldModel  = new SeatHold();
        $this->screeningModel = new Screening();
    }

    /**
     * Process an incoming checkout order (JSON POST from POS or Seat Matrix)
     */
    public function processOrder(): void
    {
        $payload = $this->getJsonBody();
        if (empty($payload)) {
            $payload = $this->post();
        }

        $ticketsData     = (array)($payload['tickets'] ?? []);       // [{screening_id, seat_id, price}]
        $concessionsData = (array)($payload['concessions'] ?? []);   // [{type: 'product'|'combo', id: int, quantity: int, price: float}]
        $paymentsData    = (array)($payload['payments'] ?? []);      // [{method: 'Cash'|'E-Wallet'|'Card', amount: float, reference: string|null}]

        if (empty($ticketsData) && empty($concessionsData)) {
            $this->json(['success' => false, 'message' => 'Cart is empty. Select tickets or concessions.'], 400);
            return;
        }

        if (empty($paymentsData)) {
            $this->json(['success' => false, 'message' => 'Payment details are required.'], 400);
            return;
        }

        // 1. Calculate Grand Total
        $grossTotal = 0.00;
        foreach ($ticketsData as $t) {
            $grossTotal += (float)$t['price'];
        }
        foreach ($concessionsData as $c) {
            $grossTotal += ((float)$c['price'] * (int)$c['quantity']);
        }

        // Tax calculation: Gross total includes 12% VAT in the Philippines
        $subtotal = round($grossTotal / (1 + TAX_RATE), 2);
        $taxAmount = round($grossTotal - $subtotal, 2);

        // 2. Validate Payment Tender
        $totalPaid = 0.00;
        foreach ($paymentsData as $p) {
            $totalPaid += (float)$p['amount'];
        }

        if ($totalPaid < $grossTotal) {
            $deficit = number_format($grossTotal - $totalPaid, 2);
            $this->json([
                'success' => false,
                'message' => "Insufficient payment tender. Amount remaining: ₱{$deficit}"
            ], 400);
            return;
        }

        $changeDue = round($totalPaid - $grossTotal, 2);

        // 3. Execute Transaction
        $this->db->beginTransaction();

        try {
            $userId = Auth::id() ?? 1;
            $orderNumber = $this->orderModel->generateOrderNumber();
            $orderId = $this->orderModel->createOrder($orderNumber, $userId, $subtotal, $taxAmount);

            // A. Process Tickets
            foreach ($ticketsData as $item) {
                $screeningId = (int)$item['screening_id'];
                $seatId      = (int)$item['seat_id'];
                $price       = (float)$item['price'];

                $seat = $this->seatModel->findById($seatId);
                if (!$seat) {
                    throw new \RuntimeException("Invalid seat ID: {$seatId}");
                }

                $hallId = (int)$seat['hall_id'];

                // Insert into tickets table
                $this->ticketModel->createTicket($orderId, $screeningId, $seatId, $hallId, $price);

                // Release seat hold from seat_holds table
                $this->seatHoldModel->releaseHold($screeningId, $seatId, session_id());
            }

            // B. Process Concessions & Decrement Inventory
            foreach ($concessionsData as $c) {
                $type = $c['type'] ?? 'product';
                $id   = (int)$c['id'];
                $qty  = max(1, (int)$c['quantity']);
                $price = (float)$c['price'];

                if ($type === 'product') {
                    $deducted = $this->productModel->decrementStock($id, $qty);
                    if (!$deducted) {
                        $prod = $this->productModel->findById($id);
                        $prodName = $prod['product_name'] ?? "Item #{$id}";
                        throw new \RuntimeException("Insufficient inventory stock for {$prodName}.");
                    }
                    $this->orderModel->addOrderItem($orderId, $id, null, $qty, $price);
                } elseif ($type === 'combo') {
                    $combo = $this->comboModel->findWithItems($id);
                    if (!$combo || empty($combo['items'])) {
                        throw new \RuntimeException("Invalid bundle package #{$id}");
                    }

                    // Decrement child products in the bundle
                    foreach ($combo['items'] as $child) {
                        $childId = (int)$child['product_id'];
                        $childNeeded = (int)$child['quantity'] * $qty;
                        $deducted = $this->productModel->decrementStock($childId, $childNeeded);
                        if (!$deducted) {
                            throw new \RuntimeException("Insufficient stock for {$child['product_name']} in bundle {$combo['combo_name']}.");
                        }
                    }
                    $this->orderModel->addOrderItem($orderId, null, $id, $qty, $price);
                }
            }

            // C. Record Payments & Tender Distribution
            $remainingChange = $changeDue;
            foreach ($paymentsData as $p) {
                $method = (string)$p['method'];
                $amt    = (float)$p['amount'];
                $ref    = !empty($p['reference']) ? (string)$p['reference'] : null;

                $changeForThisTender = 0.00;
                if ($method === PAYMENT_CASH && $remainingChange > 0) {
                    $changeForThisTender = $remainingChange;
                    $remainingChange = 0.00;
                }

                $this->paymentModel->recordPayment($orderId, $method, $amt, $changeForThisTender, $ref);
            }

            $this->db->commit();

            $this->json([
                'success'      => true,
                'message'      => 'Transaction completed successfully.',
                'order_number' => $orderNumber,
                'change_due'   => $changeDue,
                'receipt_url'  => BASE_URL . '/receipt/' . $orderNumber
            ]);
        } catch (Throwable $e) {
            $this->db->rollBack();
            $this->json([
                'success' => false,
                'message' => 'Checkout failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Render the consolidated printable thermal receipt (FR10)
     */
    public function viewReceipt(string $orderNumber): void
    {
        $order = $this->orderModel->findByOrderNumber($orderNumber);
        if (!$order) {
            $_SESSION['flash_error'] = 'Receipt not found.';
            $this->redirect('pos');
            return;
        }

        $orderId    = (int)$order['order_id'];
        $tickets    = $this->ticketModel->getTicketsByOrderId($orderId);
        $items      = $this->orderModel->getOrderItems($orderId);
        $payments   = $this->paymentModel->getPaymentsByOrderId($orderId);

        // Render standalone receipt template without header/sidebar
        $this->view('receipts/printable_pos', [
            'order'    => $order,
            'tickets'  => $tickets,
            'items'    => $items,
            'payments' => $payments
        ], null);
    }
}