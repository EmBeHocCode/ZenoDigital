<?php $showGoogleLogin = is_google_oauth_configured(); ?>
<div class="auth-shell mx-auto">
    <div class="auth-panel auth-panel--left">
        <span class="auth-badge">Create Account</span>
        <h1 class="auth-title">Nền tảng dịch vụ số</h1>
        <p class="auth-text">Đăng ký tài khoản mới để theo dõi dịch vụ, thanh toán và nhận hỗ trợ kỹ thuật xuyên suốt quá trình sử dụng.</p>

        <ul class="auth-list">
            <li>Mua VPS nhanh chóng</li>
            <li>Quản lý dịch vụ tập trung</li>
            <li>Hỗ trợ kỹ thuật 24/7</li>
        </ul>

        <div class="auth-left-stats">
            <div class="auth-mini-stat">
                <strong>24/7</strong>
                <span>Hỗ trợ khách hàng</span>
            </div>
            <div class="auth-mini-stat">
                <strong>10K+</strong>
                <span>Người dùng tin tưởng</span>
            </div>
        </div>
    </div>

    <div class="auth-panel auth-panel--right">
        <h2 class="auth-form-title">Đăng ký</h2>
        <p class="auth-form-subtitle">Điền thông tin bên dưới để tạo tài khoản mới.</p>

        <form method="post" action="<?= base_url('register') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Họ tên</label>
                <div class="auth-input-wrap">
                    <i class="fa-solid fa-user auth-input-icon" aria-hidden="true"></i>
                    <input class="form-control auth-input" name="full_name" required value="<?= e(old('full_name')) ?>" autocomplete="name">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="auth-input-wrap">
                    <i class="fa-solid fa-user-tag auth-input-icon" aria-hidden="true"></i>
                    <input class="form-control auth-input" name="username" required value="<?= e(old('username')) ?>" autocomplete="username">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <div class="auth-input-wrap">
                    <i class="fa-solid fa-envelope auth-input-icon" aria-hidden="true"></i>
                    <input type="email" class="form-control auth-input" name="email" required value="<?= e(old('email')) ?>" autocomplete="email">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Giới tính</label>
                    <div class="auth-input-wrap">
                        <i class="fa-solid fa-venus-mars auth-input-icon" aria-hidden="true"></i>
                        <select class="form-select auth-input" name="gender">
                            <?php foreach (user_gender_options() as $genderKey => $genderLabel): ?>
                                <option value="<?= e($genderKey) ?>" <?= old('gender', 'unknown') === $genderKey ? 'selected' : '' ?>><?= e($genderLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-text">Có thể để mặc định nếu bạn chưa muốn cập nhật ngay.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày sinh</label>
                    <div class="auth-input-wrap">
                        <i class="fa-solid fa-cake-candles auth-input-icon" aria-hidden="true"></i>
                        <input type="date" class="form-control auth-input" name="birth_date" value="<?= e(old('birth_date')) ?>" max="<?= e(date('Y-m-d')) ?>" autocomplete="bday">
                    </div>
                    <div class="form-text">Dùng để cá nhân hóa cách xưng hô trong hỗ trợ AI, không bắt buộc.</div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Mật khẩu</label>
                    <div class="auth-input-wrap">
                        <i class="fa-solid fa-lock auth-input-icon" aria-hidden="true"></i>
                        <input id="register-password" data-strength-source="register-password" type="password" class="form-control auth-input" name="password" minlength="8" required autocomplete="new-password">
                        <button type="button" class="auth-toggle-password" data-target="register-password" aria-label="Hiện hoặc ẩn mật khẩu">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nhập lại mật khẩu</label>
                    <div class="auth-input-wrap">
                        <i class="fa-solid fa-lock auth-input-icon" aria-hidden="true"></i>
                        <input id="register-password-confirm" type="password" class="form-control auth-input" name="password_confirmation" minlength="8" required autocomplete="new-password">
                        <button type="button" class="auth-toggle-password" data-target="register-password-confirm" aria-label="Hiện hoặc ẩn mật khẩu xác nhận">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="auth-strength" data-strength-target="register-password">
                <span class="auth-strength-bar is-weak"></span>
            </div>
            <p class="auth-strength-text" data-strength-label="register-password">Độ mạnh mật khẩu: Yếu</p>

            <button class="btn btn-primary w-100 auth-btn-main">Tạo tài khoản</button>
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
                <span>Tiếp tục với Google</span>
            </a>
        <?php endif; ?>

        <p class="auth-security-note"><i class="fa-solid fa-shield-halved"></i> Secure authentication protected by SSL encryption</p>
        <p class="auth-footnote mb-0">Đã có tài khoản? <a class="auth-link" href="<?= base_url('login') ?>">Đăng nhập ngay</a></p>
    </div>
</div>
