<h1 class="h4 fw-bold mb-3">Sửa danh mục</h1>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= base_url('admin/categories/update/' . $category['id']) ?>">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Tên danh mục</label><input name="name" class="form-control" value="<?= e($category['name']) ?>" required></div>
            <div class="mb-3"><label class="form-label">Mô tả ngắn</label><textarea name="description" class="form-control" rows="4"><?= e($category['description']) ?></textarea></div>
            <button class="btn btn-primary">Cập nhật</button>
            <a href="<?= base_url('admin/categories') ?>" class="btn btn-light">Quay lại</a>
        </form>
    </div>
</div>
