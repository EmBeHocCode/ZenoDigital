<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Category;
use App\Models\Coupon;
use PDO;

class AiSalesRecommendationService
{
    private const VALID_ORDER_STATUSES = ['paid', 'processing', 'completed'];
    private const ANALYSIS_WINDOW_DAYS = 30;

    private array $config;
    private PDO $db;
    private Category $categoryModel;
    private Coupon $couponModel;
    private AiGuardService $guardService;
    private ?array $productColumns = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::getConnection($config['db']);
        $this->categoryModel = new Category($config);
        $this->couponModel = new Coupon($config);
        $this->guardService = new AiGuardService($config);
    }

    public function build(array $scope = []): array
    {
        $canViewFinance = !empty($scope['can_view_finance']);
        $categories = $this->categoryModel->all();
        $cloudCategories = array_values(array_filter($categories, fn (array $row): bool => $this->categoryModel->isCloudStorefrontCategory($row)));
        $cloudCategoryIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $cloudCategories);
        $products = $this->activeProducts();
        $cloudProducts = array_values(array_filter($products, static fn (array $row): bool => in_array((int) ($row['category_id'] ?? 0), $cloudCategoryIds, true)));
        $salesByProduct = $this->salesByProduct(self::ANALYSIS_WINDOW_DAYS);
        $salesByCategory = $this->salesByCategory(self::ANALYSIS_WINDOW_DAYS);
        $couponSummary = $this->normalizeCouponSummary($this->couponModel->summary());
        $latestCoupons = $this->couponModel->latest(10);
        $pairSignals = $this->coPurchasePairs(self::ANALYSIS_WINDOW_DAYS);
        $pendingCloudSignals = $this->pendingCloudSignals($cloudCategoryIds, self::ANALYSIS_WINDOW_DAYS);
        $missingProfitFields = $this->guardService->getMissingProfitFields();
        $missingCapacityFields = $this->guardService->getMissingCapacityFields();
        $insufficientProfitFields = $this->guardService->getInsufficientProfitFields();
        $insufficientCapacityFields = $this->guardService->getInsufficientCapacityFields();
        $profitDataGaps = array_values(array_unique(array_merge($missingProfitFields, $insufficientProfitFields)));
        $capacityDataGaps = array_values(array_unique(array_merge($missingCapacityFields, $insufficientCapacityFields)));
        $missingFinancialFields = array_values(array_unique(array_merge(
            $this->guardService->getMissingFinancialFields(),
            $profitDataGaps,
            $capacityDataGaps
        )));
        $cannotConfirm = [
            'lời/lỗ thực tế',
            'mức giảm tối đa an toàn theo biên lợi nhuận',
            'cross-sell pattern đủ mạnh để tự động hóa',
        ];
        if ($capacityDataGaps !== []) {
            $cannotConfirm[] = 'capacity hoặc stock risk';
        }
        $catalogSummary = $this->catalogSummary($products, $cloudProducts);
        $recommendations = [
            'push' => $this->buildPushRecommendations($cloudProducts, $salesByProduct, $canViewFinance),
            'homepage' => $this->buildHomepageRecommendations($cloudProducts, $salesByProduct, $canViewFinance),
            'upsell' => $this->buildUpsellRecommendations($cloudProducts, $salesByProduct, $canViewFinance),
            'cross_sell' => $this->buildCrossSellRecommendations($pairSignals),
            'promotions' => $this->buildPromotionRecommendations($cloudProducts, $salesByProduct, $couponSummary, $latestCoupons),
            'coupon_actions' => $this->buildCouponActions($couponSummary, $latestCoupons),
            'capacity' => $this->buildCapacityRecommendations($cloudProducts, $salesByProduct, $pendingCloudSignals, $capacityDataGaps),
        ];

        return [
            'generated_at' => date('c'),
            'analysis_window_days' => self::ANALYSIS_WINDOW_DAYS,
            'advice_scope' => 'preliminary_only',
            'executive_summary' => $this->buildExecutiveSummary($catalogSummary, $salesByCategory, $salesByProduct, $couponSummary),
            'core_business' => [
                'primary_category' => $cloudCategories[0]['name'] ?? 'VPS / Cloud Server',
                'catalog_share_percent' => $catalogSummary['cloud_share_percent'],
                'cloud_product_count' => $catalogSummary['cloud_product_count'],
                'all_active_product_count' => $catalogSummary['all_active_product_count'],
                'reason' => 'Nhóm Cloud/VPS đang là mảng core vì chiếm phần lớn SKU active và storefront hiện xoay quanh nhóm này.',
            ],
            'data_used' => [
                'product_fields' => [
                    'category_id',
                    'name',
                    'slug',
                    'price',
                    'product_type',
                    'stock_qty',
                    'reorder_point',
                    'supplier_name',
                    'lead_time_days',
                    'cost_price',
                    'min_margin_percent',
                    'platform_fee_percent',
                    'payment_fee_percent',
                    'ads_cost_per_order',
                    'delivery_cost',
                    'capacity_limit',
                    'capacity_used',
                    'short_description',
                    'description',
                    'specs',
                    'image',
                    'stock_status',
                    'status',
                    'created_at',
                ],
                'order_fields' => ['status', 'created_at', 'total_amount', 'order_items.quantity', 'order_items.unit_price', 'order_items.total_price'],
                'coupon_fields' => ['code', 'description', 'discount_percent', 'max_uses', 'used_count', 'expires_at', 'status'],
                'coupon_rows' => count($latestCoupons),
                'valid_order_statuses' => self::VALID_ORDER_STATUSES,
                'catalog_summary' => $catalogSummary,
                'pending_cloud_signals' => $pendingCloudSignals,
            ],
            'coverage' => [
                'can_recommend' => [
                    'sản phẩm cloud/vps nên đẩy',
                    'thứ tự ưu tiên homepage cho cloud',
                    'upsell ladder theo giá và cấu hình hiện có',
                    'coupon pilot và khuyến mãi nhẹ ở mức sơ bộ',
                    'gợi ý nhập hàng/capacity ở mức dữ liệu hiện có',
                ],
                'cannot_confirm' => $cannotConfirm,
            ],
            'data_gaps' => [
                'missing_fields' => $missingFinancialFields,
                'missing_profit_fields' => $missingProfitFields,
                'missing_capacity_fields' => $missingCapacityFields,
                'insufficient_profit_fields' => $insufficientProfitFields,
                'insufficient_capacity_fields' => $insufficientCapacityFields,
                'notes' => $this->buildDataGapNotes(
                    $couponSummary,
                    $pairSignals,
                    $missingProfitFields,
                    $missingCapacityFields,
                    $insufficientProfitFields,
                    $insufficientCapacityFields
                ),
            ],
            'recommendations' => $recommendations,
            'action_queue' => $this->buildActionQueue($recommendations),
        ];
    }

    private function activeProducts(): array
    {
        $availableColumns = array_flip($this->availableProductColumns());
        $selectColumns = [
            'p.id',
            'p.category_id',
            'p.name',
            'p.slug',
            'p.price',
            'p.short_description',
            'p.description',
            'p.specs',
            'p.image',
            'p.stock_status',
            'p.status',
            'p.created_at',
        ];

        $optionalColumns = [
            'product_type' => 'p.product_type',
            'stock_qty' => 'p.stock_qty',
            'reorder_point' => 'p.reorder_point',
            'supplier_name' => 'p.supplier_name',
            'lead_time_days' => 'p.lead_time_days',
            'min_margin_percent' => 'p.min_margin_percent',
            'capacity_limit' => 'p.capacity_limit',
            'capacity_used' => 'p.capacity_used',
            'cost_price' => 'p.cost_price',
        ];

        foreach ($optionalColumns as $column => $select) {
            if (isset($availableColumns[$column])) {
                $selectColumns[] = $select;
            }
        }

        $selectColumns[] = 'c.name AS category_name';
        $selectColumns[] = 'c.slug AS category_slug';

        $stmt = $this->db->query("SELECT
                " . implode(",\n                ", $selectColumns) . "
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            WHERE p.deleted_at IS NULL
              AND p.status = 'active'
              AND c.deleted_at IS NULL
            ORDER BY p.price ASC, p.id ASC");

        $rows = $stmt->fetchAll() ?: [];

        return array_map(function (array $row): array {
            $row['parsed_specs'] = $this->parseProductSpecs($row);
            return $row;
        }, $rows);
    }

    private function salesByProduct(int $windowDays): array
    {
        $stmt = $this->db->prepare("SELECT
                p.id AS product_id,
                COUNT(DISTINCT CASE WHEN o.id IS NOT NULL THEN o.id END) AS order_count,
                COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS sold_qty,
                COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.total_price ELSE 0 END), 0) AS sold_revenue,
                MAX(CASE WHEN o.id IS NOT NULL THEN o.created_at ELSE NULL END) AS last_sold_at
            FROM products p
            LEFT JOIN order_items oi ON oi.product_id = p.id
            LEFT JOIN orders o ON o.id = oi.order_id
                AND o.deleted_at IS NULL
                AND o.status IN ('paid', 'processing', 'completed')
                AND o.created_at >= DATE_SUB(NOW(), INTERVAL :window_days DAY)
            WHERE p.deleted_at IS NULL
              AND p.status = 'active'
            GROUP BY p.id");
        $stmt->bindValue(':window_days', $windowDays, PDO::PARAM_INT);
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $map[(int) ($row['product_id'] ?? 0)] = [
                'order_count' => (int) ($row['order_count'] ?? 0),
                'sold_qty' => (int) ($row['sold_qty'] ?? 0),
                'sold_revenue' => (float) ($row['sold_revenue'] ?? 0),
                'last_sold_at' => $row['last_sold_at'] ?? null,
            ];
        }

        return $map;
    }

    private function salesByCategory(int $windowDays): array
    {
        $stmt = $this->db->prepare("SELECT
                c.id AS category_id,
                c.name AS category_name,
                c.slug AS category_slug,
                COUNT(DISTINCT CASE WHEN o.id IS NOT NULL THEN o.id END) AS order_count,
                COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS sold_qty,
                COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.total_price ELSE 0 END), 0) AS sold_revenue
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id
                AND p.deleted_at IS NULL
                AND p.status = 'active'
            LEFT JOIN order_items oi ON oi.product_id = p.id
            LEFT JOIN orders o ON o.id = oi.order_id
                AND o.deleted_at IS NULL
                AND o.status IN ('paid', 'processing', 'completed')
                AND o.created_at >= DATE_SUB(NOW(), INTERVAL :window_days DAY)
            WHERE c.deleted_at IS NULL
            GROUP BY c.id, c.name, c.slug
            ORDER BY sold_revenue DESC, sold_qty DESC, c.id ASC");
        $stmt->bindValue(':window_days', $windowDays, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): array {
            return [
                'category_id' => (int) ($row['category_id'] ?? 0),
                'category_name' => (string) ($row['category_name'] ?? ''),
                'category_slug' => (string) ($row['category_slug'] ?? ''),
                'order_count' => (int) ($row['order_count'] ?? 0),
                'sold_qty' => (int) ($row['sold_qty'] ?? 0),
                'sold_revenue' => (float) ($row['sold_revenue'] ?? 0),
            ];
        }, $stmt->fetchAll() ?: []);
    }

    private function coPurchasePairs(int $windowDays): array
    {
        $stmt = $this->db->prepare("SELECT
                p1.id AS product_a_id,
                p1.name AS product_a_name,
                c1.name AS product_a_category,
                c1.slug AS product_a_category_slug,
                p2.id AS product_b_id,
                p2.name AS product_b_name,
                c2.name AS product_b_category,
                c2.slug AS product_b_category_slug,
                COUNT(DISTINCT o.id) AS pair_orders
            FROM orders o
            INNER JOIN order_items oi1 ON oi1.order_id = o.id
            INNER JOIN order_items oi2 ON oi2.order_id = o.id AND oi1.product_id < oi2.product_id
            INNER JOIN products p1 ON p1.id = oi1.product_id
            INNER JOIN categories c1 ON c1.id = p1.category_id
            INNER JOIN products p2 ON p2.id = oi2.product_id
            INNER JOIN categories c2 ON c2.id = p2.category_id
            WHERE o.deleted_at IS NULL
              AND o.status IN ('paid', 'processing', 'completed')
              AND o.created_at >= DATE_SUB(NOW(), INTERVAL :window_days DAY)
            GROUP BY p1.id, p1.name, c1.name, c1.slug, p2.id, p2.name, c2.name, c2.slug
            ORDER BY pair_orders DESC, p1.id ASC, p2.id ASC");
        $stmt->bindValue(':window_days', $windowDays, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): array {
            return [
                'product_a_id' => (int) ($row['product_a_id'] ?? 0),
                'product_a_name' => (string) ($row['product_a_name'] ?? ''),
                'product_a_category' => (string) ($row['product_a_category'] ?? ''),
                'product_a_category_slug' => (string) ($row['product_a_category_slug'] ?? ''),
                'product_b_id' => (int) ($row['product_b_id'] ?? 0),
                'product_b_name' => (string) ($row['product_b_name'] ?? ''),
                'product_b_category' => (string) ($row['product_b_category'] ?? ''),
                'product_b_category_slug' => (string) ($row['product_b_category_slug'] ?? ''),
                'pair_orders' => (int) ($row['pair_orders'] ?? 0),
            ];
        }, $stmt->fetchAll() ?: []);
    }

    private function pendingCloudSignals(array $cloudCategoryIds, int $windowDays): array
    {
        if ($cloudCategoryIds === []) {
            return [];
        }

        $placeholders = [];
        foreach ($cloudCategoryIds as $index => $categoryId) {
            $placeholders[] = ':category_' . $index;
        }

        $stmt = $this->db->prepare("SELECT
                p.id AS product_id,
                p.name,
                COUNT(DISTINCT o.id) AS pending_orders,
                COALESCE(SUM(oi.total_price), 0) AS pending_amount,
                MAX(o.created_at) AS latest_pending_at
            FROM products p
            INNER JOIN order_items oi ON oi.product_id = p.id
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE p.deleted_at IS NULL
              AND p.status = 'active'
              AND p.category_id IN (" . implode(', ', $placeholders) . ")
              AND o.deleted_at IS NULL
              AND o.status = 'pending'
              AND o.created_at >= DATE_SUB(NOW(), INTERVAL :window_days DAY)
            GROUP BY p.id, p.name
            ORDER BY pending_amount DESC, pending_orders DESC, p.id ASC");
        foreach ($cloudCategoryIds as $index => $categoryId) {
            $stmt->bindValue(':category_' . $index, $categoryId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':window_days', $windowDays, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): array {
            return [
                'product_id' => (int) ($row['product_id'] ?? 0),
                'product_name' => (string) ($row['name'] ?? ''),
                'pending_orders' => (int) ($row['pending_orders'] ?? 0),
                'pending_amount' => (float) ($row['pending_amount'] ?? 0),
                'latest_pending_at' => $row['latest_pending_at'] ?? null,
            ];
        }, $stmt->fetchAll() ?: []);
    }

    private function catalogSummary(array $products, array $cloudProducts): array
    {
        $allActiveProductCount = count($products);
        $cloudProductCount = count($cloudProducts);
        $share = $allActiveProductCount > 0
            ? round(($cloudProductCount / $allActiveProductCount) * 100, 1)
            : 0;

        return [
            'all_active_product_count' => $allActiveProductCount,
            'cloud_product_count' => $cloudProductCount,
            'cloud_share_percent' => $share,
        ];
    }

    private function buildPushRecommendations(array $cloudProducts, array $salesByProduct, bool $canViewFinance): array
    {
        $candidates = [];

        foreach ($cloudProducts as $product) {
            $productId = (int) ($product['id'] ?? 0);
            $sales = $salesByProduct[$productId] ?? [
                'order_count' => 0,
                'sold_qty' => 0,
                'sold_revenue' => 0,
                'last_sold_at' => null,
            ];
            $price = (float) ($product['price'] ?? 0);
            $specs = (array) ($product['parsed_specs'] ?? []);
            $score = 0;
            $reasons = [];
            $confidence = 'medium';

            if (($sales['order_count'] ?? 0) >= 2) {
                $score += 120;
                $reasons[] = 'đã có ' . (int) $sales['order_count'] . ' đơn hợp lệ trong ' . self::ANALYSIS_WINDOW_DAYS . ' ngày gần đây';
                $confidence = 'high';
            } elseif (($sales['order_count'] ?? 0) === 1) {
                $score += 75;
                $reasons[] = 'đã có đơn hợp lệ gần đây nên có tín hiệu nhu cầu thật';
            }

            if ($price > 0 && $price <= 220000) {
                $score += 55;
                $reasons[] = 'giá vào cửa thấp, hợp làm gói mồi cho khách mới';
            } elseif ($price <= 350000) {
                $score += 35;
                $reasons[] = 'nằm ở tầng giá dễ chốt cho web bán hàng nhỏ';
            } elseif ($price <= 500000) {
                $score += 20;
                $reasons[] = 'mức giá mid-tier, phù hợp khách đã qua bước test';
            }

            if (($specs['ram_gb'] ?? 0) >= 8 && $price <= 500000) {
                $score += 25;
                $reasons[] = 'RAM 8GB giúp nhìn cấu hình chắc hơn nhưng chưa đẩy giá quá cao';
            }

            if (($specs['cpu_cores'] ?? 0) >= 4 && $price <= 500000) {
                $score += 20;
                $reasons[] = '4vCore tạo bậc nâng cấp rõ từ các gói entry';
            }

            if (in_array((string) ($specs['plan_type'] ?? ''), ['starter', 'basic'], true)) {
                $score += 15;
            }

            if (in_array((string) ($specs['plan_type'] ?? ''), ['business', 'nvme'], true)) {
                $score += 10;
            }

            if (str_contains((string) ($specs['cpu_profile'] ?? ''), 'ryzen')) {
                $score += 8;
                $reasons[] = 'tên gói có điểm nhấn Ryzen nên dễ làm thông điệp hiệu năng';
            }

            $candidates[] = [
                'score' => $score,
                'recommendation_type' => 'push',
                'product_id' => $productId,
                'product_name' => (string) ($product['name'] ?? ''),
                'category_name' => (string) ($product['category_name'] ?? 'VPS / Cloud Server'),
                'confidence' => $confidence,
                'reason' => $this->sentenceFromParts($reasons),
                'next_action' => $this->pushNextAction($product, $sales),
                'metrics' => $this->metricsPayload($product, $sales, $canViewFinance),
                'notes' => $this->notesFromProduct($product, $sales),
            ];
        }

        usort($candidates, static function (array $left, array $right): int {
            return ($right['score'] <=> $left['score'])
                ?: ((float) ($left['metrics']['current_price'] ?? 0) <=> (float) ($right['metrics']['current_price'] ?? 0));
        });

        return array_values(array_map(static function (array $item): array {
            unset($item['score']);
            return $item;
        }, array_slice($candidates, 0, 3)));
    }

    private function buildHomepageRecommendations(array $cloudProducts, array $salesByProduct, bool $canViewFinance): array
    {
        $entryProduct = null;
        $proofProduct = null;
        $upsellProduct = null;

        foreach ($cloudProducts as $product) {
            $sales = $salesByProduct[(int) ($product['id'] ?? 0)] ?? ['order_count' => 0, 'sold_qty' => 0, 'sold_revenue' => 0];
            $specs = (array) ($product['parsed_specs'] ?? []);

            if ($entryProduct === null) {
                $entryProduct = $product;
            }

            if ($proofProduct === null && (int) ($sales['order_count'] ?? 0) > 0 && (float) ($product['price'] ?? 0) <= 250000) {
                $proofProduct = $product;
            }

            if ($upsellProduct === null && (($specs['cpu_cores'] ?? 0) >= 4 || ($specs['ram_gb'] ?? 0) >= 8) && (float) ($product['price'] ?? 0) <= 350000) {
                $upsellProduct = $product;
            }
        }

        $proofProduct ??= $entryProduct;
        $upsellProduct ??= $cloudProducts[1] ?? $entryProduct;

        $slots = array_values(array_filter([
            ['slot' => 'Homepage slot 1', 'product' => $proofProduct, 'label' => 'proof'],
            ['slot' => 'Homepage slot 2', 'product' => $entryProduct, 'label' => 'entry'],
            ['slot' => 'Homepage slot 3', 'product' => $upsellProduct, 'label' => 'upsell'],
        ], static fn (array $row): bool => is_array($row['product'] ?? null)));

        $usedIds = [];
        $recommendations = [];

        foreach ($slots as $slot) {
            $product = (array) ($slot['product'] ?? []);
            $productId = (int) ($product['id'] ?? 0);
            if ($productId <= 0 || in_array($productId, $usedIds, true)) {
                continue;
            }

            $sales = $salesByProduct[$productId] ?? ['order_count' => 0, 'sold_qty' => 0, 'sold_revenue' => 0];
            $usedIds[] = $productId;
            $recommendations[] = [
                'recommendation_type' => 'homepage',
                'slot' => (string) ($slot['slot'] ?? 'Homepage'),
                'product_id' => $productId,
                'product_name' => (string) ($product['name'] ?? ''),
                'category_name' => (string) ($product['category_name'] ?? 'VPS / Cloud Server'),
                'confidence' => (int) ($sales['order_count'] ?? 0) > 0 ? 'high' : 'medium',
                'reason' => $this->homepageReason((string) ($slot['label'] ?? 'entry'), $product, $sales),
                'next_action' => $this->homepageNextAction((string) ($slot['slot'] ?? 'Homepage'), (string) ($slot['label'] ?? 'entry')),
                'metrics' => $this->metricsPayload($product, $sales, $canViewFinance),
                'notes' => [
                    'Ưu tiên giữ homepage cloud-first: gói mồi trước, gói nâng cấp ngay sau.',
                ],
            ];
        }

        return $recommendations;
    }

    private function buildUpsellRecommendations(array $cloudProducts, array $salesByProduct, bool $canViewFinance): array
    {
        $usedTargetIds = [];
        $recommendations = [];

        foreach ($cloudProducts as $product) {
            if ((float) ($product['price'] ?? 0) <= 0) {
                continue;
            }

            $target = $this->findUpsellTarget($product, $cloudProducts, $usedTargetIds);
            if ($target === null) {
                continue;
            }

            $sourceSpecs = (array) ($product['parsed_specs'] ?? []);
            $targetSpecs = (array) ($target['parsed_specs'] ?? []);
            $benefits = $this->describeSpecUpgrade($sourceSpecs, $targetSpecs);
            if ($benefits === []) {
                continue;
            }

            $sourceId = (int) ($product['id'] ?? 0);
            $targetId = (int) ($target['id'] ?? 0);
            $sourceSales = $salesByProduct[$sourceId] ?? ['order_count' => 0, 'sold_qty' => 0, 'sold_revenue' => 0];
            $targetSales = $salesByProduct[$targetId] ?? ['order_count' => 0, 'sold_qty' => 0, 'sold_revenue' => 0];
            $usedTargetIds[] = $targetId;

            $recommendations[] = [
                'recommendation_type' => 'upsell',
                'from_product_id' => $sourceId,
                'from_product_name' => (string) ($product['name'] ?? ''),
                'to_product_id' => $targetId,
                'to_product_name' => (string) ($target['name'] ?? ''),
                'confidence' => (int) ($sourceSales['order_count'] ?? 0) > 0 ? 'high' : 'medium',
                'reason' => 'Bậc giá tăng từ ' . (int) ($product['price'] ?? 0) . ' lên ' . (int) ($target['price'] ?? 0)
                    . ' và đổi được ' . implode(', ', array_slice($benefits, 0, 3)) . '.',
                'next_action' => 'Gắn block upsell trong homepage/chatbot từ `' . (string) ($product['name'] ?? '')
                    . '` sang `' . (string) ($target['name'] ?? '') . '` khi khách hỏi nhu cầu web bán hàng hoặc production.',
                'metrics' => [
                    'from' => $this->metricsPayload($product, $sourceSales, $canViewFinance),
                    'to' => $this->metricsPayload($target, $targetSales, $canViewFinance),
                ],
                'notes' => [
                    'Ladder này dựa trên catalog giá + cấu hình hiện có, chưa có lịch sử nâng cấp theo từng user.',
                ],
            ];

            if (count($recommendations) >= 3) {
                break;
            }
        }

        return $recommendations;
    }

    private function buildCrossSellRecommendations(array $pairSignals): array
    {
        if ($pairSignals === []) {
            return [[
                'recommendation_type' => 'cross_sell',
                'confidence' => 'low',
                'reason' => 'Chưa có cặp mua chung đủ lặp lại để xem là pattern cross-sell đáng tin cậy.',
                'next_action' => 'Tạm thời chưa bật combo tự động cho Cloud/VPS; cần thêm dữ liệu đơn hoặc trường addon chuyên biệt.',
                'notes' => [
                    'Không có promotion history hoặc order pair đủ dày cho cloud/vps.',
                ],
            ]];
        }

        $firstPair = $pairSignals[0];
        $isCloudRelevant = in_array((string) ($firstPair['product_a_category_slug'] ?? ''), ['vps-cloud-server'], true)
            || in_array((string) ($firstPair['product_b_category_slug'] ?? ''), ['vps-cloud-server'], true);

        if (!$isCloudRelevant || (int) ($firstPair['pair_orders'] ?? 0) < 2) {
            return [[
                'recommendation_type' => 'cross_sell',
                'confidence' => 'low',
                'reason' => 'Hiện mới thấy ' . (int) ($firstPair['pair_orders'] ?? 0) . ' đơn ghép `' . (string) ($firstPair['product_a_name'] ?? '')
                    . '` + `' . (string) ($firstPair['product_b_name'] ?? '') . '`, chưa đủ để coi là combo ổn định.',
                'next_action' => 'Chưa nên đẩy cross-sell tự động. Theo dõi thêm 10-15 đơn cloud hoặc thêm trường mục đích mua để gom pattern tốt hơn.',
                'notes' => [
                    'Shop chưa có addon cloud riêng, nên không bịa sản phẩm bổ trợ ngoài catalog thật.',
                ],
            ]];
        }

        return [[
            'recommendation_type' => 'cross_sell',
            'confidence' => 'medium',
            'reason' => 'Có tối thiểu ' . (int) ($firstPair['pair_orders'] ?? 0) . ' đơn ghép `' . (string) ($firstPair['product_a_name'] ?? '')
                . '` + `' . (string) ($firstPair['product_b_name'] ?? '') . '` trong ' . self::ANALYSIS_WINDOW_DAYS . ' ngày gần đây.',
            'next_action' => 'Test một block cross-sell thủ công ở cart hoặc chatbot, nhưng chưa auto-wide rollout.',
            'notes' => [
                'Chỉ triển khai ở mức pilot vì dữ liệu cặp mua chung còn mỏng.',
            ],
        ]];
    }

    private function buildPromotionRecommendations(array $cloudProducts, array $salesByProduct, array $couponSummary, array $latestCoupons): array
    {
        $entryProduct = $cloudProducts[0] ?? [];
        $bestSellingCloud = $this->bestSellingCloudProduct($cloudProducts, $salesByProduct);
        $recommendations = [];

        $recommendations[] = [
            'recommendation_type' => 'coupon',
            'confidence' => 'medium',
            'reason' => count($latestCoupons) === 0
                ? 'Bảng coupon đang trống, nên shop chưa có đòn khuyến mãi nào cho khách mới ở nhóm cloud.'
                : 'Shop đã có coupon nhưng chưa thấy coupon nào chuyên dùng để kéo khách mới vào nhóm cloud.',
            'next_action' => 'Tạo 1 coupon pilot mức 5% cho traffic cloud mới, `max_uses` khoảng 20-30 và `expires_at` trong 7 ngày để test phản ứng thị trường.',
            'notes' => [
                'Đây là gợi ý sơ bộ, chưa thể kết luận lời/lỗ vì chưa có `cost_price`, fee và lịch sử khuyến mãi.',
                'Schema coupon hiện là global, chưa gắn trực tiếp với từng product/category nên cần triển khai theo campaign thủ công.',
            ],
        ];

        if ($entryProduct !== []) {
            $recommendations[] = [
                'recommendation_type' => 'promotion',
                'product_name' => (string) ($entryProduct['name'] ?? ''),
                'confidence' => 'medium',
                'reason' => 'Gói `' . (string) ($entryProduct['name'] ?? '') . '` đang là mức giá thấp nhất của cloud, hợp làm mồi kéo click vào nhóm core business.',
                'next_action' => 'Trên homepage/category cloud, ưu tiên badge kiểu `Khởi động từ ' . (int) ($entryProduct['price'] ?? 0) . 'đ/tháng` thay vì giảm sâu ngay từ đầu.',
                'notes' => [
                    'Không có dữ liệu margin nên ưu tiên test thông điệp và coupon nhẹ trước khi giảm mạnh.',
                ],
            ];
        }

        if ($bestSellingCloud !== []) {
            $recommendations[] = [
                'recommendation_type' => 'promotion',
                'product_name' => (string) ($bestSellingCloud['name'] ?? ''),
                'confidence' => 'high',
                'reason' => 'Gói `' . (string) ($bestSellingCloud['name'] ?? '') . '` đã có tín hiệu bán thật nên phù hợp làm anchor cho campaign cloud.',
                'next_action' => 'Đẩy nội dung `bán tốt / khách mới chọn nhiều` cho gói này ở homepage, chatbot và block so sánh sản phẩm.',
                'notes' => [
                    'Nên ưu tiên social proof trước, chỉ thêm coupon nhẹ nếu CTR tốt nhưng đơn chưa tăng.',
                ],
            ];
        }

        return $recommendations;
    }

    private function buildCouponActions(array $couponSummary, array $latestCoupons): array
    {
        if (count($latestCoupons) === 0 || (int) ($couponSummary['total_coupons'] ?? 0) === 0) {
            return [[
                'recommendation_type' => 'coupon_action',
                'confidence' => 'high',
                'reason' => 'Hiện chưa có coupon nào đang bật hoặc cần tắt vì bảng coupon đang trống.',
                'next_action' => 'Việc hợp lý lúc này là tạo 1 mã test nhỏ cho Cloud/VPS thay vì bật hàng loạt coupon.',
                'notes' => [
                    'Chưa có `used_count` lịch sử để tối ưu mức giảm hay thời lượng coupon.',
                ],
            ]];
        }

        $actions = [];
        foreach ($latestCoupons as $coupon) {
            $isExpiringSoon = !empty($coupon['expires_at']) && strtotime((string) $coupon['expires_at']) <= strtotime('+7 days');
            if ((string) ($coupon['status'] ?? '') === 'active' && $isExpiringSoon) {
                $actions[] = [
                    'recommendation_type' => 'coupon_action',
                    'coupon_code' => (string) ($coupon['code'] ?? ''),
                    'confidence' => 'medium',
                    'reason' => 'Coupon đang active nhưng sắp hết hạn.',
                    'next_action' => 'Quyết định rõ: hoặc gia hạn để tiếp tục test, hoặc tắt hẳn để tránh treo coupon mập mờ.',
                    'notes' => [
                        'Cần kiểm tra thêm `used_count` trước khi gia hạn dài hơn.',
                    ],
                ];
            }
        }

        if ($actions === []) {
            $actions[] = [
                'recommendation_type' => 'coupon_action',
                'confidence' => 'medium',
                'reason' => 'Hiện chưa có coupon nào phát ra tín hiệu bất thường cần bật/tắt gấp.',
                'next_action' => 'Ưu tiên rà `used_count` theo tuần và gắn 1 coupon test riêng cho cloud nếu muốn chạy campaign mới.',
                'notes' => [
                    'Schema coupon chưa có scope theo category.',
                ],
            ];
        }

        return $actions;
    }

    private function buildCapacityRecommendations(
        array $cloudProducts,
        array $salesByProduct,
        array $pendingCloudSignals,
        array $capacityDataGaps
    ): array {
        if ($capacityDataGaps !== []) {
            return [[
                'recommendation_type' => 'capacity',
                'confidence' => 'low',
                'reason' => 'Chưa thể phân tích tồn kho/capacity vì dữ liệu bắt buộc chưa sẵn sàng: ' . implode(', ', $capacityDataGaps) . '.',
                'next_action' => 'Cập nhật đầy đủ các cột này rồi mới chạy cảnh báo nhập hàng/capacity tự động.',
                'notes' => [
                    'Không có dữ liệu `product_type/stock_qty/capacity_*` nên Meow không thể kết luận còn hàng hay sắp full slot.',
                ],
            ]];
        }

        $pendingByProduct = [];
        foreach ($pendingCloudSignals as $row) {
            $pendingByProduct[(int) ($row['product_id'] ?? 0)] = $row;
        }

        $candidates = [];
        foreach ($cloudProducts as $product) {
            $productId = (int) ($product['id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $sales = $salesByProduct[$productId] ?? ['order_count' => 0, 'sold_qty' => 0];
            $pending = $pendingByProduct[$productId] ?? ['pending_orders' => 0];
            $productType = strtolower(trim((string) ($product['product_type'] ?? 'service')));
            $stockQty = (int) ($product['stock_qty'] ?? 0);
            $reorderPoint = (int) ($product['reorder_point'] ?? 0);
            $capacityLimit = (int) ($product['capacity_limit'] ?? 0);
            $capacityUsed = (int) ($product['capacity_used'] ?? 0);
            $leadTimeDays = (int) ($product['lead_time_days'] ?? 0);

            $score = 0;
            $confidence = 'medium';
            $reasonParts = [];
            $nextAction = '';

            if (in_array($productType, ['digital_code', 'wallet'], true)) {
                if ($stockQty <= 0) {
                    $score += 100;
                    $confidence = 'high';
                    $reasonParts[] = 'stock_qty đã về 0';
                    $nextAction = 'Ưu tiên nhập thêm mã/quỹ cho `' . (string) ($product['name'] ?? 'N/A') . '` trước khi chạy thêm campaign.';
                } elseif ($reorderPoint > 0 && $stockQty <= $reorderPoint) {
                    $score += 85;
                    $reasonParts[] = 'stock_qty đang chạm ngưỡng reorder_point';
                    $nextAction = 'Đặt lệnh bổ sung nguồn hàng trong ' . max($leadTimeDays, 1) . ' ngày tới để tránh hụt mã.';
                } elseif ((int) ($pending['pending_orders'] ?? 0) >= 3) {
                    $score += 60;
                    $reasonParts[] = 'đơn pending tăng trong 30 ngày gần đây';
                    $nextAction = 'Theo dõi tốc độ trừ kho theo ngày và chuẩn bị batch nhập nhỏ để tránh thiếu hàng đột ngột.';
                }
            } elseif ($productType === 'capacity') {
                if ($capacityLimit > 0) {
                    $ratio = (int) round(($capacityUsed / max($capacityLimit, 1)) * 100);
                    if ($ratio >= 90) {
                        $score += 95;
                        $confidence = 'high';
                        $reasonParts[] = 'capacity_used đã đạt ' . $ratio . '%';
                        $nextAction = 'Ưu tiên mở thêm slot/cụm tài nguyên cho `' . (string) ($product['name'] ?? 'N/A') . '` trước khi nhận thêm đơn mới.';
                    } elseif ($ratio >= 75) {
                        $score += 75;
                        $reasonParts[] = 'capacity_used đang ở ' . $ratio . '%';
                        $nextAction = 'Chuẩn bị kế hoạch mở rộng capacity trong 3-7 ngày tới để tránh nghẽn đơn.';
                    }
                }

                if ((int) ($pending['pending_orders'] ?? 0) >= 3) {
                    $score += 20;
                    $reasonParts[] = 'đơn pending cho nhóm này đang tăng';
                }
            }

            if ($score <= 0) {
                continue;
            }

            $candidates[] = [
                'score' => $score,
                'recommendation_type' => 'capacity',
                'product_id' => $productId,
                'product_name' => (string) ($product['name'] ?? ''),
                'product_type' => $productType !== '' ? $productType : 'service',
                'confidence' => $confidence,
                'reason' => $this->sentenceFromParts($reasonParts),
                'next_action' => $nextAction !== '' ? $nextAction : 'Theo dõi thêm 3-5 ngày để chốt kế hoạch nhập hàng/capacity.',
                'metrics' => [
                    'stock_qty' => $stockQty,
                    'reorder_point' => $reorderPoint,
                    'capacity_limit' => $capacityLimit,
                    'capacity_used' => $capacityUsed,
                    'pending_orders_30d' => (int) ($pending['pending_orders'] ?? 0),
                    'order_count_30d' => (int) ($sales['order_count'] ?? 0),
                ],
                'notes' => [
                    'Đây là gợi ý theo dữ liệu nội bộ hiện có, chưa có lead-time nhà cung cấp chuẩn hóa theo từng SKU.',
                ],
            ];
        }

        usort($candidates, static fn(array $a, array $b): int => (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0));

        if ($candidates === []) {
            return [[
                'recommendation_type' => 'capacity',
                'confidence' => 'medium',
                'reason' => 'Chưa thấy SKU cloud nào chạm ngưỡng cảnh báo tồn kho/capacity trong snapshot hiện tại.',
                'next_action' => 'Tiếp tục theo dõi stock_qty, capacity_used và pending orders theo chu kỳ hằng ngày.',
                'notes' => [
                    'Khi đơn tăng đột biến, nên giảm chu kỳ kiểm tra xuống theo giờ để tránh trễ cảnh báo.',
                ],
            ]];
        }

        return array_values(array_map(static function (array $item): array {
            unset($item['score']);
            return $item;
        }, array_slice($candidates, 0, 3)));
    }

    private function buildActionQueue(array $recommendations): array
    {
        $actions = [];

        foreach (['push', 'homepage', 'upsell', 'promotions', 'coupon_actions', 'capacity'] as $bucket) {
            foreach (array_slice((array) ($recommendations[$bucket] ?? []), 0, 2) as $item) {
                $nextAction = trim((string) ($item['next_action'] ?? ''));
                if ($nextAction !== '') {
                    $actions[] = $nextAction;
                }
            }
        }

        return array_values(array_unique(array_slice($actions, 0, 5)));
    }

    private function buildExecutiveSummary(array $catalogSummary, array $salesByCategory, array $salesByProduct, array $couponSummary): string
    {
        $cloudCategory = null;
        foreach ($salesByCategory as $row) {
            if ((string) ($row['category_slug'] ?? '') === 'vps-cloud-server') {
                $cloudCategory = $row;
                break;
            }
        }

        $bestCloudOrders = 0;
        foreach ($salesByProduct as $row) {
            $bestCloudOrders = max($bestCloudOrders, (int) ($row['order_count'] ?? 0));
        }

        $parts = [
            'Cloud/VPS vẫn là mảng core vì đang chiếm ' . (float) ($catalogSummary['cloud_share_percent'] ?? 0) . '% catalog active.',
        ];

        if (is_array($cloudCategory)) {
            $parts[] = 'Trong ' . self::ANALYSIS_WINDOW_DAYS . ' ngày gần đây, nhóm cloud ghi nhận ' . (int) ($cloudCategory['order_count'] ?? 0) . ' đơn hợp lệ.';
        }

        $parts[] = (int) ($couponSummary['total_coupons'] ?? 0) === 0
            ? 'Coupon hiện đang trống nên đây là lúc phù hợp để test một mã pilot nhỏ.'
            : 'Coupon đã tồn tại, cần rà lại coupon active trước khi chạy thêm chiến dịch.';

        if ($bestCloudOrders <= 2) {
            $parts[] = 'Dữ liệu bán cloud còn mỏng, nên ưu tiên gợi ý sơ bộ và test nhỏ thay vì giảm sâu.';
        }

        return implode(' ', $parts);
    }

    private function buildDataGapNotes(
        array $couponSummary,
        array $pairSignals,
        array $missingProfitFields,
        array $missingCapacityFields,
        array $insufficientProfitFields,
        array $insufficientCapacityFields
    ): array
    {
        $notes = [];

        if ($missingProfitFields !== []) {
            $notes[] = 'Chưa có ' . implode(', ', array_map(static fn(string $field): string => '`' . $field . '`', $missingProfitFields))
                . ', nên không thể kết luận chắc chắn về lời/lỗ.';
        }

        if ($insufficientProfitFields !== []) {
            $notes[] = 'Các cột ' . implode(', ', array_map(static fn(string $field): string => '`' . $field . '`', $insufficientProfitFields))
                . ' đã có trong schema nhưng chưa có dữ liệu vận hành đủ để tính lợi nhuận.';
        }

        if ($missingCapacityFields !== []) {
            $notes[] = 'Chưa có ' . implode(', ', array_map(static fn(string $field): string => '`' . $field . '`', $missingCapacityFields))
                . ', nên không thể xác nhận rủi ro capacity hoặc hết slot.';
        }

        if ($insufficientCapacityFields !== []) {
            $notes[] = 'Các cột ' . implode(', ', array_map(static fn(string $field): string => '`' . $field . '`', $insufficientCapacityFields))
                . ' đã có nhưng chưa được nhập đủ để cảnh báo tồn kho/capacity chính xác.';
        }

        if ((int) ($couponSummary['total_coupons'] ?? 0) === 0) {
            $notes[] = 'Bảng coupon đang trống, nên chưa có lịch sử khuyến mãi để so sánh hiệu quả.';
        }

        if (count($pairSignals) < 2) {
            $notes[] = 'Dữ liệu mua kèm còn rất mỏng, nên cross-sell mới dừng ở mức cảnh báo/quan sát.';
        }

        $notes[] = 'Schema chưa có trường thời hạn gói hoặc chu kỳ thanh toán, nên chưa dựng được upsell theo tháng/quý/năm.';

        return $notes;
    }

    private function availableProductColumns(): array
    {
        if (is_array($this->productColumns)) {
            return $this->productColumns;
        }

        try {
            $stmt = $this->db->query('SHOW COLUMNS FROM products');
            $this->productColumns = array_map(static fn(array $row): string => strtolower((string) ($row['Field'] ?? '')), $stmt->fetchAll() ?: []);
        } catch (\Throwable $exception) {
            $this->productColumns = [];
        }

        return $this->productColumns;
    }

    private function findUpsellTarget(array $sourceProduct, array $cloudProducts, array $usedTargetIds): ?array
    {
        $sourcePrice = (float) ($sourceProduct['price'] ?? 0);
        $sourceSpecs = (array) ($sourceProduct['parsed_specs'] ?? []);
        $sourceScore = (float) ($sourceSpecs['resource_score'] ?? 0);
        $bestCandidate = null;
        $bestScore = -INF;

        foreach ($cloudProducts as $candidate) {
            $candidateId = (int) ($candidate['id'] ?? 0);
            if ($candidateId === (int) ($sourceProduct['id'] ?? 0) || in_array($candidateId, $usedTargetIds, true)) {
                continue;
            }

            $candidatePrice = (float) ($candidate['price'] ?? 0);
            if ($candidatePrice <= $sourcePrice || $candidatePrice > ($sourcePrice * 2.4)) {
                continue;
            }

            $candidateSpecs = (array) ($candidate['parsed_specs'] ?? []);
            $resourceGain = (float) ($candidateSpecs['resource_score'] ?? 0) - $sourceScore;
            if ($resourceGain <= 1.5) {
                continue;
            }

            $benefits = $this->describeSpecUpgrade($sourceSpecs, $candidateSpecs);
            if (count($benefits) < 2) {
                continue;
            }

            $priceRatio = $sourcePrice > 0 ? $candidatePrice / $sourcePrice : 999;
            $score = (count($benefits) * 25) + ($resourceGain * 4) - ($priceRatio * 10);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCandidate = $candidate;
            }
        }

        return $bestCandidate;
    }

    private function bestSellingCloudProduct(array $cloudProducts, array $salesByProduct): array
    {
        $bestProduct = [];
        $bestOrders = -1;

        foreach ($cloudProducts as $product) {
            $sales = $salesByProduct[(int) ($product['id'] ?? 0)] ?? ['order_count' => 0];
            if ((int) ($sales['order_count'] ?? 0) > $bestOrders) {
                $bestOrders = (int) ($sales['order_count'] ?? 0);
                $bestProduct = $product;
            }
        }

        return $bestProduct;
    }

    private function parseProductSpecs(array $product): array
    {
        $name = mb_strtolower(trim((string) ($product['name'] ?? '')), 'UTF-8');
        $specs = preg_split('/\r\n|\r|\n/', (string) ($product['specs'] ?? '')) ?: [];
        $parsed = [
            'cpu_cores' => 0,
            'cpu_profile' => '',
            'ram_gb' => 0,
            'disk_gb' => 0,
            'disk_type' => '',
            'location' => '',
            'plan_type' => $this->detectPlanType($name),
        ];

        foreach ($specs as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $lower = mb_strtolower($line, 'UTF-8');

            if (str_starts_with($lower, 'cpu')) {
                if (preg_match('/(\d+)\s*v?core/iu', $line, $matches) === 1) {
                    $parsed['cpu_cores'] = (int) $matches[1];
                }

                if (str_contains($lower, 'ryzen')) {
                    $parsed['cpu_profile'] = 'ryzen';
                } elseif (str_contains($lower, 'high clock')) {
                    $parsed['cpu_profile'] = 'high_clock';
                }
            }

            if (str_starts_with($lower, 'ram') && preg_match('/(\d+)\s*gb/iu', $line, $matches) === 1) {
                $parsed['ram_gb'] = (int) $matches[1];
            }

            if ((str_contains($lower, 'ssd') || str_contains($lower, 'storage')) && preg_match('/(\d+)\s*gb/iu', $line, $matches) === 1) {
                $parsed['disk_gb'] = (int) $matches[1];
                $parsed['disk_type'] = str_contains($lower, 'nvme') ? 'nvme' : 'ssd';
            }

            if (str_starts_with($lower, 'location')) {
                $parts = explode(':', $line, 2);
                $parsed['location'] = trim((string) ($parts[1] ?? ''));
            }
        }

        if ($parsed['cpu_profile'] === '' && str_contains($name, 'ryzen')) {
            $parsed['cpu_profile'] = 'ryzen';
        }

        if ($parsed['plan_type'] === '' && str_contains($name, 'cloud server')) {
            $parsed['plan_type'] = 'cloud';
        }

        $parsed['resource_score'] = ($parsed['cpu_cores'] * 3)
            + ($parsed['ram_gb'] * 2)
            + ($parsed['disk_gb'] / 40)
            + ($parsed['disk_type'] === 'nvme' ? 2 : 0)
            + ($parsed['cpu_profile'] === 'ryzen' ? 1.5 : 0)
            + ($parsed['cpu_profile'] === 'high_clock' ? 1 : 0);

        return $parsed;
    }

    private function detectPlanType(string $name): string
    {
        return match (true) {
            str_contains($name, 'starter') => 'starter',
            str_contains($name, 'basic') => 'basic',
            str_contains($name, 'business') => 'business',
            str_contains($name, 'high cpu') => 'high_cpu',
            str_contains($name, 'high ram') => 'high_ram',
            str_contains($name, 'nvme') => 'nvme',
            str_contains($name, 'pro') => 'pro',
            default => '',
        };
    }

    private function describeSpecUpgrade(array $sourceSpecs, array $targetSpecs): array
    {
        $benefits = [];

        if (($targetSpecs['cpu_cores'] ?? 0) > ($sourceSpecs['cpu_cores'] ?? 0)) {
            $benefits[] = 'CPU tăng lên ' . (int) ($targetSpecs['cpu_cores'] ?? 0) . 'vCore';
        }

        if (($targetSpecs['ram_gb'] ?? 0) > ($sourceSpecs['ram_gb'] ?? 0)) {
            $benefits[] = 'RAM tăng lên ' . (int) ($targetSpecs['ram_gb'] ?? 0) . 'GB';
        }

        if (($targetSpecs['disk_gb'] ?? 0) > ($sourceSpecs['disk_gb'] ?? 0)) {
            $benefits[] = 'disk tăng lên ' . (int) ($targetSpecs['disk_gb'] ?? 0) . 'GB';
        }

        if (($sourceSpecs['disk_type'] ?? '') !== 'nvme' && ($targetSpecs['disk_type'] ?? '') === 'nvme') {
            $benefits[] = 'chuyển sang NVMe';
        }

        if (($sourceSpecs['cpu_profile'] ?? '') !== 'ryzen' && ($targetSpecs['cpu_profile'] ?? '') === 'ryzen') {
            $benefits[] = 'CPU Ryzen cho đơn nhân tốt hơn';
        }

        if (($sourceSpecs['cpu_profile'] ?? '') !== 'high_clock' && ($targetSpecs['cpu_profile'] ?? '') === 'high_clock') {
            $benefits[] = 'clock CPU cao hơn';
        }

        return $benefits;
    }

    private function metricsPayload(array $product, array $sales, bool $canViewFinance): array
    {
        $specs = (array) ($product['parsed_specs'] ?? []);

        return [
            'current_price' => (float) ($product['price'] ?? 0),
            'order_count_30d' => (int) ($sales['order_count'] ?? 0),
            'sold_qty_30d' => (int) ($sales['sold_qty'] ?? 0),
            'sold_revenue_30d' => $canViewFinance ? (float) ($sales['sold_revenue'] ?? 0) : null,
            'specs_summary' => $this->specSummary($specs),
            'location' => (string) ($specs['location'] ?? ''),
        ];
    }

    private function specSummary(array $specs): string
    {
        $parts = [];

        if (($specs['cpu_cores'] ?? 0) > 0) {
            $parts[] = (int) ($specs['cpu_cores'] ?? 0) . 'vCore';
        }

        if (($specs['ram_gb'] ?? 0) > 0) {
            $parts[] = (int) ($specs['ram_gb'] ?? 0) . 'GB RAM';
        }

        if (($specs['disk_gb'] ?? 0) > 0) {
            $diskType = strtoupper((string) ($specs['disk_type'] ?? 'SSD'));
            $parts[] = (int) ($specs['disk_gb'] ?? 0) . 'GB ' . ($diskType !== '' ? $diskType : 'SSD');
        }

        return implode(' / ', $parts);
    }

    private function notesFromProduct(array $product, array $sales): array
    {
        $notes = [];
        if ((int) ($sales['order_count'] ?? 0) === 0) {
            $notes[] = 'Chưa có đơn hợp lệ trong cửa sổ phân tích, nên đây là gợi ý theo catalog chứ chưa phải bằng chứng chuyển đổi.';
        }

        if ((string) ($product['stock_status'] ?? '') !== 'in_stock') {
            $notes[] = 'Trạng thái stock hiện không phải `in_stock`, cần kiểm tra lại trước khi đẩy bán.';
        }

        return $notes;
    }

    private function pushNextAction(array $product, array $sales): string
    {
        $price = (float) ($product['price'] ?? 0);

        if ((int) ($sales['order_count'] ?? 0) >= 2 && $price <= 250000) {
            return 'Đưa gói này lên homepage slot chính và dùng làm gói mồi trong chatbot để kéo khách mới vào nhóm cloud.';
        }

        if ($price <= 220000) {
            return 'Đặt badge entry-level cho gói này ở homepage/category cloud để tăng click đầu phễu.';
        }

        return 'Giữ gói này ngay dưới gói mồi như lựa chọn nâng cấp đầu tiên cho khách cần 8GB RAM hoặc 4vCore.';
    }

    private function homepageReason(string $slotLabel, array $product, array $sales): string
    {
        return match ($slotLabel) {
            'proof' => 'Đây là gói cloud đang có tín hiệu mua thật, phù hợp làm card đầu tiên để tạo social proof.',
            'entry' => 'Đây là gói giá thấp nhất trong nhóm cloud, hợp làm điểm vào cho khách mới.',
            'upsell' => 'Đây là nấc nâng cấp rõ ràng ngay sau gói entry, hợp đặt cạnh gói mồi để tăng AOV sơ bộ.',
            default => 'Gói này phù hợp để xuất hiện sớm trên homepage cloud.',
        };
    }

    private function homepageNextAction(string $slot, string $slotLabel): string
    {
        return match ($slotLabel) {
            'proof' => $slot . ': dùng headline nhấn vào `khách mới chọn nhiều` hoặc `bán tốt gần đây`.',
            'entry' => $slot . ': dùng CTA kiểu `Khởi động nhanh` hoặc `Từ 179k/tháng`.',
            'upsell' => $slot . ': đặt ngay cạnh gói mồi với copy `nâng cấu hình lên 8GB RAM / 4vCore`.',
            default => $slot . ': giữ làm card cloud ưu tiên.',
        };
    }

    private function normalizeCouponSummary(array $summary): array
    {
        return [
            'total_coupons' => (int) ($summary['total_coupons'] ?? 0),
            'active_coupons' => (int) ($summary['active_coupons'] ?? 0),
            'inactive_coupons' => (int) ($summary['inactive_coupons'] ?? 0),
            'expiring_soon' => (int) ($summary['expiring_soon'] ?? 0),
        ];
    }

    private function sentenceFromParts(array $parts): string
    {
        $parts = array_values(array_unique(array_filter(array_map('trim', $parts))));
        if ($parts === []) {
            return '';
        }

        return implode('; ', $parts) . '.';
    }
}
