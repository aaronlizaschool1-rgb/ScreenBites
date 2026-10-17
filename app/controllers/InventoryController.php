<?php
/**
 * ScreenBites: Inventory Controller
 * 
 * Manages concession stock audits, replenishment overrides,
 * and low-stock threshold notifications (FR5, FR7).
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use Core\Logger;
use App\Models\Product;

class InventoryController extends Controller
{
    private Product $productModel;

    public function __construct()
    {
        // Enforce RBAC: Cashiers can view items; Admins can adjust and add stock
        Auth::requireRole([ROLE_ADMIN, ROLE_CASHIER]);

        $this->productModel = new Product();
    }

    /**
     * Display inventory dashboard with low-stock callouts (FR5, FR7)
     */
    public function index(): void
    {
        $products       = $this->productModel->getAllProducts();
        $lowStockAlerts = $this->productModel->getLowStockAlerts();

        // AJAX polling endpoint for live cashier alert badge updates
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->json([
                'success'      => true,
                'alerts_count' => count($lowStockAlerts),
                'items'        => $lowStockAlerts
            ]);
            return;
        }

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        // Renders app/views/concessions/inventory.php
        $this->view('concessions/inventory', [
            'products'       => $products,
            'lowStockAlerts' => $lowStockAlerts,
            'flashSuccess'   => $flashSuccess,
            'flashError'     => $flashError,
            'csrfToken'      => Auth::csrfToken(),
            'userRole'       => Auth::role()
        ]);
    }

    /**
     * Process manual inventory adjustments / restocks (FR5)
     * Automatically logs the modification in audit_logs (FR16)
     */
    public function adjustStock(): void
    {
        // Only Administrators can manually modify stock levels
        Auth::requireRole(ROLE_ADMIN);

        if (!Auth::verifyCsrfToken((string)$this->post('csrf_token'))) {
            $_SESSION['flash_error'] = 'Security validation failed. Please try again.';
            $this->redirect('inventory');
            return;
        }

        $productId = (int)$this->post('product_id', 0);
        $newStock  = (int)$this->post('new_stock', -1);
        $reason    = trim((string)$this->post('reason', 'Routine restock'));

        if ($productId <= 0 || $newStock < 0) {
            $_SESSION['flash_error'] = 'Invalid product or negative stock count provided.';
            $this->redirect('inventory');
            return;
        }

        $product = $this->productModel->findById($productId);
        if (!$product) {
            $_SESSION['flash_error'] = 'Product not found.';
            $this->redirect('inventory');
            return;
        }

        $previousStock = (int)$product['current_stock'];

        // Execute update
        $updated = $this->productModel->updateStock($productId, $newStock);

        if ($updated) {
            // FR16: Log manual stock override in audit_logs
            Logger::log(
                AUDIT_INVENTORY_OVERRIDE,
                'products',
                $productId,
                [
                    'product_name'   => $product['product_name'],
                    'previous_stock' => $previousStock,
                    'adjusted_stock' => $newStock,
                    'reason'         => $reason
                ]
            );

            $_SESSION['flash_success'] = "Inventory for '{$product['product_name']}' updated to {$newStock} units.";
        } else {
            $_SESSION['flash_error'] = 'Failed to update inventory levels.';
        }

        $this->redirect('inventory');
    }
}