<?php use App\Core\Auth; ?>
<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$siteName = (string) app_setting('site_name', config('app.name', 'Digital Market Pro'));

$isActive = static function (array $needles) use ($path): bool {
    foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($path, $needle)) {
            return true;
        }
    }
    return false;
};

$canDashboard = Auth::can('backoffice.dashboard');
$canManageProducts = Auth::can('admin.products.manage');
$canManageCategories = Auth::can('admin.categories.manage');
$canManageOrders = Auth::can('admin.orders.manage');
$canViewPayments = Auth::can('admin.payments.view') || Auth::can('backoffice.data.payments') || Auth::can('backoffice.data.finance');
$canViewFeedbackPage = Auth::can('admin.feedback.view');
$canManageCoupons = Auth::can('admin.coupons.manage');
$canManageRanks = Auth::can('admin.ranks.manage');
$canManageUsers = Auth::can('admin.users.manage');
$canManageSettings = Auth::can('admin.settings.manage');
$canUseSqlManager = Auth::can('admin.sql.manage');
$canViewAudit = Auth::can('admin.audit.view');

$dashboardItems = [];
if ($canDashboard) {
    $dashboardItems[] = [
        'label' => 'Tổng quan',
        'icon' => 'fas fa-gauge-high',
        'url' => base_url('admin'),
        'active' => $isActive(['/admin']) && !$isActive(['/admin/products', '/admin/categories', '/admin/orders', '/admin/payments', '/admin/feedback', '/admin/users', '/admin/settings', '/admin/coupons', '/admin/ranks', '/admin/audit-logs', '/admin/sql-manager']),
    ];
}

$contentItems = [];
if ($canManageProducts) {
    $contentItems[] = [
        'label' => 'Quản lý sản phẩm',
        'icon' => 'fas fa-box',
        'url' => base_url('admin/products'),
        'active' => $isActive(['/admin/products']),
    ];
}
if ($canManageCategories) {
    $contentItems[] = [
        'label' => 'Quản lý danh mục',
        'icon' => 'fas fa-folder-tree',
        'url' => base_url('admin/categories'),
        'active' => $isActive(['/admin/categories']),
    ];
}

$transactionItems = [];
if ($canManageOrders) {
    $transactionItems[] = [
        'label' => 'Quản lý đơn hàng',
        'icon' => 'fas fa-receipt',
        'url' => base_url('admin/orders'),
        'active' => $isActive(['/admin/orders']),
    ];
}
if ($canViewPayments) {
    $transactionItems[] = [
        'label' => 'Quản lý thanh toán',
        'icon' => 'fas fa-credit-card',
        'url' => base_url('admin/payments'),
        'active' => $isActive(['/admin/payments']),
    ];
}
if ($canViewFeedbackPage) {
    $transactionItems[] = [
        'label' => 'Feedback khách hàng',
        'icon' => 'fas fa-headset',
        'url' => base_url('admin/feedback'),
        'active' => $isActive(['/admin/feedback']),
    ];
}
if ($canManageCoupons) {
    $transactionItems[] = [
        'label' => 'Coupon Management',
        'icon' => 'fas fa-ticket',
        'url' => base_url('admin/coupons'),
        'active' => $isActive(['/admin/coupons']),
    ];
}
if ($canManageRanks) {
    $transactionItems[] = [
        'label' => 'Rank Management',
        'icon' => 'fas fa-ranking-star',
        'url' => base_url('admin/ranks'),
        'active' => $isActive(['/admin/ranks']),
    ];
}

$accountItems = [];
if ($canManageUsers) {
    $accountItems[] = [
        'label' => 'Quản lý người dùng',
        'icon' => 'fas fa-users',
        'url' => base_url('admin/users'),
        'active' => $isActive(['/admin/users']),
    ];
}

$systemItems = [];
if ($canManageSettings) {
    $systemItems[] = [
        'label' => 'Cài đặt hệ thống',
        'icon' => 'fas fa-gear',
        'url' => base_url('admin/settings'),
        'active' => $isActive(['/admin/settings']),
    ];
}
if ($canUseSqlManager) {
    $systemItems[] = [
        'label' => 'SQL Manager',
        'icon' => 'fas fa-database',
        'url' => base_url('admin/sql-manager'),
        'active' => $isActive(['/admin/sql-manager']),
    ];
}
if ($canViewAudit) {
    $systemItems[] = [
        'label' => 'Audit Log',
        'icon' => 'fas fa-clipboard-list',
        'url' => base_url('admin/audit-logs'),
        'active' => $isActive(['/admin/audit-logs']),
    ];
}

