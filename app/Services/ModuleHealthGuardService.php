<?php

namespace App\Services;

class ModuleHealthGuardService
{
    private SchemaHealthService $schemaHealth;

    public function __construct(array $config)
    {
        $this->schemaHealth = new SchemaHealthService($config);
    }

    public function moduleStatus(string $module): array
    {
        return $this->schemaHealth->moduleStatus($module);
    }

    public function summary(?array $modules = null): array
    {
        return $this->schemaHealth->summary($modules);
    }

    public function isHealthy(string $module): bool
    {
        return $this->schemaHealth->isHealthy($module);
    }

    public function messageFor(string $module, string $intent = 'read'): string
    {
        $status = $this->moduleStatus($module);
        if (!empty($status['healthy'])) {
            return '';
        }

        $label = (string) ($status['label'] ?? ucfirst($module));
        $actionLabel = $intent === 'write'
            ? 'ghi hoặc chỉnh sửa dữ liệu'
            : 'truy cập dữ liệu';
        $firstIssue = (string) (($status['issues'][0]['message'] ?? 'schema chưa toàn vẹn'));

        return 'Module ' . $label . ' đang tạm khóa để bảo vệ dữ liệu. Không thể ' . $actionLabel . ' vì ' . $firstIssue;
    }

    public function logBlockedAction(string $module, string $intent, array $context = []): void
    {
        $status = $this->moduleStatus($module);
        security_log('Module health guard chặn thao tác', [
            'module' => $module,
            'intent' => $intent,
            'issues' => $status['issues'] ?? [],
            'context' => $context,
        ]);
    }
}
