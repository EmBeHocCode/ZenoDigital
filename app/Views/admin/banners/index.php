<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Quản lý hero banner</h1>
        <p class="text-secondary mb-0">Sắp xếp các slide khuyến mãi hiển thị ngay dưới navbar trang chủ.</p>
    </div>
    <a href="<?= base_url('admin/banners/create') ?>" class="btn btn-primary"><i class="fas fa-image me-1"></i>Thêm banner</a>
</div>

<div class="admin-card mb-3">
    <div class="admin-card-body">
        <form class="row g-2" method="get">
            <div class="col-md-4">
                <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Tìm theo tiêu đề hoặc link...">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100">Tìm</button>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="h6 fw-bold mb-0">Danh sách banner</h2>
        <span class="text-secondary small">Active banner sẽ lên slider trang chủ.</span>
    </div>
    <div class="admin-card-body p-0">
        <div class="admin-table-wrap">
            <div class="table-responsive">
                <table class="table admin-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ảnh</th>
                            <th>Nội dung</th>
                            <th>Link đích</th>
                            <th>Thứ tự</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($banners as $banner): ?>
                        <?php
                        $status = (string) ($banner['status'] ?? 'inactive');
                        $image = (string) ($banner['image_path'] ?? '');
                        ?>
                        <tr>
                            <td>#<?= (int) $banner['id'] ?></td>
                            <td>
                                <?php if ($image !== ''): ?>
                                    <img class="banner-admin-thumb" src="<?= e(base_url('assets/images/slides/' . rawurlencode($image))) ?>" alt="<?= e((string) $banner['title']) ?>">
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e((string) $banner['title']) ?></div>
                                <div class="text-secondary small"><?= e((string) ($banner['subtitle'] ?? '')) ?></div>
                                <?php if (!empty($banner['link_label'])): ?>
                                    <span class="admin-badge-soft is-info mt-1"><?= e((string) $banner['link_label']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e((string) ($banner['link_url'] ?? '')) ?></code></td>
                            <td><?= (int) ($banner['display_order'] ?? 0) ?></td>
                            <td>
                                <span class="admin-badge-soft <?= $status === 'active' ? 'is-success' : 'is-muted' ?>"><?= e($status) ?></span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-end gap-1 flex-wrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('admin/banners/edit/' . (int) $banner['id']) ?>">Sửa</a>
                                    <form method="post" action="<?= base_url('admin/banners/toggle-status/' . (int) $banner['id']) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-secondary"><?= $status === 'active' ? 'Tắt' : 'Bật' ?></button>
                                    </form>
                                    <form method="post" action="<?= base_url('admin/banners/delete/' . (int) $banner['id']) ?>" onsubmit="return confirm('Xác nhận xóa banner này?')">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger">Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$banners): ?>
                        <tr><td colspan="7" class="text-center py-4 text-secondary">Chưa có banner.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($meta['last_page'] > 1): ?>
    <nav class="mt-3">
        <ul class="pagination mb-0">
            <?php for ($i = 1; $i <= $meta['last_page']; $i++): ?>
                <li class="page-item <?= $meta['current_page'] === $i ? 'active' : '' ?>">
                    <a class="page-link" href="<?= base_url('admin/banners?' . http_build_query(['q' => $search, 'page' => $i])) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>