$menuGroups = array_values(array_filter([
    !empty($dashboardItems) ? [
        'key' => 'overview',
        'label' => 'Tổng quan',
        'icon' => 'fas fa-chart-pie',
        'items' => $dashboardItems,
    ] : null,
    !empty($contentItems) ? [
        'key' => 'content',
        'label' => 'Quản trị nội dung',
        'icon' => 'fas fa-layer-group',
        'items' => $contentItems,
    ] : null,
    !empty($transactionItems) ? [
        'key' => 'transactions',
        'label' => 'Quản trị giao dịch',
        'icon' => 'fas fa-wallet',
        'items' => $transactionItems,
    ] : null,
    !empty($accountItems) ? [
        'key' => 'accounts',
        'label' => 'Quản trị tài khoản',
        'icon' => 'fas fa-user-shield',
        'items' => $accountItems,
    ] : null,
    !empty($systemItems) ? [
        'key' => 'system',
        'label' => 'Hệ thống',
        'icon' => 'fas fa-sliders',
        'items' => $systemItems,
    ] : null,
]));
?>
<aside class="admin-sidebar" id="adminSidebar" data-admin-sidebar aria-label="Điều hướng quản trị">
    <div class="admin-sidebar-header">
        <a class="admin-sidebar-brand" href="<?= base_url('admin') ?>">
            <i class="fas fa-chart-pie"></i>
            <!-- Added: dedicated brand text wrapper so collapse animation only hides text. -->
            <span class="admin-sidebar-brand-text"><?= e($siteName) ?> Admin</span>
        </a>
        <div class="admin-sidebar-caption">Control center for marketplace operations</div>
    </div>

    <div class="admin-sidebar-nav">
        <?php foreach ($menuGroups as $group): ?>
            <?php
            $groupId = 'adminMenuGroup-' . $group['key'];
            $hasActiveItem = false;
            foreach ($group['items'] as $item) {
                if (!empty($item['active'])) {
                    $hasActiveItem = true;
                    break;
                }
            }
            ?>
            <div class="admin-menu-group <?= $hasActiveItem ? 'has-active is-open' : '' ?>"
                 data-admin-menu-group
                 data-group-key="<?= e($group['key']) ?>"
                 data-has-active="<?= $hasActiveItem ? '1' : '0' ?>"
                 data-default-open="<?= $hasActiveItem ? '1' : '0' ?>">
                <button class="admin-menu-toggle"
                        type="button"
                        data-admin-menu-toggle
                        aria-expanded="<?= $hasActiveItem ? 'true' : 'false' ?>"
                        aria-controls="<?= e($groupId) ?>"
                        aria-label="<?= e($group['label']) ?>"
                        title="<?= e($group['label']) ?>">
                    <span class="admin-menu-toggle-main">
                        <span class="admin-menu-group-icon" aria-hidden="true">
                            <i class="<?= e($group['icon']) ?>"></i>
                        </span>
                        <span class="admin-menu-toggle-text"><?= e($group['label']) ?></span>
                    </span>
                    <span class="admin-menu-caret" aria-hidden="true">
                        <i class="fas fa-chevron-down"></i>
                    </span>
                </button>

                <div class="admin-menu-panel"
                     id="<?= e($groupId) ?>"
                     data-admin-menu-panel
                     aria-hidden="<?= $hasActiveItem ? 'false' : 'true' ?>">
                    <div class="admin-menu-panel-inner">
                        <?php foreach ($group['items'] as $item): ?>
                            <a class="admin-menu-link <?= !empty($item['active']) ? 'active' : '' ?>" href="<?= e($item['url']) ?>">
                                <i class="<?= e($item['icon']) ?>"></i>
                                <span class="admin-menu-text"><?= e($item['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="admin-sidebar-footer">
        <a class="admin-home-link" href="<?= base_url('/') ?>">
            <i class="fas fa-arrow-left"></i>
            <span class="admin-home-text">Về trang chủ</span>
        </a>
    </div>
</aside>
