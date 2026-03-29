<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class AiChatMessage extends Model
{
    private static bool $schemaReady = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->ensureSchema();
    }

    public function create(array $payload): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ai_chat_messages (
                chat_session_id,
                role,
                content,
                meta_json,
                created_at
            ) VALUES (
                :chat_session_id,
                :role,
                :content,
                :meta_json,
                :created_at
            )'
        );

        return $stmt->execute($payload);
    }

    public function recentBySession(int $chatSessionId, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM (
                SELECT *
                FROM ai_chat_messages
                WHERE chat_session_id = :chat_session_id
                ORDER BY id DESC
                LIMIT :limit
            ) AS recent_messages
            ORDER BY id ASC'
        );
        $stmt->bindValue(':chat_session_id', $chatSessionId, PDO::PARAM_INT);
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
                'CREATE TABLE IF NOT EXISTS ai_chat_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    chat_session_id INT NOT NULL,
                    role ENUM("user","assistant","system") NOT NULL,
                    content TEXT NOT NULL,
                    meta_json LONGTEXT NULL,
                    created_at DATETIME NOT NULL,
                    CONSTRAINT fk_ai_chat_messages_session FOREIGN KEY (chat_session_id) REFERENCES ai_chat_sessions(id) ON DELETE CASCADE,
                    INDEX idx_ai_chat_messages_session (chat_session_id, id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->db->exec('ALTER TABLE ai_chat_messages ADD COLUMN IF NOT EXISTS meta_json LONGTEXT NULL AFTER content');
        } catch (\Throwable $exception) {
            security_log('Không thể đảm bảo schema ai_chat_messages', [
                'error' => $exception->getMessage(),
            ]);
        }

        self::$schemaReady = true;
    }
}
