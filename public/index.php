<?php
/**
 * ScreenBites: Cinema Reservation and Snackbar System
 * Entry Point: Front Controller
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

// 1. Bootstrap Core Configurations and System Constants
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/constants.php';

// 2. Register Autoloader for Core, Controllers, and Models
spl_autoload_register(function (string $class): void {
    $namespaces = [
        'Core\\'            => ROOT_PATH . '/core/',
        'App\\Controllers\\' => ROOT_PATH . '/app/controllers/',
        'App\\Models\\'      => ROOT_PATH . '/app/models/',
    ];

    foreach ($namespaces as $prefix => $baseDir) {
        $prefixLength = strlen($prefix);
        if (strncmp($prefix, $class, $prefixLength) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $prefixLength);
        $filePath = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($filePath)) {
            require_once $filePath;
            return;
        }
    }
});

use Core\Router;

// 3. Initialize Router Instance
$router = new Router();

// -----------------------------------------------------------------------------
// ROUTE REGISTRATION MAPPED TO SYSTEM REQUIREMENTS (FR1 - FR16)
// -----------------------------------------------------------------------------

// --- Authentication & Dashboard (FR13) ---
$router->get('', 'AuthController@index');
$router->get('login', 'AuthController@index');
$router->post('login', 'AuthController@authenticate');
$router->get('logout', 'AuthController@logout');
$router->get('dashboard', 'DashboardController@index');

// --- Movie & Screening Scheduling (FR1) ---
$router->get('movies', 'MovieController@index');
$router->post('movies/create', 'MovieController@create');
$router->get('schedules', 'ScheduleController@index');
$router->post('schedules/create', 'ScheduleController@create');

// --- Seat Matrix, Hold & Reservation Engine (FR2, FR3, FR4) ---
$router->get('ticketing/screening/{id}', 'BookingController@seatMatrix');
$router->post('ticketing/hold-seat', 'BookingController@holdSeat');
$router->post('ticketing/release-seat', 'BookingController@releaseSeat');

// --- Concessions & Snack Bar POS (FR5, FR6, FR7, FR8) ---
$router->get('pos', 'PosController@index');
$router->get('inventory', 'InventoryController@index');
$router->post('inventory/adjust', 'InventoryController@adjustStock');
$router->get('bundles', 'BundleController@index');
$router->post('bundles/create', 'BundleController@create');

// --- Checkout, Split Payments & Receipt Generation (FR9, FR10) ---
$router->post('checkout/process', 'CheckoutController@processOrder');
$router->get('receipt/{order_number}', 'CheckoutController@viewReceipt');

// --- Utility Overhead Expense Logging & Net Income (FR11, FR12) ---
$router->get('expenses/utilities', 'UtilityController@index');
$router->post('expenses/utilities/store', 'UtilityController@store');
$router->get('reports/financial', 'ReportController@financialReport');

// --- Sales & Occupancy Analytics (FR14, FR15) ---
$router->get('reports/sales', 'ReportController@salesReport');
$router->get('reports/occupancy', 'ReportController@occupancyAnalytics');

// --- Transaction Audit Logs (FR16) ---
$router->get('audit', 'AuditController@index');

// 4. Resolve Route and Execute
$router->dispatch();