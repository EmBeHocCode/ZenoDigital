<?php
$supportPhone = '0888941220';
$supportHotlineText = 'Hotline / Zalo: ' . $supportPhone;
$zaloChatUrl = 'https://zalo.me/' . $supportPhone;
$zaloCommunityUrl = '';
$homeNoticeConfig = [
    'title' => 'Thông báo & hỗ trợ nhanh',
    'subtitle' => 'Tổng hợp nhanh các mục hỗ trợ, cộng đồng và hướng dẫn cần thiết để bạn thao tác thuận tiện hơn ngay từ trang chủ.',
    'storage_key' => 'zenox-home-notice-hidden-until',
    'snooze_hours' => 2,
    'snooze_label' => 'Tạm ẩn trong 2 giờ',
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
            'helper' => 'Phù hợp với nhu cầu triển khai website, panel, tools và các hỗ trợ kỹ thuật cơ bản.',
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
            'helper' => $zaloCommunityUrl !== '' ? 'Nhóm cộng đồng hỗ trợ hỏi đáp nhanh và cập nhật thông tin mới.' : 'Link nhóm cộng đồng sẽ được cập nhật khi khu vực hỗ trợ chung được mở.',
        ],
        [
            'tone' => 'proxy',
            'icon' => 'fas fa-network-wired',
            'title' => 'Giới thiệu MTDProxy',
            'badge' => 'Dịch vụ mở rộng',
            'items' => [
                'Proxy dân cư Viettel, FPT, VNPT',
                'Proxy Datacenter Việt Nam & US',
                'Proxy IPv4 Tĩnh chuyên dụng',
                'Giá rẻ nhất, mua càng lâu giá càng rẻ',
                'Có thể tư vấn theo nhu cầu sử dụng thực tế',
            ],
            'helper' => 'Nhóm dịch vụ proxy đang được hoàn thiện thêm nội dung chi tiết. Nếu cần báo giá nhanh, bạn có thể liên hệ trực tiếp.',
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
            'helper' => 'Nếu reboot xong vẫn chưa truy cập được, hãy gửi mã dịch vụ để bên mình kiểm tra nhanh hơn.',
        ],
    ],
];

$cloudCategories = is_array($cloudCategories ?? null) ? $cloudCategories : [];
$secondaryCategories = is_array($secondaryCategories ?? null) ? $secondaryCategories : [];
$cloudFeaturedProducts = is_array($cloudFeaturedProducts ?? null) ? $cloudFeaturedProducts : [];
$studentVpsCategory = is_array($studentVpsCategory ?? null) ? $studentVpsCategory : null;
$studentVpsProducts = is_array($studentVpsProducts ?? null) ? $studentVpsProducts : [];
$publicBrandName = app_site_name();
$primaryCloudCategory = $cloudCategories[0] ?? null;
$cloudCatalogQuery = [];
if (is_array($primaryCloudCategory) && !empty($primaryCloudCategory['id'])) {
    $cloudCatalogQuery['category_id'] = (int) $primaryCloudCategory['id'];
}
$cloudCatalogUrl = base_url('products' . ($cloudCatalogQuery ? '?' . http_build_query($cloudCatalogQuery) : ''));
$studentVpsUrl = is_array($studentVpsCategory) && !empty($studentVpsCategory['id'])
    ? base_url('products?' . http_build_query(['category_id' => (int) $studentVpsCategory['id']]))
    : $cloudCatalogUrl;
$cloudHeroProducts = array_slice($cloudFeaturedProducts, 0, 3);
$primaryCloudProduct = $cloudFeaturedProducts[0] ?? null;
$studentPlanBadges = [
    'student-nano' => 'Rẻ nhất',
    'student-basic' => 'Dễ bắt đầu',
    'student-plus' => 'Cân bằng',
    'student-pro' => 'Đồ án lớn',
];
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

if (!function_exists('home_student_specs_extract')) {
    function home_student_specs_extract(string $specs): array
    {
        $items = [];
        $labels = [
            'cpu' => 'CPU',
            'ram' => 'RAM',
            'ssd' => 'Ổ NVMe',
            'nvme' => 'Ổ NVMe',
            'storage' => 'Ổ lưu trữ',
            'bandwidth' => 'Bandwidth',
        ];

        foreach (preg_split('/\r\n|\r|\n/', trim($specs)) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$label, $value] = array_map('trim', explode(':', $line, 2));
            $normalized = strtolower($label);
            foreach ($labels as $needle => $displayLabel) {
                if (str_contains($normalized, $needle)) {
                    $items[] = ['label' => $displayLabel, 'value' => $value];
                    break;
                }
            }

            if (count($items) >= 4) {
                break;
            }
        }

        return $items;
    }
}
?>

