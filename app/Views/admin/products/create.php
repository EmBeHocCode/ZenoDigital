<h1 class="h4 fw-bold mb-3">Thêm sản phẩm</h1>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" action="<?= base_url('admin/products/store') ?>">
            <?= csrf_field() ?>
            <?php require BASE_PATH . '/app/Views/admin/products/form.php'; ?>
            <button class="btn btn-primary">Lưu sản phẩm</button>
            <a href="<?= base_url('admin/products') ?>" class="btn btn-light">Quay lại</a>
        </form>
    </div>
</div>
