<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Chi tiết sản phẩm #<?= (int)$product['id'] ?></h1>
    <a href="<?= base_url('admin/products') ?>" class="btn btn-light">Quay lại</a>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-lg-5">
                <img class="img-fluid rounded border" src="<?= product_image_url($product) ?>" alt="<?= e($product['name']) ?>">
            </div>
            <div class="col-lg-7">
                <h2 class="h4 fw-bold"><?= e($product['name']) ?></h2>
                <p><strong>Slug:</strong> <?= e($product['slug']) ?></p>
                <p><strong>Danh mục:</strong> <?= e($product['category_name']) ?></p>
                <p><strong>Giá:</strong> <?= format_money((float) $product['price']) ?></p>
                <p><strong>Trạng thái:</strong> <?= e($product['status']) ?> | <?= e($product['stock_status']) ?></p>
                <p><strong>Mô tả ngắn:</strong><br><?= nl2br(e($product['short_description'])) ?></p>
                <p><strong>Mô tả chi tiết:</strong><br><?= nl2br(e($product['description'])) ?></p>
                <p><strong>Thông số:</strong><br><pre class="spec-box"><?= e($product['specs']) ?></pre></p>
            </div>
        </div>
        <?php if ($images): ?>
            <hr>
            <h6>Gallery</h6>
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($images as $image): ?>
                    <img src="<?= base_url('uploads/' . $image['image_path']) ?>" width="120" class="rounded border" alt="Gallery">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
