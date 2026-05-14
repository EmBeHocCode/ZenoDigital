<?php
$siteName = app_site_name();
$footerText = 'Nền tảng Cloud VPS & Cloud Server hiện đại, an toàn và dễ mở rộng.';
$primaryContactEmail = 'meowshopsite@gmail.com';
$secondaryContactEmail = 'mieowzeno@outlook.com.vn';
$contactEmail = $primaryContactEmail;
$contactPhone = trim((string) app_setting('contact_phone', '0888941220'));
$contactAddress = trim((string) app_setting('address', 'TP.HCM, Việt Nam'));
$siteLogo = trim((string) app_setting('site_logo', ''));
$siteLogoUrl = $siteLogo !== '' ? base_url('uploads/' . ltrim($siteLogo, '/')) : base_url('images/logo/zenox.png');
$contactPhoneDigits = preg_replace('/\D+/', '', $contactPhone) ?? '';
$zaloUrl = trim((string) app_setting('zalo_url', ''));
$facebookUrl = trim((string) app_setting('facebook_url', ''));
$youtubeUrl = trim((string) app_setting('youtube_url', ''));
$telegramUrl = trim((string) app_setting('telegram_url', ''));
$tiktokUrl = trim((string) app_setting('tiktok_url', ''));
$consultUrl = $zaloUrl !== ''
    ? $zaloUrl
    : ($contactPhoneDigits !== '' ? 'https://zalo.me/' . $contactPhoneDigits : 'mailto:' . $contactEmail);

$footerProductLinks = [
    ['label' => 'VPS Giá Rẻ', 'url' => base_url('/#student-vps-plans')],
    ['label' => 'Cloud VPS', 'url' => base_url('/#cloud-vps-plans')],
    ['label' => 'Cloud Server', 'url' => base_url('products?q=server')],
    ['label' => 'Game Server', 'url' => base_url('products?q=game')],
    ['label' => 'AI GPU', 'url' => base_url('products?q=gpu')],
    ['label' => 'SIM Số', 'url' => base_url('products?q=sim')],
];

$footerSupportLinks = [
    ['label' => 'Hướng dẫn sử dụng', 'url' => base_url('/#vps-guides')],
    ['label' => 'FAQ', 'url' => base_url('/#faq')],
    ['label' => 'Ticket hỗ trợ', 'url' => $consultUrl],
    ['label' => 'Liên hệ', 'url' => $consultUrl],
];

$footerPolicyLinks = [
    ['label' => 'Điều khoản dịch vụ', 'url' => base_url('/#faq')],
    ['label' => 'Chính sách bảo mật', 'url' => base_url('/#faq')],
    ['label' => 'Chính sách hoàn tiền', 'url' => base_url('/#faq')],
    ['label' => 'Phương thức thanh toán', 'url' => base_url('/#payment-methods')],
];

$socialLinks = [
    ['label' => 'Facebook', 'icon' => 'fab fa-facebook-f', 'url' => $facebookUrl !== '' ? $facebookUrl : $consultUrl],
    ['label' => 'Zalo OA', 'icon' => 'fas fa-comment-dots', 'url' => $consultUrl],
    ['label' => 'Telegram', 'icon' => 'fab fa-telegram', 'url' => $telegramUrl !== '' ? $telegramUrl : $consultUrl],
    ['label' => 'YouTube', 'icon' => 'fab fa-youtube', 'url' => $youtubeUrl !== '' ? $youtubeUrl : $consultUrl],
    ['label' => 'Email', 'icon' => 'fas fa-envelope', 'url' => 'mailto:' . $contactEmail],
];

$paymentMethods = [
    ['label' => 'VietQR', 'icon' => 'fas fa-qrcode'],
    ['label' => 'MoMo', 'icon' => 'fas fa-wallet'],
    ['label' => 'ZaloPay', 'icon' => 'fas fa-bolt'],
    ['label' => 'Visa', 'icon' => 'fab fa-cc-visa'],
    ['label' => 'Mastercard', 'icon' => 'fab fa-cc-mastercard'],
];

