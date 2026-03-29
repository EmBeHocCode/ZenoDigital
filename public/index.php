<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

$config = require BASE_PATH . '/config/config.php';
require BASE_PATH . '/app/Helpers/functions.php';

$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

if (!headers_sent()) {
    session_name('DMPSESSID');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

session_start();

if (($config['app']['https_only'] ?? false) && !$isHttps) {
    $target = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $target, true, 301);
    exit;
}

$debug = (bool) ($config['app']['debug'] ?? false);
if (!$debug) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    set_exception_handler(static function (Throwable $exception): void {
        error_log('[APP_EXCEPTION] ' . $exception->getMessage());
        http_response_code(500);
        echo 'Hệ thống đang bận, vui lòng thử lại sau.';
    });

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        error_log("[APP_ERROR] {$message} in {$file}:{$line}");
        return true;
    });
}

$idleTimeout = (int) ($config['security']['session_idle_timeout'] ?? 1800);
$now = time();
// Hết phiên nếu user không hoạt động quá lâu để giảm rủi ro hijacking.
if (!empty($_SESSION['auth']) && isset($_SESSION['_last_activity']) && ($now - (int) $_SESSION['_last_activity']) > $idleTimeout) {
    $_SESSION = [];
    session_regenerate_id(true);
    session_destroy();
    session_start();
    flash('warning', 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại.');
}
$_SESSION['_last_activity'] = $now;

spl_autoload_register(function ($class) {
    $prefixes = [
        'App\\' => BASE_PATH . '/app/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }
    }
});

security_validate_active_session($config);

// Security middleware toàn cục: header cứng + CSRF cho mọi POST.
apply_security_headers();
verify_csrf_or_abort();

use App\Core\App;

$app = new App($config, require BASE_PATH . '/config/routes.php');
$app->run();
