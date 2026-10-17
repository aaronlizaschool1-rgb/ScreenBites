<?php
/**
 * ScreenBites: Financial Analytics & Reporting Engine Controller
 * 
 * Computes Net Operating Income, sales segregations, and hall occupancy analytics.
 * Strictly restricted to Administrator role (FR12, FR14, FR15, FR13).
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use Core\Database;

class ReportController extends Controller
{
    private Database $db;

    public function __construct()
    {
        // Enforce RBAC: Financial reports and analytics require Administrator access
        Auth::requireRole(ROLE_ADMIN);

        $this->db = Database::getInstance();
    }

    /**
     * FR12: Net Operating Income Computation
     * Gross Revenue (Box Office + Concessions) - COGS - Overhead Utilities = Net Operating Income
     */
    public function financialReport(): void
    {
        $selectedYear  = (int)$this->get('year', (int)date('Y'));
        $selectedMonth = (int)$this->get('month', (int)date('m'));

        // 1. Box Office Gross Revenue
        $ticketSql = "SELECT COALESCE(SUM(t.price_charged), 0.00) AS total_ticket_sales,
                             COUNT(t.ticket_id) AS total_tickets_sold
                      FROM tickets t
                      INNER JOIN orders o ON t.order_id = o.order_id
                      WHERE o.order_status = 'Completed'
                        AND YEAR(o.created_at) = :year1
                        AND MONTH(o.created_at) = :month1";
        
        $ticketData = $this->db->fetchOne($ticketSql, [
            ':year1'  => $selectedYear,
            ':month1' => $selectedMonth
        ]);
        $ticketRevenue = (float)($ticketData['total_ticket_sales'] ?? 0.00);
        $ticketsSold   = (int)($ticketData['total_tickets_sold'] ?? 0);

        // 2. Concessions Gross Revenue
        $concessionSql = "SELECT COALESCE(SUM(oi.quantity * oi.unit_price_charged), 0.00) AS total_concessions_sales,
                                 COALESCE(SUM(oi.quantity), 0) AS total_items_sold
                          FROM order_items oi
                          INNER JOIN orders o ON oi.order_id = o.order_id
                          WHERE o.order_status = 'Completed'
                            AND YEAR(o.created_at) = :year2
                            AND MONTH(o.created_at) = :month2";

        $concessionData = $this->db->fetchOne($concessionSql, [
            ':year2'  => $selectedYear,
            ':month2' => $selectedMonth
        ]);
        $concessionRevenue = (float)($concessionData['total_concessions_sales'] ?? 0.00);
        $itemsSold         = (int)($concessionData['total_items_sold'] ?? 0);

        $grossRevenue = $ticketRevenue + $concessionRevenue;

        // 3. Concessions Cost of Goods Sold (COGS)
        // A. Standalone Products COGS
        $standaloneCogsSql = "SELECT COALESCE(SUM(oi.quantity * p.unit_cost), 0.00)
                              FROM order_items oi
                              INNER JOIN orders o ON oi.order_id = o.order_id
                              INNER JOIN products p ON oi.product_id = p.product_id
                              WHERE o.order_status = 'Completed'
                                AND oi.product_id IS NOT NULL
                                AND YEAR(o.created_at) = :year3
                                AND MONTH(o.created_at) = :month3";

        $standaloneCogs = (float)$this->db->fetchColumn($standaloneCogsSql, [
            ':year3'  => $selectedYear,
            ':month3' => $selectedMonth
        ]);

        // B. Combo / Bundle Concessions COGS
        $comboCogsSql = "SELECT COALESCE(SUM(oi.quantity * ci.quantity * p.unit_cost), 0.00)
                         FROM order_items oi
                         INNER JOIN orders o ON oi.order_id = o.order_id
                         INNER JOIN combos c ON oi.combo_id = c.combo_id
                         INNER JOIN combo_items ci ON c.combo_id = ci.combo_id
                         INNER JOIN products p ON ci.product_id = p.product_id
                         WHERE o.order_status = 'Completed'
                           AND oi.combo_id IS NOT NULL
                           AND YEAR(o.created_at) = :year4
                           AND MONTH(o.created_at) = :month4";

        $comboCogs = (float)$this->db->fetchColumn($comboCogsSql, [
            ':year4'  => $selectedYear,
            ':month4' => $selectedMonth
        ]);

        $totalCogs = $standaloneCogs + $comboCogs;

        // 4. Overhead Utilities (Electricity and Water)
        $utilitySql = "SELECT utility_type, bill_amount 
                       FROM utility_expenses 
                       WHERE billing_year = :year5 
                         AND billing_month = :month5";

        $utilityRows = $this->db->fetchAll($utilitySql, [
            ':year5'  => $selectedYear,
            ':month5' => $selectedMonth
        ]);

        $electricityCost = 0.00;
        $waterCost       = 0.00;
        foreach ($utilityRows as $row) {
            if ($row['utility_type'] === UTILITY_ELECTRICITY) {
                $electricityCost += (float)$row['bill_amount'];
            } elseif ($row['utility_type'] === UTILITY_WATER) {
                $waterCost += (float)$row['bill_amount'];
            }
        }
        $totalUtilities = $electricityCost + $waterCost;

        // 5. Net Operating Income
        $totalOverheadExpenses = $totalCogs + $totalUtilities;
        $netOperatingIncome    = $grossRevenue - $totalOverheadExpenses;

        $financialSummary = [
            'period'               => date('F Y', mktime(0, 0, 0, $selectedMonth, 10, $selectedYear)),
            'ticket_revenue'       => $ticketRevenue,
            'tickets_sold'         => $ticketsSold,
            'concession_revenue'   => $concessionRevenue,
            'items_sold'           => $itemsSold,
            'gross_revenue'        => $grossRevenue,
            'cogs'                 => $totalCogs,
            'electricity_cost'     => $electricityCost,
            'water_cost'           => $waterCost,
            'total_utilities'      => $totalUtilities,
            'total_expenses'       => $totalOverheadExpenses,
            'net_operating_income' => $netOperatingIncome,
            'profit_margin'        => $grossRevenue > 0 ? round(($netOperatingIncome / $grossRevenue) * 100, 2) : 0.00
        ];

        if ($this->isAjaxOrJson()) {
            $this->json(['success' => true, 'data' => $financialSummary]);
            return;
        }

        // Renders app/views/reports/financial.php
        $this->view('reports/financial', [
            'financial'     => $financialSummary,
            'selectedYear'  => $selectedYear,
            'selectedMonth' => $selectedMonth
        ]);
    }

    /**
     * FR14: Box Office & Concession Sales Reports
     * Itemized daily and monthly performance segregating tickets, food, and tender methods.
     */
    public function salesReport(): void
    {
        $startDate = (string)$this->get('start_date', date('Y-m-01'));
        $endDate   = (string)$this->get('end_date', date('Y-m-d'));

        // Daily breakdown of tickets vs concessions
        $timelineSql = "SELECT 
                            DATE(o.created_at) AS sale_date,
                            COALESCE(SUM(t.price_charged), 0.00) AS box_office_sales,
                            COALESCE(SUM(oi.quantity * oi.unit_price_charged), 0.00) AS concession_sales,
                            (COALESCE(SUM(t.price_charged), 0.00) + COALESCE(SUM(oi.quantity * oi.unit_price_charged), 0.00)) AS total_sales
                        FROM orders o
                        LEFT JOIN tickets t ON o.order_id = t.order_id AND t.ticket_status = 'Valid'
                        LEFT JOIN order_items oi ON o.order_id = oi.order_id
                        WHERE o.order_status = 'Completed'
                          AND DATE(o.created_at) BETWEEN :start_date AND :end_date
                        GROUP BY DATE(o.created_at)
                        ORDER BY sale_date DESC";

        $salesTimeline = $this->db->fetchAll($timelineSql, [
            ':start_date' => $startDate,
            ':end_date'   => $endDate
        ]);

        // Revenue by Payment Tender Method
        $paymentSql = "SELECT 
                           p.payment_method,
                           COUNT(p.payment_id) AS transaction_count,
                           SUM(p.amount_paid - p.change_returned) AS net_collected
                       FROM payments p
                       INNER JOIN orders o ON p.order_id = o.order_id
                       WHERE o.order_status = 'Completed'
                         AND DATE(o.created_at) BETWEEN :start_date AND :end_date
                       GROUP BY p.payment_method";

        $paymentBreakdown = $this->db->fetchAll($paymentSql, [
            ':start_date' => $startDate,
            ':end_date'   => $endDate
        ]);

        if ($this->isAjaxOrJson()) {
            $this->json([
                'success'           => true,
                'sales_timeline'    => $salesTimeline,
                'payment_breakdown' => $paymentBreakdown
            ]);
            return;
        }

        // Renders app/views/reports/sales.php
        $this->view('reports/sales', [
            'salesTimeline'    => $salesTimeline,
            'paymentBreakdown' => $paymentBreakdown,
            'startDate'        => $startDate,
            'endDate'          => $endDate
        ]);
    }

    /**
     * FR15: Hall Occupancy & Peak-Hour Analytics
     * Computes screening capacity utilization and hourly attendance curves.
     */
    public function occupancyAnalytics(): void
    {
        $selectedDate = (string)$this->get('date', date('Y-m-d'));

        // Hall Occupancy Rates per screening
        $occupancySql = "SELECT 
                             s.screening_id,
                             m.title AS movie_title,
                             h.hall_name,
                             s.screening_date,
                             s.start_time,
                             s.end_time,
                             (h.total_rows * h.total_columns) AS total_capacity,
                             COUNT(t.ticket_id) AS seats_sold,
                             ROUND((COUNT(t.ticket_id) / (h.total_rows * h.total_columns)) * 100, 2) AS occupancy_rate
                         FROM screenings s
                         INNER JOIN movies m ON s.movie_id = m.movie_id
                         INNER JOIN halls h ON s.hall_id = h.hall_id
                         LEFT JOIN tickets t ON s.screening_id = t.screening_id AND t.ticket_status = 'Valid'
                         WHERE s.screening_date = :target_date
                         GROUP BY s.screening_id
                         ORDER BY s.start_time ASC";

        $screeningOccupancies = $this->db->fetchAll($occupancySql, [
            ':target_date' => $selectedDate
        ]);

        // Peak-Hour Distribution (Aggregated attendance by screening start hour over last 30 days)
        $peakHourSql = "SELECT 
                            HOUR(s.start_time) AS hour_of_day,
                            COUNT(t.ticket_id) AS total_tickets_sold
                        FROM screenings s
                        INNER JOIN tickets t ON s.screening_id = t.screening_id AND t.ticket_status = 'Valid'
                        WHERE s.screening_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
                        GROUP BY HOUR(s.start_time)
                        ORDER BY hour_of_day ASC";

        $peakHourStats = $this->db->fetchAll($peakHourSql);

        if ($this->isAjaxOrJson()) {
            $this->json([
                'success'               => true,
                'screening_occupancies' => $screeningOccupancies,
                'peak_hour_stats'       => $peakHourStats
            ]);
            return;
        }

        // Renders app/views/reports/occupancy.php
        $this->view('reports/occupancy', [
            'screeningOccupancies' => $screeningOccupancies,
            'peakHourStats'        => $peakHourStats,
            'selectedDate'         => $selectedDate
        ]);
    }

    /**
     * Check if request expects JSON format or is AJAX
     */
    private function isAjaxOrJson(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    }
}