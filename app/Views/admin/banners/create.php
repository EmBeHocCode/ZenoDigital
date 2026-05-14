<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Thêm banner trang chủ</h1>
        <p class="text-secondary mb-0">Tạo slide khuyến mãi hiển thị ngay dưới navbar.</p>
    </div>
</div>

<form method="post" action="<?= base_url('admin/banners/store') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php require __DIR__ . '/form.php'; ?>
</form>
