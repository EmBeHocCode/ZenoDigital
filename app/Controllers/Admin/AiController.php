<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\AdminAiIntentService;
use App\Services\AdminAiMutationService;
use App\Services\AdminAiProgressService;
use App\Services\AdminAiSessionService;
use App\Services\AiActorResolver;
use App\Services\AiBridgeService;
use App\Services\AiContextBuilder;
use App\Services\AiGuardService;
use App\Services\AiInputNormalizerService;
use App\Services\AiPersonaService;

class AiController extends Controller
{
    public function chat(): void
    {
        $actor = $this->resolveBackofficeActorOrAbort();
        $this->ensurePostApi();
        $this->ensureAdminRateLimit('chat');

        $payload = $this->requestPayload();
        $message = sanitize_text((string) ($payload['message'] ?? ''), 4000);
        $adminId = (int) ($actor['actor_id'] ?? 0);
        $reset = !empty($payload['reset']);
        $personaService = new AiPersonaService($this->config);
        $conversationMode = $personaService->resolveModeFromActor($actor, 'admin_copilot');
        $backofficeScope = $this->buildBackofficeScope();
        $requestId = $this->normalizeRequestId((string) ($payload['request_id'] ?? ''));

        $progressService = new AdminAiProgressService($this->config);
        $contextBuilder = new AiContextBuilder($this->config);
        $guard = new AiGuardService($this->config);
        $bridge = new AiBridgeService($this->config);
        $normalizer = new AiInputNormalizerService();
        $intentService = new AdminAiIntentService($this->config);
        $mutationService = new AdminAiMutationService($this->config);
        $sessionService = new AdminAiSessionService($this->config);

        $restoredSession = $sessionService->restoreOrCreate(
            $adminId,
            (string) ($actor['actor_role'] ?? $actor['role_name'] ?? 'admin'),
            $conversationMode,
            'admin'
        );
        $sessionId = (string) ($restoredSession['session']['session_key'] ?? '');

        if ($message === '' && !$reset) {
            $this->respondJson([
                'success' => false,
                'message' => 'Tin nhắn không được để trống.',
                'request_id' => $requestId,
                'csrf_token' => $this->csrfTokenForResponse(),
            ], 422);
        }

        if ($reset) {
            $mutationService->clearSessionDraft($sessionId);
            $bridgeResult = $bridge->resetSession($sessionId);
            $nextSession = $sessionService->resetSession(
                $adminId,
                (string) ($actor['actor_role'] ?? $actor['role_name'] ?? 'admin'),
                $conversationMode,
                'admin'
            );
            $meta = $this->buildResponseMeta(
                $bridgeResult,
                'local-fallback',
                true,
                'fallback',
                'local-fallback',
                $actor,
                [
                    'backoffice_scope' => $backofficeScope,
                    'session_lifecycle' => 'reset',
                ]
            );

            $this->respondJson([
                'success' => true,
                'message' => (string) ($bridgeResult['message'] ?? 'Đã reset phiên admin AI.'),
                'data' => [
                    'sessionId' => (string) ($nextSession['session']['session_key'] ?? ''),
                    'session' => $sessionService->toClientPayload($nextSession, $conversationMode),
                    'reply' => '',
                    'provider' => $meta['provider'],
                    'isFallback' => $meta['is_fallback'],
                    'is_fallback' => $meta['is_fallback'],
                    'mode' => $meta['mode'],
                    'source' => $meta['source'],
                    'conversationMode' => $meta['conversation_mode'],
                ],
                'meta' => $meta,
                'request_id' => $requestId,
                'csrf_token' => $this->csrfTokenForResponse(),
            ]);
        }

        $sessionService->appendMessage($sessionId, $adminId, 'admin', 'user', $message, [
            'request_id' => $requestId,
            'conversation_mode' => $conversationMode,
        ]);
        $sessionService->markPending($sessionId, $adminId, 'admin', $requestId);
        $recentMessages = $sessionService->recentConversationWindow($sessionId, $adminId, 'admin', 6);

        $progressService->start($requestId, $adminId, [
            'session_id' => $sessionId,
            'channel' => 'admin',
        ]);
        $this->releaseSessionLockForProgressPolling();

        try {
            $context = $contextBuilder->buildAdminContext([
                'actor_context' => $actor,
                'backoffice_scope' => $backofficeScope,
            ]);
            $languageAnalysis = $normalizer->normalize($message, $recentMessages, $actor, $conversationMode);
            $context['language_analysis'] = $languageAnalysis;
            $context['recent_messages'] = $recentMessages;

            $sensitiveCapabilities = $guard->detectSensitiveAdminCapabilities($message);
            $restrictedCapabilities = $guard->detectRestrictedBackofficeCapabilities($message);
            $warnings = $guard->buildCapabilityWarnings(array_merge([
                'dashboard_summary',
                'pending_orders',
            ], $sensitiveCapabilities));
            $warnings = array_values(array_unique(array_merge(
                $warnings,
                $guard->buildBackofficeScopeWarnings($actor, $backofficeScope, $restrictedCapabilities)
            )));
            $refusal = $guard->buildFinancialCapabilityRefusal($sensitiveCapabilities);
            $scopeRefusal = $guard->buildBackofficePermissionRefusal($actor, $backofficeScope, $restrictedCapabilities);

            if ($refusal !== null || $scopeRefusal !== null) {
                $activeRefusal = $refusal ?? $scopeRefusal;
                $reply = (string) ($activeRefusal['message'] ?? 'Thiếu dữ liệu để phân tích.');
                $meta = array_merge([
                    'provider' => 'guardrail',
                    'is_fallback' => false,
                    'mode' => 'guardrail',
                    'source' => 'ai-guardrail',
                ], $this->buildActorMeta($actor, [
                    'backoffice_scope' => $backofficeScope,
                ]));
                $progressService->markSummarizing($requestId, $adminId, [
                    'intent' => 'guardrail',
                ]);
                $progressState = $progressService->complete($requestId, $adminId, [
                    'result_mode' => 'guardrail',
                ]);
                $sessionService->appendMessage($sessionId, $adminId, 'admin', 'assistant', $reply, [
                    'mode' => 'guardrail',
                    'blocked_capabilities' => (array) ($activeRefusal['capabilities'] ?? []),
                ]);
                $sessionService->clearPending($sessionId, $adminId, 'admin');

                $this->respondJson([
                    'success' => true,
                    'message' => 'Yêu cầu đã được chặn bởi guardrail dữ liệu.',
                    'data' => [
                        'sessionId' => $sessionId,
                        'session' => $this->buildSessionPayload($sessionService, $adminId, $actor, $conversationMode),
                        'reply' => $reply,
                        'provider' => $meta['provider'],
                        'isFallback' => $meta['is_fallback'],
                        'is_fallback' => $meta['is_fallback'],
                        'mode' => $meta['mode'],
                        'source' => $meta['source'],
                        'conversationMode' => $meta['conversation_mode'],
                        'blocked' => true,
                    ],
                    'meta' => $meta,
                    'progress' => $progressState,
                    'request_id' => $requestId,
                    'guardrails' => $guard->getGuardRules('admin'),
                    'warnings' => $warnings,
                    'missing_fields' => (array) ($activeRefusal['missing_fields'] ?? []),
                    'blocked_capabilities' => (array) ($activeRefusal['capabilities'] ?? []),
                    'csrf_token' => $this->csrfTokenForResponse(),
                ]);
            }

            $intentDecision = $intentService->resolve($message, $languageAnalysis, $actor, $backofficeScope);
            if (($intentDecision['action'] ?? '') === 'clarify') {
                $reply = (string) ($intentDecision['question'] ?? 'Bạn muốn xem chi tiết theo tiêu chí nào?');
                $meta = array_merge([
                    'provider' => 'admin-data-engine',
                    'is_fallback' => false,
                    'mode' => 'clarification',
                    'source' => 'shop-data',
                ], $this->buildActorMeta($actor, [
                    'backoffice_scope' => $backofficeScope,
                    'intent' => (string) ($intentDecision['intent'] ?? 'unknown'),
                ]));
                $progressService->markSummarizing($requestId, $adminId, [
                    'intent' => (string) ($intentDecision['intent'] ?? 'orders_generic'),
                ]);
                $progressState = $progressService->complete($requestId, $adminId, [
                    'result_mode' => 'clarification',
                ]);
                $sessionService->appendMessage($sessionId, $adminId, 'admin', 'assistant', $reply, [
                    'mode' => 'clarification',
                    'intent' => (string) ($intentDecision['intent'] ?? 'unknown'),
                ]);
                $sessionService->clearPending($sessionId, $adminId, 'admin');

                $this->respondJson([
                    'success' => true,
                    'message' => 'Cần làm rõ một tham số để tiếp tục.',
                    'data' => [
                        'sessionId' => $sessionId,
                        'session' => $this->buildSessionPayload($sessionService, $adminId, $actor, $conversationMode),
                        'reply' => $reply,
                        'provider' => $meta['provider'],
                        'isFallback' => $meta['is_fallback'],
                        'is_fallback' => $meta['is_fallback'],
                        'mode' => $meta['mode'],
                        'source' => $meta['source'],
                        'conversationMode' => $meta['conversation_mode'],
                    ],
                    'meta' => $meta,
                    'progress' => $progressState,
                    'request_id' => $requestId,
                    'guardrails' => $guard->getGuardRules('admin'),
                    'warnings' => $warnings,
                    'csrf_token' => $this->csrfTokenForResponse(),
                ]);
            }

            if (($intentDecision['action'] ?? '') === 'execute') {
                $directResult = $intentService->execute($intentDecision, $actor, $backofficeScope);
                $reply = (string) ($directResult['reply'] ?? '');
                $progressService->markSummarizing($requestId, $adminId, [
                    'intent' => (string) ($intentDecision['intent'] ?? 'direct_admin_read'),
                ]);
                $meta = $this->buildResponseMeta(
                    (array) ($directResult['meta'] ?? []),
                    'admin-data-engine',
                    false,
                    'direct_admin_read',
                    'shop-data',
                    $actor,
                    [
                        'backoffice_scope' => $backofficeScope,
                        'intent' => (string) ($intentDecision['intent'] ?? 'direct_admin_read'),
                    ]
                );
                $progressState = $progressService->complete($requestId, $adminId, [
                    'result_mode' => 'direct_admin_read',
                ]);
                $sessionService->appendMessage($sessionId, $adminId, 'admin', 'assistant', $reply, [
                    'mode' => 'direct_admin_read',
                    'intent' => (string) ($intentDecision['intent'] ?? 'direct_admin_read'),
                    'mutation' => $directResult['mutation'] ?? null,
                    'refresh_summary' => !empty($directResult['refresh_summary']),
                ]);
                $sessionService->clearPending($sessionId, $adminId, 'admin');

                $this->respondJson([
                    'success' => true,
                    'message' => 'Đã xử lý yêu cầu AI admin bằng dữ liệu shop trực tiếp.',
                    'data' => [
                        'sessionId' => $sessionId,
                        'session' => $this->buildSessionPayload($sessionService, $adminId, $actor, $conversationMode),
                        'reply' => $reply,
                        'provider' => $meta['provider'],
                        'isFallback' => $meta['is_fallback'],
                        'is_fallback' => $meta['is_fallback'],
                        'mode' => $meta['mode'],
                        'source' => $meta['source'],
                        'conversationMode' => $meta['conversation_mode'],
                        'mutation' => $directResult['mutation'] ?? null,
                        'refreshSummary' => !empty($directResult['refresh_summary']),
                        'refresh_summary' => !empty($directResult['refresh_summary']),
                    ],
                    'meta' => $meta,
                    'progress' => $progressState,
                    'request_id' => $requestId,
                    'guardrails' => $guard->getGuardRules('admin'),
                    'warnings' => $warnings,
                    'csrf_token' => $this->csrfTokenForResponse(),
                ]);
            }

            if (($intentDecision['action'] ?? '') === 'preview') {
                $this->ensureWritableSession();
                $previewResult = $mutationService->preview($intentDecision, $actor, $backofficeScope, $sessionId);
                $reply = (string) ($previewResult['reply'] ?? '');
                $progressService->markSummarizing($requestId, $adminId, [
                    'intent' => (string) ($intentDecision['intent'] ?? 'mutation_preview'),
                ]);
                $meta = $this->buildResponseMeta(
                    (array) ($previewResult['meta'] ?? []),
                    'admin-data-engine',
                    false,
                    'mutation_preview',
                    'shop-data',
                    $actor,
                    [
                        'backoffice_scope' => $backofficeScope,
                        'intent' => (string) ($intentDecision['intent'] ?? 'mutation_preview'),
                    ]
                );
                $progressState = $progressService->complete($requestId, $adminId, [
                    'result_mode' => (string) ($meta['mode'] ?? 'mutation_preview'),
                ]);
                $sessionService->appendMessage($sessionId, $adminId, 'admin', 'assistant', $reply, [
                    'mode' => (string) ($meta['mode'] ?? 'mutation_preview'),
                    'intent' => (string) ($intentDecision['intent'] ?? 'mutation_preview'),
                    'requires_confirmation' => !empty($previewResult['requires_confirmation']),
                    'draft_id' => $previewResult['draft_id'] ?? null,
                ]);
                $sessionService->clearPending($sessionId, $adminId, 'admin');

                $this->respondJson([
                    'success' => true,
                    'message' => 'Đã tạo preview thao tác quản trị.',
                    'data' => [
                        'sessionId' => $sessionId,
                        'session' => $this->buildSessionPayload($sessionService, $adminId, $actor, $conversationMode),
                        'reply' => $reply,
                        'provider' => $meta['provider'],
                        'isFallback' => $meta['is_fallback'],
                        'is_fallback' => $meta['is_fallback'],
                        'mode' => $meta['mode'],
                        'source' => $meta['source'],
                        'conversationMode' => $meta['conversation_mode'],
                        'requiresConfirmation' => !empty($previewResult['requires_confirmation']),
                        'requires_confirmation' => !empty($previewResult['requires_confirmation']),
                        'draftId' => $previewResult['draft_id'] ?? null,
                        'draft_id' => $previewResult['draft_id'] ?? null,
                    ],
                    'meta' => $meta,
                    'progress' => $progressState,
                    'request_id' => $requestId,
                    'guardrails' => $guard->getGuardRules('admin'),
                    'warnings' => $warnings,
                    'csrf_token' => $this->csrfTokenForResponse(),
                ]);
            }

            if (($intentDecision['action'] ?? '') === 'confirm') {
                $this->ensureWritableSession();
                $directResult = $mutationService->confirmCurrent($actor, $backofficeScope, $sessionId);
                $reply = (string) ($directResult['reply'] ?? '');
                $progressService->markSummarizing($requestId, $adminId, [
                    'intent' => (string) ($intentDecision['intent'] ?? 'mutation_confirm'),
                ]);
                $meta = $this->buildResponseMeta(
                    (array) ($directResult['meta'] ?? []),
                    'admin-data-engine',
                    false,
                    'direct_admin_action',
                    'shop-data',
                    $actor,
                    [
                        'backoffice_scope' => $backofficeScope,
                        'intent' => (string) ($intentDecision['intent'] ?? 'mutation_confirm'),
                    ]
                );
                $progressState = $progressService->complete($requestId, $adminId, [
                    'result_mode' => (string) ($meta['mode'] ?? 'direct_admin_action'),
                ]);
                $sessionService->appendMessage($sessionId, $adminId, 'admin', 'assistant', $reply, [
                    'mode' => (string) ($meta['mode'] ?? 'direct_admin_action'),
                    'intent' => (string) ($intentDecision['intent'] ?? 'mutation_confirm'),
                    'mutation' => $directResult['mutation'] ?? null,
                    'refresh_summary' => !empty($directResult['refresh_summary']),
                ]);
                $sessionService->clearPending($sessionId, $adminId, 'admin');

                $this->respondJson([
                    'success' => true,
                    'message' => 'Đã xử lý xác nhận thao tác quản trị.',
                    'data' => [
                        'sessionId' => $sessionId,
                        'session' => $this->buildSessionPayload($sessionService, $adminId, $actor, $conversationMode),
                        'reply' => $reply,
                        'provider' => $meta['provider'],
                        'isFallback' => $meta['is_fallback'],
                        'is_fallback' => $meta['is_fallback'],
                        'mode' => $meta['mode'],
                        'source' => $meta['source'],
                        'conversationMode' => $meta['conversation_mode'],
                        'mutation' => $directResult['mutation'] ?? null,
                        'refreshSummary' => !empty($directResult['refresh_summary']),
                        'refresh_summary' => !empty($directResult['refresh_summary']),
                    ],
                    'meta' => $meta,
                    'progress' => $progressState,
                    'request_id' => $requestId,
                    'guardrails' => $guard->getGuardRules('admin'),
                    'warnings' => $warnings,
                    'csrf_token' => $this->csrfTokenForResponse(),
                ]);
            }

            if (($intentDecision['action'] ?? '') === 'cancel') {
                $this->ensureWritableSession();
                $directResult = $mutationService->cancelCurrent($actor, $sessionId);
                $reply = (string) ($directResult['reply'] ?? '');
                $progressService->markSummarizing($requestId, $adminId, [
                    'intent' => 'mutation_cancel',
                ]);
                $meta = $this->buildResponseMeta(
                    (array) ($directResult['meta'] ?? []),
                    'admin-data-engine',
                    false,
                    'mutation_cancelled',
                    'shop-data',
                    $actor,
                    [
                        'backoffice_scope' => $backofficeScope,
                        'intent' => 'mutation_cancel',
                    ]
                );
                $progressState = $progressService->complete($requestId, $adminId, [
                    'result_mode' => (string) ($meta['mode'] ?? 'mutation_cancelled'),
                ]);
                $sessionService->appendMessage($sessionId, $adminId, 'admin', 'assistant', $reply, [
                    'mode' => (string) ($meta['mode'] ?? 'mutation_cancelled'),
                    'intent' => 'mutation_cancel',
                ]);
                $sessionService->clearPending($sessionId, $adminId, 'admin');

                $this->respondJson([
                    'success' => true,
                    'message' => 'Đã hủy thao tác nháp.',
                    'data' => [
                        'sessionId' => $sessionId,
                        'session' => $this->buildSessionPayload($sessionService, $adminId, $actor, $conversationMode),
                        'reply' => $reply,
                        'provider' => $meta['provider'],
                        'isFallback' => $meta['is_fallback'],
                        'is_fallback' => $meta['is_fallback'],
                        'mode' => $meta['mode'],
                        'source' => $meta['source'],
                        'conversationMode' => $meta['conversation_mode'],
                    ],
                    'meta' => $meta,
                    'progress' => $progressState,
                    'request_id' => $requestId,
                    'guardrails' => $guard->getGuardRules('admin'),
                    'warnings' => $warnings,
                    'csrf_token' => $this->csrfTokenForResponse(),
                ]);
            }

            $progressService->markSummarizing($requestId, $adminId, [
                'intent' => (string) ($languageAnalysis['intent_guess'] ?? 'bridge'),
                'freshness' => (array) ($context['data_freshness'] ?? []),
            ]);
            $result = $bridge->chat('admin', $sessionId, $message, $context, $this->buildActorMeta($actor, [
                'guard_warnings' => $warnings,
                'admin_id' => $adminId,
                'backoffice_scope' => $backofficeScope,
                'original_text' => (string) ($languageAnalysis['original_text'] ?? $message),
                'normalized_text' => (string) ($languageAnalysis['normalized_text'] ?? $message),
                'intent_guess' => (string) ($languageAnalysis['intent_guess'] ?? 'unknown'),
                'normalization_confidence' => (float) ($languageAnalysis['confidence'] ?? 0),
                'requires_clarification' => !empty($languageAnalysis['requires_clarification']),
                'slang_hits' => (array) ($languageAnalysis['slang_hits'] ?? []),
                'context_hint' => (string) ($languageAnalysis['context_hint'] ?? ''),
                'recent_messages' => $recentMessages,
            ]));
            $meta = $this->buildResponseMeta($result, 'bridge', false, 'real_bridge', 'ai-bridge', $actor, [
                'backoffice_scope' => $backofficeScope,
                'freshness' => (array) ($context['data_freshness'] ?? []),
            ]);
            $progressState = $progressService->complete($requestId, $adminId, [
                'result_mode' => 'bridge',
            ]);
            $reply = (string) ($result['reply'] ?? '');
            $sessionService->appendMessage($sessionId, $adminId, 'admin', 'assistant', $reply, [
                'mode' => 'real_bridge',
                'provider' => (string) ($meta['provider'] ?? 'bridge'),
                'source' => (string) ($meta['source'] ?? 'ai-bridge'),
                'is_fallback' => (bool) ($meta['is_fallback'] ?? false),
            ]);
            $sessionService->clearPending($sessionId, $adminId, 'admin');

            $this->respondJson([
                'success' => true,
                'message' => 'Đã xử lý yêu cầu AI admin.',
                'data' => [
                    'sessionId' => $sessionId,
                    'session' => $this->buildSessionPayload($sessionService, $adminId, $actor, $conversationMode),
                    'reply' => $reply,
                    'provider' => $meta['provider'],
                    'isFallback' => $meta['is_fallback'],
                    'is_fallback' => $meta['is_fallback'],
                    'mode' => $meta['mode'],
                    'source' => $meta['source'],
                    'conversationMode' => $meta['conversation_mode'],
                ],
                'meta' => $meta,
                'progress' => $progressState,
                'request_id' => $requestId,
                'guardrails' => $guard->getGuardRules('admin'),
                'warnings' => $warnings,
                'csrf_token' => $this->csrfTokenForResponse(),
            ]);
        } catch (\Throwable $exception) {
            try {
                if ($sessionId !== '') {
                    $sessionService->appendMessage(
                        $sessionId,
                        $adminId,
                        'admin',
                        'system',
                        'Yêu cầu trước chưa hoàn tất do lỗi kỹ thuật. Nếu cần, bạn có thể hỏi lại ngay trong phiên hiện tại.',
                        [
                            'mode' => 'system_error',
                            'request_id' => $requestId,
                            'error' => $exception->getMessage(),
                        ]
                    );
                    $sessionService->clearPending($sessionId, $adminId, 'admin');
                }
            } catch (\Throwable $ignored) {
                // Ignore nested persistence failures to preserve the main error response.
            }

            $progressState = $progressService->fail(
                $requestId,
                $adminId,
                'Bot không thể hoàn tất yêu cầu lần này. Vui lòng thử lại sau ít phút.',
                ['error' => $exception->getMessage()]
            );

            $this->respondJson([
                'success' => false,
                'message' => 'Bot không thể hoàn tất yêu cầu lần này. Vui lòng thử lại sau ít phút.',
                'progress' => $progressState,
                'request_id' => $requestId,
                'csrf_token' => $this->csrfTokenForResponse(),
            ], 500);
        }
    }

