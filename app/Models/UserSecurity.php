<?php

namespace App\Models;

use App\Core\Model;
use PDOException;

class UserSecurity extends Model
{
    private static bool $schemaReady = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->ensureSchema();
    }

    public function logLoginActivity(int $userId, string $ip, string $device, string $location): void
    {
        $stmt = $this->db->prepare('INSERT INTO user_login_activities (user_id, ip_address, device, location, logged_in_at) VALUES (:user_id, :ip_address, :device, :location, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'ip_address' => sanitize_text($ip, 45),
            'device' => sanitize_text($device, 180),
            'location' => sanitize_text($location, 120),
        ]);
    }

    public function recentLoginActivities(int $userId, int $limit = 10): array
    {
        $stmt = $this->db->prepare('SELECT id, ip_address, device, location, logged_in_at FROM user_login_activities WHERE user_id = :user_id ORDER BY logged_in_at DESC LIMIT :limit');
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min($limit, 50)), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function createSession(int $userId, string $sessionToken, string $ip, string $userAgent, string $device): void
    {
        $stmt = $this->db->prepare('INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, device, created_at, last_activity_at, revoked_at) VALUES (:user_id, :session_token, :ip_address, :user_agent, :device, NOW(), NOW(), NULL)');
        $stmt->execute([
            'user_id' => $userId,
            'session_token' => $sessionToken,
            'ip_address' => sanitize_text($ip, 45),
            'user_agent' => sanitize_text($userAgent, 255),
            'device' => sanitize_text($device, 180),
        ]);
    }

    public function touchSession(string $sessionToken): void
    {
        $stmt = $this->db->prepare('UPDATE user_sessions SET last_activity_at = NOW() WHERE session_token = :session_token AND revoked_at IS NULL');
        $stmt->execute(['session_token' => $sessionToken]);
    }

    public function isSessionActive(int $userId, string $sessionToken): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM user_sessions WHERE user_id = :user_id AND session_token = :session_token AND revoked_at IS NULL LIMIT 1');
        $stmt->execute([
            'user_id' => $userId,
            'session_token' => $sessionToken,
        ]);

        return (bool) $stmt->fetch();
    }

    public function revokeSession(int $userId, string $sessionToken): void
    {
        $stmt = $this->db->prepare('UPDATE user_sessions SET revoked_at = NOW(), last_activity_at = NOW() WHERE user_id = :user_id AND session_token = :session_token AND revoked_at IS NULL');
        $stmt->execute([
            'user_id' => $userId,
            'session_token' => $sessionToken,
        ]);
    }

    public function revokeOtherSessions(int $userId, string $currentSessionToken): int
    {
        $stmt = $this->db->prepare('UPDATE user_sessions SET revoked_at = NOW(), last_activity_at = NOW() WHERE user_id = :user_id AND session_token <> :session_token AND revoked_at IS NULL');
        $stmt->execute([
            'user_id' => $userId,
            'session_token' => $currentSessionToken,
        ]);

        return $stmt->rowCount();
    }

    public function activeSessions(int $userId, string $currentSessionToken, int $limit = 8): array
    {
        $stmt = $this->db->prepare('SELECT id, ip_address, device, created_at, last_activity_at, session_token FROM user_sessions WHERE user_id = :user_id AND revoked_at IS NULL ORDER BY last_activity_at DESC LIMIT :limit');
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min($limit, 20)), \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['is_current'] = hash_equals((string) $row['session_token'], $currentSessionToken);
            unset($row['session_token']);
        }
        unset($row);

        return $rows;
    }

    public function replaceBackupCodes(int $userId, array $plainCodes): void
    {
        $this->clearBackupCodes($userId);

        $stmt = $this->db->prepare('INSERT INTO user_backup_codes (user_id, code_hash, code_hint, created_at) VALUES (:user_id, :code_hash, :code_hint, NOW())');
        foreach ($plainCodes as $code) {
            $normalized = two_factor_normalize_backup_code((string) $code);
            if ($normalized === '') {
                continue;
            }

            $stmt->execute([
                'user_id' => $userId,
                'code_hash' => password_hash($normalized, PASSWORD_DEFAULT),
                'code_hint' => substr($normalized, 0, 2) . '****' . substr($normalized, -2),
            ]);
        }
    }

    public function clearBackupCodes(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM user_backup_codes WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function consumeBackupCode(int $userId, string $inputCode): bool
    {
        $normalized = two_factor_normalize_backup_code($inputCode);
        if ($normalized === '') {
            return false;
        }

        $stmt = $this->db->prepare('SELECT id, code_hash FROM user_backup_codes WHERE user_id = :user_id AND used_at IS NULL ORDER BY id ASC');
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as $row) {
            if (password_verify($normalized, (string) $row['code_hash'])) {
                $update = $this->db->prepare('UPDATE user_backup_codes SET used_at = NOW() WHERE id = :id AND used_at IS NULL');
                $update->execute(['id' => (int) $row['id']]);
                return $update->rowCount() > 0;
            }
        }

        return false;
    }

    public function backupCodesStats(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS total, SUM(CASE WHEN used_at IS NULL THEN 1 ELSE 0 END) AS available FROM user_backup_codes WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch() ?: ['total' => 0, 'available' => 0];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'available' => (int) ($row['available'] ?? 0),
        ];
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        try {
            $this->db->exec('CREATE TABLE IF NOT EXISTS user_backup_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                code_hint VARCHAR(20) NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_user_backup_codes_user FOREIGN KEY (user_id) REFERENCES users(id),
                INDEX idx_user_backup_codes_user (user_id),
                INDEX idx_user_backup_codes_used (used_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $this->db->exec('CREATE TABLE IF NOT EXISTS user_login_activities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                device VARCHAR(180) NOT NULL,
                location VARCHAR(120) NOT NULL,
                logged_in_at DATETIME NOT NULL,
                CONSTRAINT fk_user_login_activities_user FOREIGN KEY (user_id) REFERENCES users(id),
                INDEX idx_user_login_activities_user_time (user_id, logged_in_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $this->db->exec('CREATE TABLE IF NOT EXISTS user_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                session_token VARCHAR(128) NOT NULL UNIQUE,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) NOT NULL,
                device VARCHAR(180) NOT NULL,
                created_at DATETIME NOT NULL,
                last_activity_at DATETIME NOT NULL,
                revoked_at DATETIME NULL,
                CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id),
                INDEX idx_user_sessions_user_active (user_id, revoked_at),
                INDEX idx_user_sessions_last_activity (last_activity_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } catch (PDOException $exception) {
            security_log('Không thể khởi tạo bảng security', ['error' => $exception->getMessage()]);
        }

        self::$schemaReady = true;
    }
}
