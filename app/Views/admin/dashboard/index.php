<?php
use App\Core\Auth;

$stats = array_merge([
    'products' => 0,
    'users' => 0,
    'orders' => 0,
    'revenue' => 0,
    'pending_orders' => 0,
    'new_users' => 0,
    'today_revenue' => 0,
    'today_orders' => 0,
    'active_coupons' => 0,
    'new_feedback' => 0,
    'expiring_coupons' => 0,
], is_array($stats ?? null) ? $stats : []);

$backofficeScope = is_array($backofficeScope ?? null) ? $backofficeScope : [];
$canViewFinance = !empty($backofficeScope['can_view_finance']);
$canViewUsers = !empty($backofficeScope['can_view_users']);
$canViewProducts = !empty($backofficeScope['can_view_products']);
$canViewOrders = !empty($backofficeScope['can_view_orders']);
$canViewCoupons = !empty($backofficeScope['can_view_coupons']);
$canViewFeedback = !empty($backofficeScope['can_view_feedback']);
$canViewRank = !empty($backofficeScope['can_view_rank']);
$canUseAdminAi = !empty($backofficeScope['can_use_ai_copilot']);
$canOpenOrdersPage = Auth::can('admin.orders.view') || Auth::can('admin.orders.manage');
$canOpenCouponsPage = Auth::can('admin.coupons.view') || Auth::can('admin.coupons.manage');
$roleLabel = (string) ($backofficeScope['label'] ?? 'Backoffice');

