<?php
$siteName = (string) app_setting('site_name', config('app.name', 'Digital Market Pro'));
$footerText = (string) app_setting('footer_text', 'Nền tảng bán dịch vụ/sản phẩm số chuyên nghiệp: VPS, cloud, game server, wallet, thẻ nạp và nhiều hơn nữa.');
$contactEmail = (string) app_setting('contact_email', 'support@digitalmarket.local');
$contactPhone = (string) app_setting('contact_phone', '0900 000 999');
$contactAddress = (string) app_setting('address', 'TP.HCM, Việt Nam');
?>
<footer class="site-footer mt-5 py-5 bg-dark text-light">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <h5 class="fw-bold"><?= e($siteName) ?></h5>
                <p class="text-light-emphasis"><?= e($footerText) ?></p>
            </div>
            <div class="col-md-4">
                <h6 class="fw-semibold">Liên hệ</h6>
                <ul class="list-unstyled mb-0">
                    <li>Email: <?= e($contactEmail) ?></li>
                    <li>Hotline: <?= e($contactPhone) ?></li>
                    <li>Địa chỉ: <?= e($contactAddress) ?></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="fw-semibold">Liên kết nhanh</h6>
                <ul class="list-unstyled mb-0">
                    <li><a class="text-decoration-none text-light-emphasis" href="<?= base_url('/') ?>">Trang chủ</a></li>
                    <li><a class="text-decoration-none text-light-emphasis" href="<?= base_url('products') ?>">Sản phẩm</a></li>
                    <li><a class="text-decoration-none text-light-emphasis" href="<?= base_url('profile') ?>">Tài khoản</a></li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary my-4">
        <p class="mb-0 text-center text-light-emphasis">© <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
    </div>
</footer>
