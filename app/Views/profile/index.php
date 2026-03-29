<?php
$avatar = !empty($user['avatar']) ? base_url('uploads/' . $user['avatar']) : base_url('assets/images/avatar-default.svg');
$bannerMedia = trim((string) ($user['banner_media'] ?? ''));
$bannerMediaType = trim((string) ($user['banner_media_type'] ?? ''));
$bannerMediaUrl = $bannerMedia !== '' ? base_url('uploads/' . $bannerMedia) : '';
$hasBannerImage = $bannerMediaUrl !== '' && $bannerMediaType === 'image';
$hasBannerVideo = $bannerMediaUrl !== '' && $bannerMediaType === 'video';
$bannerMeta = is_array($bannerMeta ?? null) ? $bannerMeta : [];
$bannerFitMode = in_array((string) ($bannerMeta['fit_mode'] ?? 'cover'), ['cover', 'contain', 'fill'], true) ? (string) $bannerMeta['fit_mode'] : 'cover';
$bannerHeightMode = in_array((string) ($bannerMeta['height_mode'] ?? 'standard'), ['narrow', 'standard', 'tall'], true) ? (string) $bannerMeta['height_mode'] : 'standard';
$bannerZoom = max(1, min(2.8, (float) ($bannerMeta['zoom'] ?? 1)));
$bannerPositionX = max(0, min(100, (float) ($bannerMeta['position_x'] ?? 50)));
$bannerPositionY = max(0, min(100, (float) ($bannerMeta['position_y'] ?? 50)));
$bannerVideoStart = max(0, (float) ($bannerMeta['video_start'] ?? 0));
$bannerVideoEnd = max(0, (float) ($bannerMeta['video_end'] ?? 0));
$bannerHeightMap = [
    'narrow' => ['desktop' => '184px', 'mobile' => '152px'],
    'standard' => ['desktop' => '220px', 'mobile' => '180px'],
    'tall' => ['desktop' => '276px', 'mobile' => '228px'],
];
$formatBannerCssNumber = static function (float $value): string {
    $formatted = number_format($value, 3, '.', '');
    $formatted = preg_replace('/(\.\d*?[1-9])0+$/', '$1', $formatted) ?? $formatted;
    $formatted = preg_replace('/\.0+$/', '', $formatted) ?? $formatted;
    return $formatted !== '' ? $formatted : '0';
};
$bannerHeightVars = $bannerHeightMap[$bannerHeightMode] ?? $bannerHeightMap['standard'];
$bannerHeaderStyle = sprintf(
    '--ua-banner-height:%s;--ua-banner-height-mobile:%s;--ua-banner-fit:%s;--ua-banner-pos-x:%s%%;--ua-banner-pos-y:%s%%;--ua-banner-scale:%s;',
    $bannerHeightVars['desktop'],
    $bannerHeightVars['mobile'],
    $bannerFitMode,
    $formatBannerCssNumber($bannerPositionX),
    $formatBannerCssNumber($bannerPositionY),
    $formatBannerCssNumber($bannerZoom)
);
$bannerFitLabels = [
    'cover' => 'Cover',
    'contain' => 'Contain',
    'fill' => 'Fill',
];
$bannerHeightLabels = [
    'narrow' => 'Hẹp',
    'standard' => 'Chuẩn',
    'tall' => 'Cao',
];
$orderCount = count($orders);
$totalSpent = 0.0;
$latestOrderCode = null;
$latestActivity = null;

foreach ($orders as $order) {
    $totalSpent += (float) $order['total_amount'];
    if ($latestActivity === null || strtotime((string) $order['created_at']) > strtotime((string) $latestActivity)) {
        $latestActivity = $order['created_at'];
        $latestOrderCode = (string) $order['order_code'];
    }
}

$completion = 35;
if (!empty($user['phone'])) {
    $completion += 20;
}
if (!empty($user['address'])) {
    $completion += 20;
}
if (!empty($user['avatar'])) {
    $completion += 25;
}
if (normalize_user_gender((string) ($user['gender'] ?? 'unknown')) !== 'unknown') {
    $completion += 10;
}
if (normalize_birth_date((string) ($user['birth_date'] ?? '')) !== null) {
    $completion += 10;
}

$activeTab = sanitize_text((string) ($_GET['tab'] ?? 'dashboard'), 30);
$validTabs = ['dashboard', 'profile', 'password', 'security', 'wallet-log', 'activity-log', 'history', 'vps-history', 'domain-history', 'license-history', 'service-history', 'api', 'seller'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'dashboard';
}

