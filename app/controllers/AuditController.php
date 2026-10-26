<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use App\Models\AuditLog;
use App\Models\User;

class AuditController extends Controller
{
    private AuditLog $auditModel;
    private User $userModel;

    public function __construct()
    {

        Auth::requireRole(ROLE_ADMIN);

        $this->auditModel = new AuditLog();
        $this->userModel  = new User();
    }

    public function index(): void
    {
        $filters = [
            'action_type' => $this->get('action_type') ?: null,
            'user_id'     => $this->get('user_id') ?: null,
            'start_date'  => $this->get('start_date') ?: null,
            'end_date'    => $this->get('end_date') ?: null,
        ];

        $page = max(1, (int)$this->get('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $logs = $this->auditModel->getLogs($filters, $limit, $offset);
        $totalLogs = $this->auditModel->countLogs($filters);
        $totalPages = (int)ceil($totalLogs / $limit);

        $users = $this->userModel->getAllUsers();

        $actionTypes = [
            AUDIT_CANCEL_TICKET,
            AUDIT_REFUND_ORDER,
            AUDIT_INVENTORY_OVERRIDE,
            AUDIT_EXPENSE_MODIFIED,
            AUDIT_LOGIN_FAILURE
        ];

        $this->view('audit/index', [
            'logs'        => $logs,
            'users'       => $users,
            'actionTypes' => $actionTypes,
            'filters'     => $filters,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'totalLogs'   => $totalLogs
        ]);
    }
}
