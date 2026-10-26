<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Auth;
use Core\Controller;
use Core\Database;
use App\Models\Screening;
use App\Models\Product;
use App\Models\Movie;

class DashboardController extends Controller
{
    private Database $db;
    private Screening $screeningModel;
    private Product $productModel;
    private Movie $movieModel;

    public function __construct()
    {
        $this->db             = Database::getInstance();
        $this->screeningModel = new Screening();
        $this->productModel   = new Product();
        $this->movieModel     = new Movie();
    }

    public function index(): void
    {
        if (!Auth::check()) {
            $this->redirect('login');
            return;
        }

        if (Auth::hasRole(ROLE_TICKETING)) {
            $this->redirect('schedules');
            return;
        }

        if (Auth::hasRole(ROLE_CASHIER)) {
            $this->redirect('pos');
            return;
        }

        if (Auth::hasRole(ROLE_ADMIN)) {
            $today = date('Y-m-d');

            $totalRevenue = (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(subtotal_amount + tax_amount), 0.00) FROM orders WHERE order_status = 'Completed'"
            );

            $todayRevenue = (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(subtotal_amount + tax_amount), 0.00) FROM orders WHERE order_status = 'Completed' AND DATE(created_at) = :today",
                [':today' => $today]
            );

            $totalTicketsSold = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM tickets WHERE ticket_status = 'Valid'"
            );

            $totalConcessionsSold = (int)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(quantity), 0) FROM order_items"
            );

            $todayScreenings = $this->screeningModel->getScheduleByDate($today);

            if (empty($todayScreenings)) {
                $todayScreenings = $this->db->fetchAll(
                    "SELECT s.screening_id, s.movie_id, s.hall_id, s.screening_date, s.start_time, s.end_time, s.base_price,
                            m.title AS movie_title, m.duration_minutes, m.rating AS movie_rating, m.poster_path,
                            h.hall_name,
                            (SELECT COUNT(*) FROM tickets t WHERE t.screening_id = s.screening_id AND t.ticket_status = 'Valid') AS sold_seats_count
                     FROM screenings s
                     INNER JOIN movies m ON s.movie_id = m.movie_id
                     INNER JOIN halls h ON s.hall_id = h.hall_id
                     WHERE s.screening_date >= :today
                     ORDER BY s.screening_date ASC, s.start_time ASC
                     LIMIT 4",
                    [':today' => $today]
                );
            }

            $lowStockAlerts = $this->productModel->getLowStockAlerts();

            $recentOrders = $this->db->fetchAll(
                "SELECT o.order_id, o.order_number, (o.subtotal_amount + o.tax_amount) AS total_amount,
                        o.order_status, o.created_at, u.full_name AS cashier_name,
                        (SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.order_id) AS ticket_count,
                        (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.order_id) AS concession_count
                 FROM orders o
                 INNER JOIN users u ON o.user_id = u.user_id
                 ORDER BY o.created_at DESC
                 LIMIT 6"
            );

            $activeMovies = $this->movieModel->getActiveMovies();

            $kpis = [
                'total_revenue'       => $totalRevenue,
                'today_revenue'       => $todayRevenue,
                'tickets_sold'        => $totalTicketsSold,
                'concessions_sold'    => $totalConcessionsSold,
                'low_stock_count'     => count($lowStockAlerts),
                'active_movies_count' => count($activeMovies),
                'screenings_count'    => count($todayScreenings),
            ];

            $this->view('dashboard/index', [
                'pageTitle'       => 'Command Dashboard',
                'kpis'            => $kpis,
                'todayScreenings' => $todayScreenings,
                'recentOrders'    => $recentOrders,
                'lowStockAlerts'  => $lowStockAlerts,
                'todayDate'       => $today
            ]);
            return;
        }

        $this->redirect('login');
    }
}