$twoFactorEnabled = !empty($twoFactorEnabled);
$setupKey = (string) ($twoFactorSetupKey ?? '');
$otpAuthUri = (string) ($twoFactorOtpAuthUri ?? '');
$backupStats = is_array($backupStats ?? null) ? $backupStats : ['total' => 0, 'available' => 0];
$freshBackupCodes = is_array($freshBackupCodes ?? null) ? $freshBackupCodes : [];
$loginActivities = is_array($loginActivities ?? null) ? $loginActivities : [];
$activeSessions = is_array($activeSessions ?? null) ? $activeSessions : [];
$resetPending = !empty($resetPending);
$resetExpiresLabel = (string) ($resetExpiresLabel ?? '');
$twoFactorConfirmedAtLabel = !empty($user['two_factor_confirmed_at']) ? date('d/m/Y H:i', strtotime((string) $user['two_factor_confirmed_at'])) : 'Chưa bật';
$twoFactorLastUsedAtLabel = !empty($user['two_factor_last_used_at']) ? date('d/m/Y H:i', strtotime((string) $user['two_factor_last_used_at'])) : 'Chưa có';
$rankSummary = is_array($rankSummary ?? null) ? $rankSummary : [];
$rankLabelMap = [
    'common' => 'Common',
    'uncommon' => 'Uncommon',
    'rare' => 'Rare',
    'epic' => 'Epic',
    'legendary' => 'Legendary',
    'mythic' => 'Mythic',
    'starter' => 'Common',
    'silver' => 'Uncommon',
    'gold' => 'Rare',
    'platinum' => 'Epic',
    'diamond' => 'Legendary',
];
$rankKeyAliases = [
    'starter' => 'common',
    'silver' => 'uncommon',
    'gold' => 'rare',
    'platinum' => 'epic',
    'diamond' => 'legendary',
];
$currentRankRaw = is_array($rankSummary['rank'] ?? null) ? $rankSummary['rank'] : ['key' => 'common', 'label' => 'Common', 'discount_percent' => 0, 'min_points' => 0];
$currentRankKeyRaw = strtolower(trim((string) ($currentRankRaw['key'] ?? 'common')));
$currentRankKey = $rankKeyAliases[$currentRankKeyRaw] ?? ($currentRankKeyRaw !== '' ? $currentRankKeyRaw : 'common');
$currentRank = $currentRankRaw;
$currentRank['key'] = $currentRankKey;
$currentRank['label'] = (string) ($rankLabelMap[$currentRankKey] ?? ($currentRankRaw['label'] ?? 'Common'));
$nextRankRaw = is_array($rankSummary['next_rank'] ?? null) ? $rankSummary['next_rank'] : null;
$nextRank = null;
if ($nextRankRaw !== null) {
    $nextRankKeyRaw = strtolower(trim((string) ($nextRankRaw['key'] ?? '')));
    $nextRankKey = $rankKeyAliases[$nextRankKeyRaw] ?? $nextRankKeyRaw;
    $nextRank = $nextRankRaw;
    $nextRank['key'] = $nextRankKey;
    $nextRank['label'] = (string) ($rankLabelMap[$nextRankKey] ?? ($nextRankRaw['label'] ?? ucfirst($nextRankKey)));
}
$loyaltyPoints = (int) ($rankSummary['points'] ?? 0);
$loyaltyProgress = (int) ($rankSummary['progress_percent'] ?? 0);
$pointsToNext = (int) ($rankSummary['points_to_next'] ?? 0);
$rankCoupons = is_array($rankSummary['coupons'] ?? null) ? $rankSummary['coupons'] : [];
$walletSummary = is_array($walletSummary ?? null) ? $walletSummary : [];
$walletTransactions = is_array($walletTransactions ?? null) ? $walletTransactions : [];
$roleName = strtolower(trim((string) ($user['role_name'] ?? 'user')));
$roleLabel = role_display_name($roleName);
$profileFullName = (string) old('full_name', (string) ($user['full_name'] ?? ''));
$profilePhone = (string) old('phone', (string) ($user['phone'] ?? ''));
$profileAddress = (string) old('address', (string) ($user['address'] ?? ''));
$userGender = normalize_user_gender((string) old('gender', (string) ($user['gender'] ?? 'unknown')));
$userGenderLabel = user_gender_label($userGender);
$userBirthDate = normalize_birth_date((string) old('birth_date', (string) ($user['birth_date'] ?? '')));
$userBirthDateLabel = $userBirthDate !== null ? date('d/m/Y', strtotime($userBirthDate)) : 'Chưa cập nhật';
$userAge = calculate_age_from_birth_date($userBirthDate);
$isAdminRole = $roleName === 'admin';
$rankThemeClass = 'ua-rank-theme-' . preg_replace('/[^a-z0-9_-]/', '', $currentRankKey);
$rankImageUrl = base_url('assets/images/rank/' . $currentRankKey . '.svg');
$accountTitle = $isAdminRole ? 'Không gian tài khoản dành cho quản trị viên' : 'Không gian tài khoản dành cho khách hàng';
$accountSubtitle = $isAdminRole
    ? 'Theo dõi hồ sơ, bảo mật và truy cập nhanh tới khu vực vận hành hệ thống trong cùng một workspace.'
    : 'Theo dõi số dư, giao dịch, hồ sơ và bảo mật trong giao diện account center hiện đại, tối ưu cho thao tác hàng ngày.';
$rankCardHint = $isAdminRole ? 'Bạn đang đăng nhập với quyền quản trị hệ thống.' : ('Huy hiệu hiện tại của bạn đang ở cấp ' . $currentRank['label'] . '.');
$currentBalance = (float) ($walletSummary['current_balance'] ?? ($user['wallet_balance'] ?? 0));
$totalDeposited = (float) ($walletSummary['total_deposit'] ?? 0);
// Added: "Đã chi" now follows wallet reality, staying consistent with total deposit and current balance.
$walletSpent = (float) ($walletSummary['display_spent'] ?? max(0, $totalDeposited - $currentBalance));
$walletDepositCount = (int) ($walletSummary['deposit_count'] ?? 0);
$latestDepositAt = !empty($walletSummary['latest_deposit_at']) ? date('d/m/Y H:i', strtotime((string) $walletSummary['latest_deposit_at'])) : 'Chưa có giao dịch nạp';
$latestWalletActivityAt = !empty($walletSummary['latest_activity_at'])
    ? date('d/m/Y H:i', strtotime((string) $walletSummary['latest_activity_at']))
    : ($latestActivity ?? $latestDepositAt);
$walletPaymentMethodLabels = [
    'bank_transfer' => 'Chuyển khoản',
    'momo' => 'MoMo',
    'zalopay' => 'ZaloPay',
    'card' => 'Thẻ cào',
    'wallet' => 'Ví nội bộ',
];
$walletPaymentMethodIcons = [
    'bank_transfer' => 'fa-building-columns',
    'momo' => 'fa-mobile-screen-button',
    'zalopay' => 'fa-qrcode',
    'card' => 'fa-credit-card',
    'wallet' => 'fa-wallet',
];
$walletStatusLabels = [
    'completed' => 'Hoàn tất',
    'pending' => 'Đang xử lý',
    'failed' => 'Thất bại',
];
$walletDirectionLabels = [
    'credit' => 'Cộng',
    'debit' => 'Trừ',
];
$walletAmountOld = old('wallet_amount', '100000');
$walletMethodOld = old('wallet_payment_method', 'bank_transfer');
$walletNoteOld = old('wallet_note', '');
?>

