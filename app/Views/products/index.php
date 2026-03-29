<?php
$trustBadges = [
    ['icon' => 'fas fa-shield-halved', 'text' => 'Anti DDoS'],
    ['icon' => 'fas fa-clock', 'text' => 'Uptime 99.9%'],
    ['icon' => 'fas fa-hard-drive', 'text' => 'SSD NVMe'],
    ['icon' => 'fas fa-life-ring', 'text' => 'Hỗ trợ 24/7'],
];
$planBadges = ['Best Seller', 'Phổ biến', 'Giá tốt', 'Hiệu năng cao'];
$specIconMap = [
    'vCPU' => 'fas fa-microchip',
    'RAM' => 'fas fa-memory',
    'Disk' => 'fas fa-hdd',
    'Băng thông' => 'fas fa-network-wired',
    'IP' => 'fas fa-globe',
    'Location' => 'fas fa-location-dot',
];

$cloudCategories = is_array($cloudCategories ?? null) ? $cloudCategories : [];
$secondaryCategories = is_array($secondaryCategories ?? null) ? $secondaryCategories : [];
$selectedCategory = is_array($selectedCategory ?? null) ? $selectedCategory : null;
$isCloudCatalog = !empty($isCloudCatalog);
$selectedCategoryId = (int) ($selectedCategory['id'] ?? 0);
$cloudCatalogQuery = [];
if ($cloudCategories !== []) {
    $cloudCatalogQuery['category_id'] = (int) ($cloudCategories[0]['id'] ?? 0);
}
$cloudCatalogUrl = base_url('products' . ($cloudCatalogQuery ? '?' . http_build_query($cloudCatalogQuery) : ''));
$catalogTitle = $isCloudCatalog
    ? (string) ($selectedCategory['name'] ?? 'Cloud VPS & Cloud Server')
    : (string) ($selectedCategory['name'] ?? 'Danh mục dịch vụ số');
$catalogSubtitle = $isCloudCatalog
    ? 'Danh mục cloud tập trung cho website, API, SaaS, automation và workload cần hạ tầng ổn định.'
    : 'Nhóm dịch vụ này vẫn tồn tại trong hệ thống, nhưng storefront hiện ưu tiên trải nghiệm cloud-first ở homepage và catalog chính.';
$catalogKicker = $isCloudCatalog ? 'Cloud Hosting Premium' : 'Digital Service Catalog';

if (!function_exists('vps_specs_extract')) {
    function vps_specs_extract(string $specs): array
    {
        $defaults = [
            'vCPU' => '2 vCore',
            'RAM' => '4 GB',
            'Disk' => '80 GB NVMe',
            'Băng thông' => '2 TB',
            'IP' => '1 IPv4',
            'Location' => 'VN / SG',
        ];

        $lines = preg_split('/\r\n|\r|\n/', trim($specs));
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                if (stripos($k, 'cpu') !== false) {
                    $defaults['vCPU'] = $v;
                }
                if (stripos($k, 'ram') !== false) {
                    $defaults['RAM'] = $v;
                }
                if (stripos($k, 'ssd') !== false || stripos($k, 'nvme') !== false || stripos($k, 'storage') !== false) {
                    $defaults['Disk'] = $v;
                }
                if (stripos($k, 'bandwidth') !== false) {
                    $defaults['Băng thông'] = $v;
                }
                if (stripos($k, 'ip') !== false) {
                    $defaults['IP'] = $v;
                }
                if (stripos($k, 'region') !== false || stripos($k, 'location') !== false) {
                    $defaults['Location'] = $v;
                }
            }
        }

        return $defaults;
    }
}

if (!function_exists('catalog_generic_facts')) {
    function catalog_generic_facts(string $specs, string $description): array
    {
        $facts = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($specs));

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $facts[] = $line;
            }
            if (count($facts) >= 3) {
                break;
            }
        }

        if ($facts === [] && trim($description) !== '') {
            $facts[] = trim($description);
        }

        return $facts;
    }
}
?>

