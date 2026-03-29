<?php
$supportPhone = '0888941220';
$supportHotlineText = 'Hotline / Zalo: ' . $supportPhone;
$zaloChatUrl = 'https://zalo.me/' . $supportPhone;
$zaloCommunityUrl = '';
$homeNoticeConfig = [
    'title' => 'Thông báo & hỗ trợ nhanh',
    'subtitle' => 'Một vài thông tin quan trọng để bạn tìm đúng dịch vụ, nhận hỗ trợ sớm và xử lý nhanh các tình huống thường gặp.',
    'storage_key' => 'zenox-home-notice-hidden-until',
    'snooze_hours' => 2,
    'snooze_label' => 'Không hiển thị lại trong 2 giờ',
    'cards' => [
        [
            'tone' => 'service',
            'icon' => 'fas fa-layer-group',
            'title' => 'Dịch vụ của chúng tôi',
            'items' => [
                'Nhận thiết kế Website, Panel, Tools theo yêu cầu',
                'Hỗ trợ cài đặt phần mềm trên VPS / Server',
                'Tư vấn & triển khai hệ thống Cloud VPS',
                $supportHotlineText,
                'Inbox ngay để được tư vấn!',
            ],
            'cta' => [
                'label' => 'Liên hệ Zalo',
                'url' => $zaloChatUrl,
                'variant' => 'primary',
                'target' => '_blank',
            ],
            'helper' => 'Ưu tiên hỗ trợ các nhu cầu triển khai nhanh và xử lý kỹ thuật cơ bản.',
        ],
        [
            'tone' => 'community',
            'icon' => 'fas fa-users',
            'title' => 'Cộng đồng Zalo',
            'items' => [
                'Tham gia nhóm để được hỗ trợ nhanh và cập nhật tin mới nhất',
            ],
            'cta' => [
                'label' => $zaloCommunityUrl !== '' ? 'Mở nhóm Zalo' : 'Link nhóm sẽ cập nhật sau',
                'url' => $zaloCommunityUrl,
                'variant' => 'secondary',
                'target' => '_blank',
            ],
            'helper' => $zaloCommunityUrl !== '' ? 'Nhóm cộng đồng hỗ trợ hỏi đáp và thông báo nhanh.' : 'Đã chừa sẵn khu vực CTA. Chỉ cần thay link nhóm Zalo thật ở biến $zaloCommunityUrl.',
        ],
        [
            'tone' => 'proxy',
            'icon' => 'fas fa-network-wired',
            'title' => 'Giới thiệu MTDProxy',
            'badge' => 'Giới thiệu tạm thời',
            'items' => [
                'Proxy dân cư Viettel, FPT, VNPT',
                'Proxy Datacenter Việt Nam & US',
                'Proxy IPv4 Tĩnh chuyên dụng',
                'Giá rẻ nhất, mua càng lâu giá càng rẻ',
                '[Hiện chưa update]',
            ],
            'helper' => 'Mục này đang ở trạng thái giới thiệu sơ bộ để bạn biết định hướng dịch vụ, chưa phải nội dung hoàn thiện.',
        ],
        [
            'tone' => 'support',
            'icon' => 'fas fa-screwdriver-wrench',
            'title' => 'VPS không vào được?',
            'ordered' => true,
            'items' => [
                'Đăng nhập tài khoản trên website',
                'Vào Lịch sử → VPS đã mua',
                'Nhấn vào VPS bị lỗi',
                'Nhấn nút Reboot',
                'Đợi 1-2 phút rồi thử lại',
                'Vẫn lỗi? Liên hệ Zalo ' . $supportPhone,
            ],
            'cta' => [
                'label' => 'Nhắn hỗ trợ ngay',
                'url' => $zaloChatUrl,
                'variant' => 'ghost',
                'target' => '_blank',
            ],
            'helper' => 'Nếu reboot xong vẫn không truy cập được, gửi mã dịch vụ để hỗ trợ kiểm tra nhanh hơn.',
        ],
    ],
];

