<?php
$googleConfig = google_oauth_config();
$googleClientId = (string) ($googleConfig['client_id'] ?? '');
$googleRedirectUri = (string) ($googleConfig['redirect_uri'] ?? '');
$hasStoredGoogleSecret = trim((string) ($googleConfig['client_secret'] ?? '')) !== '';
$defaultGoogleRedirectUri = base_url('auth/google/callback');
$siteLogo = trim((string) ($settings['site_logo'] ?? ''));
$siteFavicon = trim((string) ($settings['site_favicon'] ?? ''));
$siteLogoUrl = $siteLogo !== '' ? base_url('uploads/' . ltrim($siteLogo, '/')) : base_url('images/logo/zenox.png');
$siteFaviconUrl = $siteFavicon !== '' ? base_url('uploads/' . ltrim($siteFavicon, '/')) : base_url('favicon.ico');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Cài đặt hệ thống</h1>
        <p class="text-secondary mb-0">Quản trị branding, kết nối OAuth, thanh toán, email và bảo mật hệ thống.</p>
    </div>
</div>

<form method="post" action="<?= base_url('admin/settings/update') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="admin-card mb-3">
        <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">General</h2></div>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Tên website</label><input class="form-control" name="site_name" value="<?= e((string) ($settings['site_name'] ?? 'Digital Market Pro')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Email liên hệ</label><input class="form-control" name="contact_email" value="<?= e((string) ($settings['contact_email'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Số điện thoại</label><input class="form-control" name="contact_phone" value="<?= e((string) ($settings['contact_phone'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Địa chỉ</label><input class="form-control" name="address" value="<?= e((string) ($settings['address'] ?? '')) ?>"></div>
                <div class="col-12"><label class="form-label">Footer text</label><textarea class="form-control" rows="3" name="footer_text"><?= e((string) ($settings['footer_text'] ?? '')) ?></textarea></div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-3">
        <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Branding</h2></div>
        <div class="admin-card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Logo</label>
                    <div class="border rounded p-2 text-center mb-2"><img src="<?= e($siteLogoUrl) ?>" alt="Logo" style="max-height:56px;max-width:100%;object-fit:contain;"></div>
                    <input class="form-control" type="file" name="logo" accept="image/*">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Favicon</label>
                    <div class="border rounded p-2 text-center mb-2"><img src="<?= e($siteFaviconUrl) ?>" alt="Favicon" style="width:32px;height:32px;object-fit:contain;"></div>
                    <input class="form-control" type="file" name="favicon" accept=".ico,image/*">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Facebook URL</label>
                    <input class="form-control mb-2" name="facebook_url" value="<?= e((string) ($settings['facebook_url'] ?? '')) ?>">
                    <label class="form-label">Zalo URL</label>
                    <input class="form-control" name="zalo_url" value="<?= e((string) ($settings['zalo_url'] ?? '')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-3">
        <div class="admin-card-header d-flex justify-content-between align-items-center"><h2 class="h6 fw-bold mb-0">Google OAuth</h2><span class="admin-badge-soft <?= is_google_oauth_configured() ? 'is-success' : 'is-muted' ?>"><?= is_google_oauth_configured() ? 'Đã cấu hình' : 'Chưa cấu hình' ?></span></div>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="google_oauth_enabled" name="google_oauth_enabled" value="1" <?= !empty($googleConfig['enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="google_oauth_enabled">Bật đăng nhập bằng Google</label>
                    </div>
                </div>
                <div class="col-md-6"><label class="form-label">Google Client ID</label><input class="form-control" name="google_client_id" value="<?= e($googleClientId) ?>" placeholder="xxxx.apps.googleusercontent.com"></div>
                <div class="col-md-6"><label class="form-label">Google Client Secret</label><input class="form-control" type="password" name="google_client_secret" placeholder="<?= $hasStoredGoogleSecret ? 'Để trống để giữ secret hiện tại' : 'Nhập Google Client Secret' ?>" autocomplete="new-password"><div class="form-text"><?= $hasStoredGoogleSecret ? 'Secret đang được lưu. Chỉ nhập lại khi muốn thay đổi.' : 'Secret chỉ hiển thị khi bạn nhập mới.' ?></div></div>
                <div class="col-md-6"><label class="form-label">Redirect URI</label><input class="form-control" name="google_redirect_uri" value="<?= e($googleRedirectUri) ?>" placeholder="<?= e($defaultGoogleRedirectUri) ?>"><div class="form-text">Mặc định: <code><?= e($defaultGoogleRedirectUri) ?></code></div></div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-3">
        <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Payment</h2></div>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Ngân hàng</label><input class="form-control" name="payment_bank_name" value="<?= e((string) ($settings['payment_bank_name'] ?? '')) ?>" placeholder="VD: Vietcombank"></div>
                <div class="col-md-4"><label class="form-label">Số tài khoản</label><input class="form-control" name="payment_bank_account" value="<?= e((string) ($settings['payment_bank_account'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Chủ tài khoản</label><input class="form-control" name="payment_bank_owner" value="<?= e((string) ($settings['payment_bank_owner'] ?? '')) ?>"></div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-3">
        <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">SMTP</h2></div>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">SMTP Host</label><input class="form-control" name="smtp_host" value="<?= e((string) ($settings['smtp_host'] ?? '')) ?>"></div>
                <div class="col-md-2"><label class="form-label">Port</label><input class="form-control" name="smtp_port" value="<?= e((string) ($settings['smtp_port'] ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Username</label><input class="form-control" name="smtp_username" value="<?= e((string) ($settings['smtp_username'] ?? '')) ?>"></div>
                <div class="col-md-2"><label class="form-label">Encryption</label><select class="form-select" name="smtp_encryption"><?php $smtpEnc = (string) ($settings['smtp_encryption'] ?? 'tls'); ?><option value="" <?= $smtpEnc === '' ? 'selected' : '' ?>>None</option><option value="tls" <?= $smtpEnc === 'tls' ? 'selected' : '' ?>>TLS</option><option value="ssl" <?= $smtpEnc === 'ssl' ? 'selected' : '' ?>>SSL</option></select></div>
                <div class="col-md-2"><label class="form-label">Password</label><input class="form-control" type="password" name="smtp_password" placeholder="Giữ nguyên nếu để trống"></div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-3">
        <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Security</h2></div>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="require_2fa_admin" name="require_2fa_admin" value="1" <?= !empty($settings['require_2fa_admin']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="require_2fa_admin">Bắt buộc 2FA cho tài khoản admin</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="maintenance_mode" name="maintenance_mode" value="1" <?= !empty($settings['maintenance_mode']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="maintenance_mode">Bật maintenance mode</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end">
        <button class="btn btn-primary px-4">Lưu cài đặt</button>
    </div>
</form>
