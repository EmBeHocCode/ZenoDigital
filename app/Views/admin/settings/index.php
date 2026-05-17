<?php
$googleConfig = google_oauth_config();
$googleClientId = (string) ($googleConfig['client_id'] ?? '');
$googleRedirectUri = (string) ($googleConfig['redirect_uri'] ?? '');
$hasStoredGoogleSecret = trim((string) ($googleConfig['client_secret'] ?? '')) !== '';
$siteNameSettingValue = normalize_public_brand_name((string) ($settings['site_name'] ?? app_site_name()));
$defaultGoogleRedirectUri = base_url('auth/google/callback');
$siteLogo = trim((string) ($settings['site_logo'] ?? ''));
$siteFavicon = trim((string) ($settings['site_favicon'] ?? ''));
$siteLogoUrl = $siteLogo !== '' ? base_url('uploads/' . ltrim($siteLogo, '/')) : base_url('images/logo/zenox.png');
$siteFaviconUrl = $siteFavicon !== '' ? base_url('uploads/' . ltrim($siteFavicon, '/')) : base_url('favicon.ico');
$sepayConfig = sepay_config();
$sepayWebhookToken = (string) ($sepayConfig['webhook_token'] ?? '');
$sepayWebhookUrl = sepay_webhook_url();
$settingText = static function (string $key, string $default = '') use ($settings): string {
    $value = trim((string) ($settings[$key] ?? ''));

    return $value !== '' ? $value : $default;
};
$defaultFooterProductLinks = "VPS Giá Rẻ|/#student-vps-plans\nCloud VPS|/#cloud-vps-plans\nCloud Server|products?q=server\nGame Server|products?q=game\nAI GPU|products?q=gpu\nSIM Số|products?q=sim";
$defaultFooterSupportLinks = "Hướng dẫn sử dụng|/#vps-guides\nFAQ|/#faq\nTicket hỗ trợ|#contact\nLiên hệ|#contact";
$defaultFooterPolicyLinks = "Điều khoản dịch vụ|/#faq\nChính sách bảo mật|/#faq\nChính sách hoàn tiền|/#faq\nPhương thức thanh toán|/#payment-methods";
$defaultFooterCommitments = "Kích hoạt tự động trong vài phút\nBảo mật nhiều lớp\nHỗ trợ kỹ thuật 24/7\nHạ tầng ổn định cho production";
$defaultFooterPayments = "VietQR|fas fa-qrcode\nMoMo|fas fa-wallet\nZaloPay|fas fa-bolt\nVisa|fab fa-cc-visa\nMastercard|fab fa-cc-mastercard";
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
        <div class="admin-card-header d-flex justify-content-between align-items-center gap-2">
            <h2 class="h6 fw-bold mb-0">General</h2>
            <button type="submit" name="settings_section" value="general" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-save me-1"></i>Lưu mục này
            </button>
        </div>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Tên website</label><input class="form-control" name="site_name" value="<?= e($siteNameSettingValue) ?>"></div>
                <div class="col-md-6"><label class="form-label">Email liên hệ</label><input class="form-control" name="contact_email" value="<?= e((string) ($settings['contact_email'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Email phụ ở footer</label><input class="form-control" name="contact_email_secondary" value="<?= e((string) ($settings['contact_email_secondary'] ?? '')) ?>" placeholder="support-phu@example.com"></div>
                <div class="col-md-6"><label class="form-label">Số điện thoại</label><input class="form-control" name="contact_phone" value="<?= e((string) ($settings['contact_phone'] ?? '')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Địa chỉ</label><input class="form-control" name="address" value="<?= e((string) ($settings['address'] ?? '')) ?>"></div>
                <div class="col-12"><label class="form-label">Footer text</label><textarea class="form-control" rows="3" name="footer_text"><?= e((string) ($settings['footer_text'] ?? '')) ?></textarea></div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-3">
        <div class="admin-card-header d-flex justify-content-between align-items-center gap-2">
            <h2 class="h6 fw-bold mb-0">Branding</h2>
            <button type="submit" name="settings_section" value="branding" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-save me-1"></i>Lưu mục này
            </button>
        </div>
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
                    <input class="form-control mb-2" name="zalo_url" value="<?= e((string) ($settings['zalo_url'] ?? '')) ?>">
                    <label class="form-label">Telegram URL</label>
                    <input class="form-control mb-2" name="telegram_url" value="<?= e((string) ($settings['telegram_url'] ?? '')) ?>">
                    <label class="form-label">YouTube URL</label>
                    <input class="form-control" name="youtube_url" value="<?= e((string) ($settings['youtube_url'] ?? '')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-3">
        <div class="admin-card-header d-flex justify-content-between align-items-center gap-2">
            <h2 class="h6 fw-bold mb-0">Footer & CTA cuối trang</h2>
            <button type="submit" name="settings_section" value="footer" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-save me-1"></i>Lưu mục này
            </button>
        </div>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nhãn CTA</label>
                    <input class="form-control" name="footer_cta_kicker" value="<?= e($settingText('footer_cta_kicker', 'Cloud VPS cho học tập và vận hành')) ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Tiêu đề CTA</label>
                    <input class="form-control" name="footer_cta_title" value="<?= e($settingText('footer_cta_title', 'Sẵn sàng triển khai Cloud VPS chỉ từ 35.000đ/tháng?')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Mô tả CTA</label>
                    <input class="form-control" name="footer_cta_description" value="<?= e($settingText('footer_cta_description', 'Phù hợp cho học tập, website, bot, game server và các dự án cá nhân.')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nút chính</label>
                    <input class="form-control" name="footer_cta_primary_label" value="<?= e($settingText('footer_cta_primary_label', 'Xem gói VPS')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Link nút chính</label>
                    <input class="form-control" name="footer_cta_primary_url" value="<?= e($settingText('footer_cta_primary_url', '/#cloud-vps-plans')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nút phụ</label>
                    <input class="form-control" name="footer_cta_secondary_label" value="<?= e($settingText('footer_cta_secondary_label', 'Liên hệ tư vấn')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Link nút phụ</label>
                    <input class="form-control" name="footer_cta_secondary_url" value="<?= e($settingText('footer_cta_secondary_url', '')) ?>" placeholder="Để trống sẽ dùng Zalo/email">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cột sản phẩm</label>
                    <textarea class="form-control" name="footer_product_links" rows="6"><?= e($settingText('footer_product_links', $defaultFooterProductLinks)) ?></textarea>
                    <div class="form-text">Mỗi dòng: <code>Tên|link</code></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cột hỗ trợ</label>
                    <textarea class="form-control" name="footer_support_links" rows="6"><?= e($settingText('footer_support_links', $defaultFooterSupportLinks)) ?></textarea>
                    <div class="form-text">Hỗ trợ link nội bộ, anchor, mailto, tel, https.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cột chính sách</label>
                    <textarea class="form-control" name="footer_policy_links" rows="6"><?= e($settingText('footer_policy_links', $defaultFooterPolicyLinks)) ?></textarea>
                    <div class="form-text">Mỗi dòng: <code>Tên|link</code></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cam kết dịch vụ</label>
                    <textarea class="form-control" name="footer_service_commitments" rows="5"><?= e($settingText('footer_service_commitments', $defaultFooterCommitments)) ?></textarea>
                    <div class="form-text">Mỗi dòng là một cam kết.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phương thức thanh toán</label>
                    <textarea class="form-control" name="footer_payment_methods" rows="5"><?= e($settingText('footer_payment_methods', $defaultFooterPayments)) ?></textarea>
                    <div class="form-text">Mỗi dòng: <code>Tên|class icon Font Awesome</code></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ghi chú kết nối</label>
                    <input class="form-control" name="footer_social_note" value="<?= e($settingText('footer_social_note', 'Theo dõi kênh hỗ trợ để nhận cập nhật dịch vụ, ưu đãi cloud và thông báo vận hành.')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Dòng mô tả cuối footer</label>
                    <input class="form-control" name="footer_bottom_note" value="<?= e($settingText('footer_bottom_note', 'Cloud VPS, dịch vụ số và hỗ trợ kỹ thuật cho sản phẩm thực tế.')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-3">
        <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h6 fw-bold mb-0">Slide landing page</h2>
                <div class="text-secondary small">Thêm, sửa, xóa, bật/tắt và sắp xếp ảnh slide ngoài trang chủ.</div>
            </div>
            <a class="btn btn-outline-primary btn-sm" href="<?= base_url('admin/banners') ?>">
                <i class="fas fa-images me-1"></i>Quản lý slide
            </a>
        </div>
    </div>

    <div class="admin-card mb-3">
        <div class="admin-card-header d-flex justify-content-between align-items-center gap-2">
            <h2 class="h6 fw-bold mb-0">Google OAuth</h2>
            <div class="d-flex align-items-center gap-2">
                <span class="admin-badge-soft <?= is_google_oauth_configured() ? 'is-success' : 'is-muted' ?>"><?= is_google_oauth_configured() ? 'Đã cấu hình' : 'Chưa cấu hình' ?></span>
                <button type="submit" name="settings_section" value="google" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-save me-1"></i>Lưu mục này
                </button>
            </div>
        </div>
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
        <div class="admin-card-header d-flex justify-content-between align-items-center gap-2">
            <h2 class="h6 fw-bold mb-0">Payment</h2>
            <button type="submit" name="settings_section" value="payment" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-save me-1"></i>Lưu mục này
            </button>
        </div>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Ngân hàng</label><input class="form-control" name="payment_bank_name" value="<?= e((string) ($settings['payment_bank_name'] ?? '')) ?>" placeholder="VD: Vietcombank"></div>
                <div class="col-md-4"><label class="form-label">Số tài khoản</label><input class="form-control" name="payment_bank_account" value="<?= e((string) ($settings['payment_bank_account'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Chủ tài khoản</label><input class="form-control" name="payment_bank_owner" value="<?= e((string) ($settings['payment_bank_owner'] ?? '')) ?>"></div>
                <div class="col-md-4">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="sepay_enabled" name="sepay_enabled" value="1" <?= !empty($sepayConfig['enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sepay_enabled">Bật QR SePay cho nạp ví</label>
                    </div>
                </div>
                <div class="col-md-4"><label class="form-label">Mã ngân hàng SePay</label><input class="form-control" name="sepay_bank_code" value="<?= e((string) ($sepayConfig['bank_code'] ?? '')) ?>" placeholder="VD: MBBank, Vietcombank"><div class="form-text">Dùng đúng short name ngân hàng để SePay dựng QR.</div></div>
                <div class="col-md-4"><label class="form-label">Template QR</label><select class="form-select" name="sepay_qr_template"><?php $sepayQrTemplate = (string) ($sepayConfig['qr_template'] ?? 'compact'); ?><option value="" <?= $sepayQrTemplate === '' ? 'selected' : '' ?>>Mặc định</option><option value="compact" <?= $sepayQrTemplate === 'compact' ? 'selected' : '' ?>>Compact</option><option value="qronly" <?= $sepayQrTemplate === 'qronly' ? 'selected' : '' ?>>QR Only</option></select></div>
                <div class="col-md-6"><label class="form-label">Webhook token</label><input class="form-control" name="sepay_webhook_token" value="<?= e($sepayWebhookToken) ?>" placeholder="Để trống để hệ thống tự tạo khi bật SePay"><div class="form-text">Có thể để chế độ Không chứng thực trên SePay và dùng token nằm ngay trong URL webhook.</div></div>
                <div class="col-md-6"><label class="form-label">Webhook URL SePay</label><input class="form-control" value="<?= e($sepayWebhookUrl) ?>" readonly><div class="form-text">SePay cấu hình: event <code>Có tiền vào</code>, bỏ qua nếu không có code = <code>Không</code>, xác thực thanh toán = <code>Đúng</code>.</div></div>
                <div class="col-12">
                    <div class="alert alert-light border mb-0 small">
                        <strong>Gợi ý cấu hình SePay:</strong> dùng URL webhook ở trên, chọn <code>application/json</code>. Hệ thống sẽ tự tạo mã nạp ví ngắn kiểu <code>zno1234</code> và nhúng sẵn vào QR cùng số tiền để SePay tự đối soát.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-3">
        <div class="admin-card-header d-flex justify-content-between align-items-center gap-2">
            <h2 class="h6 fw-bold mb-0">SMTP</h2>
            <button type="submit" name="settings_section" value="smtp" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-save me-1"></i>Lưu mục này
            </button>
        </div>
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
        <div class="admin-card-header d-flex justify-content-between align-items-center gap-2">
            <h2 class="h6 fw-bold mb-0">Security</h2>
            <button type="submit" name="settings_section" value="security" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-save me-1"></i>Lưu mục này
            </button>
        </div>
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
        <button class="btn btn-primary px-4" name="settings_section" value="all">
            <i class="fas fa-save me-1"></i>Lưu tất cả
        </button>
    </div>
</form>
