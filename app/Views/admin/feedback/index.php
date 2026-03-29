<?php
$summary = is_array($summary ?? null) ? $summary : [];
$feedbackItems = is_array($feedbackItems ?? null) ? $feedbackItems : [];
$meta = is_array($meta ?? null) ? $meta : ['current_page' => 1, 'last_page' => 1];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'status' => '', 'sentiment' => '', 'source' => '', 'page_type' => ''];
$filterOptions = is_array($filterOptions ?? null) ? $filterOptions : ['sources' => [], 'page_types' => []];

$statusLabels = [
    'new' => ['Mới', 'bg-primary-subtle text-primary'],
    'reviewing' => ['Đang xem', 'bg-warning-subtle text-warning-emphasis'],
    'resolved' => ['Đã xử lý', 'bg-success-subtle text-success-emphasis'],
    'closed' => ['Đã đóng', 'bg-secondary-subtle text-secondary'],
];

$sentimentLabels = [
    'positive' => ['Tích cực', 'bg-success-subtle text-success-emphasis'],
    'neutral' => ['Trung lập', 'bg-info-subtle text-info-emphasis'],
    'negative' => ['Tiêu cực', 'bg-danger-subtle text-danger-emphasis'],
];

$typeLabels = [
    'general' => 'Góp ý chung',
    'product' => 'Sản phẩm',
    'delivery' => 'Bàn giao',
    'payment' => 'Thanh toán',
    'support' => 'Hỗ trợ',
    'system_bug' => 'Lỗi hệ thống',
];

$sourceLabels = [
    'ai_widget' => 'AI widget',
    'storefront_header' => 'Header storefront',
];

