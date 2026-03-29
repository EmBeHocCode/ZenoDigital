<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class AiChatSession extends Model
{
    private static bool $schemaReady = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->ensureSchema();
    }

    public function findActiveByUser(int $userId, string $channel, string $conversationMode): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ai_chat_sessions
             WHERE user_id = :user_id
               AND channel = :channel
               AND conversation_mode = :conversation_mode
               AND status = "active"
             ORDER BY last_activity_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'channel' => $channel,
            'conversation_mode' => $conversationMode,
        ]);

        return $stmt->fetch() ?: null;
    }

    public function findBySessionKey(string $sessionKey, int $userId, string $channel): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ai_chat_sessions
             WHERE session_key = :session_key
               AND user_id = :user_id
               AND channel = :channel
             LIMIT 1'
        );
        $stmt->execute([
            'session_key' => $sessionKey,
            'user_id' => $userId,
            'channel' => $channel,
        ]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $payload): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ai_chat_sessions (
                session_key,
                user_id,
                channel,
                conversation_mode,
                actor_role,
                status,
                pending_request_id,
                created_at,
                last_activity_at,
                expires_at,
                updated_at
            ) VALUES (
                :session_key,
                :user_id,
                :channel,
                :conversation_mode,
                :actor_role,
                :status,
                :pending_request_id,
                :created_at,
                :last_activity_at,
                :expires_at,
                :updated_at
            )'
        );
        $stmt->execute($payload);

        return $this->findById((int) $this->db->lastInsertId()) ?? $payload;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_chat_sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function touchSession(int $id, string $expiresAt, ?string $pendingRequestId = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE ai_chat_sessions
             SET last_activity_at = NOW(),
                 expires_at = :expires_at,
                 pending_request_id = :pending_request_id,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'expires_at' => $expiresAt,
            'pending_request_id' => $pendingRequestId,
        ]);
    }

    public function markPending(int $id, string $requestId, string $expiresAt): void
    {
        $this->touchSession($id, $expiresAt, $requestId);
    }

    public function clearPending(int $id, string $expiresAt): void
    {
        $this->touchSession($id, $expiresAt, null);
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare(
            'UPDATE ai_chat_sessions
             SET status = :status,
                 pending_request_id = NULL,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => $status,
        ]);
    }

    public function expireStaleSessions(int $userId, string $channel, string $conversationMode): int
    {
        $stmt = $this->db->prepare(
            'UPDATE ai_chat_sessions
             SET status = "expired",
                 pending_request_id = NULL,
                 updated_at = NOW()
             WHERE user_id = :user_id
               AND channel = :channel
               AND conversation_mode = :conversation_mode
               AND status = "active"
               AND expires_at <= NOW()'
        );
        $stmt->execute([
            'user_id' => $userId,
            'channel' => $channel,
            'conversation_mode' => $conversationMode,
        ]);

        return $stmt->rowCount();
    }

    public function closeOtherActiveSessions(int $userId, string $channel, string $conversationMode, int $exceptId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE ai_chat_sessions
             SET status = "closed",
                 pending_request_id = NULL,
                 updated_at = NOW()
             WHERE user_id = :user_id
               AND channel = :channel
               AND conversation_mode = :conversation_mode
               AND status = "active"
               AND id <> :except_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'channel' => $channel,
            'conversation_mode' => $conversationMode,
            'except_id' => $exceptId,
        ]);
    }

    public function allRecentByUser(int $userId, string $channel, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ai_chat_sessions
             WHERE user_id = :user_id
               AND channel = :channel
             ORDER BY updated_at DESC, id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':channel', $channel, PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        try {
            $this->db->exec(
                'CREATE TABLE IF NOT EXISTS ai_chat_sessions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    session_key VARCHAR(120) NOT NULL UNIQUE,
                    user_id INT NOT NULL,
                    channel VARCHAR(30) NOT NULL,
                    conversation_mode VARCHAR(40) NOT NULL,
                    actor_role VARCHAR(60) NOT NULL,
                    status ENUM("active","reset","expired","closed") NOT NULL DEFAULT "active",
                    pending_request_id VARCHAR(80) NULL,
                    created_at DATETIME NOT NULL,
                    last_activity_at DATETIME NOT NULL,
                    expires_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    CONSTRAINT fk_ai_chat_sessions_user FOREIGN KEY (user_id) REFERENCES users(id),
                    INDEX idx_ai_chat_sessions_user_active (user_id, channel, conversation_mode, status, last_activity_at),
                    INDEX idx_ai_chat_sessions_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->db->exec('ALTER TABLE ai_chat_sessions ADD COLUMN IF NOT EXISTS conversation_mode VARCHAR(40) NOT NULL DEFAULT "admin_copilot" AFTER channel');
            $this->db->exec('ALTER TABLE ai_chat_sessions ADD COLUMN IF NOT EXISTS actor_role VARCHAR(60) NOT NULL DEFAULT "admin" AFTER conversation_mode');
            $this->db->exec('ALTER TABLE ai_chat_sessions ADD COLUMN IF NOT EXISTS status ENUM("active","reset","expired","closed") NOT NULL DEFAULT "active" AFTER actor_role');
            $this->db->exec('ALTER TABLE ai_chat_sessions ADD COLUMN IF NOT EXISTS pending_request_id VARCHAR(80) NULL AFTER status');
            $this->db->exec('ALTER TABLE ai_chat_sessions ADD COLUMN IF NOT EXISTS last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_at');
            $this->db->exec('ALTER TABLE ai_chat_sessions ADD COLUMN IF NOT EXISTS expires_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER last_activity_at');
            $this->db->exec('ALTER TABLE ai_chat_sessions ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER expires_at');
        } catch (\Throwable $exception) {
            security_log('Không thể đảm bảo schema ai_chat_sessions', [
                'error' => $exception->getMessage(),
            ]);
        }

        self::$schemaReady = true;
    }
}
