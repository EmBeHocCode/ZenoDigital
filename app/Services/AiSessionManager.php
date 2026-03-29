<?php

namespace App\Services;

class AiSessionManager
{
    private const STORAGE_KEY = '_ai_sessions';

    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function resolveSessionId(string $scope, string $requestedSessionId, string $prefix, ?int $actorId = null): string
    {
        $storageKey = $this->storageKey($scope, $actorId);
        $storedSessionId = (string) ($_SESSION[self::STORAGE_KEY][$storageKey] ?? '');

        if ($storedSessionId !== '') {
            return $storedSessionId;
        }

        // Added: always generate the first AI session id server-side to avoid trusting client-chosen ids.
        $candidate = $this->generateSessionId($prefix);

        return $this->rememberSessionId($scope, $candidate, $prefix, $actorId);
    }

    public function rememberSessionId(string $scope, string $sessionId, string $prefix, ?int $actorId = null): string
    {
        $normalized = $this->normalizeSessionId($sessionId, $prefix);

        if (!isset($_SESSION[self::STORAGE_KEY]) || !is_array($_SESSION[self::STORAGE_KEY])) {
            $_SESSION[self::STORAGE_KEY] = [];
        }

        $_SESSION[self::STORAGE_KEY][$this->storageKey($scope, $actorId)] = $normalized;

        return $normalized;
    }

    public function forgetSessionId(string $scope, ?int $actorId = null): void
    {
        $storageKey = $this->storageKey($scope, $actorId);
        if (isset($_SESSION[self::STORAGE_KEY][$storageKey])) {
            unset($_SESSION[self::STORAGE_KEY][$storageKey]);
        }
    }

    private function generateSessionId(string $prefix): string
    {
        try {
            $random = bin2hex(random_bytes(8));
        } catch (\Throwable $exception) {
            $random = uniqid('fallback', true);
        }

        return $this->normalizeSessionId($prefix . '-' . $random, $prefix);
    }

    private function normalizeSessionId(string $sessionId, string $prefix): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower(trim($sessionId))) ?? '';
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            $normalized = trim($prefix) !== '' ? strtolower(trim($prefix)) . '-session' : 'ai-session';
        }

        return mb_substr($normalized, 0, 120);
    }

    private function storageKey(string $scope, ?int $actorId): string
    {
        $scope = strtolower(trim($scope)) ?: 'default';
        $actor = $actorId !== null ? 'user-' . $actorId : 'guest';

        return $scope . ':' . $actor;
    }
}
