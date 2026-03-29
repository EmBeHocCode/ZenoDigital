<?php
$aiWidgetProduct = $product;
$baseCpu = 2;
$baseRam = 4;
$baseDisk = 80;
if (preg_match('/CPU:\s*(\d+)/i', (string) $product['specs'], $cpuMatch)) {
    $baseCpu = (int) $cpuMatch[1];
}
if (preg_match('/RAM:\s*(\d+)/i', (string) $product['specs'], $ramMatch)) {
    $baseRam = (int) $ramMatch[1];
}
if (preg_match('/(?:SSD|NVMe|Storage):\s*(\d+)/i', (string) $product['specs'], $diskMatch)) {
    $baseDisk = (int) $diskMatch[1];
}

$osOptions = [
    ['name' => 'Ubuntu 22.04 LTS', 'icon' => 'fab fa-ubuntu'],
    ['name' => 'Debian 12', 'icon' => 'fab fa-debian'],
    ['name' => 'AlmaLinux 9', 'icon' => 'fas fa-circle-dot'],
    ['name' => 'CentOS Stream', 'icon' => 'fas fa-circle-dot'],
    ['name' => 'Windows Server 2022', 'icon' => 'fab fa-windows'],
    ['name' => 'Windows 10 Pro', 'icon' => 'fab fa-windows'],
];

$billingOptions = [
    ['key' => '1m', 'label' => '1 tháng', 'multiplier' => 1, 'hint' => 'Linh hoạt'],
    ['key' => '3m', 'label' => '3 tháng', 'multiplier' => 3, 'hint' => 'Phổ biến'],
    ['key' => '12m', 'label' => '12 tháng', 'multiplier' => 12, 'hint' => 'Tiết kiệm 12%'],
];

$addons = [
    ['key' => 'backup', 'title' => 'Backup hàng ngày', 'price' => 49000, 'icon' => 'fas fa-clock-rotate-left'],
    ['key' => 'premium_support', 'title' => 'Hỗ trợ 24/7 Premium', 'price' => 79000, 'icon' => 'fas fa-headset'],
    ['key' => 'private_ip', 'title' => 'IP riêng bổ sung', 'price' => 59000, 'icon' => 'fas fa-globe'],
    ['key' => 'snapshot', 'title' => 'Snapshot tự động', 'price' => 39000, 'icon' => 'fas fa-camera'],
    ['key' => 'vat', 'title' => 'Xuất hóa đơn VAT', 'price' => 0, 'icon' => 'fas fa-file-invoice'],
];

$isAuthenticated = !empty($isAuthenticated);
$currentWalletBalance = (float) ($currentWalletBalance ?? 0);
$walletCheckoutDisabled = $isAuthenticated && $currentWalletBalance <= 0;
$walletBoxStateClass = !$isAuthenticated ? 'is-guest' : ($walletCheckoutDisabled ? 'is-insufficient' : 'is-ready');
$walletStatusMessage = !$isAuthenticated
    ? 'Đăng nhập để thanh toán sản phẩm bằng số dư ví nội bộ.'
    : ($currentWalletBalance > 0
        ? 'Khi xác nhận mua hàng, hệ thống sẽ thanh trừ trực tiếp vào số dư hiện có của bạn.'
        : 'Số dư ví đang là 0 ₫. Bạn cần nạp tiền trước khi tiếp tục mua hàng.');
?>

<section class="vps-detail-top py-4 py-lg-5">
    <div class="container">
        <div class="vps-detail-hero">
            <div>
                <span class="vps-kicker">Cloud VPS Product</span>
                <h1 class="vps-title mb-2"><i class="fas fa-server me-2"></i><?= e($product['name']) ?></h1>
                <p class="text-secondary mb-3"><?= e($product['description']) ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="vps-trust-pill"><i class="fas fa-clock me-2"></i>Uptime 99.9%</span>
                    <span class="vps-trust-pill"><i class="fas fa-shield-halved me-2"></i>Anti DDoS</span>
                    <span class="vps-trust-pill"><i class="fas fa-bolt me-2"></i>Kích hoạt nhanh</span>
                    <span class="vps-trust-pill"><i class="fas fa-life-ring me-2"></i>Hỗ trợ 24/7</span>
                </div>
            </div>
            <img class="vps-hero-icon" src="<?= product_image_url($product) ?>" alt="<?= e($product['name']) ?>">
        </div>
    </div>
