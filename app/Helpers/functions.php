<?php

use App\Core\Auth;

function config(string $key, $default = null)
{
    global $config;
    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function value_is_truthy($value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function env_has_value(string $key): bool
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value !== false && $value !== null && trim((string) $value) !== '';
}

function app_settings(bool $refresh = false): array
{
    static $cachedSettings = null;

    if ($refresh) {
        $cachedSettings = null;
    }

    if (is_array($cachedSettings)) {
        return $cachedSettings;
    }

    global $config;

    try {
        $model = new \App\Models\Setting($config);
        $cachedSettings = $model->all();
    } catch (\Throwable $exception) {
        error_log('[APP_SETTINGS_ERROR] ' . $exception->getMessage());
        $cachedSettings = [];
    }

    return $cachedSettings;
}

function app_setting(string $key, $default = null)
{
    $settings = app_settings();
    if (!array_key_exists($key, $settings)) {
        return $default;
    }

    return $settings[$key];
}

function google_oauth_config(bool $refresh = false): array
{
    $oauthConfig = (array) config('oauth.google', []);
    $settings = app_settings($refresh);

    $clientId = trim((string) ($settings['google_client_id'] ?? ''));
    if ($clientId === '') {
        $clientId = trim((string) ($oauthConfig['client_id'] ?? ''));
    }

    $clientSecret = trim((string) ($settings['google_client_secret'] ?? ''));
    if ($clientSecret === '') {
        $clientSecret = trim((string) ($oauthConfig['client_secret'] ?? ''));
    }

    $redirectUri = trim((string) ($settings['google_redirect_uri'] ?? ''));
    if ($redirectUri === '') {
        $redirectUri = trim((string) ($oauthConfig['redirect_uri'] ?? ''));
    }

    if (array_key_exists('google_oauth_enabled', $settings)) {
        $enabled = value_is_truthy($settings['google_oauth_enabled']);
    } elseif (env_has_value('GOOGLE_OAUTH_ENABLED')) {
        $enabled = value_is_truthy($_ENV['GOOGLE_OAUTH_ENABLED'] ?? getenv('GOOGLE_OAUTH_ENABLED'));
    } else {
        $enabled = $clientId !== '' && $clientSecret !== '';
    }

    return [
        'enabled' => $enabled,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
    ];
}

function is_google_oauth_configured(bool $refresh = false): bool
{
    $googleConfig = google_oauth_config($refresh);

    return !empty($googleConfig['enabled'])
        && $googleConfig['client_id'] !== ''
        && $googleConfig['client_secret'] !== '';
}

function app_env(): string
{
    return (string) config('app.env', 'local');
}

function role_display_name(?string $roleName): string
{
    $normalized = strtolower(trim((string) $roleName));

    return match ($normalized) {
        'admin', 'administrator' => 'Quản trị viên',
        'staff', 'support', 'support_staff', 'operator', 'assistant' => 'Nhân viên vận hành',
        'manager', 'management', 'owner', 'director', 'team_lead', 'teamlead', 'lead', 'head' => 'Quản lý',
        'seller', 'vendor' => 'Người bán',
        'user', 'customer', '' => 'Khách hàng',
        default => ucwords(str_replace(['_', '-'], ' ', $normalized)),
    };
}

function order_status_options(): array
{
    return [
        'pending' => 'Chờ xử lý',
        'paid' => 'Đã thanh toán',
        'processing' => 'Đang xử lý',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
    ];
}

function order_status_label(?string $status): string
{
    $normalized = strtolower(trim((string) $status));
    $options = order_status_options();

    if ($normalized === '') {
        return 'Không xác định';
    }

    return $options[$normalized] ?? ucwords(str_replace(['_', '-'], ' ', $normalized));
}

function user_gender_options(): array
{
    return [
        'unknown' => 'Chưa xác định',
        'male' => 'Nam',
        'female' => 'Nữ',
        'other' => 'Khác',
    ];
}

function normalize_user_gender(?string $value, string $default = 'unknown'): string
{
    $normalized = strtolower(trim((string) $value));
    $allowed = array_keys(user_gender_options());

    return in_array($normalized, $allowed, true) ? $normalized : $default;
}

function user_gender_label(?string $value): string
{
    $normalized = normalize_user_gender($value);
    $options = user_gender_options();

    return (string) ($options[$normalized] ?? $options['unknown']);
}

function normalize_birth_date(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
    $errors = \DateTimeImmutable::getLastErrors();

    if (!$date || !is_array($errors) || !empty($errors['warning_count']) || !empty($errors['error_count'])) {
        return null;
    }

    $today = new \DateTimeImmutable('today');
    $minDate = $today->modify('-120 years');

    if ($date > $today || $date < $minDate) {
        return null;
    }

    return $date->format('Y-m-d');
}

function calculate_age_from_birth_date(?string $birthDate): ?int
{
    $normalized = normalize_birth_date($birthDate);
    if ($normalized === null) {
        return null;
    }

    $birth = new \DateTimeImmutable($normalized);
    $today = new \DateTimeImmutable('today');
    $age = (int) $birth->diff($today)->y;

    return $age >= 0 && $age <= 120 ? $age : null;
}

function is_production(): bool
{
    return app_env() === 'production';
}

function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $forwardedProto === 'https';
}