<div class="home-top-gradient">
    <?php require __DIR__ . '/../partials/hero_banner_carousel.php'; ?>

    <section class="hero-section py-5 py-lg-6">
        <div class="container">
            <div class="row align-items-center g-4">
            <div class="col-xl-6">
                <span class="badge text-bg-primary mb-3">Cloud-first storefront</span>
                <h1 class="display-5 fw-bold mb-3">Cloud VPS và Cloud Server cho website, app và workload production</h1>
                <p class="lead text-secondary mb-4"><?= e($publicBrandName) ?> định vị rõ mảng chủ lực là hạ tầng cloud: triển khai nhanh, hỗ trợ kỹ thuật tốt và phù hợp từ website nhỏ đến hệ thống chạy thực tế.</p>
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
</div>

<?php require __DIR__ . '/../partials/home_notice_popup.php'; ?>

<div class="home-content-surface">
<?php if ($studentVpsProducts !== []): ?>
    <section class="student-vps-section py-5" id="student-vps-plans">
        <span id="vps-student" class="section-anchor"></span>
        <div class="container">
            <div class="student-vps-head">
                <div>
                    <span class="section-kicker">VPS Giá Rẻ Cho Sinh Viên</span>
                    <h2>Cloud VPS học tập chỉ từ 35.000đ/tháng</h2>
                    <p>Nhóm gói nhỏ gọn cho sinh viên học Linux, chạy website môn học, bot thử nghiệm, API mini và đồ án cá nhân với chi phí dễ kiểm soát.</p>
                </div>
                <a class="btn btn-outline-primary" href="<?= e($studentVpsUrl) ?>">Xem toàn bộ nhóm</a>
            </div>

            <div class="student-vps-grid">
                <?php foreach ($studentVpsProducts as $product): ?>
                    <?php
                    $slug = (string) ($product['slug'] ?? '');
                    $specItems = home_student_specs_extract((string) ($product['specs'] ?? ''));
                    $badge = $studentPlanBadges[$slug] ?? (string) ($product['category_name'] ?? 'Student VPS');
                    ?>
                    <article class="student-plan-card <?= $slug === 'student-nano' ? 'is-cheapest' : '' ?>">
                        <div class="student-plan-topline">
                            <span class="student-plan-icon"><i class="fas fa-graduation-cap"></i></span>
                            <span class="student-plan-badge"><?= e($badge) ?></span>
                        </div>
                        <h3><?= e((string) ($product['name'] ?? 'Student VPS')) ?></h3>
                        <p class="student-plan-desc"><?= e((string) ($product['short_description'] ?? '')) ?></p>
                        <div class="student-plan-price">
                            <strong><?= format_money((float) ($product['price'] ?? 0)) ?></strong>
                            <span>/tháng</span>
                        </div>
                        <ul class="student-plan-specs">
                            <?php foreach ($specItems as $spec): ?>
                                <li>
                                    <span><?= e((string) $spec['label']) ?></span>
                                    <b><?= e((string) $spec['value']) ?></b>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="student-plan-note"><?= e((string) ($product['description'] ?? '')) ?></p>
                        <div class="student-plan-actions">
                            <a class="btn btn-outline-primary" href="<?= base_url('products/show/' . (int) $product['id']) ?>">Xem chi tiết</a>
                            <a class="btn btn-primary" href="<?= base_url('products/show/' . (int) $product['id']) ?>#checkout">Chọn gói</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="py-5 bg-light" id="cloud-catalog">
    <span id="cloud-vps-plans" class="section-anchor"></span>
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
    <span id="business-cloud" class="section-anchor"></span>
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
        <span id="game-server" class="section-anchor"></span>
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

