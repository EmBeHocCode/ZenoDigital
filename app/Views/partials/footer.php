<?php
$siteName = app_site_name();

if (!function_exists('zenox_footer_setting')) {
    function zenox_footer_setting(string $key, string $default = ''): string
    {
        $value = trim((string) app_setting($key, ''));

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('zenox_footer_url')) {
    function zenox_footer_url(string $url, string $fallback = ''): string
    {
        $url = trim($url);
        if ($url === '') {
            $url = $fallback;
        }
        if ($url === '') {
            return '';
        }
        if (preg_match('#^(https?://|mailto:|tel:)#i', $url) === 1) {
            return $url;
        }

        return base_url(ltrim($url, '/'));
    }
}

if (!function_exists('zenox_footer_links')) {
    function zenox_footer_links(string $value, string $fallback): array
    {
        $source = trim($value) !== '' ? $value : $fallback;
        $links = [];

        foreach (preg_split('/\R+/', $source) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($label === '') {
                continue;
            }

            $links[] = [
                'label' => $label,
                'url' => zenox_footer_url($url, '#'),
            ];
        }

        return $links;
    }
}

if (!function_exists('zenox_footer_lines')) {
    function zenox_footer_lines(string $value, string $fallback): array
    {
        $source = trim($value) !== '' ? $value : $fallback;
        $items = [];

        foreach (preg_split('/\R+/', $source) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $items[] = $line;
            }
        }

        return $items;
    }
}

if (!function_exists('zenox_footer_icon_lines')) {
    function zenox_footer_icon_lines(string $value, string $fallback): array
    {
        $source = trim($value) !== '' ? $value : $fallback;
        $items = [];

        foreach (preg_split('/\R+/', $source) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            [$label, $icon] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($label !== '') {
                $items[] = ['label' => $label, 'icon' => $icon !== '' ? $icon : 'fas fa-check'];
            }
        }

        return $items;
    }
}

$footerText = zenox_footer_setting('footer_text', 'Nền tảng Cloud VPS & Cloud Server hiện đại, an toàn và dễ mở rộng.');
$primaryContactEmail = zenox_footer_setting('contact_email', 'meowshopsite@gmail.com');
$secondaryContactEmail = zenox_footer_setting('contact_email_secondary', '');
$contactPhone = zenox_footer_setting('contact_phone', '0888941220');
$contactAddress = zenox_footer_setting('address', 'TP.HCM, Việt Nam');
$siteLogo = trim((string) app_setting('site_logo', ''));
$siteLogoUrl = $siteLogo !== '' ? base_url('uploads/' . ltrim($siteLogo, '/')) : base_url('images/logo/zenox.png');
$contactPhoneDigits = preg_replace('/\D+/', '', $contactPhone) ?? '';
$zaloUrl = trim((string) app_setting('zalo_url', ''));
$facebookUrl = trim((string) app_setting('facebook_url', ''));
$youtubeUrl = trim((string) app_setting('youtube_url', ''));
$telegramUrl = trim((string) app_setting('telegram_url', ''));
$consultUrl = $zaloUrl !== ''
    ? $zaloUrl
    : ($contactPhoneDigits !== '' ? 'https://zalo.me/' . $contactPhoneDigits : 'mailto:' . $primaryContactEmail);

$defaultProductLinks = "VPS Giá Rẻ|/#student-vps-plans\nCloud VPS|/#cloud-vps-plans\nCloud Server|products?q=server\nGame Server|products?q=game\nAI GPU|products?q=gpu\nSIM Số|products?q=sim";
$defaultSupportLinks = "Hướng dẫn sử dụng|/#vps-guides\nFAQ|/#faq\nTicket hỗ trợ|{$consultUrl}\nLiên hệ|{$consultUrl}";
$defaultPolicyLinks = "Điều khoản dịch vụ|/#faq\nChính sách bảo mật|/#faq\nChính sách hoàn tiền|/#faq\nPhương thức thanh toán|/#payment-methods";
$defaultCommitments = "Kích hoạt tự động trong vài phút\nBảo mật nhiều lớp\nHỗ trợ kỹ thuật 24/7\nHạ tầng ổn định cho production";
$defaultPayments = "VietQR|fas fa-qrcode\nMoMo|fas fa-wallet\nZaloPay|fas fa-bolt\nVisa|fab fa-cc-visa\nMastercard|fab fa-cc-mastercard";

$footerProductLinks = zenox_footer_links((string) app_setting('footer_product_links', ''), $defaultProductLinks);
$footerSupportLinks = zenox_footer_links((string) app_setting('footer_support_links', ''), $defaultSupportLinks);
$footerPolicyLinks = zenox_footer_links((string) app_setting('footer_policy_links', ''), $defaultPolicyLinks);
$serviceCommitments = zenox_footer_lines((string) app_setting('footer_service_commitments', ''), $defaultCommitments);
$paymentMethods = zenox_footer_icon_lines((string) app_setting('footer_payment_methods', ''), $defaultPayments);