$pageTypeLabels = [
    'storefront' => 'Storefront',
    'product' => 'Trang sản phẩm',
    'profile' => 'Hồ sơ',
    'support' => 'Hỗ trợ',
    'other' => 'Khác',
    'storefront_header' => 'Header storefront',
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Feedback khách hàng</h1>
        <p class="text-secondary mb-0">Tổng hợp góp ý sau bán hàng và các phản hồi cần đội ngũ hỗ trợ theo dõi.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="text-secondary small mb-1">Tổng feedback</div>
                <div class="h4 fw-bold mb-0"><?= (int) ($summary['total_feedback'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="text-secondary small mb-1">Feedback mới</div>
                <div class="h4 fw-bold mb-0"><?= (int) ($summary['total_new'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="text-secondary small mb-1">Tiêu cực</div>
                <div class="h4 fw-bold mb-0"><?= (int) ($summary['total_negative'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="text-secondary small mb-1">Cần follow-up</div>
                <div class="h4 fw-bold mb-0"><?= (int) ($summary['total_follow_up'] ?? 0) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h2 class="h6 fw-bold mb-0">Bộ lọc feedback</h2>
    </div>
    <div class="admin-card-body">
        <form method="get" action="<?= base_url('admin/feedback') ?>" class="row g-2">
            <div class="col-xl-4 col-lg-5">
                <input type="text" class="form-control" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Tìm theo mã feedback, người gửi, email hoặc sản phẩm...">
            </div>
            <div class="col-xl-2 col-lg-3">
                <select class="form-select" name="status">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach (['new' => 'Mới', 'reviewing' => 'Đang xem', 'resolved' => 'Đã xử lý', 'closed' => 'Đã đóng'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-lg-2">
                <select class="form-select" name="sentiment">
                    <option value="">Tất cả cảm xúc</option>
                    <?php foreach (['positive' => 'Tích cực', 'neutral' => 'Trung lập', 'negative' => 'Tiêu cực'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($filters['sentiment'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-lg-3">
                <select class="form-select" name="source">
                    <option value="">Tất cả nguồn</option>
                    <?php foreach ((array) ($filterOptions['sources'] ?? []) as $value): ?>
                        <option value="<?= e((string) $value) ?>" <?= ($filters['source'] ?? '') === (string) $value ? 'selected' : '' ?>><?= e((string) ($sourceLabels[(string) $value] ?? $value)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-lg-3">
                <select class="form-select" name="page_type">
                    <option value="">Tất cả ngữ cảnh</option>
                    <?php foreach ((array) ($filterOptions['page_types'] ?? []) as $value): ?>
                        <option value="<?= e((string) $value) ?>" <?= ($filters['page_type'] ?? '') === (string) $value ? 'selected' : '' ?>><?= e((string) ($pageTypeLabels[(string) $value] ?? $value)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-12 col-lg-12 d-grid">
                <button type="submit" class="btn btn-primary">Lọc feedback</button>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="h6 fw-bold mb-0">Danh sách feedback</h2>
    </div>
    <div class="admin-card-body">
        <div class="admin-table-wrap">
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Mã feedback</th>
                            <th>Khách hàng</th>
                            <th>Loại</th>
                            <th>Cảm xúc</th>
                            <th>Nội dung</th>
                            <th>Liên quan</th>
                            <th>Ngày tạo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbackItems as $item): ?>
                            <?php
                            $statusMeta = $statusLabels[(string) ($item['status'] ?? '')] ?? ['N/A', 'bg-light text-dark'];
                            $sentimentMeta = $sentimentLabels[(string) ($item['sentiment'] ?? '')] ?? ['N/A', 'bg-light text-dark'];
                            $customerName = (string) ($item['user_name'] ?? $item['customer_name'] ?? 'Khách vãng lai');
                            $customerEmail = (string) ($item['user_email'] ?? $item['customer_email'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e((string) ($item['feedback_code'] ?? '')) ?></div>
                                    <div class="small text-secondary"><?= e($statusMeta[0]) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($customerName) ?></div>
                                    <div class="small text-secondary"><?= e($customerEmail !== '' ? $customerEmail : 'Không có email liên kết') ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e((string) ($typeLabels[(string) ($item['feedback_type'] ?? '')] ?? 'Khác')) ?></div>
                                    <?php if ((int) ($item['rating'] ?? 0) > 0): ?>
                                        <div class="small text-secondary">Đánh giá: <?= (int) $item['rating'] ?>/5</div>
                                    <?php else: ?>
                                        <div class="small text-secondary">Mức độ: <?= e((string) ucfirst((string) ($item['severity'] ?? 'low'))) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge rounded-pill <?= e($sentimentMeta[1]) ?> mb-2"><?= e($sentimentMeta[0]) ?></span>
                                    <?php if (!empty($item['needs_follow_up'])): ?>
                                        <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis mb-2">Cần follow-up</span>
                                    <?php endif; ?>
                                    <div class="small text-secondary mt-1"><?= nl2br(e((string) ($item['message'] ?? ''))) ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($item['product_name'])): ?>
                                        <div class="fw-semibold"><?= e((string) ($item['product_name'] ?? '')) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['order_code'])): ?>
                                        <div class="small text-secondary"><?= e((string) ($item['order_code'] ?? '')) ?></div>
                                    <?php endif; ?>
                                    <div class="small text-secondary">Nguồn: <?= e((string) ($sourceLabels[(string) ($item['source'] ?? 'ai_widget')] ?? ($item['source'] ?? 'ai_widget'))) ?></div>
                                </td>
                                <td>
                                    <div><?= e((string) ($item['created_at'] ?? '')) ?></div>
                                    <div class="small text-secondary"><?= e((string) ($pageTypeLabels[(string) ($item['page_type'] ?? 'storefront')] ?? ucfirst((string) ($item['page_type'] ?? 'storefront')))) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$feedbackItems): ?>
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">Chưa có feedback nào được ghi nhận.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ((int) ($meta['last_page'] ?? 1) > 1): ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <div class="small text-secondary">
                    Trang <?= (int) ($meta['current_page'] ?? 1) ?> / <?= (int) ($meta['last_page'] ?? 1) ?>
                </div>
                <div class="btn-group" role="group" aria-label="Điều hướng feedback">
                    <?php
                    $currentPage = (int) ($meta['current_page'] ?? 1);
                    $lastPage = (int) ($meta['last_page'] ?? 1);
                    $prevQuery = http_build_query(array_filter([
                        'q' => (string) ($filters['q'] ?? ''),
                        'status' => (string) ($filters['status'] ?? ''),
                        'sentiment' => (string) ($filters['sentiment'] ?? ''),
                        'source' => (string) ($filters['source'] ?? ''),
                        'page_type' => (string) ($filters['page_type'] ?? ''),
                        'page' => max(1, $currentPage - 1),
                    ], static fn($value) => $value !== ''));
                    $nextQuery = http_build_query(array_filter([
                        'q' => (string) ($filters['q'] ?? ''),
                        'status' => (string) ($filters['status'] ?? ''),
                        'sentiment' => (string) ($filters['sentiment'] ?? ''),
                        'source' => (string) ($filters['source'] ?? ''),
                        'page_type' => (string) ($filters['page_type'] ?? ''),
                        'page' => min($lastPage, $currentPage + 1),
                    ], static fn($value) => $value !== ''));
                    ?>
                    <a class="btn btn-outline-secondary <?= $currentPage <= 1 ? 'disabled' : '' ?>" href="<?= e(base_url('admin/feedback' . ($prevQuery !== '' ? '?' . $prevQuery : ''))) ?>">Trước</a>
                    <a class="btn btn-outline-secondary <?= $currentPage >= $lastPage ? 'disabled' : '' ?>" href="<?= e(base_url('admin/feedback' . ($nextQuery !== '' ? '?' . $nextQuery : ''))) ?>">Sau</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
