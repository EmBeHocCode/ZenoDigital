<?php

$envPath = BASE_PATH . '/.env';
if (is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

$env = static function (string $key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    $normalized = strtolower((string) $value);
    if ($normalized === 'true') {
        return true;
    }

    if ($normalized === 'false') {
        return false;
    }

    return $value;
};

return [
    'app' => [
        'name' => (string) $env('APP_NAME', 'Digital Market Pro'),
        'key' => (string) $env('APP_KEY', 'change-this-app-key'),
        'env' => (string) $env('APP_ENV', 'local'),
        'debug' => (bool) $env('APP_DEBUG', true),
        'url' => (string) $env('APP_URL', 'http://localhost/ZenoxDigital/public'),
        'timezone' => (string) $env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),
        'default_login_location' => (string) $env('APP_DEFAULT_LOGIN_LOCATION', 'Vietnam'),
        'currency' => 'VND',
        'https_only' => (bool) $env('APP_HTTPS_ONLY', false),
    ],
    'db' => [
        'host' => (string) $env('DB_HOST', '127.0.0.1'),
        'port' => (string) $env('DB_PORT', '3306'),
        'name' => (string) $env('DB_NAME', 'digital_market'),
        'user' => (string) $env('DB_USER', 'root'),
        'pass' => (string) $env('DB_PASS', ''),


        // 'host' => (string) $env('DB_HOST', '127.0.0.1'),
        // 'port' => (string) $env('DB_PORT', '3306'),
        // 'name' => (string) $env('DB_NAME', 'digital_market'),
        // 'user' => (string) $env('DB_USER', 'root'),
        // 'pass' => (string) $env('DB_PASS', ''),

        'charset' => (string) $env('DB_CHARSET', 'utf8mb4'),
    ],
    'upload' => [
        'path' => (string) $env('UPLOAD_PATH', BASE_PATH . '/public/uploads'),
        'url' => (string) $env('UPLOAD_URL', '/uploads'),
        'max_size' => (int) $env('UPLOAD_MAX_SIZE', 2 * 1024 * 1024),
        'max_video_size' => (int) $env('UPLOAD_MAX_VIDEO_SIZE', 500 * 1024 * 1024),
        'allowed_image_ext' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'ico'],
        'allowed_image_mime' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'],
        'allowed_video_ext' => ['mp4', 'webm', 'mov'],
        'allowed_video_mime' => ['video/mp4', 'video/webm', 'video/quicktime'],
    ],
    'security' => [
        'session_idle_timeout' => (int) $env('SESSION_IDLE_TIMEOUT', 1800),
        'csrf_token_ttl' => (int) $env('CSRF_TOKEN_TTL', 7200),
        'rate_limits' => [
            'login' => [
                'max_attempts' => (int) $env('RL_LOGIN_MAX_ATTEMPTS', 5),
                'window_seconds' => (int) $env('RL_LOGIN_WINDOW', 300),
                'lock_seconds' => (int) $env('RL_LOGIN_LOCK', 600),
            ],
            'login_2fa' => [
                'max_attempts' => (int) $env('RL_LOGIN_2FA_MAX_ATTEMPTS', 5),
                'window_seconds' => (int) $env('RL_LOGIN_2FA_WINDOW', 300),
                'lock_seconds' => (int) $env('RL_LOGIN_2FA_LOCK', 600),
            ],
            'register' => [
                'max_attempts' => (int) $env('RL_REGISTER_MAX_ATTEMPTS', 5),
                'window_seconds' => (int) $env('RL_REGISTER_WINDOW', 600),
                'lock_seconds' => (int) $env('RL_REGISTER_LOCK', 900),
            ],
            'forgot_password' => [
                'max_attempts' => (int) $env('RL_FORGOT_MAX_ATTEMPTS', 3),
                'window_seconds' => (int) $env('RL_FORGOT_WINDOW', 600),
                'lock_seconds' => (int) $env('RL_FORGOT_LOCK', 900),
            ],
            'profile_otp' => [
                'max_attempts' => (int) $env('RL_PROFILE_OTP_MAX_ATTEMPTS', 6),
                'window_seconds' => (int) $env('RL_PROFILE_OTP_WINDOW', 300),
                'lock_seconds' => (int) $env('RL_PROFILE_OTP_LOCK', 600),
            ],
            'profile_sensitive' => [
                'max_attempts' => (int) $env('RL_PROFILE_SENSITIVE_MAX_ATTEMPTS', 6),
                'window_seconds' => (int) $env('RL_PROFILE_SENSITIVE_WINDOW', 600),
                'lock_seconds' => (int) $env('RL_PROFILE_SENSITIVE_LOCK', 900),
            ],
            'ai_customer_chat' => [
                'max_attempts' => (int) $env('RL_AI_CUSTOMER_MAX_ATTEMPTS', 12),
                'window_seconds' => (int) $env('RL_AI_CUSTOMER_WINDOW', 180),
                'lock_seconds' => (int) $env('RL_AI_CUSTOMER_LOCK', 180),
            ],
            'ai_admin_chat' => [
                'max_attempts' => (int) $env('RL_AI_ADMIN_MAX_ATTEMPTS', 30),
                'window_seconds' => (int) $env('RL_AI_ADMIN_WINDOW', 180),
                'lock_seconds' => (int) $env('RL_AI_ADMIN_LOCK', 120),
            ],
            'public_feedback' => [
                'max_attempts' => (int) $env('RL_PUBLIC_FEEDBACK_MAX_ATTEMPTS', 6),
                'window_seconds' => (int) $env('RL_PUBLIC_FEEDBACK_WINDOW', 600),
                'lock_seconds' => (int) $env('RL_PUBLIC_FEEDBACK_LOCK', 600),
            ],
        ],
    ],
    'oauth' => [
        'google' => [
            'enabled' => (bool) $env('GOOGLE_OAUTH_ENABLED', false),
            'client_id' => (string) $env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => (string) $env('GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri' => (string) $env('GOOGLE_REDIRECT_URI', ''),
        ],
    ],
    'ai' => [
        'enabled' => (bool) $env('AI_ENABLED', true),
        'provider' => (string) $env('AI_PROVIDER', 'bridge'),
        'bridge_url' => (string) $env('AI_BRIDGE_URL', ''),
        'bridge_key' => (string) $env('AI_BRIDGE_KEY', ''),
        'chat_timeout' => (int) $env('AI_CHAT_TIMEOUT', 20),
        'retry_times' => (int) $env('AI_BRIDGE_RETRIES', 1),
        'allow_local_fallback' => (bool) $env('AI_BRIDGE_ALLOW_LOCAL_FALLBACK', true),
        'customer_session_prefix' => (string) $env('AI_CUSTOMER_SESSION_PREFIX', 'customer-web'),
        'admin_session_prefix' => (string) $env('AI_ADMIN_SESSION_PREFIX', 'admin-dashboard'),
        'admin_session_ttl_seconds' => (int) $env('AI_ADMIN_SESSION_TTL', 43200),
        'admin_session_restore_limit' => (int) $env('AI_ADMIN_SESSION_RESTORE_LIMIT', 30),
        'capabilities' => require BASE_PATH . '/config/ai_capabilities.php',
    ],
];
