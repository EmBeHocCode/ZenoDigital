<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerFeedback;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletTransaction;

class AiContextBuilder
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function buildCustomerContext(array $options = []): array
    {
        $productModel = new Product($this->config);
        $categoryModel = new Category($this->config);
        $orderModel = new Order($this->config);
        $userModel = new User($this->config);
        $walletModel = new WalletTransaction($this->config);
        $actorResolver = new AiActorResolver($this->config);
        $personaService = new AiPersonaService($this->config);
        $actor = is_array($options['actor_context'] ?? null)
            ? $options['actor_context']
            : $actorResolver->resolveByUserId((int) ($options['user_id'] ?? 0));
        $productId = (int) ($options['product_id'] ?? 0);

        $context = array_merge($this->baseActorContext($actor), [
            'site' => [
                'name' => 'ZenoxDigital',
                'url' => (string) config('app.url', ''),
                'currency' => (string) config('app.currency', 'VND'),
            ],
            'bot' => $personaService->identity(),
            'surface' => [
                'route_scope' => (string) ($options['route_scope'] ?? 'public_storefront'),
                'page_type' => $productId > 0 ? 'product' : 'storefront',
            ],
            'faq' => [
                [
                    'question' => 'Sau khi thanh toán bao lâu nhận dịch vụ?',
                    'answer' => 'Thông thường từ 1-5 phút tùy loại sản phẩm số.',
                ],
                [
                    'question' => 'Có hỗ trợ kỹ thuật không?',
                    'answer' => 'Có. Đội ngũ hỗ trợ trực tuyến 24/7.',
                ],
            ],
            'categories' => array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                ];
            }, array_slice($categoryModel->all(), 0, 8)),
            'featured_products' => array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'price' => (float) ($row['price'] ?? 0),
                    'category_name' => (string) ($row['category_name'] ?? ''),
                    'url' => base_url('products/show/' . (int) ($row['id'] ?? 0)),
                ];
            }, array_slice($productModel->featured(6), 0, 6)),
        ]);

        if ($productId > 0) {
            $product = $productModel->find($productId);
            if ($product) {
                $context['current_product'] = [
                    'id' => (int) ($product['id'] ?? 0),
                    'name' => (string) ($product['name'] ?? ''),
                    'price' => (float) ($product['price'] ?? 0),
                    'category_name' => (string) ($product['category_name'] ?? ''),
                    'short_description' => (string) ($product['short_description'] ?? ''),
                    'specs' => (string) ($product['specs'] ?? ''),
                    'url' => base_url('products/show/' . (int) ($product['id'] ?? 0)),
                ];
            }
        }

        $trustedActorId = (int) ($actor['actor_id'] ?? 0);
        if ($trustedActorId > 0 && (string) ($actor['actor_type'] ?? 'unknown') === 'customer') {
            $user = $userModel->find($trustedActorId);
            $walletSummary = $walletModel->summaryByUser($trustedActorId);

            if ($user) {
                $birthDate = normalize_birth_date((string) ($user['birth_date'] ?? ''));
                $context['account_profile'] = [
                    'id' => (int) ($user['id'] ?? 0),
                    'full_name' => (string) ($user['full_name'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                    'gender' => normalize_user_gender((string) ($user['gender'] ?? 'unknown')),
                    'birth_date' => $birthDate,
                    'age' => calculate_age_from_birth_date($birthDate),
                    'wallet_balance' => (float) ($user['wallet_balance'] ?? 0),
                    'created_at' => (string) ($user['created_at'] ?? ''),
                ];
            }

            $context['wallet_summary'] = [
                'current_balance' => (float) ($walletSummary['current_balance'] ?? 0),
                'total_deposit' => (float) ($walletSummary['total_deposit'] ?? 0),
                'display_spent' => (float) ($walletSummary['display_spent'] ?? 0),
                'deposit_count' => (int) ($walletSummary['deposit_count'] ?? 0),
                'latest_activity_at' => $walletSummary['latest_activity_at'] ?? null,
            ];
            $context['recent_orders'] = array_map(static function (array $row): array {
                return [
                    'order_code' => (string) ($row['order_code'] ?? ''),
                    'total_amount' => (float) ($row['total_amount'] ?? 0),
                    'status' => (string) ($row['status'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }, array_slice($orderModel->byUser($trustedActorId), 0, 3));
            $context['recent_wallet_transactions'] = array_map(static function (array $row): array {
                return [
                    'transaction_code' => (string) ($row['transaction_code'] ?? ''),
                    'transaction_type' => (string) ($row['transaction_type'] ?? ''),
                    'direction' => (string) ($row['direction'] ?? ''),
                    'amount' => (float) ($row['amount'] ?? 0),
                    'status' => (string) ($row['status'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }, array_slice($walletModel->recentByUser($trustedActorId, 3), 0, 3));
        }

        return $context;
    }

    public function buildAdminContext(array $options = []): array
    {
        $actorResolver = new AiActorResolver($this->config);
        $personaService = new AiPersonaService($this->config);
        $freshnessService = new AdminAiDataFreshnessService($this->config);
        $actor = is_array($options['actor_context'] ?? null)
            ? $options['actor_context']
            : $actorResolver->resolveFromSession();
        $productId = (int) ($options['product_id'] ?? 0);
        $routeScope = (string) ($options['route_scope'] ?? 'admin_panel');
        $backofficeScope = is_array($options['backoffice_scope'] ?? null)
            ? (array) $options['backoffice_scope']
            : $this->resolveBackofficeScope($actor);
        $freshness = $freshnessService->buildAdminSignature();
        $cacheKey = $this->buildAdminDataCacheKey($routeScope, $productId, $backofficeScope);
        $cachedSnapshot = $freshnessService->loadCachedSnapshot($cacheKey, (string) ($freshness['fingerprint'] ?? ''));
        $dataSnapshot = is_array($cachedSnapshot['data'] ?? null)
            ? (array) $cachedSnapshot['data']
            : $this->buildAdminDataSnapshot($productId, $backofficeScope);
        $cacheHit = !empty($cachedSnapshot['cache_hit']);

        if (!$cacheHit) {
            $freshnessService->storeCachedSnapshot($cacheKey, (string) ($freshness['fingerprint'] ?? ''), $dataSnapshot);
        }

        $dataSnapshot['data_freshness'] = [
            'fingerprint' => (string) ($freshness['fingerprint'] ?? ''),
            'generated_at' => (string) ($freshness['generated_at'] ?? date('c')),
            'modules' => (array) ($freshness['modules'] ?? []),
            'refresh_policy' => (array) ($freshness['refresh_policy'] ?? []),
            'cache' => [
                'hit' => $cacheHit,
                'ttl_seconds' => $freshnessService->cacheTtlSeconds(),
                'age_seconds' => (int) ($cachedSnapshot['age_seconds'] ?? 0),
                'stored_at' => $cachedSnapshot['stored_at'] ?? null,
            ],
        ];

        $context = array_merge($this->baseActorContext($actor), [
            'site' => [
                'name' => 'ZenoxDigital',
                'url' => (string) config('app.url', ''),
                'currency' => (string) config('app.currency', 'VND'),
            ],
            'bot' => $personaService->identity(),
            'surface' => [
                'route_scope' => $routeScope,
                'page_type' => $productId > 0
                    ? 'product'
                    : ($routeScope === 'admin_panel' ? 'dashboard' : 'storefront'),
            ],
            'backoffice_scope' => $backofficeScope,
        ], $dataSnapshot);

        return $context;
    }

    private function baseActorContext(array $actor): array
    {
        $personaService = new AiPersonaService($this->config);

        return [
            'auth_state' => (string) ($actor['auth_state'] ?? 'unknown'),
            'actor_type' => (string) ($actor['actor_type'] ?? 'unknown'),
            'actor_role' => (string) ($actor['actor_role'] ?? $actor['role_name'] ?? 'unknown'),
            'role_group' => (string) ($actor['role_group'] ?? 'safe'),
            'actor_name' => $actor['actor_name'] ?? null,
            'actor_id' => $actor['actor_id'] ?? null,
            'actor_gender' => (string) ($actor['actor_gender'] ?? 'unknown'),
            'actor_birth_date' => $actor['actor_birth_date'] ?? null,
            'actor_age' => $actor['actor_age'] ?? null,
            'conversation_mode' => $personaService->resolveModeFromActor($actor),
            'is_admin' => !empty($actor['is_admin']),
            'is_staff' => !empty($actor['is_staff']),
            'is_management_role' => !empty($actor['is_management_role']),
            'is_backoffice_actor' => !empty($actor['is_backoffice_actor']),
            'is_customer' => !empty($actor['is_customer']),
            'is_guest' => !empty($actor['is_guest']),
            'is_authenticated' => !empty($actor['is_authenticated']),
            'safe_addressing' => (string) ($actor['safe_addressing'] ?? 'bạn'),
            'support_scope' => (string) ($actor['support_scope'] ?? 'safe'),
            'actor' => $actor,
        ];
    }

    private function resolveBackofficeScope(array $actor): array
    {
        $actorType = (string) ($actor['actor_type'] ?? 'unknown');

        if ($actorType === 'admin') {
            return [
                'scope_key' => 'admin_full',
                'label' => 'Admin toàn quyền',
                'can_access_dashboard' => true,
                'can_use_ai_copilot' => true,
                'can_view_products' => true,
                'can_manage_products' => true,
                'can_view_categories' => true,
                'can_manage_categories' => true,
                'can_view_orders' => true,
                'can_manage_orders' => true,
                'can_view_payments' => true,
                'can_view_finance' => true,
                'can_view_users' => true,
                'can_manage_users' => true,
                'can_view_coupons' => true,
                'can_manage_coupons' => true,
                'can_view_feedback' => true,
                'can_manage_feedback' => true,
                'can_view_rank' => true,
                'can_manage_rank' => true,
                'can_view_settings' => true,
                'can_manage_settings' => true,
                'can_view_audit' => true,
                'can_view_sql' => true,
                'can_manage_sql' => true,
                'can_manage_system' => true,
            ];
        }

        if (in_array($actorType, ['staff', 'management'], true)) {
            return [
                'scope_key' => 'operations_limited',
                'label' => $actorType === 'management' ? 'Backoffice điều hành giới hạn' : 'Staff vận hành giới hạn',
                'can_access_dashboard' => true,
                'can_use_ai_copilot' => true,
                'can_view_products' => true,
                'can_manage_products' => false,
                'can_view_categories' => false,
                'can_manage_categories' => false,
                'can_view_orders' => true,
                'can_manage_orders' => false,
                'can_view_payments' => false,
                'can_view_finance' => false,
                'can_view_users' => false,
                'can_manage_users' => false,
                'can_view_coupons' => true,
                'can_manage_coupons' => false,
                'can_view_feedback' => true,
                'can_manage_feedback' => false,
                'can_view_rank' => false,
                'can_manage_rank' => false,
                'can_view_settings' => false,
                'can_manage_settings' => false,
                'can_view_audit' => false,
                'can_view_sql' => false,
                'can_manage_sql' => false,
                'can_manage_system' => false,
            ];
        }

        return [
            'scope_key' => 'public',
            'label' => 'Không phải backoffice',
            'can_access_dashboard' => false,
            'can_use_ai_copilot' => false,
            'can_view_products' => false,
            'can_manage_products' => false,
            'can_view_categories' => false,
            'can_manage_categories' => false,
            'can_view_orders' => false,
            'can_manage_orders' => false,
            'can_view_payments' => false,
            'can_view_finance' => false,
            'can_view_users' => false,
            'can_manage_users' => false,
            'can_view_coupons' => false,
            'can_manage_coupons' => false,
            'can_view_feedback' => false,
            'can_manage_feedback' => false,
            'can_view_rank' => false,
            'can_manage_rank' => false,
            'can_view_settings' => false,
            'can_manage_settings' => false,
            'can_view_audit' => false,
            'can_view_sql' => false,
            'can_manage_sql' => false,
            'can_manage_system' => false,
        ];
    }

    private function buildAdminDataSnapshot(int $productId, array $backofficeScope): array
    {
        $productModel = new Product($this->config);
        $userModel = new \App\Models\User($this->config);
        $orderModel = new Order($this->config);
        $couponModel = new Coupon($this->config);
        $feedbackModel = new CustomerFeedback($this->config);
        $salesRecommendationService = new AiSalesRecommendationService($this->config);

        $couponSummary = !empty($backofficeScope['can_view_coupons']) ? $couponModel->summary() : [];
        $feedbackSummary = !empty($backofficeScope['can_view_feedback']) ? $feedbackModel->summary() : [];
        $stats = [
            'products' => $productModel->countAll(),
            'orders' => $orderModel->countAll(),
            'pending_orders' => $orderModel->countByStatus('pending'),
            'today_orders' => $orderModel->todayOrdersCount(),
            'active_coupons' => $couponModel->countActive(),
        ];

        if (!empty($backofficeScope['can_view_users'])) {
            $stats['users'] = $userModel->countAll();
        }

        if (!empty($backofficeScope['can_view_finance'])) {
            $stats['revenue'] = $orderModel->totalRevenue();
            $stats['today_revenue'] = $orderModel->todayRevenue();
        }

        if (!empty($backofficeScope['can_view_feedback'])) {
            $stats['new_feedback'] = (int) ($feedbackSummary['total_new'] ?? 0);
        }

        if (!empty($backofficeScope['can_view_coupons'])) {
            $stats['expiring_coupons'] = (int) ($couponSummary['expiring_soon'] ?? 0);
        }

        $snapshot = [
            'stats' => $stats,
            'top_products' => !empty($backofficeScope['can_view_products']) ? array_map(static function (array $row) use ($backofficeScope): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'category_name' => (string) ($row['category_name'] ?? ''),
                    'sold_qty' => (int) ($row['sold_qty'] ?? 0),
                    'sold_revenue' => !empty($backofficeScope['can_view_finance']) ? (float) ($row['sold_revenue'] ?? 0) : null,
                ];
            }, array_slice($productModel->topSelling(5), 0, 5)) : [],
            'latest_orders' => !empty($backofficeScope['can_view_orders']) ? array_map(static function (array $row): array {
                return [
                    'order_code' => (string) ($row['order_code'] ?? ''),
                    'total_amount' => (float) ($row['total_amount'] ?? 0),
                    'status' => (string) ($row['status'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }, array_slice($orderModel->latest(5), 0, 5)) : [],
            'revenue_by_month' => !empty($backofficeScope['can_view_finance']) ? $orderModel->revenueByMonth(6) : [],
            'order_status' => !empty($backofficeScope['can_view_orders']) ? $orderModel->statusBreakdown() : [],
            'coupon_summary' => $couponSummary,
            'latest_coupons' => !empty($backofficeScope['can_view_coupons']) ? array_map(static function (array $row): array {
                return [
                    'code' => (string) ($row['code'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'discount_percent' => (int) ($row['discount_percent'] ?? 0),
                    'used_count' => (int) ($row['used_count'] ?? 0),
                    'max_uses' => (int) ($row['max_uses'] ?? 0),
                    'status' => (string) ($row['status'] ?? ''),
                    'expires_at' => $row['expires_at'] ?? null,
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }, array_slice($couponModel->latest(5), 0, 5)) : [],
            'feedback_summary' => $feedbackSummary,
            'latest_feedback' => !empty($backofficeScope['can_view_feedback']) ? array_map(static function (array $row): array {
                return [
                    'feedback_code' => (string) ($row['feedback_code'] ?? ''),
                    'feedback_type' => (string) ($row['feedback_type'] ?? ''),
                    'sentiment' => (string) ($row['sentiment'] ?? ''),
                    'severity' => (string) ($row['severity'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'needs_follow_up' => !empty($row['needs_follow_up']),
                    'user_name' => (string) ($row['user_name'] ?? ''),
                    'product_name' => (string) ($row['product_name'] ?? ''),
                    'order_code' => (string) ($row['order_code'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }, array_slice($feedbackModel->latest(5), 0, 5)) : [],
            'sales_recommendations' => !empty($backofficeScope['can_view_products']) && !empty($backofficeScope['can_view_orders'])
                ? $salesRecommendationService->build($backofficeScope)
                : [],
        ];

        if ($productId > 0) {
            $product = $productModel->find($productId);
            if ($product) {
                $snapshot['current_product'] = [
                    'id' => (int) ($product['id'] ?? 0),
                    'name' => (string) ($product['name'] ?? ''),
                    'price' => (float) ($product['price'] ?? 0),
                    'category_name' => (string) ($product['category_name'] ?? ''),
                    'short_description' => (string) ($product['short_description'] ?? ''),
                    'specs' => (string) ($product['specs'] ?? ''),
                    'url' => base_url('products/show/' . (int) ($product['id'] ?? 0)),
                ];
            }
        }

        return $snapshot;
    }

    private function buildAdminDataCacheKey(string $routeScope, int $productId, array $backofficeScope): string
    {
        return implode('|', [
            'admin_ai_snapshot',
            trim($routeScope) !== '' ? trim($routeScope) : 'admin_panel',
            'product:' . max(0, $productId),
            'scope:' . (string) ($backofficeScope['scope_key'] ?? 'limited'),
            'finance:' . (!empty($backofficeScope['can_view_finance']) ? '1' : '0'),
            'users:' . (!empty($backofficeScope['can_view_users']) ? '1' : '0'),
            'feedback:' . (!empty($backofficeScope['can_view_feedback']) ? '1' : '0'),
            'coupons:' . (!empty($backofficeScope['can_view_coupons']) ? '1' : '0'),
        ]);
    }
}
