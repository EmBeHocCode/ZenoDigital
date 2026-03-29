<?php $showGoogleLogin = is_google_oauth_configured(); ?>
<div class="auth-shell mx-auto">
    <div class="auth-panel auth-panel--left">
        <span class="auth-badge">SaaS Platform</span>
        <h1 class="auth-title">Nền tảng dịch vụ số</h1>
        <p class="auth-text">Đăng nhập để quản lý tài khoản, đơn hàng và toàn bộ dịch vụ của bạn trên cùng một hệ thống.</p>

        <ul class="auth-list">
            <li>Mua VPS nhanh chóng</li>
            <li>Quản lý dịch vụ tập trung</li>
            <li>Hỗ trợ kỹ thuật 24/7</li>
        </ul>

        <div class="auth-left-stats">
            <div class="auth-mini-stat">
                <strong>500+</strong>
                <span>Dịch vụ đang hoạt động</span>
            </div>
            <div class="auth-mini-stat">
                <strong>99.9%</strong>
                <span>Uptime ổn định</span>
            </div>
        </div>
    </div>

    <div class="auth-panel auth-panel--right">
        <h2 class="auth-form-title">Đăng nhập</h2>
        <p class="auth-form-subtitle">Nhập thông tin để truy cập tài khoản của bạn.</p>

        <form method="post" action="<?= base_url('login') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <div class="auth-input-wrap">
                    <i class="fa-solid fa-envelope auth-input-icon" aria-hidden="true"></i>
                    <input type="email" name="email" class="form-control auth-input" required value="<?= e(old('email')) ?>" autocomplete="email">
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label">Mật khẩu</label>
                <div class="auth-input-wrap">
                    <i class="fa-solid fa-lock auth-input-icon" aria-hidden="true"></i>
                    <input id="login-password" type="password" name="password" class="form-control auth-input" required minlength="6" autocomplete="current-password">
                    <button type="button" class="auth-toggle-password" data-target="login-password" aria-label="Hiện hoặc ẩn mật khẩu">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 auth-helper-row">
                <label class="form-check auth-remember mb-0">
                    <input class="form-check-input" type="checkbox" name="remember" value="1">
                    <span class="form-check-label">Ghi nhớ đăng nhập</span>
                </label>
                <a class="auth-link" href="<?= base_url('forgot-password') ?>">Quên mật khẩu?</a>
            </div>

            <button class="btn btn-primary w-100 auth-btn-main">Đăng nhập</button>
        </form>

        <?php if ($showGoogleLogin): ?>
            <div class="auth-divider"><span>Hoặc</span></div>

            <a href="<?= base_url('auth/google') ?>" class="btn btn-outline-secondary w-100 auth-google-btn" role="button">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="#EA4335" d="M12 10.2v3.9h5.5c-.2 1.3-1.5 3.9-5.5 3.9-3.3 0-6-2.8-6-6.1s2.7-6.1 6-6.1c1.9 0 3.1.8 3.8 1.5l2.6-2.5C16.9 3.4 14.7 2.5 12 2.5A9.5 9.5 0 0 0 2.5 12c0 5.2 4.2 9.5 9.5 9.5 5.5 0 9.2-3.8 9.2-9.1 0-.6-.1-1.1-.2-1.6H12z"/>
                    <path fill="#34A853" d="M2.5 7.1l3.2 2.3C6.5 7.6 9 5.9 12 5.9c1.9 0 3.1.8 3.8 1.5l2.6-2.5C16.9 3.4 14.7 2.5 12 2.5c-3.6 0-6.7 2-8.4 4.9z"/>
                    <path fill="#FBBC05" d="M12 21.5c2.6 0 4.8-.8 6.4-2.3l-3-2.4c-.8.6-1.9 1-3.4 1-4 0-5.3-2.6-5.5-3.9l-3.1 2.4c1.7 3 4.8 5.2 8.6 5.2z"/>
                    <path fill="#4285F4" d="M21.2 12.4c0-.6-.1-1.1-.2-1.6H12v3.9h5.5c-.2 1.1-1 2-2.1 2.6l3 2.4c1.8-1.7 2.8-4.2 2.8-7.3z"/>
                </svg>
                <span>Đăng nhập với Google</span>
            </a>
        <?php endif; ?>

        <p class="auth-security-note"><i class="fa-solid fa-shield-halved"></i> Secure authentication protected by SSL encryption</p>
        <p class="auth-footnote mb-0">Chưa có tài khoản? <a class="auth-link" href="<?= base_url('register') ?>">Đăng ký ngay</a></p>
    </div>
</div>
