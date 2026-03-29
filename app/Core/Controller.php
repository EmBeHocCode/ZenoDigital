<?php

namespace App\Core;

class Controller
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        View::render($view, $data, $layout, $this->config);
    }

    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            flash('danger', 'Vui lòng đăng nhập để tiếp tục.');
            redirect('login');
        }
    }

    protected function requireAdmin(): void
    {
        if (!Auth::check() || !Auth::isAdmin()) {
            flash('danger', 'Bạn không có quyền truy cập khu vực quản trị.');
            redirect('login');
        }
    }

    protected function requirePermission(string $permission, string $deniedMessage = 'Bạn không có quyền truy cập khu vực này.'): void
    {
        if (!Auth::check() || !Auth::can($permission)) {
            flash('danger', $deniedMessage);
            redirect('login');
        }
    }

    protected function requirePostWithCsrf(string $redirectPath): void
    {
        if (!is_post()) {
            redirect($redirectPath);
        }
    }

    protected function throttle(string $bucketKey, string $ruleKey): void
    {
        $result = $this->consumeRateLimit($bucketKey, $ruleKey);

        if (!$result['allowed']) {
            flash('danger', 'Bạn thao tác quá nhanh. Vui lòng thử lại sau ' . (int) $result['retry_after'] . ' giây.');
            redirect('login');
        }
    }

    protected function consumeRateLimit(string $bucketKey, string $ruleKey): array
    {
        $rule = (array) config('security.rate_limits.' . $ruleKey, []);
        $maxAttempts = (int) ($rule['max_attempts'] ?? 5);
        $windowSeconds = (int) ($rule['window_seconds'] ?? 300);
        $lockSeconds = (int) ($rule['lock_seconds'] ?? 600);

        $identity = client_ip() . '|' . (session_id() ?: 'no-session');

        return rate_limit_hit($bucketKey . '|' . $identity, $maxAttempts, $windowSeconds, $lockSeconds);
    }
}
