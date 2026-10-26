<?php

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

        Auth::requireRole([ROLE_ADMIN, ROLE_CASHIER]);

        $this->productModel = new Product();
    }

    public function index(): void
    {
        $products       = $this->productModel->getAllProducts();
        $lowStockAlerts = $this->productModel->getLowStockAlerts();

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

        $this->view('concessions/inventory', [
            'products'       => $products,
            'lowStockAlerts' => $lowStockAlerts,
            'flashSuccess'   => $flashSuccess,
            'flashError'     => $flashError,
            'csrfToken'      => Auth::csrfToken(),
            'userRole'       => Auth::role()
        ]);
    }

    public function adjustStock(): void
    {

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

        $updated = $this->productModel->updateStock($productId, $newStock);

        if ($updated) {

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
