<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Quản lý danh mục</h1>
        <p class="text-secondary mb-0">Quản trị taxonomy sản phẩm và liên kết danh mục bán hàng.</p>
    </div>
    <a href="<?= base_url('admin/categories/create') ?>" class="btn btn-primary"><i class="fas fa-folder-plus me-1"></i>Thêm danh mục</a>
</div>

<div class="admin-card mb-3">
    <div class="admin-card-body">
        <form class="row g-2" method="get">
            <div class="col-md-4"><input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Tìm danh mục..."></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Tìm</button></div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Danh sách danh mục</h2></div>
    <div class="admin-card-body p-0">
        <div class="admin-table-wrap">
            <div class="table-responsive">
                <table class="table admin-table mb-0 align-middle">
                    <thead><tr><th>ID</th><th>Tên</th><th>Slug</th><th>Sản phẩm liên kết</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>#<?= (int) $category['id'] ?></td>
                                <td class="fw-semibold"><?= e((string) $category['name']) ?></td>
                                <td><?= e((string) $category['slug']) ?></td>
                                <td><span class="admin-badge-soft is-info"><?= (int) $category['product_count'] ?> sản phẩm</span></td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="<?= base_url('admin/categories/edit/' . (int) $category['id']) ?>" class="btn btn-sm btn-outline-primary">Sửa</a>
                                        <form method="post" action="<?= base_url('admin/categories/delete/' . (int) $category['id']) ?>" onsubmit="return confirm('Xác nhận xóa danh mục?')">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger">Xóa</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$categories): ?><tr><td colspan="5" class="text-center py-4 text-secondary">Không có dữ liệu.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($meta['last_page'] > 1): ?>
    <nav class="mt-3"><ul class="pagination mb-0">
        <?php for ($i = 1; $i <= $meta['last_page']; $i++): ?>
            <li class="page-item <?= $meta['current_page'] === $i ? 'active' : '' ?>"><a class="page-link" href="<?= base_url('admin/categories?q=' . urlencode($search) . '&page=' . $i) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
<?php endif; ?>
