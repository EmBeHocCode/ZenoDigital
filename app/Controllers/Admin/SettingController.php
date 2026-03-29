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

        $data = [
            'site_name' => sanitize_text((string) ($_POST['site_name'] ?? 'Digital Market Pro'), 120),
            'contact_email' => sanitize_text((string) ($_POST['contact_email'] ?? ''), 190),
            'contact_phone' => sanitize_text((string) ($_POST['contact_phone'] ?? ''), 20),
            'address' => sanitize_text((string) ($_POST['address'] ?? ''), 255),
            'footer_text' => sanitize_text((string) ($_POST['footer_text'] ?? ''), 255),
            'facebook_url' => sanitize_text((string) ($_POST['facebook_url'] ?? ''), 255),
            'zalo_url' => sanitize_text((string) ($_POST['zalo_url'] ?? ''), 255),
            'google_oauth_enabled' => isset($_POST['google_oauth_enabled']) ? '1' : '0',
            'google_client_id' => sanitize_text((string) ($_POST['google_client_id'] ?? ''), 255),
            'google_redirect_uri' => sanitize_text((string) ($_POST['google_redirect_uri'] ?? ''), 255),
            'payment_bank_name' => sanitize_text((string) ($_POST['payment_bank_name'] ?? ''), 120),
            'payment_bank_account' => sanitize_text((string) ($_POST['payment_bank_account'] ?? ''), 80),
            'payment_bank_owner' => sanitize_text((string) ($_POST['payment_bank_owner'] ?? ''), 120),
            'smtp_host' => sanitize_text((string) ($_POST['smtp_host'] ?? ''), 120),
            'smtp_port' => sanitize_text((string) ($_POST['smtp_port'] ?? ''), 10),
            'smtp_username' => sanitize_text((string) ($_POST['smtp_username'] ?? ''), 120),
            'smtp_password' => sanitize_text((string) ($_POST['smtp_password'] ?? ''), 120),
            'smtp_encryption' => validate_enum((string) ($_POST['smtp_encryption'] ?? 'tls'), ['', 'tls', 'ssl'], 'tls'),
            'require_2fa_admin' => isset($_POST['require_2fa_admin']) ? '1' : '0',
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
        ];

        $googleClientSecret = trim((string) ($_POST['google_client_secret'] ?? ''));
        if ($googleClientSecret !== '') {
            $data['google_client_secret'] = sanitize_text($googleClientSecret, 255);
        } elseif (isset($existingSettings['google_client_secret'])) {
            $data['google_client_secret'] = (string) $existingSettings['google_client_secret'];
        }

        $smtpPassword = trim((string) ($_POST['smtp_password'] ?? ''));
        if ($smtpPassword !== '') {
            $data['smtp_password'] = sanitize_text($smtpPassword, 120);
        } elseif (isset($existingSettings['smtp_password'])) {
            $data['smtp_password'] = (string) $existingSettings['smtp_password'];
        }

        if ($data['contact_email'] !== '' && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Email liên hệ không hợp lệ.');
            redirect('admin/settings');
        }

        if ($data['contact_phone'] !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $data['contact_phone'])) {
            flash('danger', 'Số điện thoại liên hệ không hợp lệ.');
            redirect('admin/settings');
        }

        foreach (['facebook_url', 'zalo_url'] as $urlKey) {
            if ($data[$urlKey] !== '' && !filter_var($data[$urlKey], FILTER_VALIDATE_URL)) {
                flash('danger', 'URL ' . $urlKey . ' không hợp lệ.');
                redirect('admin/settings');
            }
        }

        if ($data['google_redirect_uri'] !== '' && !filter_var($data['google_redirect_uri'], FILTER_VALIDATE_URL)) {
            flash('danger', 'Redirect URI của Google OAuth không hợp lệ.');
            redirect('admin/settings');
        }

        if ($data['google_oauth_enabled'] === '1') {
            $effectiveGoogleSecret = trim((string) ($data['google_client_secret'] ?? ''));
            if ($data['google_client_id'] === '' || $effectiveGoogleSecret === '') {
                flash('danger', 'Muốn bật đăng nhập Google, bạn phải nhập đầy đủ Google Client ID và Google Client Secret.');
                redirect('admin/settings');
            }
        }

        if (!empty($_FILES['logo']['name'])) {
            $logo = secure_upload_image($_FILES['logo'], 'logo');
            if ($logo) {
                $data['site_logo'] = $logo;
            } else {
                flash('danger', 'Logo không hợp lệ hoặc vượt quá dung lượng cho phép.');
                redirect('admin/settings');
            }
        }

        if (!empty($_FILES['favicon']['name'])) {
            $favicon = secure_upload_image($_FILES['favicon'], 'favicon');
            if ($favicon) {
                $data['site_favicon'] = $favicon;
            } else {
                flash('danger', 'Favicon không hợp lệ hoặc vượt quá dung lượng cho phép.');
                redirect('admin/settings');
            }
        }

        $model->upsertMany($data);
        app_settings(true);
        admin_audit('update', 'settings', null, [
            'site_name' => $data['site_name'],
            'google_oauth_enabled' => $data['google_oauth_enabled'],
            'require_2fa_admin' => $data['require_2fa_admin'],
            'maintenance_mode' => $data['maintenance_mode'],
        ]);

        flash('success', 'Cập nhật cài đặt hệ thống thành công.');
        redirect('admin/settings');
    }
}
