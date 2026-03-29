<?php

use App\Services\AiActorResolver;
use App\Services\AiPersonaService;

global $config;

$aiWidgetSourceProduct = isset($aiWidgetProduct) && is_array($aiWidgetProduct) ? $aiWidgetProduct : null;
$aiWidgetProductId = isset($aiWidgetSourceProduct['id']) ? (int) $aiWidgetSourceProduct['id'] : 0;
$aiWidgetPageType = $aiWidgetProductId > 0 ? 'product' : 'storefront';
$aiWidgetActorResolver = new AiActorResolver(is_array($config ?? null) ? $config : []);
$aiWidgetActor = $aiWidgetActorResolver->resolveFromSession();
$aiWidgetActorType = (string) ($aiWidgetActor['actor_type'] ?? 'guest');
$aiWidgetConversationMode = (string) ($aiWidgetActor['conversation_mode'] ?? 'customer_support');
$aiWidgetRoleGroup = (string) ($aiWidgetActor['role_group'] ?? 'public');
$aiWidgetSafeAddressing = trim((string) ($aiWidgetActor['safe_addressing'] ?? 'bạn'));
$aiWidgetActorId = (int) ($aiWidgetActor['actor_id'] ?? 0);
$aiWidgetPersonaService = new AiPersonaService(is_array($config ?? null) ? $config : []);
$aiWidgetProfile = $aiWidgetPersonaService->buildWidgetProfile($aiWidgetActor, $aiWidgetProductId > 0);
$aiWidgetSupportsFeedback = !empty($aiWidgetProfile['supports_feedback']);
$aiWidgetPromptRailLabel = (string) ($aiWidgetProfile['prompt_rail_label'] ?? 'Hỏi nhanh');
$aiWidgetLauncherTitle = (string) ($aiWidgetProfile['launcher_title'] ?? 'Meow');
$aiWidgetLauncherSubtitle = (string) ($aiWidgetProfile['launcher_subtitle'] ?? 'Tư vấn mua hàng');
$aiWelcomeTitle = (string) ($aiWidgetProfile['welcome_title'] ?? 'Meow hỗ trợ');
$aiWelcomeText = (string) ($aiWidgetProfile['welcome_text'] ?? '');
$aiWelcomeHint = (string) ($aiWidgetProfile['welcome_hint'] ?? '');
$aiMetaText = (string) ($aiWidgetProfile['meta_text'] ?? '');
$aiDefaultStatus = (string) ($aiWidgetProfile['default_status'] ?? 'Chạm một gợi ý để bắt đầu nhanh hơn.');
$aiPromptsDismissedStatus = (string) ($aiWidgetProfile['prompts_dismissed_status'] ?? 'Đã ẩn gợi ý nhanh cho lần mở widget này.');
$aiBridgeStatus = (string) ($aiWidgetProfile['bridge_status'] ?? 'Meow đã phản hồi.');
$aiFallbackStatus = (string) ($aiWidgetProfile['fallback_status'] ?? 'Meow đang phản hồi bằng chế độ dự phòng kỹ thuật.');
$aiUtilityTitle = (string) ($aiWidgetProfile['utility_title'] ?? 'Tiện ích nhanh');
$aiInputPlaceholder = (string) ($aiWidgetProfile['input_placeholder'] ?? 'Ví dụ: tôi cần VPS chạy web bán hàng thì nên chọn gói nào?');
$aiStarterPrompts = (array) ($aiWidgetProfile['starter_prompts'] ?? []);
$aiUtilityActions = (array) ($aiWidgetProfile['utility_actions'] ?? []);
$aiWidgetHeaderBadge = (string) ($aiWidgetProfile['header_badge'] ?? 'Meow');

$aiWidgetStorageKeyParts = [
    'zenox-ai-widget-v5',
    $aiWidgetConversationMode,
    $aiWidgetActorType,
    (string) ($aiWidgetActorId > 0 ? $aiWidgetActorId : 'guest'),
    $aiWidgetPageType,
];
$aiWidgetSessionStorageKey = preg_replace('/[^a-z0-9\-]+/', '-', strtolower(implode('-', $aiWidgetStorageKeyParts))) ?: 'zenox-ai-widget-v5';
?>

