<div class="auth-shell mx-auto">
    <div class="auth-panel auth-panel--left">
        <span class="auth-badge">Account Recovery</span>
        <h1 class="auth-title">Nền tảng dịch vụ số</h1>
        <p class="auth-text">Khôi phục quyền truy cập tài khoản bằng email đã đăng ký trên hệ thống của bạn.</p>

        <ul class="auth-list">
            <li>Quy trình lấy lại mật khẩu an toàn</li>
            <li>Thao tác nhanh trong vài bước</li>
            <li>Hỗ trợ kỹ thuật 24/7</li>
        </ul>
    </div>

    <div class="auth-panel auth-panel--right">
        <h2 class="auth-form-title">Quên mật khẩu</h2>
        <p class="auth-form-subtitle">Nhập email để nhận liên kết đặt lại mật khẩu.</p>

        <form method="post" action="<?= base_url('forgot-password') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <div class="auth-input-wrap">
                    <i class="fa-solid fa-envelope auth-input-icon" aria-hidden="true"></i>
                    <input type="email" name="email" class="form-control auth-input" required autocomplete="email">
                </div>
            </div>

            <button class="btn btn-primary w-100 auth-btn-main">Gửi yêu cầu</button>
        </form>

        <p class="auth-security-note"><i class="fa-solid fa-shield-halved"></i> Secure authentication protected by SSL encryption</p>
        <p class="auth-footnote mb-0">Quay lại đăng nhập? <a class="auth-link" href="<?= base_url('login') ?>">Đăng nhập ngay</a></p>
    </div>
</div>
