<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Order;
use App\Services\ModuleHealthGuardService;

class OrderController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        $this->ensureOrdersModuleHealthy('read');

        $model = new Order($this->config);
        $statusOptions = array_keys(order_status_options());
        $search = sanitize_text((string) ($_GET['q'] ?? ''), 120);
        $status = validate_enum((string) ($_GET['status'] ?? ''), array_merge([''], $statusOptions), '');
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = $model->paginated($search, $status, $page, 10);

        $this->view('admin/orders/index', [
            'orders' => $result['data'],
            'meta' => $result['meta'],
            'search' => $search,
            'status' => $status,
            'statusOptions' => $statusOptions,
        ], 'admin');
    }

    public function show(int $id): void
    {
        $this->requireAdmin();
        $this->ensureOrdersModuleHealthy('read');

        if ($id <= 0) {
            flash('danger', 'Đơn hàng không hợp lệ.');
            redirect('admin/orders');
        }

        $model = new Order($this->config);
        $order = $model->find($id);

        if (!$order) {
            flash('danger', 'Đơn hàng không tồn tại.');
            redirect('admin/orders');
        }

        $this->view('admin/orders/show', [
            'order' => $order,
            'statusOptions' => array_keys(order_status_options()),
        ], 'admin');
    }

    public function updateStatus(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/orders/show/' . $id);
        $this->ensureOrdersModuleHealthy('write');

        if ($id <= 0) {
            flash('danger', 'Đơn hàng không hợp lệ.');
            redirect('admin/orders');
        }

        $allowed = array_keys(order_status_options());
        $status = validate_enum((string) ($_POST['status'] ?? ''), $allowed, 'pending');

        if (!in_array($status, $allowed, true)) {
            flash('danger', 'Trạng thái đơn hàng không hợp lệ.');
            redirect('admin/orders/show/' . $id);
        }

        $model = new Order($this->config);
        $ok = $model->updateStatus($id, $status);

        if ($ok) {
            admin_audit('update_status', 'order', $id, ['status' => $status]);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Cập nhật trạng thái đơn hàng thành công.' : 'Cập nhật trạng thái thất bại.');
        redirect('admin/orders/show/' . $id);
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/orders');
        $this->ensureOrdersModuleHealthy('write');

        if ($id <= 0) {
            flash('danger', 'Đơn hàng không hợp lệ.');
            redirect('admin/orders');
        }

        $model = new Order($this->config);
        $ok = $model->delete($id);

        if ($ok) {
            admin_audit('delete', 'order', $id);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Xóa đơn hàng thành công.' : 'Xóa đơn hàng thất bại.');
        redirect('admin/orders');
    }

    private function ensureOrdersModuleHealthy(string $intent): void
    {
        $healthGuard = new ModuleHealthGuardService($this->config);
        if ($healthGuard->isHealthy('orders')) {
            return;
        }

        $healthGuard->logBlockedAction('orders', $intent, [
            'controller' => __CLASS__,
            'intent' => $intent,
        ]);
        flash('danger', $healthGuard->messageFor('orders', $intent));
        redirect('admin');
    }
}
