<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Order;
use App\Models\RankProgram;
use App\Models\User;
use App\Models\UserSecurity;
use App\Models\WalletTransaction;

class ProfileController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $userModel = new User($this->config);
        $orderModel = new Order($this->config);
        $rankProgram = new RankProgram($this->config);
        $securityModel = new UserSecurity($this->config);
        $walletModel = new WalletTransaction($this->config);

        $user = $userModel->find((int) Auth::id());
        $twoFactorEnabled = !empty($user['two_factor_enabled']) && trim((string) ($user['two_factor_secret'] ?? '')) !== '';

        if ($twoFactorEnabled) {
            two_factor_clear_setup_secret((int) Auth::id());
        }

        $twoFactorSetupSecret = $twoFactorEnabled ? null : two_factor_get_or_create_setup_secret((int) Auth::id());
        $twoFactorSetupKey = $twoFactorSetupSecret ? trim(chunk_split($twoFactorSetupSecret, 4, ' ')) : null;
        $twoFactorIssuer = preg_replace('/[^A-Za-z0-9 ._-]/', '', (string) config('app.name', 'Digital Market Pro'));
        $twoFactorIssuer = $twoFactorIssuer !== '' ? $twoFactorIssuer : 'Digital Market Pro';
        $twoFactorLabel = (string) ($user['email'] ?? ('user-' . (Auth::id() ?? 0)));
        $twoFactorOtpAuthUri = $twoFactorSetupSecret ? totp_provisioning_uri($twoFactorIssuer, $twoFactorLabel, $twoFactorSetupSecret) : null;

        $currentSessionToken = (string) ((Auth::user()['session_token'] ?? ''));
        $backupStats = $securityModel->backupCodesStats((int) Auth::id());
        $freshBackupCodes = two_factor_take_plain_backup_codes((int) Auth::id());
        $loginActivities = $securityModel->recentLoginActivities((int) Auth::id(), 8);
        $activeSessions = $currentSessionToken !== ''
            ? $securityModel->activeSessions((int) Auth::id(), $currentSessionToken, 8)
            : [];
        // Normalize legacy rows without banner JSON so the profile header always renders safely.
        $bannerMeta = $this->sanitizeBannerMetadata(
            $this->decodeBannerMetadata((string) ($user['banner_media_meta'] ?? '')),
            trim((string) ($user['banner_media_type'] ?? ''))
        );

        $resetMeta = $_SESSION['_2fa_reset'][(int) Auth::id()] ?? null;
        $resetPending = is_array($resetMeta) && (int) ($resetMeta['expires_at'] ?? 0) >= time();
        $resetExpiresLabel = $resetPending ? date('H:i:s', (int) $resetMeta['expires_at']) : null;
        $rankSummary = $rankProgram->getRankSummary((int) Auth::id());
        $isAdminRole = strtolower(trim((string) ($user['role_name'] ?? ''))) === 'admin';

        $this->view('profile/index', [
            'title' => $isAdminRole ? 'Tài khoản quản trị viên' : 'Tài khoản khách hàng',
            'userPanel' => true,
            'user' => $user,
            'orders' => $orderModel->byUser((int) Auth::id()),
            'twoFactorEnabled' => $twoFactorEnabled,
            'twoFactorSetupSecret' => $twoFactorSetupSecret,
            'twoFactorSetupKey' => $twoFactorSetupKey,
            'twoFactorOtpAuthUri' => $twoFactorOtpAuthUri,
            'twoFactorIssuer' => $twoFactorIssuer,
            'twoFactorLabel' => $twoFactorLabel,
            'backupStats' => $backupStats,
            'freshBackupCodes' => $freshBackupCodes,
            'loginActivities' => $loginActivities,
            'activeSessions' => $activeSessions,
            'resetPending' => $resetPending,
            'resetExpiresLabel' => $resetExpiresLabel,
            'rankSummary' => $rankSummary,
            'walletSummary' => $walletModel->summaryByUser((int) Auth::id()),
            'walletTransactions' => $walletModel->recentByUser((int) Auth::id(), 20),
            'bannerMeta' => $bannerMeta,
        ]);
    }

    public function depositWallet(): void
    {
        $this->requireAuth();
        $this->requirePostWithCsrf('profile?tab=wallet-log');
        $this->throttleProfileAction('profile_sensitive', 'profile?tab=wallet-log');

        $allowedMethods = ['bank_transfer', 'momo', 'zalopay', 'card'];
        $amountDigits = preg_replace('/\D+/', '', (string) ($_POST['amount'] ?? '')) ?? '';
        $paymentMethod = sanitize_text((string) ($_POST['payment_method'] ?? 'bank_transfer'), 40);
        $note = sanitize_text((string) ($_POST['note'] ?? ''), 160);

        set_old([
            'wallet_amount' => $amountDigits,
            'wallet_payment_method' => $paymentMethod,
            'wallet_note' => $note,
        ]);

        if ($amountDigits === '') {
            flash('danger', 'Vui lòng nhập số tiền muốn nạp.');
            redirect('profile?tab=wallet-log');
        }

        $amount = (float) $amountDigits;
        if ($amount < 10000 || $amount > 50000000) {
            flash('danger', 'Số tiền nạp phải từ 10.000 ₫ đến 50.000.000 ₫.');
            redirect('profile?tab=wallet-log');
        }

        if (!in_array($paymentMethod, $allowedMethods, true)) {
            $paymentMethod = 'bank_transfer';
        }

        $walletModel = new WalletTransaction($this->config);
        $transaction = $walletModel->createInstantDeposit((int) Auth::id(), $amount, $paymentMethod, $note);

        if ($transaction === null) {
            flash('danger', 'Không thể nạp số dư lúc này. Vui lòng thử lại.');
            redirect('profile?tab=wallet-log');
        }

        $this->refreshAuthUser(new User($this->config));
        clear_old();

        flash('success', 'Nạp thành công ' . format_money($amount) . '. Mã giao dịch: ' . $transaction['transaction_code']);
        redirect('profile?tab=wallet-log');
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requirePostWithCsrf('profile?tab=profile');

        $userModel = new User($this->config);
        $currentUser = $userModel->find((int) Auth::id());

        $avatar = $currentUser['avatar'] ?? null;
        $bannerMedia = $currentUser['banner_media'] ?? null;
        $bannerMediaType = $currentUser['banner_media_type'] ?? null;
        $existingBannerMeta = $this->decodeBannerMetadata((string) ($currentUser['banner_media_meta'] ?? ''));
        if (!empty($_FILES['avatar']['name'])) {
            $uploaded = secure_upload_image($_FILES['avatar'], 'avatar');
            if ($uploaded === null) {
                flash('danger', 'Avatar không hợp lệ hoặc vượt quá giới hạn dung lượng.');
                redirect('profile?tab=profile');
            }
            $avatar = $uploaded;
        }

        if (!empty($_FILES['banner']['name'])) {
            $bannerMime = strtolower((string) ($_FILES['banner']['type'] ?? ''));
            if (str_starts_with($bannerMime, 'image/')) {
                $uploadedBanner = secure_upload_image($_FILES['banner'], 'banner');
                if ($uploadedBanner === null) {
                    flash('danger', 'Ảnh bìa không hợp lệ hoặc vượt quá giới hạn dung lượng.');
                    redirect('profile?tab=profile');
                }

                $bannerMedia = $uploadedBanner;
                $bannerMediaType = 'image';
            } elseif (str_starts_with($bannerMime, 'video/')) {
                $uploadedBannerVideo = secure_upload_video($_FILES['banner'], 'banner_video');
                if ($uploadedBannerVideo === null) {
                    flash('danger', 'Video bìa không hợp lệ hoặc vượt quá giới hạn dung lượng.');
                    redirect('profile?tab=profile');
                }

                $bannerMedia = $uploadedBannerVideo;
                $bannerMediaType = 'video';
            } else {
                flash('danger', 'Banner chỉ hỗ trợ ảnh hoặc video hợp lệ.');
                redirect('profile?tab=profile');
            }
        }

        $fullName = sanitize_text((string) ($_POST['full_name'] ?? ''), 120);
        $phone = sanitize_text((string) ($_POST['phone'] ?? ''), 20);
        $address = sanitize_text((string) ($_POST['address'] ?? ''), 255);
        $gender = normalize_user_gender((string) ($_POST['gender'] ?? 'unknown'));
        $birthDateInput = (string) ($_POST['birth_date'] ?? '');
        $birthDate = normalize_birth_date($birthDateInput);

        set_old([
            'full_name' => $fullName,
            'phone' => $phone,
            'address' => $address,
            'gender' => $gender,
            'birth_date' => $birthDateInput,
        ]);

        if ($fullName === '') {
            flash('danger', 'Họ tên không được để trống.');
            redirect('profile?tab=profile');
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
            flash('danger', 'Số điện thoại không hợp lệ.');
            redirect('profile?tab=profile');
        }

        if (trim($birthDateInput) !== '' && $birthDate === null) {
            flash('danger', 'Ngày sinh không hợp lệ hoặc nằm ngoài khoảng cho phép.');
            redirect('profile?tab=profile');
        }

        $bannerMeta = $this->sanitizeBannerMetadata([
            'fit_mode' => $_POST['banner_fit_mode'] ?? null,
            'zoom' => $_POST['banner_zoom'] ?? null,
            'position_x' => $_POST['banner_position_x'] ?? null,
            'position_y' => $_POST['banner_position_y'] ?? null,
            'height_mode' => $_POST['banner_height_mode'] ?? null,
            'video_start' => $_POST['banner_video_start'] ?? null,
            'video_end' => $_POST['banner_video_end'] ?? null,
        ], is_string($bannerMediaType) ? $bannerMediaType : null, $existingBannerMeta);
        $bannerMetaPayload = json_encode($bannerMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ok = $userModel->updateProfile((int) Auth::id(), [
            'full_name' => $fullName,
            'phone' => $phone,
            'address' => $address,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'avatar' => $avatar,
            'banner_media' => $bannerMedia,
            'banner_media_type' => $bannerMediaType,
            'banner_media_meta' => $bannerMetaPayload !== false ? $bannerMetaPayload : null,
        ]);

        if ($ok) {
            $updated = $userModel->find((int) Auth::id());
            Auth::login($updated);
            clear_old();
            flash('success', 'Cập nhật hồ sơ thành công.');
        } else {
            flash('danger', 'Không thể cập nhật hồ sơ.');
        }

        redirect('profile?tab=profile');
    }

    private function decodeBannerMetadata(string $rawMeta): array
    {
        $rawMeta = trim($rawMeta);
        if ($rawMeta === '') {
            return [];
        }

        $decoded = json_decode($rawMeta, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function sanitizeBannerMetadata(array $input, ?string $mediaType, array $fallback = []): array
    {
        $allowedFits = ['cover', 'contain', 'fill'];
        $allowedHeights = ['narrow', 'standard', 'tall'];
        $resolvedType = $this->normalizeBannerMediaType($mediaType)
            ?? $this->normalizeBannerMediaType((string) ($fallback['media_type'] ?? ''))
            ?? $this->normalizeBannerMediaType((string) ($input['media_type'] ?? ''));

        $fitMode = strtolower(trim((string) ($input['fit_mode'] ?? ($fallback['fit_mode'] ?? 'cover'))));
        if (!in_array($fitMode, $allowedFits, true)) {
            $fitMode = 'cover';
        }

        $heightMode = strtolower(trim((string) ($input['height_mode'] ?? ($fallback['height_mode'] ?? 'standard'))));
        if (!in_array($heightMode, $allowedHeights, true)) {
            $heightMode = 'standard';
        }

        $videoStart = $this->clampBannerFloat($input['video_start'] ?? ($fallback['video_start'] ?? 0), 0, 86400, 0);
        $videoEnd = $this->clampBannerFloat($input['video_end'] ?? ($fallback['video_end'] ?? 0), 0, 86400, 0);
        if ($resolvedType !== 'video') {
            $videoStart = 0;
            $videoEnd = 0;
        } elseif ($videoEnd > 0 && $videoEnd < $videoStart) {
            $videoEnd = $videoStart;
        }

        return [
            'media_type' => $resolvedType,
            'fit_mode' => $fitMode,
            'zoom' => $this->clampBannerFloat($input['zoom'] ?? ($fallback['zoom'] ?? 1), 1, 2.8, 1),
            'position_x' => $this->clampBannerFloat($input['position_x'] ?? ($fallback['position_x'] ?? 50), 0, 100, 50),
            'position_y' => $this->clampBannerFloat($input['position_y'] ?? ($fallback['position_y'] ?? 50), 0, 100, 50),
            'height_mode' => $heightMode,
            'video_start' => $videoStart,
            'video_end' => $videoEnd,
        ];
    }

    private function normalizeBannerMediaType(?string $mediaType): ?string
    {
        $mediaType = strtolower(trim((string) $mediaType));
        if ($mediaType === 'image' || $mediaType === 'video') {
            return $mediaType;
        }

        return null;
    }

    private function clampBannerFloat(mixed $value, float $min, float $max, float $default): float
    {
        $resolved = is_numeric($value) ? (float) $value : $default;
        if (!is_finite($resolved)) {
            $resolved = $default;
        }

        if ($resolved < $min) {
            $resolved = $min;
        }

        if ($resolved > $max) {
            $resolved = $max;
        }

        return round($resolved, 3);
    }

    public function changePassword(): void
    {
        $this->requireAuth();
        $this->requirePostWithCsrf('profile?tab=password');

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $userModel = new User($this->config);
        $user = $userModel->find((int) Auth::id());

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            flash('danger', 'Mật khẩu hiện tại không chính xác.');
            redirect('profile?tab=password');
        }

        $passwordError = validate_password_strength($newPassword);
        if ($passwordError !== null || $newPassword !== $confirmPassword) {
            flash('danger', $passwordError ?? 'Mật khẩu mới không hợp lệ.');
            redirect('profile?tab=password');
        }

        $ok = $userModel->updatePassword((int) Auth::id(), password_hash($newPassword, PASSWORD_DEFAULT));

        if ($ok) {
            flash('success', 'Đổi mật khẩu thành công.');
        } else {
            flash('danger', 'Không thể đổi mật khẩu.');
        }

        redirect('profile?tab=password');
    }

    public function enableTwoFactor(): void
    {
        $this->requireAuth();
        $this->requirePostWithCsrf('profile?tab=security');
        $this->throttleProfileAction('profile_otp', 'profile?tab=security');

        $userModel = new User($this->config);
        $user = $userModel->find((int) Auth::id());
        if (!$user) {
            flash('danger', 'Không tìm thấy tài khoản.');
            redirect('profile?tab=security');
        }

        if (!empty($user['two_factor_enabled']) && trim((string) ($user['two_factor_secret'] ?? '')) !== '') {
            flash('info', '2FA đã được bật trước đó.');
            redirect('profile?tab=security');
        }

        $setupSecret = two_factor_get_setup_secret((int) Auth::id());
        if ($setupSecret === null) {
            $setupSecret = two_factor_get_or_create_setup_secret((int) Auth::id());
        }

        $otpCode = otp_digits_only((string) ($_POST['otp_code'] ?? ''));
        if (strlen($otpCode) !== 6) {
            flash('danger', 'Mã OTP phải gồm đúng 6 số.');
            redirect('profile?tab=security');
        }

        if (!totp_verify_code($setupSecret, $otpCode, 0)) {
            flash('danger', 'Mã OTP không chính xác hoặc đã hết hạn. Vui lòng kiểm tra lại ứng dụng authenticator.');
            redirect('profile?tab=security');
        }

        $ok = $userModel->enableTwoFactor((int) Auth::id(), two_factor_secret_for_storage($setupSecret));
        if (!$ok) {
            flash('danger', 'Không thể bật 2FA lúc này.');
            redirect('profile?tab=security');
        }

        $securityModel = new UserSecurity($this->config);
        $codes = two_factor_generate_backup_codes(10);
        $securityModel->replaceBackupCodes((int) Auth::id(), $codes);
        two_factor_store_plain_backup_codes((int) Auth::id(), $codes);

        two_factor_clear_setup_secret((int) Auth::id());
        $updated = $userModel->find((int) Auth::id());
        if ($updated) {
            Auth::login($updated);
        }

        flash('success', 'Đã bật 2FA thành công. Từ lần đăng nhập tiếp theo, hệ thống sẽ yêu cầu mã OTP.');
        redirect('profile?tab=security');
    }

    public function disableTwoFactor(): void
    {
        $this->requireAuth();
        $this->requirePostWithCsrf('profile?tab=security');
        $this->throttleProfileAction('profile_otp', 'profile?tab=security');

        $userModel = new User($this->config);
        $securityModel = new UserSecurity($this->config);
        $user = $userModel->find((int) Auth::id());

        if (!$user || empty($user['two_factor_enabled'])) {
            flash('warning', '2FA hiện chưa được bật.');
            redirect('profile?tab=security');
        }

        $otpCode = otp_digits_only((string) ($_POST['otp_code_disable'] ?? ''));
        if (strlen($otpCode) !== 6) {
            flash('danger', 'Vui lòng nhập đúng mã OTP gồm 6 số.');
            redirect('profile?tab=security');
        }

        $secret = two_factor_secret_for_verification((string) ($user['two_factor_secret'] ?? ''));
        if ($secret === '' || !totp_verify_code($secret, $otpCode, 0)) {
            flash('danger', 'Mã OTP không hợp lệ hoặc đã hết hạn.');
            redirect('profile?tab=security');
        }

        if (!$userModel->disableTwoFactor((int) Auth::id(), true)) {
            flash('danger', 'Không thể tắt 2FA lúc này.');
            redirect('profile?tab=security');
        }

        $securityModel->clearBackupCodes((int) Auth::id());
        two_factor_clear_setup_secret((int) Auth::id());
        security_clear_reset_email_code((int) Auth::id());
        $this->refreshAuthUser($userModel);

        flash('success', 'Đã tắt xác thực hai lớp thành công.');
        redirect('profile?tab=security');
    }

    public function requestTwoFactorReset(): void
    {
        $this->requireAuth();
        $this->requirePostWithCsrf('profile?tab=security');
        $this->throttleProfileAction('profile_sensitive', 'profile?tab=security');

        $password = (string) ($_POST['reset_password'] ?? '');
        if ($password === '') {
            flash('danger', 'Vui lòng nhập mật khẩu hiện tại để tiếp tục.');
            redirect('profile?tab=security');
        }

        $userModel = new User($this->config);
        $user = $userModel->find((int) Auth::id());
        if (!$user || !password_verify($password, (string) ($user['password'] ?? ''))) {
            flash('danger', 'Mật khẩu xác nhận không chính xác.');
            redirect('profile?tab=security');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $code = security_store_reset_email_code((int) Auth::id(), $passwordHash);
        security_log('Yêu cầu reset 2FA', ['user_id' => Auth::id(), 'ip' => client_ip()]);

        $message = 'Mã xác minh email đã được tạo. Demo local code: ' . $code . ' (hết hạn sau 10 phút).';
        flash('info', $message);
        redirect('profile?tab=security');
    }

    public function confirmTwoFactorReset(): void
    {
        $this->requireAuth();
        $this->requirePostWithCsrf('profile?tab=security');
        $this->throttleProfileAction('profile_sensitive', 'profile?tab=security');

        $password = (string) ($_POST['reset_password_confirm'] ?? '');
        $emailCode = otp_digits_only((string) ($_POST['reset_email_code'] ?? ''));

        if ($password === '' || strlen($emailCode) !== 6) {
            flash('danger', 'Vui lòng nhập đầy đủ mật khẩu và mã xác minh email.');
            redirect('profile?tab=security');
        }

        if (!security_verify_reset_email_code((int) Auth::id(), $password, $emailCode)) {
            flash('danger', 'Mã xác minh email hoặc mật khẩu không hợp lệ.');
            redirect('profile?tab=security');
        }

        $userModel = new User($this->config);
        $securityModel = new UserSecurity($this->config);

        if (!$userModel->disableTwoFactor((int) Auth::id(), true)) {
            flash('danger', 'Không thể reset 2FA lúc này.');
            redirect('profile?tab=security');
        }

        $securityModel->clearBackupCodes((int) Auth::id());
        two_factor_clear_setup_secret((int) Auth::id());
        $this->refreshAuthUser($userModel);

        flash('success', 'Đã reset 2FA. Vui lòng quét QR và thiết lập lại xác thực hai lớp.');
        redirect('profile?tab=security');
    }

    public function regenerateBackupCodes(): void
    {
        $this->requireAuth();
        $this->requirePostWithCsrf('profile?tab=security');
        $this->throttleProfileAction('profile_otp', 'profile?tab=security');

        $otpCode = otp_digits_only((string) ($_POST['otp_code_regen'] ?? ''));
        if (strlen($otpCode) !== 6) {
            flash('danger', 'Mã OTP không hợp lệ.');
            redirect('profile?tab=security');
        }

        $userModel = new User($this->config);
        $securityModel = new UserSecurity($this->config);
        $user = $userModel->find((int) Auth::id());

        if (!$user || empty($user['two_factor_enabled'])) {
            flash('danger', 'Bạn cần bật 2FA trước khi tạo backup codes.');
            redirect('profile?tab=security');
        }

        $secret = two_factor_secret_for_verification((string) ($user['two_factor_secret'] ?? ''));
        if ($secret === '' || !totp_verify_code($secret, $otpCode, 0)) {
            flash('danger', 'Mã OTP không chính xác hoặc đã hết hạn.');
            redirect('profile?tab=security');
        }

        $codes = two_factor_generate_backup_codes(10);
        $securityModel->replaceBackupCodes((int) Auth::id(), $codes);
        two_factor_store_plain_backup_codes((int) Auth::id(), $codes);

        flash('success', 'Đã tạo mới backup codes. Hãy lưu lại ở nơi an toàn.');
        redirect('profile?tab=security');
    }

    public function logoutOtherSessions(): void
    {
        $this->requireAuth();
        $this->requirePostWithCsrf('profile?tab=security');
        $this->throttleProfileAction('profile_sensitive', 'profile?tab=security');

        $currentToken = (string) (Auth::user()['session_token'] ?? '');
        if ($currentToken === '') {
            flash('warning', 'Không tìm thấy thông tin phiên hiện tại.');
            redirect('profile?tab=security');
        }

        $securityModel = new UserSecurity($this->config);
        $revoked = $securityModel->revokeOtherSessions((int) Auth::id(), $currentToken);

        flash('success', 'Đã đăng xuất khỏi ' . $revoked . ' thiết bị khác.');
        redirect('profile?tab=security');
    }

    public function changeEmailSecure(): void
    {
        $this->requireAuth();
        $this->requirePostWithCsrf('profile?tab=security');
        $this->throttleProfileAction('profile_sensitive', 'profile?tab=security');

        $newEmail = sanitize_text((string) ($_POST['new_email'] ?? ''), 190);
        $password = (string) ($_POST['email_change_password'] ?? '');
        $otpCode = otp_digits_only((string) ($_POST['email_change_otp'] ?? ''));

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Email mới không hợp lệ.');
            redirect('profile?tab=security');
        }

        if ($password === '' || strlen($otpCode) !== 6) {
            flash('danger', 'Vui lòng nhập mật khẩu và mã OTP hợp lệ.');
            redirect('profile?tab=security');
        }

        $userModel = new User($this->config);
        $user = $userModel->find((int) Auth::id());
        if (!$user || !password_verify($password, (string) ($user['password'] ?? ''))) {
            flash('danger', 'Mật khẩu xác nhận không đúng.');
            redirect('profile?tab=security');
        }

        if (strcasecmp((string) ($user['email'] ?? ''), $newEmail) === 0) {
            flash('warning', 'Email mới trùng với email hiện tại.');
            redirect('profile?tab=security');
        }

        if (!$this->isTwoFactorEnabled($user)) {
            flash('danger', 'Bạn cần bật 2FA để đổi email an toàn.');
            redirect('profile?tab=security');
        }

        $secret = two_factor_secret_for_verification((string) ($user['two_factor_secret'] ?? ''));
        if ($secret === '' || !totp_verify_code($secret, $otpCode, 0)) {
            flash('danger', 'Mã OTP không hợp lệ hoặc đã hết hạn.');
            redirect('profile?tab=security');
        }

        $existing = $userModel->findByEmail($newEmail);
        if ($existing && (int) ($existing['id'] ?? 0) !== (int) Auth::id()) {
            flash('danger', 'Email này đã được sử dụng bởi tài khoản khác.');
            redirect('profile?tab=security');
        }

        if (!$userModel->updateEmail((int) Auth::id(), $newEmail)) {
            flash('danger', 'Không thể cập nhật email lúc này.');
            redirect('profile?tab=security');
        }

        $this->refreshAuthUser($userModel);
        flash('success', 'Đổi email thành công.');
        redirect('profile?tab=security');
    }

    private function throttleProfileAction(string $ruleKey, string $redirect): void
    {
        $rule = (array) config('security.rate_limits.' . $ruleKey, []);
        $maxAttempts = (int) ($rule['max_attempts'] ?? 6);
        $windowSeconds = (int) ($rule['window_seconds'] ?? 300);
        $lockSeconds = (int) ($rule['lock_seconds'] ?? 600);

        $bucket = 'profile:' . $ruleKey . '|uid:' . (int) Auth::id() . '|ip:' . client_ip();
        $result = rate_limit_hit($bucket, $maxAttempts, $windowSeconds, $lockSeconds);
        if (!$result['allowed']) {
            flash('danger', 'Bạn thao tác quá nhanh. Vui lòng thử lại sau ' . (int) $result['retry_after'] . ' giây.');
            redirect($redirect);
        }
    }

    private function refreshAuthUser(User $userModel): void
    {
        $updated = $userModel->find((int) Auth::id());
        if (!$updated) {
            return;
        }

        $updated['session_token'] = (string) (Auth::user()['session_token'] ?? '');
        Auth::login($updated);
    }

    private function isTwoFactorEnabled(array $user): bool
    {
        return !empty($user['two_factor_enabled']) && trim((string) ($user['two_factor_secret'] ?? '')) !== '';
    }
}
