<?php use App\Core\Auth; ?>
<?php
$siteName = (string) app_setting('site_name', config('app.name', 'Digital Market Pro'));
$siteFavicon = trim((string) app_setting('site_favicon', ''));
$siteFaviconUrl = $siteFavicon !== '' ? base_url('uploads/' . ltrim($siteFavicon, '/')) : base_url('images/logo/zenox.png');
$publicRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public';
$currentContentPath = isset($content) ? realpath((string) $content) : false;
$homeViewPath = realpath(__DIR__ . '/../home/index.php');
$isHomePage = $currentContentPath !== false && $homeViewPath !== false && $currentContentPath === $homeViewPath;
$aiWidgetCssVersion = @filemtime($publicRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'ai-chat-widget.css') ?: '1';
$aiWidgetJsVersion = @filemtime($publicRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'ai-chat-widget.js') ?: '1';
$headerFeedbackCssVersion = @filemtime($publicRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'header-feedback.css') ?: '1';
$headerFeedbackJsVersion = @filemtime($publicRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'header-feedback.js') ?: '1';
$homePopupCssVersion = @filemtime($publicRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'home-popup.css') ?: '1';
$homePopupJsVersion = @filemtime($publicRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'home-popup.js') ?: '1';
$profileBannerStudioCssVersion = @filemtime($publicRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'profile-banner-studio.css') ?: '1';
$profileBannerStudioJsVersion = @filemtime($publicRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'profile-banner-studio.js') ?: '1';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $siteName) ?></title>
    <link rel="icon" href="<?= e($siteFaviconUrl) ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <?php if (Auth::check() || !empty($userPanel)): ?>
        <link rel="stylesheet" href="<?= base_url('assets/css/user-account.css') ?>">
        <link rel="stylesheet" href="<?= base_url('assets/css/profile-banner-studio.css?v=' . $profileBannerStudioCssVersion) ?>">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
    <?php endif; ?>
    <?php if (!empty($vpsUi)): ?>
        <link rel="stylesheet" href="<?= base_url('assets/css/products.css') ?>">
    <?php endif; ?>
    <?php if (empty($userPanel)): ?>
        <!-- Added: Phase 1 customer AI widget styles on public storefront pages. -->
        <link rel="stylesheet" href="<?= base_url('assets/css/ai-chat-widget.css?v=' . $aiWidgetCssVersion) ?>">
        <link rel="stylesheet" href="<?= base_url('assets/css/header-feedback.css?v=' . $headerFeedbackCssVersion) ?>">
    <?php endif; ?>
    <?php if ($isHomePage): ?>
        <link rel="stylesheet" href="<?= base_url('assets/css/home-popup.css?v=' . $homePopupCssVersion) ?>">
    <?php endif; ?>
</head>
<body class="site-body<?= !empty($vpsUi) ? ' site-body-products' : '' ?><?= !empty($userPanel) ? ' site-body-user' : '' ?>">
<?php require __DIR__ . '/../partials/header.php'; ?>
<main class="site-main">
    <?php require __DIR__ . '/../partials/flash.php'; ?>
    <?php require $content; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
<?php if (empty($userPanel)): ?>
    <?php
    $headerFeedbackProductId = 0;
    $headerFeedbackProductName = '';
    $headerFeedbackCandidate = null;

    if (isset($aiWidgetProduct) && is_array($aiWidgetProduct)) {
        $headerFeedbackCandidate = $aiWidgetProduct;
    } elseif (isset($product) && is_array($product)) {
        $headerFeedbackCandidate = $product;
    }

    if (is_array($headerFeedbackCandidate)) {
        $headerFeedbackProductId = (int) ($headerFeedbackCandidate['id'] ?? 0);
        $headerFeedbackProductName = (string) ($headerFeedbackCandidate['name'] ?? '');
    }

    require __DIR__ . '/../partials/header_feedback_modal.php';
    ?>
    <?php $aiWidgetProduct = isset($aiWidgetProduct) && is_array($aiWidgetProduct) ? $aiWidgetProduct : null; ?>
    <?php require __DIR__ . '/../partials/ai_chat_widget.php'; ?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<?php if (!empty($userPanel)): ?>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
    <script src="<?= base_url('assets/js/profile-banner-studio.js?v=' . $profileBannerStudioJsVersion) ?>"></script>
    <script src="<?= base_url('assets/js/user-account.js') ?>"></script>
<?php endif; ?>
<?php if (!empty($vpsUi)): ?>
    <script src="<?= base_url('assets/js/vps-products.js') ?>"></script>
<?php endif; ?>
<?php if (empty($userPanel)): ?>
    <!-- Added: Phase 1 customer AI widget behavior on public storefront pages. -->
    <script src="<?= base_url('assets/js/header-feedback.js?v=' . $headerFeedbackJsVersion) ?>"></script>
    <script src="<?= base_url('assets/js/ai-chat-widget.js?v=' . $aiWidgetJsVersion) ?>"></script>
<?php endif; ?>
<?php if ($isHomePage): ?>
    <script src="<?= base_url('assets/js/home-popup.js?v=' . $homePopupJsVersion) ?>"></script>
<?php endif; ?>
</body>
</html>
