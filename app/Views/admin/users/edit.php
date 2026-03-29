<h1 class="h4 fw-bold mb-3">Sửa người dùng</h1>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= base_url('admin/users/update/' . $user['id']) ?>">
            <?= csrf_field() ?>
            <?php require BASE_PATH . '/app/Views/admin/users/form.php'; ?>
            <button class="btn btn-primary">Cập nhật</button>
            <a href="<?= base_url('admin/users') ?>" class="btn btn-light">Quay lại</a>
        </form>
    </div>
</div>
