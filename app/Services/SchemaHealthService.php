<?php

namespace App\Services;

use App\Core\Database;

class SchemaHealthService
{
    private \PDO $db;
    private ?array $summaryCache = null;

    public function __construct(array $config)
    {
        $this->db = Database::getConnection($config['db']);
    }

    public function summary(?array $modules = null): array
    {
        $summary = $this->buildSummary();

        if ($modules === null) {
            return $summary;
        }

        $requested = [];
        foreach ($modules as $module) {
            $key = strtolower(trim((string) $module));
            if ($key !== '' && isset($summary['modules'][$key])) {
                $requested[$key] = $summary['modules'][$key];
            }
        }

        return [
            'checked_at' => $summary['checked_at'],
            'modules' => $requested,
            'unhealthy_modules' => array_values(array_map(
                static fn(array $item): string => (string) ($item['key'] ?? ''),
                array_filter($requested, static fn(array $item): bool => empty($item['healthy']))
            )),
        ];
    }

    public function moduleStatus(string $module): array
    {
        $module = strtolower(trim($module));
        $summary = $this->buildSummary();

        return $summary['modules'][$module] ?? [
            'key' => $module,
            'label' => ucfirst($module),
            'healthy' => false,
            'severity' => 'error',
            'issues' => [
                [
                    'type' => 'unknown_module',
                    'message' => 'Module chưa được định nghĩa trong schema health checker.',
                ],
            ],
            'affected_tables' => [],
            'blocked_operations' => ['read', 'write'],
            'summary' => 'Không xác định được module.',
        ];
    }

    public function isHealthy(string $module): bool
    {
        return !empty($this->moduleStatus($module)['healthy']);
    }

    public function affectedModulesByTables(array $tables): array
    {
        $tableToModules = [];
        foreach ($this->moduleDefinitions() as $moduleKey => $definition) {
            foreach (array_keys((array) ($definition['tables'] ?? [])) as $tableName) {
                $tableToModules[$tableName][] = $moduleKey;
            }
        }

        $matched = [];
        foreach ($tables as $table) {
            $table = strtolower(trim((string) $table));
            if ($table === '' || !isset($tableToModules[$table])) {
                continue;
            }

            foreach ($tableToModules[$table] as $moduleKey) {
                $matched[$moduleKey] = true;
            }
        }

        return array_values(array_keys($matched));
    }

    private function buildSummary(): array
    {
        if (is_array($this->summaryCache)) {
            return $this->summaryCache;
        }

        $snapshot = $this->loadSchemaSnapshot();
        $modules = [];

        foreach ($this->moduleDefinitions() as $moduleKey => $definition) {
            $issues = [];
            $affectedTables = [];

            foreach ((array) ($definition['tables'] ?? []) as $tableName => $requiredColumns) {
                $affectedTables[] = $tableName;

                if (empty($snapshot['tables'][$tableName])) {
                    $issues[] = [
                        'type' => 'missing_table',
                        'table' => $tableName,
                        'message' => 'Thiếu bảng `' . $tableName . '`.',
                    ];
                    continue;
                }

                $existingColumns = $snapshot['columns'][$tableName] ?? [];
                foreach ((array) $requiredColumns as $columnName) {
                    if (empty($existingColumns[$columnName])) {
                        $issues[] = [
                            'type' => 'missing_column',
                            'table' => $tableName,
                            'column' => $columnName,
                            'message' => 'Thiếu cột `' . $tableName . '.' . $columnName . '`.',
                        ];
                    }
                }
            }

            foreach ((array) ($definition['unique_indexes'] ?? []) as $tableName => $requiredUniqueColumns) {
                if (empty($snapshot['tables'][$tableName])) {
                    continue;
                }

                $existingUniqueColumns = $snapshot['unique_columns'][$tableName] ?? [];
                foreach ((array) $requiredUniqueColumns as $columnName) {
                    if (empty($existingUniqueColumns[$columnName])) {
                        $issues[] = [
                            'type' => 'missing_unique_index',
                            'table' => $tableName,
                            'column' => $columnName,
                            'message' => 'Thiếu unique index cho `' . $tableName . '.' . $columnName . '`.',
                        ];
                    }
                }
            }

            $healthy = $issues === [];
            $modules[$moduleKey] = [
                'key' => $moduleKey,
                'label' => (string) ($definition['label'] ?? ucfirst($moduleKey)),
                'healthy' => $healthy,
                'severity' => $healthy ? 'success' : 'error',
                'issues' => $issues,
                'affected_tables' => array_values(array_unique($affectedTables)),
                'blocked_operations' => $healthy ? [] : ['read', 'write'],
                'summary' => $healthy
                    ? 'Schema toàn vẹn.'
                    : ('Phát hiện ' . count($issues) . ' vấn đề schema.'),
            ];
        }

        $this->summaryCache = [
            'checked_at' => date('c'),
            'modules' => $modules,
            'unhealthy_modules' => array_values(array_keys(array_filter(
                $modules,
                static fn(array $module): bool => empty($module['healthy'])
            ))),
        ];

        return $this->summaryCache;
    }