    public function session(): void
    {
        $actor = $this->resolveBackofficeActorOrAbort();
        $this->ensureGetApi();
        $this->ensureAdminRateLimit('session');

        $personaService = new AiPersonaService($this->config);
        $conversationMode = $personaService->resolveModeFromActor($actor, 'admin_copilot');
        $sessionService = new AdminAiSessionService($this->config);
        $restored = $sessionService->restoreOrCreate(
            (int) ($actor['actor_id'] ?? 0),
            (string) ($actor['actor_role'] ?? $actor['role_name'] ?? 'admin'),
            $conversationMode,
            'admin'
        );

        $this->respondJson([
            'success' => true,
            'data' => $sessionService->toClientPayload($restored, $conversationMode),
            'csrf_token' => $this->csrfTokenForResponse(),
        ]);
    }

    public function summary(): void
    {
        $actor = $this->resolveBackofficeActorOrAbort();
        $this->ensureGetApi();
        $this->ensureAdminRateLimit('summary');

        $contextBuilder = new AiContextBuilder($this->config);
        $guard = new AiGuardService($this->config);
        $bridge = new AiBridgeService($this->config);
        $personaService = new AiPersonaService($this->config);
        $backofficeScope = $this->buildBackofficeScope();
        $context = $contextBuilder->buildAdminContext([
            'actor_context' => $actor,
            'backoffice_scope' => $backofficeScope,
        ]);

        $this->respondJson([
            'success' => true,
            'data' => array_merge($context, [
                'panel_profile' => $personaService->buildWidgetProfile($actor),
                'runtime' => [
                    'provider' => (string) config('ai.provider', 'bridge'),
                    'bridge_enabled' => $bridge->isEnabled(),
                    'bridge_configured' => $bridge->isConfigured(),
                ],
            ]),
            'warnings' => $guard->buildCapabilityWarnings(['dashboard_summary']),
            'csrf_token' => $this->csrfTokenForResponse(),
        ]);
    }