<section class="vps-hero py-4 py-lg-5">
    <div class="container">
        <div class="vps-hero-card">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <span class="vps-kicker"><?= e($catalogKicker) ?></span>
                    <h1 class="vps-title mb-2"><i class="fas fa-server me-2"></i><?= e($catalogTitle) ?></h1>
                    <p class="vps-subtitle mb-3"><?= e($catalogSubtitle) ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($isCloudCatalog): ?>
                            <?php foreach ($trustBadges as $badge): ?>
                                <span class="vps-trust-pill"><i class="<?= e($badge['icon']) ?> me-2"></i><?= e($badge['text']) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="vps-trust-pill"><i class="fas fa-layer-group me-2"></i>Dịch vụ vẫn đang hoạt động</span>
                            <span class="vps-trust-pill"><i class="fas fa-arrow-turn-up me-2"></i>Cloud vẫn là danh mục ưu tiên</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <img class="vps-hero-icon" src="<?= base_url('assets/images/vps.svg') ?>" alt="Catalog Icon">
                </div>
            </div>
        </div>
    </div>
</section>

<section class="pb-3">
    <div class="container">
        <div class="catalog-category-ribbon">
            <div class="catalog-category-group">
                <span class="catalog-category-label">Cloud chính</span>
                <div class="catalog-category-links">
                    <a class="catalog-category-chip <?= $selectedCategoryId === 0 || ($cloudCategories !== [] && $selectedCategoryId === (int) ($cloudCategories[0]['id'] ?? 0)) ? 'is-active' : '' ?>" href="<?= e($cloudCatalogUrl) ?>">
                        Cloud catalog
                    </a>
                    <?php foreach ($cloudCategories as $category): ?>
                        <a class="catalog-category-chip <?= $selectedCategoryId === (int) $category['id'] ? 'is-active' : '' ?>" href="<?= base_url('products?' . http_build_query(['category_id' => (int) $category['id']])) ?>">
                            <?= e((string) $category['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($secondaryCategories !== []): ?>
                <div class="catalog-category-group is-secondary">
                    <span class="catalog-category-label">Nhóm phụ</span>
                    <div class="catalog-category-links">
                        <?php foreach (array_slice($secondaryCategories, 0, 6) as $category): ?>
                            <a class="catalog-category-chip <?= $selectedCategoryId === (int) $category['id'] ? 'is-active' : '' ?>" href="<?= base_url('products?' . http_build_query(['category_id' => (int) $category['id']])) ?>">
                                <?= e((string) $category['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($isCloudCatalog): ?>
    <section class="pb-3">
        <div class="container">
            <form class="vps-filter-wrap" method="get" action="<?= base_url('products') ?>">
                <?php if ($selectedCategoryId > 0): ?>
                    <input type="hidden" name="category_id" value="<?= $selectedCategoryId ?>">
                <?php endif; ?>
                <div class="row g-2 g-lg-3">
                    <div class="col-12 col-lg-3">
                        <div class="vps-input-group">
                            <i class="fas fa-magnifying-glass"></i>
                            <input class="form-control vps-filter-input" name="q" value="<?= e($filters['q']) ?>" placeholder="Tìm gói cloud...">
                        </div>
                    </div>
                    <div class="col-6 col-lg-2">
                        <select class="form-select vps-filter-input" name="cpu">
                            <option value="">CPU</option>
                            <option value="2" <?= $filters['cpu'] === '2' ? 'selected' : '' ?>>2 vCPU</option>
                            <option value="4" <?= $filters['cpu'] === '4' ? 'selected' : '' ?>>4 vCPU</option>
                            <option value="6" <?= $filters['cpu'] === '6' ? 'selected' : '' ?>>6+ vCPU</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <select class="form-select vps-filter-input" name="ram">
                            <option value="">RAM</option>
                            <option value="4GB" <?= $filters['ram'] === '4GB' ? 'selected' : '' ?>>4 GB</option>
                            <option value="8GB" <?= $filters['ram'] === '8GB' ? 'selected' : '' ?>>8 GB</option>
                            <option value="16GB" <?= $filters['ram'] === '16GB' ? 'selected' : '' ?>>16 GB+</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <select class="form-select vps-filter-input" name="location">
                            <option value="">Location</option>
                            <option value="VN" <?= $filters['location'] === 'VN' ? 'selected' : '' ?>>Việt Nam</option>
                            <option value="SG" <?= $filters['location'] === 'SG' ? 'selected' : '' ?>>Singapore</option>
                            <option value="US" <?= $filters['location'] === 'US' ? 'selected' : '' ?>>US</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <select class="form-select vps-filter-input" name="sort">
                            <option value="popular" <?= $filters['sort'] === 'popular' ? 'selected' : '' ?>>Phổ biến</option>
                            <option value="latest" <?= $filters['sort'] === 'latest' ? 'selected' : '' ?>>Mới nhất</option>
                            <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Giá tăng</option>
                            <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Giá giảm</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-1">
                        <button class="btn vps-btn-filter w-100" type="submit"><i class="fas fa-sliders me-1"></i>Lọc</button>
                    </div>
                </div>

                <div class="vps-chip-row mt-3">
                    <input type="hidden" name="plan_type" value="<?= e($filters['plan_type']) ?>" data-plan-type-input>
                    <?php foreach (['Tiết kiệm', 'Business', 'Pro', 'Gaming'] as $chip): ?>
                        <button type="button" class="vps-chip <?= $filters['plan_type'] === $chip ? 'active' : '' ?>" data-plan-chip="<?= e($chip) ?>"><?= e($chip) ?></button>
                    <?php endforeach; ?>
                    <a href="<?= e($cloudCatalogUrl) ?>" class="vps-chip-reset">Đặt lại bộ lọc</a>
                </div>
            </form>
        </div>
    </section>
<?php else: ?>
    <section class="pb-3">
        <div class="container">
            <form class="vps-filter-wrap" method="get" action="<?= base_url('products') ?>">
                <?php if ($selectedCategoryId > 0): ?>
                    <input type="hidden" name="category_id" value="<?= $selectedCategoryId ?>">
                <?php endif; ?>
                <div class="row g-2 g-lg-3 align-items-center">
                    <div class="col-12 col-lg-5">
                        <div class="vps-input-group">
                            <i class="fas fa-magnifying-glass"></i>
                            <input class="form-control vps-filter-input" name="q" value="<?= e($filters['q']) ?>" placeholder="Tìm trong danh mục này...">
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <select class="form-select vps-filter-input" name="sort">
                            <option value="popular" <?= $filters['sort'] === 'popular' ? 'selected' : '' ?>>Phổ biến</option>
                            <option value="latest" <?= $filters['sort'] === 'latest' ? 'selected' : '' ?>>Mới nhất</option>
                            <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Giá tăng</option>
                            <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Giá giảm</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <button class="btn vps-btn-filter w-100" type="submit">Lọc</button>
                    </div>
                    <div class="col-12 col-lg-2">
                        <a href="<?= e($cloudCatalogUrl) ?>" class="btn btn-outline-primary w-100">Về Cloud</a>
                    </div>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<section class="pb-5">
    <div class="container">
        <div class="vps-section-head d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
            <div>
                <h2 class="h4 fw-bold mb-1"><?= $isCloudCatalog ? 'Gói cloud phù hợp cho từng workload' : 'Danh mục dịch vụ đang xem' ?></h2>
                <p class="text-secondary mb-0"><?= $isCloudCatalog ? 'Tối ưu hiệu năng, dễ nâng cấp, minh bạch chi phí và đi thẳng tới luồng mua hàng.' : 'Các nhóm phụ vẫn tồn tại trong hệ thống, nhưng homepage và catalog chính ưu tiên cloud/VPS.' ?></p>
            </div>
            <span class="vps-count-pill"><?= (int) $meta['total'] ?> sản phẩm khả dụng</span>
        </div>

        <?php if ($isCloudCatalog): ?>
            <div class="row g-4 vps-plan-grid">
                <?php foreach ($products as $index => $product): ?>
                    <?php
                    $specs = vps_specs_extract((string) ($product['specs'] ?? ''));
                    $featured = $index === 1;
                    $label = $planBadges[$index % count($planBadges)];
                    ?>
                    <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                        <article class="vps-plan-card h-100 <?= $featured ? 'is-featured' : '' ?>">
                            <div class="vps-plan-head">
                                <span class="vps-plan-icon"><i class="fas fa-server"></i></span>
                                <span class="vps-plan-badge"><?= e($label) ?></span>
                            </div>
                            <h3 class="vps-plan-title"><?= e($product['name']) ?></h3>
                            <p class="vps-plan-desc"><?= e($product['short_description']) ?></p>

                            <div class="vps-price-wrap">
                                <strong class="vps-price"><?= format_money((float) $product['price']) ?></strong>
                                <small class="vps-cycle">/tháng</small>
                            </div>

                            <ul class="vps-spec-list">
                                <?php foreach ($specs as $key => $value): ?>
                                    <li><i class="<?= e($specIconMap[$key] ?? 'fas fa-circle') ?>"></i><span><?= e($value) ?> <?= $key === 'Băng thông' ? 'bandwidth' : '' ?></span></li>
                                <?php endforeach; ?>
                            </ul>

                            <ul class="vps-feature-list">
                                <li><i class="fas fa-check-circle"></i>Triển khai tự động trong vài phút</li>
                                <li><i class="fas fa-check-circle"></i>Bảo mật nhiều lớp + giám sát 24/7</li>
                                <li><i class="fas fa-check-circle"></i>Hỗ trợ kỹ thuật chuyên sâu</li>
                                <li><i class="fas fa-check-circle"></i>Hạ tầng ổn định cho production</li>
                            </ul>

                            <div class="vps-card-actions mt-auto">
                                <a class="btn vps-btn-outline" href="<?= base_url('products/show/' . $product['id']) ?>"><i class="fas fa-eye me-1"></i>Xem chi tiết</a>
                                <a class="btn vps-btn-solid" href="<?= base_url('products/show/' . $product['id']) ?>#checkout"><i class="fas fa-credit-card me-1"></i>Chọn gói</a>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($products as $product): ?>
                    <?php $facts = catalog_generic_facts((string) ($product['specs'] ?? ''), (string) ($product['description'] ?? '')); ?>
                    <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                        <article class="service-catalog-card h-100">
                            <span class="service-catalog-badge"><?= e((string) ($product['category_name'] ?? 'Dịch vụ')) ?></span>
                            <h3><?= e((string) $product['name']) ?></h3>
                            <p class="service-catalog-desc"><?= e((string) ($product['short_description'] ?? '')) ?></p>
                            <div class="service-catalog-price"><?= format_money((float) ($product['price'] ?? 0)) ?></div>
                            <ul class="service-catalog-facts">
                                <?php foreach ($facts as $fact): ?>
                                    <li><?= e($fact) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="service-catalog-actions mt-auto">
                                <a class="btn btn-outline-primary" href="<?= base_url('products/show/' . (int) $product['id']) ?>">Xem chi tiết</a>
                                <a class="btn btn-primary" href="<?= base_url('products/show/' . (int) $product['id']) ?>">Mở sản phẩm</a>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$products): ?>
            <div class="alert alert-light border mt-4">Chưa có sản phẩm phù hợp với bộ lọc hiện tại.</div>
        <?php endif; ?>

        <?php if ($meta['last_page'] > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center vps-pagination">
                    <?php for ($i = 1; $i <= $meta['last_page']; $i++): ?>
                        <li class="page-item <?= $meta['current_page'] === $i ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('products?' . http_build_query(array_merge($filters, ['page' => $i]))) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</section>

<?php if ($isCloudCatalog): ?>
    <?php
    $vpsGuideSectionId = 'cloud-catalog-guides';
    $vpsGuideVariant = 'catalog';
    $vpsGuideEyebrow = 'Cẩm nang dùng Cloud VPS';
    $vpsGuideTitle = 'Hướng dẫn sử dụng VPS đặt ngay trong catalog cloud';
    $vpsGuideSubtitle = 'Người dùng có thể vừa xem gói, vừa đọc nhanh cách triển khai website, auto/bot, game server, bảo mật và giám sát để chọn đúng cấu hình hơn.';
    $vpsGuideCtaLabel = 'Quay về landing cloud';
    $vpsGuideCtaUrl = base_url('/#cloud-catalog');
    require __DIR__ . '/../partials/vps_guides.php';
    ?>
<?php endif; ?>
