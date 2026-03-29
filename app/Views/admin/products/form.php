<?php $isEdit = isset($product); ?>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Tên sản phẩm *</label><input class="form-control" name="name" required value="<?= e($product['name'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">Danh mục *</label>
        <select class="form-select" name="category_id" required>
            <option value="">-- Chọn --</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>" <?= (string)($product['category_id'] ?? '') === (string)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">Giá *</label><input type="number" min="0" class="form-control" name="price" required value="<?= e($product['price'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Ảnh đại diện</label><input type="file" class="form-control" name="image" accept="image/*"></div>
    <div class="col-md-6"><label class="form-label">Ảnh gallery (nhiều ảnh)</label><input type="file" class="form-control" name="gallery[]" multiple accept="image/*"></div>
    <div class="col-md-6"><label class="form-label">Trạng thái kho</label><select class="form-select" name="stock_status"><option value="in_stock" <?= ($product['stock_status'] ?? '')==='in_stock'?'selected':'' ?>>Còn hàng</option><option value="out_of_stock" <?= ($product['stock_status'] ?? '')==='out_of_stock'?'selected':'' ?>>Hết hàng</option></select></div>
    <div class="col-md-6"><label class="form-label">Trạng thái hiển thị</label><select class="form-select" name="status"><option value="active" <?= ($product['status'] ?? '')==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= ($product['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option></select></div>
    <div class="col-12"><label class="form-label">Mô tả ngắn</label><textarea class="form-control" name="short_description" rows="2"><?= e($product['short_description'] ?? '') ?></textarea></div>
    <div class="col-12"><label class="form-label">Mô tả chi tiết</label><textarea class="form-control" name="description" rows="4"><?= e($product['description'] ?? '') ?></textarea></div>
    <div class="col-12"><label class="form-label">Thông số / cấu hình</label><textarea class="form-control" name="specs" rows="4"><?= e($product['specs'] ?? '') ?></textarea></div>
</div>
<?php if ($isEdit && !empty($images)): ?>
    <div class="mt-3">
        <p class="fw-semibold mb-2">Ảnh gallery hiện tại:</p>
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($images as $img): ?>
                <img src="<?= base_url('uploads/' . $img['image_path']) ?>" width="100" class="rounded border" alt="Gallery">
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
