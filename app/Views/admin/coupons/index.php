<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Coupon Management</h1>
        <p class="text-secondary mb-0">Tạo mã giảm giá, giới hạn lượt dùng và theo dõi trạng thái chiến dịch.</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-4">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h2 class="h6 fw-bold mb-0">Tạo coupon mới</h2>
            </div>
            <div class="admin-card-body">
                <form method="post" action="<?= base_url('admin/coupons/store') ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label">Mã coupon</label>
                        <input class="form-control" name="code" maxlength="80" placeholder="VD: SUMMER2026" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Mô tả</label>
                        <input class="form-control" name="description" maxlength="255" placeholder="Mô tả ngắn chiến dịch">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Giảm giá (%)</label>
                        <input class="form-control" type="number" min="1" max="90" name="discount_percent" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Giới hạn lượt dùng</label>
                        <input class="form-control" type="number" min="0" name="max_uses" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="status">
                            <option value="active">active</option>
                            <option value="inactive">inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hết hạn</label>
                        <input class="form-control" type="datetime-local" name="expires_at">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary w-100">Tạo coupon</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h2 class="h6 fw-bold mb-0">Danh sách coupon</h2>
            </div>
            <div class="admin-card-body">
                <form class="row g-2 mb-3" method="get" action="<?= base_url('admin/coupons') ?>">
                    <div class="col-md-7"><input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Tìm mã coupon hoặc mô tả..."></div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="" <?= $status === '' ? 'selected' : '' ?>>Tất cả trạng thái</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Lọc</button></div>
                </form>

                <div class="admin-table-wrap">
                    <div class="table-responsive">
                        <table class="table admin-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Mã</th>
                                    <th>Giảm</th>
                                    <th>Lượt dùng</th>
                                    <th>Hết hạn</th>
                                    <th>Trạng thái</th>
                                    <th class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($coupons as $item): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) $item['code']) ?></div>
                                        <div class="small text-secondary"><?= e((string) ($item['description'] ?? '')) ?></div>
                                    </td>
                                    <td>-<?= (int) $item['discount_percent'] ?>%</td>
                                    <td><?= (int) $item['used_count'] ?>/<?= (int) $item['max_uses'] ?></td>
                                    <td><?= !empty($item['expires_at']) ? e((string) $item['expires_at']) : 'Không giới hạn' ?></td>
                                    <td>
                                        <span class="admin-badge-soft <?= ((string) $item['status'] === 'active') ? 'is-success' : 'is-muted' ?>">
                                            <?= e((string) $item['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-end gap-1">
                                            <form method="post" action="<?= base_url('admin/coupons/toggle-status/' . (int) $item['id']) ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-secondary">Đổi trạng thái</button>
                                            </form>
                                            <form method="post" action="<?= base_url('admin/coupons/delete/' . (int) $item['id']) ?>" onsubmit="return confirm('Xác nhận xóa coupon?')">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-danger">Xóa</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$coupons): ?>
                                <tr><td colspan="6" class="text-center text-secondary py-4">Chưa có coupon nào.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (($meta['last_page'] ?? 1) > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination mb-0">
                            <?php for ($i = 1; $i <= (int) $meta['last_page']; $i++): ?>
                                <li class="page-item <?= ((int) $meta['current_page'] === $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= base_url('admin/coupons?' . http_build_query(['q' => $search, 'status' => $status, 'page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