$socialLinks = array_values(array_filter([
    $facebookUrl !== '' ? ['label' => 'Facebook', 'icon' => 'fab fa-facebook-f', 'url' => $facebookUrl] : null,
    ['label' => 'Zalo OA', 'icon' => 'fas fa-comment-dots', 'url' => $consultUrl],
    $telegramUrl !== '' ? ['label' => 'Telegram', 'icon' => 'fab fa-telegram', 'url' => $telegramUrl] : null,
    $youtubeUrl !== '' ? ['label' => 'YouTube', 'icon' => 'fab fa-youtube', 'url' => $youtubeUrl] : null,
    $primaryContactEmail !== '' ? ['label' => 'Email', 'icon' => 'fas fa-envelope', 'url' => 'mailto:' . $primaryContactEmail] : null,
]));

$footerCtaKicker = zenox_footer_setting('footer_cta_kicker', 'Cloud VPS cho học tập và vận hành');
$footerCtaTitle = zenox_footer_setting('footer_cta_title', 'Sẵn sàng triển khai Cloud VPS chỉ từ 35.000đ/tháng?');
$footerCtaDescription = zenox_footer_setting('footer_cta_description', 'Phù hợp cho học tập, website, bot, game server và các dự án cá nhân.');
$footerCtaPrimaryLabel = zenox_footer_setting('footer_cta_primary_label', 'Xem gói VPS');
$footerCtaPrimaryUrl = zenox_footer_url(zenox_footer_setting('footer_cta_primary_url', '/#cloud-vps-plans'));
$footerCtaSecondaryLabel = zenox_footer_setting('footer_cta_secondary_label', 'Liên hệ tư vấn');
$footerCtaSecondaryUrl = zenox_footer_url(zenox_footer_setting('footer_cta_secondary_url', ''), $consultUrl);
$footerSocialNote = zenox_footer_setting('footer_social_note', 'Theo dõi kênh hỗ trợ để nhận cập nhật dịch vụ, ưu đãi cloud và thông báo vận hành.');
$footerBottomNote = zenox_footer_setting('footer_bottom_note', 'Cloud VPS, dịch vụ số và hỗ trợ kỹ thuật cho sản phẩm thực tế.');
?>

<section class="site-footer-cta">
    <div class="container">
        <div class="site-footer-cta-inner">
            <div>
                <span class="site-footer-cta-kicker"><?= e($footerCtaKicker) ?></span>
                <h2><?= e($footerCtaTitle) ?></h2>
                <p><?= e($footerCtaDescription) ?></p>
            </div>
            <div class="site-footer-cta-actions">
                <a class="btn site-footer-cta-primary" href="<?= e($footerCtaPrimaryUrl) ?>"><?= e($footerCtaPrimaryLabel) ?></a>
                <a class="btn site-footer-cta-secondary" href="<?= e($footerCtaSecondaryUrl) ?>"><?= e($footerCtaSecondaryLabel) ?></a>
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
                    <?php if ($contactPhone !== ''): ?>
                        <li><i class="fas fa-phone"></i><span><?= e($contactPhone) ?></span></li>
                    <?php endif; ?>
                    <?php if ($primaryContactEmail !== ''): ?>
                        <li><i class="fas fa-envelope"></i><span>Email: <?= e($primaryContactEmail) ?></span></li>
                    <?php endif; ?>
                    <?php if ($secondaryContactEmail !== ''): ?>
                        <li><i class="fas fa-envelope-open-text"></i><span>Email phụ: <?= e($secondaryContactEmail) ?></span></li>
                    <?php endif; ?>
                    <?php if ($contactAddress !== ''): ?>
                        <li><i class="fas fa-location-dot"></i><span><?= e($contactAddress) ?></span></li>
                    <?php endif; ?>
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
                <p class="site-footer-social-note"><?= e($footerSocialNote) ?></p>
            </div>
        </div>

        <?php if ($serviceCommitments !== []): ?>
            <div class="site-footer-service-row">
                <?php foreach ($serviceCommitments as $commitment): ?>
                    <span><i class="fas fa-check"></i><?= e($commitment) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($paymentMethods !== []): ?>
            <div class="site-footer-payment-row" id="payment-methods">
                <span class="site-footer-payment-title">Thanh toán hỗ trợ</span>
                <div class="site-footer-payment-logos">
                    <?php foreach ($paymentMethods as $method): ?>
                        <span><i class="<?= e($method['icon']) ?>"></i><?= e($method['label']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="site-footer-bottom">
            <span>© <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</span>
            <span><?= e($footerBottomNote) ?></span>
        </div>
    </div>
</footer>
