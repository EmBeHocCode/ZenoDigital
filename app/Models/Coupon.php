<?php

namespace App\Models;

use App\Core\Model;

class Coupon extends Model
{
    private static bool $schemaReady = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->ensureSchema();
    }

    public function paginated(string $search, string $status, int $page, int $perPage): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $where[] = '(code LIKE :search OR description LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM coupons {$whereSql}");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $meta = paginate_meta($total, $page, $perPage);

        $stmt = $this->db->prepare("SELECT * FROM coupons {$whereSql} ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $meta['per_page'], \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $meta['offset'], \PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll() ?: [], 'meta' => $meta];
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare('INSERT INTO coupons (code, description, discount_percent, max_uses, used_count, expires_at, status, created_at, updated_at)
            VALUES (:code, :description, :discount_percent, :max_uses, 0, :expires_at, :status, NOW(), NOW())');

        return $stmt->execute($data);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE code = :code AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['code' => $code]);

        return $stmt->fetch() ?: null;
    }

    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE coupons SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL");
        return $stmt->execute(['id' => $id]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE coupons SET status = :status, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        return $stmt->execute([
            'id' => $id,
            'status' => $status,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE coupons SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function countActive(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) AS total FROM coupons WHERE status = 'active' AND deleted_at IS NULL");
        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function summary(): array
    {
        $stmt = $this->db->query("SELECT
                COUNT(*) AS total_coupons,
                COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_coupons,
                COALESCE(SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END), 0) AS inactive_coupons,
                COALESCE(SUM(CASE WHEN expires_at IS NOT NULL AND expires_at >= NOW() AND expires_at <= DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS expiring_soon
            FROM coupons
            WHERE deleted_at IS NULL");

        return $stmt->fetch() ?: [
            'total_coupons' => 0,
            'active_coupons' => 0,
            'inactive_coupons' => 0,
            'expiring_soon' => 0,
        ];
    }

    public function latest(int $limit = 5): array
    {
        $stmt = $this->db->prepare("SELECT
                code,
                description,
                discount_percent,
                max_uses,
                used_count,
                expires_at,
                status,
                created_at
            FROM coupons
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC, id DESC
            LIMIT :limit");
        $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS coupons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(80) NOT NULL UNIQUE,
                description VARCHAR(255) NULL,
                discount_percent INT NOT NULL,
                max_uses INT NOT NULL DEFAULT 0,
                used_count INT NOT NULL DEFAULT 0,
                expires_at DATETIME NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Throwable $exception) {
            security_log('Không thể khởi tạo bảng coupons', ['error' => $exception->getMessage()]);
        }

        self::$schemaReady = true;
    }
}
