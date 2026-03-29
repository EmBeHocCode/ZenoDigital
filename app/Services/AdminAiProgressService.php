<?php

namespace App\Services;

class AdminAiProgressService
{
    private const DEFAULT_STEP_LABELS = [
        'checking_data' => 'Bot đang kiểm dữ liệu...',
        'summarizing' => 'Bot đang thống kê và gửi đến...',
        'completed' => 'Bot đã trả kết quả.',
        'failed' => 'Bot không thể hoàn tất yêu cầu.',
    ];

    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function start(string $requestId, int $actorId, array $meta = []): array
    {
        return $this->write($requestId, $actorId, [
            'status' => 'processing',
            'step_key' => 'checking_data',
            'step_label' => self::DEFAULT_STEP_LABELS['checking_data'],
            'message' => self::DEFAULT_STEP_LABELS['checking_data'],
            'meta' => $meta,
        ], true);
    }

    public function markCheckingData(string $requestId, int $actorId, array $meta = []): array
    {
        return $this->write($requestId, $actorId, [
            'status' => 'processing',
            'step_key' => 'checking_data',
            'step_label' => self::DEFAULT_STEP_LABELS['checking_data'],
            'message' => self::DEFAULT_STEP_LABELS['checking_data'],
            'meta' => $meta,
        ]);
    }

    public function markSummarizing(string $requestId, int $actorId, array $meta = []): array
    {
        return $this->write($requestId, $actorId, [
            'status' => 'processing',
            'step_key' => 'summarizing',
            'step_label' => self::DEFAULT_STEP_LABELS['summarizing'],
            'message' => self::DEFAULT_STEP_LABELS['summarizing'],
            'meta' => $meta,
        ]);
    }

    public function complete(string $requestId, int $actorId, array $meta = []): array
    {
        return $this->write($requestId, $actorId, [
            'status' => 'completed',
            'step_key' => 'completed',
            'step_label' => self::DEFAULT_STEP_LABELS['completed'],
            'message' => self::DEFAULT_STEP_LABELS['completed'],
            'meta' => $meta,
        ]);
    }

    public function fail(string $requestId, int $actorId, string $errorMessage, array $meta = []): array
    {
        return $this->write($requestId, $actorId, [
            'status' => 'failed',
            'step_key' => 'failed',
            'step_label' => self::DEFAULT_STEP_LABELS['failed'],
            'message' => $errorMessage !== '' ? $errorMessage : self::DEFAULT_STEP_LABELS['failed'],
            'meta' => $meta,
        ]);
    }

    public function find(string $requestId, int $actorId): ?array
    {
        $path = $this->path($requestId, $actorId);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $this->enrichState($decoded);
    }

    private function write(string $requestId, int $actorId, array $payload, bool $resetHistory = false): array
    {
        $this->cleanupStaleFiles();

        $requestId = $this->normalizeRequestId($requestId);
        $actorId = max(0, $actorId);
        $now = time();
        $isoNow = date('c', $now);
        $current = $this->find($requestId, $actorId);

        $history = $resetHistory || !is_array($current['history'] ?? null)
            ? []
            : (array) $current['history'];

        $stepKey = (string) ($payload['step_key'] ?? $current['step_key'] ?? 'checking_data');
        $stepLabel = (string) ($payload['step_label'] ?? self::DEFAULT_STEP_LABELS[$stepKey] ?? $stepKey);
        $message = (string) ($payload['message'] ?? $stepLabel);

        $lastHistory = $history !== [] ? (array) end($history) : [];
        if (($lastHistory['step_key'] ?? null) !== $stepKey || ($lastHistory['status'] ?? null) !== ($payload['status'] ?? 'processing')) {
            $history[] = [
                'step_key' => $stepKey,
                'step_label' => $stepLabel,
                'status' => (string) ($payload['status'] ?? 'processing'),
                'recorded_at' => $isoNow,
            ];
        }

        $state = [
            'request_id' => $requestId,
            'actor_id' => $actorId,
            'status' => (string) ($payload['status'] ?? $current['status'] ?? 'processing'),
            'step_key' => $stepKey,
            'step_label' => $stepLabel,
            'message' => $message,
            'meta' => array_merge((array) ($current['meta'] ?? []), (array) ($payload['meta'] ?? [])),
            'created_at' => (string) ($current['created_at'] ?? $isoNow),
            'updated_at' => $isoNow,
            'started_at_unix' => (int) ($current['started_at_unix'] ?? $now),
            'history' => $history,
        ];

        $path = $this->path($requestId, $actorId);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return $this->enrichState($state);
        }

        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $tmpPath = $path . '.tmp';
        @file_put_contents($tmpPath, (string) $json, LOCK_EX);
        @rename($tmpPath, $path);

        return $this->enrichState($state);
    }

    private function enrichState(array $state): array
    {
        $startedAt = (int) ($state['started_at_unix'] ?? time());
        $elapsedSeconds = max(0, time() - $startedAt);
        $state['elapsed_seconds'] = $elapsedSeconds;
        $state['long_wait'] = $elapsedSeconds >= $this->longWaitThreshold();
        $state['is_processing'] = (string) ($state['status'] ?? '') === 'processing';

        return $state;
    }

    private function cleanupStaleFiles(): void
    {
        $root = $this->baseDirectory();
        if (!is_dir($root)) {
            return;
        }

        $ttl = max(300, (int) config('ai.admin_progress_ttl', 1800));
        $now = time();
        $directories = glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $directory) {
            $files = glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
            foreach ($files as $file) {
                $modifiedAt = @filemtime($file);
                if ($modifiedAt !== false && ($now - $modifiedAt) > $ttl) {
                    @unlink($file);
                }
            }
        }
    }

    private function baseDirectory(): string
    {
        return BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'admin-ai' . DIRECTORY_SEPARATOR . 'progress';
    }

    private function path(string $requestId, int $actorId): string
    {
        $actorDirectory = $this->baseDirectory() . DIRECTORY_SEPARATOR . 'actor-' . max(0, $actorId);
        return $actorDirectory . DIRECTORY_SEPARATOR . $this->normalizeRequestId($requestId) . '.json';
    }

    private function normalizeRequestId(string $requestId): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower(trim($requestId))) ?? '';
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            $normalized = 'req-' . substr(sha1((string) microtime(true)), 0, 12);
        }

        return substr($normalized, 0, 80);
    }

    private function longWaitThreshold(): int
    {
        return max(5, (int) config('ai.admin_progress_long_wait_seconds', 8));
    }
}
