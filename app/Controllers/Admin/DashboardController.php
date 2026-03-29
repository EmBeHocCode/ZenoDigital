<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\RankProgram;
use App\Models\User;
use App\Services\ModuleHealthGuardService;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('backoffice.dashboard', 'Bạn không có quyền truy cập dashboard quản trị.');

        $healthGuard = new ModuleHealthGuardService($this->config);
        $backofficeScope = $this->buildBackofficeScope();

        $productsHealthy = $healthGuard->isHealthy('products');
        $ordersHealthy = $healthGuard->isHealthy('orders');
        $couponsHealthy = $healthGuard->isHealthy('coupons');
        $categoriesHealthy = $healthGuard->isHealthy('categories');
        $usersHealthy = $healthGuard->isHealthy('users');

        $productModel = $productsHealthy ? new Product($this->config) : null;
        $userModel = $usersHealthy ? new User($this->config) : null;
        $orderModel = $ordersHealthy ? new Order($this->config) : null;
        $couponModel = $couponsHealthy ? new Coupon($this->config) : null;
        $categoryModel = $categoriesHealthy ? new Category($this->config) : null;

        $pendingOrders = $orderModel ? $orderModel->countByStatus('pending') : 0;
        $todayOrders = $orderModel ? $orderModel->todayOrdersCount() : 0;
        $couponSummary = (!empty($backofficeScope['can_view_coupons']) && $couponModel) ? $couponModel->summary() : [];
        $feedbackSummary = !empty($backofficeScope['can_view_feedback'])
            ? (new \App\Models\CustomerFeedback($this->config))->summary()
            : [];

        $stats = [
            'products' => $productModel ? $productModel->countAll() : 0,
            'orders' => $orderModel ? $orderModel->countAll() : 0,
            'pending_orders' => $pendingOrders,
            'today_orders' => $todayOrders,
            'active_coupons' => (!empty($backofficeScope['can_view_coupons']) && $couponModel) ? $couponModel->countActive() : 0,
            'new_feedback' => !empty($backofficeScope['can_view_feedback']) ? (int) ($feedbackSummary['total_new'] ?? 0) : 0,
            'expiring_coupons' => !empty($backofficeScope['can_view_coupons']) ? (int) ($couponSummary['expiring_soon'] ?? 0) : 0,
        ];

        if (!empty($backofficeScope['can_view_users']) && $userModel) {
            $stats['users'] = $userModel->countAll();
            $stats['new_users'] = $userModel->countRegisteredWithinDays(7);
        }

        if (!empty($backofficeScope['can_view_finance']) && $orderModel) {
            $stats['revenue'] = $orderModel->totalRevenue();
            $stats['today_revenue'] = $orderModel->todayRevenue();
        }

        $rankOverview = ['top_users' => [], 'latest_coupons' => []];
        if (!empty($backofficeScope['can_view_rank'])) {
            $rankProgram = new RankProgram($this->config);
            $rankOverview = $rankProgram->adminOverview(8, 8);
        }

        $this->view('admin/dashboard/index', [
            'stats' => $stats,
            'latestProducts' => (!empty($backofficeScope['can_view_products']) && $productModel) ? $productModel->latest(5) : [],
            'latestOrders' => (!empty($backofficeScope['can_view_orders']) && $orderModel) ? $orderModel->latest(5) : [],
            'revenueChart' => (!empty($backofficeScope['can_view_finance']) && $orderModel) ? $orderModel->revenueByMonth(6) : [],
            'ordersByMonth' => (!empty($backofficeScope['can_view_orders']) && $orderModel) ? $orderModel->ordersByMonth(6) : [],
            'userGrowthChart' => (!empty($backofficeScope['can_view_users']) && $userModel) ? $userModel->registeredByMonth(6) : [],
            'orderStatusChart' => (!empty($backofficeScope['can_view_orders']) && $orderModel) ? $orderModel->statusBreakdown() : [],
            'topProducts' => (!empty($backofficeScope['can_view_products']) && $productModel) ? $productModel->topSelling(6) : [],
            'topCategories' => (!empty($backofficeScope['can_view_products']) && $categoryModel) ? $categoryModel->topCategories(6) : [],
            'rankOverview' => $rankOverview,
            'backofficeScope' => $backofficeScope,
            'schemaHealthSummary' => $healthGuard->summary(),
        ], 'admin');
    }

    private function buildBackofficeScope(): array
    {
        $roleName = Auth::roleName();

        return [
            'role_name' => $roleName,
            'scope_key' => Auth::isAdmin() ? 'admin_full' : 'operations_limited',
            'label' => Auth::isAdmin()
                ? 'Admin toàn quyền'
                : (Auth::isStaff() ? 'Staff vận hành giới hạn' : 'Backoffice điều hành giới hạn'),
            'can_access_dashboard' => Auth::can('backoffice.dashboard'),
            'can_use_ai_copilot' => Auth::can('backoffice.ai'),
            'can_view_products' => Auth::can('backoffice.data.products'),
            'can_view_orders' => Auth::can('backoffice.data.orders'),
            'can_view_coupons' => Auth::can('backoffice.data.coupons'),
            'can_view_feedback' => Auth::can('backoffice.data.feedback'),
            'can_view_finance' => Auth::can('backoffice.data.finance'),
            'can_view_users' => Auth::can('backoffice.data.users'),
            'can_view_rank' => Auth::can('backoffice.data.rank'),
        ];
    }
}
