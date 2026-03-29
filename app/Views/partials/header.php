<?php

use App\Core\Auth;
use App\Models\Category;
use App\Services\ModuleHealthGuardService;

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$activeTab = sanitize_text((string) ($_GET['tab'] ?? 'dashboard'), 30);
$selectedCategoryId = (int) sanitize_text((string) ($_GET['category_id'] ?? ''), 10);
$siteName = (string) app_setting('site_name', config('app.name', 'Digital Market Pro'));
$siteLogo = trim((string) app_setting('site_logo', ''));
$siteLogoUrl = $siteLogo !== '' ? base_url('uploads/' . ltrim($siteLogo, '/')) : base_url('images/logo/zenox.png');
$currentRoleLabel = role_display_name((string) (Auth::user()['role_name'] ?? 'user'));
$currentWalletBalance = (float) (Auth::user()['wallet_balance'] ?? 0);
$contactEmail = trim((string) app_setting('contact_email', 'support@digitalmarket.local'));
$contactPhone = trim((string) app_setting('contact_phone', '0888941220'));
$contactPhoneDigits = preg_replace('/\D+/', '', $contactPhone) ?? '';

$storefrontCloudCategories = [];
$storefrontSecondaryCategories = [];

try {
    global $config;

    if (is_array($config ?? null)) {
        $healthGuard = new ModuleHealthGuardService($config);
        if ($healthGuard->isHealthy('categories')) {
            $categoryGroups = (new Category($config))->storefrontGroups();
            $storefrontCloudCategories = array_values($categoryGroups['cloud'] ?? []);
            $storefrontSecondaryCategories = array_values($categoryGroups['secondary'] ?? []);
        }
    }
} catch (\Throwable $exception) {
    security_log('Không thể nạp menu danh mục storefront', ['error' => $exception->getMessage()]);
}

$primaryCloudCategory = $storefrontCloudCategories[0] ?? null;
$cloudCatalogQuery = [];
if (is_array($primaryCloudCategory) && !empty($primaryCloudCategory['id'])) {
    $cloudCatalogQuery['category_id'] = (int) $primaryCloudCategory['id'];
}

$cloudCatalogUrl = base_url('products' . ($cloudCatalogQuery ? '?' . http_build_query($cloudCatalogQuery) : ''));
$cloudServerQuery = $cloudCatalogQuery;
$cloudServerQuery['q'] = 'server';
$cloudServerUrl = base_url('products?' . http_build_query($cloudServerQuery));
$walletUrl = Auth::check() ? base_url('profile?tab=wallet-log') : base_url('login');
$orderHistoryUrl = Auth::check() ? base_url('profile?tab=history') : base_url('login');
$vpsHistoryUrl = Auth::check() ? base_url('profile?tab=vps-history') : base_url('login');
$serviceHistoryUrl = Auth::check() ? base_url('profile?tab=service-history') : base_url('login');
$affiliateUrl = Auth::check() ? base_url('profile?tab=seller') : base_url('login');
$faqUrl = base_url('/#faq');
$guideUrl = base_url('/#vps-guides');
$aboutUrl = base_url('/#cloud-overview');
$contactUrl = $contactPhoneDigits !== '' ? 'https://zalo.me/' . $contactPhoneDigits : ('mailto:' . $contactEmail);
$homePath = parse_url(base_url('/'), PHP_URL_PATH) ?: '/';
$isHomeActive = rtrim($uriPath, '/') === rtrim($homePath, '/');
$isProductsActive = str_contains($uriPath, '/products');
$isWalletActive = str_contains($uriPath, '/profile') && $activeTab === 'wallet-log';
$isHistoryActive = str_contains($uriPath, '/profile') && in_array($activeTab, ['history', 'vps-history', 'service-history', 'domain-history', 'license-history'], true);
$isAffiliateActive = str_contains($uriPath, '/profile') && $activeTab === 'seller';
?>

