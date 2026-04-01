<?php
$summary = is_array($summary ?? null) ? $summary : [];
$transactions = is_array($transactions ?? null) ? $transactions : [];
$meta = is_array($meta ?? null) ? $meta : ['current_page' => 1, 'last_page' => 1];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'type' => '', 'status' => ''];
$orderSnapshot = is_array($orderSnapshot ?? null) ? $orderSnapshot : [];

$typeLabels = [
    'deposit' => 'Nạp ví',
    'spend' => 'Thanh toán',
    'refund' => 'Hoàn tiền',
    'adjustment' => 'Điều chỉnh',
];

$statusLabels = [
    'completed' => ['Hoàn tất', 'bg-success-subtle text-success-emphasis'],
    'pending' => ['Đang chờ', 'bg-warning-subtle text-warning-emphasis'],
    'failed' => ['Thất bại', 'bg-danger-subtle text-danger-emphasis'],
];

$directionLabels = [
    'credit' => ['Tiền vào', 'text-success'],
    'debit' => ['Tiền ra', 'text-danger'],
];

$paymentMethodLabels = [
    'bank_transfer' => 'QR ngân hàng (SePay)',
    'wallet' => 'Ví nội bộ',
    'manual' => 'Thủ công',
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Quản lý thanh toán</h1>
        <p class="text-secondary mb-0">Theo dõi nạp ví, thanh toán đơn hàng và biến động giao dịch của khách hàng trên toàn shop.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="text-secondary small mb-1">Tổng giao dịch</div>
                <div class="h4 fw-bold mb-0"><?= (int) ($summary['total_transactions'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="text-secondary small mb-1">Tổng nạp thành công</div>
                <div class="h4 fw-bold mb-0"><?= format_money((float) ($summary['total_deposit'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="text-secondary small mb-1">Đã thanh toán</div>
                <div class="h4 fw-bold mb-0"><?= format_money((float) ($summary['total_debit'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="admin-card h-100">
            <div class="admin-card-body">
                <div class="text-secondary small mb-1">Giao dịch chờ xử lý</div>
                <div class="h4 fw-bold mb-0"><?= (int) ($summary['pending_transactions'] ?? 0) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h2 class="h6 fw-bold mb-0">Bộ lọc giao dịch</h2>
            </div>
            <div class="admin-card-body">
                <form method="get" action="<?= base_url('admin/payments') ?>" class="row g-2">
                    <div class="col-lg-6">
                        <input type="text" class="form-control" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Tìm theo mã giao dịch, khách hàng, email hoặc mô tả...">
                    </div>
                    <div class="col-lg-3">
                        <select class="form-select" name="type">
                            <option value="">Tất cả loại giao dịch</option>
                            <?php foreach ($typeLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($filters['type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <select class="form-select" name="status">
                            <option value="">Tất cả trạng thái</option>
                            <?php foreach ($statusLabels as $value => $metaLabel): ?>
                                <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e((string) $metaLabel[0]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 d-grid d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary px-4">Lọc giao dịch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h2 class="h6 fw-bold mb-0">Snapshot thanh toán đơn hàng</h2>
            </div>
            <div class="admin-card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-secondary mb-1">Doanh thu hôm nay</div>
                            <div class="fw-semibold"><?= format_money((float) ($orderSnapshot['today_revenue'] ?? 0)) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-secondary mb-1">Đơn hôm nay</div>
                            <div class="fw-semibold"><?= (int) ($orderSnapshot['today_orders'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-secondary mb-1">Pending</div>
                            <div class="fw-semibold"><?= (int) ($orderSnapshot['pending_orders'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-secondary mb-1">Processing</div>
                            <div class="fw-semibold"><?= (int) ($orderSnapshot['processing_orders'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-secondary mb-1">Paid</div>
                            <div class="fw-semibold"><?= (int) ($orderSnapshot['paid_orders'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-secondary mb-1">Completed</div>
                            <div class="fw-semibold"><?= (int) ($orderSnapshot['completed_orders'] ?? 0) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h2 class="h6 fw-bold mb-0">Lịch sử giao dịch khách hàng</h2>
    </div>
    <div class="admin-card-body p-0">
        <div class="admin-table-wrap">
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Mã giao dịch</th>
                            <th>Khách hàng</th>
                            <th>Loại</th>
                            <th>Thanh toán</th>
                            <th>Số tiền</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <?php
                            $statusMeta = $statusLabels[(string) ($transaction['status'] ?? '')] ?? ['N/A', 'bg-light text-dark'];
                            $directionMeta = $directionLabels[(string) ($transaction['direction'] ?? '')] ?? ['Không rõ', 'text-body'];
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e((string) ($transaction['transaction_code'] ?? '')) ?></div>
                                    <div class="small text-secondary"><?= e((string) ($transaction['description'] ?? 'Không có mô tả')) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e((string) ($transaction['full_name'] ?? 'Khách không xác định')) ?></div>
                                    <div class="small text-secondary"><?= e((string) ($transaction['email'] ?? '')) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e((string) ($typeLabels[(string) ($transaction['transaction_type'] ?? '')] ?? ucfirst((string) ($transaction['transaction_type'] ?? 'Khác')))) ?></div>
                                    <div class="small <?= e((string) $directionMeta[1]) ?>"><?= e((string) $directionMeta[0]) ?></div>
                                </td>
                                <td>
                                    <?php $paymentMethod = (string) ($transaction['payment_method'] ?? 'manual'); ?>
                                    <div class="fw-semibold"><?= e((string) ($paymentMethodLabels[$paymentMethod] ?? ucwords(str_replace('_', ' ', $paymentMethod)))) ?></div>
                                    <div class="small text-secondary">Số dư: <?= format_money((float) ($transaction['balance_after'] ?? 0)) ?></div>
                                </td>
                                <td><?= format_money((float) ($transaction['amount'] ?? 0)) ?></td>
                                <td><span class="badge rounded-pill <?= e((string) $statusMeta[1]) ?>"><?= e((string) $statusMeta[0]) ?></span></td>
                                <td><?= e((string) ($transaction['created_at'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$transactions): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-secondary">Chưa có giao dịch thanh toán nào khớp bộ lọc hiện tại.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ((int) ($meta['last_page'] ?? 1) > 1): ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-top">
            <div class="small text-secondary">Trang <?= (int) ($meta['current_page'] ?? 1) ?> / <?= (int) ($meta['last_page'] ?? 1) ?></div>
            <div class="btn-group" role="group" aria-label="Điều hướng giao dịch">
                <?php
                $currentPage = (int) ($meta['current_page'] ?? 1);
                $lastPage = (int) ($meta['last_page'] ?? 1);
                $prevQuery = http_build_query(array_filter([
                    'q' => (string) ($filters['q'] ?? ''),
                    'type' => (string) ($filters['type'] ?? ''),
                    'status' => (string) ($filters['status'] ?? ''),
                    'page' => max(1, $currentPage - 1),
                ], static fn($value) => $value !== ''));
                $nextQuery = http_build_query(array_filter([
                    'q' => (string) ($filters['q'] ?? ''),
                    'type' => (string) ($filters['type'] ?? ''),
                    'status' => (string) ($filters['status'] ?? ''),
                    'page' => min($lastPage, $currentPage + 1),
                ], static fn($value) => $value !== ''));
                ?>
                <a class="btn btn-outline-secondary <?= $currentPage <= 1 ? 'disabled' : '' ?>" href="<?= e(base_url('admin/payments' . ($prevQuery !== '' ? '?' . $prevQuery : ''))) ?>">Trước</a>
                <a class="btn btn-outline-secondary <?= $currentPage >= $lastPage ? 'disabled' : '' ?>" href="<?= e(base_url('admin/payments' . ($nextQuery !== '' ? '?' . $nextQuery : ''))) ?>">Sau</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="h6 fw-bold mb-0">Đơn hàng mới nhất liên quan thanh toán</h2>
    </div>
    <div class="admin-card-body p-0">
        <div class="admin-table-wrap">
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ((array) ($orderSnapshot['latest_orders'] ?? []) as $order): ?>
                            <tr>
                                <td class="fw-semibold"><?= e((string) ($order['order_code'] ?? '')) ?></td>
                                <td><?= format_money((float) ($order['total_amount'] ?? 0)) ?></td>
                                <td><?= e(order_status_label((string) ($order['status'] ?? 'pending'))) ?></td>
                                <td><?= e((string) ($order['created_at'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($orderSnapshot['latest_orders'])): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-secondary">Chưa có đơn hàng nào để hiển thị.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