    public function progress(): void
    {
        $actor = $this->resolveBackofficeActorOrAbort();
        $this->ensureGetApi();

        $rawRequestId = trim((string) ($_GET['request_id'] ?? ''));
        if ($rawRequestId === '') {
            $this->respondJson([
                'success' => false,
                'message' => 'Thiếu request_id để đọc tiến trình AI.',
            ], 422);
        }

        $requestId = $this->normalizeRequestId($rawRequestId);
        $adminId = (int) ($actor['actor_id'] ?? 0);
        $progressService = new AdminAiProgressService($this->config);
        $state = $progressService->find($requestId, $adminId);

        if ($state === null) {
            $this->respondJson([
                'success' => false,
                'message' => 'Không tìm thấy tiến trình AI tương ứng.',
                'request_id' => $requestId,
            ], 404);
        }

        $this->respondJson([
            'success' => true,
            'data' => $state,
            'request_id' => $requestId,
        ]);
    }

    private function requestPayload(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    private function resolveBackofficeActorOrAbort(): array
    {
        $resolver = new AiActorResolver($this->config);
        $actor = $resolver->resolveFromSession();

        if (!$resolver->isBackofficeActor($actor) || !Auth::can('backoffice.ai')) {
            $this->respondJson([
                'success' => false,
                'message' => 'Phiên backoffice không hợp lệ hoặc đã hết hạn.',
            ], 403);
        }

        return $actor;
    }

    private function ensurePostApi(): void
    {
        if (!is_post()) {
            $this->respondJson([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }
    }

    private function ensureAdminRateLimit(string $bucketSuffix): void
    {
        $result = $this->consumeRateLimit('ai-admin-' . trim($bucketSuffix), 'ai_admin_chat');

        if (!$result['allowed']) {
            header('Retry-After: ' . (int) ($result['retry_after'] ?? 60));
            $this->respondJson([
                'success' => false,
                'message' => 'Bạn đang gửi yêu cầu AI quá nhanh. Vui lòng thử lại sau ít giây.',
                'retry_after' => (int) ($result['retry_after'] ?? 60),
                'csrf_token' => $this->csrfTokenForResponse(),
            ], 429);
        }
    }

    private function ensureGetApi(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
            $this->respondJson([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }
    }

    private function buildResponseMeta(array $result, string $defaultProvider, bool $defaultFallback, string $defaultMode, string $defaultSource, array $actor = [], array $extra = []): array
    {
        return array_merge([
            'provider' => (string) ($result['provider'] ?? $defaultProvider),
            'is_fallback' => (bool) ($result['is_fallback'] ?? $defaultFallback),
            'mode' => (string) ($result['mode'] ?? $defaultMode),
            'source' => (string) ($result['source'] ?? $defaultSource),
        ], $this->buildActorMeta($actor, $extra));
    }

    private function respondJson(array $payload, int $status = 200): void
    {
        $this->ensureWritableSession();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function buildActorMeta(array $actor, array $extra = []): array
    {
        return array_merge([
            'auth' => (string) ($actor['auth_state'] ?? 'unknown'),
            'auth_state' => (string) ($actor['auth_state'] ?? 'unknown'),
            'actor_type' => (string) ($actor['actor_type'] ?? 'unknown'),
            'actor_role' => (string) ($actor['actor_role'] ?? $actor['role_name'] ?? 'unknown'),
            'role_group' => (string) ($actor['role_group'] ?? 'safe'),
            'actor_name' => (string) ($actor['actor_name'] ?? ''),
            'actor_gender' => (string) ($actor['actor_gender'] ?? 'unknown'),
            'actor_birth_date' => $actor['actor_birth_date'] ?? null,
            'actor_age' => $actor['actor_age'] ?? null,
            'safe_addressing' => (string) ($actor['safe_addressing'] ?? 'bạn'),
            'actor_id' => $actor['actor_id'] ?? null,
            'is_admin' => !empty($actor['is_admin']),
            'is_staff' => !empty($actor['is_staff']),
            'is_management_role' => !empty($actor['is_management_role']),
            'conversation_mode' => (new AiPersonaService($this->config))->resolveModeFromActor($actor, 'admin_copilot'),
            'route_scope' => 'admin_panel',
        ], $extra);
    }

    private function buildBackofficeScope(): array
    {
        $roleName = Auth::roleName();

        return [
            'role_name' => $roleName,
            'scope_key' => Auth::isAdmin() ? 'admin_full' : 'operations_limited',
            'label' => Auth::isAdmin()
                ? 'Admin toàn quyền'
                : (Auth::isStaff() ? 'Staff vận hành giới hạn' : 'Backoffice điều hành giới hạn'),
            'can_access_dashboard' => Auth::can('backoffice.dashboard'),
            'can_use_ai_copilot' => Auth::can('backoffice.ai'),
            'can_view_products' => Auth::can('backoffice.data.products'),
            'can_manage_products' => Auth::can('admin.products.manage'),
            'can_view_categories' => Auth::can('admin.categories.view'),
            'can_manage_categories' => Auth::can('admin.categories.manage'),
            'can_view_orders' => Auth::can('backoffice.data.orders'),
            'can_manage_orders' => Auth::can('admin.orders.manage'),
            'can_view_payments' => Auth::can('admin.payments.view'),
            'can_view_coupons' => Auth::can('backoffice.data.coupons'),
            'can_manage_coupons' => Auth::can('admin.coupons.manage'),
            'can_view_feedback' => Auth::can('backoffice.data.feedback'),
            'can_manage_feedback' => Auth::can('admin.feedback.manage'),
            'can_view_finance' => Auth::can('backoffice.data.finance'),
            'can_view_users' => Auth::can('backoffice.data.users'),
            'can_manage_users' => Auth::can('admin.users.manage'),
            'can_view_rank' => Auth::can('backoffice.data.rank'),
            'can_manage_rank' => Auth::can('admin.ranks.manage'),
            'can_view_settings' => Auth::can('admin.settings.manage'),
            'can_manage_settings' => Auth::can('admin.settings.manage'),
            'can_view_audit' => Auth::can('admin.audit.view'),
            'can_view_sql' => Auth::can('admin.sql.manage'),
            'can_manage_sql' => Auth::can('admin.sql.manage'),
            'can_manage_system' => Auth::isAdmin(),
        ];
    }

    private function buildSessionPayload(AdminAiSessionService $sessionService, int $adminId, array $actor, string $conversationMode): array
    {
        $restored = $sessionService->restoreOrCreate(
            $adminId,
            (string) ($actor['actor_role'] ?? $actor['role_name'] ?? 'admin'),
            $conversationMode,
            'admin'
        );

        return $sessionService->toClientPayload($restored, $conversationMode);
    }

    private function releaseSessionLockForProgressPolling(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    private function ensureWritableSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    private function csrfTokenForResponse(): string
    {
        $this->ensureWritableSession();
        return csrf_token();
    }

    private function normalizeRequestId(string $requestId): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower(trim($requestId))) ?? '';
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            $normalized = 'req-' . substr(sha1(uniqid('admin-ai', true)), 0, 12);
        }

        return substr($normalized, 0, 80);
    }
}