$rankOverview = is_array($rankOverview ?? null) ? $rankOverview : ['total_coupons' => 0, 'used_coupons' => 0, 'active_coupons' => 0, 'top_users' => [], 'latest_coupons' => []];
$topProducts = is_array($topProducts ?? null) ? $topProducts : [];
$topCategories = is_array($topCategories ?? null) ? $topCategories : [];
$latestProducts = is_array($latestProducts ?? null) ? $latestProducts : [];
$latestOrders = is_array($latestOrders ?? null) ? $latestOrders : [];
$revenueChart = is_array($revenueChart ?? null) ? $revenueChart : [];
$orderStatusChart = is_array($orderStatusChart ?? null) ? $orderStatusChart : [];
$userGrowthChart = is_array($userGrowthChart ?? null) ? $userGrowthChart : [];
$orderStatusChart = array_map(static function (array $item): array {
    $item['status_label'] = order_status_label((string) ($item['status'] ?? ''));
    return $item;
}, $orderStatusChart);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <h1 class="h4 fw-bold mb-0">Dashboard Control Center</h1>
            <span class="admin-badge-soft <?= $canViewFinance ? 'is-info' : 'is-warning' ?>"><?= e($roleLabel) ?></span>
        </div>
        <p class="text-secondary mb-0">
            <?= $canViewFinance
                ? 'Tổng quan vận hành marketplace theo thời gian thực.'
                : 'Không gian vận hành giới hạn cho staff: ưu tiên đơn hàng, sản phẩm, coupon và feedback.' ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($canUseAdminAi): ?>
            <button class="btn btn-primary" type="button" data-admin-ai-open>
                <i class="fas fa-wand-magic-sparkles me-1"></i>Mở Meow Copilot
            </button>
        <?php endif; ?>
        <?php if ($canOpenOrdersPage): ?>
            <a href="<?= base_url('admin/orders?status=pending') ?>" class="btn btn-outline-secondary"><i class="fas fa-clock me-1"></i>Đơn chờ xử lý</a>
        <?php endif; ?>
        <?php if ($canOpenCouponsPage): ?>
            <a href="<?= base_url('admin/coupons') ?>" class="btn btn-outline-secondary"><i class="fas fa-ticket me-1"></i>Coupon hiện tại</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$canViewFinance || !$canViewUsers): ?>
    <div class="admin-card mb-3">
        <div class="admin-card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="admin-section-title mb-1">Chế độ quyền hiện tại</div>
                <p class="text-secondary mb-0">
                    Bạn đang ở phạm vi backoffice giới hạn. Dashboard và Meow Copilot chỉ hiển thị dữ liệu vận hành được cấp quyền thực từ backend.
                </p>
            </div>
            <?php if ($canUseAdminAi): ?>
                <button class="btn btn-outline-primary" type="button" data-admin-ai-open>
                    Hỏi nhanh Meow về đơn, sản phẩm, coupon hoặc feedback
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="admin-kpi-grid mb-3">
    <article class="admin-kpi-card"><div class="admin-kpi-head"><span class="admin-kpi-label">Tổng sản phẩm</span><i class="fas fa-box admin-kpi-icon"></i></div><p class="admin-kpi-value"><?= (int) $stats['products'] ?></p><p class="admin-kpi-hint">Sản phẩm đang quản lý</p></article>
    <article class="admin-kpi-card"><div class="admin-kpi-head"><span class="admin-kpi-label">Tổng đơn hàng</span><i class="fas fa-receipt admin-kpi-icon"></i></div><p class="admin-kpi-value"><?= (int) $stats['orders'] ?></p><p class="admin-kpi-hint">Đơn phát sinh toàn kỳ</p></article>
    <article class="admin-kpi-card"><div class="admin-kpi-head"><span class="admin-kpi-label">Đơn chờ xử lý</span><i class="fas fa-hourglass-half admin-kpi-icon"></i></div><p class="admin-kpi-value"><?= (int) $stats['pending_orders'] ?></p><p class="admin-kpi-hint">Cần follow-up sớm</p></article>
    <article class="admin-kpi-card"><div class="admin-kpi-head"><span class="admin-kpi-label">Đơn hôm nay</span><i class="fas fa-clock admin-kpi-icon"></i></div><p class="admin-kpi-value"><?= (int) $stats['today_orders'] ?></p><p class="admin-kpi-hint">Phát sinh trong ngày</p></article>
    <article class="admin-kpi-card"><div class="admin-kpi-head"><span class="admin-kpi-label">Coupon hoạt động</span><i class="fas fa-ticket admin-kpi-icon"></i></div><p class="admin-kpi-value"><?= (int) $stats['active_coupons'] ?></p><p class="admin-kpi-hint">Đang còn hiệu lực</p></article>
    <?php if ($canViewCoupons): ?>
        <article class="admin-kpi-card"><div class="admin-kpi-head"><span class="admin-kpi-label">Coupon sắp hết hạn</span><i class="fas fa-hourglass-end admin-kpi-icon"></i></div><p class="admin-kpi-value"><?= (int) $stats['expiring_coupons'] ?></p><p class="admin-kpi-hint">Cần kiểm tra chiến dịch</p></article>
    <?php endif; ?>
    <?php if ($canViewFeedback): ?>
        <article class="admin-kpi-card"><div class="admin-kpi-head"><span class="admin-kpi-label">Feedback mới</span><i class="fas fa-comments admin-kpi-icon"></i></div><p class="admin-kpi-value"><?= (int) $stats['new_feedback'] ?></p><p class="admin-kpi-hint">Phản hồi chưa xử lý</p></article>
    <?php endif; ?>
    <?php if ($canViewUsers): ?>
        <article class="admin-kpi-card"><div class="admin-kpi-head"><span class="admin-kpi-label">Tổng người dùng</span><i class="fas fa-users admin-kpi-icon"></i></div><p class="admin-kpi-value"><?= (int) $stats['users'] ?></p><p class="admin-kpi-hint">Khách hàng hệ thống</p></article>
        <article class="admin-kpi-card"><div class="admin-kpi-head"><span class="admin-kpi-label">Người dùng mới (7 ngày)</span><i class="fas fa-user-plus admin-kpi-icon"></i></div><p class="admin-kpi-value"><?= (int) $stats['new_users'] ?></p><p class="admin-kpi-hint">Xu hướng tăng trưởng user</p></article>
    <?php endif; ?>
    <?php if ($canViewFinance): ?>
        <article class="admin-kpi-card"><div class="admin-kpi-head"><span class="admin-kpi-label">Doanh thu</span><i class="fas fa-wallet admin-kpi-icon"></i></div><p class="admin-kpi-value"><?= format_money((float) $stats['revenue']) ?></p><p class="admin-kpi-hint">Doanh thu tích lũy</p></article>
        <article class="admin-kpi-card"><div class="admin-kpi-head"><span class="admin-kpi-label">Doanh thu hôm nay</span><i class="fas fa-chart-line admin-kpi-icon"></i></div><p class="admin-kpi-value"><?= format_money((float) $stats['today_revenue']) ?></p><p class="admin-kpi-hint">Đơn mới hôm nay: <?= (int) $stats['today_orders'] ?></p></article>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <?php if ($canViewFinance): ?>
        <div class="col-xl-8">
            <div class="admin-card h-100">
                <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Doanh thu theo tháng</h2></div>
                <div class="admin-card-body" style="height:320px;"><canvas id="revenueChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="admin-card h-100">
                <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Đơn hàng theo trạng thái</h2></div>
                <div class="admin-card-body" style="height:320px;"><canvas id="orderStatusChart"></canvas></div>
            </div>
        </div>
    <?php else: ?>
        <div class="col-xl-6">
            <div class="admin-card h-100">
                <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Đơn hàng theo trạng thái</h2></div>
                <div class="admin-card-body" style="height:320px;">
                    <?php if ($canViewOrders && $orderStatusChart): ?>
                        <canvas id="orderStatusChart"></canvas>
                    <?php else: ?>
                        <div class="admin-empty h-100 d-flex align-items-center justify-content-center">Chưa có dữ liệu trạng thái đơn phù hợp với quyền hiện tại.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="admin-card h-100">
                <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Tín hiệu vận hành</h2></div>
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div class="fw-semibold">Đơn chờ xử lý</div>
                        <div class="small text-secondary"><?= (int) $stats['pending_orders'] ?> đơn</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div class="fw-semibold">Feedback mới</div>
                        <div class="small text-secondary"><?= (int) $stats['new_feedback'] ?> phản hồi</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div class="fw-semibold">Coupon sắp hết hạn</div>
                        <div class="small text-secondary"><?= (int) $stats['expiring_coupons'] ?> mã</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($canViewUsers): ?>
    <div class="row g-3 mb-3">
        <div class="col-xl-6">
            <div class="admin-card h-100">
                <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Người dùng mới theo tháng</h2></div>
                <div class="admin-card-body" style="height:280px;"><canvas id="userGrowthChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="admin-card h-100">
                <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Top sản phẩm bán chạy</h2></div>
                <div class="admin-card-body">
                    <?php if ($topProducts): ?>
                        <?php foreach ($topProducts as $item): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <div>
                                    <div class="fw-semibold"><?= e((string) $item['name']) ?></div>
                                    <div class="small text-secondary"><?= e((string) ($item['category_name'] ?? 'N/A')) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?= (int) $item['sold_qty'] ?> bán</div>
                                    <div class="small text-secondary"><?= format_money((float) ($item['sold_revenue'] ?? 0)) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="admin-empty">Chưa có dữ liệu bán hàng cho sản phẩm.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <?php if ($canViewProducts): ?>
        <div class="col-xl-6">
            <div class="admin-card h-100">
                <div class="admin-card-header"><h2 class="h6 fw-bold mb-0"><?= $canViewUsers ? 'Top danh mục' : 'Top sản phẩm bán chạy' ?></h2></div>
                <div class="admin-card-body">
                    <?php if ($canViewUsers && $topCategories): ?>
                        <?php foreach ($topCategories as $item): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <div class="fw-semibold"><?= e((string) $item['name']) ?></div>
                                <div class="small text-secondary"><?= (int) $item['product_total'] ?> sản phẩm · <?= (int) $item['sold_qty'] ?> đã bán</div>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($topProducts): ?>
                        <?php foreach ($topProducts as $item): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <div>
                                    <div class="fw-semibold"><?= e((string) $item['name']) ?></div>
                                    <div class="small text-secondary"><?= e((string) ($item['category_name'] ?? 'N/A')) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?= (int) $item['sold_qty'] ?> bán</div>
                                    <?php if ($canViewFinance && !empty($item['sold_revenue'])): ?>
                                        <div class="small text-secondary"><?= format_money((float) $item['sold_revenue']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="admin-empty"><?= $canViewUsers ? 'Chưa có dữ liệu danh mục.' : 'Chưa có dữ liệu bán hàng cho sản phẩm.' ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="admin-card h-100">
                <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Top danh mục</h2></div>
                <div class="admin-card-body">
                    <?php if ($topCategories): ?>
                        <?php foreach ($topCategories as $item): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <div class="fw-semibold"><?= e((string) $item['name']) ?></div>
                                <div class="small text-secondary"><?= (int) $item['product_total'] ?> sản phẩm · <?= (int) $item['sold_qty'] ?> đã bán</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="admin-empty">Chưa có dữ liệu danh mục.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($canViewProducts): ?>
    <div class="row g-3 mb-3">
        <div class="col-xl-6">
            <div class="admin-card h-100">
                <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Sản phẩm mới</h2></div>
                <div class="admin-card-body">
                    <?php if ($latestProducts): ?>
                        <?php foreach ($latestProducts as $item): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <div class="fw-semibold"><?= e((string) $item['name']) ?></div>
                                <div class="small text-secondary"><?= format_money((float) $item['price']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="admin-empty">Chưa có sản phẩm mới.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="admin-card h-100">
                <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Gợi ý sử dụng Meow Copilot</h2></div>
                <div class="admin-card-body">
                    <div class="d-flex flex-column gap-2">
                        <div class="fw-semibold">Bạn có thể hỏi ngay:</div>
                        <div class="small text-secondary">Xem nhanh đơn chờ xử lý, sản phẩm bán chạy, feedback mới hoặc tình trạng coupon.</div>
                        <?php if ($canUseAdminAi): ?>
                            <button class="btn btn-outline-primary align-self-start" type="button" data-admin-ai-open>
                                Mở Meow Copilot
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($canViewOrders): ?>
    <div class="admin-card mb-3">
        <div class="admin-card-header">
            <h2 class="h6 fw-bold mb-0">Đơn hàng gần đây</h2>
            <?php if ($canOpenOrdersPage): ?>
                <a href="<?= base_url('admin/orders') ?>" class="btn btn-sm btn-outline-secondary">Xem tất cả</a>
            <?php endif; ?>
        </div>
        <div class="admin-card-body">
            <div class="admin-table-wrap">
                <div class="table-responsive">
                    <table class="table admin-table align-middle">
                        <thead><tr><th>Mã đơn</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead>
                        <tbody>
                        <?php foreach ($latestOrders as $order): ?>
                            <?php
                            $status = (string) $order['status'];
                            $badge = match ($status) {
                                'completed', 'paid' => 'is-success',
                                'processing' => 'is-info',
                                'pending' => 'is-warning',
                                default => 'is-muted',
                            };
                            ?>
                            <tr data-order-row data-order-code="<?= e((string) $order['order_code']) ?>">
                                <td><?= e((string) $order['order_code']) ?></td>
                                <td><?= format_money((float) $order['total_amount']) ?></td>
                                <td><span class="admin-badge-soft <?= $badge ?>" data-order-status-badge><?= e(order_status_label($status)) ?></span></td>
                                <td><?= e((string) $order['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$latestOrders): ?><tr><td colspan="4" class="text-center text-secondary py-4">Chưa có đơn hàng gần đây.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($canViewRank): ?>
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="h6 fw-bold mb-0">Quản lý Rank & Coupon</h2>
            <span class="admin-badge-soft is-info">Tự động theo cấp bậc</span>
        </div>
        <div class="admin-card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4"><div class="admin-kpi-card"><div class="admin-kpi-label">Tổng coupon rank</div><p class="admin-kpi-value"><?= (int) ($rankOverview['total_coupons'] ?? 0) ?></p></div></div>
                <div class="col-md-4"><div class="admin-kpi-card"><div class="admin-kpi-label">Coupon còn hiệu lực</div><p class="admin-kpi-value"><?= (int) ($rankOverview['active_coupons'] ?? 0) ?></p></div></div>
                <div class="col-md-4"><div class="admin-kpi-card"><div class="admin-kpi-label">Coupon đã sử dụng</div><p class="admin-kpi-value"><?= (int) ($rankOverview['used_coupons'] ?? 0) ?></p></div></div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <h3 class="admin-section-title">Top khách hàng theo điểm rank</h3>
                    <div class="admin-table-wrap">
                        <div class="table-responsive">
                            <table class="table admin-table table-sm align-middle">
                                <thead><tr><th>Khách hàng</th><th>Điểm</th><th>Tổng mua</th></tr></thead>
                                <tbody>
                                <?php if (!empty($rankOverview['top_users'])): ?>
                                    <?php foreach ($rankOverview['top_users'] as $row): ?>
                                        <tr>
                                            <td><div class="fw-semibold"><?= e((string) $row['full_name']) ?></div><div class="small text-secondary"><?= e((string) $row['email']) ?></div></td>
                                            <td><strong><?= number_format((int) $row['points']) ?></strong></td>
                                            <td><?= format_money((float) $row['total_spent']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-secondary">Chưa có dữ liệu điểm rank.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h3 class="admin-section-title">Coupon rank mới cấp</h3>
                    <div class="admin-table-wrap">
                        <div class="table-responsive">
                            <table class="table admin-table table-sm align-middle">
                                <thead><tr><th>Mã coupon</th><th>Khách hàng</th><th>Ưu đãi</th><th>Trạng thái</th></tr></thead>
                                <tbody>
                                <?php if (!empty($rankOverview['latest_coupons'])): ?>
                                    <?php foreach ($rankOverview['latest_coupons'] as $coupon): ?>
                                        <tr>
                                            <td><code><?= e((string) $coupon['coupon_code']) ?></code></td>
                                            <td><div class="fw-semibold"><?= e((string) $coupon['full_name']) ?></div><div class="small text-secondary"><?= e((string) $coupon['rank_key']) ?></div></td>
                                            <td>-<?= (int) $coupon['discount_percent'] ?>%</td>
                                            <td><span class="admin-badge-soft <?= !empty($coupon['is_used']) ? 'is-muted' : 'is-success' ?>"><?= !empty($coupon['is_used']) ? 'Đã dùng' : 'Sẵn sàng' ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-secondary">Chưa có coupon rank nào được cấp.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
window.__revenueData = <?= json_encode($revenueChart, JSON_UNESCAPED_UNICODE) ?>;
window.__orderStatusData = <?= json_encode($orderStatusChart, JSON_UNESCAPED_UNICODE) ?>;
window.__userGrowthData = <?= json_encode($userGrowthChart, JSON_UNESCAPED_UNICODE) ?>;
</script>