function base_url(string $path = ''): string
{
    $url = rtrim(config('app.url', ''), '/');
    return $path ? $url . '/' . ltrim($path, '/') : $url;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    $ttl = (int) config('security.csrf_token_ttl', 7200);
    $now = time();

    if (!isset($_SESSION['_csrf']) || !is_array($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = [];
    }

    foreach ($_SESSION['_csrf'] as $key => $meta) {
        if (!is_array($meta) || ($meta['expires_at'] ?? 0) < $now) {
            unset($_SESSION['_csrf'][$key]);
        }
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['_csrf'][$token] = [
        'expires_at' => $now + $ttl,
    ];

    return $token;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf_or_abort(): void
{
    if (!is_post()) {
        return;
    }

    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
        $postMaxSize = (string) ini_get('post_max_size');
        $uploadMaxFileSize = (string) ini_get('upload_max_filesize');
        flash('danger', 'Dữ liệu gửi lên không hợp lệ hoặc vượt giới hạn máy chủ (post_max_size=' . $postMaxSize . ', upload_max_filesize=' . $uploadMaxFileSize . '). Vui lòng giảm dung lượng file và thử lại.');
        if (!headers_sent()) {
            $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            if ($referrer !== '' && str_starts_with($referrer, base_url())) {
                header('Location: ' . $referrer);
            } else {
                header('Location: ' . base_url('/'));
            }
        }
        exit;
    }

    $token = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $store = $_SESSION['_csrf'] ?? [];

    if ($token === '' || !isset($store[$token])) {
        flash('danger', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
        if (!headers_sent()) {
            $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            if ($referrer !== '' && str_starts_with($referrer, base_url())) {
                header('Location: ' . $referrer);
            } else {
                header('Location: ' . base_url('/'));
            }
        }
        exit;
    }

    $meta = $store[$token];
    unset($_SESSION['_csrf'][$token]);

    if (($meta['expires_at'] ?? 0) < time()) {
        flash('danger', 'CSRF token không còn hiệu lực, vui lòng gửi lại biểu mẫu.');
        if (!headers_sent()) {
            $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            if ($referrer !== '' && str_starts_with($referrer, base_url())) {
                header('Location: ' . $referrer);
            } else {
                header('Location: ' . base_url('/'));
            }
        }
        exit;
    }
}

function old(string $key, string $default = ''): string
{
    return $_SESSION['old'][$key] ?? $default;
}

function set_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function client_ip(): string
{
    $raw = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $parts = explode(',', $raw);
    return trim($parts[0]);
}

function rate_limit_hit(string $key, int $maxAttempts, int $windowSeconds, int $lockSeconds): array
{
    $now = time();
    if (!isset($_SESSION['_rate_limit']) || !is_array($_SESSION['_rate_limit'])) {
        $_SESSION['_rate_limit'] = [];
    }

    $bucket = $_SESSION['_rate_limit'][$key] ?? [
        'count' => 0,
        'window_start' => $now,
        'locked_until' => 0,
    ];

    if (($bucket['locked_until'] ?? 0) > $now) {
        return ['allowed' => false, 'retry_after' => (int) $bucket['locked_until'] - $now];
    }

    if (($bucket['window_start'] ?? 0) + $windowSeconds <= $now) {
        $bucket['count'] = 0;
        $bucket['window_start'] = $now;
        $bucket['locked_until'] = 0;
    }

    $bucket['count']++;

    if ($bucket['count'] > $maxAttempts) {
        $bucket['locked_until'] = $now + $lockSeconds;
        $_SESSION['_rate_limit'][$key] = $bucket;
        return ['allowed' => false, 'retry_after' => $lockSeconds];
    }

    $_SESSION['_rate_limit'][$key] = $bucket;
    return ['allowed' => true, 'retry_after' => 0];
}

function rate_limit_reset(string $key): void
{
    if (isset($_SESSION['_rate_limit'][$key])) {
        unset($_SESSION['_rate_limit'][$key]);
    }
}

function validate_password_strength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Mật khẩu phải có ít nhất 8 ký tự.';
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Mật khẩu cần có chữ hoa, chữ thường và số.';
    }

    return null;
}

function sanitize_text(string $value, int $maxLength = 255): string
{
    $value = trim($value);
    if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function validate_enum(string $value, array $allowed, string $default = ''): string
{
    return in_array($value, $allowed, true) ? $value : $default;
}

function validate_int_range($value, int $min, int $max, int $default): int
{
    if (!is_numeric($value)) {
        return $default;
    }

    $number = (int) $value;
    if ($number < $min || $number > $max) {
        return $default;
    }

    return $number;
}

function validate_float_range($value, float $min, float $max, float $default): float
{
    if (!is_numeric($value)) {
        return $default;
    }

    $number = (float) $value;
    if ($number < $min || $number > $max) {
        return $default;
    }

    return $number;
}

function secure_upload_image(array $file, string $prefix = 'upload_'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $uploadPath = (string) config('upload.path');
    if (!is_dir($uploadPath) && !mkdir($uploadPath, 0755, true) && !is_dir($uploadPath)) {
        return null;
    }

    $maxSize = (int) config('upload.max_size', 2 * 1024 * 1024);
    if (($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxSize) {
        return null;
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allowedExt = (array) config('upload.allowed_image_ext', []);
    if (!in_array($ext, $allowedExt, true)) {
        return null;
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmpPath) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMime = (array) config('upload.allowed_image_mime', []);
    if ($mime === false || !in_array($mime, $allowedMime, true)) {
        return null;
    }

    $fileName = bin2hex(random_bytes(16)) . '_' . $prefix . '.' . $ext;
    $targetPath = rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        return null;
    }

    @chmod($targetPath, 0644);

    return $fileName;
}

function secure_upload_video(array $file, string $prefix = 'video_'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $uploadPath = (string) config('upload.path');
    if (!is_dir($uploadPath) && !mkdir($uploadPath, 0755, true) && !is_dir($uploadPath)) {
        return null;
    }

    $maxSize = (int) config('upload.max_video_size', 20 * 1024 * 1024);
    if (($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxSize) {
        return null;
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allowedExt = (array) config('upload.allowed_video_ext', []);
    if (!in_array($ext, $allowedExt, true)) {
        return null;
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmpPath) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMime = (array) config('upload.allowed_video_mime', []);
    if ($mime === false || !in_array($mime, $allowedMime, true)) {
        return null;
    }

    $fileName = bin2hex(random_bytes(16)) . '_' . $prefix . '.' . $ext;
    $targetPath = rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        return null;
    }

    @chmod($targetPath, 0644);

    return $fileName;
}

function apply_security_headers(): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()');

    $csp = implode('; ', [
        "default-src 'self'",
        "img-src 'self' data: blob: https:",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "font-src 'self' data: https://cdnjs.cloudflare.com",
        "connect-src 'self' blob: https:",
        "media-src 'self' blob: data: https:",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
    ]);
    header('Content-Security-Policy: ' . $csp);

    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    if (Auth::check()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

function security_log(string $message, array $context = []): void
{
    $safeContext = $context;
    unset($safeContext['password'], $safeContext['token'], $safeContext['secret']);
    error_log('[SECURITY] ' . $message . ' ' . json_encode($safeContext, JSON_UNESCAPED_UNICODE));
}

function admin_audit(string $action, string $entity, ?int $entityId = null, array $meta = []): void
{
    try {
        if (!\App\Core\Auth::check() || !\App\Core\Auth::isAdmin()) {
            return;
        }

        $requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $meta = array_merge([
            'request_path' => $requestPath,
            'request_method' => $requestMethod,
        ], $meta);

        global $config;
        $model = new \App\Models\AdminAuditLog($config);
        $model->create((int) (\App\Core\Auth::id() ?? 0), $action, $entity, $entityId, $meta);
    } catch (\Throwable $exception) {
        security_log('Không thể ghi audit log admin', ['error' => $exception->getMessage()]);
    }
}

function format_money(float $amount): string
{
    return number_format($amount, 0, ',', '.') . ' ₫';
}

function product_image_url(array $product): string
{
    $image = trim((string) ($product['image'] ?? ''));

    if ($image !== '') {
        if (preg_match('#^(https?:)?//#', $image)) {
            return $image;
        }

        if (strpos($image, 'uploads/') === 0 || strpos($image, 'assets/') === 0 || strpos($image, 'images/') === 0) {
            return base_url($image);
        }

        return base_url('uploads/' . ltrim($image, '/'));
    }

    $slug = trim((string) ($product['slug'] ?? ''));
    $mappedImages = [
        'steam-wallet-500k' => 'images/product-steam-wallet-500k.svg',
        'the-game-da-nen-tang-200k' => 'images/product-the-game-200k.svg',
    ];

    return base_url($mappedImages[$slug] ?? 'assets/images/vps.svg');
}

function otp_digits_only(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function totp_generate_secret(int $length = 32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bytes = random_bytes(max(16, $length));
    $secret = '';

    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[ord($bytes[$i]) % 32];
    }

    return $secret;
}

function totp_base32_decode(string $secret): string
{
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
    if ($secret === '') {
        return '';
    }

    $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
    $bits = '';

    foreach (str_split($secret) as $char) {
        if (!isset($alphabet[$char])) {
            return '';
        }

        $bits .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT);
    }

    $binary = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $binary .= chr(bindec($chunk));
        }
    }

    return $binary;
}

function totp_generate_code(string $secret, ?int $timestamp = null, int $period = 30, int $digits = 6): string
{
    $secretKey = totp_base32_decode($secret);
    if ($secretKey === '') {
        return str_repeat('0', $digits);
    }

    $counter = (int) floor(($timestamp ?? time()) / $period);
    $binaryCounter = pack('N2', 0, $counter);
    $hash = hash_hmac('sha1', $binaryCounter, $secretKey, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $chunk = substr($hash, $offset, 4);
    $value = unpack('N', $chunk)[1] & 0x7FFFFFFF;
    $mod = 10 ** $digits;

    return str_pad((string) ($value % $mod), $digits, '0', STR_PAD_LEFT);
}

function totp_verify_code(string $secret, string $code, int $window = 1, ?int $timestamp = null, int $period = 30, int $digits = 6): bool
{
    $code = otp_digits_only($code);
    if (strlen($code) !== $digits) {
        return false;
    }

    $timestamp = $timestamp ?? time();

    for ($offset = -$window; $offset <= $window; $offset++) {
        $expected = totp_generate_code($secret, $timestamp + ($offset * $period), $period, $digits);
        if (hash_equals($expected, $code)) {
            return true;
        }
    }

    return false;
}

function totp_provisioning_uri(string $issuer, string $accountName, string $secret, int $digits = 6, int $period = 30): string
{
    $issuer = trim($issuer);
    $accountName = trim($accountName);

    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $accountName)
        . '?secret=' . rawurlencode($secret)
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1'
        . '&digits=' . $digits
        . '&period=' . $period;
}

function two_factor_get_setup_secret(int $userId): ?string
{
    $meta = $_SESSION['_2fa_setup'][$userId] ?? null;
    if (!is_array($meta)) {
        return null;
    }

    $secret = (string) ($meta['secret'] ?? '');
    $createdAt = (int) ($meta['created_at'] ?? 0);
    if ($secret === '' || ($createdAt > 0 && $createdAt < (time() - 1800))) {
        unset($_SESSION['_2fa_setup'][$userId]);
        return null;
    }

    return $secret;
}

function two_factor_get_or_create_setup_secret(int $userId): string
{
    $secret = two_factor_get_setup_secret($userId);
    if ($secret !== null) {
        return $secret;
    }

    $secret = totp_generate_secret(32);
    $_SESSION['_2fa_setup'][$userId] = [
        'secret' => $secret,
        'created_at' => time(),
    ];

    return $secret;
}

function two_factor_clear_setup_secret(int $userId): void
{
    if (isset($_SESSION['_2fa_setup'][$userId])) {
        unset($_SESSION['_2fa_setup'][$userId]);
    }
}

function security_encrypt_value(string $plainText): string
{
    $plainText = trim($plainText);
    if ($plainText === '') {
        return '';
    }

    if (!function_exists('openssl_encrypt')) {
        return $plainText;
    }

    $keySeed = (string) config('app.key', config('app.name', 'zenox-security-key'));
    $key = hash('sha256', $keySeed, true);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    if (!is_string($cipher) || $cipher === '') {
        return '';
    }

    return 'enc:' . base64_encode($iv . $cipher);
}

function security_decrypt_value(string $storedValue): string
{
    $storedValue = trim($storedValue);
    if ($storedValue === '') {
        return '';
    }

    if (!str_starts_with($storedValue, 'enc:')) {
        return $storedValue;
    }

    if (!function_exists('openssl_decrypt')) {
        return '';
    }

    $payload = base64_decode(substr($storedValue, 4), true);
    if (!is_string($payload) || strlen($payload) <= 16) {
        return '';
    }

    $iv = substr($payload, 0, 16);
    $cipher = substr($payload, 16);
    $keySeed = (string) config('app.key', config('app.name', 'zenox-security-key'));
    $key = hash('sha256', $keySeed, true);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    return is_string($plain) ? $plain : '';
}

function two_factor_secret_for_storage(string $plainSecret): string
{
    return security_encrypt_value($plainSecret);
}

function two_factor_secret_for_verification(string $storedSecret): string
{
    return security_decrypt_value($storedSecret);
}

function two_factor_normalize_backup_code(string $code): string
{
    $normalized = strtoupper(trim($code));
    $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized) ?? '';

    if (strlen($normalized) !== 8) {
        return '';
    }

    return substr($normalized, 0, 4) . '-' . substr($normalized, 4, 4);
}

function two_factor_generate_backup_codes(int $count = 10): array
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $codes = [];

    while (count($codes) < $count) {
        $raw = '';
        $bytes = random_bytes(8);
        for ($i = 0; $i < 8; $i++) {
            $raw .= $alphabet[ord($bytes[$i]) % strlen($alphabet)];
        }

        $code = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        $codes[$code] = $code;
    }

    return array_values($codes);
}