</section>

<section class="pb-5" id="checkout">
    <div class="container">
        <!-- Added: real checkout form that submits to backend and creates an order record. -->
        <form method="post" action="<?= base_url('products/checkout/' . (int) $product['id']) ?>" id="productCheckoutForm" data-product-checkout-form>
            <?= csrf_field() ?>
            <input type="hidden" name="cpu" value="<?= $baseCpu ?>" data-checkout-cpu>
            <input type="hidden" name="ram" value="<?= $baseRam ?>" data-checkout-ram>
            <input type="hidden" name="disk" value="<?= $baseDisk ?>" data-checkout-disk>
            <div class="row g-4">
            <!-- Left column: configurator -->
            <div class="col-xl-8">
                <div class="vps-config-card mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0">Nâng cấp cấu hình</h2>
                        <span class="vps-soft-note">Tùy chỉnh linh hoạt theo nhu cầu tải thực tế</span>
                    </div>

                    <div class="vps-config-summary-chip mb-3">
                        Cấu hình hiện tại: <b data-current-config><?= $baseCpu ?> vCPU · <?= $baseRam ?>GB RAM · <?= $baseDisk ?>GB NVMe</b>
                    </div>

                    <div class="vps-stepper-wrap" data-vps-stepper data-base-cpu="<?= $baseCpu ?>" data-base-ram="<?= $baseRam ?>" data-base-disk="<?= $baseDisk ?>">
                        <div class="vps-stepper-item" data-key="cpu" data-step="1" data-min="<?= $baseCpu ?>" data-max="16" data-unit="vCPU" data-price-step="35000">
                            <label><i class="fas fa-microchip me-2"></i>CPU</label>
                            <div class="vps-stepper-control">
                                <button type="button" class="vps-step-btn" data-action="minus">−</button>
                                <strong data-value><?= $baseCpu ?></strong>
                                <button type="button" class="vps-step-btn" data-action="plus">+</button>
                            </div>
                        </div>
                        <div class="vps-stepper-item" data-key="ram" data-step="2" data-min="<?= $baseRam ?>" data-max="64" data-unit="GB" data-price-step="28000">
                            <label><i class="fas fa-memory me-2"></i>RAM</label>
                            <div class="vps-stepper-control">
                                <button type="button" class="vps-step-btn" data-action="minus">−</button>
                                <strong data-value><?= $baseRam ?></strong>
                                <button type="button" class="vps-step-btn" data-action="plus">+</button>
                            </div>
                        </div>
                        <div class="vps-stepper-item" data-key="disk" data-step="20" data-min="<?= $baseDisk ?>" data-max="500" data-unit="GB NVMe" data-price-step="9000">
                            <label><i class="fas fa-hdd me-2"></i>Disk</label>
                            <div class="vps-stepper-control">
                                <button type="button" class="vps-step-btn" data-action="minus">−</button>
                                <strong data-value><?= $baseDisk ?></strong>
                                <button type="button" class="vps-step-btn" data-action="plus">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="vps-config-card mb-4">
                    <h3 class="h6 fw-bold mb-3">Chu kỳ thanh toán</h3>
                    <div class="vps-radio-grid" data-billing-list>
                        <?php foreach ($billingOptions as $idx => $bill): ?>
                            <label class="vps-radio-card <?= $idx === 1 ? 'is-selected' : '' ?>">
                                <input type="radio" name="billing_cycle" value="<?= e($bill['key']) ?>" data-multiplier="<?= (int) $bill['multiplier'] ?>" <?= $idx === 1 ? 'checked' : '' ?>>
                                <span><?= e($bill['label']) ?></span>
                                <small><?= e($bill['hint']) ?></small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="vps-config-card mb-4">
                    <h3 class="h6 fw-bold mb-3">Chọn hệ điều hành</h3>
                    <div class="vps-os-list" data-os-list>
                        <?php foreach ($osOptions as $idx => $os): ?>
                            <label class="vps-os-item <?= $idx === 0 ? 'is-selected' : '' ?>">
                                <input type="radio" name="os" value="<?= e($os['name']) ?>" <?= $idx === 0 ? 'checked' : '' ?>>
                                <span class="vps-os-icon"><i class="<?= e($os['icon']) ?>"></i></span>
                                <span class="vps-os-name"><?= e($os['name']) ?></span>
                                <span class="vps-os-check"><i class="fas fa-check-circle"></i></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="vps-config-card">
                    <h3 class="h6 fw-bold mb-3">Dịch vụ bổ sung</h3>
                    <div class="vps-addon-grid" data-addon-list>
                        <?php foreach ($addons as $addon): ?>
                            <label class="vps-addon-item">
                                <input type="checkbox" name="addons[]" value="<?= e($addon['key']) ?>" data-addon-price="<?= (float) $addon['price'] ?>">
                                <div>
                                    <strong><i class="<?= e($addon['icon']) ?> me-2"></i><?= e($addon['title']) ?></strong>
                                    <p class="mb-0 text-secondary small">Phù hợp cho môi trường production ổn định.</p>
                                </div>
                                <span class="vps-addon-price"><?= format_money((float) $addon['price']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <aside class="vps-summary-card" data-summary-card>
                    <h3 class="h5 fw-bold mb-1">Tóm tắt thanh toán</h3>
                    <p class="text-secondary small mb-3">Chi phí minh bạch, thanh toán bảo mật.</p>

                    <div class="vps-summary-row"><span>Gói VPS</span><b><?= e($product['name']) ?></b></div>
                    <div class="vps-summary-row"><span>Giá gốc</span><b data-base-price="<?= (float) $product['price'] ?>"><?= format_money((float) $product['price']) ?></b></div>
                    <div class="vps-summary-row"><span>Cycle</span><b data-selected-cycle>3 tháng</b></div>
                    <div class="vps-summary-row"><span>Nâng cấp cấu hình</span><b data-upgrade-price>0 ₫</b></div>
                    <div class="vps-summary-row"><span>Dịch vụ bổ sung</span><b data-addon-total>0 ₫</b></div>

                    <div class="vps-coupon-box mt-3">
                        <label class="form-label small fw-semibold">Mã giảm giá</label>
                        <div class="input-group">
                            <input class="form-control" placeholder="Nhập coupon">
                            <button class="btn btn-outline-secondary" type="button">Áp dụng</button>
                        </div>
                    </div>

                    <div class="vps-total-box mt-3">
                        <span>Tổng cộng</span>
                        <strong data-grand-total><?= format_money((float) $product['price'] * 3) ?></strong>
                    </div>

                    <!-- Added: wallet payment status box so users can see whether current balance is enough. -->
                    <div
                        class="vps-wallet-box mt-3 <?= e($walletBoxStateClass) ?>"
                        data-wallet-box
                        data-wallet-balance="<?= e((string) $currentWalletBalance) ?>"
                        data-wallet-auth="<?= $isAuthenticated ? '1' : '0' ?>"
                    >
                        <div class="vps-wallet-box-head">
                            <span><i class="fas fa-wallet me-2"></i>Số dư ví hiện có</span>
                            <strong><?= format_money($currentWalletBalance) ?></strong>
                        </div>
                        <p class="mb-0 small" data-wallet-note><?= e($walletStatusMessage) ?></p>
                        <?php if ($isAuthenticated): ?>
                            <a href="<?= base_url('profile?tab=wallet-log') ?>" class="btn btn-sm btn-outline-primary mt-3">
                                <i class="fas fa-plus me-1"></i>Nạp thêm số dư
                            </a>
                        <?php endif; ?>
                    </div>

                    <button
                        type="button"
                        class="btn vps-pay-btn w-100 mt-3"
                        data-open-confirm
                        <?= $walletCheckoutDisabled ? 'disabled' : '' ?>
                    >
                        <i class="fas fa-credit-card me-2"></i><span data-pay-button-text>Thanh toán ngay</span>
                    </button>

                    <div class="vps-trust-note mt-3">
                        <small>Thanh toán đang dùng ví nội bộ của tài khoản · Cam kết hoàn tiền 7 ngày · Giám sát 24/7 · Hỗ trợ kỹ thuật ưu tiên</small>
                    </div>
                </aside>
            </div>
            </div>
        </form>

        <hr class="my-5">
        <h3 class="h5 fw-bold mb-3">Gói liên quan</h3>
        <div class="row g-3">
            <?php foreach ($related as $item): ?>
                <div class="col-sm-6 col-lg-3">
                    <article class="vps-related-card h-100">
                        <h4><?= e($item['name']) ?></h4>
                        <p class="mb-2"><?= format_money((float) $item['price']) ?>/tháng</p>
                        <a href="<?= base_url('products/show/' . $item['id']) ?>" class="btn btn-sm vps-btn-outline"><i class="fas fa-arrow-right me-1"></i>Xem chi tiết</a>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($isCloudProduct)): ?>
    <?php
    $vpsGuideSectionId = 'product-vps-guides';
    $vpsGuideVariant = 'detail';
    $vpsGuideEyebrow = 'Ứng dụng phù hợp';
    $vpsGuideTitle = 'Sau khi mua gói này, bạn có thể triển khai gì?';
    $vpsGuideSubtitle = 'Phần hướng dẫn ngắn này giúp khách hàng hình dung ngay các use case phổ biến của Cloud VPS như website, automation, game server, bảo mật và giám sát vận hành.';
    $vpsGuideCtaLabel = 'Xem thêm gói cloud';
    $vpsGuideCtaUrl = base_url('products');
    require __DIR__ . '/../partials/vps_guides.php';
    ?>