$cloudCategories = is_array($cloudCategories ?? null) ? $cloudCategories : [];
$secondaryCategories = is_array($secondaryCategories ?? null) ? $secondaryCategories : [];
$cloudFeaturedProducts = is_array($cloudFeaturedProducts ?? null) ? $cloudFeaturedProducts : [];
$primaryCloudCategory = $cloudCategories[0] ?? null;
$cloudCatalogQuery = [];
if (is_array($primaryCloudCategory) && !empty($primaryCloudCategory['id'])) {
    $cloudCatalogQuery['category_id'] = (int) $primaryCloudCategory['id'];
}
$cloudCatalogUrl = base_url('products' . ($cloudCatalogQuery ? '?' . http_build_query($cloudCatalogQuery) : ''));
$cloudHeroProducts = array_slice($cloudFeaturedProducts, 0, 3);
$primaryCloudProduct = $cloudFeaturedProducts[0] ?? null;
$cloudValueCards = [
    [
        'icon' => 'fas fa-gauge-high',
        'title' => 'Tập trung cho workload cloud',
        'text' => 'Homepage ưu tiên Cloud VPS và Cloud Server để người dùng nhìn thấy ngay nhóm dịch vụ chủ lực.',
    ],
    [
        'icon' => 'fas fa-bolt',
        'title' => 'Triển khai nhanh, mua rõ ràng',
        'text' => 'Từ landing page có thể đi thẳng tới catalog cloud hoặc chi tiết từng gói mà không vòng vèo qua các nhóm phụ.',
    ],
    [
        'icon' => 'fas fa-headset',
        'title' => 'Hỗ trợ sau mua sát nhu cầu',
        'text' => 'Bổ sung hướng dẫn dùng VPS, triển khai website, automation và quy trình reboot để khách tự xử lý nhanh hơn.',
    ],
];

if (!function_exists('home_cloud_specs_extract')) {
    function home_cloud_specs_extract(string $specs): array
    {
        $summary = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($specs));

        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$label, $value] = array_map('trim', explode(':', $line, 2));
            $normalized = strtolower($label);

            if (str_contains($normalized, 'cpu')) {
                $summary[] = $value . ' CPU';
            } elseif (str_contains($normalized, 'ram')) {
                $summary[] = $value . ' RAM';
            } elseif (str_contains($normalized, 'ssd') || str_contains($normalized, 'nvme') || str_contains($normalized, 'storage')) {
                $summary[] = $value . ' Disk';
            } elseif (str_contains($normalized, 'bandwidth')) {
                $summary[] = $value . ' Bandwidth';
            }

            if (count($summary) >= 4) {
                break;
            }
        }

        return $summary;
    }
}
?>

