<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;
use App\Models\UserSecurity;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (($_GET['cancel_2fa'] ?? '') === '1') {
            $this->clearPendingTwoFactorChallenge();
        }

        if ($this->getPendingTwoFactorChallenge() !== null) {
            redirect('login/2fa');
        }

        $this->view('auth/login', [], 'auth');
    }

    public function login(): void
    {
        $this->requirePostWithCsrf('login');
        $this->applyRateLimit('login');
        $this->clearPendingTwoFactorChallenge();

        $email = sanitize_text((string) ($_POST['email'] ?? ''), 190);
        $password = (string) ($_POST['password'] ?? '');
        set_old(['email' => $email]);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            flash('danger', 'Email hoặc mật khẩu không hợp lệ.');
            redirect('login');
        }

        $userModel = new User($this->config);
        $user = $userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            flash('danger', 'Thông tin đăng nhập không chính xác.');
            redirect('login');
        }

        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $userModel->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        if (($user['status'] ?? 'active') !== 'active') {
            flash('danger', 'Tài khoản của bạn đang bị khóa.');
            redirect('login');
        }

        if ($this->isTwoFactorEnabled($user)) {
            $this->storePendingTwoFactorChallenge($user);
            clear_old();
            flash('info', 'Nhập mã OTP từ ứng dụng authenticator để hoàn tất đăng nhập.');
            redirect('login/2fa');
        }

        $user = $this->finalizeAuthenticatedSession($user);
        $this->clearPendingTwoFactorChallenge();
        $this->resetRateLimit('login');
        clear_old();
        flash('success', 'Đăng nhập thành công.');

        if (Auth::can('backoffice.dashboard')) {
            redirect('admin');
        }

        redirect('/');
    }

    public function showRegister(): void
    {
        $this->view('auth/register', [], 'auth');
    }

    public function redirectGoogle(): void
    {
        $googleConfig = google_oauth_config();
        if (!$this->isGoogleLoginEnabled($googleConfig)) {
            flash('danger', 'Đăng nhập Google chưa sẵn sàng. Vui lòng liên hệ quản trị viên để cấu hình Google OAuth.');
            redirect('login');
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['_google_oauth_state'] = [
            'value' => $state,
            'expires_at' => time() + 600,
        ];

        $query = http_build_query([
            'client_id' => (string) $googleConfig['client_id'],
            'redirect_uri' => $this->googleRedirectUri($googleConfig),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query);
        exit;
    }

    public function callbackGoogle(): void
    {
        $googleConfig = google_oauth_config();
        if (!$this->isGoogleLoginEnabled($googleConfig)) {
            flash('danger', 'Đăng nhập Google chưa sẵn sàng. Vui lòng liên hệ quản trị viên để cấu hình Google OAuth.');
            redirect('login');
        }

        $oauthError = trim((string) ($_GET['error'] ?? ''));
        if ($oauthError !== '') {
            $oauthErrorDescription = trim((string) ($_GET['error_description'] ?? ''));
            $message = 'Google OAuth trả về lỗi: ' . $oauthError . '.';
            if ($oauthErrorDescription !== '') {
                $message .= ' ' . $oauthErrorDescription;
            }

            flash('danger', $message);
            redirect('login');
        }

        $state = (string) ($_GET['state'] ?? '');
        $code = (string) ($_GET['code'] ?? '');
        if (!$this->validateGoogleOauthState($state) || $code === '') {
            flash('danger', 'Yêu cầu đăng nhập Google không hợp lệ hoặc đã hết hạn.');
            redirect('login');
        }

        $token = $this->googleTokenRequest($code, $googleConfig);
        if (!empty($token['error'])) {
            $tokenError = sanitize_text((string) ($token['error'] ?? ''), 120);
            $tokenDescription = sanitize_text((string) ($token['error_description'] ?? ''), 255);
            flash('danger', 'Không thể trao đổi token với Google: ' . $tokenError . ($tokenDescription !== '' ? (' - ' . $tokenDescription) : '') . '.');
            redirect('login');
        }

        $accessToken = (string) ($token['access_token'] ?? '');
        if ($accessToken === '') {
            flash('danger', 'Không thể lấy access token từ Google. Vui lòng thử lại.');
            redirect('login');
        }

        $profile = $this->googleUserInfoRequest($accessToken);
        if (!empty($profile['error'])) {
            $profileError = sanitize_text((string) ($profile['error'] ?? ''), 120);
            flash('danger', 'Không thể lấy thông tin người dùng từ Google: ' . $profileError . '.');
            redirect('login');
        }

        $email = sanitize_text((string) ($profile['email'] ?? ''), 190);
        $fullName = sanitize_text((string) ($profile['name'] ?? ''), 120);
        $emailVerified = $this->isGoogleEmailVerified($profile['email_verified'] ?? false);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$emailVerified) {
            flash('danger', 'Tài khoản Google chưa xác minh email hoặc không hợp lệ.');
            redirect('login');
        }

        $userModel = new User($this->config);
        $user = $userModel->findByEmail($email);

        if (!$user) {
            $seed = $fullName !== '' ? $fullName : explode('@', $email)[0];
            $username = $this->generateUniqueUsername($userModel, $seed);
            $created = $userModel->create([
                'role_id' => 2,
                'full_name' => $fullName !== '' ? $fullName : $username,
                'username' => $username,
                'email' => $email,
                'phone' => null,
                'address' => null,
                'gender' => 'unknown',
                'birth_date' => null,
                'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                'avatar' => null,
                'status' => 'active',
            ]);

            if (!$created) {
                flash('danger', 'Không thể tạo tài khoản từ Google. Vui lòng thử lại.');
                redirect('login');
            }

            $user = $userModel->findByEmail($email);
        }

        if (!$user) {
            flash('danger', 'Không thể đăng nhập bằng Google. Vui lòng thử lại.');
            redirect('login');
        }

        if (($user['status'] ?? 'active') !== 'active') {
            flash('danger', 'Tài khoản của bạn đang bị khóa.');
            redirect('login');
        }

        if ($this->isTwoFactorEnabled($user)) {
            $this->storePendingTwoFactorChallenge($user);
            clear_old();
            flash('info', 'Nhập mã OTP từ ứng dụng authenticator để hoàn tất đăng nhập.');
            redirect('login/2fa');
        }

        $user = $this->finalizeAuthenticatedSession($user);
        $this->clearPendingTwoFactorChallenge();
        clear_old();
        flash('success', 'Đăng nhập Google thành công.');

        if (Auth::can('backoffice.dashboard')) {
            redirect('admin');
        }

        redirect('/');
    }

    public function showTwoFactorChallenge(): void
    {
        $challenge = $this->getPendingTwoFactorChallenge();
        if ($challenge === null) {
            flash('warning', 'Phiên xác minh 2FA không còn hiệu lực. Vui lòng đăng nhập lại.');
            redirect('login');
        }

        $this->view('auth/login_2fa', [
            'title' => 'Xác minh 2FA',
            'challengeEmail' => $challenge['email'],
        ], 'auth');
    }

    public function verifyTwoFactorChallenge(): void
    {
        $this->requirePostWithCsrf('login/2fa');
        $this->applyRateLimit('login_2fa');

        $challenge = $this->getPendingTwoFactorChallenge();
        if ($challenge === null) {
            flash('warning', 'Phiên xác minh 2FA không còn hiệu lực. Vui lòng đăng nhập lại.');
            redirect('login');
        }

        $otpRaw = sanitize_text((string) ($_POST['otp'] ?? ''), 20);
        $otp = otp_digits_only($otpRaw);
        $backupCode = two_factor_normalize_backup_code($otpRaw);
        if (strlen($otp) !== 6 && $backupCode === '') {
            flash('danger', 'Vui lòng nhập mã OTP 6 số hoặc backup code hợp lệ.');
            redirect('login/2fa');
        }

        $userModel = new User($this->config);
        $user = $userModel->find((int) $challenge['user_id']);

        if (!$user || ($user['status'] ?? 'active') !== 'active' || !$this->isTwoFactorEnabled($user)) {
            $this->clearPendingTwoFactorChallenge();
            flash('danger', 'Tài khoản không hợp lệ cho xác minh 2FA. Vui lòng đăng nhập lại.');
            redirect('login');
        }

        $secret = two_factor_secret_for_verification((string) ($user['two_factor_secret'] ?? ''));
        $isOtpValid = strlen($otp) === 6 && $secret !== '' && totp_verify_code($secret, $otp, 0);
        if (!$isOtpValid) {
            $securityModel = new UserSecurity($this->config);
            if ($backupCode === '' || !$securityModel->consumeBackupCode((int) $user['id'], $backupCode)) {
                flash('danger', 'Mã OTP hoặc backup code không chính xác hoặc đã hết hạn.');
                redirect('login/2fa');
            }

            flash('warning', 'Bạn vừa đăng nhập bằng backup code. Hãy tạo lại bộ mã dự phòng nếu cần.');
        }

        $userModel->markTwoFactorUsed((int) $user['id']);
        $freshUser = $userModel->find((int) $user['id']) ?? $user;

        $freshUser = $this->finalizeAuthenticatedSession($freshUser);
        $this->clearPendingTwoFactorChallenge();
        $this->resetRateLimit('login');
        $this->resetRateLimit('login_2fa');
        clear_old();
        flash('success', 'Xác minh 2FA thành công.');

        if (Auth::can('backoffice.dashboard')) {
            redirect('admin');
        }

        redirect('/');
    }

    public function register(): void
    {
        $this->requirePostWithCsrf('register');
        $this->applyRateLimit('register');

        $fullName = sanitize_text((string) ($_POST['full_name'] ?? ''), 120);
        $username = sanitize_text((string) ($_POST['username'] ?? ''), 60);
        $email = sanitize_text((string) ($_POST['email'] ?? ''), 190);
        $gender = normalize_user_gender((string) ($_POST['gender'] ?? 'unknown'));
        $birthDate = normalize_birth_date((string) ($_POST['birth_date'] ?? ''));
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirmation'] ?? '';

        set_old([
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'gender' => $gender,
            'birth_date' => $birthDate ?? '',
        ]);

        if ($fullName === '' || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Vui lòng nhập đầy đủ và đúng định dạng thông tin.');
            redirect('register');
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
            flash('danger', 'Username chỉ gồm chữ, số và ký tự ._- (3-30 ký tự).');
            redirect('register');
        }

        if (trim((string) ($_POST['birth_date'] ?? '')) !== '' && $birthDate === null) {
            flash('danger', 'Ngày sinh không hợp lệ hoặc nằm ngoài khoảng cho phép.');
            redirect('register');
        }

        $passwordError = validate_password_strength($password);
        if ($passwordError !== null || $password !== $passwordConfirm) {
            flash('danger', $passwordError ?? 'Mật khẩu xác nhận không khớp.');
            redirect('register');
        }

        $userModel = new User($this->config);
        if ($userModel->findByEmail($email)) {
            flash('danger', 'Email đã tồn tại.');
            redirect('register');
        }

        $ok = $userModel->create([
            'role_id' => 2,
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'phone' => null,
            'address' => null,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'avatar' => null,
            'status' => 'active',
        ]);

        if ($ok) {
            $this->resetRateLimit('register');
            clear_old();
            flash('success', 'Đăng ký thành công, vui lòng đăng nhập.');
            redirect('login');
        }

        flash('danger', 'Đăng ký thất bại, vui lòng thử lại.');
        redirect('register');
    }

    public function showForgot(): void
    {
        $this->view('auth/forgot', [], 'auth');
    }

    public function forgotPassword(): void
    {
        $this->requirePostWithCsrf('forgot-password');
        $this->applyRateLimit('forgot_password');

        $email = sanitize_text((string) ($_POST['email'] ?? ''), 190);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Email không hợp lệ.');
            redirect('forgot-password');
        }

        flash('info', 'Demo: chức năng quên mật khẩu sẽ được gửi email ở môi trường production.');
        redirect('forgot-password');
    }

    public function logout(): void
    {
        $this->requirePostWithCsrf('/');

        $userId = (int) (Auth::id() ?? 0);
        $sessionToken = (string) (Auth::user()['session_token'] ?? '');
        if ($userId > 0 && $sessionToken !== '') {
            try {
                $securityModel = new UserSecurity($this->config);
                $securityModel->revokeSession($userId, $sessionToken);
            } catch (\Throwable $exception) {
                security_log('Không thể revoke phiên khi logout', ['error' => $exception->getMessage()]);
            }
        }

        Auth::logout();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        flash('success', 'Bạn đã đăng xuất.');

        redirect('/');
    }

    private function applyRateLimit(string $key): void
    {
        $rule = (array) config('security.rate_limits.' . $key, []);
        $maxAttempts = (int) ($rule['max_attempts'] ?? 5);
        $windowSeconds = (int) ($rule['window_seconds'] ?? 300);
        $lockSeconds = (int) ($rule['lock_seconds'] ?? 600);

        $bucket = $key . '|ip:' . client_ip();
        $result = rate_limit_hit($bucket, $maxAttempts, $windowSeconds, $lockSeconds);
        if (!$result['allowed']) {
            $redirectMap = [
                'login' => 'login',
                'login_2fa' => 'login/2fa',
                'register' => 'register',
                'forgot_password' => 'forgot-password',
            ];
            flash('danger', 'Bạn thao tác quá nhanh. Vui lòng thử lại sau ' . (int) $result['retry_after'] . ' giây.');
            redirect($redirectMap[$key] ?? 'login');
        }
    }

    private function resetRateLimit(string $key): void
    {
        rate_limit_reset($key . '|ip:' . client_ip());
    }

    private function isTwoFactorEnabled(array $user): bool
    {
        return !empty($user['two_factor_enabled']) && trim((string) ($user['two_factor_secret'] ?? '')) !== '';
    }

    private function finalizeAuthenticatedSession(array $user): array
    {
        $sessionToken = bin2hex(random_bytes(32));
        $user['session_token'] = $sessionToken;

        Auth::login($user);

        $securityModel = new UserSecurity($this->config);
        $ipAddress = client_ip();
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
        $device = security_detect_device($userAgent);
        $location = security_detect_location($ipAddress);

        $securityModel->createSession((int) $user['id'], $sessionToken, $ipAddress, $userAgent, $device);
        $securityModel->logLoginActivity((int) $user['id'], $ipAddress, $device, $location);

        return $user;
    }

    private function storePendingTwoFactorChallenge(array $user): void
    {
        $_SESSION['_2fa_login'] = [
            'user_id' => (int) $user['id'],
            'email' => (string) ($user['email'] ?? ''),
            'created_at' => time(),
        ];
    }

    private function getPendingTwoFactorChallenge(): ?array
    {
        $challenge = $_SESSION['_2fa_login'] ?? null;
        if (!is_array($challenge)) {
            return null;
        }

        $userId = (int) ($challenge['user_id'] ?? 0);
        $createdAt = (int) ($challenge['created_at'] ?? 0);
        if ($userId <= 0 || $createdAt <= 0 || $createdAt < (time() - 600)) {
            $this->clearPendingTwoFactorChallenge();
            return null;
        }

        return [
            'user_id' => $userId,
            'email' => (string) ($challenge['email'] ?? ''),
            'created_at' => $createdAt,
        ];
    }

    private function clearPendingTwoFactorChallenge(): void
    {
        unset($_SESSION['_2fa_login']);
    }

    private function isGoogleLoginEnabled(array $googleConfig): bool
    {
        return !empty($googleConfig['enabled'])
            && trim((string) ($googleConfig['client_id'] ?? '')) !== ''
            && trim((string) ($googleConfig['client_secret'] ?? '')) !== '';
    }

    private function googleRedirectUri(array $googleConfig): string
    {
        $configuredUri = trim((string) ($googleConfig['redirect_uri'] ?? ''));
        return $configuredUri !== '' ? $configuredUri : base_url('auth/google/callback');
    }

    private function validateGoogleOauthState(string $state): bool
    {
        $stored = $_SESSION['_google_oauth_state'] ?? null;
        unset($_SESSION['_google_oauth_state']);

        if (!is_array($stored)) {
            return false;
        }

        $expected = (string) ($stored['value'] ?? '');
        $expiresAt = (int) ($stored['expires_at'] ?? 0);
        if ($state === '' || $expected === '' || $expiresAt < time()) {
            return false;
        }

        return hash_equals($expected, $state);
    }

    private function googleTokenRequest(string $code, array $googleConfig): array
    {
        $body = http_build_query([
            'code' => $code,
            'client_id' => (string) $googleConfig['client_id'],
            'client_secret' => (string) $googleConfig['client_secret'],
            'redirect_uri' => $this->googleRedirectUri($googleConfig),
            'grant_type' => 'authorization_code',
        ]);

        $response = $this->requestJson('https://oauth2.googleapis.com/token', [
            'method' => 'POST',
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'body' => $body,
        ]);

        return is_array($response) ? $response : [];
    }

    private function googleUserInfoRequest(string $accessToken): array
    {
        $response = $this->requestJson('https://openidconnect.googleapis.com/v1/userinfo', [
            'method' => 'GET',
            'headers' => ['Authorization: Bearer ' . $accessToken],
        ]);

        return is_array($response) ? $response : [];
    }

    private function requestJson(string $url, array $options): ?array
    {
        $method = strtoupper((string) ($options['method'] ?? 'GET'));
        $headers = (array) ($options['headers'] ?? []);
        $body = (string) ($options['body'] ?? '');

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }

            $curlOptions = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 20,
            ];

            if ($method === 'POST') {
                $curlOptions[CURLOPT_POSTFIELDS] = $body;
            }

            curl_setopt_array($ch, $curlOptions);
            $raw = curl_exec($ch);
            curl_close($ch);

            if (!is_string($raw) || $raw === '') {
                return null;
            }

            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $method === 'POST' ? $body : '',
                'timeout' => 20,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function isGoogleEmailVerified($emailVerified): bool
    {
        if (is_bool($emailVerified)) {
            return $emailVerified;
        }

        $normalized = strtolower(trim((string) $emailVerified));
        return in_array($normalized, ['1', 'true', 'yes'], true);
    }

    private function generateUniqueUsername(User $userModel, string $seed): string
    {
        $base = strtolower(trim($seed));
        $base = preg_replace('/\s+/', '.', $base) ?? '';
        $base = preg_replace('/[^a-z0-9_.-]/', '', $base) ?? '';

        if ($base === '') {
            $base = 'user';
        }

        if (strlen($base) < 3) {
            $base = str_pad($base, 3, 'x');
        }

        $base = substr($base, 0, 24);
        $username = $base;

        $attempt = 0;
        while ($userModel->findByUsername($username) !== null) {
            $attempt++;
            $suffix = (string) random_int(1000, 9999);
            $username = substr($base, 0, max(3, 30 - strlen($suffix))) . $suffix;

            if ($attempt > 20) {
                $username = 'user' . time() . random_int(10, 99);
                break;
            }
        }

        return substr($username, 0, 30);
    }
}
