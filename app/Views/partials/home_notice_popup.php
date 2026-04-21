<?php
$homeNoticeConfig = is_array($homeNoticeConfig ?? null) ? $homeNoticeConfig : [];
$homeNoticeTitle = trim((string) ($homeNoticeConfig['title'] ?? 'Thông báo'));
$homeNoticeSubtitle = trim((string) ($homeNoticeConfig['subtitle'] ?? ''));
$homeNoticeBrand = app_site_name();
$homeNoticeStorageKey = trim((string) ($homeNoticeConfig['storage_key'] ?? 'zenox-home-notice-hidden-until'));
$homeNoticeSnoozeHours = max(1, (int) ($homeNoticeConfig['snooze_hours'] ?? 2));
$homeNoticeSnoozeLabel = trim((string) ($homeNoticeConfig['snooze_label'] ?? 'Không hiển thị lại trong 2 giờ'));
$homeNoticeCards = is_array($homeNoticeConfig['cards'] ?? null) ? $homeNoticeConfig['cards'] : [];
$homeNoticeSummaryPills = [
    count($homeNoticeCards) . ' mục cần xem nhanh',
    'Hỗ trợ thao tác ngay từ trang chủ',
    $homeNoticeSnoozeLabel,
];
?>

<?php if ($homeNoticeCards !== []): ?>
<div
    class="home-notice-popup"
    data-home-popup
    data-storage-key="<?= e($homeNoticeStorageKey) ?>"
    data-snooze-hours="<?= $homeNoticeSnoozeHours ?>"
    aria-hidden="true"
    hidden
