<?php

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

        Auth::requireRole([ROLE_ADMIN, ROLE_CASHIER, ROLE_TICKETING]);

        $this->productModel = new Product();
        $this->comboModel   = new Combo();
    }

    public function index(): void
    {
        $products = $this->productModel->getAllProducts();
        $combos   = $this->comboModel->getAllCombosWithItems();

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->json([
                'success'  => true,
                'products' => $products,
                'combos'   => $combos
            ]);
            return;
        }

        $this->view('concessions/pos', [
            'products'  => $products,
            'combos'    => $combos,
            'taxRate'   => TAX_RATE,
            'csrfToken' => Auth::csrfToken(),
            'user'      => Auth::user()
        ]);
    }
}
