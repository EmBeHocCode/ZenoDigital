<?php
$banner = is_array($banner ?? null) ? $banner : [];
$isEdit = !empty($banner['id']);
$currentImage = trim((string) ($banner['image_path'] ?? ''));
$currentImageUrl = $currentImage !== '' ? base_url('assets/images/slides/' . rawurlencode($currentImage)) : '';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h2 class="h6 fw-bold mb-0">Nội dung banner</h2>
            </div>
            <div class="admin-card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Tiêu đề *</label>
                        <input class="form-control" name="title" maxlength="180" required value="<?= e((string) ($banner['title'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Mô tả ngắn</label>
                        <textarea class="form-control" name="subtitle" rows="3" maxlength="500"><?= e((string) ($banner['subtitle'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nút CTA / link_label</label>
                        <input class="form-control" name="link_label" maxlength="80" value="<?= e((string) ($banner['link_label'] ?? '')) ?>" placeholder="Xem gói ngay">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Link đích / link_url</label>
                        <input class="form-control" name="link_url" maxlength="500" value="<?= e((string) ($banner['link_url'] ?? '')) ?>" placeholder="products?category_id=1 hoặc #cloud-vps-plans">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Thứ tự hiển thị</label>
                        <input type="number" min="0" max="999999" class="form-control" name="display_order" value="<?= e((string) ($banner['display_order'] ?? 0)) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Trạng thái</label>
                        <?php $status = (string) ($banner['status'] ?? 'active'); ?>
                        <select class="form-select" name="status">
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card">
            <div class="admin-card-header">
                <h2 class="h6 fw-bold mb-0">Ảnh slide</h2>
            </div>
            <div class="admin-card-body">
                <?php if ($currentImageUrl !== ''): ?>
                    <img class="banner-admin-preview mb-3" src="<?= e($currentImageUrl) ?>" alt="<?= e((string) ($banner['title'] ?? 'Banner')) ?>">
                <?php endif; ?>

                <label class="form-label">Upload ảnh mới</label>
                <input type="file" class="form-control" name="image_file" accept="image/*">
                <div class="form-text">Ảnh sẽ lưu vào <code>public/assets/images/slides</code>.</div>

                <div class="mt-3">
                    <label class="form-label">Hoặc dùng file đã có</label>
                    <input class="form-control" name="image_path" value="<?= e($currentImage) ?>" placeholder="slide1.png">
                    <div class="form-text">Chỉ lưu tên file, không lưu đường dẫn tuyệt đối Windows.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-3 d-flex flex-wrap gap-2">
    <button class="btn btn-primary"><?= $isEdit ? 'Cập nhật banner' : 'Lưu banner' ?></button>
    <a href="<?= base_url('admin/banners') ?>" class="btn btn-light">Quay lại</a>
</div>
