<?php

namespace App\Services;

use App\Core\Database;

class AiGuardService
{
    private const PROFIT_REQUIRED_FIELDS = [
        'cost_price',
        'min_margin_percent',
        'platform_fee_percent',
        'payment_fee_percent',
        'ads_cost_per_order',
        'delivery_cost',
    ];

    private const CAPACITY_REQUIRED_FIELDS = [
        'product_type',
        'stock_qty',
        'capacity_limit',
        'capacity_used',
    ];

    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getGuardRules(string $channel): array
    {
        $rules = [
            'Không copy nguyên logic Zalo-specific sang webshop.',
            'Chỉ tái sử dụng cơ chế phù hợp và rewrite theo PHP MVC.',
            'Không bịa số liệu nếu dữ liệu thật không có trong context.',
        ];

        if ($channel === 'admin') {
            $rules[] = 'Nếu câu hỏi liên quan khuyến mãi, lợi nhuận, nhập hàng hoặc capacity thì phải kiểm tra dữ liệu hiện có trước khi kết luận.';
        }

        return $rules;
    }

    public function getMissingFinancialFields(): array
    {
        $requiredFields = array_values(array_unique(array_merge(
            self::PROFIT_REQUIRED_FIELDS,
            self::CAPACITY_REQUIRED_FIELDS,
            ['reorder_point', 'supplier_name', 'lead_time_days']
        )));

        return $this->getMissingColumns($requiredFields);
    }

    public function getMissingProfitFields(): array
    {
        return $this->getMissingColumns(self::PROFIT_REQUIRED_FIELDS);
    }

    public function getMissingCapacityFields(): array
    {
        return $this->getMissingColumns(self::CAPACITY_REQUIRED_FIELDS);
    }

    public function getInsufficientProfitFields(): array
    {
        return $this->getColumnsWithoutData(self::PROFIT_REQUIRED_FIELDS);
    }

    public function getInsufficientCapacityFields(): array
    {
        return $this->getColumnsWithoutData(self::CAPACITY_REQUIRED_FIELDS);
    }

    public function buildCapabilityWarnings(array $capabilities): array
    {
        $warnings = [];
        $capabilities = $this->normalizeCapabilities($capabilities);

        $requiresProfitCheck = array_intersect(['promotion_advisor', 'profit_guard'], $capabilities);
        if ($requiresProfitCheck) {
            $fieldGaps = $this->mergeFieldGaps(
                $this->getMissingProfitFields(),
                $this->getInsufficientProfitFields()
            );
            if ($fieldGaps) {
                $warnings[] = 'Thiếu dữ liệu cho phân tích lợi nhuận/khuyến mãi: ' . implode(', ', $fieldGaps);
            }
        }

        $requiresCapacityCheck = array_intersect(['capacity_advisor'], $capabilities);
        if ($requiresCapacityCheck) {
            $fieldGaps = $this->mergeFieldGaps(
                $this->getMissingCapacityFields(),
                $this->getInsufficientCapacityFields()
            );
            if ($fieldGaps) {
                $warnings[] = 'Thiếu dữ liệu cho phân tích tồn kho/capacity: ' . implode(', ', $fieldGaps);
            }
        }

        return $warnings;
    }

    public function detectSensitiveAdminCapabilities(string $message): array
    {
        $message = mb_strtolower(trim($message), 'UTF-8');
        if ($message === '') {
            return [];
        }

        $matches = [];
        $keywordMap = [
            'promotion_advisor' => [
                'khuyến mãi',
                'khuyen mai',
                'giảm giá',
                'giam gia',
                'coupon',
                'ưu đãi',
                'uu dai',
                'combo',
                'upsell',
                'cross sell',
                'flash sale',
                'margin',
                'lợi nhuận',
                'loi nhuan',
                'lãi',
                'lai',
                'giá vốn',
                'gia von',
                'chi phí',
                'chi phi',
                'phí nền tảng',
                'phi nen tang',
            ],
            'capacity_advisor' => [
                'tồn kho',
                'ton kho',
                'stock',
                'capacity',
                'slot',
                'hết mã',
                'het ma',
                'mã hàng',
                'ma hang',
                'nhập hàng',
                'nhap hang',
                'lead time',
                'reorder',
                'công suất',
                'cong suat',
                'sức chứa',
                'suc chua',
                'sắp đầy',
                'sap day',
                'sắp hết',
                'sap het',
            ],
            'profit_guard' => [
                'lỗ',
                'profit',
                'biên lợi nhuận',
                'bien loi nhuan',
                'giá vốn',
                'gia von',
                'min margin',
            ],
        ];

        foreach ($keywordMap as $capability => $keywords) {
            if ($this->containsAny($message, $keywords)) {
                $matches[] = $capability;
            }
        }

        return array_values(array_unique($matches));
    }

