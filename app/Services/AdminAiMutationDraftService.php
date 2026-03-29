<?php

namespace App\Services;

class AdminAiMutationDraftService
{
    private const SESSION_KEY = 'admin_ai_mutation_drafts';
    private const TTL_SECONDS = 1800;

    public function store(string $sessionId, int $actorId, array $draft): array
    {
        $this->ensureWritableSession();
        $draftId = 'draft-' . substr(sha1(uniqid('admin-ai-draft', true)), 0, 12);
        $payload = [
            'draft_id' => $draftId,
            'actor_id' => $actorId,
            'session_id' => $sessionId,
            'created_at' => date('c'),
            'expires_at' => date('c', time() + self::TTL_SECONDS),
            'draft' => $draft,
        ];

        $_SESSION[self::SESSION_KEY][$sessionId] = $payload;

        return $payload;
    }

    public function current(string $sessionId, int $actorId): ?array
    {
        $this->ensureWritableSession();
        $record = $_SESSION[self::SESSION_KEY][$sessionId] ?? null;
        if (!is_array($record)) {
            return null;
        }

        if ((int) ($record['actor_id'] ?? 0) !== $actorId) {
            return null;
        }

        $expiresAt = strtotime((string) ($record['expires_at'] ?? ''));
        if ($expiresAt !== false && $expiresAt < time()) {
            unset($_SESSION[self::SESSION_KEY][$sessionId]);
            return null;
        }

        return $record;
    }

    public function clear(string $sessionId, int $actorId): void
    {
        $this->ensureWritableSession();
        $record = $_SESSION[self::SESSION_KEY][$sessionId] ?? null;
        if (!is_array($record)) {
            return;
        }

        if ((int) ($record['actor_id'] ?? 0) !== $actorId) {
            return;
        }

        unset($_SESSION[self::SESSION_KEY][$sessionId]);
    }

    public function clearSession(string $sessionId): void
    {
        $this->ensureWritableSession();
        unset($_SESSION[self::SESSION_KEY][$sessionId]);
    }

    private function ensureWritableSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }
}
