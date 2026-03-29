<?php use App\Core\Auth; ?>
<?php
$siteName = (string) app_setting('site_name', config('app.name', 'Digital Market Pro'));
$siteFavicon = trim((string) app_setting('site_favicon', ''));
$siteFaviconUrl = $siteFavicon !== '' ? base_url('uploads/' . ltrim($siteFavicon, '/')) : base_url('images/logo/zenox.png');
$pageStyles = is_array($pageStyles ?? null) ? $pageStyles : [];
$pageScripts = is_array($pageScripts ?? null) ? $pageScripts : [];
$canUseAdminAi = Auth::can('backoffice.ai');
$adminAiCssPath = BASE_PATH . '/public/assets/css/admin-ai-panel.css';
$adminAiJsPath = BASE_PATH . '/public/assets/js/admin-ai-panel.js';
$adminAiCssUrl = base_url('assets/css/admin-ai-panel.css') . (is_file($adminAiCssPath) ? '?v=' . filemtime($adminAiCssPath) : '');
$adminAiJsUrl = base_url('assets/js/admin-ai-panel.js') . (is_file($adminAiJsPath) ? '?v=' . filemtime($adminAiJsPath) : '');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? ('Admin Dashboard - ' . $siteName)) ?></title>
    <link rel="icon" href="<?= e($siteFaviconUrl) ?>" type="image/x-icon">
    <!-- Added: hydrate saved desktop sidebar state before CSS paints. -->
    <script>
        (function () {
            try {
                if (window.matchMedia('(min-width: 768px)').matches && window.localStorage.getItem('adminSidebarCollapsed') === '1') {
                    document.documentElement.classList.add('admin-sidebar-collapsed');
                }
            } catch (error) {
                /* Ignore localStorage access issues for a graceful fallback. */
            }
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/admin-dashboard.css') ?>">
    <?php if ($canUseAdminAi): ?>
        <link rel="stylesheet" href="<?= e($adminAiCssUrl) ?>">
    <?php endif; ?>
    <?php foreach ($pageStyles as $styleUrl): ?>
        <link rel="stylesheet" href="<?= e((string) $styleUrl) ?>">
    <?php endforeach; ?>
</head>
<body class="admin-body">
<div class="admin-wrapper" data-admin-layout>
    <?php require BASE_PATH . '/app/Views/partials/admin_sidebar.php'; ?>
    <!-- Added: custom mobile overlay for off-canvas sidebar. -->
    <button class="admin-sidebar-overlay" type="button" data-admin-sidebar-overlay aria-label="Đóng menu admin"></button>

    <div class="admin-main">
        <?php require BASE_PATH . '/app/Views/partials/admin_topbar.php'; ?>
        <div class="admin-content-wrap">
            <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
            <?php require $content; ?>
        </div>
    </div>
</div>
<?php if ($canUseAdminAi): ?>
    <?php require BASE_PATH . '/app/Views/partials/admin_ai_panel.php'; ?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<script src="<?= base_url('assets/js/admin-dashboard.js') ?>"></script>
<?php if ($canUseAdminAi): ?>
    <script src="<?= e($adminAiJsUrl) ?>"></script>
<?php endif; ?>
<?php foreach ($pageScripts as $scriptUrl): ?>
    <script src="<?= e((string) $scriptUrl) ?>"></script>
<?php endforeach; ?>
</body>
</html>