    public function buildFinancialCapabilityRefusal(array $capabilities): ?array
    {
        $capabilities = $this->normalizeCapabilities($capabilities);
        $hardBlocked = array_values(array_intersect($capabilities, ['capacity_advisor']));
        if ($hardBlocked === []) {
            return null;
        }

        $missingColumns = $this->getMissingCapacityFields();
        $insufficientFields = $this->getInsufficientCapacityFields();
        $fieldGaps = $this->mergeFieldGaps($missingColumns, $insufficientFields);
        if ($fieldGaps === []) {
            return null;
        }

        $labels = array_map(function (string $capability): string {
            return match ($capability) {
                'capacity_advisor' => 'tồn kho/capacity',
                default => $capability,
            };
        }, $hardBlocked);

        return [
            'capabilities' => $hardBlocked,
            'missing_fields' => $fieldGaps,
            'missing_columns' => $missingColumns,
            'insufficient_fields' => $insufficientFields,
            'message' => 'Mình chưa thể kết luận về ' . implode(', ', array_unique($labels))
                . ' vì dữ liệu thật chưa đủ. '
                . ($missingColumns !== []
                    ? 'Thiếu cột schema: ' . implode(', ', $missingColumns) . '. '
                    : '')
                . ($insufficientFields !== []
                    ? 'Các cột chưa có dữ liệu vận hành: ' . implode(', ', $insufficientFields) . '. '
                    : '')
                . 'Với khuyến mãi hoặc upsell, Meow chỉ nên dừng ở mức gợi ý sơ bộ chứ không khẳng định capacity hay lời/lỗ.',
        ];
    }

    public function detectRestrictedBackofficeCapabilities(string $message): array
    {
        $message = mb_strtolower(trim($message), 'UTF-8');
        if ($message === '') {
            return [];
        }

        $matches = [];
        $keywordMap = [
            'finance_metrics' => [
                'doanh thu',
                'revenue',
                'lợi nhuận',
                'loi nhuan',
                'lãi',
                'lai',
                'chi phí',
                'chi phi',
                'aov',
                'arpu',
                'gmv',
                'doanh số',
                'doanh so',
            ],
            'user_metrics' => [
                'người dùng',
                'nguoi dung',
                'khách hàng mới',
                'khach hang moi',
                'tăng trưởng user',
                'tang truong user',
                'user growth',
                'registered',
            ],
            'rank_metrics' => [
                'rank',
                'cấp bậc',
                'cap bac',
                'điểm rank',
                'diem rank',
                'loyalty',
            ],
            'system_control' => [
                'sql',
                'database',
                'audit',
                'log hệ thống',
                'log he thong',
                'cài đặt',
                'cai dat',
                'system',
                'phân quyền',
                'phan quyen',
                'role',
            ],
        ];

        foreach ($keywordMap as $capability => $keywords) {
            if ($this->containsAny($message, $keywords)) {
                $matches[] = $capability;
            }
        }

        return array_values(array_unique($matches));
    }

    public function buildBackofficePermissionRefusal(array $actor, array $scope, array $capabilities): ?array
    {
        $capabilities = $this->normalizeCapabilities($capabilities);
        if ($capabilities === []) {
            return null;
        }

        $permissionMap = [
            'finance_metrics' => 'can_view_finance',
            'user_metrics' => 'can_view_users',
            'rank_metrics' => 'can_view_rank',
            'system_control' => 'can_manage_system',
        ];

        $blocked = [];
        foreach ($capabilities as $capability) {
            $flag = $permissionMap[$capability] ?? null;
            if ($flag !== null && empty($scope[$flag])) {
                $blocked[] = $capability;
            }
        }

        if ($blocked === []) {
            return null;
        }

        $actorType = (string) ($actor['actor_type'] ?? 'unknown');
        $scopeLabel = trim((string) ($scope['label'] ?? 'quyền hiện tại'));
        $allowedAreas = implode(', ', array_filter([
            !empty($scope['can_view_orders']) ? 'đơn hàng' : null,
            !empty($scope['can_view_products']) ? 'sản phẩm' : null,
            !empty($scope['can_view_coupons']) ? 'coupon' : null,
            !empty($scope['can_view_feedback']) ? 'feedback' : null,
        ]));

        $labels = array_map(static function (string $capability): string {
            return match ($capability) {
                'finance_metrics' => 'dữ liệu tài chính/doanh thu',
                'user_metrics' => 'số liệu người dùng',
                'rank_metrics' => 'thống kê rank/loyalty',
                'system_control' => 'dữ liệu hệ thống/quản trị sâu',
                default => $capability,
            };
        }, $blocked);

        $message = 'Meow chưa thể mở phần ' . implode(', ', array_unique($labels))
            . ' trong phiên này vì vai trò `' . $actorType . '` đang ở phạm vi `' . $scopeLabel . '`.';

        if ($allowedAreas !== '') {
            $message .= ' Hiện mình chỉ hỗ trợ trong phạm vi: ' . $allowedAreas . '.';
        }

        return [
            'capabilities' => $blocked,
            'message' => $message,
            'scope_key' => (string) ($scope['scope_key'] ?? 'limited'),
        ];
    }

