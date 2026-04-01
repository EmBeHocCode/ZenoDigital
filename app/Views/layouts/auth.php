<?php
$siteName = app_site_name();
$siteFavicon = trim((string) app_setting('site_favicon', ''));
$siteFaviconUrl = $siteFavicon !== '' ? base_url('uploads/' . ltrim($siteFavicon, '/')) : base_url('images/logo/zenox.png');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? ('Xác thực tài khoản - ' . $siteName)) ?></title>
    <link rel="icon" href="<?= e($siteFaviconUrl) ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body class="auth-page">
<div class="auth-wrapper d-flex align-items-center justify-content-center py-5">
    <span class="auth-bg-shape auth-bg-shape--one" aria-hidden="true"></span>
    <span class="auth-bg-shape auth-bg-shape--two" aria-hidden="true"></span>
    <span class="auth-bg-shape auth-bg-shape--three" aria-hidden="true"></span>
    <div class="container">
        <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
        <?php require $content; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<script>
    document.querySelectorAll('.auth-toggle-password').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-target');
            var input = targetId ? document.getElementById(targetId) : null;
            if (!input) {
                return;
            }

            var icon = button.querySelector('i');
            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';

            if (icon) {
                icon.classList.toggle('fa-eye', !isPassword);
                icon.classList.toggle('fa-eye-slash', isPassword);
            }
        });
    });

    var registerPasswordInput = document.querySelector('[data-strength-source="register-password"]');
    var strengthBar = document.querySelector('[data-strength-target="register-password"] .auth-strength-bar');
    var strengthLabel = document.querySelector('[data-strength-label="register-password"]');

    if (registerPasswordInput && strengthBar && strengthLabel) {
        var updateStrength = function () {
            var value = registerPasswordInput.value || '';
            var score = 0;

            if (value.length >= 8) score += 1;
            if (/[A-Z]/.test(value)) score += 1;
            if (/[a-z]/.test(value)) score += 1;
            if (/\d/.test(value)) score += 1;
            if (/[^A-Za-z0-9]/.test(value)) score += 1;

            var width = Math.min(score * 20, 100);
            strengthBar.style.width = width + '%';

            var level = 'Yếu';
            var levelClass = 'is-weak';
            if (score >= 4) {
                level = 'Mạnh';
                levelClass = 'is-strong';
            } else if (score >= 3) {
                level = 'Trung bình';
                levelClass = 'is-medium';
            }

            strengthBar.classList.remove('is-weak', 'is-medium', 'is-strong');
            strengthBar.classList.add(levelClass);
            strengthLabel.textContent = 'Độ mạnh mật khẩu: ' + level;
        };

        registerPasswordInput.addEventListener('input', updateStrength);
        updateStrength();
    }
</script>
</body>
</html>
