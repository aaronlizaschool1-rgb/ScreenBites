<?php
/**
 * ScreenBites: Audit Controller
 * 
 * Handles rendering and filtering of operational audit trail logs.
 * Restricted strictly to the Administrator role (FR13, FR16).
 * 
 * @author Aaron Louis F. Macapagal
 */

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
        // FR13 Enforcement: Audit logs are strictly restricted to Administrators
        Auth::requireRole(ROLE_ADMIN);

        $this->auditModel = new AuditLog();
        $this->userModel  = new User();
    }

    /**
     * Display the audit log table with filter options
     */
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

        // Fetch users to populate the filter dropdown
        $users = $this->userModel->getAllUsers();

        $actionTypes = [
            AUDIT_CANCEL_TICKET,
            AUDIT_REFUND_ORDER,
            AUDIT_INVENTORY_OVERRIDE,
            AUDIT_EXPENSE_MODIFIED,
            AUDIT_LOGIN_FAILURE
        ];

        // Deliver data to app/views/audit/index.php (assigned to John for frontend presentation)
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