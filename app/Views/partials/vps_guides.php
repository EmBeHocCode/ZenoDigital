<?php
$vpsGuideSectionId = trim((string) ($vpsGuideSectionId ?? 'vps-guides'));
$vpsGuideVariant = trim((string) ($vpsGuideVariant ?? 'default'));
$vpsGuideEyebrow = trim((string) ($vpsGuideEyebrow ?? 'Hướng dẫn sử dụng VPS'));
$vpsGuideTitle = trim((string) ($vpsGuideTitle ?? 'Khởi động Cloud VPS đúng cách từ ngày đầu'));
$vpsGuideSubtitle = trim((string) ($vpsGuideSubtitle ?? 'Các nội dung ngắn gọn dưới đây giúp khách hàng hiểu nhanh cách dùng VPS cho website, automation, game server và vận hành production an toàn hơn.'));
$vpsGuideCtaLabel = trim((string) ($vpsGuideCtaLabel ?? 'Xem catalog cloud'));
$vpsGuideCtaUrl = trim((string) ($vpsGuideCtaUrl ?? base_url('products')));
$vpsGuideCards = [
    [
        'tag' => 'Onboarding',
        'icon' => 'fas fa-rocket',
        'title' => 'Bắt đầu với VPS',
        'items' => [
            'Chuẩn bị OS phù hợp và đổi mật khẩu quản trị ngay sau khi nhận máy.',
            'Kiểm tra IP, SSH/RDP và snapshot ban đầu trước khi triển khai production.',
            'Bắt đầu từ cấu hình vừa đủ, sau đó nâng cấp tài nguyên theo tải thực tế.',
        ],
    ],
    [
        'tag' => 'Website',
        'icon' => 'fas fa-globe',
        'title' => 'Cài đặt Website trên VPS',
        'items' => [
            'Phù hợp cho WordPress, Laravel, Node.js, API nội bộ hoặc landing page hiệu năng cao.',
            'Ưu tiên stack chuẩn như Nginx, PHP-FPM, MariaDB và SSL tự động.',
            'Nên tách môi trường test và production nếu website đã có traffic thật.',
        ],
    ],
    [
        'tag' => 'Game',
        'icon' => 'fas fa-gamepad',
        'title' => 'VPS cho Game Server',
        'items' => [
            'Chọn gói có CPU ổn định và RAM đủ cho plugin, mod hoặc player đồng thời.',
            'Theo dõi tài nguyên và backup định kỳ trước khi cập nhật server game.',
            'Ưu tiên cấu hình mạng ổn định nếu cần ping thấp hoặc chống giật lag.',
        ],
    ],
    [
        'tag' => 'Automation',
        'icon' => 'fas fa-robot',
        'title' => 'VPS cho Auto / Bot',
        'items' => [
            'Tách bot, cronjob và worker khỏi máy cá nhân để chạy liên tục 24/7.',
            'Giới hạn tiến trình và log rõ ràng để tránh treo toàn bộ hệ thống.',
            'Nếu automation quan trọng, nên bật snapshot và cảnh báo tài nguyên.',
        ],
    ],
    [
        'tag' => 'Security',
        'icon' => 'fas fa-shield-halved',
        'title' => 'Bảo mật VPS',
        'items' => [
            'Đổi port quản trị nếu cần, dùng key SSH và giới hạn IP đăng nhập quản trị.',
            'Bật tường lửa, cập nhật bản vá và vô hiệu các dịch vụ không sử dụng.',
            'Không lưu khóa bí mật hoặc tài khoản quan trọng trong file cấu hình công khai.',
        ],
    ],
    [
        'tag' => 'Ops',
        'icon' => 'fas fa-chart-line',
        'title' => 'Quản lý & Giám sát',
        'items' => [
            'Theo dõi CPU, RAM, disk và băng thông để biết thời điểm cần scale up.',
            'Thiết lập backup, cảnh báo dịch vụ và kiểm tra log định kỳ.',
            'Khi VPS lỗi truy cập, thử reboot từ lịch sử mua trước khi gửi ticket hỗ trợ.',
        ],
    ],
];
?>

<section id="<?= e($vpsGuideSectionId) ?>" class="vps-guide-section vps-guide-section--<?= e($vpsGuideVariant) ?>">
    <div class="container">
        <div class="vps-guide-head">
            <div>
                <span class="vps-guide-eyebrow"><?= e($vpsGuideEyebrow) ?></span>
                <h2><?= e($vpsGuideTitle) ?></h2>
                <p><?= e($vpsGuideSubtitle) ?></p>
            </div>
            <?php if ($vpsGuideCtaUrl !== ''): ?>
                <a class="vps-guide-head-cta" href="<?= e($vpsGuideCtaUrl) ?>">
                    <i class="fas fa-arrow-right"></i>
                    <?= e($vpsGuideCtaLabel) ?>
                </a>
            <?php endif; ?>
        </div>

        <div class="vps-guide-grid">
            <?php foreach ($vpsGuideCards as $card): ?>
                <article class="vps-guide-card">
                    <div class="vps-guide-card-head">
                        <span class="vps-guide-icon"><i class="<?= e((string) $card['icon']) ?>"></i></span>
                        <span class="vps-guide-tag"><?= e((string) $card['tag']) ?></span>
                    </div>
                    <h3><?= e((string) $card['title']) ?></h3>
                    <ul class="vps-guide-list">
                        <?php foreach ((array) ($card['items'] ?? []) as $item): ?>
                            <li><?= e((string) $item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
