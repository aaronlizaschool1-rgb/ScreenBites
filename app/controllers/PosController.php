<?php
/**
 * ScreenBites: Point-of-Sale Controller
 * 
 * Manages concession catalog rendering and quick-touch cashier views (FR8).
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use App\Models\Product;
use App\Models\Combo;

class PosController extends Controller
{
    private Product $productModel;
    private Combo $comboModel;

    public function __construct()
    {
        // Enforce RBAC: POS interface is open to Cashiers and Administrators
        Auth::requireRole([ROLE_ADMIN, ROLE_CASHIER, ROLE_TICKETING]);

        $this->productModel = new Product();
        $this->comboModel   = new Combo();
    }

    /**
     * Render the POS register workspace
     */
    public function index(): void
    {
        $products = $this->productModel->getAllProducts();
        $combos   = $this->comboModel->getAllCombosWithItems();

        // AJAX request for dynamic cart replenishment checks
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->json([
                'success'  => true,
                'products' => $products,
                'combos'   => $combos
            ]);
            return;
        }

        // Renders app/views/concessions/pos.php
        $this->view('concessions/pos', [
            'products'  => $products,
            'combos'    => $combos,
            'taxRate'   => TAX_RATE,
            'csrfToken' => Auth::csrfToken(),
            'user'      => Auth::user()
        ]);
    }
}