function two_factor_store_plain_backup_codes(int $userId, array $codes): void
{
    $_SESSION['_2fa_backup_codes'][$userId] = [
        'codes' => array_values($codes),
        'created_at' => time(),
    ];
}

function two_factor_take_plain_backup_codes(int $userId): array
{
    $meta = $_SESSION['_2fa_backup_codes'][$userId] ?? null;
    unset($_SESSION['_2fa_backup_codes'][$userId]);

    if (!is_array($meta)) {
        return [];
    }

    $codes = $meta['codes'] ?? [];
    $createdAt = (int) ($meta['created_at'] ?? 0);
    if (!is_array($codes) || $createdAt < (time() - 1800)) {
        return [];
    }

    return array_values(array_filter(array_map(static fn($value) => is_string($value) ? trim($value) : '', $codes)));
}

function security_detect_device(?string $userAgent = null): string
{
    $userAgent = strtolower((string) ($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')));

    $browser = 'Unknown';
    if (str_contains($userAgent, 'edg/')) {
        $browser = 'Edge';
    } elseif (str_contains($userAgent, 'chrome/')) {
        $browser = 'Chrome';
    } elseif (str_contains($userAgent, 'firefox/')) {
        $browser = 'Firefox';
    } elseif (str_contains($userAgent, 'safari/') && !str_contains($userAgent, 'chrome/')) {
        $browser = 'Safari';
    }

    $os = 'Unknown OS';
    if (str_contains($userAgent, 'windows')) {
        $os = 'Windows';
    } elseif (str_contains($userAgent, 'android')) {
        $os = 'Android';
    } elseif (str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ipad') || str_contains($userAgent, 'ios')) {
        $os = 'iOS';
    } elseif (str_contains($userAgent, 'mac os')) {
        $os = 'macOS';
    } elseif (str_contains($userAgent, 'linux')) {
        $os = 'Linux';
    }

    return $browser . ' / ' . $os;
}

function security_detect_location(string $ipAddress): string
{
    if ($ipAddress === '127.0.0.1' || $ipAddress === '::1') {
        return 'Localhost';
    }

    return (string) config('app.default_login_location', 'Vietnam');
}

function security_store_reset_email_code(int $userId, string $passwordHash): string
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['_2fa_reset'][$userId] = [
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'password_hash' => $passwordHash,
        'expires_at' => time() + 600,
        'created_at' => time(),
    ];

    return $code;
}

function security_verify_reset_email_code(int $userId, string $password, string $code): bool
{
    $meta = $_SESSION['_2fa_reset'][$userId] ?? null;
    if (!is_array($meta)) {
        return false;
    }

    $expiresAt = (int) ($meta['expires_at'] ?? 0);
    $passwordHash = (string) ($meta['password_hash'] ?? '');
    $codeHash = (string) ($meta['code_hash'] ?? '');

    if ($expiresAt < time() || $passwordHash === '' || $codeHash === '') {
        unset($_SESSION['_2fa_reset'][$userId]);
        return false;
    }

    $normalizedCode = otp_digits_only($code);
    if (strlen($normalizedCode) !== 6) {
        return false;
    }

    if (!password_verify($password, $passwordHash) || !password_verify($normalizedCode, $codeHash)) {
        return false;
    }

    unset($_SESSION['_2fa_reset'][$userId]);
    return true;
}

function security_clear_reset_email_code(int $userId): void
{
    unset($_SESSION['_2fa_reset'][$userId]);
}

function security_validate_active_session(array $config): void
{
    $auth = $_SESSION['auth'] ?? null;
    if (!is_array($auth)) {
        return;
    }

    $userId = (int) ($auth['id'] ?? 0);
    $sessionToken = (string) ($auth['session_token'] ?? '');
    if ($userId <= 0 || $sessionToken === '') {
        return;
    }

    try {
        $securityModel = new \App\Models\UserSecurity($config);
        if (!$securityModel->isSessionActive($userId, $sessionToken)) {
            $_SESSION = [];
            session_regenerate_id(true);
            session_destroy();
            session_start();
            flash('warning', 'Phiên đăng nhập của bạn đã bị thu hồi. Vui lòng đăng nhập lại.');
            redirect('login');
        }

        $securityModel->touchSession($sessionToken);
    } catch (\Throwable $exception) {
        security_log('Không thể kiểm tra phiên đăng nhập', ['error' => $exception->getMessage()]);
    }
}

function paginate_meta(int $total, int $page, int $perPage): array
{
    $lastPage = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $lastPage));

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $page,
        'last_page' => $lastPage,
        'offset' => ($page - 1) * $perPage,
    ];
}
