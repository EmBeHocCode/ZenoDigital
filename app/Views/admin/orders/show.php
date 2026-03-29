<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Chi tiết đơn hàng <?= e($order['order_code']) ?></h1>
    <a href="<?= base_url('admin/orders') ?>" class="btn btn-light">Quay lại</a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6"><strong>Khách hàng:</strong> <?= e($order['full_name']) ?></div>
            <div class="col-md-6"><strong>Email:</strong> <?= e($order['email']) ?></div>
            <div class="col-md-6"><strong>Điện thoại:</strong> <?= e($order['phone']) ?></div>
            <div class="col-md-6"><strong>Địa chỉ:</strong> <?= e($order['address']) ?></div>
            <div class="col-md-6"><strong>Tổng tiền:</strong> <?= format_money((float) $order['total_amount']) ?></div>
            <div class="col-md-6"><strong>Ngày tạo:</strong> <?= e($order['created_at']) ?></div>
        </div>
        <hr>
        <form method="post" action="<?= base_url('admin/orders/update-status/' . $order['id']) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label">Trạng thái</label>
                <select class="form-select" name="status" data-order-status-select data-order-code="<?= e((string) $order['order_code']) ?>">
                    <?php foreach ($statusOptions as $item): ?>
                        <option value="<?= e($item) ?>" <?= $order['status']===$item?'selected':'' ?>><?= e(order_status_label($item)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Cập nhật</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>Sản phẩm</th><th>Đơn giá</th><th>Số lượng</th><th>Thành tiền</th></tr></thead>
            <tbody>
                <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td><?= e($item['product_name']) ?></td>
                        <td><?= format_money((float) $item['unit_price']) ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                        <td><?= format_money((float) $item['total_price']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$order['items']): ?><tr><td colspan="4" class="text-center py-4 text-secondary">Không có item.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