>
    <div class="home-notice-popup__overlay" data-home-popup-close></div>

    <section
        class="home-notice-popup__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="homeNoticePopupTitle"
        aria-describedby="homeNoticePopupSubtitle"
        tabindex="-1"
    >
        <button
            type="button"
            class="home-notice-popup__close"
            data-home-popup-close
            aria-label="Đóng thông báo"
        >
            <i class="fas fa-xmark"></i>
        </button>

        <div class="home-notice-popup__hero">
            <div class="home-notice-popup__hero-main">
                <div class="home-notice-popup__hero-topline">
                    <span class="home-notice-popup__eyebrow"><?= e($homeNoticeBrand) ?></span>
                    <div class="home-notice-popup__hero-actions">
                        <span class="home-notice-popup__meta-pill">
                            <i class="fas fa-circle-info"></i>
                            Popup này chỉ hiện ở trang chủ
                        </span>
                        <button
                            type="button"
                            class="home-notice-popup__quick-action"
                            data-home-popup-snooze
                        >
                            <i class="fas fa-clock"></i>
                            <?= e($homeNoticeSnoozeLabel) ?>
                        </button>
                    </div>
                </div>

                <h2 id="homeNoticePopupTitle"><?= e($homeNoticeTitle) ?></h2>
                <?php if ($homeNoticeSubtitle !== ''): ?>
                    <p id="homeNoticePopupSubtitle"><?= e($homeNoticeSubtitle) ?></p>
                <?php endif; ?>

                <div class="home-notice-popup__hero-actions">
                    <span class="home-notice-popup__meta-pill">
                        <i class="fas fa-circle-info"></i>
                        Popup này chỉ hiện ở trang chủ
                    </span>
                    <button
                        type="button"
                        class="home-notice-popup__quick-action"
                        data-home-popup-snooze
                    >
                        <i class="fas fa-clock"></i>
                        <?= e($homeNoticeSnoozeLabel) ?>
                    </button>
                </div>

                <div class="home-notice-popup__summary">
                    <?php foreach ($homeNoticeSummaryPills as $summaryItem): ?>
                        <span class="home-notice-popup__summary-pill"><?= e((string) $summaryItem) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="home-notice-popup__meta-card">
                <span class="home-notice-popup__meta-label">Hỗ trợ nhanh</span>
                <strong>Mở đúng dịch vụ, vào đúng hướng dẫn, liên hệ đúng kênh.</strong>
                <p>Popup này gom sẵn các mục mà người dùng thường cần nhất khi mới vào trang chủ.</p>
            </div>
        </div>

        <div class="home-notice-popup__body">
        <div class="home-notice-popup__grid">
            <?php foreach ($homeNoticeCards as $card): ?>
                <?php
                $cardTone = preg_replace('/[^a-z0-9\-]+/i', '', (string) ($card['tone'] ?? 'default')) ?: 'default';
                $cardIcon = trim((string) ($card['icon'] ?? 'fas fa-bell'));
                $cardTitle = trim((string) ($card['title'] ?? 'Thông tin'));
                $cardBadge = trim((string) ($card['badge'] ?? ''));
                $cardHelper = trim((string) ($card['helper'] ?? ''));
                $cardItems = array_values(array_filter((array) ($card['items'] ?? []), static fn ($item) => trim((string) $item) !== ''));
                $cardOrdered = !empty($card['ordered']);
                $cardCta = is_array($card['cta'] ?? null) ? $card['cta'] : [];
                $cardCtaLabel = trim((string) ($cardCta['label'] ?? ''));
                $cardCtaUrl = trim((string) ($cardCta['url'] ?? ''));
                $cardCtaVariant = trim((string) ($cardCta['variant'] ?? 'secondary'));
                $cardCtaTarget = trim((string) ($cardCta['target'] ?? ''));
                $cardCtaDisabled = $cardCtaLabel !== '' && $cardCtaUrl === '';
                $cardListTag = $cardOrdered ? 'ol' : 'ul';
                ?>
                <article class="home-notice-popup__card home-notice-popup__card--<?= e($cardTone) ?>">
                    <div class="home-notice-popup__card-head">
                        <div class="home-notice-popup__icon" aria-hidden="true">
                            <i class="<?= e($cardIcon) ?>"></i>
                        </div>
                        <div class="home-notice-popup__card-copy">
                            <div class="home-notice-popup__card-title-row">
                                <h3><?= e($cardTitle) ?></h3>
                                <?php if ($cardBadge !== ''): ?>
                                    <span class="home-notice-popup__badge"><?= e($cardBadge) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($cardHelper !== ''): ?>
                                <p><?= e($cardHelper) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <<?= $cardListTag ?> class="home-notice-popup__list<?= $cardOrdered ? ' is-ordered' : '' ?>">
                        <?php foreach ($cardItems as $item): ?>
                            <li><?= e((string) $item) ?></li>
                        <?php endforeach; ?>
                    </<?= $cardListTag ?>>

                    <?php if ($cardCtaLabel !== ''): ?>
                        <div class="home-notice-popup__card-actions">
                            <?php if ($cardCtaDisabled): ?>
                                <span class="home-notice-popup__cta home-notice-popup__cta--disabled">
                                    <i class="fas fa-link-slash"></i>
                                    <?= e($cardCtaLabel) ?>
                                </span>
                            <?php else: ?>
                                <a
                                    class="home-notice-popup__cta home-notice-popup__cta--<?= e($cardCtaVariant) ?>"
                                    href="<?= e($cardCtaUrl) ?>"
                                    <?php if ($cardCtaTarget !== ''): ?>
                                        target="<?= e($cardCtaTarget) ?>"
                                        rel="noopener noreferrer"
                                    <?php endif; ?>
                                >
                                    <i class="fas fa-arrow-up-right-from-square"></i>
                                    <?= e($cardCtaLabel) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        </div>

        <div class="home-notice-popup__footer">
            <div class="home-notice-popup__footer-note">
                <i class="fas fa-clock"></i>
                <span>Bạn có thể tạm ẩn popup nếu đã xem xong. Reload trang chủ sẽ hiện lại sau khi hết thời gian tạm ẩn.</span>
            </div>

            <div class="home-notice-popup__footer-actions">
                <button type="button" class="home-notice-popup__button home-notice-popup__button--ghost" data-home-popup-close>
                    Đóng
                </button>
                <button type="button" class="home-notice-popup__button home-notice-popup__button--primary" data-home-popup-snooze>
                    <?= e($homeNoticeSnoozeLabel) ?>
                </button>
            </div>
        </div>
    </section>
</div>
<?php endif; ?>
