<?php

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    private static bool $profileMediaSchemaReady = false;
    private static bool $baseRolesReady = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->ensureProfileMediaSchema();
        $this->ensureBaseRoles();
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.username = :username AND u.deleted_at IS NULL LIMIT 1');
        $stmt->execute(['username' => $username]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.email = :email AND u.deleted_at IS NULL LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = :id AND u.deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $this->findByEmail($identifier);
        }

        if (preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $identifier) === 1) {
            $user = $this->findByUsername($identifier);
            if ($user) {
                return $user;
            }
        }

        if (ctype_digit($identifier)) {
            $user = $this->find((int) $identifier);
            if ($user) {
                return $user;
            }
        }

        $stmt = $this->db->prepare('SELECT u.*, r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE LOWER(u.full_name) = LOWER(:full_name) AND u.deleted_at IS NULL
            ORDER BY u.id DESC
            LIMIT 1');
        $stmt->execute(['full_name' => $identifier]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): bool
    {
        $payload = array_merge([
            'phone' => null,
            'address' => null,
            'gender' => 'unknown',
            'birth_date' => null,
            'avatar' => null,
            'status' => 'active',
        ], $data);

        $stmt = $this->db->prepare('INSERT INTO users (role_id, full_name, username, email, phone, address, gender, birth_date, password, avatar, status, created_at, updated_at) VALUES (:role_id, :full_name, :username, :email, :phone, :address, :gender, :birth_date, :password, :avatar, :status, NOW(), NOW())');
        return $stmt->execute($payload);
    }

    public function updateProfile(int $id, array $data): bool
    {
        $payload = array_merge([
            'gender' => 'unknown',
            'birth_date' => null,
            'banner_media_meta' => null,
        ], $data);
        $payload['id'] = $id;

        $stmt = $this->db->prepare('UPDATE users SET full_name = :full_name, phone = :phone, address = :address, gender = :gender, birth_date = :birth_date, avatar = :avatar, banner_media = :banner_media, banner_media_type = :banner_media_type, banner_media_meta = :banner_media_meta, updated_at = NOW() WHERE id = :id');
        return $stmt->execute($payload);
    }

    public function updatePassword(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id, 'password' => $password]);
    }

    public function enableTwoFactor(int $id, string $secret): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET two_factor_secret = :secret, two_factor_enabled = 1, two_factor_confirmed_at = NOW(), updated_at = NOW() WHERE id = :id');
        return $stmt->execute([
            'id' => $id,
            'secret' => $secret,
        ]);
    }

    public function disableTwoFactor(int $id, bool $wipeSecret = true): bool
    {
        $secretValue = $wipeSecret ? null : '';
        $stmt = $this->db->prepare('UPDATE users SET two_factor_secret = :secret, two_factor_enabled = 0, two_factor_confirmed_at = NULL, updated_at = NOW() WHERE id = :id');

        return $stmt->execute([
            'id' => $id,
            'secret' => $secretValue,
        ]);
    }

    public function updateEmail(int $id, string $email): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET email = :email, updated_at = NOW() WHERE id = :id');
        return $stmt->execute([
            'id' => $id,
            'email' => $email,
        ]);
    }

    public function markTwoFactorUsed(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET two_factor_last_used_at = NOW(), updated_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function paginated(string $search, string $role, int $page, int $perPage): array
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $where[] = '(u.full_name LIKE :search OR u.email LIKE :search OR u.username LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($role !== '') {
            $where[] = 'r.name = :role';
            $params['role'] = $role;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM users u LEFT JOIN roles r ON r.id = u.role_id {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $meta = paginate_meta($total, $page, $perPage);

        $stmt = $this->db->prepare("SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id {$whereSql} ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $meta['per_page'], \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $meta['offset'], \PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'meta' => $meta];
    }

    public function updateByAdmin(int $id, array $data): bool
    {
        $payload = array_merge([
            'gender' => 'unknown',
            'birth_date' => null,
        ], $data);
        $payload['id'] = $id;

        $stmt = $this->db->prepare('UPDATE users SET role_id = :role_id, full_name = :full_name, username = :username, email = :email, phone = :phone, address = :address, gender = :gender, birth_date = :birth_date, status = :status, updated_at = NOW() WHERE id = :id');
        return $stmt->execute($payload);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET deleted_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET status = CASE WHEN status = "active" THEN "blocked" ELSE "active" END, updated_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function countAll(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) AS total FROM users WHERE deleted_at IS NULL');
        return (int) $stmt->fetch()['total'];
    }

    public function countRegisteredWithinDays(int $days): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS total FROM users WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)');
        $stmt->bindValue(':days', max(1, $days), \PDO::PARAM_INT);
        $stmt->execute();

        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function registeredByMonth(int $months = 6): array
    {
        $stmt = $this->db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
            FROM users
            WHERE deleted_at IS NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC");
        $stmt->bindValue(':months', max(1, $months), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function allRoles(): array
    {
        $stmt = $this->db->query('SELECT * FROM roles ORDER BY id ASC');
        return $stmt->fetchAll();
    }

    private function ensureProfileMediaSchema(): void
    {
        if (self::$profileMediaSchemaReady) {
            return;
        }

        try {
            $this->db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS banner_media VARCHAR(255) NULL AFTER avatar");
            $this->db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS banner_media_type ENUM('image','video') NULL AFTER banner_media");
            $this->db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS banner_media_meta TEXT NULL AFTER banner_media_type");
            $this->db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS gender ENUM('male','female','other','unknown') NOT NULL DEFAULT 'unknown' AFTER phone");
            $this->db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_date DATE NULL AFTER gender");
            $this->db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER address");
        } catch (\Throwable $exception) {
            security_log('Không thể mở rộng schema users cho banner media', ['error' => $exception->getMessage()]);
        }

        self::$profileMediaSchemaReady = true;
    }

    private function ensureBaseRoles(): void
    {
        if (self::$baseRolesReady) {
            return;
        }

        try {
            $this->db->exec("INSERT IGNORE INTO roles (id, name, created_at) VALUES
                (1, 'admin', NOW()),
                (2, 'user', NOW()),
                (3, 'staff', NOW())");
        } catch (\Throwable $exception) {
            security_log('Không thể seed role nền cho hệ thống', ['error' => $exception->getMessage()]);
        }

        self::$baseRolesReady = true;
    }
}