    public function buildBackofficeScopeWarnings(array $actor, array $scope, array $capabilities): array
    {
        $refusal = $this->buildBackofficePermissionRefusal($actor, $scope, $capabilities);

        if ($refusal === null) {
            return [];
        }

        return [(string) ($refusal['message'] ?? '')];
    }

    private function normalizeCapabilities(array $capabilities): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn($item): string => strtolower(trim((string) $item)),
            $capabilities
        ))));
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            $needle = trim((string) $needle);
            if ($needle === '') {
                continue;
            }

            if (str_contains($needle, ' ')) {
                if (str_contains($haystack, $needle)) {
                    return true;
                }

                continue;
            }

            if (preg_match('/(^|[^\p{L}\p{N}_])' . preg_quote($needle, '/') . '([^\p{L}\p{N}_]|$)/iu', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    private function mergeFieldGaps(array ...$groups): array
    {
        $merged = [];
        foreach ($groups as $group) {
            foreach ($group as $field) {
                $normalized = strtolower(trim((string) $field));
                if ($normalized !== '') {
                    $merged[$normalized] = true;
                }
            }
        }

        return array_values(array_keys($merged));
    }

    private function getColumnsWithoutData(array $requiredFields): array
    {
        $requiredFields = array_values(array_unique(array_filter(array_map(
            static fn($item): string => strtolower(trim((string) $item)),
            $requiredFields
        ))));
        if ($requiredFields === []) {
            return [];
        }

        $missingColumns = $this->getMissingColumns($requiredFields);
        $availableFields = array_values(array_diff($requiredFields, $missingColumns));
        if ($availableFields === []) {
            return [];
        }

        try {
            $db = Database::getConnection($this->config['db']);
            $totalActiveProducts = (int) ($db->query("SELECT COUNT(*) AS total FROM products WHERE deleted_at IS NULL AND status = 'active'")->fetch()['total'] ?? 0);
            if ($totalActiveProducts <= 0) {
                return $availableFields;
            }

            $insufficient = [];
            foreach ($availableFields as $field) {
                if (preg_match('/^[a-z0-9_]+$/', $field) !== 1) {
                    continue;
                }

                $presenceExpression = $this->columnPresenceExpression($field);
                $sql = "SELECT COUNT(*) AS total
                    FROM products
                    WHERE deleted_at IS NULL
                      AND status = 'active'
                      AND {$presenceExpression}";
                $count = (int) ($db->query($sql)->fetch()['total'] ?? 0);
                if ($count <= 0) {
                    $insufficient[] = $field;
                }
            }

            return $insufficient;
        } catch (\Throwable $exception) {
            return $availableFields;
        }
    }

    private function columnPresenceExpression(string $field): string
    {
        return match ($field) {
            'product_type', 'supplier_name' => "`{$field}` IS NOT NULL AND TRIM(`{$field}`) <> ''",
            default => "`{$field}` IS NOT NULL",
        };
    }

    private function getMissingColumns(array $requiredFields): array
    {
        $requiredFields = array_values(array_unique(array_filter(array_map(
            static fn($item): string => strtolower(trim((string) $item)),
            $requiredFields
        ))));

        if ($requiredFields === []) {
            return [];
        }

        try {
            $db = Database::getConnection($this->config['db']);
            $stmt = $db->query('SHOW COLUMNS FROM products');
            $columns = array_map(static fn(array $row): string => strtolower((string) ($row['Field'] ?? '')), $stmt->fetchAll() ?: []);
        } catch (\Throwable $exception) {
            return $requiredFields;
        }

        return array_values(array_diff($requiredFields, $columns));
    }
}