<?php endif; ?>

<!-- Confirm modal -->
<div class="modal fade vps-state-modal" id="vpsConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="vps-state-icon is-confirm"><i class="fas fa-circle-question"></i></div>
                <h4 class="h5 fw-bold mt-3">Xác nhận thanh toán</h4>
                <p class="text-secondary mb-3">Bạn muốn tiếp tục thanh toán gói VPS này ngay bây giờ?</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button class="btn btn-light" data-bs-dismiss="modal">Xem lại</button>
                    <!-- Added: submit real checkout form instead of fake success modal. -->
                    <button class="btn vps-btn-solid" type="submit" form="productCheckoutForm" data-start-payment <?= $walletCheckoutDisabled ? 'disabled' : '' ?>><i class="fas fa-credit-card me-1"></i><span data-start-payment-text>Bắt đầu thanh toán</span></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Processing modal -->
<div class="modal fade vps-state-modal" id="vpsProcessingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="vps-state-icon is-loading"><span></span></div>
                <h4 class="h5 fw-bold mt-3">Đang xử lý</h4>
                <p class="text-secondary mb-0">Hệ thống đang tạo đơn và kiểm tra cấu hình của bạn...</p>
            </div>
        </div>
    </div>
</div>

<!-- Result modals -->
<div class="modal fade vps-state-modal" id="vpsSuccessModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="vps-state-icon is-success"><i class="fas fa-circle-check"></i></div>
                <h4 class="h5 fw-bold mt-3">Thanh toán thành công</h4>
                <p class="text-secondary mb-3">Đơn hàng đã được ghi nhận. Bạn sẽ nhận thông tin kích hoạt trong vài phút.</p>
                <button class="btn vps-btn-solid" data-bs-dismiss="modal">Đã hiểu</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade vps-state-modal" id="vpsErrorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="vps-state-icon is-error"><i class="fas fa-circle-xmark"></i></div>
                <h4 class="h5 fw-bold mt-3">Thanh toán thất bại</h4>
                <p class="text-secondary mb-3">Có lỗi xảy ra trong quá trình xử lý. Vui lòng thử lại sau ít phút.</p>
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
