<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/constants.php';

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

$router = new Router();

$router->get('', 'AuthController@index');
$router->get('login', 'AuthController@index');
$router->post('login', 'AuthController@authenticate');
$router->get('logout', 'AuthController@logout');
$router->get('dashboard', 'DashboardController@index');

$router->get('movies', 'MovieController@index');
$router->post('movies/create', 'MovieController@create');
$router->get('schedules', 'ScheduleController@index');
$router->post('schedules/create', 'ScheduleController@create');

$router->get('ticketing/screening/{id}', 'BookingController@seatMatrix');
$router->post('ticketing/hold-seat', 'BookingController@holdSeat');
$router->post('ticketing/release-seat', 'BookingController@releaseSeat');

$router->get('pos', 'PosController@index');
$router->get('inventory', 'InventoryController@index');
$router->post('inventory/adjust', 'InventoryController@adjustStock');
$router->get('bundles', 'BundleController@index');
$router->post('bundles/create', 'BundleController@create');

$router->post('checkout/process', 'CheckoutController@processOrder');
$router->post('checkout/refund', 'CheckoutController@refund');
$router->get('receipt/{order_number}', 'CheckoutController@viewReceipt');

$router->get('expenses/utilities', 'UtilityController@index');
$router->post('expenses/utilities/store', 'UtilityController@store');
$router->get('reports/financial', 'ReportController@financialReport');

$router->get('reports/sales', 'ReportController@salesReport');
$router->get('reports/occupancy', 'ReportController@occupancyAnalytics');

$router->get('audit', 'AuditController@index');

$router->dispatch();
