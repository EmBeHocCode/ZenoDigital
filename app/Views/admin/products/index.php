<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Quản lý sản phẩm</h1>
        <p class="text-secondary mb-0">Quản trị catalog, trạng thái hiển thị và giá bán sản phẩm.</p>
    </div>
    <a href="<?= base_url('admin/products/create') ?>" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Thêm sản phẩm</a>
</div>

<div class="admin-card mb-3">
    <div class="admin-card-body">
        <form class="row g-2" method="get">
            <div class="col-md-3"><input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="Tìm kiếm..."></div>
            <div class="col-md-2">
                <select class="form-select" name="category_id">
                    <option value="">Tất cả danh mục</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= (string) $filters['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e((string) $category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input class="form-control" type="number" name="min_price" value="<?= e($filters['min_price']) ?>" placeholder="Giá từ"></div>
            <div class="col-md-2"><input class="form-control" type="number" name="max_price" value="<?= e($filters['max_price']) ?>" placeholder="Giá đến"></div>
            <div class="col-md-2"><select class="form-select" name="sort"><option value="latest">Mới nhất</option><option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Giá tăng</option><option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Giá giảm</option></select></div>
            <div class="col-md-1"><button class="btn btn-outline-secondary w-100">Lọc</button></div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="h6 fw-bold mb-0">Danh sách sản phẩm</h2>
        <span class="text-secondary small">Đã chọn: <strong data-bulk-count>0</strong></span>
    </div>
    <div class="admin-card-body p-0">
        <div class="admin-table-wrap">
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead><tr><th style="width:44px;"><input type="checkbox" disabled></th><th>ID</th><th>Ảnh</th><th>Tên</th><th>Danh mục</th><th>Giá</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><input type="checkbox" data-bulk-checkbox></td>
                            <td>#<?= (int) $product['id'] ?></td>
                            <td><img src="<?= product_image_url($product) ?>" width="70" class="rounded" alt="product"></td>
                            <td class="fw-semibold"><?= e((string) $product['name']) ?></td>
                            <td><?= e((string) $product['category_name']) ?></td>
                            <td><?= format_money((float) $product['price']) ?></td>
                            <td><span class="admin-badge-soft <?= ((string) $product['status'] === 'active') ? 'is-success' : 'is-muted' ?>"><?= e((string) $product['status']) ?></span></td>
                            <td>
                                <div class="d-flex justify-content-end gap-1">
                                    <a class="btn btn-sm btn-outline-info" href="<?= base_url('admin/products/show/' . (int) $product['id']) ?>">Xem</a>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('admin/products/edit/' . (int) $product['id']) ?>">Sửa</a>
                                    <form method="post" action="<?= base_url('admin/products/delete/' . (int) $product['id']) ?>" onsubmit="return confirm('Xác nhận xóa sản phẩm?')">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger">Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$products): ?><tr><td colspan="8" class="text-center py-4 text-secondary">Không có dữ liệu.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($meta['last_page'] > 1): ?>
    <nav class="mt-3"><ul class="pagination mb-0">
        <?php for ($i = 1; $i <= $meta['last_page']; $i++): ?>
            <li class="page-item <?= $meta['current_page'] === $i ? 'active' : '' ?>"><a class="page-link" href="<?= base_url('admin/products?' . http_build_query(array_merge($filters, ['page' => $i]))) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
<?php endif; ?>