<?php
$affiliateBenefits = [
    ['icon' => 'fas fa-percent', 'text' => 'Hoa hồng lên đến 20%'],
    ['icon' => 'fas fa-chart-line', 'text' => 'Theo dõi doanh thu minh bạch'],
    ['icon' => 'fas fa-calendar-check', 'text' => 'Thanh toán định kỳ'],
    ['icon' => 'fas fa-infinity', 'text' => 'Không giới hạn thu nhập'],
    ['icon' => 'fas fa-bullhorn', 'text' => 'Hỗ trợ tài liệu quảng bá'],
    ['icon' => 'fas fa-link', 'text' => 'Link giới thiệu cá nhân'],
];

$commissionLevels = [
    ['name' => 'Bronze', 'rate' => '10%', 'text' => 'Phù hợp người mới bắt đầu giới thiệu dịch vụ cloud.'],
    ['name' => 'Silver', 'rate' => '15%', 'text' => 'Cho cộng tác viên có doanh số đều và tệp khách rõ.'],
    ['name' => 'Gold', 'rate' => '20%', 'text' => 'Mức cao nhất cho đối tác tăng trưởng ổn định.'],
];

$resellerBenefits = [
    ['icon' => 'fas fa-tags', 'text' => 'Chiết khấu giá sỉ'],
    ['icon' => 'fas fa-users-gear', 'text' => 'Quản lý khách hàng'],
    ['icon' => 'fas fa-chart-pie', 'text' => 'Theo dõi doanh thu'],
    ['icon' => 'fas fa-cart-plus', 'text' => 'Tạo đơn hàng nhanh'],
    ['icon' => 'fas fa-headset', 'text' => 'Hỗ trợ kỹ thuật'],
    ['icon' => 'fas fa-file-lines', 'text' => 'Tài liệu bán hàng'],
];

$cloudFaqItems = [
    [
        'question' => 'Sau khi thanh toán bao lâu nhận được VPS?',
        'answer' => 'Hệ thống tự động khởi tạo và bàn giao trong 1–5 phút tùy gói và thời điểm triển khai.',
    ],
    [
        'question' => 'Có hỗ trợ cài đặt website hoặc phần mềm không?',
        'answer' => 'Có. Hỗ trợ cài WordPress, Laravel, Node.js, Python, Docker và các phần mềm phổ biến.',
    ],
    [
        'question' => 'Tôi có thể nâng cấp cấu hình sau này không?',
        'answer' => 'Có. CPU, RAM, dung lượng lưu trữ và băng thông có thể nâng cấp bất kỳ lúc nào.',
    ],
    [
        'question' => 'Có backup dữ liệu không?',
        'answer' => 'Tùy từng gói. Một số gói hỗ trợ snapshot và backup định kỳ.',
    ],
    [
        'question' => 'Có hỗ trợ kỹ thuật 24/7 không?',
        'answer' => 'Có. Đội ngũ kỹ thuật luôn sẵn sàng hỗ trợ khi cần.',
    ],
    [
        'question' => 'VPS phù hợp cho những mục đích nào?',
        'answer' => 'Website, bot, automation, game server, AI và môi trường học tập.',
    ],
    [
        'question' => 'Có thể chọn vị trí máy chủ không?',
        'answer' => 'Có. Hỗ trợ Việt Nam, Singapore, Mỹ và nhiều khu vực khác.',
    ],
    [
        'question' => 'Có chống DDoS không?',
        'answer' => 'Có. Hệ thống được bảo vệ bằng nhiều lớp bảo mật mạng.',
    ],
    [
        'question' => 'Có chính sách hoàn tiền không?',
        'answer' => 'Có thể áp dụng trong thời gian cam kết theo chính sách dịch vụ.',
    ],
    [
        'question' => 'Người mới có sử dụng được không?',
        'answer' => 'Hoàn toàn được. Có hướng dẫn chi tiết và đội ngũ hỗ trợ tận tình.',
    ],
    [
        'question' => 'Tôi có quyền root hoặc administrator không?',
        'answer' => 'Có. Khách hàng được toàn quyền quản trị máy chủ.',
    ],
    [
        'question' => 'Hỗ trợ hệ điều hành nào?',
        'answer' => 'Ubuntu, Debian, AlmaLinux, Rocky Linux và Windows Server (tùy gói).',
    ],
];

$affiliateApplyUrl = base_url('profile?tab=seller');
$commissionUrl = '#affiliate-commission';
$resellerApplyUrl = base_url('profile?tab=seller');
$faqColumnSize = (int) ceil(count($cloudFaqItems) / 2);
$faqColumns = array_chunk($cloudFaqItems, max(1, $faqColumnSize), true);
?>

