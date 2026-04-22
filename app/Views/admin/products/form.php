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
    <div class="col-md-4">
        <label class="form-label">Loại sản phẩm (AI)</label>
        <select class="form-select" name="product_type">
            <?php $productType = (string) ($product['product_type'] ?? 'service'); ?>
            <option value="service" <?= $productType === 'service' ? 'selected' : '' ?>>service</option>
            <option value="capacity" <?= $productType === 'capacity' ? 'selected' : '' ?>>capacity</option>
            <option value="wallet" <?= $productType === 'wallet' ? 'selected' : '' ?>>wallet</option>
            <option value="digital_code" <?= $productType === 'digital_code' ? 'selected' : '' ?>>digital_code</option>
        </select>
    </div>
    <div class="col-md-4"><label class="form-label">Tồn kho (`stock_qty`)</label><input type="number" min="0" class="form-control" name="stock_qty" value="<?= e($product['stock_qty'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Ngưỡng nhập lại (`reorder_point`)</label><input type="number" min="0" class="form-control" name="reorder_point" value="<?= e($product['reorder_point'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Nhà cung cấp (`supplier_name`)</label><input class="form-control" name="supplier_name" maxlength="160" value="<?= e($product['supplier_name'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Lead time (`lead_time_days`)</label><input type="number" min="0" class="form-control" name="lead_time_days" value="<?= e($product['lead_time_days'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Giới hạn capacity (`capacity_limit`)</label><input type="number" min="0" class="form-control" name="capacity_limit" value="<?= e($product['capacity_limit'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Capacity đã dùng (`capacity_used`)</label><input type="number" min="0" class="form-control" name="capacity_used" value="<?= e($product['capacity_used'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Giá vốn (`cost_price`)</label><input type="number" min="0" step="0.01" class="form-control" name="cost_price" value="<?= e($product['cost_price'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Min margin (%)</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="min_margin_percent" value="<?= e($product['min_margin_percent'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Phí nền tảng (%)</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="platform_fee_percent" value="<?= e($product['platform_fee_percent'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Phí thanh toán (%)</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="payment_fee_percent" value="<?= e($product['payment_fee_percent'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Chi phí ads/đơn (`ads_cost_per_order`)</label><input type="number" min="0" step="0.01" class="form-control" name="ads_cost_per_order" value="<?= e($product['ads_cost_per_order'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Chi phí giao hàng (`delivery_cost`)</label><input type="number" min="0" step="0.01" class="form-control" name="delivery_cost" value="<?= e($product['delivery_cost'] ?? '') ?>"></div>
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
