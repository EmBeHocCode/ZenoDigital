<?php

namespace App\Services;

use App\Core\Auth;
use App\Models\AdminAuditLog;
use App\Models\Coupon;
use App\Models\CustomerFeedback;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\WalletTransaction;

class AdminAiIntentService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function resolve(string $message, array $languageAnalysis = [], array $actor = [], array $backofficeScope = []): array
    {
        $original = trim($message);
        $normalized = trim((string) ($languageAnalysis['normalized_text'] ?? $message));
        $haystack = $this->ascii($normalized !== '' ? $normalized : $original);

        if ($haystack === '') {
            return ['action' => 'pass_through'];
        }

        if ($this->isCancelCommand($haystack)) {
            return [
                'action' => 'cancel',
                'intent' => 'mutation_cancel',
                'confidence' => 'high',
            ];
        }

        if ($this->isConfirmCommand($haystack)) {
            return [
                'action' => 'confirm',
                'intent' => 'mutation_confirm',
                'confidence' => 'high',
            ];
        }

        if ($this->containsAny($haystack, ['doanh thu hom nay', 'tom tat doanh thu hom nay', 'revenue hom nay'])) {
            return [
                'action' => 'execute',
                'intent' => 'revenue_today',
                'confidence' => 'high',
            ];
        }

        if ($this->containsAny($haystack, ['feedback moi', 'phan hoi moi', 'feedback moi co gi'])) {
            return [
                'action' => 'execute',
                'intent' => 'latest_feedback',
                'confidence' => 'high',
            ];
        }

        if ($this->containsAny($haystack, ['coupon hien tai', 'coupon hien gio', 'coupon the nao', 'tinh trang coupon hien tai'])) {
            return [
                'action' => 'execute',
                'intent' => 'current_coupons',
                'confidence' => 'high',
            ];
        }

        if ($this->containsAny($haystack, ['san pham nao dang ban chay', 'san pham ban chay', 'top san pham', 'goi nao dang ban chay'])) {
            return [
                'action' => 'execute',
                'intent' => 'top_products',
                'confidence' => 'high',
            ];
        }

        if ($this->containsAny($haystack, ['giao dich vi', 'wallet transaction', 'giao dich thanh toan', 'nap tien gan day', 'lich su giao dich vi'])) {
            return [
                'action' => 'execute',
                'intent' => 'wallet_transactions',
                'confidence' => 'high',
            ];
        }

        if ($this->containsAny($haystack, ['cau hinh rank', 'xem rank', 'rank hien tai', 'rank settings'])) {
            return [
                'action' => 'execute',
                'intent' => 'rank_overview',
                'confidence' => 'high',
            ];
        }

        if ($this->containsAny($haystack, ['cai dat he thong', 'system settings', 'settings hien tai'])) {
            return [
                'action' => 'execute',
                'intent' => 'settings_overview',
                'confidence' => 'medium',
            ];
        }

        if ($this->containsAny($haystack, ['audit log', 'nhat ky audit', 'log admin gan day'])) {
            return [
                'action' => 'execute',
                'intent' => 'audit_log_recent',
                'confidence' => 'high',
            ];
        }

        if ($this->containsAny($haystack, ['sql manager', 'schema health', 'tinh trang sql', 'module health'])) {
            return [
                'action' => 'execute',
                'intent' => 'sql_health_summary',
                'confidence' => 'medium',
            ];
        }

        if ($this->containsAny($haystack, ['homepage', 'dua len homepage'])) {
            return [
                'action' => 'execute',
                'intent' => 'homepage_spotlight',
                'confidence' => 'medium',
            ];
        }

        if ($this->containsAny($haystack, ['capacity', 'ton kho', 'tồn kho', 'nhap hang', 'nhập hàng', 'het slot', 'hết slot', 'restock'])) {
            return [
                'action' => 'execute',
                'intent' => 'capacity_overview',
                'confidence' => 'high',
            ];
        }

        $mutationDecision = $this->resolveMutationIntent($original !== '' ? $original : $normalized, $haystack);
        if ($mutationDecision !== null) {
            return $mutationDecision;
        }

        if ($this->looksLikeOrderStatusUpdate($haystack)) {
            $orderCode = $this->extractOrderCode($original !== '' ? $original : $normalized);
            $status = $this->detectOrderStatus($haystack);

            if ($orderCode === '') {
                return [
                    'action' => 'clarify',
                    'intent' => 'order_status_update',
                    'question' => 'Bạn muốn đổi trạng thái cho mã đơn nào?',
                    'confidence' => 'high',
                ];
            }

            if ($status === '') {
                return [
                    'action' => 'clarify',
                    'intent' => 'order_status_update',
                    'question' => 'Chuyển đơn ' . $orderCode . ' sang trạng thái nào?',
                    'confidence' => 'high',
                ];
            }

            return [
                'action' => 'preview',
                'intent' => 'order_status_update',
                'params' => [
                    'order_code' => $orderCode,
                    'status' => $status,
                ],
                'confidence' => 'high',
            ];
        }

        if ($this->looksLikeOrderQuery($haystack)) {
            $status = $this->detectOrderStatus($haystack);
            $datePreset = $this->detectDatePreset($haystack);

            if ($status === '' && $datePreset === '' && !$this->containsAny($haystack, ['ma don', 'ord-'])) {
                return [
                    'action' => 'clarify',
                    'intent' => 'orders_generic',
                    'question' => $this->clarifyOrderQuestion($actor),
                    'confidence' => 'high',
                ];
            }

            return [
                'action' => 'execute',
                'intent' => 'orders_list',
                'params' => [
                    'status' => $status !== '' ? $status : 'pending',
                    'date_preset' => $datePreset,
                ],
                'confidence' => $status !== '' ? 'high' : 'medium',
            ];
        }

        return ['action' => 'pass_through'];
    }

    public function execute(array $decision, array $actor = [], array $backofficeScope = []): array
    {
        $intent = (string) ($decision['intent'] ?? '');
        $params = (array) ($decision['params'] ?? []);

        return match ($intent) {
            'orders_list' => $this->executeOrdersList($params),
            'revenue_today' => $this->executeRevenueToday(),
            'latest_feedback' => $this->executeLatestFeedback(),
            'current_coupons' => $this->executeCurrentCoupons(),
            'top_products' => $this->executeTopProducts($backofficeScope),
            'homepage_spotlight' => $this->executeHomepageSpotlight($backofficeScope),
            'capacity_overview' => $this->executeCapacityOverview($backofficeScope),
            'wallet_transactions' => $this->executeWalletTransactions($backofficeScope),
            'rank_overview' => $this->executeRankOverview($backofficeScope),
            'settings_overview' => $this->executeSettingsOverview($backofficeScope),
            'audit_log_recent' => $this->executeAuditLogRecent($backofficeScope),
            'sql_health_summary' => $this->executeSqlHealthSummary($backofficeScope),
            default => [
                'reply' => 'Hiện Meow chưa có fast-path phù hợp cho yêu cầu này, nên sẽ chuyển qua phân tích mở rộng.',
                'meta' => [
                    'provider' => 'admin-data-engine',
                    'mode' => 'direct_admin_read',
                    'source' => 'shop-data',
                    'is_fallback' => false,
                    'intent' => $intent !== '' ? $intent : 'unknown',
                ],
            ],
        };
    }

    private function executeOrdersList(array $params): array
    {
        $status = validate_enum((string) ($params['status'] ?? 'pending'), ['pending', 'paid', 'processing', 'completed', 'cancelled'], 'pending');
        $datePreset = (string) ($params['date_preset'] ?? '');
        $orderModel = new Order($this->config);
        $summary = $orderModel->summaryByFilters([
            'status' => $status,
            'date_preset' => $datePreset,
        ]);
        $orders = $orderModel->latestByFilters([
            'status' => $status,
            'date_preset' => $datePreset,
        ], 5);

        $scopeLabel = $datePreset === 'today'
            ? 'hôm nay'
            : ($datePreset === 'last_7_days' ? '7 ngày gần nhất' : 'hiện tại');
        $title = 'Đơn `' . order_status_label($status) . '` ' . $scopeLabel . ' hiện có ' . (int) ($summary['total_orders'] ?? 0) . ' đơn.';

        if ($orders === []) {
            $reply = $title . "\nKhông có đơn nào phù hợp với bộ lọc này.";
        } else {
            $lines = array_map(static function (array $row): string {
                return '- ' . (string) ($row['order_code'] ?? 'N/A')
                    . ' · ' . format_money((float) ($row['total_amount'] ?? 0))
                    . ' · ' . (string) ($row['created_at'] ?? '');
            }, $orders);

            $reply = $title
                . "\n" . implode("\n", $lines)
                . "\nNext action: mở " . base_url('admin/orders?status=' . urlencode($status)) . ' để xử lý các đơn cũ nhất trước.';
        }

        return $this->directEnvelope('orders_list', $reply);
    }

    private function executeOrderStatusUpdate(array $params, array $actor, array $backofficeScope): array
    {
        if (empty($backofficeScope['can_manage_orders']) || !Auth::can('admin.orders.manage')) {
            return $this->directEnvelope(
                'order_status_update',
                'Phiên này chưa có quyền đổi trạng thái đơn hàng. Cần quyền quản lý đơn để thực hiện thao tác ghi.',
                'direct_admin_action'
            );
        }

        $healthGuard = new ModuleHealthGuardService($this->config);
        if (!$healthGuard->isHealthy('orders')) {
            return $this->directEnvelope(
                'order_status_update',
                $healthGuard->messageFor('orders', 'write'),
                'direct_admin_action'
            );
        }

        $orderCode = $this->extractOrderCode((string) ($params['order_code'] ?? ''));
        $status = validate_enum(
            (string) ($params['status'] ?? ''),
            ['pending', 'paid', 'processing', 'completed', 'cancelled'],
            ''
        );

        if ($orderCode === '' || $status === '') {
            return $this->directEnvelope(
                'order_status_update',
                'Thiếu mã đơn hoặc trạng thái đích nên chưa thể cập nhật.',
                'direct_admin_action'
            );
        }

        $orderModel = new Order($this->config);
        $order = $orderModel->findByOrderCode($orderCode);

        if (!$order) {
            return $this->directEnvelope(
                'order_status_update',
                'Không tìm thấy đơn `' . $orderCode . '` trong hệ thống.',
                'direct_admin_action'
            );
        }

        $currentStatus = (string) ($order['status'] ?? '');
        if ($currentStatus === $status) {
            return $this->directEnvelope(
                'order_status_update',
                'Đơn `' . $orderCode . '` đang ở trạng thái `' . order_status_label($status) . '`, không cần cập nhật thêm.',
                'direct_admin_action'
            );
        }

        $orderId = (int) ($order['id'] ?? 0);
        $updated = $orderModel->updateStatus($orderId, $status);

        if (!$updated) {
            return $this->directEnvelope(
                'order_status_update',
                'Không thể cập nhật trạng thái cho đơn `' . $orderCode . '` lúc này.',
                'direct_admin_action'
            );
        }

        admin_audit('update_status_via_ai', 'order', $orderId, [
            'order_code' => $orderCode,
            'from_status' => $currentStatus,
            'to_status' => $status,
            'actor_id' => (int) ($actor['actor_id'] ?? 0),
            'source' => 'admin_ai',
        ]);

        return $this->directEnvelope(
            'order_status_update',
            'Đã chuyển đơn `' . $orderCode . '` từ `' . order_status_label($currentStatus) . '` sang `' . order_status_label($status) . '`.'
            . "\nNext action: rà tiếp đơn vừa cập nhật để xử lý bước vận hành kế tiếp.",
            'direct_admin_action',
            [
                'mutation' => [
                    'type' => 'order_status_updated',
                    'order_id' => $orderId,
                    'order_code' => $orderCode,
                    'previous_status' => $currentStatus,
                    'previous_status_label' => order_status_label($currentStatus),
                    'status' => $status,
                    'status_label' => order_status_label($status),
                ],
                'refresh_summary' => true,
            ]
        );
    }

    private function executeRevenueToday(): array
    {
        $orderModel = new Order($this->config);
        $todayRevenue = $orderModel->todayRevenue();
        $todayOrders = $orderModel->todayOrdersCount();
        $pendingOrders = $orderModel->countByStatus('pending');

        $reply = 'Doanh thu hôm nay đang là ' . format_money($todayRevenue)
            . ' từ ' . (int) $todayOrders . ' đơn trong ngày.'
            . "\nĐơn pending toàn hệ thống hiện là " . (int) $pendingOrders . ' đơn.'
            . "\nNext action: nếu cần, mình có thể bóc tiếp top gói cloud đang kéo doanh thu hôm nay.";

        return $this->directEnvelope('revenue_today', $reply);
    }

    private function executeLatestFeedback(): array
    {
        $feedbackModel = new CustomerFeedback($this->config);
        $feedbackPage = $feedbackModel->paginated('', 'new', '', '', '', 1, 5);
        $feedbackRows = (array) ($feedbackPage['data'] ?? []);
        $total = (int) (($feedbackPage['meta']['total'] ?? 0));

        if ($feedbackRows === []) {
            $reply = 'Hiện không có feedback mới chưa xử lý.'
                . "\nNext action: có thể chuyển sang rà feedback negative cũ hơn hoặc kiểm tra đơn pending.";
            return $this->directEnvelope('latest_feedback', $reply);
        }

        $lines = array_map(static function (array $row): string {
            $target = (string) ($row['product_name'] ?? $row['order_code'] ?? 'Không có liên kết');
            return '- ' . (string) ($row['feedback_code'] ?? 'N/A')
                . ' · ' . (string) ($row['sentiment'] ?? 'neutral')
                . ' · ' . $target
                . ' · ' . (string) ($row['created_at'] ?? '');
        }, $feedbackRows);

        $reply = 'Feedback mới chưa xử lý hiện có ' . $total . ' phản hồi.'
            . "\n" . implode("\n", $lines)
            . "\nNext action: ưu tiên các phản hồi negative hoặc cần follow-up trước.";

        return $this->directEnvelope('latest_feedback', $reply);
    }

    private function executeCurrentCoupons(): array
    {
        $couponModel = new Coupon($this->config);
        $summary = $couponModel->summary();
        $latestCoupons = $couponModel->latest(5);

        if ((int) ($summary['total_coupons'] ?? 0) === 0) {
            $reply = 'Hiện chưa có coupon nào trong hệ thống.'
                . "\nNext action: nếu muốn, mình có thể gợi ý một coupon pilot cho nhóm Cloud/VPS.";
            return $this->directEnvelope('current_coupons', $reply);
        }

        $lines = array_map(static function (array $row): string {
            $expiresAt = (string) ($row['expires_at'] ?? '');
            return '- ' . (string) ($row['code'] ?? 'N/A')
                . ' · ' . (string) ($row['status'] ?? 'inactive')
                . ' · -' . (int) ($row['discount_percent'] ?? 0) . '%'
                . ($expiresAt !== '' ? ' · hết hạn ' . $expiresAt : '');
        }, $latestCoupons);

        $reply = 'Coupon hiện tại: '
            . (int) ($summary['active_coupons'] ?? 0) . ' active, '
            . (int) ($summary['inactive_coupons'] ?? 0) . ' inactive, '
            . (int) ($summary['expiring_soon'] ?? 0) . ' sắp hết hạn 7 ngày.'
            . "\n" . implode("\n", $lines)
            . "\nNext action: rà các coupon sắp hết hạn trước khi bật chiến dịch mới.";

        return $this->directEnvelope('current_coupons', $reply);
    }

    private function executeTopProducts(array $backofficeScope): array
    {
        $productModel = new Product($this->config);
        $recommendationService = new AiSalesRecommendationService($this->config);
        $topProducts = $productModel->topSelling(5);
        $recommendations = $recommendationService->build($backofficeScope);
        $coreBusiness = (array) ($recommendations['core_business'] ?? []);

        if ($topProducts === []) {
            return $this->directEnvelope('top_products', 'Hiện chưa có dữ liệu bán hàng đủ để xếp hạng sản phẩm bán chạy.');
        }

        $lines = array_map(static function (array $row): string {
            return '- ' . (string) ($row['name'] ?? 'N/A')
                . ' · ' . (int) ($row['sold_qty'] ?? 0) . ' lượt bán'
                . ' · ' . format_money((float) ($row['sold_revenue'] ?? 0));
        }, $topProducts);

        $cloudNote = '';
        if ((int) ($coreBusiness['cloud_product_count'] ?? 0) > 0) {
            $cloudNote = "\nCloud/VPS vẫn là core business: "
                . (int) ($coreBusiness['cloud_product_count'] ?? 0)
                . ' SKU, chiếm '
                . (int) ($coreBusiness['catalog_share_percent'] ?? 0)
                . '% catalog active.';
        }

        $reply = 'Top sản phẩm bán chạy hiện tại:'
            . "\n" . implode("\n", $lines)
            . $cloudNote
            . "\nNext action: ưu tiên đẩy 1-2 gói cloud đầu danh sách lên homepage và quick prompt.";

        return $this->directEnvelope('top_products', $reply);
    }

    private function executeHomepageSpotlight(array $backofficeScope): array
    {
        $recommendationService = new AiSalesRecommendationService($this->config);
        $recommendations = $recommendationService->build($backofficeScope);
        $homepage = array_slice((array) (($recommendations['recommendations'] ?? [])['homepage'] ?? []), 0, 3);

        if ($homepage === []) {
            return $this->directEnvelope('homepage_spotlight', 'Hiện chưa có đủ tín hiệu để chốt slot homepage từ snapshot hiện tại.');
        }

        $lines = array_map(static function (array $row): string {
            return '- ' . (string) ($row['slot'] ?? 'Homepage')
                . ': ' . (string) ($row['product_name'] ?? 'N/A')
                . ' · ' . (string) ($row['reason'] ?? '');
        }, $homepage);

        $reply = 'Các gói nên đưa lên homepage từ snapshot hiện tại:'
            . "\n" . implode("\n", $lines)
            . "\nNext action: giữ tối đa 2 gói cloud entry/mid-tier ở hero để tránh loãng thông điệp.";

        return $this->directEnvelope('homepage_spotlight', $reply);
    }

    private function executeCapacityOverview(array $backofficeScope): array
    {
        $recommendationService = new AiSalesRecommendationService($this->config);
        $payload = $recommendationService->build($backofficeScope);
        $capacityItems = array_slice((array) (($payload['recommendations'] ?? [])['capacity'] ?? []), 0, 3);
        $missingCapacityFields = array_values(array_map('strval', (array) (($payload['data_gaps'] ?? [])['missing_capacity_fields'] ?? [])));
        $insufficientCapacityFields = array_values(array_map('strval', (array) (($payload['data_gaps'] ?? [])['insufficient_capacity_fields'] ?? [])));

        if ($missingCapacityFields !== [] || $insufficientCapacityFields !== []) {
            $details = [];
            if ($missingCapacityFields !== []) {
                $details[] = 'thiếu cột schema: ' . implode(', ', $missingCapacityFields);
            }
            if ($insufficientCapacityFields !== []) {
                $details[] = 'chưa có dữ liệu vận hành tại: ' . implode(', ', $insufficientCapacityFields);
            }

            return $this->directEnvelope(
                'capacity_overview',
                'Hiện chưa thể kết luận nhập hàng/capacity vì ' . implode('; ', $details) . "."
                . "\nNext action: cập nhật đủ schema và dữ liệu cho các cột này trước khi bật cảnh báo tồn kho/slot."
            );
        }

        if ($capacityItems === []) {
            return $this->directEnvelope(
                'capacity_overview',
                'Snapshot hiện tại chưa có SKU nào chạm ngưỡng cảnh báo tồn kho/capacity.'
                . "\nNext action: tiếp tục theo dõi stock/capacity theo ngày, ưu tiên nhóm Cloud/VPS."
            );
        }

        $lines = array_map(static function (array $row): string {
            return '- ' . (string) ($row['product_name'] ?? 'N/A')
                . ' · ' . (string) ($row['reason'] ?? 'chưa có lý do')
                . ' · Next: ' . (string) ($row['next_action'] ?? 'theo dõi thêm');
        }, $capacityItems);

        $reply = 'Cảnh báo nhập hàng/capacity từ snapshot hiện tại:'
            . "\n" . implode("\n", $lines)
            . "\nLưu ý: đây là phân tích theo dữ liệu thật trong schema hiện có, không suy diễn lời/lỗ.";

        return $this->directEnvelope('capacity_overview', $reply);
    }

    private function executeWalletTransactions(array $backofficeScope): array
    {
        if (empty($backofficeScope['can_view_payments'])) {
            return $this->directEnvelope(
                'wallet_transactions',
                'Phiên này chưa có quyền xem giao dịch ví / thanh toán.',
                'mutation_blocked'
            );
        }

        $walletModel = new WalletTransaction($this->config);
        $rows = $walletModel->recentForAdmin(5);

        if ($rows === []) {
            return $this->directEnvelope('wallet_transactions', 'Hiện chưa có giao dịch ví nào trong hệ thống.');
        }

        $lines = array_map(static function (array $row): string {
            return '- ' . (string) ($row['transaction_code'] ?? 'N/A')
                . ' · ' . (string) ($row['full_name'] ?? $row['email'] ?? 'N/A')
                . ' · ' . format_money((float) ($row['amount'] ?? 0))
                . ' · ' . (string) ($row['status'] ?? 'completed');
        }, $rows);

        return $this->directEnvelope(
            'wallet_transactions',
            "Giao dịch ví / thanh toán gần nhất:\n" . implode("\n", $lines)
            . "\nNext action: rà các giao dịch pending hoặc failed trước nếu có."
        );
    }

    private function executeRankOverview(array $backofficeScope): array
    {
        if (empty($backofficeScope['can_view_rank'])) {
            return $this->directEnvelope('rank_overview', 'Phiên này chưa có quyền xem cấu hình rank.', 'mutation_blocked');
        }

        $lines = [
            '- Uncommon: ' . (int) app_setting('rank_uncommon_points', (int) app_setting('rank_silver_points', 500)) . ' điểm · giảm ' . (int) app_setting('rank_uncommon_discount', (int) app_setting('rank_silver_discount', 3)) . '%',
            '- Rare: ' . (int) app_setting('rank_rare_points', (int) app_setting('rank_gold_points', 1500)) . ' điểm · giảm ' . (int) app_setting('rank_rare_discount', (int) app_setting('rank_gold_discount', 5)) . '%',
            '- Epic: ' . (int) app_setting('rank_epic_points', (int) app_setting('rank_platinum_points', 3000)) . ' điểm · giảm ' . (int) app_setting('rank_epic_discount', (int) app_setting('rank_platinum_discount', 8)) . '%',
            '- Legendary: ' . (int) app_setting('rank_legendary_points', (int) app_setting('rank_diamond_points', 6000)) . ' điểm · giảm ' . (int) app_setting('rank_legendary_discount', (int) app_setting('rank_diamond_discount', 12)) . '%',
            '- Mythic: ' . (int) app_setting('rank_mythic_points', 10000) . ' điểm · giảm ' . (int) app_setting('rank_mythic_discount', 18) . '%',
        ];

        return $this->directEnvelope(
            'rank_overview',
            "Cấu hình rank hiện tại:\n" . implode("\n", $lines)
        );
    }

    private function executeSettingsOverview(array $backofficeScope): array
    {
        if (empty($backofficeScope['can_view_settings'])) {
            return $this->directEnvelope('settings_overview', 'Phiên này chưa có quyền xem cài đặt hệ thống.', 'mutation_blocked');
        }

        $settings = (new Setting($this->config))->all();
        $reply = 'Cài đặt hệ thống an toàn để rà nhanh:'
            . "\n- Site name: " . normalize_public_brand_name((string) ($settings['site_name'] ?? app_site_name()))
            . "\n- Contact email: " . (string) ($settings['contact_email'] ?? 'chưa đặt')
            . "\n- Contact phone: " . (string) ($settings['contact_phone'] ?? 'chưa đặt')
            . "\n- Maintenance mode: " . (!empty($settings['maintenance_mode']) && (string) $settings['maintenance_mode'] === '1' ? 'bật' : 'tắt');

        return $this->directEnvelope('settings_overview', $reply);
    }

    private function executeAuditLogRecent(array $backofficeScope): array
    {
        if (empty($backofficeScope['can_view_audit'])) {
            return $this->directEnvelope('audit_log_recent', 'Phiên này chưa có quyền xem audit log.', 'mutation_blocked');
        }

        $rows = (new AdminAuditLog($this->config))->latest(5);
        if ($rows === []) {
            return $this->directEnvelope('audit_log_recent', 'Hiện chưa có audit log nào để tóm tắt.');
        }

        $lines = array_map(static function (array $row): string {
            return '- ' . (string) ($row['created_at'] ?? '')
                . ' · ' . (string) ($row['admin_name'] ?? $row['admin_email'] ?? 'N/A')
                . ' · ' . (string) ($row['action_name'] ?? 'N/A')
                . ' · ' . (string) ($row['entity_name'] ?? 'N/A');
        }, $rows);

        return $this->directEnvelope('audit_log_recent', "Audit log gần đây:\n" . implode("\n", $lines));
    }

    private function executeSqlHealthSummary(array $backofficeScope): array
    {
        if (empty($backofficeScope['can_view_sql'])) {
            return $this->directEnvelope('sql_health_summary', 'Phiên này chưa có quyền xem SQL Manager / schema health.', 'mutation_blocked');
        }

        $summary = (new SchemaHealthService($this->config))->summary();
        $unhealthy = (array) ($summary['unhealthy_modules'] ?? []);
        if ($unhealthy === []) {
            return $this->directEnvelope('sql_health_summary', 'Schema health hiện ổn. Chưa phát hiện module nào unhealthy.');
        }

        return $this->directEnvelope(
            'sql_health_summary',
            'Schema health đang có module unhealthy: ' . implode(', ', $unhealthy)
                . '. Next action: mở SQL Manager hoặc module tương ứng để rà lỗi schema trước khi ghi dữ liệu.'
        );
    }

    private function directEnvelope(string $intent, string $reply, string $mode = 'direct_admin_read', array $extra = []): array
    {
        return array_merge([
            'reply' => $reply,
            'meta' => [
                'provider' => 'admin-data-engine',
                'is_fallback' => false,
                'mode' => $mode,
                'source' => 'shop-data',
                'intent' => $intent,
            ],
        ], $extra);
    }

    private function isConfirmCommand(string $haystack): bool
    {
        return $this->containsAny($haystack, ['xac nhan', 'confirm', 'dong y chay', 'thuc hien di', 'ok chay']);
    }

    private function isCancelCommand(string $haystack): bool
    {
        return $this->containsAny($haystack, ['huy thao tac', 'bo thao tac', 'cancel thao tac', 'khong thuc hien', 'thoi bo qua']);
    }

    private function resolveMutationIntent(string $original, string $haystack): ?array
    {
        $resolvers = [
            $this->resolveProductMutation($original, $haystack),
            $this->resolveCategoryMutation($original, $haystack),
            $this->resolveCouponMutation($original, $haystack),
            $this->resolveUserMutation($original, $haystack),
            $this->resolveOrderDeleteMutation($original, $haystack),
            $this->resolveFeedbackMutation($original, $haystack),
            $this->resolveSettingsMutation($original, $haystack),
            $this->resolveRankMutation($original, $haystack),
        ];

        foreach ($resolvers as $decision) {
            if ($decision !== null) {
                return $decision;
            }
        }

        return null;
    }

    private function resolveProductMutation(string $original, string $haystack): ?array
    {
        if (preg_match('/(?:them|thêm|tao|tạo)\s+(?:san\s+pham|sản\s+phẩm)\s+(.+?)\s+(?:gia|giá)\s+([0-9\.\,\s]+(?:k|tr|trieu)?)(?:\s+(?:vao|vào|thuoc|thuộc)\s+(?:danh\s+muc|danh\s+mục)\s+(.+?))?$/iu', $original, $matches) === 1) {
            return [
                'action' => 'preview',
                'intent' => 'product_create',
                'params' => [
                    'name' => trim((string) ($matches[1] ?? '')),
                    'price' => $this->parseMoneyText((string) ($matches[2] ?? '')),
                    'category_identifier' => trim((string) ($matches[3] ?? '')),
                ],
                'confidence' => 'high',
            ];
        }

        if (preg_match('/(?:sua|sửa|doi|đổi|cap nhat|cập nhật)\s+(?:gia|giá)(?:\s+san\s+pham|\s+sản\s+phẩm)?\s+(.+?)\s+(?:thanh|thành|la|là|=|len)\s+([0-9\.\,\s]+(?:k|tr|trieu)?)/iu', $original, $matches) === 1) {
            return [
                'action' => 'preview',
                'intent' => 'product_price_update',
                'params' => [
                    'identifier' => trim((string) ($matches[1] ?? '')),
                    'price' => $this->parseMoneyText((string) ($matches[2] ?? '')),
                ],
                'confidence' => 'high',
            ];
        }

        if (preg_match('/(?:sua|sửa|doi|đổi|cap nhat|cập nhật)\s+(?:mo\s+ta\s+ngan|mô\s+tả\s+ngắn|mo\s+ta|mô\s+tả)(?:\s+san\s+pham|\s+sản\s+phẩm)?\s+(.+?)\s+(?:thanh|thành|la|là)\s+(.+)$/iu', $original, $matches) === 1) {
            $field = $this->containsAny($haystack, ['mo ta ngan', 'mô tả ngắn']) ? 'short_description' : 'description';

            return [
                'action' => 'preview',
                'intent' => 'product_description_update',
                'params' => [
                    'identifier' => trim((string) ($matches[1] ?? '')),
                    'field' => $field,
                    'value' => trim((string) ($matches[2] ?? '')),
                ],
                'confidence' => 'high',
            ];
        }

        if ($this->containsAny($haystack, ['bat san pham', 'tat san pham', 'an san pham', 'hien san pham'])) {
            $status = $this->containsAny($haystack, ['bat', 'hien']) ? 'active' : 'inactive';
            if (preg_match('/(?:bat|bật|tat|tắt|an|ẩn|hien|hiện)\s+(?:san\s+pham|sản\s+phẩm)\s+(.+)$/iu', $original, $matches) === 1) {
                return [
                    'action' => 'preview',
                    'intent' => 'product_status_update',
                    'params' => [
                        'identifier' => trim((string) ($matches[1] ?? '')),
                        'status' => $status,
                    ],
                    'confidence' => 'high',
                ];
            }
        }

        if (preg_match('/(?:xoa|xóa)\s+(?:san\s+pham|sản\s+phẩm)\s+(.+)$/iu', $original, $matches) === 1) {
            return [
                'action' => 'preview',
                'intent' => 'product_delete',
                'params' => [
                    'identifier' => trim((string) ($matches[1] ?? '')),
                ],
                'confidence' => 'high',
            ];
        }

        return null;
    }

    private function resolveCategoryMutation(string $original, string $haystack): ?array
    {
        if (preg_match('/(?:them|thêm|tao|tạo)\s+(?:danh\s+muc|danh\s+mục)\s+(.+)$/iu', $original, $matches) === 1) {
            return [
                'action' => 'preview',
                'intent' => 'category_create',
                'params' => [
                    'name' => trim((string) ($matches[1] ?? '')),
                ],
                'confidence' => 'high',
            ];
        }

        if (preg_match('/(?:sua|sửa|doi|đổi|cap nhat|cập nhật)\s+(?:danh\s+muc|danh\s+mục)\s+(.+?)\s+(?:thanh|thành|la|là)\s+(.+)$/iu', $original, $matches) === 1) {
            return [
                'action' => 'preview',
                'intent' => 'category_update',
                'params' => [
                    'identifier' => trim((string) ($matches[1] ?? '')),
                    'name' => trim((string) ($matches[2] ?? '')),
                ],
                'confidence' => 'high',
            ];
        }

        if (preg_match('/(?:xoa|xóa)\s+(?:danh\s+muc|danh\s+mục)\s+(.+)$/iu', $original, $matches) === 1) {
            return [
                'action' => 'preview',
                'intent' => 'category_delete',
                'params' => [
                    'identifier' => trim((string) ($matches[1] ?? '')),
                ],
                'confidence' => 'high',
            ];
        }

        return null;
    }

    private function resolveCouponMutation(string $original, string $haystack): ?array
    {
        if (preg_match('/(?:tao|tạo|them|thêm)\s+coupon\s+([A-Za-z0-9_-]+).*?(?:giam|giảm|-)\s*(\d{1,2})\s*%/iu', $original, $matches) === 1) {
            $maxUses = 0;
            $expiresAt = '';
            if (preg_match('/max\s+(\d+)/iu', $original, $maxMatches) === 1) {
                $maxUses = (int) ($maxMatches[1] ?? 0);
            }
            if (preg_match('/(?:het\s+han|hết\s+hạn)\s+([0-9\/\-\s:]+)/iu', $original, $dateMatches) === 1) {
                $expiresAt = trim((string) ($dateMatches[1] ?? ''));
            }

            return [
                'action' => 'preview',
                'intent' => 'coupon_create',
                'params' => [
                    'code' => trim((string) ($matches[1] ?? '')),
                    'discount_percent' => (int) ($matches[2] ?? 0),
                    'max_uses' => $maxUses,
                    'expires_at' => $expiresAt,
                    'status' => $this->containsAny($haystack, ['tat coupon', 'inactive']) ? 'inactive' : 'active',
                ],
                'confidence' => 'high',
            ];
        }

        if (preg_match('/(?:bat|bật|kich hoat|kích hoạt|tat|tắt|vo hieu hoa|vô hiệu hóa)\s+coupon\s+([A-Za-z0-9_-]+)/iu', $original, $matches) === 1) {
            return [
                'action' => 'preview',
                'intent' => 'coupon_status_update',
                'params' => [
                    'identifier' => trim((string) ($matches[1] ?? '')),
                    'status' => $this->containsAny($haystack, ['bat', 'kich hoat']) ? 'active' : 'inactive',
                ],
                'confidence' => 'high',
            ];
        }

        if (preg_match('/(?:xoa|xóa)\s+coupon\s+([A-Za-z0-9_-]+)/iu', $original, $matches) === 1) {
            return [
                'action' => 'preview',
                'intent' => 'coupon_delete',
                'params' => [
                    'identifier' => trim((string) ($matches[1] ?? '')),
                ],
                'confidence' => 'high',
            ];
        }

        return null;
    }

    private function resolveUserMutation(string $original, string $haystack): ?array
    {
        if (preg_match('/(?:khoa|khóa|mo|mở)\s+(?:user|nguoi dung|người dùng|tai khoan|tài khoản)\s+(.+)$/iu', $original, $matches) === 1) {
            return [
                'action' => 'preview',
                'intent' => 'user_status_update',
                'params' => [
                    'identifier' => trim((string) ($matches[1] ?? '')),
                    'status' => $this->containsAny($haystack, ['khoa']) ? 'blocked' : 'active',
                ],
                'confidence' => 'high',
            ];
        }

        if (preg_match('/(?:doi|đổi|cap nhat|cập nhật|set)\s+role\s+(?:user|nguoi dung|người dùng|tai khoan|tài khoản)\s+(.+?)\s+(?:sang|thanh|thành)\s+([A-Za-z_ -]+)$/iu', $original, $matches) === 1) {
            return [
                'action' => 'preview',
                'intent' => 'user_role_update',
                'params' => [
                    'identifier' => trim((string) ($matches[1] ?? '')),
                    'role_name' => trim((string) ($matches[2] ?? '')),
                ],
                'confidence' => 'high',
            ];
        }

        if (preg_match('/(?:xoa|xóa)\s+(?:user|nguoi dung|người dùng|tai khoan|tài khoản)\s+(.+)$/iu', $original, $matches) === 1) {
            return [
                'action' => 'preview',
                'intent' => 'user_delete',
                'params' => [
                    'identifier' => trim((string) ($matches[1] ?? '')),
                ],
                'confidence' => 'high',
            ];
        }

        return null;
    }

    private function resolveFeedbackMutation(string $original, string $haystack): ?array
    {
        if (preg_match('/(?:feedback|phan hoi|phản hồi)\s+(FDB-[A-Z0-9-]+).*(?:da xu ly|đã xử lý|resolved|reviewing|dang xem|đang xem|dong|đóng|closed|follow-?up)/iu', $original, $matches) !== 1) {
            return null;
        }

        $status = match (true) {
            $this->containsAny($haystack, ['da xu ly', 'resolved']) => 'resolved',
            $this->containsAny($haystack, ['dong', 'closed']) => 'closed',
            default => 'reviewing',
        };

        return [
            'action' => 'preview',
            'intent' => 'feedback_status_update',
            'params' => [
                'feedback_code' => trim((string) ($matches[1] ?? '')),
                'status' => $status,
                'needs_follow_up' => $this->containsAny($haystack, ['follow up', 'follow-up']),
            ],
            'confidence' => 'high',
        ];
    }

    private function resolveSettingsMutation(string $original, string $haystack): ?array
    {
        if ($this->containsAny($haystack, ['maintenance mode'])) {
            return [
                'action' => 'preview',
                'intent' => 'settings_update_safe',
                'params' => [
                    'setting_key' => 'maintenance_mode',
                    'setting_value' => $this->containsAny($haystack, ['bat', 'on']) ? '1' : '0',
                ],
                'confidence' => 'medium',
            ];
        }

        if (preg_match('/(?:doi|đổi|cap nhat|cập nhật)\s+ten\s+site\s+(?:thanh|thành|la|là)\s+(.+)$/iu', $original, $matches) === 1) {
            return [
                'action' => 'preview',
                'intent' => 'settings_update_safe',
                'params' => [
                    'setting_key' => 'site_name',
                    'setting_value' => trim((string) ($matches[1] ?? '')),
                ],
                'confidence' => 'medium',
            ];
        }

        return null;
    }

    private function resolveRankMutation(string $original, string $haystack): ?array
    {
        if (preg_match('/(?:cap nhat|cập nhật|doi|đổi)\s+rank\s+(uncommon|rare|epic|legendary|mythic)\s+(points|point|diem|điểm|discount)\s+(?:thanh|thành|la|là|=)\s+(\d+)/iu', $original, $matches) !== 1) {
            return null;
        }

        return [
            'action' => 'preview',
            'intent' => 'rank_update',
            'params' => [
                'rank_key' => strtolower(trim((string) ($matches[1] ?? ''))),
                'metric' => strtolower(trim((string) ($matches[2] ?? ''))),
                'value' => trim((string) ($matches[3] ?? '')),
            ],
            'confidence' => 'medium',
        ];
    }

    private function resolveOrderDeleteMutation(string $original, string $haystack): ?array
    {
        if (!$this->containsAny($haystack, ['xoa don', 'xóa đơn', 'xoa don hang', 'xóa đơn hàng', 'delete order'])) {
            return null;
        }

        $orderCode = $this->extractOrderCode($original);
        if ($orderCode === '') {
            return [
                'action' => 'clarify',
                'intent' => 'order_delete',
                'question' => 'Bạn muốn xóa mềm mã đơn nào?',
                'confidence' => 'high',
            ];
        }

        return [
            'action' => 'preview',
            'intent' => 'order_delete',
            'params' => [
                'order_code' => $orderCode,
            ],
            'confidence' => 'high',
        ];
    }

    private function parseMoneyText(string $value): float
    {
        $normalized = $this->ascii($value);
        $normalized = str_replace(' ', '', $normalized);

        if ($normalized === '') {
            return 0;
        }

        if (str_ends_with($normalized, 'trieu')) {
            $base = (float) str_replace(['trieu', ','], ['', '.'], $normalized);
            return $base * 1000000;
        }

        if (str_ends_with($normalized, 'tr')) {
            $base = (float) str_replace(['tr', ','], ['', '.'], $normalized);
            return $base * 1000000;
        }

        if (str_ends_with($normalized, 'k')) {
            $base = (float) str_replace(['k', ','], ['', '.'], $normalized);
            return $base * 1000;
        }

        $digits = preg_replace('/[^0-9.]/', '', $normalized) ?? '0';
        return (float) $digits;
    }

    private function looksLikeOrderQuery(string $haystack): bool
    {
        return $this->containsAny($haystack, [
            'xem don',
            'don hang',
            'don pending',
            'don cho xu ly',
            'danh sach don',
        ]);
    }

    private function looksLikeOrderStatusUpdate(string $haystack): bool
    {
        if (!$this->containsAny($haystack, ['ord-', 'don hang', 'don '])) {
            return false;
        }

        if ($this->containsAny($haystack, [
            'xem don',
            'danh sach don',
            'don pending',
            'don cho xu ly',
        ])) {
            return false;
        }

        return $this->containsAny($haystack, [
            'chuyen',
            'doi trang thai',
            'cap nhat trang thai',
            'set trang thai',
            'duyet don',
            'duyet',
            'xac nhan don',
            'huy don',
            'mark',
            'doi don',
            'cap nhat don',
        ]);
    }

    private function detectOrderStatus(string $haystack): string
    {
        return match (true) {
            $this->containsAny($haystack, ['pending', 'cho xu ly', 'don cho xu ly']) => 'pending',
            $this->containsAny($haystack, ['completed', 'hoan thanh', 'hoan tat']) => 'completed',
            $this->containsAny($haystack, ['paid', 'da thanh toan', 'xac nhan thanh toan']) => 'paid',
            $this->containsAny($haystack, ['cancelled', 'canceled', 'da huy', 'huy don']) => 'cancelled',
            $this->containsAny($haystack, ['processing', 'dang xu ly', 'duyet don', 'duyet', 'xac nhan don']) => 'processing',
            default => '',
        };
    }

    private function detectDatePreset(string $haystack): string
    {
        return match (true) {
            $this->containsAny($haystack, ['hom nay', 'trong ngay']) => 'today',
            $this->containsAny($haystack, ['7 ngay', 'bay ngay', 'tuan nay']) => 'last_7_days',
            default => '',
        };
    }

    private function clarifyOrderQuestion(array $actor): string
    {
        $safeAddressing = strtolower(trim((string) ($actor['safe_addressing'] ?? '')));
        $subject = match ($safeAddressing) {
            'anh' => 'Anh',
            'chị', 'chi' => 'Chị',
            default => 'Bạn',
        };

        return $subject . ' muốn xem đơn theo trạng thái nào: chờ xử lý, đã thanh toán, đang xử lý, hoàn thành hay đã hủy?';
    }

    private function extractOrderCode(string $text): string
    {
        if (preg_match('/\b(ord-[a-z0-9-]+)\b/i', $text, $matches) === 1) {
            return strtoupper((string) ($matches[1] ?? ''));
        }

        return '';
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

    private function ascii(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        if ($value === '') {
            return '';
        }

        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
            'đ' => 'd',
        ]);
        $value = preg_replace('/[^a-z0-9\s-]/', ' ', strtolower($value)) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
