<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Order;
use App\Models\WalletTransaction;

class PaymentController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('admin.payments.view', 'Bạn không có quyền truy cập khu vực quản lý thanh toán.');

        $search = sanitize_text((string) ($_GET['q'] ?? ''), 120);
        $transactionType = validate_enum((string) ($_GET['type'] ?? ''), ['', 'deposit', 'spend', 'refund', 'adjustment'], '');
        $status = validate_enum((string) ($_GET['status'] ?? ''), ['', 'pending', 'completed', 'failed'], '');
        $page = validate_int_range($_GET['page'] ?? 1, 1, 999999, 1);

        $walletTransactionModel = new WalletTransaction($this->config);
        $orderModel = new Order($this->config);

        $walletResult = $walletTransactionModel->adminPaginated($search, $transactionType, $status, $page, 15);

        $this->view('admin/payments/index', [
            'title' => 'Quản lý thanh toán',
            'summary' => $walletTransactionModel->adminSummary(),
            'transactions' => $walletResult['data'],
            'meta' => $walletResult['meta'],
            'filters' => [
                'q' => $search,
                'type' => $transactionType,
                'status' => $status,
            ],
            'orderSnapshot' => [
                'today_revenue' => $orderModel->todayRevenue(),
                'today_orders' => $orderModel->todayOrdersCount(),
                'pending_orders' => $orderModel->countByStatus('pending'),
                'processing_orders' => $orderModel->countByStatus('processing'),
                'paid_orders' => $orderModel->countByStatus('paid'),
                'completed_orders' => $orderModel->countByStatus('completed'),
                'latest_orders' => $orderModel->latestByFilters([], 6),
            ],
        ], 'admin');
    }
}
