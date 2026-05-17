<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Setting;

class SettingController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $model = new Setting($this->config);
        $this->view('admin/settings/index', ['settings' => $model->all()], 'admin');
    }

    public function update(): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/settings');

        $model = new Setting($this->config);
        $existingSettings = $model->all();
        $section = $this->settingsSection();
        $sectionKeys = $this->settingsSectionKeys($section);

        $data = [
            'site_name' => normalize_public_brand_name(
                sanitize_text((string) ($_POST['site_name'] ?? 'ZenoDigital'), 120)
            ),
            'contact_email' => sanitize_text((string) ($_POST['contact_email'] ?? ''), 190),
            'contact_email_secondary' => sanitize_text((string) ($_POST['contact_email_secondary'] ?? ''), 190),
            'contact_phone' => sanitize_text((string) ($_POST['contact_phone'] ?? ''), 20),
            'address' => sanitize_text((string) ($_POST['address'] ?? ''), 255),
            'footer_text' => sanitize_text((string) ($_POST['footer_text'] ?? ''), 255),
            'facebook_url' => sanitize_text((string) ($_POST['facebook_url'] ?? ''), 255),
            'zalo_url' => sanitize_text((string) ($_POST['zalo_url'] ?? ''), 255),
            'telegram_url' => sanitize_text((string) ($_POST['telegram_url'] ?? ''), 255),
            'youtube_url' => sanitize_text((string) ($_POST['youtube_url'] ?? ''), 255),
            'footer_social_note' => sanitize_text((string) ($_POST['footer_social_note'] ?? ''), 255),
            'footer_cta_kicker' => sanitize_text((string) ($_POST['footer_cta_kicker'] ?? ''), 120),
            'footer_cta_title' => sanitize_text((string) ($_POST['footer_cta_title'] ?? ''), 180),
            'footer_cta_description' => sanitize_text((string) ($_POST['footer_cta_description'] ?? ''), 255),
            'footer_cta_primary_label' => sanitize_text((string) ($_POST['footer_cta_primary_label'] ?? ''), 80),
            'footer_cta_primary_url' => sanitize_text((string) ($_POST['footer_cta_primary_url'] ?? ''), 255),
            'footer_cta_secondary_label' => sanitize_text((string) ($_POST['footer_cta_secondary_label'] ?? ''), 80),
            'footer_cta_secondary_url' => sanitize_text((string) ($_POST['footer_cta_secondary_url'] ?? ''), 255),
            'footer_product_links' => $this->sanitizeMultilineSetting('footer_product_links'),
            'footer_support_links' => $this->sanitizeMultilineSetting('footer_support_links'),
            'footer_policy_links' => $this->sanitizeMultilineSetting('footer_policy_links'),
            'footer_service_commitments' => $this->sanitizeMultilineSetting('footer_service_commitments'),
            'footer_payment_methods' => $this->sanitizeMultilineSetting('footer_payment_methods'),
            'footer_bottom_note' => sanitize_text((string) ($_POST['footer_bottom_note'] ?? ''), 255),
            'google_oauth_enabled' => isset($_POST['google_oauth_enabled']) ? '1' : '0',
            'google_client_id' => sanitize_text((string) ($_POST['google_client_id'] ?? ''), 255),
            'google_redirect_uri' => sanitize_text((string) ($_POST['google_redirect_uri'] ?? ''), 255),
            'payment_bank_name' => sanitize_text((string) ($_POST['payment_bank_name'] ?? ''), 120),
            'payment_bank_account' => sanitize_text((string) ($_POST['payment_bank_account'] ?? ''), 80),
            'payment_bank_owner' => sanitize_text((string) ($_POST['payment_bank_owner'] ?? ''), 120),
            'sepay_enabled' => isset($_POST['sepay_enabled']) ? '1' : '0',
            'sepay_bank_code' => sanitize_text((string) ($_POST['sepay_bank_code'] ?? ''), 40),
            'sepay_qr_template' => validate_enum((string) ($_POST['sepay_qr_template'] ?? 'compact'), ['', 'compact', 'qronly'], 'compact'),
            'smtp_host' => sanitize_text((string) ($_POST['smtp_host'] ?? ''), 120),
            'smtp_port' => sanitize_text((string) ($_POST['smtp_port'] ?? ''), 10),
            'smtp_username' => sanitize_text((string) ($_POST['smtp_username'] ?? ''), 120),
            'smtp_password' => sanitize_text((string) ($_POST['smtp_password'] ?? ''), 120),
            'smtp_encryption' => validate_enum((string) ($_POST['smtp_encryption'] ?? 'tls'), ['', 'tls', 'ssl'], 'tls'),
            'require_2fa_admin' => isset($_POST['require_2fa_admin']) ? '1' : '0',
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
        ];

        if ($this->shouldSaveSection($section, 'google')) {
            $googleClientSecret = trim((string) ($_POST['google_client_secret'] ?? ''));
            if ($googleClientSecret !== '') {
                $data['google_client_secret'] = sanitize_text($googleClientSecret, 255);
            } elseif (isset($existingSettings['google_client_secret'])) {
                $data['google_client_secret'] = (string) $existingSettings['google_client_secret'];
            }
        }

        if ($this->shouldSaveSection($section, 'smtp')) {
            $smtpPassword = trim((string) ($_POST['smtp_password'] ?? ''));
            if ($smtpPassword !== '') {
                $data['smtp_password'] = sanitize_text($smtpPassword, 120);
            } elseif (isset($existingSettings['smtp_password'])) {
                $data['smtp_password'] = (string) $existingSettings['smtp_password'];
            }
        }

        if ($this->shouldSaveSection($section, 'payment')) {
            $sepayWebhookToken = trim((string) ($_POST['sepay_webhook_token'] ?? ''));
            if ($sepayWebhookToken !== '') {
                $data['sepay_webhook_token'] = sanitize_text($sepayWebhookToken, 120);
            } elseif (isset($existingSettings['sepay_webhook_token']) && trim((string) $existingSettings['sepay_webhook_token']) !== '') {
                $data['sepay_webhook_token'] = (string) $existingSettings['sepay_webhook_token'];
            } elseif ($data['sepay_enabled'] === '1') {
                $data['sepay_webhook_token'] = bin2hex(random_bytes(16));
            }
        }

        $this->validateSection($section, $data);

        if ($this->shouldSaveSection($section, 'branding') && !empty($_FILES['logo']['name'])) {
            $logo = secure_upload_image($_FILES['logo'], 'logo');
            if (!$logo) {
                flash('danger', 'Logo không hợp lệ hoặc vượt quá dung lượng cho phép.');
                redirect('admin/settings');
            }
            $data['site_logo'] = $logo;
        }

        if ($this->shouldSaveSection($section, 'branding') && !empty($_FILES['favicon']['name'])) {
            $favicon = secure_upload_image($_FILES['favicon'], 'favicon');
            if (!$favicon) {
                flash('danger', 'Favicon không hợp lệ hoặc vượt quá dung lượng cho phép.');
                redirect('admin/settings');
            }
            $data['site_favicon'] = $favicon;
        }

        $data = array_intersect_key($data, array_flip($sectionKeys));
        $model->upsertMany($data);
        app_settings(true);

        admin_audit('update', 'settings', null, [
            'section' => $section,
            'keys' => array_keys($data),
        ]);

        flash('success', 'Đã lưu ' . $this->settingsSectionLabel($section) . '.');
        redirect('admin/settings');
    }

    private function validateSection(string $section, array $data): void
    {
        if ($this->shouldSaveSection($section, 'general')) {
            if ($data['contact_email'] !== '' && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
                flash('danger', 'Email liên hệ không hợp lệ.');
                redirect('admin/settings');
            }

            if ($data['contact_email_secondary'] !== '' && !filter_var($data['contact_email_secondary'], FILTER_VALIDATE_EMAIL)) {
                flash('danger', 'Email phụ không hợp lệ.');
                redirect('admin/settings');
            }

            if ($data['contact_phone'] !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $data['contact_phone'])) {
                flash('danger', 'Số điện thoại liên hệ không hợp lệ.');
                redirect('admin/settings');
            }
        }

        if ($this->shouldSaveSection($section, 'branding')) {
            foreach (['facebook_url', 'zalo_url', 'telegram_url', 'youtube_url'] as $urlKey) {
                if ($data[$urlKey] !== '' && !filter_var($data[$urlKey], FILTER_VALIDATE_URL)) {
                    flash('danger', 'URL ' . $urlKey . ' không hợp lệ.');
                    redirect('admin/settings');
                }
            }
        }

        if ($this->shouldSaveSection($section, 'google')) {
            if ($data['google_redirect_uri'] !== '' && !filter_var($data['google_redirect_uri'], FILTER_VALIDATE_URL)) {
                flash('danger', 'Redirect URI của Google OAuth không hợp lệ.');
                redirect('admin/settings');
            }

            if ($data['google_oauth_enabled'] === '1') {
                $effectiveGoogleSecret = trim((string) ($data['google_client_secret'] ?? ''));
                if ($data['google_client_id'] === '' || $effectiveGoogleSecret === '') {
                    flash('danger', 'Muốn bật đăng nhập Google, bạn phải nhập đủ Google Client ID và Google Client Secret.');
                    redirect('admin/settings');
                }
            }
        }

        if ($this->shouldSaveSection($section, 'payment') && $data['sepay_enabled'] === '1') {
            if ($data['payment_bank_account'] === '' || $data['payment_bank_owner'] === '' || $data['sepay_bank_code'] === '') {
                flash('danger', 'Muốn bật SePay QR, bạn phải nhập đủ số tài khoản, chủ tài khoản và mã ngân hàng SePay.');
                redirect('admin/settings');
            }
        }
    }

    private function settingsSection(): string
    {
        return validate_enum(
            (string) ($_POST['settings_section'] ?? 'all'),
            ['all', 'general', 'branding', 'footer', 'google', 'payment', 'smtp', 'security'],
            'all'
        );
    }

    private function shouldSaveSection(string $section, string $target): bool
    {
        return $section === 'all' || $section === $target;
    }

    private function settingsSectionKeys(string $section): array
    {
        $map = [
            'general' => ['site_name', 'contact_email', 'contact_email_secondary', 'contact_phone', 'address', 'footer_text'],
            'branding' => ['site_logo', 'site_favicon', 'facebook_url', 'zalo_url', 'telegram_url', 'youtube_url'],
            'footer' => [
                'footer_social_note',
                'footer_cta_kicker',
                'footer_cta_title',
                'footer_cta_description',
                'footer_cta_primary_label',
                'footer_cta_primary_url',
                'footer_cta_secondary_label',
                'footer_cta_secondary_url',
                'footer_product_links',
                'footer_support_links',
                'footer_policy_links',
                'footer_service_commitments',
                'footer_payment_methods',
                'footer_bottom_note',
            ],
            'google' => ['google_oauth_enabled', 'google_client_id', 'google_client_secret', 'google_redirect_uri'],
            'payment' => ['payment_bank_name', 'payment_bank_account', 'payment_bank_owner', 'sepay_enabled', 'sepay_bank_code', 'sepay_qr_template', 'sepay_webhook_token'],
            'smtp' => ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption'],
            'security' => ['require_2fa_admin', 'maintenance_mode'],
        ];

        if ($section !== 'all') {
            return $map[$section] ?? $map['general'];
        }

        $keys = [];
        foreach ($map as $sectionKeys) {
            $keys = array_merge($keys, $sectionKeys);
        }

        return array_values(array_unique($keys));
    }

    private function settingsSectionLabel(string $section): string
    {
        return [
            'all' => 'toàn bộ cài đặt',
            'general' => 'mục General',
            'branding' => 'mục Branding',
            'footer' => 'mục Footer',
            'google' => 'mục Google OAuth',
            'payment' => 'mục Payment',
            'smtp' => 'mục SMTP',
            'security' => 'mục Security',
        ][$section] ?? 'cài đặt';
    }

    private function sanitizeMultilineSetting(string $key, int $maxLength = 4000): string
    {
        $value = sanitize_text((string) ($_POST[$key] ?? ''), $maxLength);

        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}
