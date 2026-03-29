<?php
use App\Services\AiActorResolver;
use App\Services\AiPersonaService;

$actorResolver = new AiActorResolver($config);
$actor = $actorResolver->resolveFromSession();
$personaService = new AiPersonaService($config);
$profile = $personaService->buildWidgetProfile($actor);
$profileJson = htmlspecialchars((string) json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$scopeLabel = !empty($actor['is_admin'])
    ? 'Admin toàn quyền'
    : (!empty($actor['is_staff']) ? 'Staff vận hành giới hạn' : (!empty($actor['is_management_role']) ? 'Backoffice điều hành giới hạn' : 'Backoffice'));
$roleLabel = trim((string) ($actor['actor_role'] ?? 'backoffice'));
$starterPrompts = array_values(array_filter(array_map('strval', (array) ($profile['starter_prompts'] ?? []))));
?>
<div
    class="admin-ai-shell"
    data-admin-ai-panel
    data-chat-endpoint="<?= e(base_url('admin/ai/chat')) ?>"
    data-progress-endpoint="<?= e(base_url('admin/ai/progress')) ?>"
    data-session-endpoint="<?= e(base_url('admin/ai/session')) ?>"
    data-summary-endpoint="<?= e(base_url('admin/ai/summary')) ?>"
    data-profile="<?= $profileJson ?>"
    data-csrf-token="<?= e(csrf_token()) ?>"
>
    <button class="admin-ai-fab" type="button" data-admin-ai-open aria-label="Mở Meow Copilot">
        <i class="fas fa-wand-magic-sparkles"></i>
        <span>Meow Copilot</span>
    </button>

    <button class="admin-ai-overlay" type="button" data-admin-ai-overlay aria-label="Đóng Meow Copilot"></button>

    <aside class="admin-ai-drawer" data-admin-ai-drawer aria-hidden="true">
        <header class="admin-ai-header">
            <div class="admin-ai-header-copy">
                <span class="admin-ai-badge"><?= e((string) ($profile['header_badge'] ?? 'Meow Copilot')) ?></span>
                <h2 class="admin-ai-title"><?= e((string) ($profile['welcome_title'] ?? 'Meow Copilot')) ?></h2>
                <p class="admin-ai-subtitle"><?= e((string) ($profile['welcome_text'] ?? 'Tóm tắt nhanh dashboard, đơn hàng, sản phẩm và coupon.')) ?></p>
            </div>
            <div class="admin-ai-header-actions">
                <button class="admin-ai-icon-btn" type="button" data-admin-ai-refresh title="Làm mới snapshot">
                    <i class="fas fa-rotate"></i>
                </button>
                <button class="admin-ai-icon-btn" type="button" data-admin-ai-close title="Đóng copilot">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
        </header>

        <section class="admin-ai-meta">
            <span class="admin-ai-scope-pill"><?= e($scopeLabel) ?></span>
            <span class="admin-ai-role-pill"><?= e($roleLabel !== '' ? $roleLabel : 'backoffice') ?></span>
            <span class="admin-ai-session-pill" data-admin-ai-session-status>Đang chuẩn bị phiên copilot...</span>
            <span class="admin-ai-runtime-pill" data-admin-ai-runtime>Đang chuẩn bị copilot...</span>
        </section>

        <section class="admin-ai-snapshot" data-admin-ai-summary>
            <div class="admin-ai-snapshot-header">
                <div>
                    <h3 class="admin-ai-section-title">Snapshot vận hành</h3>
                    <p class="admin-ai-section-note">Dữ liệu thật từ dashboard, đơn, sản phẩm, coupon và feedback.</p>
                </div>
                <div class="admin-ai-snapshot-actions">
                    <span class="admin-ai-refresh-note">Tự làm mới khi bạn bấm mở panel</span>
                    <button
                        class="admin-ai-section-toggle"
                        type="button"
                        data-admin-ai-snapshot-toggle
                        aria-expanded="true"
                        title="Thu gọn snapshot"
                    >
                        <i class="fas fa-chevron-left" data-admin-ai-snapshot-toggle-icon></i>
                        <span data-admin-ai-snapshot-toggle-label>Thu gọn</span>
                    </button>
                </div>
            </div>
            <div class="admin-ai-card-grid" data-admin-ai-cards>
                <div class="admin-ai-empty">Đang tải snapshot...</div>
            </div>
            <div class="admin-ai-list-grid" data-admin-ai-lists></div>
        </section>

        <section class="admin-ai-chat-log" data-admin-ai-messages>
            <article class="admin-ai-message is-assistant">
                <div class="admin-ai-message-bubble">
                    <strong><?= e((string) ($profile['bot_display_name'] ?? 'Meow')) ?></strong>
                    <p><?= e((string) ($profile['welcome_hint'] ?? 'Mình có thể tóm tắt dashboard hoặc trả lời các câu hỏi quản trị ngắn gọn.')) ?></p>
                </div>
            </article>
        </section>

        <section class="admin-ai-quickbar">
            <div class="admin-ai-quickbar-head">
                <span><?= e((string) ($profile['prompt_rail_label'] ?? 'Tác vụ nhanh')) ?></span>
                <div class="admin-ai-scroll-actions">
                    <button class="admin-ai-scroll-btn" type="button" data-admin-ai-prompts-left aria-label="Cuộn trái">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="admin-ai-scroll-btn" type="button" data-admin-ai-prompts-right aria-label="Cuộn phải">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            <div class="admin-ai-prompt-rail" data-admin-ai-prompts>
                <?php foreach ($starterPrompts as $prompt): ?>
                    <button class="admin-ai-chip" type="button" data-admin-ai-prompt="<?= e($prompt) ?>"><?= e($prompt) ?></button>
                <?php endforeach; ?>
            </div>
        </section>

        <form class="admin-ai-composer" data-admin-ai-form>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <textarea
                class="admin-ai-input"
                data-admin-ai-input
                rows="2"
                maxlength="1200"
                placeholder="<?= e((string) ($profile['input_placeholder'] ?? 'Ví dụ: xem nhanh đơn chờ xử lý hoặc tóm tắt doanh thu hôm nay')) ?>"
            ></textarea>
            <div class="admin-ai-composer-actions">
                <button class="btn btn-light border" type="button" data-admin-ai-reset>
                    <i class="fas fa-rotate-right me-1"></i>Reset
                </button>
                <button class="btn btn-primary" type="submit">
                    <i class="fas fa-paper-plane me-1"></i>Gửi
                </button>
            </div>
        </form>
    </aside>
</div>
