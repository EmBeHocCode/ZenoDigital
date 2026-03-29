<?php

namespace App\Services;

use App\Core\Auth;

class AdminAiModulePermissionService
{
    public function moduleConfig(string $module): array
    {
        $module = strtolower(trim($module));

        return $this->definitions()[$module] ?? [
            'label' => ucfirst($module),
            'read_scope_flag' => null,
            'write_permission' => null,
            'high_risk_actions' => [],
            'write_enabled' => false,
        ];
    }

    public function canRead(string $module, array $scope = []): bool
    {
        $config = $this->moduleConfig($module);
        $flag = $config['read_scope_flag'] ?? null;

        if ($flag === null) {
            return false;
        }

        return !empty($scope[$flag]);
    }

    public function canWrite(string $module): bool
    {
        if (!$this->hasManagePermission($module)) {
            return false;
        }

        $config = $this->moduleConfig($module);
        return !empty($config['write_enabled']);
    }

    public function hasManagePermission(string $module): bool
    {
        $config = $this->moduleConfig($module);
        $permission = (string) ($config['write_permission'] ?? '');

        if ($permission === '') {
            return false;
        }

        return Auth::can($permission);
    }

    public function denyReadMessage(string $module, array $scope = []): string
    {
        $config = $this->moduleConfig($module);
        $label = (string) ($config['label'] ?? ucfirst($module));
        $scopeLabel = trim((string) ($scope['label'] ?? 'quyền hiện tại'));

        return 'Phiên này không có quyền đọc module `' . $label . '` trong phạm vi `' . $scopeLabel . '`.';
    }

    public function denyWriteMessage(string $module, array $scope = []): string
    {
        $config = $this->moduleConfig($module);
        $label = (string) ($config['label'] ?? ucfirst($module));
        $scopeLabel = trim((string) ($scope['label'] ?? 'quyền hiện tại'));

        return 'Phiên này không có quyền ghi vào module `' . $label . '` trong phạm vi `' . $scopeLabel . '`.';
    }

    public function riskLabel(string $risk): string
    {
        return match (strtolower(trim($risk))) {
            'low' => 'low-risk write',
            'medium' => 'medium-risk write',
            'high' => 'high-risk / destructive',
            default => 'read-only',
        };
    }

    public function definitions(): array
    {
        return [
            'products' => [
                'label' => 'Sản phẩm',
                'read_scope_flag' => 'can_view_products',
                'write_permission' => 'admin.products.manage',
                'write_enabled' => true,
                'high_risk_actions' => ['product_delete'],
            ],
            'categories' => [
                'label' => 'Danh mục',
                'read_scope_flag' => 'can_view_categories',
                'write_permission' => 'admin.categories.manage',
                'write_enabled' => true,
                'high_risk_actions' => ['category_delete'],
            ],
            'orders' => [
                'label' => 'Đơn hàng',
                'read_scope_flag' => 'can_view_orders',
                'write_permission' => 'admin.orders.manage',
                'write_enabled' => true,
                'high_risk_actions' => ['order_delete'],
            ],
            'coupons' => [
                'label' => 'Coupon',
                'read_scope_flag' => 'can_view_coupons',
                'write_permission' => 'admin.coupons.manage',
                'write_enabled' => true,
                'high_risk_actions' => ['coupon_delete'],
            ],
            'feedback' => [
                'label' => 'Feedback',
                'read_scope_flag' => 'can_view_feedback',
                'write_permission' => 'admin.feedback.manage',
                'write_enabled' => true,
                'high_risk_actions' => [],
            ],
            'users' => [
                'label' => 'Người dùng',
                'read_scope_flag' => 'can_view_users',
                'write_permission' => 'admin.users.manage',
                'write_enabled' => true,
                'high_risk_actions' => ['user_delete', 'user_role_update'],
            ],
            'settings' => [
                'label' => 'Cài đặt hệ thống',
                'read_scope_flag' => 'can_view_settings',
                'write_permission' => 'admin.settings.manage',
                'write_enabled' => false,
                'high_risk_actions' => ['settings_update_safe'],
            ],
            'rank' => [
                'label' => 'Rank',
                'read_scope_flag' => 'can_view_rank',
                'write_permission' => 'admin.ranks.manage',
                'write_enabled' => false,
                'high_risk_actions' => ['rank_update'],
            ],
            'payments' => [
                'label' => 'Thanh toán / Ví',
                'read_scope_flag' => 'can_view_payments',
                'write_permission' => null,
                'write_enabled' => false,
                'high_risk_actions' => [],
            ],
            'audit' => [
                'label' => 'Audit log',
                'read_scope_flag' => 'can_view_audit',
                'write_permission' => null,
                'write_enabled' => false,
                'high_risk_actions' => [],
            ],
            'sql' => [
                'label' => 'SQL Manager',
                'read_scope_flag' => 'can_view_sql',
                'write_permission' => null,
                'write_enabled' => false,
                'high_risk_actions' => [],
            ],
        ];
    }
}