<nav class="navbar navbar-expand-xl site-header sticky-top">
    <div class="container">
        <a class="navbar-brand site-brand" href="<?= base_url('/') ?>">
            <img src="<?= e($siteLogoUrl) ?>" alt="<?= e($siteName) ?>" class="site-brand-logo">
            <span class="site-brand-copy">
                <span class="site-brand-text"><?= e($siteName) ?></span>
                <small class="site-brand-tag">Cloud VPS & Cloud Server</small>
            </span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Mở menu điều hướng">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-xl-4 me-xl-auto align-items-xl-center site-nav-list">
                <li class="nav-item">
                    <a class="nav-link <?= $isHomeActive ? 'active' : '' ?>" href="<?= base_url('/') ?>">Trang chủ</a>
                </li>

                <li class="nav-item dropdown site-nav-dropdown">
                    <button class="nav-link dropdown-toggle <?= $isProductsActive ? 'active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Dịch vụ
                    </button>
                    <div class="dropdown-menu site-nav-menu site-nav-menu--services">
                        <div class="site-nav-menu-shell">
                            <div class="site-nav-menu-section">
                                <span class="site-nav-section-label">Cloud trọng tâm</span>
                                <a class="site-nav-service-card <?= $selectedCategoryId === (int) ($primaryCloudCategory['id'] ?? 0) && empty($_GET['q']) ? 'is-active' : '' ?>" href="<?= e($cloudCatalogUrl) ?>">
                                    <strong>Cloud VPS</strong>
                                    <small>Cho website, automation, app nội bộ và workload cần triển khai nhanh.</small>
                                </a>
                                <a class="site-nav-service-card <?= $isProductsActive && str_contains(strtolower((string) ($_GET['q'] ?? '')), 'server') ? 'is-active' : '' ?>" href="<?= e($cloudServerUrl) ?>">
                                    <strong>Cloud Server</strong>
                                    <small>Ưu tiên production, API, database và các hệ thống cần hiệu năng cao hơn.</small>
                                </a>
                            </div>

                            <?php if ($storefrontSecondaryCategories !== []): ?>
                                <div class="site-nav-menu-section">
                                    <span class="site-nav-section-label">Nhóm phụ khác</span>
                                    <div class="site-nav-link-list">
                                        <?php foreach (array_slice($storefrontSecondaryCategories, 0, 6) as $category): ?>
                                            <a class="site-nav-list-link <?= $selectedCategoryId === (int) $category['id'] ? 'is-active' : '' ?>" href="<?= base_url('products?' . http_build_query(['category_id' => (int) $category['id']])) ?>">
                                                <span><?= e((string) $category['name']) ?></span>
                                                <small><?= e((string) ($category['description'] ?? 'Xem danh mục dịch vụ')) ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $isWalletActive ? 'active' : '' ?>" href="<?= e($walletUrl) ?>">Nạp tiền</a>
                </li>

                <li class="nav-item dropdown site-nav-dropdown">
                    <button class="nav-link dropdown-toggle <?= $isHistoryActive ? 'active' : '' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Lịch sử
                    </button>
                    <div class="dropdown-menu site-nav-menu site-nav-menu--compact">
                        <a class="dropdown-item <?= str_contains($uriPath, '/profile') && $activeTab === 'history' ? 'active' : '' ?>" href="<?= e($orderHistoryUrl) ?>">
                            <i class="fas fa-receipt"></i>
                            <span>Lịch sử đơn hàng</span>
                        </a>
                        <a class="dropdown-item <?= str_contains($uriPath, '/profile') && $activeTab === 'vps-history' ? 'active' : '' ?>" href="<?= e($vpsHistoryUrl) ?>">
                            <i class="fas fa-server"></i>
                            <span>Lịch sử Cloud VPS</span>
                        </a>
                        <a class="dropdown-item <?= str_contains($uriPath, '/profile') && $activeTab === 'service-history' ? 'active' : '' ?>" href="<?= e($serviceHistoryUrl) ?>">
                            <i class="fas fa-clock-rotate-left"></i>
                            <span>Lịch sử dịch vụ khác</span>
                        </a>
                    </div>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $isAffiliateActive ? 'active' : '' ?>" href="<?= e($affiliateUrl) ?>">Tiếp thị liên kết</a>
                </li>

                <li class="nav-item dropdown site-nav-dropdown">
                    <button class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Thêm
                    </button>
                    <div class="dropdown-menu site-nav-menu site-nav-menu--compact">
                        <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#storefrontHeaderFeedbackModal">
                            <i class="fas fa-comment-dots"></i>
                            <span>Góp ý</span>
                        </button>
                        <a class="dropdown-item" href="<?= e($guideUrl) ?>">
                            <i class="fas fa-book-open"></i>
                            <span>Hướng dẫn VPS</span>
                        </a>
                        <a class="dropdown-item" href="<?= e($faqUrl) ?>">
                            <i class="fas fa-circle-question"></i>
                            <span>FAQ</span>
                        </a>
                        <a class="dropdown-item" href="<?= e($aboutUrl) ?>">
                            <i class="fas fa-circle-info"></i>
                            <span>Giới thiệu dịch vụ</span>
                        </a>
                        <a class="dropdown-item" href="<?= e($contactUrl) ?>" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-headset"></i>
                            <span>Liên hệ hỗ trợ</span>
                        </a>
                    </div>
                </li>
            </ul>

            <div class="site-nav-actions">
                <button class="btn btn-outline-secondary btn-sm site-header-feedback-btn" type="button" data-bs-toggle="modal" data-bs-target="#storefrontHeaderFeedbackModal">
                    <i class="fas fa-comment-dots"></i>Góp ý
                </button>
                <?php if (Auth::check()): ?>
                    <?php if (Auth::can('backoffice.dashboard')): ?>
                        <a class="btn btn-outline-primary btn-sm site-header-admin-btn" href="<?= base_url('admin') ?>">
                            <i class="fas fa-chart-line me-1"></i>Quản trị
                        </a>
                    <?php endif; ?>

                    <div class="nav-item dropdown ua-user-dropdown">
                        <button class="btn ua-user-trigger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= !empty(Auth::user()['avatar']) ? base_url('uploads/' . Auth::user()['avatar']) : base_url('assets/images/avatar-default.svg') ?>" alt="Avatar" class="ua-user-avatar">
                            <span class="ua-user-meta">
                                <strong><?= e(Auth::user()['full_name']) ?></strong>
                                <small><?= e($currentRoleLabel) ?></small>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end ua-user-menu">
                            <li class="ua-user-menu-head">
                                <img src="<?= !empty(Auth::user()['avatar']) ? base_url('uploads/' . Auth::user()['avatar']) : base_url('assets/images/avatar-default.svg') ?>" alt="Avatar" class="ua-user-avatar-lg">
                                <div>
                                    <strong><?= e(Auth::user()['full_name']) ?></strong>
                                    <p><?= e(Auth::user()['email']) ?></p>
                                    <span class="ua-balance-pill"><i class="fas fa-wallet"></i> Số dư: <?= format_money($currentWalletBalance) ?></span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item <?= str_contains($uriPath, '/profile') && $activeTab === 'dashboard' ? 'active' : '' ?>" href="<?= base_url('profile?tab=dashboard') ?>">
                                    <i class="fas fa-chart-line"></i><span>Dashboard</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?= str_contains($uriPath, '/profile') && $activeTab === 'profile' ? 'active' : '' ?>" href="<?= base_url('profile?tab=profile') ?>">
                                    <i class="fas fa-id-card"></i><span>Tài khoản</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?= str_contains($uriPath, '/profile') && $activeTab === 'wallet-log' ? 'active' : '' ?>" href="<?= base_url('profile?tab=wallet-log') ?>">
                                    <i class="fas fa-wallet"></i><span>Nạp số dư</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= base_url('profile?tab=seller') ?>">
                                    <i class="fas fa-store"></i><span>Trở thành người bán</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= base_url('profile?tab=api') ?>">
                                    <i class="fas fa-key"></i><span>API Key</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?= str_contains($uriPath, '/profile') && $activeTab === 'security' ? 'active' : '' ?>" href="<?= base_url('profile?tab=security') ?>">
                                    <i class="fas fa-shield-halved"></i><span>Bảo mật</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="<?= base_url('logout') ?>" class="px-3">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-danger btn-sm w-100" type="submit"><i class="fas fa-right-from-bracket me-1"></i>Đăng xuất</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a class="btn btn-outline-primary btn-sm" href="<?= base_url('login') ?>">Đăng nhập</a>
                    <a class="btn btn-primary btn-sm" href="<?= base_url('register') ?>">Bắt đầu ngay</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