<section class="ua-account-hero py-4 py-lg-5">
    <div class="container">
        <div class="ua-panel">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <span class="ua-kicker">User Account Center</span>
                    <h1 class="ua-title"><?= e($accountTitle) ?></h1>
                    <p class="ua-subtitle"><?= e($accountSubtitle) ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Added: quick wallet top-up entry point. -->
                        <button class="btn btn-success" type="button" data-ua-tab="wallet-log">Nạp số dư</button>
                        <button class="btn btn-primary" type="button" data-ua-tab="profile">Cập nhật hồ sơ</button>
                        <button class="btn btn-outline-secondary" type="button" data-ua-tab="security">Bảo mật tài khoản</button>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="ua-profile-mini">
                        <div class="d-flex gap-3 align-items-center mb-2">
                            <img src="<?= $avatar ?>" class="ua-user-avatar-lg" alt="Avatar">
                            <div>
                                <h2 class="h5 fw-bold mb-0"><?= e($user['full_name']) ?></h2>
                                <p class="small text-secondary mb-0"><?= e($user['email']) ?></p>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="ua-chip"><?= e($roleLabel) ?></span>
                            <span class="ua-chip is-success">Đang hoạt động</span>
                            <span class="ua-chip"><?= e((string) ($currentRank['label'] ?? 'Common')) ?></span>
                        </div>
                        <div class="small text-secondary">
                            <div><strong>Thành viên từ:</strong> <?= e(substr((string) ($user['created_at'] ?? date('Y-m-d')), 0, 10)) ?></div>
                            <div><strong>Hoạt động gần đây:</strong> <?= e($latestActivity ?? 'Chưa có giao dịch') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ua-stat-grid mt-3">
                <article class="ua-stat-card">
                    <div class="ua-stat-head">
                        <span class="ua-stat-label">Số dư</span>
                        <i class="fas fa-wallet ua-stat-icon"></i>
                    </div>
                    <p class="ua-stat-value"><?= format_money($currentBalance) ?></p>
                    <p class="ua-stat-hint"><?= $currentBalance > 0 ? 'Sẵn sàng dùng số dư cho các giao dịch tiếp theo.' : 'Bạn chưa có số dư. Hãy nạp tiền để sử dụng ví.' ?></p>
                    <button class="btn btn-sm btn-outline-primary mt-3" type="button" data-ua-tab="wallet-log">
                        <i class="fas fa-plus me-1"></i>Nạp ngay
                    </button>
                </article>

                <article class="ua-stat-card">
                    <div class="ua-stat-head">
                        <span class="ua-stat-label">Tổng nạp</span>
                        <i class="fas fa-arrow-up ua-stat-icon"></i>
                    </div>
                    <p class="ua-stat-value"><?= format_money($totalDeposited) ?></p>
                    <p class="ua-stat-hint"><?= $walletDepositCount > 0 ? ('Đã có ' . number_format($walletDepositCount) . ' giao dịch nạp ví.') : 'Chưa phát sinh giao dịch nạp ví.' ?></p>
                </article>

                <article class="ua-stat-card">
                    <div class="ua-stat-head">
                        <span class="ua-stat-label">Đã chi</span>
                        <i class="fas fa-arrow-down ua-stat-icon"></i>
                    </div>
                    <p class="ua-stat-value"><?= format_money($walletSpent) ?></p>
                    <p class="ua-stat-hint"><?= $walletSpent > 0 ? 'Số này được tính theo phần tiền đã thực sự rời khỏi ví.' : 'Chưa có khoản thanh toán nào bị trừ khỏi ví.' ?></p>
                </article>

                <article class="ua-stat-card ua-rank-stat-card <?= e($rankThemeClass) ?>">
                    <div class="ua-stat-head">
                        <span class="ua-stat-label">Cấp bậc</span>
                        <i class="fas fa-medal ua-stat-icon"></i>
                    </div>
                    <!-- Added: rank artwork for each tier in the account dashboard. -->
                    <div class="ua-rank-stat-body">
                        <img src="<?= e($rankImageUrl) ?>" alt="<?= e((string) $currentRank['label']) ?>" class="ua-rank-image ua-rank-image-sm">
                        <p class="ua-stat-value"><?= e((string) ($currentRank['label'] ?? 'Common')) ?></p>
                    </div>
                    <p class="ua-stat-hint"><?= e($rankCardHint) ?></p>
                </article>
            </div>

            <div class="ua-seller-banner">
                <div>
                    <h3 class="h5 fw-bold mb-1"><i class="fas fa-store me-2"></i>Bạn có muốn bán hàng không?</h3>
                    <p class="mb-0 text-secondary">Mở gian hàng cá nhân, quản lý sản phẩm và tăng doanh thu trên nền tảng.</p>
                </div>
                <button type="button" class="btn btn-primary" data-ua-tab="seller">Trở thành người bán hàng</button>
            </div>

            <div class="ua-tabs">
                <button class="ua-tab <?= $activeTab === 'dashboard' ? 'is-active' : '' ?>" type="button" data-ua-tab="dashboard">Dashboard</button>
                <button class="ua-tab <?= $activeTab === 'profile' ? 'is-active' : '' ?>" type="button" data-ua-tab="profile">Hồ sơ</button>
                <button class="ua-tab <?= $activeTab === 'password' ? 'is-active' : '' ?>" type="button" data-ua-tab="password">Đổi mật khẩu</button>
                <button class="ua-tab <?= $activeTab === 'security' ? 'is-active' : '' ?>" type="button" data-ua-tab="security">Bảo mật 2FA</button>
                <button class="ua-tab <?= $activeTab === 'wallet-log' ? 'is-active' : '' ?>" type="button" data-ua-tab="wallet-log">Biến động số dư</button>
                <button class="ua-tab <?= $activeTab === 'activity-log' ? 'is-active' : '' ?>" type="button" data-ua-tab="activity-log">Nhật ký hoạt động</button>
                <button class="ua-tab <?= $activeTab === 'history' ? 'is-active' : '' ?>" type="button" data-ua-tab="history">Lịch sử mua hàng</button>
                <button class="ua-tab <?= $activeTab === 'vps-history' ? 'is-active' : '' ?>" type="button" data-ua-tab="vps-history">Lịch sử mua VPS</button>
                <button class="ua-tab <?= $activeTab === 'domain-history' ? 'is-active' : '' ?>" type="button" data-ua-tab="domain-history">Lịch sử mua miền</button>
                <button class="ua-tab <?= $activeTab === 'license-history' ? 'is-active' : '' ?>" type="button" data-ua-tab="license-history">Lịch sử mua License</button>
                <button class="ua-tab <?= $activeTab === 'service-history' ? 'is-active' : '' ?>" type="button" data-ua-tab="service-history">Lịch sử đặt dịch vụ</button>
            </div>
        </div>

        <div class="ua-section <?= $activeTab === 'dashboard' ? 'is-visible' : '' ?> mt-4" data-ua-section="dashboard">
            <div class="ua-card p-4">
                <h3 class="h5 fw-bold mb-2">Tổng quan tài khoản</h3>
                <p class="text-secondary mb-0">Chọn tab phía trên để truy cập nhanh hồ sơ, bảo mật và lịch sử giao dịch.</p>
            </div>
        </div>

        <div class="ua-section <?= $activeTab === 'profile' ? 'is-visible' : '' ?> mt-4" data-ua-section="profile">
            <div class="ua-content-grid">
                <div>
                    <div class="ua-form-card mb-3">
                        <div
                            class="ua-profile-header mb-3"
                            data-banner-header-root
                            data-banner-current-url="<?= e($bannerMediaUrl) ?>"
                            data-banner-current-type="<?= e($bannerMediaType) ?>"
                            data-banner-fit-mode="<?= e($bannerFitMode) ?>"
                            data-banner-height-mode="<?= e($bannerHeightMode) ?>"
                            data-banner-zoom="<?= e((string) $bannerZoom) ?>"
                            data-banner-position-x="<?= e((string) $bannerPositionX) ?>"
                            data-banner-position-y="<?= e((string) $bannerPositionY) ?>"
                            data-banner-video-start="<?= e((string) $bannerVideoStart) ?>"
                            data-banner-video-end="<?= e((string) $bannerVideoEnd) ?>"
                            style="<?= e($bannerHeaderStyle) ?>"
                        >
                            <?php if ($hasBannerImage): ?>
                                <img src="<?= e($bannerMediaUrl) ?>" alt="Banner" class="ua-profile-header-media" data-banner-header-image data-banner-header-media>
                            <?php elseif ($hasBannerVideo): ?>
                                <video src="<?= e($bannerMediaUrl) ?>" class="ua-profile-header-media" data-banner-header-video data-banner-header-media data-banner-video-start="<?= e((string) $bannerVideoStart) ?>" data-banner-video-end="<?= e((string) $bannerVideoEnd) ?>" muted autoplay loop playsinline></video>
                            <?php endif; ?>
                            <div class="ua-profile-header-overlay" aria-hidden="true"></div>
                            <button type="button" class="ua-banner-edit-btn" data-banner-editor-trigger aria-label="Chỉnh sửa banner">
                                <i class="far fa-edit"></i>
                            </button>
                            <div class="ua-profile-header-content d-flex gap-3 align-items-center">
                                <div class="ua-avatar-wrap">
                                    <img src="<?= $avatar ?>" class="ua-profile-avatar" alt="Avatar" data-avatar-preview>
                                    <button type="button" class="ua-avatar-edit-btn" data-avatar-editor-trigger aria-label="Chỉnh sửa ảnh đại diện">
                                        <i class="far fa-edit"></i>
                                    </button>
                                </div>
                                <div class="ua-profile-identity">
                                    <span class="ua-profile-kicker"><i class="far fa-user me-1"></i>Profile Header</span>
                                    <h3 class="ua-profile-name mb-1"><?= e($user['full_name']) ?></h3>
                                    <p class="ua-profile-email mb-1"><?= e($user['email']) ?></p>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <span class="ua-profile-badge ua-profile-badge-rank <?= e($rankThemeClass) ?>">
                                            <img src="<?= e($rankImageUrl) ?>" alt="<?= e((string) $currentRank['label']) ?>" class="ua-rank-image ua-rank-image-inline">
                                            <?= e((string) ($currentRank['label'] ?? 'Common')) ?>
                                        </span>
                                        <span class="ua-profile-badge ua-profile-badge-active">Đang hoạt động</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form method="post" enctype="multipart/form-data" action="<?= base_url('profile/update') ?>">
                            <?= csrf_field() ?>
                            <input type="file" class="d-none" name="avatar" accept="image/*" id="ua-avatar-input" data-avatar-crop-input>
                            <input type="file" class="d-none" name="banner" accept="image/*,video/*" id="ua-banner-input" data-banner-editor-input>
                            <input type="hidden" name="banner_fit_mode" value="<?= e($bannerFitMode) ?>" data-banner-meta-input="fit_mode">
                            <input type="hidden" name="banner_zoom" value="<?= e((string) $bannerZoom) ?>" data-banner-meta-input="zoom">
                            <input type="hidden" name="banner_position_x" value="<?= e((string) $bannerPositionX) ?>" data-banner-meta-input="position_x">
                            <input type="hidden" name="banner_position_y" value="<?= e((string) $bannerPositionY) ?>" data-banner-meta-input="position_y">
                            <input type="hidden" name="banner_height_mode" value="<?= e($bannerHeightMode) ?>" data-banner-meta-input="height_mode">
                            <input type="hidden" name="banner_video_start" value="<?= e((string) $bannerVideoStart) ?>" data-banner-meta-input="video_start">
                            <input type="hidden" name="banner_video_end" value="<?= e((string) $bannerVideoEnd) ?>" data-banner-meta-input="video_end">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Họ tên</label>
                                    <input name="full_name" class="form-control" value="<?= e($profileFullName) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input class="form-control" value="<?= e($user['email']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Số điện thoại</label>
                                    <input name="phone" class="form-control" value="<?= e($profilePhone) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Địa chỉ</label>
                                    <input name="address" class="form-control" value="<?= e($profileAddress) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Giới tính</label>
                                    <select name="gender" class="form-select">
                                        <?php foreach (user_gender_options() as $genderKey => $genderLabel): ?>
                                            <option value="<?= e($genderKey) ?>" <?= $userGender === $genderKey ? 'selected' : '' ?>><?= e($genderLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Nếu chưa muốn cập nhật, bạn có thể giữ ở trạng thái "Chưa xác định".</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Ngày sinh</label>
                                    <input type="date" name="birth_date" class="form-control" value="<?= e($userBirthDate ?? '') ?>" max="<?= e(date('Y-m-d')) ?>">
                                    <div class="form-text">Dùng để hỗ trợ AI chọn cách xưng hô an toàn hơn. Có thể để trống nếu chưa muốn cập nhật.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Loại tài khoản</label>
                                    <input class="form-control" value="<?= e($roleLabel) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nhân xưng AI hiện tại</label>
                                    <input class="form-control" value="<?= e($userAge !== null ? ($userGenderLabel . ' / ' . $userBirthDateLabel . ' / ' . $userAge . ' tuổi') : ($userGenderLabel . ' / ' . $userBirthDateLabel)) ?>" disabled>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Mô tả bản thân</label>
                                    <textarea name="bio" class="form-control" rows="4" placeholder="Giới thiệu ngắn về bạn...\"><?= e(old('bio')) ?></textarea>
                                </div>
                            </div>
                            <button class="btn btn-primary mt-3">Cập nhật</button>
                        </form>
                    </div>
                </div>

                <aside>
                    <div class="ua-rank-card mb-3 <?= e($rankThemeClass) ?>">
                        <span class="ua-kicker">Rank Card</span>
                        <!-- Added: hero block showing rank image mapped to Common/Uncommon/Rare/Epic/Legendary/Mythic. -->
                        <div class="ua-rank-hero">
                            <img src="<?= e($rankImageUrl) ?>" alt="<?= e((string) $currentRank['label']) ?>" class="ua-rank-image ua-rank-image-lg">
                            <div>
                                <h3 class="h4 fw-bold mb-1"><?= e((string) ($currentRank['label'] ?? 'Common')) ?></h3>
                                <p class="text-secondary mb-0">Điểm rank: <strong><?= number_format($loyaltyPoints) ?></strong> điểm · Ưu đãi hiện tại: <strong><?= (int) ($currentRank['discount_percent'] ?? 0) ?>%</strong></p>
                            </div>
                        </div>

                        <?php if ($nextRank): ?>
                            <p class="small text-secondary mb-3">Cần thêm <strong><?= number_format($pointsToNext) ?></strong> điểm để lên hạng <?= e((string) ($nextRank['label'] ?? '')) ?>.</p>
                        <?php else: ?>
                            <p class="small text-success mb-3">Bạn đang ở hạng cao nhất. Mỗi mốc rank đã đạt sẽ tự động cấp coupon giảm giá.</p>
                        <?php endif; ?>

                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" style="width: <?= $loyaltyProgress ?>%;"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small text-secondary">
                            <span>Tiến độ rank</span>
                            <strong><?= $loyaltyProgress ?>%</strong>
                        </div>

                        <div class="mt-3">
                            <h4 class="h6 fw-bold mb-2">Coupon theo cấp bậc</h4>
                            <?php if ($rankCoupons): ?>
                                <div class="d-grid gap-2">
                                    <?php foreach ($rankCoupons as $coupon): ?>
                                        <div class="border rounded-3 p-2 bg-light-subtle">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <code><?= e((string) $coupon['coupon_code']) ?></code>
                                                <span class="badge text-bg-success">-<?= (int) $coupon['discount_percent'] ?>%</span>
                                            </div>
                                            <div class="small text-secondary mt-1">
                                                <?php $couponRankLabel = $rankLabelMap[strtolower((string) $coupon['rank_key'])] ?? ucfirst((string) $coupon['rank_key']); ?>
                                                Hạng <?= e($couponRankLabel) ?> · Hết hạn: <?= e(date('d/m/Y', strtotime((string) $coupon['expires_at']))) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="small text-secondary mb-0">Chưa có coupon rank. Mua thêm để tích điểm và mở khóa coupon tự động.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ua-rank-card">
                        <span class="ua-kicker">Thông tin tài khoản</span>
                        <ul class="list-unstyled ua-summary-list mb-0 mt-2">
                            <li><span>Tên hiển thị</span><b><?= e($user['full_name']) ?></b></li>
                            <li><span>Trạng thái</span><b>Đang hoạt động</b></li>
                            <li><span>Ngày đăng ký</span><b><?= e(substr((string) ($user['created_at'] ?? date('Y-m-d')), 0, 10)) ?></b></li>
                            <li><span>Hoạt động gần đây</span><b><?= e($latestActivity ?? 'Chưa có giao dịch') ?></b></li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>

        <div class="ua-section <?= $activeTab === 'password' ? 'is-visible' : '' ?> mt-4" data-ua-section="password">
            <div class="ua-form-card">
                <h3 class="h5 fw-bold mb-3">Đổi mật khẩu</h3>
                <form method="post" action="<?= base_url('profile/password') ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12"><input type="password" name="current_password" class="form-control" placeholder="Mật khẩu hiện tại" required></div>
                        <div class="col-md-6"><input type="password" name="new_password" class="form-control" placeholder="Mật khẩu mới" required minlength="8"></div>
                        <div class="col-md-6"><input type="password" name="confirm_password" class="form-control" placeholder="Nhập lại mật khẩu" required minlength="8"></div>
                    </div>
                    <button class="btn btn-outline-primary mt-3">Đổi mật khẩu</button>
                </form>
            </div>
        </div>

        <div class="ua-section <?= $activeTab === 'security' ? 'is-visible' : '' ?> mt-4" data-ua-section="security">
            <div class="ua-security-stack">
                <div class="ua-security-card">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h3 class="h5 fw-bold mb-1"><i class="fas fa-shield-halved me-2"></i>Account Security</h3>
                            <p class="text-secondary mb-0 small">Quản lý trạng thái xác thực hai lớp để bảo vệ tài khoản.</p>
                        </div>
                        <span class="ua-security-badge <?= $twoFactorEnabled ? 'is-enabled' : 'is-disabled' ?>"><?= $twoFactorEnabled ? 'Enabled' : 'Disabled' ?></span>
                    </div>

                    <ul class="list-unstyled ua-summary-list mb-0 mt-2">
                        <li><span>2FA status</span><b><?= $twoFactorEnabled ? 'Đang bảo vệ đăng nhập' : 'Chưa kích hoạt' ?></b></li>
                        <li><span>Kích hoạt lúc</span><b><?= e($twoFactorConfirmedAtLabel) ?></b></li>
                        <li><span>Xác thực gần nhất</span><b><?= e($twoFactorLastUsedAtLabel) ?></b></li>
                        <li><span>Backup codes còn lại</span><b><?= (int) ($backupStats['available'] ?? 0) ?>/<?= (int) ($backupStats['total'] ?? 0) ?></b></li>
                    </ul>

                    <?php if ($twoFactorEnabled): ?>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#disable2faModal"><i class="fas fa-lock-open me-1"></i>Tắt 2FA</button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="ua-security-grid">
                    <div class="ua-security-card">
                        <h3 class="h5 fw-bold mb-2"><i class="fas fa-key me-2"></i>Authentication</h3>
                        <p class="small text-secondary mb-3">Thiết lập QR, OTP và backup codes để dự phòng khi mất thiết bị authenticator.</p>

                        <?php if (!$twoFactorEnabled): ?>
                            <div class="ua-qr-box mb-3">
                                <div class="ua-qr-stage" aria-label="QR setup">
                                    <div class="ua-qr-frame">
                                        <div id="setup-qr" class="ua-qr-render" data-otp-auth="<?= e($otpAuthUri) ?>"></div>
                                    </div>
                                    <div class="ua-qr-caption">
                                        <strong>Quét bằng ứng dụng authenticator</strong>
                                        <span>Google Authenticator/Authy sẽ tạo OTP theo chu kỳ 30 giây.</span>
                                    </div>
                                </div>
                            </div>

                            <label class="form-label">Setup key</label>
                            <div class="ua-setup-wrap mb-3">
                                <input id="setup-key" class="form-control" value="<?= e($setupKey) ?>" readonly>
                                <button class="btn btn-outline-secondary" type="button" data-copy-target="#setup-key"><i class="fas fa-copy me-1"></i>Copy</button>
                            </div>

                            <form method="post" action="<?= base_url('profile/2fa/enable') ?>">
                                <?= csrf_field() ?>
                                <div class="mb-3">
                                    <label class="form-label">Mã OTP (6 số)</label>
                                    <input class="form-control" type="text" name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Nhập mã OTP từ ứng dụng" required>
                                </div>
                                <button class="btn btn-primary w-100" type="submit"><i class="fas fa-shield-halved me-1"></i>Bật 2FA</button>
                            </form>
                        <?php else: ?>
                            <div class="ua-info-box ua-info-box-success mb-3">
                                <i class="fas fa-circle-check mt-1"></i>
                                <span>2FA đang hoạt động. OTP chỉ có hiệu lực trong khoảng thời gian 30 giây.</span>
                            </div>

                            <form method="post" action="<?= base_url('profile/2fa/backup-codes/regenerate') ?>" class="mb-3">
                                <?= csrf_field() ?>
                                <label class="form-label">Tạo lại backup codes (xác nhận OTP)</label>
                                <div class="ua-inline-form">
                                    <input class="form-control" type="text" name="otp_code_regen" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="OTP hiện tại" required>
                                    <button class="btn btn-outline-primary" type="submit"><i class="fas fa-rotate me-1"></i>Reset codes</button>
                                </div>
                            </form>

                            <?php if ($freshBackupCodes): ?>
                                <div class="ua-backup-panel" data-backup-codes='<?= e(json_encode($freshBackupCodes, JSON_UNESCAPED_UNICODE)) ?>'>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong>Backup codes mới</strong>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-download-backup-codes><i class="fas fa-download me-1"></i>Download backup codes</button>
                                    </div>
                                    <div class="ua-backup-grid">
                                        <?php foreach ($freshBackupCodes as $code): ?>
                                            <code><?= e($code) ?></code>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="small text-danger mt-2 mb-0">Các mã này chỉ hiển thị một lần. Vui lòng lưu lại ngay.</p>
                                </div>
                            <?php else: ?>
                                <div class="ua-info-box">
                                    <i class="fas fa-circle-info mt-1"></i>
                                    <span>Backup codes được lưu dạng hash trong hệ thống. Bạn có thể tạo bộ mới bất cứ lúc nào.</span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="ua-security-card">
                        <h3 class="h5 fw-bold mb-2"><i class="fas fa-lock me-2"></i>Recovery & Email Security</h3>
                        <p class="small text-secondary mb-3">Các thao tác nhạy cảm yêu cầu xác thực nhiều lớp.</p>

                        <form method="post" action="<?= base_url('profile/2fa/reset/request') ?>" class="mb-3">
                            <?= csrf_field() ?>
                            <label class="form-label">Reset 2FA (Bước 1: xác thực mật khẩu)</label>
                            <div class="ua-inline-form">
                                <input type="password" class="form-control" name="reset_password" placeholder="Mật khẩu hiện tại" required>
                                <button class="btn btn-outline-primary" type="submit"><i class="fas fa-paper-plane me-1"></i>Gửi mã email</button>
                            </div>
                            <?php if ($resetPending): ?>
                                <div class="small text-warning mt-1">Mã xác minh đang chờ, hết hạn lúc <?= e($resetExpiresLabel) ?>.</div>
                            <?php endif; ?>
                        </form>

                        <form method="post" action="<?= base_url('profile/2fa/reset/confirm') ?>" class="mb-3">
                            <?= csrf_field() ?>
                            <label class="form-label">Reset 2FA (Bước 2: mật khẩu + mã email)</label>
                            <div class="row g-2">
                                <div class="col-md-6"><input type="password" class="form-control" name="reset_password_confirm" placeholder="Mật khẩu" required></div>
                                <div class="col-md-6"><input type="text" class="form-control" name="reset_email_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Mã email 6 số" required></div>
                            </div>
                            <button class="btn btn-outline-danger mt-2" type="submit"><i class="fas fa-triangle-exclamation me-1"></i>Xác nhận reset 2FA</button>
                        </form>

                        <hr>

                        <form method="post" action="<?= base_url('profile/email/change') ?>">
                            <?= csrf_field() ?>
                            <label class="form-label">Change Email Security</label>
                            <div class="row g-2">
                                <div class="col-12"><input type="email" class="form-control" name="new_email" placeholder="Email mới" required></div>
                                <div class="col-md-6"><input type="password" class="form-control" name="email_change_password" placeholder="Mật khẩu" required></div>
                                <div class="col-md-6"><input type="text" class="form-control" name="email_change_otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="OTP 2FA" required></div>
                            </div>
                            <button class="btn btn-primary mt-2" type="submit"><i class="fas fa-envelope-circle-check me-1"></i>Đổi email an toàn</button>
                        </form>
                    </div>
                </div>

                <div class="ua-security-grid">
                    <div class="ua-security-card">
                        <h3 class="h5 fw-bold mb-3"><i class="fas fa-desktop me-2"></i>Hoạt động đăng nhập</h3>
                        <?php if ($loginActivities): ?>
                            <div class="ua-activity-list">
                                <?php foreach ($loginActivities as $activity): ?>
                                    <article class="ua-activity-item">
                                        <div>
                                            <strong><?= e($activity['device']) ?></strong>
                                            <p class="mb-0 small text-secondary"><?= e($activity['location']) ?> · <?= e($activity['ip_address']) ?></p>
                                        </div>
                                        <time><?= e(date('d/m/Y H:i', strtotime((string) $activity['logged_in_at']))) ?></time>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-secondary mb-0">Chưa có lịch sử đăng nhập.</p>
                        <?php endif; ?>
                    </div>

                    <div class="ua-security-card">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <h3 class="h5 fw-bold mb-0"><i class="fas fa-laptop-house me-2"></i>Phiên đăng nhập hiện tại</h3>
                            <form method="post" action="<?= base_url('profile/sessions/logout-others') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit">Đăng xuất khỏi tất cả thiết bị khác</button>
                            </form>
                        </div>

                        <?php if ($activeSessions): ?>
                            <div class="ua-session-list">
                                <?php foreach ($activeSessions as $session): ?>
                                    <article class="ua-session-item">
                                        <div>
                                            <strong><?= e($session['device']) ?></strong>
                                            <p class="mb-0 small text-secondary"><?= e($session['ip_address']) ?></p>
                                        </div>
                                        <div class="text-end">
                                            <div class="small"><?= e(date('d/m/Y H:i', strtotime((string) $session['created_at']))) ?></div>
                                            <?php if (!empty($session['is_current'])): ?>
                                                <span class="ua-chip is-success mt-1">Phiên hiện tại</span>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-secondary mb-0">Không tìm thấy phiên hoạt động.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($twoFactorEnabled): ?>
                <div class="modal fade" id="disable2faModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Xác nhận tắt xác thực hai lớp</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post" action="<?= base_url('profile/2fa/disable') ?>">
                                <div class="modal-body">
                                    <?= csrf_field() ?>
                                    <p class="text-secondary">Việc tắt 2FA sẽ làm giảm mức độ bảo mật của tài khoản.</p>
                                    <label class="form-label">Nhập mã OTP hiện tại</label>
                                    <input type="text" class="form-control" name="otp_code_disable" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="123456" required>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                                    <button type="submit" class="btn btn-danger">Xác nhận</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="ua-section <?= $activeTab === 'history' ? 'is-visible' : '' ?> mt-4" data-ua-section="history">
            <div class="ua-order-card">
                <h3 class="h5 fw-bold mb-3">Lịch sử mua hàng</h3>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Mã đơn</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead>
                        <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= e($order['order_code']) ?></td>
                                <td><?= format_money((float) $order['total_amount']) ?></td>
                                <td><span class="badge text-bg-secondary"><?= e($order['status']) ?></span></td>
                                <td><?= e($order['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$orders): ?><tr><td colspan="4" class="text-center text-secondary">Chưa có đơn hàng.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="ua-section <?= $activeTab === 'wallet-log' ? 'is-visible' : '' ?> mt-4" data-ua-section="wallet-log">
            <!-- Added: functional wallet top-up area with real balance history. -->
            <div class="ua-wallet-grid">
                <div class="ua-order-card">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                        <div>
                            <h3 class="h5 fw-bold mb-1">Nạp số dư tài khoản</h3>
                            <p class="text-secondary mb-0">Chọn mệnh giá hoặc nhập số tiền muốn nạp. Sau khi gửi form, hệ thống sẽ cộng ngay vào ví và ghi lại lịch sử giao dịch.</p>
                        </div>
                        <span class="ua-chip"><i class="fas fa-bolt me-1"></i>Cộng số dư tức thì</span>
                    </div>

                    <form method="post" action="<?= base_url('profile/wallet/deposit') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Chọn nhanh mệnh giá</label>
                            <div class="ua-wallet-presets">
                                <?php foreach ([50000, 100000, 200000, 500000, 1000000, 2000000] as $presetAmount): ?>
                                    <button
                                        type="button"
                                        class="ua-wallet-preset <?= (string) $presetAmount === (string) $walletAmountOld ? 'is-active' : '' ?>"
                                        data-wallet-preset="<?= $presetAmount ?>"
                                    >
                                        <?= format_money((float) $presetAmount) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Số tiền nạp</label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    class="form-control"
                                    name="amount"
                                    min="10000"
                                    max="50000000"
                                    step="1000"
                                    value="<?= e($walletAmountOld) ?>"
                                    placeholder="Ví dụ: 100000"
                                    required
                                    data-wallet-amount-input
                                >
                                <span class="input-group-text">₫</span>
                            </div>
                            <div class="form-text">Giới hạn mỗi lần nạp: từ 10.000 ₫ đến 50.000.000 ₫.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Phương thức nạp</label>
                            <div class="ua-wallet-method-grid">
                                <?php foreach ($walletPaymentMethodLabels as $methodKey => $methodLabel): ?>
                                    <label class="ua-wallet-method <?= $walletMethodOld === $methodKey ? 'is-active' : '' ?>">
                                        <input
                                            class="ua-wallet-method-input"
                                            type="radio"
                                            name="payment_method"
                                            value="<?= e($methodKey) ?>"
                                            <?= $walletMethodOld === $methodKey ? 'checked' : '' ?>
                                        >
                                        <span class="ua-wallet-method-icon"><i class="fas <?= e($walletPaymentMethodIcons[$methodKey] ?? 'fa-wallet') ?>"></i></span>
                                        <span>
                                            <strong><?= e($methodLabel) ?></strong>
                                            <small>Nạp vào ví nội bộ</small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ghi chú giao dịch</label>
                            <input
                                type="text"
                                class="form-control"
                                name="note"
                                maxlength="160"
                                value="<?= e($walletNoteOld) ?>"
                                placeholder="Ví dụ: Nạp để thanh toán đơn VPS tháng này"
                            >
                        </div>

                        <div class="ua-wallet-submit">
                            <div class="small text-secondary">
                                <div><strong>Số dư hiện tại:</strong> <?= format_money($currentBalance) ?></div>
                                <div><strong>Lần nạp gần nhất:</strong> <?= e($latestDepositAt) ?></div>
                            </div>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-wallet me-1"></i>Xác nhận nạp tiền
                            </button>
                        </div>
                    </form>
                </div>

                <aside class="ua-wallet-side">
                    <div class="ua-order-card">
                        <span class="ua-kicker">Wallet Summary</span>
                        <h3 class="h4 fw-bold mb-3"><?= format_money($currentBalance) ?></h3>
                        <div class="ua-wallet-summary-grid">
                            <div class="ua-wallet-summary-item">
                                <span>Tổng nạp</span>
                                <strong><?= format_money($totalDeposited) ?></strong>
                            </div>
                            <div class="ua-wallet-summary-item">
                                <span>Số lần nạp</span>
                                <strong><?= number_format($walletDepositCount) ?></strong>
                            </div>
                            <div class="ua-wallet-summary-item">
                                <span>Đã chi</span>
                                <strong><?= format_money($walletSpent) ?></strong>
                            </div>
                            <div class="ua-wallet-summary-item">
                                <span>Hoạt động mới nhất</span>
                                <strong><?= e($latestWalletActivityAt) ?></strong>
                            </div>
                        </div>
                        <div class="ua-info-box mt-3">
                            <i class="fas fa-circle-info mt-1"></i>
                            <span>Ví nội bộ hiện hỗ trợ nạp số dư tức thì và được dùng trực tiếp để thanh toán đơn hàng trong hệ thống.</span>
                        </div>
                    </div>
                </aside>
            </div>

            <div class="ua-order-card mt-4">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                    <div>
                        <h3 class="h5 fw-bold mb-1">Lịch sử biến động số dư</h3>
                        <p class="text-secondary mb-0">Theo dõi mã giao dịch, phương thức nạp và số dư trước/sau mỗi lần thay đổi.</p>
                    </div>
                    <span class="ua-chip"><?= number_format(count($walletTransactions)) ?> giao dịch gần nhất</span>
                </div>

                <?php if ($walletTransactions): ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Mã GD</th>
                                    <th>Loại</th>
                                    <th>Phương thức</th>
                                    <th>Số tiền</th>
                                    <th>Số dư</th>
                                    <th>Thời gian</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($walletTransactions as $transaction): ?>
                                    <?php
                                    $direction = (string) ($transaction['direction'] ?? 'credit');
                                    $status = (string) ($transaction['status'] ?? 'completed');
                                    $amountClass = $direction === 'credit' ? 'is-positive' : 'is-negative';
                                    $statusClass = match ($status) {
                                        'completed' => 'is-success',
                                        'failed' => 'is-danger',
                                        default => 'is-warning',
                                    };
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= e((string) $transaction['transaction_code']) ?></strong>
                                            <?php if (!empty($transaction['description'])): ?>
                                                <div class="small text-secondary"><?= e((string) $transaction['description']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($walletDirectionLabels[$direction] ?? ucfirst($direction)) ?></td>
                                        <td><?= e($walletPaymentMethodLabels[(string) ($transaction['payment_method'] ?? '')] ?? 'Ví nội bộ') ?></td>
                                        <td><span class="ua-wallet-amount <?= e($amountClass) ?>"><?= ($direction === 'credit' ? '+' : '-') . format_money((float) $transaction['amount']) ?></span></td>
                                        <td>
                                            <div class="small text-secondary">Trước: <?= format_money((float) $transaction['balance_before']) ?></div>
                                            <strong>Sau: <?= format_money((float) $transaction['balance_after']) ?></strong>
                                        </td>
                                        <td><?= e(date('d/m/Y H:i', strtotime((string) $transaction['created_at']))) ?></td>
                                        <td><span class="ua-wallet-status <?= e($statusClass) ?>"><?= e($walletStatusLabels[$status] ?? ucfirst($status)) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="ua-wallet-empty">
                        <i class="fas fa-wallet"></i>
                        <h4>Chưa có giao dịch ví nào</h4>
                        <p class="mb-0">Nạp số dư lần đầu để hệ thống bắt đầu ghi nhận biến động ví của tài khoản này.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach (['activity-log', 'vps-history', 'domain-history', 'license-history', 'service-history', 'api', 'seller'] as $tab): ?>
            <div class="ua-section <?= $activeTab === $tab ? 'is-visible' : '' ?> mt-4" data-ua-section="<?= e($tab) ?>">
                <div class="ua-card p-4">
                    <h3 class="h5 fw-bold mb-2">
                        <?php
                        $labels = [
                            'activity-log' => 'Nhật ký hoạt động',
                            'vps-history' => 'Lịch sử mua VPS',
                            'domain-history' => 'Lịch sử mua miền',
                            'license-history' => 'Lịch sử mua License',
                            'service-history' => 'Lịch sử đặt dịch vụ',
                            'api' => 'API Key',
                            'seller' => 'Trở thành người bán',
                        ];
                        echo e($labels[$tab]);
                        ?>
                    </h3>
                    <p class="text-secondary mb-0">Khu vực này đang ở chế độ giao diện mới, có thể kết nối dữ liệu backend ở bước tiếp theo.</p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="modal fade" id="avatarCropModal" tabindex="-1" aria-hidden="true" data-avatar-crop-modal>
    <div class="modal-dialog modal-dialog-centered ua-crop-dialog">
        <div class="modal-content ua-crop-modal">
            <div class="modal-header">
                <h5 class="modal-title">Cắt ảnh đại diện</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small mb-2">Kéo và zoom để căn chỉnh ảnh vừa khung avatar.</p>
                <div class="ua-crop-stage">
                    <img src="" alt="Preview crop" id="ua-avatar-crop-image">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" data-avatar-crop-confirm>
                    <i class="fas fa-crop-simple me-1"></i>Xác nhận cắt ảnh
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bannerEditorModal" tabindex="-1" aria-hidden="true" data-banner-editor-modal>
    <div class="modal-dialog modal-dialog-centered ua-crop-dialog ua-banner-dialog">
        <div class="modal-content ua-crop-modal">
            <div class="modal-header">
                <h5 class="modal-title">Chỉnh sửa ảnh bìa / video bìa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="ua-banner-editor-layout">
                    <div class="ua-banner-editor-main">
                        <div class="ua-banner-editor-toolbar">
                            <div>
                                <span class="ua-banner-editor-kicker">Banner Studio</span>
                                <h6 class="mb-1">Tùy chỉnh vùng hiển thị và cảm giác banner</h6>
                                <p class="text-secondary small mb-0">Bạn có thể thay media mới, cắt ảnh, căn vị trí và preview trước khi lưu hồ sơ.</p>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-banner-editor-select-media>
                                <i class="fas fa-photo-film me-1"></i>Chọn ảnh / video
                            </button>
                        </div>

                        <div class="ua-banner-editor-empty d-none" data-banner-editor-empty-box>
                            <div class="ua-banner-editor-empty-icon"><i class="fas fa-panorama"></i></div>
                            <h6 class="mb-1">Chưa có banner để chỉnh</h6>
                            <p class="text-secondary small mb-3">Chọn ảnh hoặc video mới để bắt đầu tùy chỉnh banner hồ sơ.</p>
                            <button type="button" class="btn btn-primary btn-sm" data-banner-editor-select-media>
                                <i class="fas fa-upload me-1"></i>Chọn media
                            </button>
                        </div>

                        <div class="ua-banner-editor-image d-none" data-banner-editor-image-box>
                            <p class="text-secondary small mb-2">Kéo khung cắt để chọn vùng banner chính. Preview bên phải sẽ phản ánh đúng cách banner xuất hiện trên profile.</p>
                            <div class="ua-crop-stage ua-banner-crop-stage">
                                <img src="" alt="Banner crop" id="ua-banner-crop-image">
                            </div>
                        </div>

                        <div class="ua-banner-editor-video d-none" data-banner-editor-video-box>
                            <p class="text-secondary small mb-2">Chọn đoạn video muốn dùng và tinh chỉnh cách hiển thị cho khung banner ngang.</p>
                            <video id="ua-banner-video-preview" class="ua-banner-video-preview" controls preload="metadata"></video>
                            <div class="row g-2 mt-2">
                                <div class="col-md-6">
                                    <label class="form-label small">Bắt đầu (giây)</label>
                                    <input type="number" min="0" step="0.1" class="form-control" id="ua-banner-video-start" value="<?= e((string) $bannerVideoStart) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Kết thúc (giây)</label>
                                    <input type="number" min="0" step="0.1" class="form-control" id="ua-banner-video-end" value="<?= e((string) $bannerVideoEnd) ?>">
                                </div>
                            </div>
                            <p class="small text-secondary mt-2 mb-0">Nếu để mốc kết thúc bằng toàn bộ thời lượng, banner sẽ phát trọn video theo vòng lặp.</p>
                        </div>
                    </div>

                    <aside class="ua-banner-editor-sidebar">
                        <div class="ua-banner-preview-card">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <div>
                                    <span class="ua-banner-editor-kicker">Preview</span>
                                    <h6 class="mb-0">Banner sau khi lưu</h6>
                                </div>
                                <span class="badge text-bg-light">Realtime</span>
                            </div>
                            <div class="ua-banner-preview-frame" data-banner-editor-preview-root>
                                <div class="ua-banner-preview-empty" data-banner-editor-preview-empty>
                                    <i class="fas fa-panorama me-1"></i>Chưa có banner
                                </div>
                                <div class="ua-profile-header-overlay" aria-hidden="true"></div>
                            </div>
                            <p class="small text-secondary mb-0">Preview này dùng cùng metadata hiển thị với banner thật trên trang profile.</p>
                        </div>

                        <div class="ua-banner-control-card">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <span class="ua-banner-editor-kicker">Display controls</span>
                                    <h6 class="mb-0">Cách banner hiển thị</h6>
                                </div>
                                <button type="button" class="btn btn-link btn-sm px-0 text-decoration-none" data-banner-editor-reset>
                                    <i class="fas fa-rotate-left me-1"></i>Reset
                                </button>
                            </div>

                            <div class="ua-banner-control-group">
                                <label class="form-label small fw-semibold mb-2">Kiểu hiển thị</label>
                                <div class="ua-banner-segmented" data-banner-fit-group>
                                    <?php foreach ($bannerFitLabels as $fitKey => $fitLabel): ?>
                                        <button type="button" class="ua-banner-segmented-btn <?= $bannerFitMode === $fitKey ? 'is-active' : '' ?>" data-banner-fit-option="<?= e($fitKey) ?>">
                                            <?= e($fitLabel) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="ua-banner-control-group">
                                <label class="form-label small fw-semibold mb-2">Chiều cao banner</label>
                                <div class="ua-banner-segmented" data-banner-height-group>
                                    <?php foreach ($bannerHeightLabels as $heightKey => $heightLabel): ?>
                                        <button type="button" class="ua-banner-segmented-btn <?= $bannerHeightMode === $heightKey ? 'is-active' : '' ?>" data-banner-height-option="<?= e($heightKey) ?>">
                                            <?= e($heightLabel) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="ua-banner-control-group">
                                <div class="ua-banner-range-head">
                                    <label class="form-label small fw-semibold mb-0" for="ua-banner-zoom-range">Zoom</label>
                                    <output for="ua-banner-zoom-range" data-banner-output="zoom"><?= e(number_format($bannerZoom, 2, '.', '')) ?>x</output>
                                </div>
                                <input type="range" min="1" max="2.8" step="0.01" class="form-range" id="ua-banner-zoom-range" value="<?= e(number_format($bannerZoom, 2, '.', '')) ?>" data-banner-control="zoom">
                            </div>

                            <div class="ua-banner-control-group">
                                <div class="ua-banner-range-head">
                                    <label class="form-label small fw-semibold mb-0" for="ua-banner-position-x-range">Vị trí ngang</label>
                                    <output for="ua-banner-position-x-range" data-banner-output="position_x"><?= e(number_format($bannerPositionX, 0, '.', '')) ?>%</output>
                                </div>
                                <input type="range" min="0" max="100" step="1" class="form-range" id="ua-banner-position-x-range" value="<?= e(number_format($bannerPositionX, 0, '.', '')) ?>" data-banner-control="position_x">
                            </div>

                            <div class="ua-banner-control-group">
                                <div class="ua-banner-range-head">
                                    <label class="form-label small fw-semibold mb-0" for="ua-banner-position-y-range">Vị trí dọc</label>
                                    <output for="ua-banner-position-y-range" data-banner-output="position_y"><?= e(number_format($bannerPositionY, 0, '.', '')) ?>%</output>
                                </div>
                                <input type="range" min="0" max="100" step="1" class="form-range" id="ua-banner-position-y-range" value="<?= e(number_format($bannerPositionY, 0, '.', '')) ?>" data-banner-control="position_y">
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
            <div class="modal-footer">
                <div class="small text-secondary me-auto">Sau khi áp dụng banner, nhớ bấm <strong>Cập nhật</strong> ở form hồ sơ để lưu thay đổi.</div>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" data-banner-editor-confirm>
                    <i class="fas fa-check me-1"></i>Áp dụng banner
                </button>
            </div>
        </div>
    </div>
</div>
