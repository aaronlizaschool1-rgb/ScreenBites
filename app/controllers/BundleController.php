<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use App\Models\Combo;
use App\Models\Product;
use Throwable;

class BundleController extends Controller
{
    private Combo $comboModel;
    private Product $productModel;

    public function __construct()
    {

        Auth::requireRole([ROLE_ADMIN, ROLE_CASHIER]);

        $this->comboModel   = new Combo();
        $this->productModel = new Product();
    }

    public function index(): void
    {
        $combos   = $this->comboModel->getAllCombosWithItems();
        $products = $this->productModel->getAllProducts();

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->json([
                'success' => true,
                'combos'  => $combos
            ]);
            return;
        }

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $this->view('concessions/bundles', [
            'combos'       => $combos,
            'products'     => $products,
            'flashSuccess' => $flashSuccess,
            'flashError'   => $flashError,
            'csrfToken'    => Auth::csrfToken(),
            'userRole'     => Auth::role()
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(ROLE_ADMIN);

        if (!Auth::verifyCsrfToken((string)$this->post('csrf_token'))) {
            $_SESSION['flash_error'] = 'Security validation failed. Please try again.';
            $this->redirect('bundles');
            return;
        }

        $name        = trim((string)$this->post('combo_name', ''));
        $description = trim((string)$this->post('description', ''));
        $price       = (float)$this->post('combo_price', 0.00);
        $productIds  = (array)$this->post('product_ids', []);
        $quantities  = (array)$this->post('quantities', []);

        if ($name === '' || $price <= 0.00 || empty($productIds)) {
            $_SESSION['flash_error'] = 'A bundle title, package price, and at least one concession item are required.';
            $this->redirect('bundles');
            return;
        }

        $formattedItems = [];
        foreach ($productIds as $idx => $rawProdId) {
            $prodId = (int)$rawProdId;
            $qty    = max(1, (int)($quantities[$idx] ?? 1));

            if ($prodId > 0) {

                if (isset($formattedItems[$prodId])) {
                    $formattedItems[$prodId]['quantity'] += $qty;
                } else {
                    $formattedItems[$prodId] = [
                        'product_id' => $prodId,
                        'quantity'   => $qty
                    ];
                }
            }
        }

        if (empty($formattedItems)) {
            $_SESSION['flash_error'] = 'Please select valid products for the bundle package.';
            $this->redirect('bundles');
            return;
        }

        try {
            $this->comboModel->createCombo($name, $description, $price, array_values($formattedItems));
            $_SESSION['flash_success'] = "Promotional package '{$name}' created successfully.";
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Failed to create combo package: ' . $e->getMessage();
        }

        $this->redirect('bundles');
    }
}
