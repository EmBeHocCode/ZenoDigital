<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\AdminAuditLog;

class AuditLogController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $model = new AdminAuditLog($this->config);
        $search = sanitize_text((string) ($_GET['q'] ?? ''), 120);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $model->paginated($search, $page, 20);

        $this->view('admin/audit_logs/index', [
            'logs' => $result['data'],
            'meta' => $result['meta'],
            'search' => $search,
        ], 'admin');
    }
}
