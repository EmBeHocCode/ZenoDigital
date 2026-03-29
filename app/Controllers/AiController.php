<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AiActorResolver;
use App\Services\AiBridgeService;
use App\Services\AiContextBuilder;
use App\Services\AiGuardService;
use App\Services\AiInputNormalizerService;
use App\Services\AiOrderAccountSupportService;
use App\Services\AiPersonaService;
use App\Services\CustomerFeedbackService;
use App\Services\AiSessionManager;

class AiController extends Controller
{
    public function customerChat(): void
    {
        $this->ensurePostApi();
        $this->ensureCustomerRateLimit();

        $payload = $this->requestPayload();
        $message = sanitize_text((string) ($payload['message'] ?? ''), 4000);
        $productId = validate_int_range($payload['product_id'] ?? 0, 0, 999999999, 0);
        $actorResolver = new AiActorResolver($this->config);
        $actor = $actorResolver->resolveFromSession();
        $actorId = (int) ($actor['actor_id'] ?? 0) > 0 ? (int) $actor['actor_id'] : null;
        $personaService = new AiPersonaService($this->config);
        $conversationMode = $personaService->resolveModeFromActor($actor);
        $recentMessages = $this->extractRecentMessages($payload['recent_messages'] ?? []);
        $sessionPrefix = trim((string) config('ai.customer_session_prefix', 'customer-web'));
        $channel = $this->resolvePublicChatChannel($actor);
        $reset = !empty($payload['reset']);

        if ($message === '' && !$reset) {
            $this->respondJson([
                'success' => false,
                'message' => 'Tin nhắn không được để trống.',
                'csrf_token' => csrf_token(),
            ], 422);
        }

        $contextBuilder = new AiContextBuilder($this->config);
        $guard = new AiGuardService($this->config);
        $bridge = new AiBridgeService($this->config);
        $normalizer = new AiInputNormalizerService();
        $sessionManager = new AiSessionManager($this->config);
        $sessionId = $sessionManager->resolveSessionId(
            'customer',
            (string) ($payload['session_id'] ?? ''),
            $sessionPrefix . '-' . (session_id() ?: 'guest'),
            $actorId
        );

        if ($reset) {
            $result = $bridge->resetSession($sessionId);
            $sessionManager->forgetSessionId('customer', $actorId);
            $nextSessionId = $sessionManager->resolveSessionId('customer', '', $sessionPrefix . '-' . (session_id() ?: 'guest'), $actorId);
            $meta = $this->buildResponseMeta($result, 'local-fallback', true, 'fallback', 'local-fallback', $actor, 'public_storefront');

            $this->respondJson([
                'success' => true,
                'message' => (string) ($result['message'] ?? 'Đã reset phiên chat.'),
                'data' => [
                    'sessionId' => $nextSessionId,
                    'provider' => $meta['provider'],
                    'isFallback' => $meta['is_fallback'],
                    'is_fallback' => $meta['is_fallback'],
                    'mode' => $meta['mode'],
                    'source' => $meta['source'],
                    'conversationMode' => $meta['conversation_mode'],
                ],
                'meta' => $meta,
                'csrf_token' => csrf_token(),
            ]);
        }

        $context = $this->buildPublicChatContext($contextBuilder, $actor, $productId);
        $languageAnalysis = $normalizer->normalize($message, $recentMessages, $actor, $conversationMode);
        $context['language_analysis'] = $languageAnalysis;
        $context['recent_messages'] = $recentMessages;
        $accountSupportService = new AiOrderAccountSupportService($this->config);
        $accountSupport = $channel === 'customer'
            ? $accountSupportService->resolve($message, $actor, $languageAnalysis)
            : ['handled' => false, 'structured_reply' => '', 'context_patch' => [], 'meta' => []];

        if (!empty($accountSupport['handled']) && !empty($accountSupport['context_patch']) && is_array($accountSupport['context_patch'])) {
            $context = array_replace_recursive($context, $accountSupport['context_patch']);
        }

        $sensitiveCapabilities = $channel === 'admin'
            ? $guard->detectSensitiveAdminCapabilities($message)
            : [];
        $warnings = $channel === 'admin'
            ? $guard->buildCapabilityWarnings(array_merge(['dashboard_summary', 'pending_orders'], $sensitiveCapabilities))
            : $guard->buildCapabilityWarnings($this->resolveCustomerCapabilities($accountSupport));

        if ($channel === 'admin') {
            $refusal = $guard->buildFinancialCapabilityRefusal($sensitiveCapabilities);

            if ($refusal !== null) {
                $meta = array_merge([
                    'provider' => 'guardrail',
                    'is_fallback' => false,
                    'mode' => 'guardrail',
                    'source' => 'ai-guardrail',
                ], $this->buildActorMeta($actor, 'public_storefront'));

                $this->respondJson([
                    'success' => true,
                    'message' => 'Yêu cầu đã được chặn bởi guardrail dữ liệu.',
                    'data' => [
                        'sessionId' => $sessionId,
                        'reply' => (string) ($refusal['message'] ?? 'Thiếu dữ liệu để phân tích.'),
                        'provider' => $meta['provider'],
                        'isFallback' => $meta['is_fallback'],
                        'is_fallback' => $meta['is_fallback'],
                        'mode' => $meta['mode'],
                        'source' => $meta['source'],
                        'conversationMode' => $meta['conversation_mode'],
                        'blocked' => true,
                    ],
                    'meta' => $meta,
                    'guardrails' => $guard->getGuardRules('admin'),
                    'warnings' => $warnings,
                    'missing_fields' => (array) ($refusal['missing_fields'] ?? []),
                    'blocked_capabilities' => (array) ($refusal['capabilities'] ?? []),
                    'csrf_token' => csrf_token(),
                ]);
            }
        }

        $result = $bridge->chat($channel, $sessionId, $message, $context, $this->buildActorMeta($actor, 'public_storefront', array_merge([
            'guard_warnings' => $warnings,
            'original_text' => (string) ($languageAnalysis['original_text'] ?? $message),
            'normalized_text' => (string) ($languageAnalysis['normalized_text'] ?? $message),
            'intent_guess' => (string) ($languageAnalysis['intent_guess'] ?? 'unknown'),
            'normalization_confidence' => (float) ($languageAnalysis['confidence'] ?? 0),
            'requires_clarification' => !empty($languageAnalysis['requires_clarification']),
            'slang_hits' => (array) ($languageAnalysis['slang_hits'] ?? []),
            'context_hint' => (string) ($languageAnalysis['context_hint'] ?? ''),
            'recent_messages' => $recentMessages,
        ], (array) ($accountSupport['meta'] ?? []))));
        $meta = array_merge(
            $this->buildResponseMeta($result, 'bridge', false, 'real_bridge', 'ai-bridge', $actor, 'public_storefront'),
            (array) ($accountSupport['meta'] ?? [])
        );
        $reply = trim((string) ($result['reply'] ?? ''));
        $structuredReply = trim((string) ($accountSupport['structured_reply'] ?? ''));

        if ($structuredReply !== '') {
            $reply = $structuredReply . ($reply !== '' ? "\n\n" . $reply : '');
        }

        $this->respondJson([
            'success' => true,
            'message' => 'Đã xử lý yêu cầu chat khách.',
            'data' => [
                'sessionId' => (string) ($result['session_id'] ?? $sessionId),
                'reply' => $reply,
                'provider' => $meta['provider'],
                'isFallback' => $meta['is_fallback'],
                'is_fallback' => $meta['is_fallback'],
                'mode' => $meta['mode'],
                'source' => $meta['source'],
                'conversationMode' => $meta['conversation_mode'],
            ],
            'meta' => $meta,
            'guardrails' => $guard->getGuardRules($channel === 'admin' ? 'admin' : 'customer'),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function customerFeedback(): void
    {
        $this->ensurePostApi();
        $this->ensureCustomerRateLimit();

        $payload = $this->requestPayload();
        $actorResolver = new AiActorResolver($this->config);
        $actor = $actorResolver->resolveFromSession();
        $actorId = (int) ($actor['actor_id'] ?? 0) > 0 ? (int) $actor['actor_id'] : null;
        $sessionPrefix = trim((string) config('ai.customer_session_prefix', 'customer-web'));
        $personaService = new AiPersonaService($this->config);
        $conversationMode = $personaService->resolveModeFromActor($actor);
        $recentMessages = $this->extractRecentMessages($payload['recent_messages'] ?? []);

        $sessionManager = new AiSessionManager($this->config);
        $sessionId = $sessionManager->resolveSessionId(
            'customer',
            (string) ($payload['session_id'] ?? ''),
            $sessionPrefix . '-' . (session_id() ?: 'guest'),
            $actorId
        );

        $feedbackService = new CustomerFeedbackService($this->config);
        $channel = $this->resolvePublicChatChannel($actor);

        try {
            $feedback = $feedbackService->capture($payload, $actor, $sessionId);
        } catch (\InvalidArgumentException $exception) {
            $this->respondJson([
                'success' => false,
                'message' => $exception->getMessage(),
                'csrf_token' => csrf_token(),
            ], 422);
        } catch (\Throwable $exception) {
            security_log('Không thể lưu feedback AI widget', [
                'user_id' => $actorId,
                'error' => $exception->getMessage(),
            ]);

            $this->respondJson([
                'success' => false,
                'message' => 'Hệ thống chưa lưu được feedback lúc này. Bạn thử lại sau ít phút nhé.',
                'csrf_token' => csrf_token(),
            ], 500);
        }

        $contextBuilder = new AiContextBuilder($this->config);
        $guard = new AiGuardService($this->config);
        $bridge = new AiBridgeService($this->config);
        $normalizer = new AiInputNormalizerService();
        $context = $this->buildPublicChatContext($contextBuilder, $actor, (int) ($feedback['product_id'] ?? 0));
        $languageAnalysis = $normalizer->normalize((string) ($feedback['message'] ?? ''), $recentMessages, $actor, $conversationMode);
        $context['language_analysis'] = $languageAnalysis;
        $context['recent_messages'] = $recentMessages;
        $context['feedback'] = [
            'feedback_code' => (string) ($feedback['feedback_code'] ?? ''),
            'feedback_type' => (string) ($feedback['feedback_type'] ?? 'general'),
            'sentiment' => (string) ($feedback['sentiment'] ?? 'neutral'),
            'severity' => (string) ($feedback['severity'] ?? 'low'),
            'needs_follow_up' => !empty($feedback['needs_follow_up']),
            'product_name' => (string) ($feedback['product_name'] ?? ''),
            'related_order_code' => (string) ($feedback['order_code'] ?? ''),
        ];

        $result = $bridge->chat($channel, $sessionId, (string) ($feedback['message'] ?? ''), $context, $this->buildActorMeta($actor, 'public_storefront', [
            'guard_warnings' => $guard->buildCapabilityWarnings($channel === 'admin'
                ? ['dashboard_summary', 'pending_orders']
                : ['faq_support', 'product_advisor']),
            'interaction_type' => 'feedback_capture',
            'feedback_saved' => true,
            'feedback_code' => (string) ($feedback['feedback_code'] ?? ''),
            'feedback_type' => (string) ($feedback['feedback_type'] ?? 'general'),
            'sentiment' => (string) ($feedback['sentiment'] ?? 'neutral'),
            'severity' => (string) ($feedback['severity'] ?? 'low'),
            'needs_follow_up' => !empty($feedback['needs_follow_up']),
            'rating' => $feedback['rating'] ?? null,
            'related_order_code' => (string) ($feedback['order_code'] ?? ''),
            'original_text' => (string) ($languageAnalysis['original_text'] ?? ($feedback['message'] ?? '')),
            'normalized_text' => (string) ($languageAnalysis['normalized_text'] ?? ($feedback['message'] ?? '')),
            'intent_guess' => (string) ($languageAnalysis['intent_guess'] ?? 'feedback_capture'),
            'normalization_confidence' => (float) ($languageAnalysis['confidence'] ?? 0),
            'requires_clarification' => !empty($languageAnalysis['requires_clarification']),
            'slang_hits' => (array) ($languageAnalysis['slang_hits'] ?? []),
            'context_hint' => (string) ($languageAnalysis['context_hint'] ?? ''),
            'recent_messages' => $recentMessages,
        ]));
        $meta = $this->buildResponseMeta($result, 'bridge', false, 'real_bridge', 'ai-bridge', $actor, 'public_storefront');

        $confirmation = 'Đã lưu phản hồi của bạn với mã ' . (string) ($feedback['feedback_code'] ?? 'N/A') . '.';
        if (!empty($feedback['needs_follow_up'])) {
            $confirmation .= ' Bên mình sẽ ưu tiên xem lại trường hợp này.';
        }

        $bridgeReply = trim((string) ($result['reply'] ?? ''));
        $reply = $confirmation . ($bridgeReply !== '' ? "\n\n" . $bridgeReply : '');

        $this->respondJson([
            'success' => true,
            'message' => 'Đã lưu feedback khách hàng.',
            'data' => [
                'sessionId' => (string) ($result['session_id'] ?? $sessionId),
                'reply' => $reply,
                'feedbackSaved' => true,
                'feedback_saved' => true,
                'feedbackId' => (int) ($feedback['id'] ?? 0),
                'feedbackCode' => (string) ($feedback['feedback_code'] ?? ''),
                'feedbackType' => (string) ($feedback['feedback_type'] ?? 'general'),
                'sentiment' => (string) ($feedback['sentiment'] ?? 'neutral'),
                'severity' => (string) ($feedback['severity'] ?? 'low'),
                'needsFollowUp' => !empty($feedback['needs_follow_up']),
                'provider' => $meta['provider'],
                'isFallback' => $meta['is_fallback'],
                'is_fallback' => $meta['is_fallback'],
                'mode' => $meta['mode'],
                'source' => $meta['source'],
                'conversationMode' => $meta['conversation_mode'],
            ],
            'meta' => $meta,
            'guardrails' => $guard->getGuardRules($channel === 'admin' ? 'admin' : 'customer'),
            'csrf_token' => csrf_token(),
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

    private function ensurePostApi(): void
    {
        if (!is_post()) {
            $this->respondJson([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }
    }

    private function ensureCustomerRateLimit(): void
    {
        $result = $this->consumeRateLimit('ai-customer-chat', 'ai_customer_chat');

        if (!$result['allowed']) {
            header('Retry-After: ' . (int) ($result['retry_after'] ?? 60));
            $this->respondJson([
                'success' => false,
                'message' => 'Bạn đang gửi câu hỏi quá nhanh. Vui lòng thử lại sau ít giây.',
                'retry_after' => (int) ($result['retry_after'] ?? 60),
                'csrf_token' => csrf_token(),
            ], 429);
        }
    }

    private function buildResponseMeta(array $result, string $defaultProvider, bool $defaultFallback, string $defaultMode, string $defaultSource, array $actor = [], string $routeScope = 'public_storefront'): array
    {
        return array_merge([
            'provider' => (string) ($result['provider'] ?? $defaultProvider),
            'is_fallback' => (bool) ($result['is_fallback'] ?? $defaultFallback),
            'mode' => (string) ($result['mode'] ?? $defaultMode),
            'source' => (string) ($result['source'] ?? $defaultSource),
        ], $this->buildActorMeta($actor, $routeScope));
    }

    private function respondJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function resolvePublicChatChannel(array $actor): string
    {
        $personaService = new AiPersonaService($this->config);
        $mode = $personaService->resolveModeFromActor($actor);

        return $personaService->isBackofficeMode($mode) ? 'admin' : 'customer';
    }

    private function buildPublicChatContext(AiContextBuilder $contextBuilder, array $actor, int $productId): array
    {
        if ($this->resolvePublicChatChannel($actor) === 'admin') {
            return $contextBuilder->buildAdminContext([
                'actor_context' => $actor,
                'product_id' => $productId,
                'route_scope' => 'public_storefront',
            ]);
        }

        return $contextBuilder->buildCustomerContext([
            'product_id' => $productId,
            'actor_context' => $actor,
            'route_scope' => 'public_storefront',
        ]);
    }

    private function buildActorMeta(array $actor, string $routeScope, array $extra = []): array
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
            'conversation_mode' => (new AiPersonaService($this->config))->resolveModeFromActor($actor),
            'route_scope' => $routeScope,
        ], $extra);
    }

    private function extractRecentMessages($rawRecentMessages): array
    {
        if (is_string($rawRecentMessages) && trim($rawRecentMessages) !== '') {
            $decoded = json_decode($rawRecentMessages, true);
            $rawRecentMessages = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($rawRecentMessages)) {
            return [];
        }

        $messages = [];
        foreach (array_slice($rawRecentMessages, -6) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $role = in_array((string) ($item['role'] ?? ''), ['user', 'assistant'], true)
                ? (string) $item['role']
                : 'user';
            $text = sanitize_text((string) ($item['text'] ?? ''), 500);

            if ($text === '') {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'text' => $text,
            ];
        }

        return $messages;
    }

    private function resolveCustomerCapabilities(array $accountSupport): array
    {
        $capabilities = ['faq_support', 'product_advisor'];

        if (!empty($accountSupport['handled'])) {
            $interactionType = (string) ($accountSupport['interaction_type'] ?? '');

            if ($interactionType === 'order_lookup') {
                $capabilities[] = 'order_lookup';
            } elseif ($interactionType === 'purchase_history') {
                $capabilities[] = 'purchase_history';
            } elseif ($interactionType === 'wallet_summary') {
                $capabilities[] = 'wallet_support';
            }
        }

        return array_values(array_unique($capabilities));
    }
}