$serviceCommitments = [
    'Kích hoạt tự động trong vài phút',
    'Bảo mật nhiều lớp',
    'Hỗ trợ kỹ thuật 24/7',
    'Hạ tầng ổn định cho production',
];
?>

<section class="site-footer-cta">
    <div class="container">
        <div class="site-footer-cta-inner">
            <div>
                <span class="site-footer-cta-kicker">Cloud VPS cho học tập và vận hành</span>
                <h2>Sẵn sàng triển khai Cloud VPS chỉ từ 35.000đ/tháng?</h2>
                <p>Phù hợp cho học tập, website, bot, game server và các dự án cá nhân.</p>
            </div>
            <div class="site-footer-cta-actions">
                <a class="btn site-footer-cta-primary" href="<?= base_url('/#cloud-vps-plans') ?>">Xem gói VPS</a>
                <a class="btn site-footer-cta-secondary" href="<?= e($consultUrl) ?>" target="_blank" rel="noopener noreferrer">Liên hệ tư vấn</a>
            </div>
        </div>
    </div>
</section>

<footer class="site-footer site-footer-modern">
    <div class="container">
        <div class="site-footer-grid">
            <div class="site-footer-brand">
                <h3 class="site-footer-brand-heading">Giới thiệu thương hiệu</h3>
                <a class="site-footer-logo" href="<?= base_url('/') ?>">
                    <img src="<?= e($siteLogoUrl) ?>" alt="<?= e($siteName) ?>">
                    <span><?= e($siteName) ?></span>
                </a>
                <p><?= e($footerText) ?></p>
                <ul class="site-footer-contact">
                    <li><i class="fas fa-phone"></i><span><?= e($contactPhone) ?></span></li>
                    <li><i class="fas fa-envelope"></i><span>Email: <?= e($contactEmail) ?></span></li>
                    <li><i class="fas fa-envelope-open-text"></i><span>Email phụ: <?= e($secondaryContactEmail) ?></span></li>
                    <li><i class="fas fa-location-dot"></i><span><?= e($contactAddress) ?></span></li>
                </ul>
            </div>

            <div class="site-footer-col">
                <h3>Sản phẩm</h3>
                <ul>
                    <?php foreach ($footerProductLinks as $link): ?>
                        <li><a href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="site-footer-col">
                <h3>Hỗ trợ</h3>
                <ul>
                    <?php foreach ($footerSupportLinks as $link): ?>
                        <li><a href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="site-footer-col">
                <h3>Chính sách</h3>
                <ul>
                    <?php foreach ($footerPolicyLinks as $link): ?>
                        <li><a href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="site-footer-col">
                <h3>Kết nối</h3>
                <div class="site-footer-socials">
                    <?php foreach ($socialLinks as $link): ?>
                        <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($link['label']) ?>">
                            <i class="<?= e($link['icon']) ?>"></i>
                            <span><?= e($link['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <p class="site-footer-social-note">Theo dõi kênh hỗ trợ để nhận cập nhật dịch vụ, ưu đãi cloud và thông báo vận hành.</p>
            </div>
        </div>

        <div class="site-footer-service-row">
            <?php foreach ($serviceCommitments as $commitment): ?>
                <span><i class="fas fa-check"></i><?= e($commitment) ?></span>
            <?php endforeach; ?>
        </div>

        <div class="site-footer-payment-row" id="payment-methods">
            <span class="site-footer-payment-title">Thanh toán hỗ trợ</span>
            <div class="site-footer-payment-logos">
                <?php foreach ($paymentMethods as $method): ?>
                    <span><i class="<?= e($method['icon']) ?>"></i><?= e($method['label']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="site-footer-bottom">
            <span>© <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</span>
            <span>Cloud VPS, dịch vụ số và hỗ trợ kỹ thuật cho sản phẩm thực tế.</span>
        </div>
    </div>
</footer>
