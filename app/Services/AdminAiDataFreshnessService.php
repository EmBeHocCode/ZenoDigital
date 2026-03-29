<?php

namespace App\Services;

use App\Core\Database;

class AdminAiDataFreshnessService
{
    private const MODULE_TABLES = [
        'products' => 'products',
        'categories' => 'categories',
        'orders' => 'orders',
        'order_items' => 'order_items',
        'coupons' => 'coupons',
        'users' => 'users',
        'feedback' => 'customer_feedback',
        'settings' => 'settings',
    ];

    private const TRACKED_COLUMNS = ['updated_at', 'created_at', 'deleted_at', 'id'];

    private array $config;
    private static array $columnCache = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function buildAdminSignature(): array
    {
        $modules = [];

        foreach (self::MODULE_TABLES as $moduleKey => $table) {
            $modules[$moduleKey] = $this->describeTable($table);
        }

        $fingerprint = sha1((string) json_encode($modules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'fingerprint' => $fingerprint,
            'modules' => $modules,
            'generated_at' => date('c'),
            'refresh_policy' => [
                'strategy' => 'version_stamp + short_ttl',
                'ttl_seconds' => $this->cacheTtlSeconds(),
                'auto_invalidates_on_change' => true,
            ],
        ];
    }

    public function loadCachedSnapshot(string $scopeKey, string $fingerprint): ?array
    {
        $path = $this->cachePath($scopeKey);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        $payload = json_decode((string) $raw, true);
        if (!is_array($payload)) {
            return null;
        }

        $storedAtUnix = (int) ($payload['stored_at_unix'] ?? 0);
        $ageSeconds = $storedAtUnix > 0 ? max(0, time() - $storedAtUnix) : PHP_INT_MAX;
        $sameFingerprint = (string) ($payload['fingerprint'] ?? '') === $fingerprint;

        if (!$sameFingerprint || $ageSeconds > $this->cacheTtlSeconds()) {
            return null;
        }

        return [
            'data' => (array) ($payload['data'] ?? []),
            'stored_at' => (string) ($payload['stored_at'] ?? ''),
            'age_seconds' => $ageSeconds,
            'cache_hit' => true,
        ];
    }

    public function storeCachedSnapshot(string $scopeKey, string $fingerprint, array $data): void
    {
        $path = $this->cachePath($scopeKey);
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return;
        }

        $payload = [
            'scope_key' => $scopeKey,
            'fingerprint' => $fingerprint,
            'stored_at' => date('c'),
            'stored_at_unix' => time(),
            'data' => $data,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $tmpPath = $path . '.tmp';
        @file_put_contents($tmpPath, (string) $json, LOCK_EX);
        @rename($tmpPath, $path);
    }

    public function cacheTtlSeconds(): int
    {
        return max(5, (int) config('ai.admin_context_cache_ttl', 15));
    }

    private function describeTable(string $table): array
    {
        try {
            $db = Database::getConnection($this->config['db']);
            $columns = $this->columnsFor($table);

            if ($columns === []) {
                return [
                    'table' => $table,
                    'exists' => false,
                    'signature' => 'missing-table',
                ];
            }

            $selectParts = ['COUNT(*) AS row_count'];
            foreach (self::TRACKED_COLUMNS as $column) {
                if (in_array($column, $columns, true)) {
                    $selectParts[] = 'MAX(`' . $column . '`) AS `max_' . $column . '`';
                }
            }

            $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM `' . $table . '`';
            $row = $db->query($sql)->fetch() ?: [];

            $shape = [
                'table' => $table,
                'exists' => true,
                'row_count' => (int) ($row['row_count'] ?? 0),
            ];

            foreach (self::TRACKED_COLUMNS as $column) {
                if (array_key_exists('max_' . $column, $row)) {
                    $shape['max_' . $column] = $row['max_' . $column];
                }
            }

            $shape['signature'] = sha1((string) json_encode($shape, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $shape;
        } catch (\Throwable $exception) {
            return [
                'table' => $table,
                'exists' => false,
                'error' => $exception->getMessage(),
                'signature' => 'error-' . sha1($exception->getMessage()),
            ];
        }
    }

    private function columnsFor(string $table): array
    {
        if (isset(self::$columnCache[$table])) {
            return self::$columnCache[$table];
        }

        try {
            $db = Database::getConnection($this->config['db']);
            $stmt = $db->query('SHOW COLUMNS FROM `' . $table . '`');
            $columns = array_values(array_filter(array_map(
                static fn(array $row): string => (string) ($row['Field'] ?? ''),
                $stmt->fetchAll() ?: []
            )));
        } catch (\Throwable $exception) {
            $columns = [];
        }

        self::$columnCache[$table] = $columns;
        return $columns;
    }

    private function cachePath(string $scopeKey): string
    {
        $hash = sha1(trim($scopeKey) !== '' ? $scopeKey : 'default');

        return BASE_PATH
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'admin-ai'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . $hash . '.json';
    }
}