<section class="hero-section py-5 py-lg-6">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-xl-6">
                <span class="badge text-bg-primary mb-3">Cloud-first storefront</span>
                <h1 class="display-5 fw-bold mb-3">Cloud VPS và Cloud Server cho website, app và workload production</h1>
                <p class="lead text-secondary mb-4">ZenoxDigital định vị rõ mảng chủ lực là hạ tầng cloud: triển khai nhanh, hỗ trợ kỹ thuật tốt và phù hợp từ website nhỏ đến hệ thống chạy thực tế.</p>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-primary btn-lg" href="<?= e($cloudCatalogUrl) ?>">Xem gói Cloud</a>
                    <a class="btn btn-outline-primary btn-lg" href="<?= $primaryCloudProduct ? base_url('products/show/' . (int) $primaryCloudProduct['id']) : e($cloudCatalogUrl) ?>">
                        <?= $primaryCloudProduct ? 'Xem gói nổi bật' : 'Xem chi tiết' ?>
                    </a>
                </div>
                <div class="hero-ai-helper d-flex flex-wrap align-items-center gap-2 mt-3">
                    <button
                        type="button"
                        class="btn btn-sm btn-light border hero-ai-trigger"
                        data-ai-chat-trigger
                        data-ai-message="Tư vấn giúp tôi gói Cloud VPS phù hợp cho website bán hàng"
                        data-ai-autosend="1"
                    >
                        <i class="fas fa-comments me-2"></i>Hỏi Meow chọn gói cloud
                    </button>
                    <span class="text-secondary small">Tư vấn nhanh về Cloud VPS, Cloud Server, triển khai website và FAQ mua hàng.</span>
                </div>

                <div class="cloud-hero-pills mt-4">
                    <span class="cloud-hero-pill"><i class="fas fa-server"></i>Cloud catalog rõ ràng</span>
                    <span class="cloud-hero-pill"><i class="fas fa-shield-halved"></i>Ưu tiên workload thật</span>
                    <span class="cloud-hero-pill"><i class="fas fa-screwdriver-wrench"></i>Hỗ trợ triển khai cơ bản</span>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="cloud-hero-panel">
                    <div class="cloud-hero-panel-head">
                        <div>
                            <span class="cloud-hero-panel-kicker">Cloud spotlight</span>
                            <h2>Gói cloud đang được quan tâm</h2>
                        </div>
                        <a href="<?= e($cloudCatalogUrl) ?>" class="cloud-hero-panel-link">Xem catalog</a>
                    </div>

                    <div class="cloud-hero-product-stack">
                        <?php foreach ($cloudHeroProducts as $product): ?>
                            <a class="cloud-hero-product" href="<?= base_url('products/show/' . (int) $product['id']) ?>">
                                <div class="cloud-hero-product-copy">
                                    <strong><?= e((string) $product['name']) ?></strong>
                                    <small><?= e((string) ($product['short_description'] ?? '')) ?></small>
                                </div>
                                <span class="cloud-hero-product-price"><?= format_money((float) ($product['price'] ?? 0)) ?></span>
                            </a>
                        <?php endforeach; ?>

                        <?php if ($cloudHeroProducts === []): ?>
                            <div class="cloud-hero-empty">
                                <strong>Chưa có gói cloud để hiển thị</strong>
                                <p class="mb-0">Khi category cloud/VPS có dữ liệu thật, homepage sẽ tự lấy lên khu vực spotlight này.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="cloud-hero-categories">
                        <?php foreach (array_slice($cloudCategories, 0, 3) as $category): ?>
                            <a class="cloud-hero-category-chip" href="<?= base_url('products?' . http_build_query(['category_id' => (int) $category['id']])) ?>">
                                <i class="fas fa-layer-group"></i>
                                <span><?= e((string) $category['name']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../partials/home_notice_popup.php'; ?>

<section class="py-5 bg-light" id="cloud-catalog">
    <div class="container">
        <div class="section-heading d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
            <div>
                <span class="section-kicker">Cloud / VPS spotlight</span>
                <h2 class="h2 fw-bold mb-2">Gói Cloud VPS & Cloud Server tiêu biểu</h2>
                <p class="text-secondary mb-0">Homepage chỉ đưa lên nhóm cloud/VPS để khách nhìn rõ mảng chính, còn các nhóm sản phẩm khác được giữ ở vai trò phụ hơn.</p>
            </div>
            <a href="<?= e($cloudCatalogUrl) ?>" class="btn btn-outline-secondary">Xem tất cả gói cloud</a>
        </div>

        <div class="row g-4">
            <?php foreach ($cloudFeaturedProducts as $index => $product): ?>
                <?php $specChips = home_cloud_specs_extract((string) ($product['specs'] ?? '')); ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <article class="cloud-plan-card h-100 <?= $index === 0 ? 'is-featured' : '' ?>">
                        <div class="cloud-plan-card-head">
                            <span class="cloud-plan-card-icon"><i class="fas fa-server"></i></span>
                            <span class="cloud-plan-card-badge"><?= e((string) ($product['category_name'] ?? 'Cloud')) ?></span>
                        </div>
                        <h3><?= e((string) $product['name']) ?></h3>
                        <p class="cloud-plan-card-desc"><?= e((string) ($product['short_description'] ?? '')) ?></p>
                        <div class="cloud-plan-card-price">
                            <strong><?= format_money((float) ($product['price'] ?? 0)) ?></strong>
                            <span>/tháng</span>
                        </div>
                        <?php if ($specChips !== []): ?>
                            <div class="cloud-plan-specs">
                                <?php foreach ($specChips as $spec): ?>
                                    <span><?= e($spec) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <p class="cloud-plan-card-text"><?= e((string) ($product['description'] ?? '')) ?></p>
                        <div class="cloud-plan-card-actions mt-auto">
                            <a class="btn btn-outline-primary" href="<?= base_url('products/show/' . (int) $product['id']) ?>">Xem chi tiết</a>
                            <a class="btn btn-primary" href="<?= base_url('products/show/' . (int) $product['id']) ?>#checkout">Chọn gói</a>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>

            <?php if ($cloudFeaturedProducts === []): ?>
                <div class="col-12">
                    <div class="alert alert-light border mb-0">Chưa có dữ liệu sản phẩm cloud để hiển thị ở landing page.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="py-5" id="cloud-overview">
    <div class="container">
        <div class="section-heading mb-4">
            <span class="section-kicker">Vì sao cloud là trọng tâm</span>
            <h2 class="h2 fw-bold mb-2">Trang chủ được tối ưu như landing bán Cloud VPS</h2>
            <p class="text-secondary mb-0">Phần định vị, CTA và nội dung hỗ trợ đều xoay quanh Cloud VPS / Cloud Server để khách đi thẳng vào nhu cầu chính thay vì bị loãng bởi nhiều nhóm sản phẩm khác.</p>
        </div>

        <div class="row g-4">
            <?php foreach ($cloudValueCards as $card): ?>
                <div class="col-lg-4">
                    <article class="icon-card cloud-value-card h-100">
                        <span class="cloud-value-icon"><i class="<?= e((string) $card['icon']) ?>"></i></span>
                        <h5><?= e((string) $card['title']) ?></h5>
                        <p class="mb-0 text-secondary"><?= e((string) $card['text']) ?></p>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php
$vpsGuideSectionId = 'vps-guides';
$vpsGuideVariant = 'storefront';
$vpsGuideEyebrow = 'Hướng dẫn sử dụng VPS';
$vpsGuideTitle = 'Hướng dẫn nhanh để khách tự tin chọn và dùng Cloud VPS';
$vpsGuideSubtitle = 'Section này đóng vai trò vừa hỗ trợ bán hàng, vừa giúp khách hiểu đúng các use case phổ biến như website, game server, automation, bảo mật và giám sát.';
$vpsGuideCtaLabel = 'Đi tới catalog cloud';
$vpsGuideCtaUrl = $cloudCatalogUrl;
require __DIR__ . '/../partials/vps_guides.php';
?>

<?php if ($secondaryCategories !== []): ?>
    <section class="py-5 bg-light">
        <div class="container">
            <div class="section-heading d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
                <div>
                    <span class="section-kicker">Nhóm phụ vẫn sẵn sàng</span>
                    <h2 class="h3 fw-bold mb-2">Các dịch vụ khác trong hệ thống</h2>
                    <p class="text-secondary mb-0">Server game, wallet, sim và các nhóm khác vẫn còn trong shop, nhưng không chiếm spotlight chính của landing page cloud-first.</p>
                </div>
                <a href="<?= base_url('products') ?>" class="btn btn-outline-secondary">Mở catalog</a>
            </div>

            <div class="secondary-service-grid">
                <?php foreach (array_slice($secondaryCategories, 0, 6) as $category): ?>
                    <a class="secondary-service-card" href="<?= base_url('products?' . http_build_query(['category_id' => (int) $category['id']])) ?>">
                        <strong><?= e((string) $category['name']) ?></strong>
                        <small><?= e((string) ($category['description'] ?: 'Xem thêm sản phẩm trong nhóm này.')) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="py-5 bg-light" id="faq">
    <div class="container">
        <div class="section-heading mb-4">
            <span class="section-kicker">FAQ Cloud VPS</span>
            <h2 class="h3 fw-bold mb-2">Câu hỏi thường gặp trước khi mua cloud</h2>
        </div>

        <div class="accordion" id="faqAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#faq1">Sau khi thanh toán bao lâu nhận được VPS?</button>
                </h2>
                <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">Thông thường hệ thống xử lý và bàn giao trong vài phút, tùy gói và thời điểm triển khai.</div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq2">Có hỗ trợ cài đặt website hoặc phần mềm cơ bản không?</button>
                </h2>
                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">Có. ZenoxDigital ưu tiên hỗ trợ nhu cầu cloud/VPS, đặc biệt với website, app nội bộ và các thiết lập kỹ thuật cơ bản.</div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq3">Nếu VPS không truy cập được thì nên làm gì trước?</button>
                </h2>
                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">Đăng nhập tài khoản, vào lịch sử VPS đã mua, thử reboot dịch vụ và đợi 1-2 phút. Nếu vẫn lỗi, liên hệ Zalo <?= e($supportPhone) ?> để được hỗ trợ nhanh.</div>
                </div>
            </div>
        </div>
    </div>
</section>