<section class="growth-section growth-section--affiliate" id="affiliate-program">
    <div class="container">
        <div class="growth-section-head">
            <div>
                <span class="section-kicker">Tiếp thị liên kết</span>
                <h2>Kiếm tiền cùng ZenoDigital</h2>
                <p>Nhận hoa hồng hấp dẫn khi giới thiệu khách hàng sử dụng dịch vụ Cloud VPS, Cloud Server và các sản phẩm số.</p>
            </div>
            <div class="growth-section-actions">
                <a class="btn btn-primary" href="<?= e($affiliateApplyUrl) ?>">Đăng ký cộng tác viên</a>
                <a class="btn btn-outline-primary" href="<?= e($commissionUrl) ?>">Xem bảng hoa hồng</a>
            </div>
        </div>

        <div class="growth-benefit-grid">
            <?php foreach ($affiliateBenefits as $benefit): ?>
                <article class="growth-benefit-card">
                    <span><i class="<?= e((string) $benefit['icon']) ?>"></i></span>
                    <strong><?= e((string) $benefit['text']) ?></strong>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="commission-grid" id="affiliate-commission">
            <?php foreach ($commissionLevels as $level): ?>
                <article class="commission-card">
                    <span><?= e((string) $level['name']) ?></span>
                    <strong><?= e((string) $level['rate']) ?></strong>
                    <p><?= e((string) $level['text']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="growth-section growth-section--reseller" id="reseller-program">
    <div class="container">
        <div class="reseller-panel">
            <div class="reseller-copy">
                <span class="section-kicker">Đại lý / người bán</span>
                <h2>Trở thành đại lý và bán dịch vụ dưới thương hiệu của bạn</h2>
                <p>Tận dụng hệ thống sẵn có, giá sỉ cạnh tranh và hỗ trợ kỹ thuật chuyên sâu để phát triển hoạt động kinh doanh.</p>
                <div class="growth-section-actions">
                    <a class="btn btn-primary" href="<?= e($resellerApplyUrl) ?>">Đăng ký đại lý</a>
                    <a class="btn btn-outline-primary" href="<?= e($zaloChatUrl) ?>" target="_blank" rel="noopener noreferrer">Liên hệ tư vấn</a>
                </div>
            </div>

            <div class="reseller-benefit-grid">
                <?php foreach ($resellerBenefits as $benefit): ?>
                    <article class="reseller-benefit-card">
                        <i class="<?= e((string) $benefit['icon']) ?>"></i>
                        <span><?= e((string) $benefit['text']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="home-faq-section py-5" id="faq" aria-labelledby="cloudFaqTitle">
    <div class="container">
        <div class="home-faq-shell">
            <div class="section-heading mb-4">
                <span class="section-kicker">FAQ CLOUD VPS</span>
                <h2 class="h3 fw-bold mb-2" id="cloudFaqTitle">Câu hỏi thường gặp trước khi mua Cloud VPS</h2>
                <p class="text-secondary mb-0">Giải đáp những câu hỏi phổ biến nhất để khách hàng yên tâm lựa chọn dịch vụ.</p>
            </div>

            <div class="accordion home-faq-accordion" id="faqAccordion">
                <?php foreach ($faqColumns as $columnItems): ?>
                    <div class="home-faq-column">
                        <?php foreach ($columnItems as $index => $item): ?>
                            <?php
                            $faqId = 'cloudFaq' . ($index + 1);
                            $headingId = $faqId . 'Heading';
                            $isOpen = $index === 0;
                            ?>
                            <article class="accordion-item">
                                <h3 class="accordion-header" id="<?= e($headingId) ?>">
                                    <button
                                        class="accordion-button <?= $isOpen ? '' : 'collapsed' ?>"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#<?= e($faqId) ?>"
                                        aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                                        aria-controls="<?= e($faqId) ?>"
                                    >
                                        <?= e((string) $item['question']) ?>
                                    </button>
                                </h3>
                                <div
                                    id="<?= e($faqId) ?>"
                                    class="accordion-collapse collapse <?= $isOpen ? 'show' : '' ?>"
                                    aria-labelledby="<?= e($headingId) ?>"
                                    data-bs-parent="#faqAccordion"
                                >
                                    <div class="accordion-body"><?= e((string) $item['answer']) ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
</div>
