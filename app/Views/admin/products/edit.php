<h1 class="h4 fw-bold mb-3">Sửa sản phẩm</h1>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" action="<?= base_url('admin/products/update/' . $product['id']) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="current_image" value="<?= e($product['image']) ?>">
            <?php require BASE_PATH . '/app/Views/admin/products/form.php'; ?>
            <button class="btn btn-primary">Cập nhật</button>
            <a href="<?= base_url('admin/products') ?>" class="btn btn-light">Quay lại</a>
        </form>
    </div>
</div>