<!-- Added: Phase 1 floating customer chat widget for landing/product pages. -->
<div
    class="zen-ai-widget"
    data-ai-widget
    data-endpoint="<?= e(base_url('ai/chat/customer')) ?>"
    data-feedback-endpoint="<?= e(base_url('ai/feedback/customer')) ?>"
    data-csrf="<?= e(csrf_token()) ?>"
    data-product-id="<?= $aiWidgetProductId ?>"
    data-page-type="<?= e($aiWidgetPageType) ?>"
    data-actor-type="<?= e($aiWidgetActorType) ?>"
    data-role-group="<?= e($aiWidgetRoleGroup) ?>"
    data-conversation-mode="<?= e($aiWidgetConversationMode) ?>"
    data-default-status="<?= e($aiDefaultStatus) ?>"
    data-prompts-dismissed-status="<?= e($aiPromptsDismissedStatus) ?>"
    data-bridge-status="<?= e($aiBridgeStatus) ?>"
    data-fallback-status="<?= e($aiFallbackStatus) ?>"
    data-session-storage-key="<?= e($aiWidgetSessionStorageKey) ?>"
    data-bot-name="<?= e((string) ($aiWidgetProfile['bot_display_name'] ?? 'Meow')) ?>"
>
    <button
        type="button"
        class="zen-ai-launcher"
        data-ai-launcher
        aria-expanded="false"
        aria-controls="zenAiPanel"
    >
        <span class="zen-ai-launcher-icon">
            <i class="fas fa-comments"></i>
        </span>
        <span class="zen-ai-launcher-copy">
            <strong><?= e($aiWidgetLauncherTitle) ?></strong>
            <small><?= e($aiWidgetLauncherSubtitle) ?></small>
        </span>
    </button>

    <section class="zen-ai-panel" id="zenAiPanel" data-ai-panel hidden>
        <header class="zen-ai-header">
            <div class="zen-ai-header-copy">
                <span class="zen-ai-header-badge"><?= e($aiWidgetHeaderBadge) ?></span>
                <h3><?= e($aiWelcomeTitle) ?></h3>
                <p><?= e($aiWelcomeText) ?></p>
            </div>
            <div class="zen-ai-header-actions">
                <button type="button" class="zen-ai-header-btn" data-ai-reset title="Làm mới cuộc trò chuyện">
                    <i class="fas fa-rotate-right"></i>
                </button>
                <button type="button" class="zen-ai-header-btn" data-ai-close title="Đóng">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
        </header>

        <!-- Added: compact widget meta so the chat area gets more usable space. -->
        <div class="zen-ai-meta">
            <span><i class="fas fa-bolt"></i> <?= e($aiMetaText) ?></span>
        </div>

        <div class="zen-ai-messages" data-ai-messages>
            <article class="zen-ai-message is-assistant is-welcome">
                <div class="zen-ai-avatar"><i class="fas fa-sparkles"></i></div>
                <div class="zen-ai-bubble">
                    <p class="mb-0"><?= e($aiWelcomeHint) ?></p>
                </div>
            </article>
        </div>

        <div class="zen-ai-status" data-ai-status>
            <span class="zen-ai-status-dot"></span>
            <span><?= e($aiDefaultStatus) ?></span>
        </div>

        <?php if ($aiWidgetSupportsFeedback): ?>
        <!-- Added: feedback panel is kept near the composer, but hidden by default to avoid a tall bottom stack. -->
        <div class="zen-ai-feedback-panel is-hidden" data-ai-feedback-panel hidden>
            <div class="zen-ai-feedback-head">
                <div>
                    <strong>Feedback sau bán</strong>
                    <p>Phản hồi sẽ được lưu thật vào hệ thống để admin theo dõi.</p>
                </div>
                <button type="button" class="zen-ai-feedback-close" data-ai-feedback-close title="Đóng">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>

            <div class="zen-ai-feedback-section">
                <div class="zen-ai-feedback-label">Loại phản hồi</div>
                <div class="zen-ai-feedback-chips" data-ai-feedback-type-group>
                    <button type="button" class="zen-ai-feedback-chip is-active" data-ai-feedback-type="general">Góp ý chung</button>
                    <button type="button" class="zen-ai-feedback-chip" data-ai-feedback-type="product">Sản phẩm</button>
                    <button type="button" class="zen-ai-feedback-chip" data-ai-feedback-type="delivery">Bàn giao</button>
                    <button type="button" class="zen-ai-feedback-chip" data-ai-feedback-type="payment">Thanh toán</button>
                    <button type="button" class="zen-ai-feedback-chip" data-ai-feedback-type="support">Cần hỗ trợ</button>
                </div>
            </div>

            <div class="zen-ai-feedback-section">
                <div class="zen-ai-feedback-label">Mức độ cảm nhận</div>
                <div class="zen-ai-feedback-chips" data-ai-feedback-sentiment-group>
                    <button type="button" class="zen-ai-feedback-chip" data-ai-feedback-sentiment="positive">Hài lòng</button>
                    <button type="button" class="zen-ai-feedback-chip is-active" data-ai-feedback-sentiment="neutral">Bình thường</button>
                    <button type="button" class="zen-ai-feedback-chip" data-ai-feedback-sentiment="negative">Chưa hài lòng</button>
                </div>
            </div>

            <div class="zen-ai-feedback-section">
                <label class="zen-ai-feedback-label" for="zenAiFeedbackInput">Nội dung feedback</label>
                <textarea
                    id="zenAiFeedbackInput"
                    class="zen-ai-feedback-input"
                    data-ai-feedback-input
                    rows="3"
                    maxlength="2000"
                    placeholder="Ví dụ: tôi đã thanh toán nhưng bàn giao hơi chậm, mong shop hỗ trợ nhanh hơn."
                ></textarea>
            </div>

            <div class="zen-ai-feedback-actions">
                <span class="zen-ai-feedback-hint">Chỉ lưu thông tin cần thiết, không hiển thị dữ liệu nhạy cảm ra ngoài widget.</span>
                <button type="button" class="zen-ai-feedback-submit" data-ai-feedback-submit>Gửi feedback</button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Added: quickbar is now anchored just above the composer so suggested questions are visible immediately. -->
        <div class="zen-ai-prompts-panel" data-ai-prompts-panel>
            <span class="zen-ai-quickbar-label"><?= e($aiWidgetPromptRailLabel) ?></span>
            <div class="zen-ai-prompts-rail">
                <button type="button" class="zen-ai-prompts-scroll" data-ai-prompts-scroll-left aria-label="Cuộn trái">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="zen-ai-prompts" data-ai-prompts>
                    <?php foreach ($aiStarterPrompts as $prompt): ?>
                        <button type="button" class="zen-ai-prompt" data-ai-prompt="<?= e($prompt) ?>">
                            <?= e($prompt) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="zen-ai-prompts-scroll" data-ai-prompts-scroll-right aria-label="Cuộn phải">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="zen-ai-quickbar-actions">
                <?php if ($aiWidgetSupportsFeedback): ?>
                <button type="button" class="zen-ai-feedback-toggle zen-ai-feedback-toggle--inline" data-ai-feedback-toggle>
                    <i class="fas fa-heart-circle-exclamation"></i>
                    <span>Feedback</span>
                </button>
                <?php endif; ?>
                <button type="button" class="zen-ai-prompts-dismiss" data-ai-prompts-dismiss title="Ẩn gợi ý">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
        </div>

        <form class="zen-ai-form" data-ai-form novalidate>
            <label class="visually-hidden" for="zenAiInput">Nhập câu hỏi cho AI</label>
            <!-- Added: compact modern composer so the input row feels lighter and less cramped. -->
            <div class="zen-ai-composer">
                <div class="zen-ai-utility-wrap">
                    <button
                        type="button"
                        class="zen-ai-utility-toggle"
                        data-ai-utility-toggle
                        aria-expanded="false"
                        aria-controls="zenAiUtilityMenu"
                        title="Mở tiện ích nhanh"
                    >
                        <i class="fas fa-bars-staggered"></i>
                    </button>
                    <div class="zen-ai-utility-menu is-hidden" id="zenAiUtilityMenu" data-ai-utility-menu hidden>
                        <div class="zen-ai-utility-title"><?= e($aiUtilityTitle) ?></div>
                        <?php foreach ($aiUtilityActions as $item): ?>
                            <button
                                type="button"
                                class="zen-ai-utility-item"
                                <?php if ($item['kind'] === 'prompt'): ?>
                                    data-ai-utility-prompt="<?= e($item['value']) ?>"
                                <?php else: ?>
                                    data-ai-utility-action="<?= e($item['kind']) ?>"
                                <?php endif; ?>
                            >
                                <i class="fas <?= e($item['icon']) ?>"></i>
                                <span><?= e($item['label']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <textarea
                    id="zenAiInput"
                    class="zen-ai-input"
                    data-ai-input
                    rows="1"
                    maxlength="4000"
                    placeholder="<?= e($aiInputPlaceholder) ?>"
                ></textarea>
                <button type="submit" class="zen-ai-send" data-ai-send aria-label="Gửi câu hỏi cho AI">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </form>
    </section>
</div>