    private function loadSchemaSnapshot(): array
    {
        $tables = [];
        $tableStmt = $this->db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        foreach ($tableStmt->fetchAll() ?: [] as $row) {
            $tableName = strtolower((string) ($row['TABLE_NAME'] ?? ''));
            if ($tableName !== '') {
                $tables[$tableName] = true;
            }
        }

        $columns = [];
        $columnStmt = $this->db->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
        foreach ($columnStmt->fetchAll() ?: [] as $row) {
            $tableName = strtolower((string) ($row['TABLE_NAME'] ?? ''));
            $columnName = strtolower((string) ($row['COLUMN_NAME'] ?? ''));
            if ($tableName === '' || $columnName === '') {
                continue;
            }

            $columns[$tableName][$columnName] = true;
        }

        $uniqueIndexes = [];
        $indexStmt = $this->db->query('SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX ASC');
        foreach ($indexStmt->fetchAll() ?: [] as $row) {
            $tableName = strtolower((string) ($row['TABLE_NAME'] ?? ''));
            $indexName = strtolower((string) ($row['INDEX_NAME'] ?? ''));
            $columnName = strtolower((string) ($row['COLUMN_NAME'] ?? ''));
            $nonUnique = (int) ($row['NON_UNIQUE'] ?? 1);

            if ($tableName === '' || $indexName === '' || $columnName === '' || $nonUnique !== 0) {
                continue;
            }

            $uniqueIndexes[$tableName][$indexName][] = $columnName;
        }

        $uniqueColumns = [];
        foreach ($uniqueIndexes as $tableName => $indexes) {
            foreach ($indexes as $indexColumns) {
                if (count($indexColumns) === 1) {
                    $uniqueColumns[$tableName][$indexColumns[0]] = true;
                }
            }
        }

        return [
            'tables' => $tables,
            'columns' => $columns,
            'unique_columns' => $uniqueColumns,
        ];
    }

    private function moduleDefinitions(): array
    {
        return [
            'products' => [
                'label' => 'Sản phẩm',
                'tables' => [
                    'products' => ['id', 'category_id', 'name', 'slug', 'price', 'status', 'deleted_at'],
                    'categories' => ['id', 'name', 'slug', 'deleted_at'],
                ],
                'unique_indexes' => [
                    'products' => ['slug'],
                    'categories' => ['slug'],
                ],
            ],
            'categories' => [
                'label' => 'Danh mục',
                'tables' => [
                    'categories' => ['id', 'name', 'slug', 'deleted_at'],
                    'products' => ['id', 'category_id', 'deleted_at'],
                ],
                'unique_indexes' => [
                    'categories' => ['slug'],
                ],
            ],
            'orders' => [
                'label' => 'Đơn hàng',
                'tables' => [
                    'orders' => ['id', 'user_id', 'order_code', 'total_amount', 'status', 'created_at', 'deleted_at'],
                    'order_items' => ['id', 'order_id', 'product_id', 'quantity', 'unit_price', 'total_price'],
                    'users' => ['id', 'full_name', 'email', 'deleted_at'],
                ],
                'unique_indexes' => [
                    'orders' => ['order_code'],
                    'users' => ['email'],
                ],
            ],
            'users' => [
                'label' => 'Người dùng',
                'tables' => [
                    'users' => ['id', 'role_id', 'full_name', 'email', 'status', 'deleted_at'],
                    'roles' => ['id', 'name'],
                ],
                'unique_indexes' => [
                    'users' => ['email'],
                ],
            ],
            'coupons' => [
                'label' => 'Coupon',
                'tables' => [
                    'coupons' => ['id', 'code', 'discount_percent', 'status', 'created_at', 'updated_at', 'deleted_at'],
                ],
                'unique_indexes' => [
                    'coupons' => ['code'],
                ],
            ],
            'feedback' => [
                'label' => 'Feedback',
                'tables' => [
                    'customer_feedback' => ['id', 'feedback_code', 'status', 'needs_follow_up', 'message', 'created_at', 'updated_at'],
                    'users' => ['id', 'email', 'deleted_at'],
                    'products' => ['id', 'name', 'deleted_at'],
                    'orders' => ['id', 'order_code', 'deleted_at'],
                ],
                'unique_indexes' => [
                    'customer_feedback' => ['feedback_code'],
                    'users' => ['email'],
                    'orders' => ['order_code'],
                ],
            ],
            'settings' => [
                'label' => 'Cài đặt hệ thống',
                'tables' => [
                    'settings' => ['setting_key', 'setting_value', 'updated_at'],
                ],
                'unique_indexes' => [
                    'settings' => ['setting_key'],
                ],
            ],
            'rank' => [
                'label' => 'Rank',
                'tables' => [
                    'settings' => ['setting_key', 'setting_value', 'updated_at'],
                ],
                'unique_indexes' => [
                    'settings' => ['setting_key'],
                ],
            ],
            'payments' => [
                'label' => 'Thanh toán / Ví',
                'tables' => [
                    'wallet_transactions' => ['id', 'user_id', 'transaction_code', 'transaction_type', 'amount', 'status', 'created_at', 'updated_at'],
                    'users' => ['id', 'email', 'wallet_balance', 'deleted_at'],
                ],
                'unique_indexes' => [
                    'wallet_transactions' => ['transaction_code'],
                    'users' => ['email'],
                ],
            ],
            'audit' => [
                'label' => 'Audit log',
                'tables' => [
                    'admin_audit_logs' => ['id', 'admin_id', 'action_name', 'entity_name', 'created_at'],
                    'users' => ['id', 'email', 'deleted_at'],
                ],
                'unique_indexes' => [
                    'users' => ['email'],
                ],
            ],
        ];
    }
}
