<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Sửa banner trang chủ</h1>
        <p class="text-secondary mb-0">Cập nhật nội dung, link đích, trạng thái và thứ tự slide.</p>
    </div>
</div>

<form method="post" action="<?= base_url('admin/banners/update/' . (int) $banner['id']) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php require __DIR__ . '/form.php'; ?>
</form>
