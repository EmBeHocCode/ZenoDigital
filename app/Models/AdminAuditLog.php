<?php

namespace App\Models;

use App\Core\Model;

class AdminAuditLog extends Model
{
    private static bool $schemaReady = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->ensureSchema();
    }

    public function create(int $adminId, string $action, string $entity, ?int $entityId = null, array $meta = []): void
    {
        $stmt = $this->db->prepare('INSERT INTO admin_audit_logs (admin_id, action_name, entity_name, entity_id, ip_address, user_agent, meta_json, created_at)
            VALUES (:admin_id, :action_name, :entity_name, :entity_id, :ip_address, :user_agent, :meta_json, NOW())');

        $stmt->execute([
            'admin_id' => $adminId,
            'action_name' => $action,
            'entity_name' => $entity,
            'entity_id' => $entityId,
            'ip_address' => client_ip(),
            'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function latest(int $limit = 50): array
    {
        $stmt = $this->db->prepare('SELECT l.*, u.full_name AS admin_name, u.email AS admin_email
            FROM admin_audit_logs l
            LEFT JOIN users u ON u.id = l.admin_id
            ORDER BY l.created_at DESC
            LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function paginated(string $search, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(u.full_name LIKE :search OR u.email LIKE :search OR l.action_name LIKE :search OR l.entity_name LIKE :search OR l.ip_address LIKE :search OR l.meta_json LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total
            FROM admin_audit_logs l
            LEFT JOIN users u ON u.id = l.admin_id
            {$whereSql}");
        $countStmt->execute($params);
        $total = (int) (($countStmt->fetch()['total'] ?? 0));

        $meta = paginate_meta($total, $page, $perPage);

        $stmt = $this->db->prepare("SELECT
                l.*,
                u.full_name AS admin_name,
                u.email AS admin_email
            FROM admin_audit_logs l
            LEFT JOIN users u ON u.id = l.admin_id
            {$whereSql}
            ORDER BY l.created_at DESC, l.id DESC
            LIMIT :limit OFFSET :offset");

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $meta['per_page'], \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $meta['offset'], \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll() ?: [],
            'meta' => $meta,
        ];
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS admin_audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                action_name VARCHAR(80) NOT NULL,
                entity_name VARCHAR(80) NOT NULL,
                entity_id INT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) NOT NULL,
                meta_json TEXT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_admin_audit_logs_user FOREIGN KEY (admin_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Throwable $exception) {
            security_log('Không thể khởi tạo bảng admin_audit_logs', ['error' => $exception->getMessage()]);
        }

        self::$schemaReady = true;
    }
}
