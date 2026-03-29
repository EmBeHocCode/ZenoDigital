<?php

namespace App\Services;

use App\Core\Auth;
use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerFeedback;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class AdminAiMutationService
{
    private array $config;
    private AdminAiModulePermissionService $permissions;
    private AdminAiMutationDraftService $drafts;
    private ModuleHealthGuardService $health;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->permissions = new AdminAiModulePermissionService();
        $this->drafts = new AdminAiMutationDraftService();
        $this->health = new ModuleHealthGuardService($config);
    }

    public function preview(array $decision, array $actor, array $backofficeScope, string $sessionId): array
    {
        $intent = (string) ($decision['intent'] ?? '');
        $prepared = match ($intent) {
            'product_create' => $this->prepareProductCreateDraft($decision, $backofficeScope),
            'product_price_update' => $this->prepareProductPriceDraft($decision, $backofficeScope),
            'product_description_update' => $this->prepareProductDescriptionDraft($decision, $backofficeScope),
            'product_status_update' => $this->prepareProductStatusDraft($decision, $backofficeScope),
            'product_delete' => $this->prepareProductDeleteDraft($decision, $backofficeScope),
            'category_create' => $this->prepareCategoryCreateDraft($decision, $backofficeScope),
            'category_update' => $this->prepareCategoryUpdateDraft($decision, $backofficeScope),
            'category_delete' => $this->prepareCategoryDeleteDraft($decision, $backofficeScope),
            'coupon_create' => $this->prepareCouponCreateDraft($decision, $backofficeScope),
            'coupon_status_update' => $this->prepareCouponStatusDraft($decision, $backofficeScope),
            'coupon_delete' => $this->prepareCouponDeleteDraft($decision, $backofficeScope),
            'user_status_update' => $this->prepareUserStatusDraft($decision, $backofficeScope),
            'user_role_update' => $this->prepareUserRoleDraft($decision, $backofficeScope),
            'user_delete' => $this->prepareUserDeleteDraft($decision, $backofficeScope),
            'order_status_update' => $this->prepareOrderStatusDraft($decision, $backofficeScope),
            'order_delete' => $this->prepareOrderDeleteDraft($decision, $backofficeScope),
            'feedback_status_update' => $this->prepareFeedbackStatusDraft($decision, $backofficeScope),
            'settings_update_safe' => $this->prepareSettingsPreview($decision, $backofficeScope),
            'rank_update' => $this->prepareRankPreview($decision, $backofficeScope),
            default => [
                'status' => 'blocked',
                'reply' => 'Meow chưa có mutation path an toàn cho yêu cầu này.',
                'module' => 'unknown',
                'risk' => 'high',
            ],
        };

        if (($prepared['status'] ?? '') !== 'ready') {
            return $this->previewEnvelope(
                (string) ($prepared['reply'] ?? 'Không thể chuẩn bị thao tác này.'),
                (string) ($prepared['module'] ?? 'unknown'),
                (string) ($prepared['risk'] ?? 'high'),
                false,
                (array) ($prepared['extra'] ?? []),
                match ((string) ($prepared['status'] ?? 'blocked')) {
                    'preview_only' => 'mutation_preview_only',
                    'clarify' => 'clarification',
                    default => 'mutation_blocked',
                }
            );
        }

        $draftRecord = $this->drafts->store(
            $sessionId,
            (int) ($actor['actor_id'] ?? 0),
            (array) ($prepared['draft'] ?? [])
        );

        return $this->previewEnvelope(
            (string) ($prepared['reply'] ?? 'Đã tạo preview thay đổi.'),
            (string) ($prepared['module'] ?? 'unknown'),
            (string) ($prepared['risk'] ?? 'medium'),
            true,
            array_merge((array) ($prepared['extra'] ?? []), [
                'draft_id' => (string) ($draftRecord['draft_id'] ?? ''),
            ]),
            'mutation_preview'
        );
    }

    public function confirmCurrent(array $actor, array $backofficeScope, string $sessionId): array
    {
        $record = $this->drafts->current($sessionId, (int) ($actor['actor_id'] ?? 0));
        if (!$record) {
            return $this->actionEnvelope(
                'Không có thao tác nào đang chờ xác nhận trong phiên hiện tại.',
                'clarification'
            );
        }

        $draft = (array) ($record['draft'] ?? []);
        $intent = (string) ($draft['intent'] ?? '');

        $result = match ($intent) {
            'product_create' => $this->executeProductCreate($draft, $actor, $backofficeScope),
            'product_price_update' => $this->executeProductPriceUpdate($draft, $actor, $backofficeScope),
            'product_description_update' => $this->executeProductDescriptionUpdate($draft, $actor, $backofficeScope),
            'product_status_update' => $this->executeProductStatusUpdate($draft, $actor, $backofficeScope),
            'product_delete' => $this->executeProductDelete($draft, $actor, $backofficeScope),
            'category_create' => $this->executeCategoryCreate($draft, $actor, $backofficeScope),
            'category_update' => $this->executeCategoryUpdate($draft, $actor, $backofficeScope),
            'category_delete' => $this->executeCategoryDelete($draft, $actor, $backofficeScope),
            'coupon_create' => $this->executeCouponCreate($draft, $actor, $backofficeScope),
            'coupon_status_update' => $this->executeCouponStatusUpdate($draft, $actor, $backofficeScope),
            'coupon_delete' => $this->executeCouponDelete($draft, $actor, $backofficeScope),
            'user_status_update' => $this->executeUserStatusUpdate($draft, $actor, $backofficeScope),
            'user_role_update' => $this->executeUserRoleUpdate($draft, $actor, $backofficeScope),
            'user_delete' => $this->executeUserDelete($draft, $actor, $backofficeScope),
            'order_status_update' => $this->executeOrderStatusUpdate($draft, $actor, $backofficeScope),
            'order_delete' => $this->executeOrderDelete($draft, $actor, $backofficeScope),
            'feedback_status_update' => $this->executeFeedbackStatusUpdate($draft, $actor, $backofficeScope),
            default => $this->actionEnvelope('Draft hiện tại không còn hợp lệ để thực thi.', 'mutation_blocked'),
        };

        $this->drafts->clear($sessionId, (int) ($actor['actor_id'] ?? 0));

        return $result;
    }

    public function cancelCurrent(array $actor, string $sessionId): array
    {
        $record = $this->drafts->current($sessionId, (int) ($actor['actor_id'] ?? 0));
        if (!$record) {
            return $this->actionEnvelope('Không có thao tác nháp nào để hủy.', 'clarification');
        }

        $draft = (array) ($record['draft'] ?? []);
        $this->drafts->clear($sessionId, (int) ($actor['actor_id'] ?? 0));

        return $this->actionEnvelope(
            'Đã hủy preview `' . (string) ($draft['intent'] ?? 'mutation') . '` trong phiên hiện tại.',
            'mutation_cancelled'
        );
    }

    public function clearSessionDraft(string $sessionId): void
    {
        $this->drafts->clearSession($sessionId);
    }

    private function previewEnvelope(string $reply, string $module, string $risk, bool $requiresConfirmation, array $extra = [], string $mode = 'mutation_preview'): array
    {
        return array_merge([
            'reply' => $reply,
            'meta' => [
                'provider' => 'admin-data-engine',
                'is_fallback' => false,
                'mode' => $mode,
                'source' => 'shop-data',
                'module' => $module,
                'risk' => $risk,
            ],
            'requires_confirmation' => $requiresConfirmation,
        ], $extra);
    }

    private function actionEnvelope(string $reply, string $mode = 'direct_admin_action', array $extra = []): array
    {
        return array_merge([
            'reply' => $reply,
            'meta' => [
                'provider' => 'admin-data-engine',
                'is_fallback' => false,
                'mode' => $mode,
                'source' => 'shop-data',
            ],
        ], $extra);
    }

    private function blockedResult(string $module, string $risk, string $reply): array
    {
        return [
            'status' => 'blocked',
            'module' => $module,
            'risk' => $risk,
            'reply' => $reply,
        ];
    }

    private function clarifyResult(string $module, string $risk, string $reply): array
    {
        return [
            'status' => 'clarify',
            'module' => $module,
            'risk' => $risk,
            'reply' => $reply,
        ];
    }

    private function guardWriteModule(string $module, array $scope): ?array
    {
        if (!$this->permissions->hasManagePermission($module)) {
            return $this->blockedResult($module, 'high', $this->permissions->denyWriteMessage($module, $scope));
        }

        if (!$this->health->isHealthy($module)) {
            return $this->blockedResult($module, 'high', $this->health->messageFor($module, 'write'));
        }

        if (!$this->permissions->canWrite($module)) {
            return $this->blockedResult($module, 'high', 'Module `' . ($this->permissions->moduleConfig($module)['label'] ?? $module) . '` hiện chưa mở execute trực tiếp qua AI. Chỉ hỗ trợ preview an toàn.');
        }

        return null;
    }

    private function guardExecuteDraft(string $module, array $draft, array $scope, array $actor): ?array
    {
        $guard = $this->guardWriteModule($module, $scope);
        if ($guard === null) {
            return null;
        }

        $this->auditMutation($actor, 'mutation_blocked', $module, (int) ($draft['entity_id'] ?? 0) ?: null, [
            'source' => 'admin_ai',
            'module' => $module,
            'result' => 'blocked',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
            'message' => (string) ($guard['reply'] ?? ''),
        ]);

        return $this->actionEnvelope((string) ($guard['reply'] ?? 'Module đang bị chặn.'), 'mutation_blocked');
    }

    private function failedActionWithAudit(array $draft, array $actor, string $message, string $auditAction): array
    {
        $module = (string) ($draft['module'] ?? 'unknown');
        $this->auditMutation($actor, $auditAction, $this->entityNameForModule($module), (int) ($draft['entity_id'] ?? 0) ?: null, [
            'source' => 'admin_ai',
            'module' => $module,
            'result' => 'failed',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
            'message' => $message,
        ]);

        return $this->actionEnvelope($message, 'mutation_blocked');
    }

    private function resolveProduct(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $model = new Product($this->config);
        if (ctype_digit($identifier)) {
            $row = $model->find((int) $identifier);
            if ($row) {
                return $row;
            }
        }

        $row = $model->findBySlug($this->slugify($identifier, 'product'));
        return $row ?: $model->findByName($identifier);
    }

    private function resolveCategory(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $model = new Category($this->config);
        if (ctype_digit($identifier)) {
            $row = $model->find((int) $identifier);
            if ($row) {
                return $row;
            }
        }

        $row = $model->findBySlug($this->slugify($identifier, 'category'));
        return $row ?: $model->findByName($identifier);
    }

    private function resolveCoupon(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $model = new Coupon($this->config);
        if (ctype_digit($identifier)) {
            $row = $model->find((int) $identifier);
            if ($row) {
                return $row;
            }
        }

        return $model->findByCode($identifier);
    }

    private function resolveUser(string $identifier): ?array
    {
        $model = new User($this->config);
        return $model->findByIdentifier($identifier);
    }

    private function resolveRoleId(string $roleName): int
    {
        $normalized = strtolower(trim($roleName));
        $userModel = new User($this->config);
        foreach ($userModel->allRoles() as $row) {
            if (strtolower(trim((string) ($row['name'] ?? ''))) === $normalized) {
                return (int) ($row['id'] ?? 0);
            }
        }

        return 0;
    }

    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function productPayloadFromCurrent(array $product): array
    {
        return [
            'category_id' => (int) ($product['category_id'] ?? 0),
            'name' => (string) ($product['name'] ?? ''),
            'slug' => (string) ($product['slug'] ?? ''),
            'price' => (float) ($product['price'] ?? 0),
            'short_description' => (string) ($product['short_description'] ?? ''),
            'description' => (string) ($product['description'] ?? ''),
            'specs' => (string) ($product['specs'] ?? ''),
            'image' => $product['image'] ?? null,
            'stock_status' => (string) ($product['stock_status'] ?? 'in_stock'),
            'status' => (string) ($product['status'] ?? 'active'),
        ];
    }

    private function entityNameForModule(string $module): string
    {
        return match ($module) {
            'products' => 'product',
            'categories' => 'category',
            'orders' => 'order',
            'coupons' => 'coupon',
            'users' => 'user',
            'feedback' => 'feedback',
            'payments' => 'payment',
            'settings' => 'setting',
            'rank' => 'rank',
            default => rtrim($module, 's'),
        };
    }

    private function userPayloadFromCurrent(array $user): array
    {
        return [
            'role_id' => (int) ($user['role_id'] ?? 2),
            'full_name' => (string) ($user['full_name'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'phone' => (string) ($user['phone'] ?? ''),
            'address' => (string) ($user['address'] ?? ''),
            'gender' => (string) ($user['gender'] ?? 'unknown'),
            'birth_date' => $user['birth_date'] ?? null,
            'status' => (string) ($user['status'] ?? 'active'),
        ];
    }

    private function excerpt(string $value, int $limit = 140): string
    {
        $value = trim($value);
        if ($value === '') {
            return '(trống)';
        }

        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . '...';
    }

    private function slugify(string $text, string $fallback): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', (string) $text);
        $text = strtolower(trim((string) $text, '-'));

        return $text !== '' ? $text : $fallback;
    }

    private function productStatusLabel(string $status): string
    {
        return $status === 'active' ? 'Đang bán' : 'Đang ẩn';
    }

    private function auditMutation(array $actor, string $action, string $entity, ?int $entityId, array $meta = []): void
    {
        try {
            $model = new AdminAuditLog($this->config);
            $model->create((int) ($actor['actor_id'] ?? 0), $action, $entity, $entityId, $meta);
        } catch (\Throwable $exception) {
            security_log('Không thể ghi admin AI audit log', [
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function prepareProductPriceDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('products', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $identifier = sanitize_text((string) ($decision['params']['identifier'] ?? ''), 180);
        $newPrice = (float) ($decision['params']['price'] ?? 0);
        if ($identifier === '') {
            return $this->clarifyResult('products', 'low', 'Bạn muốn sửa giá sản phẩm nào?');
        }

        if ($newPrice <= 0) {
            return $this->clarifyResult('products', 'low', 'Giá mới của sản phẩm là bao nhiêu?');
        }

        $product = $this->resolveProduct($identifier);
        if (!$product) {
            return $this->blockedResult('products', 'low', 'Không tìm thấy sản phẩm `' . $identifier . '`.');
        }

        $currentPrice = (float) ($product['price'] ?? 0);
        if ($currentPrice === $newPrice) {
            return $this->blockedResult('products', 'low', 'Sản phẩm `' . ($product['name'] ?? $identifier) . '` đã có giá `' . format_money($currentPrice) . '`.');
        }

        return [
            'status' => 'ready',
            'module' => 'products',
            'risk' => 'low',
            'reply' => "Preview cập nhật giá sản phẩm `{$product['name']}`:\n- Giá hiện tại: " . format_money($currentPrice)
                . "\n- Giá mới: " . format_money($newPrice)
                . "\n- Risk: " . $this->permissions->riskLabel('low')
                . "\nGõ `xác nhận` để ghi thay đổi hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'product_price_update',
                'module' => 'products',
                'risk' => 'low',
                'entity_id' => (int) ($product['id'] ?? 0),
                'entity_label' => (string) ($product['name'] ?? ''),
                'before' => ['price' => $currentPrice],
                'after' => ['price' => $newPrice],
            ],
        ];
    }

    private function prepareProductCreateDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('products', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $name = sanitize_text((string) ($decision['params']['name'] ?? ''), 180);
        $price = (float) ($decision['params']['price'] ?? 0);
        $categoryIdentifier = sanitize_text((string) ($decision['params']['category_identifier'] ?? ''), 120);
        $shortDescription = sanitize_text((string) ($decision['params']['short_description'] ?? ''), 300);
        $description = sanitize_text((string) ($decision['params']['description'] ?? ''), 5000);
        $specs = sanitize_text((string) ($decision['params']['specs'] ?? ''), 5000);
        $status = validate_enum((string) ($decision['params']['status'] ?? 'active'), ['active', 'inactive'], 'active');
        $stockStatus = validate_enum((string) ($decision['params']['stock_status'] ?? 'in_stock'), ['in_stock', 'out_of_stock'], 'in_stock');

        if ($name === '') {
            return $this->clarifyResult('products', 'medium', 'Tên sản phẩm mới là gì?');
        }

        if ($price <= 0) {
            return $this->clarifyResult('products', 'medium', 'Giá sản phẩm mới là bao nhiêu?');
        }

        if ($categoryIdentifier === '') {
            return $this->clarifyResult('products', 'medium', 'Sản phẩm này thuộc danh mục nào?');
        }

        $category = $this->resolveCategory($categoryIdentifier);
        if (!$category) {
            return $this->blockedResult('products', 'medium', 'Không tìm thấy danh mục `' . $categoryIdentifier . '` để gán cho sản phẩm mới.');
        }

        $productModel = new Product($this->config);
        if ($productModel->findByName($name)) {
            return $this->blockedResult('products', 'medium', 'Sản phẩm `' . $name . '` đã tồn tại.');
        }

        $slugBase = $this->slugify($name, 'product');
        $slug = $slugBase . '-' . time();

        return [
            'status' => 'ready',
            'module' => 'products',
            'risk' => 'medium',
            'reply' => "Preview tạo sản phẩm mới `{$name}`:\n- Danh mục: " . (string) ($category['name'] ?? $categoryIdentifier)
                . "\n- Giá: " . format_money($price)
                . "\n- Slug dự kiến: `{$slug}`"
                . "\n- Trạng thái: " . $this->productStatusLabel($status)
                . "\n- Risk: " . $this->permissions->riskLabel('medium')
                . "\nGõ `xác nhận` để tạo sản phẩm hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'product_create',
                'module' => 'products',
                'risk' => 'medium',
                'entity_label' => $name,
                'before' => [],
                'after' => [
                    'category_id' => (int) ($category['id'] ?? 0),
                    'name' => $name,
                    'slug' => $slug,
                    'price' => $price,
                    'short_description' => $shortDescription,
                    'description' => $description,
                    'specs' => $specs,
                    'image' => null,
                    'stock_status' => $stockStatus,
                    'status' => $status,
                ],
            ],
        ];
    }

    private function prepareProductDescriptionDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('products', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $identifier = sanitize_text((string) ($decision['params']['identifier'] ?? ''), 180);
        $field = validate_enum((string) ($decision['params']['field'] ?? ''), ['short_description', 'description'], '');
        $value = sanitize_text((string) ($decision['params']['value'] ?? ''), $field === 'short_description' ? 300 : 5000);

        if ($identifier === '') {
            return $this->clarifyResult('products', 'low', 'Bạn muốn sửa mô tả của sản phẩm nào?');
        }

        if ($field === '') {
            return $this->clarifyResult('products', 'low', 'Bạn muốn sửa mô tả ngắn hay mô tả chi tiết?');
        }

        if ($value === '') {
            return $this->clarifyResult('products', 'low', 'Nội dung mô tả mới là gì?');
        }

        $product = $this->resolveProduct($identifier);
        if (!$product) {
            return $this->blockedResult('products', 'low', 'Không tìm thấy sản phẩm `' . $identifier . '`.');
        }

        $currentValue = (string) ($product[$field] ?? '');
        if ($currentValue === $value) {
            return $this->blockedResult('products', 'low', 'Mô tả của sản phẩm `' . ($product['name'] ?? $identifier) . '` đã đúng như nội dung mới.');
        }

        $label = $field === 'short_description' ? 'mô tả ngắn' : 'mô tả chi tiết';

        return [
            'status' => 'ready',
            'module' => 'products',
            'risk' => 'low',
            'reply' => "Preview cập nhật {$label} cho sản phẩm `{$product['name']}`:\n- Hiện tại: " . $this->excerpt($currentValue)
                . "\n- Mới: " . $this->excerpt($value)
                . "\n- Risk: " . $this->permissions->riskLabel('low')
                . "\nGõ `xác nhận` để ghi thay đổi hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'product_description_update',
                'module' => 'products',
                'risk' => 'low',
                'entity_id' => (int) ($product['id'] ?? 0),
                'entity_label' => (string) ($product['name'] ?? ''),
                'before' => [$field => $currentValue],
                'after' => [$field => $value],
                'field' => $field,
            ],
        ];
    }

    private function prepareProductStatusDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('products', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $identifier = sanitize_text((string) ($decision['params']['identifier'] ?? ''), 180);
        $status = validate_enum((string) ($decision['params']['status'] ?? ''), ['active', 'inactive'], '');
        if ($identifier === '') {
            return $this->clarifyResult('products', 'low', 'Bạn muốn bật hoặc tắt sản phẩm nào?');
        }

        if ($status === '') {
            return $this->clarifyResult('products', 'low', 'Bạn muốn bật hay tắt sản phẩm này?');
        }

        $product = $this->resolveProduct($identifier);
        if (!$product) {
            return $this->blockedResult('products', 'low', 'Không tìm thấy sản phẩm `' . $identifier . '`.');
        }

        $currentStatus = (string) ($product['status'] ?? 'inactive');
        if ($currentStatus === $status) {
            return $this->blockedResult('products', 'low', 'Sản phẩm `' . ($product['name'] ?? $identifier) . '` đã ở trạng thái `' . $this->productStatusLabel($status) . '`.');
        }

        return [
            'status' => 'ready',
            'module' => 'products',
            'risk' => 'low',
            'reply' => "Preview cập nhật trạng thái sản phẩm `{$product['name']}`:\n- Hiện tại: " . $this->productStatusLabel($currentStatus)
                . "\n- Mới: " . $this->productStatusLabel($status)
                . "\n- Risk: " . $this->permissions->riskLabel('low')
                . "\nGõ `xác nhận` để ghi thay đổi hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'product_status_update',
                'module' => 'products',
                'risk' => 'low',
                'entity_id' => (int) ($product['id'] ?? 0),
                'entity_label' => (string) ($product['name'] ?? ''),
                'before' => ['status' => $currentStatus],
                'after' => ['status' => $status],
            ],
        ];
    }

    private function prepareProductDeleteDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('products', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $identifier = sanitize_text((string) ($decision['params']['identifier'] ?? ''), 180);
        if ($identifier === '') {
            return $this->clarifyResult('products', 'high', 'Bạn muốn xóa sản phẩm nào?');
        }

        $product = $this->resolveProduct($identifier);
        if (!$product) {
            return $this->blockedResult('products', 'high', 'Không tìm thấy sản phẩm `' . $identifier . '`.');
        }

        return [
            'status' => 'ready',
            'module' => 'products',
            'risk' => 'high',
            'reply' => "Preview xóa sản phẩm `{$product['name']}`:\n- Category: " . (string) ($product['category_name'] ?? 'N/A')
                . "\n- Giá hiện tại: " . format_money((float) ($product['price'] ?? 0))
                . "\n- Risk: " . $this->permissions->riskLabel('high')
                . "\nGõ `xác nhận` để xóa mềm sản phẩm hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'product_delete',
                'module' => 'products',
                'risk' => 'high',
                'entity_id' => (int) ($product['id'] ?? 0),
                'entity_label' => (string) ($product['name'] ?? ''),
                'before' => [
                    'status' => (string) ($product['status'] ?? ''),
                    'price' => (float) ($product['price'] ?? 0),
                ],
                'after' => ['deleted_at' => 'NOW()'],
            ],
        ];
    }

    private function prepareCategoryCreateDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('categories', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $name = sanitize_text((string) ($decision['params']['name'] ?? ''), 120);
        $description = sanitize_text((string) ($decision['params']['description'] ?? ''), 500);
        if ($name === '') {
            return $this->clarifyResult('categories', 'medium', 'Tên danh mục mới là gì?');
        }

        $categoryModel = new Category($this->config);
        if ($categoryModel->findByName($name)) {
            return $this->blockedResult('categories', 'medium', 'Danh mục `' . $name . '` đã tồn tại.');
        }

        return [
            'status' => 'ready',
            'module' => 'categories',
            'risk' => 'medium',
            'reply' => "Preview tạo danh mục mới `{$name}`:\n- Slug dự kiến: `" . $this->slugify($name, 'category') . '`'
                . "\n- Mô tả: " . $this->excerpt($description)
                . "\n- Risk: " . $this->permissions->riskLabel('medium')
                . "\nGõ `xác nhận` để tạo hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'category_create',
                'module' => 'categories',
                'risk' => 'medium',
                'entity_label' => $name,
                'before' => [],
                'after' => [
                    'name' => $name,
                    'slug' => $this->slugify($name, 'category'),
                    'description' => $description,
                ],
            ],
        ];
    }

    private function prepareCategoryUpdateDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('categories', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $identifier = sanitize_text((string) ($decision['params']['identifier'] ?? ''), 120);
        $name = sanitize_text((string) ($decision['params']['name'] ?? ''), 120);
        $description = sanitize_text((string) ($decision['params']['description'] ?? ''), 500);
        if ($identifier === '') {
            return $this->clarifyResult('categories', 'medium', 'Bạn muốn sửa danh mục nào?');
        }

        if ($name === '' && $description === '') {
            return $this->clarifyResult('categories', 'medium', 'Bạn muốn đổi tên hay mô tả của danh mục?');
        }

        $category = $this->resolveCategory($identifier);
        if (!$category) {
            return $this->blockedResult('categories', 'medium', 'Không tìm thấy danh mục `' . $identifier . '`.');
        }

        $nextName = $name !== '' ? $name : (string) ($category['name'] ?? '');
        $nextDescription = $description !== '' ? $description : (string) ($category['description'] ?? '');

        return [
            'status' => 'ready',
            'module' => 'categories',
            'risk' => 'medium',
            'reply' => "Preview cập nhật danh mục `{$category['name']}`:\n- Tên mới: {$nextName}\n- Mô tả mới: " . $this->excerpt($nextDescription)
                . "\n- Risk: " . $this->permissions->riskLabel('medium')
                . "\nGõ `xác nhận` để ghi thay đổi hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'category_update',
                'module' => 'categories',
                'risk' => 'medium',
                'entity_id' => (int) ($category['id'] ?? 0),
                'entity_label' => (string) ($category['name'] ?? ''),
                'before' => [
                    'name' => (string) ($category['name'] ?? ''),
                    'description' => (string) ($category['description'] ?? ''),
                ],
                'after' => [
                    'name' => $nextName,
                    'slug' => $this->slugify($nextName, 'category'),
                    'description' => $nextDescription,
                ],
            ],
        ];
    }

    private function prepareCategoryDeleteDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('categories', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $identifier = sanitize_text((string) ($decision['params']['identifier'] ?? ''), 120);
        if ($identifier === '') {
            return $this->clarifyResult('categories', 'high', 'Bạn muốn xóa danh mục nào?');
        }

        $category = $this->resolveCategory($identifier);
        if (!$category) {
            return $this->blockedResult('categories', 'high', 'Không tìm thấy danh mục `' . $identifier . '`.');
        }

        $categoryModel = new Category($this->config);
        if (!$categoryModel->canDelete((int) ($category['id'] ?? 0))) {
            return $this->blockedResult('categories', 'high', 'Danh mục `' . ($category['name'] ?? $identifier) . '` đang còn liên kết sản phẩm nên chưa thể xóa.');
        }

        return [
            'status' => 'ready',
            'module' => 'categories',
            'risk' => 'high',
            'reply' => "Preview xóa danh mục `{$category['name']}`:\n- Slug: `" . (string) ($category['slug'] ?? '') . '`'
                . "\n- Risk: " . $this->permissions->riskLabel('high')
                . "\nGõ `xác nhận` để xóa mềm danh mục hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'category_delete',
                'module' => 'categories',
                'risk' => 'high',
                'entity_id' => (int) ($category['id'] ?? 0),
                'entity_label' => (string) ($category['name'] ?? ''),
                'before' => [
                    'name' => (string) ($category['name'] ?? ''),
                    'slug' => (string) ($category['slug'] ?? ''),
                ],
                'after' => ['deleted_at' => 'NOW()'],
            ],
        ];
    }

    private function prepareCouponCreateDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('coupons', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $code = strtoupper(sanitize_text((string) ($decision['params']['code'] ?? ''), 80));
        $discount = validate_int_range($decision['params']['discount_percent'] ?? null, 1, 90, 0);
        $status = validate_enum((string) ($decision['params']['status'] ?? 'active'), ['active', 'inactive'], 'active');
        $maxUses = validate_int_range($decision['params']['max_uses'] ?? null, 0, 1000000, 0);
        $description = sanitize_text((string) ($decision['params']['description'] ?? ''), 255);
        $expiresAt = $this->normalizeDateTime((string) ($decision['params']['expires_at'] ?? ''));

        if ($code === '' || $discount <= 0) {
            return $this->clarifyResult('coupons', 'medium', 'Để tạo coupon, cần tối thiểu mã coupon và phần trăm giảm giá.');
        }

        $couponModel = new Coupon($this->config);
        if ($couponModel->findByCode($code)) {
            return $this->blockedResult('coupons', 'medium', 'Coupon `' . $code . '` đã tồn tại.');
        }

        return [
            'status' => 'ready',
            'module' => 'coupons',
            'risk' => 'medium',
            'reply' => "Preview tạo coupon `{$code}`:\n- Discount: -{$discount}%\n- Trạng thái: {$status}\n- Max uses: {$maxUses}\n- Hết hạn: " . ($expiresAt ?: 'không đặt')
                . "\n- Risk: " . $this->permissions->riskLabel('medium')
                . "\nGõ `xác nhận` để tạo coupon hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'coupon_create',
                'module' => 'coupons',
                'risk' => 'medium',
                'entity_label' => $code,
                'before' => [],
                'after' => [
                    'code' => $code,
                    'description' => $description,
                    'discount_percent' => $discount,
                    'max_uses' => $maxUses,
                    'expires_at' => $expiresAt,
                    'status' => $status,
                ],
            ],
        ];
    }

    private function prepareCouponStatusDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('coupons', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $identifier = sanitize_text((string) ($decision['params']['identifier'] ?? ''), 80);
        $status = validate_enum((string) ($decision['params']['status'] ?? ''), ['active', 'inactive'], '');
        if ($identifier === '') {
            return $this->clarifyResult('coupons', 'low', 'Bạn muốn bật hoặc tắt coupon nào?');
        }

        if ($status === '') {
            return $this->clarifyResult('coupons', 'low', 'Bạn muốn bật hay tắt coupon này?');
        }

        $coupon = $this->resolveCoupon($identifier);
        if (!$coupon) {
            return $this->blockedResult('coupons', 'low', 'Không tìm thấy coupon `' . $identifier . '`.');
        }

        if ((string) ($coupon['status'] ?? '') === $status) {
            return $this->blockedResult('coupons', 'low', 'Coupon `' . ($coupon['code'] ?? $identifier) . '` đã ở trạng thái `' . $status . '`.');
        }

        return [
            'status' => 'ready',
            'module' => 'coupons',
            'risk' => 'low',
            'reply' => "Preview cập nhật trạng thái coupon `{$coupon['code']}`:\n- Hiện tại: " . (string) ($coupon['status'] ?? 'inactive')
                . "\n- Mới: {$status}\n- Risk: " . $this->permissions->riskLabel('low')
                . "\nGõ `xác nhận` để ghi thay đổi hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'coupon_status_update',
                'module' => 'coupons',
                'risk' => 'low',
                'entity_id' => (int) ($coupon['id'] ?? 0),
                'entity_label' => (string) ($coupon['code'] ?? ''),
                'before' => ['status' => (string) ($coupon['status'] ?? '')],
                'after' => ['status' => $status],
            ],
        ];
    }

    private function prepareCouponDeleteDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('coupons', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $identifier = sanitize_text((string) ($decision['params']['identifier'] ?? ''), 80);
        if ($identifier === '') {
            return $this->clarifyResult('coupons', 'high', 'Bạn muốn xóa coupon nào?');
        }

        $coupon = $this->resolveCoupon($identifier);
        if (!$coupon) {
            return $this->blockedResult('coupons', 'high', 'Không tìm thấy coupon `' . $identifier . '`.');
        }

        return [
            'status' => 'ready',
            'module' => 'coupons',
            'risk' => 'high',
            'reply' => "Preview xóa coupon `{$coupon['code']}`:\n- Discount: -" . (int) ($coupon['discount_percent'] ?? 0) . '%'
                . "\n- Trạng thái: " . (string) ($coupon['status'] ?? 'inactive')
                . "\n- Risk: " . $this->permissions->riskLabel('high')
                . "\nGõ `xác nhận` để xóa mềm coupon hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'coupon_delete',
                'module' => 'coupons',
                'risk' => 'high',
                'entity_id' => (int) ($coupon['id'] ?? 0),
                'entity_label' => (string) ($coupon['code'] ?? ''),
                'before' => [
                    'status' => (string) ($coupon['status'] ?? ''),
                    'discount_percent' => (int) ($coupon['discount_percent'] ?? 0),
                ],
                'after' => ['deleted_at' => 'NOW()'],
            ],
        ];
    }
    private function prepareUserStatusDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('users', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $identifier = sanitize_text((string) ($decision['params']['identifier'] ?? ''), 190);
        $status = validate_enum((string) ($decision['params']['status'] ?? ''), ['active', 'blocked'], '');
        if ($identifier === '') {
            return $this->clarifyResult('users', 'medium', 'Bạn muốn khóa hoặc mở user nào?');
        }

        if ($status === '') {
            return $this->clarifyResult('users', 'medium', 'Bạn muốn đổi trạng thái user sang active hay blocked?');
        }

        $user = $this->resolveUser($identifier);
        if (!$user) {
            return $this->blockedResult('users', 'medium', 'Không tìm thấy user `' . $identifier . '`.');
        }

        if ((int) ($user['id'] ?? 0) === (int) (Auth::id() ?? 0) && $status === 'blocked') {
            return $this->blockedResult('users', 'medium', 'Không thể khóa tài khoản đang đăng nhập qua AI.');
        }

        if ((string) ($user['status'] ?? '') === $status) {
            return $this->blockedResult('users', 'medium', 'User `' . ($user['email'] ?? $identifier) . '` đã ở trạng thái `' . $status . '`.');
        }

        return [
            'status' => 'ready',
            'module' => 'users',
            'risk' => 'medium',
            'reply' => "Preview cập nhật trạng thái user `{$user['email']}`:\n- Hiện tại: " . (string) ($user['status'] ?? 'active')
                . "\n- Mới: {$status}\n- Risk: " . $this->permissions->riskLabel('medium')
                . "\nGõ `xác nhận` để ghi thay đổi hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'user_status_update',
                'module' => 'users',
                'risk' => 'medium',
                'entity_id' => (int) ($user['id'] ?? 0),
                'entity_label' => (string) ($user['email'] ?? ''),
                'before' => ['status' => (string) ($user['status'] ?? '')],
                'after' => ['status' => $status],
            ],
        ];
    }

    private function prepareUserRoleDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('users', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $identifier = sanitize_text((string) ($decision['params']['identifier'] ?? ''), 190);
        $role = strtolower(sanitize_text((string) ($decision['params']['role_name'] ?? ''), 80));
        if ($identifier === '') {
            return $this->clarifyResult('users', 'high', 'Bạn muốn đổi role cho user nào?');
        }

        if ($role === '') {
            return $this->clarifyResult('users', 'high', 'Role đích là gì: admin, staff hay user?');
        }

        $user = $this->resolveUser($identifier);
        if (!$user) {
            return $this->blockedResult('users', 'high', 'Không tìm thấy user `' . $identifier . '`.');
        }

        $roleId = $this->resolveRoleId($role);
        if ($roleId <= 0) {
            return $this->blockedResult('users', 'high', 'Không tìm thấy role `' . $role . '` trong hệ thống.');
        }

        if ((int) ($user['role_id'] ?? 0) === $roleId) {
            return $this->blockedResult('users', 'high', 'User `' . ($user['email'] ?? $identifier) . '` đã có role `' . $role . '`.');
        }

        return [
            'status' => 'ready',
            'module' => 'users',
            'risk' => 'high',
            'reply' => "Preview đổi role user `{$user['email']}`:\n- Role hiện tại: " . (string) ($user['role_name'] ?? $user['role_id'] ?? 'N/A')
                . "\n- Role mới: {$role}\n- Risk: " . $this->permissions->riskLabel('high')
                . "\nGõ `xác nhận` để ghi thay đổi hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'user_role_update',
                'module' => 'users',
                'risk' => 'high',
                'entity_id' => (int) ($user['id'] ?? 0),
                'entity_label' => (string) ($user['email'] ?? ''),
                'before' => [
                    'role_id' => (int) ($user['role_id'] ?? 0),
                    'role_name' => (string) ($user['role_name'] ?? ''),
                ],
                'after' => [
                    'role_id' => $roleId,
                    'role_name' => $role,
                ],
            ],
        ];
    }

    private function prepareUserDeleteDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('users', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $identifier = sanitize_text((string) ($decision['params']['identifier'] ?? ''), 190);
        if ($identifier === '') {
            return $this->clarifyResult('users', 'high', 'Bạn muốn xóa user nào?');
        }

        $user = $this->resolveUser($identifier);
        if (!$user) {
            return $this->blockedResult('users', 'high', 'Không tìm thấy user `' . $identifier . '`.');
        }

        if ((int) ($user['id'] ?? 0) === (int) (Auth::id() ?? 0)) {
            return $this->blockedResult('users', 'high', 'Không thể xóa tài khoản đang đăng nhập qua AI.');
        }

        return [
            'status' => 'ready',
            'module' => 'users',
            'risk' => 'high',
            'reply' => "Preview xóa user `{$user['email']}`:\n- Họ tên: " . (string) ($user['full_name'] ?? 'N/A')
                . "\n- Trạng thái: " . (string) ($user['status'] ?? 'active')
                . "\n- Risk: " . $this->permissions->riskLabel('high')
                . "\nGõ `xác nhận` để xóa mềm user hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'user_delete',
                'module' => 'users',
                'risk' => 'high',
                'entity_id' => (int) ($user['id'] ?? 0),
                'entity_label' => (string) ($user['email'] ?? ''),
                'before' => [
                    'status' => (string) ($user['status'] ?? ''),
                    'role_id' => (int) ($user['role_id'] ?? 0),
                ],
                'after' => ['deleted_at' => 'NOW()'],
            ],
        ];
    }

    private function prepareOrderStatusDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('orders', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $orderCode = strtoupper(sanitize_text((string) ($decision['params']['order_code'] ?? ''), 60));
        $status = validate_enum((string) ($decision['params']['status'] ?? ''), ['pending', 'paid', 'processing', 'completed', 'cancelled'], '');
        if ($orderCode === '') {
            return $this->clarifyResult('orders', 'medium', 'Bạn muốn đổi trạng thái cho mã đơn nào?');
        }

        if ($status === '') {
            return $this->clarifyResult('orders', 'medium', 'Trạng thái đích của đơn là gì?');
        }

        $orderModel = new Order($this->config);
        $order = $orderModel->findByOrderCode($orderCode);
        if (!$order) {
            return $this->blockedResult('orders', 'medium', 'Không tìm thấy đơn `' . $orderCode . '`.');
        }

        $currentStatus = (string) ($order['status'] ?? '');
        if ($currentStatus === $status) {
            return $this->blockedResult('orders', 'medium', 'Đơn `' . $orderCode . '` đã ở trạng thái `' . order_status_label($status) . '`.');
        }

        return [
            'status' => 'ready',
            'module' => 'orders',
            'risk' => 'medium',
            'reply' => "Preview cập nhật đơn `{$orderCode}`:\n- Hiện tại: " . order_status_label($currentStatus)
                . "\n- Mới: " . order_status_label($status)
                . "\n- Risk: " . $this->permissions->riskLabel('medium')
                . "\nGõ `xác nhận` để ghi thay đổi hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'order_status_update',
                'module' => 'orders',
                'risk' => 'medium',
                'entity_id' => (int) ($order['id'] ?? 0),
                'entity_label' => $orderCode,
                'before' => ['status' => $currentStatus],
                'after' => ['status' => $status],
            ],
        ];
    }

    private function prepareOrderDeleteDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('orders', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $orderCode = strtoupper(sanitize_text((string) ($decision['params']['order_code'] ?? ''), 60));
        if ($orderCode === '') {
            return $this->clarifyResult('orders', 'high', 'Bạn muốn xóa mềm mã đơn nào?');
        }

        $orderModel = new Order($this->config);
        $order = $orderModel->findByOrderCode($orderCode);
        if (!$order) {
            return $this->blockedResult('orders', 'high', 'Không tìm thấy đơn `' . $orderCode . '`.');
        }

        return [
            'status' => 'ready',
            'module' => 'orders',
            'risk' => 'high',
            'reply' => "Preview xóa mềm đơn `{$orderCode}`:\n- Trạng thái hiện tại: " . order_status_label((string) ($order['status'] ?? 'pending'))
                . "\n- Tổng tiền: " . format_money((float) ($order['total_amount'] ?? 0))
                . "\n- Risk: " . $this->permissions->riskLabel('high')
                . "\nGõ `xác nhận` để xóa mềm đơn hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'order_delete',
                'module' => 'orders',
                'risk' => 'high',
                'entity_id' => (int) ($order['id'] ?? 0),
                'entity_label' => $orderCode,
                'before' => [
                    'status' => (string) ($order['status'] ?? ''),
                    'total_amount' => (float) ($order['total_amount'] ?? 0),
                ],
                'after' => ['deleted_at' => 'NOW()'],
            ],
        ];
    }

    private function prepareFeedbackStatusDraft(array $decision, array $scope): array
    {
        $guard = $this->guardWriteModule('feedback', $scope);
        if ($guard !== null) {
            return $guard;
        }

        $feedbackCode = strtoupper(sanitize_text((string) ($decision['params']['feedback_code'] ?? ''), 60));
        $status = validate_enum((string) ($decision['params']['status'] ?? ''), ['new', 'reviewing', 'resolved', 'closed'], '');
        $needsFollowUp = !empty($decision['params']['needs_follow_up']);
        $adminNote = sanitize_text((string) ($decision['params']['admin_note'] ?? ''), 500);
        if ($feedbackCode === '') {
            return $this->clarifyResult('feedback', 'medium', 'Bạn muốn xử lý feedback mã nào?');
        }

        if ($status === '') {
            return $this->clarifyResult('feedback', 'medium', 'Bạn muốn feedback này sang trạng thái reviewing, resolved hay closed?');
        }

        $feedbackModel = new CustomerFeedback($this->config);
        $feedback = $feedbackModel->findByFeedbackCode($feedbackCode);
        if (!$feedback) {
            return $this->blockedResult('feedback', 'medium', 'Không tìm thấy feedback `' . $feedbackCode . '`.');
        }

        return [
            'status' => 'ready',
            'module' => 'feedback',
            'risk' => 'medium',
            'reply' => "Preview xử lý feedback `{$feedbackCode}`:\n- Hiện tại: " . (string) ($feedback['status'] ?? 'new')
                . "\n- Mới: {$status}\n- Follow-up: " . ($needsFollowUp ? 'có' : 'không')
                . ($adminNote !== '' ? "\n- Ghi chú admin: {$adminNote}" : '')
                . "\n- Risk: " . $this->permissions->riskLabel('medium')
                . "\nGõ `xác nhận` để ghi thay đổi hoặc `hủy thao tác` để bỏ.",
            'draft' => [
                'intent' => 'feedback_status_update',
                'module' => 'feedback',
                'risk' => 'medium',
                'entity_id' => (int) ($feedback['id'] ?? 0),
                'entity_label' => $feedbackCode,
                'before' => [
                    'status' => (string) ($feedback['status'] ?? ''),
                    'needs_follow_up' => !empty($feedback['needs_follow_up']),
                    'admin_note' => (string) ($feedback['admin_note'] ?? ''),
                ],
                'after' => [
                    'status' => $status,
                    'needs_follow_up' => $needsFollowUp,
                    'admin_note' => $adminNote,
                ],
            ],
        ];
    }

    private function prepareSettingsPreview(array $decision, array $scope): array
    {
        if (!$this->permissions->hasManagePermission('settings')) {
            return $this->blockedResult('settings', 'high', $this->permissions->denyWriteMessage('settings', $scope));
        }

        if (!$this->health->isHealthy('settings')) {
            return $this->blockedResult('settings', 'high', $this->health->messageFor('settings', 'write'));
        }

        $key = sanitize_text((string) ($decision['params']['setting_key'] ?? ''), 80);
        $value = sanitize_text((string) ($decision['params']['setting_value'] ?? ''), 255);
        if ($key === '' || $value === '') {
            return $this->clarifyResult('settings', 'high', 'Meow cần biết setting an toàn nào và giá trị mới tương ứng.');
        }

        return [
            'status' => 'preview_only',
            'module' => 'settings',
            'risk' => 'high',
            'reply' => "Preview thay đổi setting an toàn:\n- Key: {$key}\n- Giá trị mới: {$value}\n- Risk: " . $this->permissions->riskLabel('high')
                . "\nModule `settings` hiện mới mở tới mức preview có kiểm soát. Meow chưa execute trực tiếp để tránh ghi nhầm cấu hình hệ thống.",
        ];
    }

    private function prepareRankPreview(array $decision, array $scope): array
    {
        if (!$this->permissions->hasManagePermission('rank')) {
            return $this->blockedResult('rank', 'high', $this->permissions->denyWriteMessage('rank', $scope));
        }

        if (!$this->health->isHealthy('rank')) {
            return $this->blockedResult('rank', 'high', $this->health->messageFor('rank', 'write'));
        }

        $rankKey = sanitize_text((string) ($decision['params']['rank_key'] ?? ''), 80);
        $metric = sanitize_text((string) ($decision['params']['metric'] ?? ''), 40);
        $value = sanitize_text((string) ($decision['params']['value'] ?? ''), 20);
        if ($rankKey === '' || $metric === '' || $value === '') {
            return $this->clarifyResult('rank', 'high', 'Để preview rank setting, cần biết cấp rank, loại chỉ số và giá trị mới.');
        }

        return [
            'status' => 'preview_only',
            'module' => 'rank',
            'risk' => 'high',
            'reply' => "Preview cập nhật rank:\n- Cấp: {$rankKey}\n- Chỉ số: {$metric}\n- Giá trị mới: {$value}\n- Risk: " . $this->permissions->riskLabel('high')
                . "\nModule `rank` hiện mới mở tới mức preview. Meow chưa execute trực tiếp để tránh làm lệch ladder loyalty toàn hệ thống.",
        ];
    }
    private function executeProductPriceUpdate(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('products', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new Product($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'Sản phẩm không còn tồn tại để cập nhật.', 'update_price_via_ai_failed');
        }

        $payload = $this->productPayloadFromCurrent($current);
        $payload['price'] = (float) ($draft['after']['price'] ?? 0);
        $ok = $model->update((int) ($current['id'] ?? 0), $payload);
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể cập nhật giá sản phẩm lúc này.', 'update_price_via_ai_failed');
        }

        $this->auditMutation($actor, 'update_price_via_ai', 'product', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'products',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã cập nhật giá sản phẩm `' . (string) ($current['name'] ?? '') . '` sang `' . format_money((float) ($draft['after']['price'] ?? 0)) . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'product_updated',
                    'product_id' => (int) ($current['id'] ?? 0),
                    'name' => (string) ($current['name'] ?? ''),
                ],
            ]
        );
    }

    private function executeProductCreate(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('products', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $payload = (array) ($draft['after'] ?? []);
        $model = new Product($this->config);
        $createdId = $model->create($payload);
        if ($createdId === false) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể tạo sản phẩm mới lúc này.', 'product_create_failed');
        }

        $this->auditMutation($actor, 'create_via_ai', 'product', (int) $createdId, [
            'source' => 'admin_ai',
            'module' => 'products',
            'result' => 'success',
            'before' => [],
            'after' => $payload,
        ]);

        return $this->actionEnvelope(
            'Đã tạo sản phẩm `' . (string) ($payload['name'] ?? '') . '` trong danh mục mới chọn.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'product_created',
                    'product_id' => (int) $createdId,
                    'product_name' => (string) ($payload['name'] ?? ''),
                ],
            ]
        );
    }

    private function executeProductDescriptionUpdate(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('products', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new Product($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'Sản phẩm không còn tồn tại để cập nhật mô tả.', 'product_update_failed');
        }

        $payload = $this->productPayloadFromCurrent($current);
        $field = validate_enum((string) ($draft['field'] ?? ''), ['short_description', 'description'], '');
        if ($field === '') {
            return $this->failedActionWithAudit($draft, $actor, 'Draft mô tả sản phẩm không hợp lệ.', 'product_update_failed');
        }

        $payload[$field] = (string) ($draft['after'][$field] ?? '');
        $ok = $model->update((int) ($current['id'] ?? 0), $payload);
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể cập nhật mô tả sản phẩm lúc này.', 'product_update_failed');
        }

        $this->auditMutation($actor, 'update_description_via_ai', 'product', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'products',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã cập nhật mô tả cho sản phẩm `' . (string) ($current['name'] ?? '') . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'product_updated',
                    'product_id' => (int) ($current['id'] ?? 0),
                    'field' => $field,
                ],
            ]
        );
    }

    private function executeProductStatusUpdate(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('products', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new Product($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'Sản phẩm không còn tồn tại để cập nhật.', 'update_status_via_ai_failed');
        }

        $payload = $this->productPayloadFromCurrent($current);
        $payload['status'] = (string) ($draft['after']['status'] ?? 'inactive');
        $ok = $model->update((int) ($current['id'] ?? 0), $payload);
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể cập nhật trạng thái sản phẩm lúc này.', 'update_status_via_ai_failed');
        }

        $this->auditMutation($actor, 'update_status_via_ai', 'product', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'products',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã cập nhật trạng thái sản phẩm `' . (string) ($current['name'] ?? '') . '` sang `' . $this->productStatusLabel((string) ($draft['after']['status'] ?? 'inactive')) . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'product_updated',
                    'product_id' => (int) ($current['id'] ?? 0),
                    'name' => (string) ($current['name'] ?? ''),
                ],
            ]
        );
    }

    private function executeProductDelete(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('products', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new Product($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'Sản phẩm không còn tồn tại để xóa.', 'product_delete_failed');
        }

        $ok = $model->delete((int) ($current['id'] ?? 0));
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể xóa mềm sản phẩm lúc này.', 'product_delete_failed');
        }

        $this->auditMutation($actor, 'delete_via_ai', 'product', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'products',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã xóa mềm sản phẩm `' . (string) ($current['name'] ?? '') . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'product_deleted',
                    'product_id' => (int) ($current['id'] ?? 0),
                    'name' => (string) ($current['name'] ?? ''),
                ],
            ]
        );
    }

    private function executeCategoryCreate(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('categories', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $payload = (array) ($draft['after'] ?? []);
        $model = new Category($this->config);
        $ok = $model->create($payload);
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể tạo danh mục lúc này.', 'category_create_failed');
        }

        $created = $model->findBySlug((string) ($payload['slug'] ?? ''));
        $entityId = (int) ($created['id'] ?? 0);
        $this->auditMutation($actor, 'create_via_ai', 'category', $entityId > 0 ? $entityId : null, [
            'source' => 'admin_ai',
            'module' => 'categories',
            'result' => 'success',
            'before' => [],
            'after' => $payload,
        ]);

        return $this->actionEnvelope(
            'Đã tạo danh mục `' . (string) ($payload['name'] ?? '') . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'category_created',
                    'category_id' => $entityId,
                    'name' => (string) ($payload['name'] ?? ''),
                ],
            ]
        );
    }

    private function executeCategoryUpdate(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('categories', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new Category($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'Danh mục không còn tồn tại để cập nhật.', 'category_update_failed');
        }

        $payload = (array) ($draft['after'] ?? []);
        $ok = $model->update((int) ($current['id'] ?? 0), $payload);
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể cập nhật danh mục lúc này.', 'category_update_failed');
        }

        $this->auditMutation($actor, 'update_via_ai', 'category', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'categories',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $payload,
        ]);

        return $this->actionEnvelope(
            'Đã cập nhật danh mục `' . (string) ($payload['name'] ?? $current['name'] ?? '') . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'category_updated',
                    'category_id' => (int) ($current['id'] ?? 0),
                    'name' => (string) ($payload['name'] ?? ''),
                ],
            ]
        );
    }

    private function executeCategoryDelete(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('categories', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new Category($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'Danh mục không còn tồn tại để xóa.', 'category_delete_failed');
        }

        if (!$model->canDelete((int) ($current['id'] ?? 0))) {
            return $this->failedActionWithAudit($draft, $actor, 'Danh mục vẫn còn liên kết sản phẩm nên chưa thể xóa.', 'category_delete_blocked');
        }

        $ok = $model->delete((int) ($current['id'] ?? 0));
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể xóa mềm danh mục lúc này.', 'category_delete_failed');
        }

        $this->auditMutation($actor, 'delete_via_ai', 'category', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'categories',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã xóa mềm danh mục `' . (string) ($current['name'] ?? '') . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'category_deleted',
                    'category_id' => (int) ($current['id'] ?? 0),
                ],
            ]
        );
    }

    private function executeCouponCreate(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('coupons', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $payload = (array) ($draft['after'] ?? []);
        $model = new Coupon($this->config);
        $ok = $model->create($payload);
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể tạo coupon lúc này. Có thể mã đã tồn tại.', 'coupon_create_failed');
        }

        $created = $model->findByCode((string) ($payload['code'] ?? ''));
        $entityId = (int) ($created['id'] ?? 0);
        $this->auditMutation($actor, 'create_via_ai', 'coupon', $entityId > 0 ? $entityId : null, [
            'source' => 'admin_ai',
            'module' => 'coupons',
            'result' => 'success',
            'before' => [],
            'after' => $payload,
        ]);

        return $this->actionEnvelope(
            'Đã tạo coupon `' . (string) ($payload['code'] ?? '') . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'coupon_created',
                    'coupon_id' => $entityId,
                    'code' => (string) ($payload['code'] ?? ''),
                ],
            ]
        );
    }

    private function executeCouponStatusUpdate(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('coupons', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new Coupon($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'Coupon không còn tồn tại để cập nhật.', 'coupon_status_failed');
        }

        $status = (string) ($draft['after']['status'] ?? 'inactive');
        $ok = $model->updateStatus((int) ($current['id'] ?? 0), $status);
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể cập nhật trạng thái coupon lúc này.', 'coupon_status_failed');
        }

        $this->auditMutation($actor, 'update_status_via_ai', 'coupon', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'coupons',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã chuyển coupon `' . (string) ($current['code'] ?? '') . '` sang trạng thái `' . $status . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'coupon_status_updated',
                    'coupon_id' => (int) ($current['id'] ?? 0),
                    'code' => (string) ($current['code'] ?? ''),
                    'status' => $status,
                ],
            ]
        );
    }

    private function executeCouponDelete(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('coupons', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new Coupon($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'Coupon không còn tồn tại để xóa.', 'coupon_delete_failed');
        }

        $ok = $model->delete((int) ($current['id'] ?? 0));
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể xóa mềm coupon lúc này.', 'coupon_delete_failed');
        }

        $this->auditMutation($actor, 'delete_via_ai', 'coupon', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'coupons',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã xóa mềm coupon `' . (string) ($current['code'] ?? '') . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'coupon_deleted',
                    'coupon_id' => (int) ($current['id'] ?? 0),
                ],
            ]
        );
    }
    private function executeUserStatusUpdate(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('users', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new User($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'User không còn tồn tại để cập nhật.', 'user_status_failed');
        }

        $payload = $this->userPayloadFromCurrent($current);
        $payload['status'] = (string) ($draft['after']['status'] ?? 'active');
        $ok = $model->updateByAdmin((int) ($current['id'] ?? 0), $payload);
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể cập nhật trạng thái user lúc này.', 'user_status_failed');
        }

        $this->auditMutation($actor, 'update_status_via_ai', 'user', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'users',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã cập nhật trạng thái user `' . (string) ($current['email'] ?? '') . '` sang `' . (string) ($draft['after']['status'] ?? 'active') . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'user_updated',
                    'user_id' => (int) ($current['id'] ?? 0),
                    'email' => (string) ($current['email'] ?? ''),
                ],
            ]
        );
    }

    private function executeUserRoleUpdate(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('users', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new User($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'User không còn tồn tại để cập nhật.', 'user_role_failed');
        }

        $payload = $this->userPayloadFromCurrent($current);
        $payload['role_id'] = (int) ($draft['after']['role_id'] ?? 2);
        $ok = $model->updateByAdmin((int) ($current['id'] ?? 0), $payload);
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể cập nhật role user lúc này.', 'user_role_failed');
        }

        $this->auditMutation($actor, 'update_role_via_ai', 'user', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'users',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã đổi role user `' . (string) ($current['email'] ?? '') . '` sang `' . (string) ($draft['after']['role_name'] ?? 'user') . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'user_updated',
                    'user_id' => (int) ($current['id'] ?? 0),
                    'email' => (string) ($current['email'] ?? ''),
                ],
            ]
        );
    }

    private function executeUserDelete(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('users', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new User($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'User không còn tồn tại để xóa.', 'user_delete_failed');
        }

        if ((int) ($current['id'] ?? 0) === (int) (Auth::id() ?? 0)) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể xóa tài khoản đang đăng nhập qua AI.', 'user_delete_blocked');
        }

        $ok = $model->delete((int) ($current['id'] ?? 0));
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể xóa mềm user lúc này.', 'user_delete_failed');
        }

        $this->auditMutation($actor, 'delete_via_ai', 'user', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'users',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã xóa mềm user `' . (string) ($current['email'] ?? '') . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'user_deleted',
                    'user_id' => (int) ($current['id'] ?? 0),
                ],
            ]
        );
    }

    private function executeOrderStatusUpdate(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('orders', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new Order($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'Đơn không còn tồn tại để cập nhật.', 'order_status_failed');
        }

        $status = (string) ($draft['after']['status'] ?? '');
        $ok = $model->updateStatus((int) ($current['id'] ?? 0), $status);
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể cập nhật trạng thái đơn lúc này.', 'order_status_failed');
        }

        $this->auditMutation($actor, 'update_status_via_ai', 'order', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'orders',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã chuyển đơn `' . (string) ($current['order_code'] ?? '') . '` sang `' . order_status_label($status) . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'order_status_updated',
                    'order_id' => (int) ($current['id'] ?? 0),
                    'order_code' => (string) ($current['order_code'] ?? ''),
                    'previous_status' => (string) ($draft['before']['status'] ?? ''),
                    'previous_status_label' => order_status_label((string) ($draft['before']['status'] ?? '')),
                    'status' => $status,
                    'status_label' => order_status_label($status),
                ],
            ]
        );
    }

    private function executeOrderDelete(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('orders', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new Order($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'Đơn không còn tồn tại để xóa.', 'order_delete_failed');
        }

        $ok = $model->delete((int) ($current['id'] ?? 0));
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể xóa mềm đơn lúc này.', 'order_delete_failed');
        }

        $this->auditMutation($actor, 'delete_via_ai', 'order', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'orders',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã xóa mềm đơn `' . (string) ($current['order_code'] ?? '') . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'order_deleted',
                    'order_id' => (int) ($current['id'] ?? 0),
                    'order_code' => (string) ($current['order_code'] ?? ''),
                ],
            ]
        );
    }

    private function executeFeedbackStatusUpdate(array $draft, array $actor, array $scope): array
    {
        $guard = $this->guardExecuteDraft('feedback', $draft, $scope, $actor);
        if ($guard !== null) {
            return $guard;
        }

        $model = new CustomerFeedback($this->config);
        $current = $model->find((int) ($draft['entity_id'] ?? 0));
        if (!$current) {
            return $this->failedActionWithAudit($draft, $actor, 'Feedback không còn tồn tại để cập nhật.', 'feedback_update_failed');
        }

        $ok = $model->updateWorkflow((int) ($current['id'] ?? 0), (array) ($draft['after'] ?? []));
        if (!$ok) {
            return $this->failedActionWithAudit($draft, $actor, 'Không thể cập nhật feedback lúc này.', 'feedback_update_failed');
        }

        $this->auditMutation($actor, 'update_workflow_via_ai', 'feedback', (int) ($current['id'] ?? 0), [
            'source' => 'admin_ai',
            'module' => 'feedback',
            'result' => 'success',
            'before' => $draft['before'] ?? [],
            'after' => $draft['after'] ?? [],
        ]);

        return $this->actionEnvelope(
            'Đã cập nhật feedback `' . (string) ($current['feedback_code'] ?? '') . '` sang trạng thái `' . (string) ($draft['after']['status'] ?? 'reviewing') . '`.',
            'direct_admin_action',
            [
                'refresh_summary' => true,
                'mutation' => [
                    'type' => 'feedback_updated',
                    'feedback_id' => (int) ($current['id'] ?? 0),
                    'feedback_code' => (string) ($current['feedback_code'] ?? ''),
                    'status' => (string) ($draft['after']['status'] ?? 'reviewing'),
                ],
            ]
        );
    }
}
