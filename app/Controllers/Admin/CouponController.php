<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Coupon;
use App\Services\ModuleHealthGuardService;

class CouponController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        $this->ensureCouponsModuleHealthy('read');

        $model = new Coupon($this->config);
        $search = sanitize_text((string) ($_GET['q'] ?? ''), 120);
        $status = validate_enum((string) ($_GET['status'] ?? ''), ['', 'active', 'inactive'], '');
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = $model->paginated($search, $status, $page, 10);

        $this->view('admin/coupons/index', [
            'coupons' => $result['data'],
            'meta' => $result['meta'],
            'search' => $search,
            'status' => $status,
        ], 'admin');
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/coupons');
        $this->ensureCouponsModuleHealthy('write');

        $code = strtoupper(sanitize_text((string) ($_POST['code'] ?? ''), 80));
        $description = sanitize_text((string) ($_POST['description'] ?? ''), 255);
        $discountPercent = validate_int_range($_POST['discount_percent'] ?? null, 1, 90, 0);
        $maxUses = validate_int_range($_POST['max_uses'] ?? null, 0, 1000000, 0);
        $status = validate_enum((string) ($_POST['status'] ?? ''), ['active', 'inactive'], 'active');
        $expiresAt = sanitize_text((string) ($_POST['expires_at'] ?? ''), 30);

        if ($code === '' || $discountPercent <= 0) {
            flash('danger', 'Mã coupon và phần trăm giảm giá là bắt buộc.');
            redirect('admin/coupons');
        }

        $expiresAtValue = null;
        if ($expiresAt !== '') {
            $timestamp = strtotime($expiresAt);
            if ($timestamp === false) {
                flash('danger', 'Ngày hết hạn coupon không hợp lệ.');
                redirect('admin/coupons');
            }
            $expiresAtValue = date('Y-m-d H:i:s', $timestamp);
        }

        $model = new Coupon($this->config);
        $ok = $model->create([
            'code' => $code,
            'description' => $description,
            'discount_percent' => $discountPercent,
            'max_uses' => $maxUses,
            'expires_at' => $expiresAtValue,
            'status' => $status,
        ]);

        if ($ok) {
            admin_audit('create', 'coupon', null, ['code' => $code, 'discount_percent' => $discountPercent]);
            flash('success', 'Tạo coupon thành công.');
        } else {
            flash('danger', 'Không thể tạo coupon. Có thể mã đã tồn tại.');
        }

        redirect('admin/coupons');
    }

    public function toggleStatus(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/coupons');
        $this->ensureCouponsModuleHealthy('write');

        $model = new Coupon($this->config);
        $ok = $model->toggleStatus($id);
        if ($ok) {
            admin_audit('toggle_status', 'coupon', $id);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Đã cập nhật trạng thái coupon.' : 'Không thể cập nhật trạng thái coupon.');
        redirect('admin/coupons');
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/coupons');
        $this->ensureCouponsModuleHealthy('write');

        $model = new Coupon($this->config);
        $ok = $model->delete($id);
        if ($ok) {
            admin_audit('delete', 'coupon', $id);
        }

        flash($ok ? 'success' : 'danger', $ok ? 'Xóa coupon thành công.' : 'Xóa coupon thất bại.');
        redirect('admin/coupons');
    }

    private function ensureCouponsModuleHealthy(string $intent): void
    {
        $healthGuard = new ModuleHealthGuardService($this->config);
        if ($healthGuard->isHealthy('coupons')) {
            return;
        }

        $healthGuard->logBlockedAction('coupons', $intent, [
            'controller' => __CLASS__,
            'intent' => $intent,
        ]);
        flash('danger', $healthGuard->messageFor('coupons', $intent));
        redirect('admin');
    }
}
