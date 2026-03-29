<?php

namespace App\Services;

class AiBridgeService
{
    private array $config;
    private array $aiConfig;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->aiConfig = (array) ($config['ai'] ?? []);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->aiConfig['enabled'] ?? true);
    }

    public function isConfigured(): bool
    {
        return trim((string) ($this->aiConfig['bridge_url'] ?? '')) !== '';
    }

    public function chat(string $channel, string $sessionId, string $message, array $context = [], array $meta = []): array
    {
        $trimmedMessage = trim($message);
        if ($trimmedMessage === '') {
            throw new \InvalidArgumentException('Tin nhắn không được để trống.');
        }

        if (!$this->isEnabled()) {
            return $this->localFallbackResponse($channel, $sessionId, $trimmedMessage, $context, $meta, 'AI hiện đang bị tắt trong cấu hình.');
        }

        if (!$this->isConfigured()) {
            return $this->localFallbackResponse($channel, $sessionId, $trimmedMessage, $context, $meta, 'AI bridge chưa được cấu hình, đang dùng local fallback để test end-to-end.');
        }

        $payload = [
            'sessionId' => $sessionId,
            'message' => $this->buildBridgePrompt($channel, $trimmedMessage, $context, $meta),
            'reset' => !empty($meta['reset']),
        ];

        $attempts = max(1, (int) ($this->aiConfig['retry_times'] ?? 1) + 1);
        $lastErrorMessage = 'Không thể kết nối AI bridge.';

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->sendJsonRequest('POST', (string) $this->aiConfig['bridge_url'], $payload);
                return $this->normalizeBridgeResponse($channel, $sessionId, $response);
            } catch (\Throwable $exception) {
                $lastErrorMessage = $exception->getMessage();

                if ($attempt >= $attempts) {
                    break;
                }

                usleep(250000 * $attempt);
            }
        }

        if ((bool) ($this->aiConfig['allow_local_fallback'] ?? true)) {
            return $this->localFallbackResponse($channel, $sessionId, $trimmedMessage, $context, $meta, 'AI bridge lỗi: ' . $lastErrorMessage);
        }

        throw new \RuntimeException($lastErrorMessage);
    }

    public function resetSession(string $sessionId): array
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            throw new \InvalidArgumentException('sessionId không hợp lệ.');
        }

        if (!$this->isConfigured()) {
            return [
                'success' => true,
                'session_id' => $sessionId,
                'provider' => 'local-fallback',
                'is_fallback' => true,
                'mode' => 'fallback',
                'source' => 'local-fallback',
                'message' => 'Đã reset local fallback session.',
            ];
        }

        $baseUrl = rtrim((string) $this->aiConfig['bridge_url'], '/');

        try {
            $response = $this->sendJsonRequest('DELETE', $baseUrl . '/' . rawurlencode($sessionId));

            return [
                'success' => (bool) ($response['success'] ?? true),
                'session_id' => (string) ($response['data']['sessionId'] ?? $sessionId),
                'provider' => $this->resolveProviderName($response),
                'is_fallback' => false,
                'mode' => 'real_bridge',
                'source' => 'ai-bridge',
                'message' => (string) ($response['message'] ?? 'Đã reset phiên chat.'),
            ];
        } catch (\Throwable $exception) {
            if ((bool) ($this->aiConfig['allow_local_fallback'] ?? true)) {
                return [
                    'success' => true,
                    'session_id' => $sessionId,
                    'provider' => 'local-fallback',
                    'is_fallback' => true,
                    'mode' => 'fallback',
                    'source' => 'local-fallback',
                    'message' => 'Bridge reset thất bại, đã reset local fallback session.',
                ];
            }

            throw $exception;
        }
    }

    private function buildBridgePrompt(string $channel, string $message, array $context, array $meta): string
    {
        $personaService = new AiPersonaService($this->config);
        $conversationMode = $this->resolveConversationMode($context, $meta, $channel);
        $identityInstruction = $personaService->buildIdentityInstruction($conversationMode);
        $naturalnessInstruction = $personaService->buildNaturalnessInstruction($conversationMode);
        $languageInstruction = $personaService->buildLanguageUnderstandingInstruction($conversationMode);
        $modeInstruction = $personaService->buildConversationModeInstruction($conversationMode, $context, $meta, $channel);
        $contextUsageInstruction = $personaService->buildContextUsageInstruction();
        $actorInstruction = $this->buildActorInstruction($channel, $context, $meta);
        $interactionInstruction = $personaService->buildInteractionInstruction($meta);
        $styleExamples = $personaService->buildStyleExamples($conversationMode, $context);
        $languageAnalysisBlock = $this->buildLanguageAnalysisBlock($context, $meta, $message);
        $contextJson = $this->buildCompactContextJson($context);
        $metaJson = $this->buildCompactMetaJson($meta);
        $prompt = <<<PROMPT
[MEOW PERSONA]
{$identityInstruction}

[VOICE & NATURALNESS]
{$naturalnessInstruction}

[LANGUAGE UNDERSTANDING]
{$languageInstruction}

[ROLE MODE]
{$modeInstruction}

[ACTOR RULES]
{$actorInstruction}

[CONTEXT & GUARDRAILS]
{$contextUsageInstruction}

[INTERACTION RULES]
{$interactionInstruction}

[STYLE EXAMPLES]
{$styleExamples}

[LANGUAGE ANALYSIS]
{$languageAnalysisBlock}

[CHANNEL]
{$channel}

[REQUEST META]
{$metaJson}

[SHOP CONTEXT]
{$contextJson}

[USER MESSAGE]
{$message}
PROMPT;

        if (strlen($prompt) > 7800) {
            return $this->buildCompactBridgePrompt($channel, $message, $context, $meta, $contextJson, $metaJson, $languageAnalysisBlock);
        }

        return $prompt;
    }

    private function buildCompactContextJson(array $context): string
    {
        $compact = [];
        $hasCustomerAccountSupport = is_array($context['customer_account_support'] ?? null);

        if (is_array($context['site'] ?? null)) {
            $compact['site'] = [
                'name' => (string) ($context['site']['name'] ?? ''),
                'currency' => (string) ($context['site']['currency'] ?? ''),
            ];
        }

        if (is_array($context['surface'] ?? null)) {
            $compact['surface'] = [
                'route_scope' => (string) ($context['surface']['route_scope'] ?? ''),
                'page_type' => (string) ($context['surface']['page_type'] ?? ''),
            ];
        }

        $compact['actor'] = [
            'auth_state' => (string) ($context['auth_state'] ?? ''),
            'actor_type' => (string) ($context['actor_type'] ?? ''),
            'actor_role' => (string) ($context['actor_role'] ?? ''),
            'actor_gender' => (string) ($context['actor_gender'] ?? ''),
            'actor_birth_date' => $context['actor_birth_date'] ?? null,
            'actor_age' => $context['actor_age'] ?? null,
            'safe_addressing' => (string) ($context['safe_addressing'] ?? ''),
        ];

        if (is_array($context['backoffice_scope'] ?? null)) {
            $compact['backoffice_scope'] = [
                'scope_key' => (string) ($context['backoffice_scope']['scope_key'] ?? ''),
                'label' => (string) ($context['backoffice_scope']['label'] ?? ''),
                'can_view_products' => !empty($context['backoffice_scope']['can_view_products']),
                'can_view_orders' => !empty($context['backoffice_scope']['can_view_orders']),
                'can_view_coupons' => !empty($context['backoffice_scope']['can_view_coupons']),
                'can_view_feedback' => !empty($context['backoffice_scope']['can_view_feedback']),
                'can_view_finance' => !empty($context['backoffice_scope']['can_view_finance']),
                'can_view_users' => !empty($context['backoffice_scope']['can_view_users']),
                'can_view_rank' => !empty($context['backoffice_scope']['can_view_rank']),
            ];
        }

        if (!$hasCustomerAccountSupport && !empty($context['categories']) && is_array($context['categories'])) {
            $compact['categories'] = array_values(array_filter(array_map(
                static fn(array $row): string => trim((string) ($row['name'] ?? '')),
                array_slice($context['categories'], 0, 6)
            )));
        }

        if (is_array($context['current_product'] ?? null)) {
            $compact['current_product'] = $this->compactProduct((array) $context['current_product'], true);
        }

        if (!$hasCustomerAccountSupport && !empty($context['featured_products']) && is_array($context['featured_products'])) {
            $compact['featured_products'] = array_values(array_filter(array_map(
                fn(array $row): array => $this->compactProduct($row),
                array_slice($context['featured_products'], 0, 3)
            )));
        }

        if (!$hasCustomerAccountSupport && !empty($context['faq']) && is_array($context['faq'])) {
            $compact['faq'] = array_values(array_filter(array_map(
                fn(array $row): array => [
                    'q' => $this->compactText((string) ($row['question'] ?? ''), 90),
                    'a' => $this->compactText((string) ($row['answer'] ?? ''), 140),
                ],
                array_slice($context['faq'], 0, 2)
            )));
        }

        if (!empty($context['recent_orders']) && is_array($context['recent_orders'])) {
            $compact['recent_orders'] = array_values(array_filter(array_map(
                fn(array $row): array => $this->compactOrder($row),
                array_slice($context['recent_orders'], 0, 3)
            )));
        }

        if (is_array($context['account_profile'] ?? null)) {
            $compact['account_profile'] = [
                'full_name' => (string) ($context['account_profile']['full_name'] ?? ''),
                'email' => (string) ($context['account_profile']['email'] ?? ''),
                'gender' => (string) ($context['account_profile']['gender'] ?? ''),
                'birth_date' => $context['account_profile']['birth_date'] ?? null,
                'age' => $context['account_profile']['age'] ?? null,
                'wallet_balance' => (float) ($context['account_profile']['wallet_balance'] ?? 0),
            ];
        }

        if (is_array($context['wallet_summary'] ?? null)) {
            $compact['wallet_summary'] = [
                'current_balance' => (float) ($context['wallet_summary']['current_balance'] ?? 0),
                'total_deposit' => (float) ($context['wallet_summary']['total_deposit'] ?? 0),
                'display_spent' => (float) ($context['wallet_summary']['display_spent'] ?? 0),
                'latest_activity_at' => $context['wallet_summary']['latest_activity_at'] ?? null,
            ];
        }

        if (!empty($context['recent_wallet_transactions']) && is_array($context['recent_wallet_transactions'])) {
            $compact['recent_wallet_transactions'] = array_values(array_filter(array_map(
                static fn(array $row): array => [
                    'transaction_type' => (string) ($row['transaction_type'] ?? ''),
                    'direction' => (string) ($row['direction'] ?? ''),
                    'amount' => (float) ($row['amount'] ?? 0),
                    'status' => (string) ($row['status'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ],
                array_slice($context['recent_wallet_transactions'], 0, 3)
            )));
        }

        if (is_array($context['customer_account_support'] ?? null)) {
            $compact['customer_account_support'] = [
                'lookup_type' => (string) ($context['customer_account_support']['lookup_type'] ?? ''),
                'lookup_state' => (string) ($context['customer_account_support']['lookup_state'] ?? ''),
                'verification_state' => (string) ($context['customer_account_support']['verification_state'] ?? ''),
                'lookup_mode' => (string) ($context['customer_account_support']['lookup_mode'] ?? ''),
                'required_fields' => array_values(array_map('strval', (array) ($context['customer_account_support']['required_fields'] ?? []))),
                'message' => $this->compactText((string) ($context['customer_account_support']['message'] ?? ''), 220),
            ];

            if (is_array($context['customer_account_support']['order'] ?? null)) {
                $compact['customer_account_support']['order'] = $this->compactOrder((array) $context['customer_account_support']['order']);
            }

            if (!empty($context['customer_account_support']['orders']) && is_array($context['customer_account_support']['orders'])) {
                $compact['customer_account_support']['orders'] = array_values(array_filter(array_map(
                    fn(array $row): array => $this->compactOrder($row),
                    array_slice($context['customer_account_support']['orders'], 0, 4)
                )));
            }

            if (is_array($context['customer_account_support']['wallet'] ?? null)) {
                $compact['customer_account_support']['wallet'] = [
                    'current_balance' => (float) ($context['customer_account_support']['wallet']['current_balance'] ?? 0),
                    'total_deposit' => (float) ($context['customer_account_support']['wallet']['total_deposit'] ?? 0),
                    'display_spent' => (float) ($context['customer_account_support']['wallet']['display_spent'] ?? 0),
                    'latest_activity_at' => $context['customer_account_support']['wallet']['latest_activity_at'] ?? null,
                ];
            }
        }

        if (is_array($context['feedback'] ?? null)) {
            $compact['feedback'] = [
                'feedback_code' => (string) ($context['feedback']['feedback_code'] ?? ''),
                'feedback_type' => (string) ($context['feedback']['feedback_type'] ?? ''),
                'sentiment' => (string) ($context['feedback']['sentiment'] ?? ''),
                'severity' => (string) ($context['feedback']['severity'] ?? ''),
                'needs_follow_up' => !empty($context['feedback']['needs_follow_up']),
                'product_name' => (string) ($context['feedback']['product_name'] ?? ''),
                'related_order_code' => (string) ($context['feedback']['related_order_code'] ?? ''),
            ];
        }

        if (is_array($context['stats'] ?? null)) {
            $compact['stats'] = [
                'products' => (int) ($context['stats']['products'] ?? 0),
                'orders' => (int) ($context['stats']['orders'] ?? 0),
                'pending_orders' => (int) ($context['stats']['pending_orders'] ?? 0),
                'today_orders' => (int) ($context['stats']['today_orders'] ?? 0),
                'active_coupons' => (int) ($context['stats']['active_coupons'] ?? 0),
            ];

            if (array_key_exists('users', $context['stats'])) {
                $compact['stats']['users'] = (int) ($context['stats']['users'] ?? 0);
            }

            if (array_key_exists('new_users', $context['stats'])) {
                $compact['stats']['new_users'] = (int) ($context['stats']['new_users'] ?? 0);
            }

            if (array_key_exists('revenue', $context['stats'])) {
                $compact['stats']['revenue'] = (float) ($context['stats']['revenue'] ?? 0);
            }

            if (array_key_exists('today_revenue', $context['stats'])) {
                $compact['stats']['today_revenue'] = (float) ($context['stats']['today_revenue'] ?? 0);
            }

            if (array_key_exists('new_feedback', $context['stats'])) {
                $compact['stats']['new_feedback'] = (int) ($context['stats']['new_feedback'] ?? 0);
            }

            if (array_key_exists('expiring_coupons', $context['stats'])) {
                $compact['stats']['expiring_coupons'] = (int) ($context['stats']['expiring_coupons'] ?? 0);
            }
        }

        if (!empty($context['top_products']) && is_array($context['top_products'])) {
            $compact['top_products'] = array_values(array_filter(array_map(
                static fn(array $row): array => [
                    'name' => (string) ($row['name'] ?? ''),
                    'sold_qty' => (int) ($row['sold_qty'] ?? 0),
                    'sold_revenue' => (float) ($row['sold_revenue'] ?? 0),
                ],
                array_slice($context['top_products'], 0, 3)
            )));
        }

        if (!empty($context['latest_orders']) && is_array($context['latest_orders'])) {
            $compact['latest_orders'] = array_values(array_filter(array_map(
                fn(array $row): array => $this->compactOrder($row),
                array_slice($context['latest_orders'], 0, 3)
            )));
        }

        if (!empty($context['order_status']) && is_array($context['order_status'])) {
            $compact['order_status'] = array_slice($context['order_status'], 0, 6);
        }

        if (!empty($context['revenue_by_month']) && is_array($context['revenue_by_month'])) {
            $compact['revenue_by_month'] = array_slice($context['revenue_by_month'], -3);
        }

        if (is_array($context['coupon_summary'] ?? null) && $context['coupon_summary'] !== []) {
            $compact['coupon_summary'] = [
                'total_coupons' => (int) ($context['coupon_summary']['total_coupons'] ?? 0),
                'active_coupons' => (int) ($context['coupon_summary']['active_coupons'] ?? 0),
                'inactive_coupons' => (int) ($context['coupon_summary']['inactive_coupons'] ?? 0),
                'expiring_soon' => (int) ($context['coupon_summary']['expiring_soon'] ?? 0),
            ];
        }

        if (!empty($context['latest_coupons']) && is_array($context['latest_coupons'])) {
            $compact['latest_coupons'] = array_values(array_filter(array_map(
                static fn(array $row): array => [
                    'code' => (string) ($row['code'] ?? ''),
                    'discount_percent' => (int) ($row['discount_percent'] ?? 0),
                    'status' => (string) ($row['status'] ?? ''),
                    'expires_at' => $row['expires_at'] ?? null,
                ],
                array_slice($context['latest_coupons'], 0, 3)
            )));
        }

        if (is_array($context['sales_recommendations'] ?? null) && $context['sales_recommendations'] !== []) {
            $compact['sales_recommendations'] = [
                'advice_scope' => (string) ($context['sales_recommendations']['advice_scope'] ?? ''),
                'executive_summary' => $this->compactText((string) ($context['sales_recommendations']['executive_summary'] ?? ''), 180),
                'cannot_confirm' => array_slice(array_map('strval', (array) ($context['sales_recommendations']['coverage']['cannot_confirm'] ?? [])), 0, 3),
                'missing_fields' => array_slice(array_map('strval', (array) ($context['sales_recommendations']['data_gaps']['missing_fields'] ?? [])), 0, 5),
                'push' => array_values(array_map(fn(array $row): string => $this->compactText(
                    (string) ($row['product_name'] ?? '') . ' | ' . (string) ($row['confidence'] ?? '') . ' | ' . (string) ($row['next_action'] ?? ''),
                    120
                ), array_slice((array) ($context['sales_recommendations']['recommendations']['push'] ?? []), 0, 2))),
                'homepage' => array_values(array_map(fn(array $row): string => $this->compactText(
                    (string) ($row['slot'] ?? '') . ' | ' . (string) ($row['product_name'] ?? ''),
                    100
                ), array_slice((array) ($context['sales_recommendations']['recommendations']['homepage'] ?? []), 0, 2))),
                'upsell' => array_values(array_map(fn(array $row): string => $this->compactText(
                    (string) ($row['from_product_name'] ?? '') . ' -> ' . (string) ($row['to_product_name'] ?? ''),
                    90
                ), array_slice((array) ($context['sales_recommendations']['recommendations']['upsell'] ?? []), 0, 2))),
                'promo_actions' => array_values(array_map(fn(array $row): string => $this->compactText(
                    (string) ($row['next_action'] ?? $row['reason'] ?? ''),
                    130
                ), array_slice(array_merge(
                    (array) ($context['sales_recommendations']['recommendations']['promotions'] ?? []),
                    (array) ($context['sales_recommendations']['recommendations']['coupon_actions'] ?? [])
                ), 0, 2))),
            ];
        }

        if (is_array($context['feedback_summary'] ?? null) && $context['feedback_summary'] !== []) {
            $compact['feedback_summary'] = [
                'total_feedback' => (int) ($context['feedback_summary']['total_feedback'] ?? 0),
                'total_new' => (int) ($context['feedback_summary']['total_new'] ?? 0),
                'total_negative' => (int) ($context['feedback_summary']['total_negative'] ?? 0),
                'total_follow_up' => (int) ($context['feedback_summary']['total_follow_up'] ?? 0),
                'latest_feedback_at' => $context['feedback_summary']['latest_feedback_at'] ?? null,
            ];
        }

        if (!empty($context['latest_feedback']) && is_array($context['latest_feedback'])) {
            $compact['latest_feedback'] = array_values(array_filter(array_map(
                static fn(array $row): array => [
                    'feedback_code' => (string) ($row['feedback_code'] ?? ''),
                    'feedback_type' => (string) ($row['feedback_type'] ?? ''),
                    'sentiment' => (string) ($row['sentiment'] ?? ''),
                    'severity' => (string) ($row['severity'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'product_name' => (string) ($row['product_name'] ?? ''),
                    'order_code' => (string) ($row['order_code'] ?? ''),
                ],
                array_slice($context['latest_feedback'], 0, 3)
            )));
        }

        if (is_array($context['data_freshness'] ?? null) && $context['data_freshness'] !== []) {
            $compact['data_freshness'] = [
                'fingerprint' => (string) ($context['data_freshness']['fingerprint'] ?? ''),
                'cache_hit' => !empty($context['data_freshness']['cache']['hit']),
                'cache_age_seconds' => (int) ($context['data_freshness']['cache']['age_seconds'] ?? 0),
                'ttl_seconds' => (int) ($context['data_freshness']['cache']['ttl_seconds'] ?? 0),
            ];
        }

        return (string) json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function buildCompactBridgePrompt(string $channel, string $message, array $context, array $meta, string $contextJson, string $metaJson, string $languageAnalysisBlock): string
    {
        $conversationMode = $this->resolveConversationMode($context, $meta, $channel);
        $modeHint = $this->compactModeHint($conversationMode, $context, $meta, $channel);
        $interactionHint = $this->compactInteractionHint($meta);

        return <<<PROMPT
Bạn là Meow, persona nền Nguyễn Thanh Hà, đang hỗ trợ cho webshop ZenoxDigital.
Giọng điệu phải tự nhiên, ngắn gọn, đúng vai và không máy móc. Hãy ưu tiên hiểu slang/không dấu từ LANGUAGE ANALYSIS trước khi kết luận là không hiểu.
Chỉ dùng dữ liệu thật có trong context/meta. Không bịa đơn hàng, ví, lịch sử mua, doanh thu hay dữ liệu nội bộ.
Nếu chưa đủ dữ liệu backend để xưng hô chắc chắn, mặc định dùng "bạn" và tự xưng "mình" hoặc "Meow".
Không dùng hành vi Zalo-specific như reaction, sticker, quote, undo, tag người dùng hay group chat.
{$modeHint}
{$interactionHint}

[LANGUAGE ANALYSIS]
{$languageAnalysisBlock}

[REQUEST META]
{$metaJson}

[SHOP CONTEXT]
{$contextJson}

[USER MESSAGE]
{$message}
PROMPT;
    }

    private function buildCompactMetaJson(array $meta): string
    {
        $compact = [
            'auth_state' => (string) ($meta['auth_state'] ?? $meta['auth'] ?? ''),
            'actor_type' => (string) ($meta['actor_type'] ?? ''),
            'actor_role' => (string) ($meta['actor_role'] ?? ''),
            'role_group' => (string) ($meta['role_group'] ?? ''),
            'actor_name' => (string) ($meta['actor_name'] ?? ''),
            'actor_gender' => (string) ($meta['actor_gender'] ?? ''),
            'actor_birth_date' => $meta['actor_birth_date'] ?? null,
            'actor_age' => $meta['actor_age'] ?? null,
            'safe_addressing' => (string) ($meta['safe_addressing'] ?? ''),
            'actor_id' => $meta['actor_id'] ?? null,
            'conversation_mode' => (string) ($meta['conversation_mode'] ?? ''),
            'route_scope' => (string) ($meta['route_scope'] ?? ''),
            'interaction_type' => (string) ($meta['interaction_type'] ?? 'chat'),
            'feedback_saved' => !empty($meta['feedback_saved']),
            'feedback_code' => (string) ($meta['feedback_code'] ?? ''),
            'feedback_type' => (string) ($meta['feedback_type'] ?? ''),
            'sentiment' => (string) ($meta['sentiment'] ?? ''),
            'severity' => (string) ($meta['severity'] ?? ''),
            'needs_follow_up' => !empty($meta['needs_follow_up']),
            'lookup_type' => (string) ($meta['lookup_type'] ?? ''),
            'lookup_state' => (string) ($meta['lookup_state'] ?? ''),
            'verification_state' => (string) ($meta['verification_state'] ?? ''),
            'lookup_verified' => !empty($meta['lookup_verified']),
            'structured_data_present' => !empty($meta['structured_data_present']),
            'guard_warnings' => array_slice(array_values(array_map('strval', (array) ($meta['guard_warnings'] ?? []))), 0, 6),
        ];

        if (is_array($meta['backoffice_scope'] ?? null)) {
            $compact['backoffice_scope'] = [
                'scope_key' => (string) ($meta['backoffice_scope']['scope_key'] ?? ''),
                'label' => (string) ($meta['backoffice_scope']['label'] ?? ''),
                'can_view_products' => !empty($meta['backoffice_scope']['can_view_products']),
                'can_view_orders' => !empty($meta['backoffice_scope']['can_view_orders']),
                'can_view_coupons' => !empty($meta['backoffice_scope']['can_view_coupons']),
                'can_view_feedback' => !empty($meta['backoffice_scope']['can_view_feedback']),
                'can_view_finance' => !empty($meta['backoffice_scope']['can_view_finance']),
                'can_view_users' => !empty($meta['backoffice_scope']['can_view_users']),
                'can_view_rank' => !empty($meta['backoffice_scope']['can_view_rank']),
            ];
        }

        return (string) json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function buildLanguageAnalysisBlock(array $context, array $meta, string $message): string
    {
        $analysis = is_array($context['language_analysis'] ?? null) ? (array) $context['language_analysis'] : [];
        $recentMessages = $analysis['recent_messages'] ?? $context['recent_messages'] ?? $meta['recent_messages'] ?? [];

        if (!is_array($recentMessages)) {
            $recentMessages = [];
        }

        $recentMessages = array_values(array_filter(array_map(static function ($item): ?array {
            if (!is_array($item)) {
                return null;
            }

            $role = in_array((string) ($item['role'] ?? ''), ['user', 'assistant'], true)
                ? (string) $item['role']
                : 'user';
            $text = sanitize_text((string) ($item['text'] ?? ''), 140);

            if ($text === '') {
                return null;
            }

            return [
                'role' => $role,
                'text' => $text,
            ];
        }, array_slice($recentMessages, -4))));

        $payload = [
            'original_text' => (string) ($analysis['original_text'] ?? ($meta['original_text'] ?? $message)),
            'normalized_text' => (string) ($analysis['normalized_text'] ?? ($meta['normalized_text'] ?? $message)),
            'intent_guess' => (string) ($analysis['intent_guess'] ?? ($meta['intent_guess'] ?? 'unknown')),
            'confidence' => (float) ($analysis['confidence'] ?? ($meta['normalization_confidence'] ?? 0)),
            'requires_clarification' => (bool) ($analysis['requires_clarification'] ?? ($meta['requires_clarification'] ?? false)),
            'slang_hits' => array_values(array_map('strval', (array) ($analysis['slang_hits'] ?? ($meta['slang_hits'] ?? [])))),
            'context_hint' => (string) ($analysis['context_hint'] ?? ($meta['context_hint'] ?? '')),
            'recent_messages' => $recentMessages,
        ];

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function compactModeHint(string $conversationMode, array $context, array $meta, string $channel): string
    {
        $personaService = new AiPersonaService($this->config);
        $mode = $personaService->normalizeConversationMode($conversationMode, $channel === 'admin' ? 'admin_copilot' : 'customer_support');
        $actor = is_array($context['actor'] ?? null) ? (array) $context['actor'] : [];
        $safeAddressing = trim((string) ($actor['safe_addressing'] ?? $context['safe_addressing'] ?? 'bạn'));
        $actorName = trim((string) ($context['actor_name'] ?? $actor['actor_name'] ?? $meta['actor_name'] ?? ''));
        $backofficeScope = is_array($context['backoffice_scope'] ?? null)
            ? (array) $context['backoffice_scope']
            : (is_array($meta['backoffice_scope'] ?? null) ? (array) $meta['backoffice_scope'] : []);
        $allowedAreas = implode(', ', array_filter([
            !empty($backofficeScope['can_view_orders']) ? 'đơn hàng' : null,
            !empty($backofficeScope['can_view_products']) ? 'sản phẩm' : null,
            !empty($backofficeScope['can_view_coupons']) ? 'coupon' : null,
            !empty($backofficeScope['can_view_feedback']) ? 'feedback' : null,
            !empty($backofficeScope['can_view_finance']) ? 'doanh thu' : null,
            !empty($backofficeScope['can_view_users']) ? 'người dùng' : null,
            !empty($backofficeScope['can_view_rank']) ? 'rank' : null,
        ]));

        return match ($mode) {
            'admin_copilot' => 'Mode hiện tại: admin copilot. Nói như trợ lý quản trị nội bộ, không dùng giọng CSKH bán hàng. Nếu cần xưng hô, ưu tiên "' . ($safeAddressing !== '' ? $safeAddressing : 'bạn') . '" hoặc tên tin cậy "' . ($actorName !== '' ? $actorName : 'không có') . '". Chỉ dùng anh/chị/em khi backend đã xác thực identity đủ chắc. Phạm vi dữ liệu hiện có: ' . ($allowedAreas !== '' ? $allowedAreas : 'chưa có') . '.',
            'staff_support' => 'Mode hiện tại: staff support. Nói như trợ lý vận hành nội bộ, không tự mở rộng quyền ngoài context. Staff chỉ được dùng các vùng dữ liệu đã cấp: ' . ($allowedAreas !== '' ? $allowedAreas : 'chưa có') . '. Nếu bị hỏi vượt quyền thì nói rõ là ngoài phạm vi staff hiện tại.',
            'management_support' => 'Mode hiện tại: management support. Tóm ý gọn, ưu tiên tín hiệu quan trọng và bước tiếp theo. Phạm vi dữ liệu hiện có: ' . ($allowedAreas !== '' ? $allowedAreas : 'chưa có') . '.',
            default => 'Mode hiện tại: customer support. Với guest hoặc profile chưa đủ dữ liệu giới tính/ngày sinh, mặc định dùng "bạn/mình" và không giả vờ biết tên hay đơn. Với customer đã đăng nhập chỉ được dùng tên tin cậy "' . ($actorName !== '' ? $actorName : 'không có') . '" hoặc cách xưng hô an toàn "' . ($safeAddressing !== '' ? $safeAddressing : 'bạn') . '". Chỉ dùng anh/chị/em khi backend đã xác thực identity đủ chắc. Tuyệt đối không tự bịa tên khác hoặc đoán giới tính từ tên/email/avatar.',
        };
    }

    private function compactInteractionHint(array $meta): string
    {
        $interactionType = (string) ($meta['interaction_type'] ?? 'chat');
        $lookupState = (string) ($meta['lookup_state'] ?? '');
        $verificationState = (string) ($meta['verification_state'] ?? '');
        $structuredDataPresent = !empty($meta['structured_data_present']);

        if (!in_array($interactionType, ['order_lookup', 'purchase_history', 'wallet_summary'], true)) {
            return 'Nếu cần hỏi thêm, chỉ hỏi 1 câu follow-up ngắn và đúng trọng tâm.';
        }

        $instructions = [
            'Tình huống hiện tại là tra cứu đơn/tài khoản bằng dữ liệu thật từ backend.',
        ];

        if ($verificationState === 'required') {
            $instructions[] = 'Người dùng chưa cung cấp đủ xác minh. Hãy yêu cầu mã đơn + email đặt hàng hoặc gợi ý đăng nhập, không hé lộ đơn có tồn tại hay không.';
        } elseif ($verificationState === 'login_required') {
            $instructions[] = 'Đây là dữ liệu cá nhân chỉ xem sau khi đăng nhập. Hãy nói ngắn gọn rằng cần đăng nhập để tra cứu đúng tài khoản.';
        } elseif ($verificationState === 'failed') {
            $instructions[] = 'Xác minh chưa đạt. Hãy nói trung tính rằng chưa thể xác minh với thông tin hiện tại, không tiết lộ dữ liệu của người khác.';
        } else {
            $instructions[] = 'Nếu đã có dữ liệu xác minh, hãy giải thích ngắn về trạng thái đơn hoặc ví và gợi ý bước tiếp theo.';
        }

        if ($structuredDataPresent) {
            $instructions[] = 'Backend có thể đã hiển thị sẵn block dữ liệu cấu trúc cho người dùng. Đừng lặp lại nguyên block; chỉ giải thích ngắn và follow-up nếu cần.';
        }

        if ($lookupState !== '') {
            $instructions[] = 'Lookup state: ' . $lookupState . '.';
        }

        return implode(' ', $instructions);
    }

    private function compactProduct(array $row, bool $withDescription = false): array
    {
        $compact = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => $this->compactText((string) ($row['name'] ?? ''), 80),
            'price' => (float) ($row['price'] ?? 0),
            'category' => $this->compactText((string) ($row['category_name'] ?? ''), 50),
            'url' => (string) ($row['url'] ?? ''),
        ];

        if ($withDescription) {
            $compact['short_description'] = $this->compactText((string) ($row['short_description'] ?? ''), 180);
            $compact['specs'] = $this->compactText((string) ($row['specs'] ?? ''), 220);
        }

        return $compact;
    }

    private function compactOrder(array $row): array
    {
        return [
            'order_code' => (string) ($row['order_code'] ?? ''),
            'total_amount' => (float) ($row['total_amount'] ?? 0),
            'status' => (string) ($row['status'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function compactText(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '' || mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(0, $limit - 1), 'UTF-8')) . '…';
    }

    private function buildActorInstruction(string $channel, array $context, array $meta): string
    {
        $personaService = new AiPersonaService($this->config);
        $actor = is_array($context['actor'] ?? null) ? (array) $context['actor'] : [];
        $authState = (string) ($context['auth_state'] ?? $actor['auth_state'] ?? $meta['auth_state'] ?? 'unknown');
        $actorType = (string) ($context['actor_type'] ?? $actor['actor_type'] ?? $meta['actor_type'] ?? 'unknown');
        $actorRole = (string) ($context['actor_role'] ?? $actor['actor_role'] ?? $actor['role_name'] ?? $meta['actor_role'] ?? 'unknown');
        $roleGroup = (string) ($context['role_group'] ?? $actor['role_group'] ?? $meta['role_group'] ?? 'safe');
        $actorName = trim((string) ($context['actor_name'] ?? $actor['actor_name'] ?? $meta['actor_name'] ?? ''));
        $actorGender = normalize_user_gender((string) ($context['actor_gender'] ?? $actor['actor_gender'] ?? $meta['actor_gender'] ?? 'unknown'));
        $actorBirthDate = normalize_birth_date((string) ($context['actor_birth_date'] ?? $actor['actor_birth_date'] ?? $meta['actor_birth_date'] ?? ''));
        $actorAgeRaw = $context['actor_age'] ?? $actor['actor_age'] ?? $meta['actor_age'] ?? null;
        $actorAge = is_numeric($actorAgeRaw) ? (int) $actorAgeRaw : null;
        if ($actorAge !== null && ($actorAge < 0 || $actorAge > 120)) {
            $actorAge = null;
        }
        $safeAddressing = trim((string) ($actor['safe_addressing'] ?? 'bạn'));
        $routeScope = trim((string) ($meta['route_scope'] ?? ($channel === 'admin' ? 'admin_panel' : 'public_storefront')));
        $conversationMode = $personaService->resolveModeFromContext($context, $meta, $channel === 'admin' ? 'admin_copilot' : 'customer_support');
        $trustsHonorific = in_array($safeAddressing, ['anh', 'chị', 'em'], true)
            && ($actorGender !== 'unknown' || $actorBirthDate !== null || $actorAge !== null);
        $backofficeScope = is_array($context['backoffice_scope'] ?? null)
            ? (array) $context['backoffice_scope']
            : (is_array($meta['backoffice_scope'] ?? null) ? (array) $meta['backoffice_scope'] : []);
        $allowedAreas = implode(', ', array_filter([
            !empty($backofficeScope['can_view_orders']) ? 'đơn hàng' : null,
            !empty($backofficeScope['can_view_products']) ? 'sản phẩm' : null,
            !empty($backofficeScope['can_view_coupons']) ? 'coupon' : null,
            !empty($backofficeScope['can_view_feedback']) ? 'feedback' : null,
            !empty($backofficeScope['can_view_finance']) ? 'doanh thu' : null,
            !empty($backofficeScope['can_view_users']) ? 'người dùng' : null,
            !empty($backofficeScope['can_view_rank']) ? 'rank' : null,
        ]));

        if ($conversationMode === 'admin_copilot') {
            return 'Danh tính người dùng đang map sang admin copilot. Chỉ dùng dữ liệu backend đã xác thực. '
                . 'Nếu route_scope là `' . $routeScope . '`, vẫn giữ giọng quản trị nội bộ. '
                . 'Nếu cần xưng hô, ưu tiên "' . ($safeAddressing !== '' ? $safeAddressing : 'bạn') . '" hoặc actor_name đáng tin từ context. '
                . 'Nếu backend chưa đủ dữ liệu giới tính/ngày sinh thì cứ dùng "bạn/mình", không tự đổi sang anh/chị/em. '
                . 'Tự xưng bằng "Meow" hoặc "mình", không tự chuyển sang cặp "anh/em".'
                . ($allowedAreas !== '' ? ' Các vùng dữ liệu đang được cấp: ' . $allowedAreas . '.' : '');
        }

        if ($conversationMode === 'staff_support') {
            return 'Danh tính người dùng đang map sang staff support. Giữ ngữ cảnh backoffice, hỗ trợ tác vụ vận hành, không tự gán quyền admin tuyệt đối và không chuyển sang văn phong CSKH bán hàng.'
                . ' Route scope hiện tại: ' . $routeScope . '. Ưu tiên tự xưng "Meow" hoặc "mình", và chỉ dùng anh/chị/em khi backend đã xác thực chắc chắn.'
                . ($allowedAreas !== '' ? ' Phạm vi hiện tại của staff chỉ gồm: ' . $allowedAreas . '.' : ' Nếu context chưa cấp dữ liệu nào thì nói rõ là ngoài phạm vi staff hiện tại.');
        }

        if ($conversationMode === 'management_support') {
            return 'Danh tính người dùng đang map sang management support cho role `' . $actorRole . '`. Giữ giọng trợ lý điều hành, không mở rộng quyền ngoài context và không chuyển sang văn phong CSKH.'
                . ' Route scope hiện tại: ' . $routeScope . '. Ưu tiên tự xưng "Meow" hoặc "mình", và nếu identity chưa đủ thì dùng "bạn".'
                . ($allowedAreas !== '' ? ' Dữ liệu đang được cấp cho role này: ' . $allowedAreas . '.' : '');
        }

        return match ($actorType) {
            'guest' => 'Người dùng hiện tại là khách ghé shop chưa đăng nhập. Hãy xưng hô trung tính bằng "bạn", tự xưng "mình" hoặc "Meow". Tuyệt đối không được dùng bất kỳ tên riêng nào cho người dùng này, không được giả vờ biết tên, lịch sử đơn hàng hay dữ liệu tài khoản. Nếu họ hỏi tra cứu đơn hoặc tài khoản cá nhân, hãy hướng dẫn đăng nhập hoặc cung cấp bước xác minh an toàn. Không dùng "quý khách" hoặc anh/chị trừ khi backend đã có dữ liệu tin cậy, mà ở guest thì không có.',
            'customer' => 'Người dùng hiện tại là khách đã đăng nhập. Tên hiển thị tin cậy duy nhất từ backend là "' . ($actorName !== '' ? $actorName : 'không có') . '". Dữ liệu nhân xưng backend hiện có: gender=' . $actorGender . ', birth_date=' . ($actorBirthDate ?? 'null') . ', age=' . ($actorAge !== null ? (string) $actorAge : 'null') . '. Cách xưng hô an toàn hiện tại là "' . ($safeAddressing !== '' ? $safeAddressing : 'bạn') . '". Nếu dữ liệu nhân xưng chưa đủ hoặc safe_addressing là "bạn", hãy giữ cặp "bạn/mình". Chỉ dùng anh/chị/em khi backend đã xác thực đủ để safe_addressing phản ánh chắc chắn. Tuyệt đối không được hardcode, thay thế, hay bịa ra tên mẫu khác như Hùng/Lan/An; không đoán giới tính từ tên/email/avatar; và không tự bịa lịch sử đơn hàng nếu context không có.',
            'admin' => 'Người dùng đã đăng nhập với vai trò admin nhưng conversation_mode hiện không ở chế độ quản trị. Hãy dùng cách nói trung tính, không bịa dữ liệu nội bộ và không chuyển sang giọng CSKH quá bán hàng.',
            'staff' => 'Người dùng đã đăng nhập với vai trò staff/backoffice nhưng conversation_mode hiện không ở chế độ quản trị. Hãy dùng cách nói trung tính, không giả định quyền quản trị đầy đủ.',
            'management' => 'Người dùng đã đăng nhập với role quản trị `' . $actorRole . '` nhưng conversation_mode hiện không ở chế độ quản trị. Hãy dùng cách nói trung tính, không giả định thêm quyền ngoài context.',
            default => 'Chưa xác định chắc chắn danh tính người dùng. Hãy fallback về ngữ cảnh an toàn, không xưng tên, không dùng tên riêng và không giả định dữ liệu cá nhân.',
        } . ($authState !== '' ? ' Trạng thái xác thực hiện tại: ' . $authState . '.' : '') . ($trustsHonorific ? ' Có thể dùng safe_addressing hiện tại vì backend đã cấp dữ liệu nhân xưng đủ tin cậy.' : ' Nếu còn thiếu độ tin cậy nhân xưng thì cứ giữ "bạn/mình".') . ($roleGroup !== '' ? ' Role group hiện tại: ' . $roleGroup . '.' : '');
    }

    private function normalizeBridgeResponse(string $channel, string $sessionId, array $response): array
    {
        $success = (bool) ($response['success'] ?? false);
        $reply = trim((string) ($response['data']['reply'] ?? $response['reply'] ?? ''));

        if (!$success || $reply === '') {
            throw new \RuntimeException((string) ($response['error'] ?? $response['message'] ?? 'AI bridge trả về dữ liệu không hợp lệ.'));
        }

        return $this->buildResultEnvelope(
            $channel,
            (string) ($response['data']['sessionId'] ?? $sessionId),
            $reply,
            $this->resolveProviderName($response),
            false,
            'real_bridge',
            'ai-bridge',
            $response
        );
    }

    private function localFallbackResponse(string $channel, string $sessionId, string $message, array $context, array $meta, string $reason): array
    {
        $personaService = new AiPersonaService($this->config);

        if ($personaService->isBackofficeMode($this->resolveConversationMode($context, $meta, $channel)) || $channel === 'admin') {
            return $this->adminFallbackResponse($sessionId, $message, $context, $reason);
        }

        if ($channel === 'customer') {
            return $this->customerFallbackResponse($sessionId, $message, $context, $reason);
        }

        $appName = (string) ($this->config['app']['name'] ?? 'ZenoxDigital');
        $contextHints = [];

        if ($channel === 'admin') {
            $stats = (array) ($context['stats'] ?? []);
            $contextHints[] = 'KPI hiện có: ' . implode(', ', array_filter([
                isset($stats['products']) ? 'san pham ' . (int) $stats['products'] : null,
                isset($stats['orders']) ? 'don hang ' . (int) $stats['orders'] : null,
                isset($stats['pending_orders']) ? 'cho xu ly ' . (int) $stats['pending_orders'] : null,
            ]));
        } else {
            $featured = array_slice((array) ($context['featured_products'] ?? []), 0, 2);
            if ($featured) {
                $contextHints[] = 'San pham goi y co san: ' . implode(', ', array_map(
                    static fn(array $item): string => (string) ($item['name'] ?? 'N/A'),
                    $featured
                ));
            }
        }

        $contextHintText = $contextHints ? "\n" . implode("\n", $contextHints) : '';
        $reply = '[Local Fallback] ' . $reason
            . "\nSession: {$sessionId}"
            . "\nChannel: {$channel}"
            . "\nProject: {$appName}"
            . $contextHintText
            . "\nTin nhắn nhận được: {$message}";

        if (!empty($meta['guard_warnings']) && is_array($meta['guard_warnings'])) {
            $reply .= "\nCanh bao guardrail: " . implode(' | ', array_map('strval', $meta['guard_warnings']));
        }

        return $this->buildResultEnvelope(
            $channel,
            $sessionId,
            $reply,
            'local-fallback',
            true,
            'fallback',
            'local-fallback',
            [
                'reason' => $reason,
            ]
        );
    }

    private function adminFallbackResponse(string $sessionId, string $message, array $context, string $reason): array
    {
        $actor = is_array($context['actor'] ?? null) ? (array) $context['actor'] : [];
        $personaService = new AiPersonaService($this->config);
        $safeAddressing = trim((string) ($actor['safe_addressing'] ?? 'bạn'));
        $conversationMode = $personaService->resolveModeFromContext($context, [], 'admin_copilot');
        $stats = (array) ($context['stats'] ?? []);
        $salesRecommendations = is_array($context['sales_recommendations'] ?? null) ? (array) $context['sales_recommendations'] : [];
        $highlights = array_filter([
            isset($stats['pending_orders']) ? 'đơn chờ xử lý: ' . (int) $stats['pending_orders'] : null,
            isset($stats['today_orders']) ? 'đơn hôm nay: ' . (int) $stats['today_orders'] : null,
            isset($stats['active_coupons']) ? 'coupon đang hoạt động: ' . (int) $stats['active_coupons'] : null,
        ]);

        $modeLabel = match ($conversationMode) {
            'staff_support' => 'hỗ trợ vận hành',
            'management_support' => 'hỗ trợ điều hành',
            default => 'hỗ trợ quản trị',
        };

        $reply = $this->buildAdminSalesFallbackReply($message, $salesRecommendations);

        if ($reply === null) {
            $reply = 'Meow đang ở chế độ dự phòng kỹ thuật nên chưa gọi được AI bridge thật cho `' . $modeLabel . '`.'
                . "\nNếu cần kiểm tra nhanh, {$safeAddressing} có thể hỏi lại sau ít phút hoặc mở trực tiếp màn quản trị tương ứng.";
        }

        if ($highlights) {
            $reply .= "\nSnapshot hiện có từ context: " . implode(', ', $highlights) . '.';
        }

        if (trim($message) !== '') {
            $reply .= "\nYêu cầu vừa nhận: " . trim($message);
        }

        return $this->buildResultEnvelope(
            'admin',
            $sessionId,
            $reply,
            'local-fallback',
            true,
            'fallback',
            'local-fallback',
            [
                'reason' => $reason,
            ]
        );
    }

    private function buildAdminSalesFallbackReply(string $message, array $salesRecommendations): ?string
    {
        if ($salesRecommendations === []) {
            return null;
        }

        $messageLower = mb_strtolower(trim($message), 'UTF-8');
        $recommendations = (array) ($salesRecommendations['recommendations'] ?? []);
        $actionQueue = array_slice(array_map('strval', (array) ($salesRecommendations['action_queue'] ?? [])), 0, 3);

        if ($this->containsAny($messageLower, ['đẩy gói', 'day goi', 'homepage', 'spotlight'])) {
            $push = array_slice((array) ($recommendations['push'] ?? []), 0, 2);
            $homepage = array_slice((array) ($recommendations['homepage'] ?? []), 0, 2);
            if ($push === [] && $homepage === []) {
                return null;
            }

            $lines = [];
            foreach ($push as $item) {
                $lines[] = '- Đẩy `' . (string) ($item['product_name'] ?? '') . '`: ' . (string) ($item['reason'] ?? '');
            }
            foreach ($homepage as $item) {
                $lines[] = '- ' . (string) ($item['slot'] ?? 'Homepage') . ': `' . (string) ($item['product_name'] ?? '') . '`';
            }

            return 'Meow đang fallback nhưng vẫn có snapshot bán hàng thật.' . "\n" . implode("\n", $lines);
        }

        if ($this->containsAny($messageLower, ['khuyến mãi', 'coupon', 'ưu đãi', 'uu dai'])) {
            $promotions = array_slice((array) ($recommendations['promotions'] ?? []), 0, 2);
            $couponActions = array_slice((array) ($recommendations['coupon_actions'] ?? []), 0, 1);
            if ($promotions === [] && $couponActions === []) {
                return null;
            }

            $lines = [];
            foreach ($promotions as $item) {
                $lines[] = '- ' . (string) ($item['reason'] ?? '');
            }
            foreach ($couponActions as $item) {
                $lines[] = '- ' . (string) ($item['reason'] ?? '') . ' Next: ' . (string) ($item['next_action'] ?? '');
            }

            return 'Gợi ý khuyến mãi hiện chỉ ở mức sơ bộ vì chưa có giá vốn/fee.' . "\n" . implode("\n", $lines);
        }

        if ($this->containsAny($messageLower, ['upsell', 'nâng gói', 'nang goi'])) {
            $upsell = array_slice((array) ($recommendations['upsell'] ?? []), 0, 2);
            if ($upsell === []) {
                return null;
            }

            $lines = array_map(static fn(array $item): string => '- `' . (string) ($item['from_product_name'] ?? '')
                . '` -> `' . (string) ($item['to_product_name'] ?? '') . '`: ' . (string) ($item['reason'] ?? ''), $upsell);

            return 'Các ladder upsell này đang bám vào catalog cloud thật hiện có.' . "\n" . implode("\n", $lines);
        }

        if ($this->containsAny($messageLower, ['giá vốn', 'gia von', 'lợi nhuận', 'loi nhuan', 'lời lỗ', 'loi lo'])) {
            $cannotConfirm = array_slice(array_map('strval', (array) ($salesRecommendations['coverage']['cannot_confirm'] ?? [])), 0, 4);
            $missingFields = array_slice(array_map('strval', (array) ($salesRecommendations['data_gaps']['missing_fields'] ?? [])), 0, 8);

            return 'Hiện Meow chỉ gợi ý được ở mức push/homepage/upsell/coupon pilot.'
                . "\nChưa thể xác nhận: " . implode(', ', $cannotConfirm)
                . "\nThiếu dữ liệu: " . implode(', ', $missingFields);
        }

        if ($actionQueue !== []) {
            return 'Snapshot Phase 5 hiện có: ' . (string) ($salesRecommendations['executive_summary'] ?? '')
                . "\nNext actions:"
                . "\n- " . implode("\n- ", $actionQueue);
        }

        return null;
    }

    private function customerFallbackResponse(string $sessionId, string $message, array $context, string $reason): array
    {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');
        $productsUrl = base_url('products');
        $faq = (array) ($context['faq'] ?? []);
        $currentProduct = is_array($context['current_product'] ?? null) ? (array) $context['current_product'] : [];
        $featuredProducts = array_values(array_filter((array) ($context['featured_products'] ?? []), 'is_array'));
        $suggestedProduct = $this->pickCustomerProduct($messageLower, $currentProduct, $featuredProducts);

        if ($this->containsAny($messageLower, ['bao lâu', 'nhận dịch vụ', 'kích hoạt', 'sau khi thanh toán', 'bao gio'])) {
            $reply = 'Thông thường dịch vụ được bàn giao trong khoảng 1-5 phút sau khi thanh toán.'
                . "\nNếu bạn muốn, mình có thể gợi ý luôn gói phù hợp trước khi bạn đặt hàng.";
        } elseif ($this->containsAny($messageLower, ['hỗ trợ', 'support', 'cài đặt', 'kỹ thuật'])) {
            $reply = 'Bên mình có hỗ trợ kỹ thuật trực tuyến 24/7 cho các sản phẩm đang cung cấp.'
                . "\nBạn chỉ cần nói rõ nhu cầu như chạy web, server game hay VPS ổn định để mình gợi ý đúng gói.";
        } elseif ($this->containsAny($messageLower, ['ví', 'wallet', 'số dư', 'nạp tiền', 'nạp số dư'])) {
            $reply = 'Bạn có thể nạp số dư trong khu vực hồ sơ sau khi đăng nhập, rồi dùng số dư đó để thanh toán trực tiếp.'
                . "\nNếu cần, mình có thể tư vấn trước gói phù hợp để bạn chủ động nạp đúng mức cần dùng.";
        } elseif ($this->containsAny($messageLower, ['server game', 'game', 'minecraft', 'mu', 'hosting game'])) {
            $reply = $suggestedProduct
                ? 'Nếu bạn đang cần server game ổn định, mình gợi ý thử `' . (string) $suggestedProduct['name'] . '` với mức giá ' . format_money((float) ($suggestedProduct['price'] ?? 0)) . '.'
                    . "\nXem nhanh: " . (string) ($suggestedProduct['url'] ?? $productsUrl)
                : 'Bên mình có thể tư vấn các gói phù hợp cho server game theo nhu cầu cấu hình và số lượng người chơi.'
                    . "\nBạn xem danh sách tại: " . $productsUrl;
        } elseif ($this->containsAny($messageLower, ['vps', 'cloud', 'web bán hàng', 'server', 'website', 'wordpress'])) {
            $reply = $suggestedProduct
                ? 'Nếu bạn đang cần VPS cho web hoặc ứng dụng, mình gợi ý `' . (string) $suggestedProduct['name'] . '` với mức giá ' . format_money((float) ($suggestedProduct['price'] ?? 0)) . '.'
                    . "\nXem nhanh: " . (string) ($suggestedProduct['url'] ?? $productsUrl)
                    . "\nNếu bạn nói thêm lượng traffic hoặc mục đích dùng, mình sẽ gợi ý sát hơn."
                : 'Mình có thể tư vấn VPS theo nhu cầu chạy web, app hoặc môi trường test.'
                    . "\nBạn xem các gói tại: " . $productsUrl;
        } elseif ($currentProduct && $this->containsAny($messageLower, ['gói này', 'sản phẩm này', 'cấu hình này', 'phù hợp không'])) {
            $reply = 'Gói `' . (string) ($currentProduct['name'] ?? 'này') . '` phù hợp nếu bạn cần triển khai nhanh với mức giá ' . format_money((float) ($currentProduct['price'] ?? 0)) . '.'
                . "\nNếu bạn nói rõ mục đích dùng, mình có thể đánh giá xem nên giữ cấu hình này hay nâng lên.";
        } elseif ($faq) {
            $reply = 'Mình có thể hỗ trợ nhanh về tư vấn sản phẩm, thời gian bàn giao và cách thanh toán.'
                . "\nVí dụ thường gặp:"
                . "\n- " . (string) (($faq[0]['question'] ?? 'Sau khi thanh toán bao lâu nhận dịch vụ?'))
                . "\n- " . (string) (($faq[1]['question'] ?? 'Có hỗ trợ kỹ thuật không?'))
                . "\nBạn đang quan tâm nhóm sản phẩm nào để mình gợi ý luôn?";
        } else {
            $reply = 'Mình có thể tư vấn nhanh về VPS, server game, wallet và các câu hỏi mua hàng phổ biến.'
                . "\nBạn có thể nói rõ nhu cầu như: chạy web bán hàng, cần server game, hay muốn hỏi thời gian bàn giao.";
        }

        return $this->buildResultEnvelope(
            'customer',
            $sessionId,
            $reply,
            'local-fallback',
            true,
            'fallback',
            'local-fallback',
            [
                'reason' => $reason,
            ]
        );
    }

    private function sendJsonRequest(string $method, string $url, array $payload): array
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
        ];

        $bridgeKey = trim((string) ($this->aiConfig['bridge_key'] ?? ''));
        if ($bridgeKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $bridgeKey;
            $headers[] = 'X-AI-Bridge-Key: ' . $bridgeKey;
        }

        $timeout = max(5, (int) ($this->aiConfig['chat_timeout'] ?? 20));
        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => in_array($method, ['POST', 'PUT', 'PATCH'], true) ? $jsonBody : null,
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($responseBody === false || $curlError !== '') {
                throw new \RuntimeException('Không gọi được AI bridge: ' . ($curlError ?: 'Unknown cURL error'));
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headers),
                    'content' => in_array($method, ['POST', 'PUT', 'PATCH'], true) ? (string) $jsonBody : '',
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
            ]);

            $responseBody = @file_get_contents($url, false, $context);
            $httpCode = 200;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $headerLine) {
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $matches)) {
                        $httpCode = (int) $matches[1];
                        break;
                    }
                }
            }

            if ($responseBody === false) {
                throw new \RuntimeException('Không gọi được AI bridge qua stream context.');
            }
        }

        $decoded = json_decode((string) $responseBody, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('AI bridge trả về JSON không hợp lệ.');
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException((string) ($decoded['error'] ?? $decoded['message'] ?? ('AI bridge HTTP ' . $httpCode)));
        }

        return $decoded;
    }

    private function pickCustomerProduct(string $messageLower, array $currentProduct, array $featuredProducts): array
    {
        if ($currentProduct && $this->containsAny($messageLower, ['gói này', 'sản phẩm này', 'cấu hình này'])) {
            return $currentProduct;
        }

        $candidates = [];
        if ($currentProduct) {
            $candidates[] = $currentProduct;
        }

        foreach ($featuredProducts as $item) {
            if (is_array($item)) {
                $candidates[] = $item;
            }
        }

        if (!$candidates) {
            return [];
        }

        foreach ($candidates as $candidate) {
            $haystack = mb_strtolower(
                trim(
                    implode(' ', [
                        (string) ($candidate['name'] ?? ''),
                        (string) ($candidate['category_name'] ?? ''),
                        (string) ($candidate['short_description'] ?? ''),
                        (string) ($candidate['specs'] ?? ''),
                    ])
                ),
                'UTF-8'
            );

            if ($haystack === '') {
                continue;
            }

            if ($this->containsAny($messageLower, ['game', 'minecraft', 'server game']) && $this->containsAny($haystack, ['game', 'minecraft'])) {
                return $candidate;
            }

            if ($this->containsAny($messageLower, ['vps', 'cloud', 'server', 'website', 'wordpress']) && $this->containsAny($haystack, ['vps', 'cloud', 'server'])) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            $needle = trim((string) $needle);
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function buildResultEnvelope(string $channel, string $sessionId, string $reply, string $provider, bool $isFallback, string $mode, string $source, array $raw = []): array
    {
        return [
            'success' => true,
            'channel' => $channel,
            'session_id' => $sessionId,
            'reply' => $reply,
            'provider' => $provider,
            'is_fallback' => $isFallback,
            'mode' => $mode,
            'source' => $source,
            'raw' => $raw,
        ];
    }

    private function resolveProviderName(array $response = []): string
    {
        $provider = trim((string) ($response['data']['provider'] ?? $response['provider'] ?? $this->aiConfig['provider'] ?? 'bridge'));

        return $provider !== '' ? $provider : 'bridge';
    }

    private function resolveConversationMode(array $context, array $meta, string $channel): string
    {
        $personaService = new AiPersonaService($this->config);

        return $personaService->resolveModeFromContext(
            $context,
            $meta,
            $channel === 'admin' ? 'admin_copilot' : 'customer_support'
        );
    }
}
