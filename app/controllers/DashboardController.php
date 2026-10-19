<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Auth;
use Core\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        if (!Auth::check()) {
            $this->redirect('login');
        }

        // Admin: KPI dashboard
        if (Auth::hasRole(ROLE_ADMIN)) {
            $this->view('dashboard/index', [
                'pageTitle' => 'Dashboard',
            ]);
            return;
        }

        // Ticketing staff: showtime picker
        if (Auth::hasRole(ROLE_TICKETING)) {
            $this->redirect('schedules');
        }

        // Snackbar cashier: POS
        if (Auth::hasRole(ROLE_CASHIER)) {
            $this->redirect('pos');
        }

        $this->redirect('login');
    }
}