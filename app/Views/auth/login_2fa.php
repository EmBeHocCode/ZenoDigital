<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h4 fw-bold mb-2">Xác minh 2FA</h1>
                <p class="text-secondary small mb-4">Nhập mã OTP 6 số hoặc backup code để hoàn tất đăng nhập cho tài khoản <strong><?= e($challengeEmail ?? '') ?></strong>.</p>

                <form method="post" action="<?= base_url('login/2fa') ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Mã OTP / Backup code</label>
                        <input type="text" name="otp" class="form-control form-control-lg text-center" maxlength="9" placeholder="123456 hoặc A8F4-2D9K" required>
                    </div>
                    <button class="btn btn-primary w-100">Xác minh và đăng nhập</button>
                </form>

                <div class="d-flex justify-content-between mt-3 small">
                    <a href="<?= base_url('login?cancel_2fa=1') ?>">Quay lại đăng nhập</a>
                    <span class="text-secondary">Google Authenticator / Authy</span>
                </div>
            </div>
        </div>
    </div>
</div>
