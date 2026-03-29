<?php use App\Core\Auth; ?>
<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/admin';
$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$crumbs = ['Admin'];
foreach ($segments as $segment) {
    if (strtolower($segment) === 'admin') {
        continue;
    }
    $crumbs[] = ucwords(str_replace('-', ' ', $segment));
}

$pendingOrders = 0;
try {
    $orderModel = new \App\Models\Order($config);
    $pendingOrders = $orderModel->countByStatus('pending');
} catch (\Throwable $exception) {
    $pendingOrders = 0;
}

$canUseCopilot = Auth::can('backoffice.ai');
$canSearchProductsPage = Auth::can('admin.products.view') || Auth::can('admin.products.manage');
$canManageProducts = Auth::can('admin.products.manage');
$canManageCategories = Auth::can('admin.categories.manage');
$canManageCoupons = Auth::can('admin.coupons.manage');
$canManageSettings = Auth::can('admin.settings.manage');
$canUseSqlManager = Auth::can('admin.sql.manage');
$canViewOrdersPage = Auth::can('admin.orders.view') || Auth::can('admin.orders.manage');
$quickActionAvailable = $canManageProducts || $canManageCategories || $canManageCoupons || $canViewOrdersPage || $canUseSqlManager;
?>
<header class="admin-topbar px-3 px-lg-4">
    <div class="admin-topbar-inner">
        <div class="admin-topbar-left">
            <!-- Added: shared toggle button for desktop collapse and mobile off-canvas sidebar. -->
            <button class="admin-mobile-toggle admin-sidebar-toggle" type="button" data-admin-sidebar-toggle aria-controls="adminSidebar" aria-expanded="true" aria-label="Thu gọn sidebar" title="Thu gọn sidebar">
                <i class="fas fa-bars-staggered"></i>
            </button>

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb admin-breadcrumb mb-0">
                    <?php foreach ($crumbs as $index => $crumb): ?>
                        <li class="breadcrumb-item <?= $index === count($crumbs) - 1 ? 'active' : '' ?>" <?= $index === count($crumbs) - 1 ? 'aria-current="page"' : '' ?>>
                            <?php if ($index === 0): ?>
                                <a href="<?= base_url('admin') ?>"><i class="fas fa-gauge-high me-1"></i><?= e($crumb) ?></a>
                            <?php else: ?>
                                <?= e($crumb) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>

        <div class="admin-topbar-right">
            <?php if ($canSearchProductsPage): ?>
                <form class="admin-search-form" action="<?= base_url('admin/products') ?>" method="get">
                    <input class="form-control" name="q" placeholder="Tìm kiếm sản phẩm, đơn hàng...">
                </form>
            <?php endif; ?>

            <?php if ($canUseCopilot): ?>
                <button class="btn btn-outline-primary border admin-ai-open-btn" type="button" data-admin-ai-open>
                    <i class="fas fa-wand-magic-sparkles me-1"></i>Meow Copilot
                </button>
            <?php endif; ?>

            <?php if ($quickActionAvailable): ?>
                <div class="dropdown">
                    <button class="btn btn-primary admin-quick-btn dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-bolt me-1"></i>Quick actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($canManageProducts): ?>
                            <li><a class="dropdown-item" href="<?= base_url('admin/products/create') ?>"><i class="fas fa-plus me-2"></i>Thêm sản phẩm</a></li>
                        <?php endif; ?>
                        <?php if ($canManageCategories): ?>
                            <li><a class="dropdown-item" href="<?= base_url('admin/categories/create') ?>"><i class="fas fa-folder-plus me-2"></i>Thêm danh mục</a></li>
                        <?php endif; ?>
                        <?php if ($canManageCoupons): ?>
                            <li><a class="dropdown-item" href="<?= base_url('admin/coupons') ?>"><i class="fas fa-ticket me-2"></i>Tạo coupon</a></li>
                        <?php endif; ?>
                        <?php if ($canViewOrdersPage): ?>
                            <li><a class="dropdown-item" href="<?= base_url('admin/orders') ?>"><i class="fas fa-receipt me-2"></i>Xem đơn mới</a></li>
                        <?php endif; ?>
                        <?php if ($canUseSqlManager): ?>
                            <li><a class="dropdown-item" href="<?= base_url('admin/sql-manager') ?>"><i class="fas fa-database me-2"></i>SQL Manager</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= base_url('/') ?>"><i class="fas fa-house me-2"></i>Về trang chủ</a></li>
                    </ul>
                </div>
            <?php endif; ?>

            <a class="btn btn-light border" href="<?= base_url('/') ?>" title="Về trang chủ">
                <i class="fas fa-house"></i>
            </a>

            <button class="btn btn-light border admin-noti-btn" type="button" title="Thông báo">
                <i class="far fa-bell"></i>
                <?php if ($pendingOrders > 0): ?>
                    <span class="admin-noti-dot"><?= min($pendingOrders, 9) ?></span>
                <?php endif; ?>
            </button>

            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <?= e(Auth::user()['full_name'] ?? 'Admin') ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= base_url('profile') ?>"><i class="far fa-user me-2"></i>Hồ sơ</a></li>
                    <?php if ($canManageSettings): ?>
                        <li><a class="dropdown-item" href="<?= base_url('admin/settings') ?>"><i class="fas fa-gear me-2"></i>Cài đặt</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="<?= base_url('logout') ?>" class="px-3 py-1">
                            <?= csrf_field() ?>
                            <button class="btn btn-danger btn-sm w-100" type="submit">Đăng xuất</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
