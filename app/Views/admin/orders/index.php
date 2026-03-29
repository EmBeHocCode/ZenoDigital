<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Quản lý đơn hàng</h1>
        <p class="text-secondary mb-0">Theo dõi trạng thái xử lý và thông tin khách hàng theo từng đơn.</p>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="admin-card-body">
        <form class="row g-2" method="get">
            <div class="col-md-4"><input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Tìm theo mã đơn/người mua..."></div>
            <div class="col-md-3"><select class="form-select" name="status"><option value="">Tất cả trạng thái</option><?php foreach ($statusOptions as $item): ?><option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e(order_status_label($item)) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Lọc</button></div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Danh sách đơn hàng</h2></div>
    <div class="admin-card-body p-0">
        <div class="admin-table-wrap">
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Email</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày tạo</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $orderStatus = (string) $order['status'];
                            $badge = match ($orderStatus) {
                                'completed', 'paid' => 'is-success',
                                'processing' => 'is-info',
                                'pending' => 'is-warning',
                                default => 'is-muted',
                            };
                            ?>
                            <tr data-order-row data-order-code="<?= e((string) $order['order_code']) ?>">
                                <td class="fw-semibold"><?= e((string) $order['order_code']) ?></td>
                                <td><?= e((string) $order['full_name']) ?></td>
                                <td><?= e((string) $order['email']) ?></td>
                                <td><?= format_money((float) $order['total_amount']) ?></td>
                                <td><span class="admin-badge-soft <?= $badge ?>" data-order-status-badge><?= e(order_status_label($orderStatus)) ?></span></td>
                                <td><?= e((string) $order['created_at']) ?></td>
                                <td>
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="<?= base_url('admin/orders/show/' . (int) $order['id']) ?>" class="btn btn-sm btn-outline-primary">Chi tiết</a>
                                        <form method="post" action="<?= base_url('admin/orders/delete/' . (int) $order['id']) ?>" onsubmit="return confirm('Xác nhận xóa đơn hàng?')">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger">Xóa</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$orders): ?><tr><td colspan="7" class="text-center py-4 text-secondary">Không có dữ liệu.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($meta['last_page'] > 1): ?>
    <nav class="mt-3"><ul class="pagination mb-0">
        <?php for ($i = 1; $i <= $meta['last_page']; $i++): ?>
            <li class="page-item <?= $meta['current_page'] === $i ? 'active' : '' ?>"><a class="page-link" href="<?= base_url('admin/orders?' . http_build_query(['q' => $search, 'status' => $status, 'page' => $i])) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
<?php endif; ?>
