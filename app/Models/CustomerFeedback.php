<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class CustomerFeedback extends Model
{
    private static bool $schemaReady = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->ensureSchema();
    }

    public function create(array $data): array|false
    {
        $feedbackCode = $this->generateFeedbackCode();

        $stmt = $this->db->prepare('INSERT INTO customer_feedback (
                feedback_code,
                user_id,
                order_id,
                product_id,
                ai_session_id,
                source,
                page_type,
                feedback_type,
                sentiment,
                severity,
                rating,
                status,
                needs_follow_up,
                message,
                admin_note,
                customer_name,
                customer_email,
                created_at,
                updated_at
            ) VALUES (
                :feedback_code,
                :user_id,
                :order_id,
                :product_id,
                :ai_session_id,
                :source,
                :page_type,
                :feedback_type,
                :sentiment,
                :severity,
                :rating,
                :status,
                :needs_follow_up,
                :message,
                :admin_note,
                :customer_name,
                :customer_email,
                NOW(),
                NOW()
            )');

        $ok = $stmt->execute([
            'feedback_code' => $feedbackCode,
            'user_id' => $data['user_id'],
            'order_id' => $data['order_id'],
            'product_id' => $data['product_id'],
            'ai_session_id' => $data['ai_session_id'],
            'source' => $data['source'],
            'page_type' => $data['page_type'],
            'feedback_type' => $data['feedback_type'],
            'sentiment' => $data['sentiment'],
            'severity' => $data['severity'],
            'rating' => $data['rating'],
            'status' => $data['status'],
            'needs_follow_up' => $data['needs_follow_up'],
            'message' => $data['message'],
            'admin_note' => $data['admin_note'],
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
        ]);

        if (!$ok) {
            return false;
        }

        return [
            'id' => (int) $this->db->lastInsertId(),
            'feedback_code' => $feedbackCode,
        ];
    }

    public function paginated(string $search, string $status, string $sentiment, string $source, string $pageType, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(cf.feedback_code LIKE :search OR cf.message LIKE :search OR cf.customer_name LIKE :search OR cf.customer_email LIKE :search OR u.full_name LIKE :search OR u.email LIKE :search OR p.name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($status !== '') {
            $where[] = 'cf.status = :status';
            $params['status'] = $status;
        }

        if ($sentiment !== '') {
            $where[] = 'cf.sentiment = :sentiment';
            $params['sentiment'] = $sentiment;
        }

        if ($source !== '') {
            $where[] = 'cf.source = :source';
            $params['source'] = $source;
        }

        if ($pageType !== '') {
            $where[] = 'cf.page_type = :page_type';
            $params['page_type'] = $pageType;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total
            FROM customer_feedback cf
            LEFT JOIN users u ON u.id = cf.user_id
            LEFT JOIN products p ON p.id = cf.product_id
            {$whereSql}");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $meta = paginate_meta($total, $page, $perPage);

        $stmt = $this->db->prepare("SELECT
                cf.*,
                u.full_name AS user_name,
                u.email AS user_email,
                p.name AS product_name,
                o.order_code
            FROM customer_feedback cf
            LEFT JOIN users u ON u.id = cf.user_id
            LEFT JOIN products p ON p.id = cf.product_id
            LEFT JOIN orders o ON o.id = cf.order_id
            {$whereSql}
            ORDER BY cf.created_at DESC, cf.id DESC
            LIMIT :limit OFFSET :offset");

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $meta['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $meta['offset'], PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll() ?: [],
            'meta' => $meta,
        ];
    }

    public function summary(): array
    {
        $stmt = $this->db->query("SELECT
                COUNT(*) AS total_feedback,
                COALESCE(SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END), 0) AS total_new,
                COALESCE(SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END), 0) AS total_negative,
                COALESCE(SUM(CASE WHEN needs_follow_up = 1 THEN 1 ELSE 0 END), 0) AS total_follow_up,
                MAX(created_at) AS latest_feedback_at
            FROM customer_feedback");

        return $stmt->fetch() ?: [
            'total_feedback' => 0,
            'total_new' => 0,
            'total_negative' => 0,
            'total_follow_up' => 0,
            'latest_feedback_at' => null,
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT
                cf.*,
                u.full_name AS user_name,
                u.email AS user_email,
                p.name AS product_name,
                o.order_code
            FROM customer_feedback cf
            LEFT JOIN users u ON u.id = cf.user_id
            LEFT JOIN products p ON p.id = cf.product_id
            LEFT JOIN orders o ON o.id = cf.order_id
            WHERE cf.id = :id
            LIMIT 1");
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function findByFeedbackCode(string $feedbackCode): ?array
    {
        $feedbackCode = strtoupper(trim($feedbackCode));
        if ($feedbackCode === '') {
            return null;
        }

        $stmt = $this->db->prepare("SELECT
                cf.*,
                u.full_name AS user_name,
                u.email AS user_email,
                p.name AS product_name,
                o.order_code
            FROM customer_feedback cf
            LEFT JOIN users u ON u.id = cf.user_id
            LEFT JOIN products p ON p.id = cf.product_id
            LEFT JOIN orders o ON o.id = cf.order_id
            WHERE cf.feedback_code = :feedback_code
            LIMIT 1");
        $stmt->execute(['feedback_code' => $feedbackCode]);

        return $stmt->fetch() ?: null;
    }

    public function updateWorkflow(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('UPDATE customer_feedback
            SET status = :status,
                needs_follow_up = :needs_follow_up,
                admin_note = :admin_note,
                updated_at = NOW()
            WHERE id = :id');

        return $stmt->execute([
            'id' => $id,
            'status' => $data['status'] ?? 'reviewing',
            'needs_follow_up' => !empty($data['needs_follow_up']) ? 1 : 0,
            'admin_note' => $data['admin_note'] ?? null,
        ]);
    }

    public function distinctSources(): array
    {
        return $this->distinctColumnValues('source', ['ai_widget', 'storefront_header']);
    }

    public function distinctPageTypes(): array
    {
        return $this->distinctColumnValues('page_type', ['storefront', 'product', 'profile', 'support', 'other', 'storefront_header']);
    }

    public function latest(int $limit = 5): array
    {
        $stmt = $this->db->prepare("SELECT
                cf.feedback_code,
                cf.feedback_type,
                cf.sentiment,
                cf.severity,
                cf.status,
                cf.needs_follow_up,
                cf.created_at,
                u.full_name AS user_name,
                p.name AS product_name,
                o.order_code
            FROM customer_feedback cf
            LEFT JOIN users u ON u.id = cf.user_id
            LEFT JOIN products p ON p.id = cf.product_id
            LEFT JOIN orders o ON o.id = cf.order_id
            ORDER BY cf.created_at DESC, cf.id DESC
            LIMIT :limit");
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
            $this->db->exec('CREATE TABLE IF NOT EXISTS customer_feedback (
                id INT AUTO_INCREMENT PRIMARY KEY,
                feedback_code VARCHAR(60) NOT NULL UNIQUE,
                user_id INT NULL,
                order_id INT NULL,
                product_id INT NULL,
                ai_session_id VARCHAR(120) NOT NULL,
                source VARCHAR(40) NOT NULL DEFAULT \'ai_widget\',
                page_type VARCHAR(40) NOT NULL DEFAULT \'storefront\',
                feedback_type ENUM(\'general\',\'product\',\'delivery\',\'payment\',\'support\',\'system_bug\') NOT NULL DEFAULT \'general\',
                sentiment ENUM(\'positive\',\'neutral\',\'negative\') NOT NULL DEFAULT \'neutral\',
                severity ENUM(\'low\',\'medium\',\'high\') NOT NULL DEFAULT \'low\',
                rating TINYINT UNSIGNED NULL,
                status ENUM(\'new\',\'reviewing\',\'resolved\',\'closed\') NOT NULL DEFAULT \'new\',
                needs_follow_up TINYINT(1) NOT NULL DEFAULT 0,
                message TEXT NOT NULL,
                admin_note TEXT NULL,
                customer_name VARCHAR(120) NULL,
                customer_email VARCHAR(120) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_customer_feedback_user FOREIGN KEY (user_id) REFERENCES users(id),
                CONSTRAINT fk_customer_feedback_order FOREIGN KEY (order_id) REFERENCES orders(id),
                CONSTRAINT fk_customer_feedback_product FOREIGN KEY (product_id) REFERENCES products(id),
                INDEX idx_customer_feedback_status_created (status, created_at),
                INDEX idx_customer_feedback_sentiment (sentiment, created_at),
                INDEX idx_customer_feedback_user (user_id, created_at),
                INDEX idx_customer_feedback_product (product_id, created_at),
                INDEX idx_customer_feedback_source (source, created_at),
                INDEX idx_customer_feedback_page_type (page_type, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $feedbackTypeColumn = $this->db->query("SHOW COLUMNS FROM customer_feedback LIKE 'feedback_type'")->fetch();
            $feedbackType = strtolower((string) ($feedbackTypeColumn['Type'] ?? ''));
            if (!str_contains($feedbackType, "'system_bug'")) {
                $this->db->exec("ALTER TABLE customer_feedback MODIFY feedback_type ENUM('general','product','delivery','payment','support','system_bug') NOT NULL DEFAULT 'general'");
            }

            $this->ensureIndex('customer_feedback', 'idx_customer_feedback_source', 'CREATE INDEX idx_customer_feedback_source ON customer_feedback (source, created_at)');
            $this->ensureIndex('customer_feedback', 'idx_customer_feedback_page_type', 'CREATE INDEX idx_customer_feedback_page_type ON customer_feedback (page_type, created_at)');
        } catch (PDOException $exception) {
            security_log('Không thể khởi tạo schema customer_feedback', ['error' => $exception->getMessage()]);
        }

        self::$schemaReady = true;
    }

    private function generateFeedbackCode(): string
    {
        do {
            $code = 'FDB-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $stmt = $this->db->prepare('SELECT id FROM customer_feedback WHERE feedback_code = :feedback_code LIMIT 1');
            $stmt->execute(['feedback_code' => $code]);
            $exists = $stmt->fetch() !== false;
        } while ($exists);

        return $code;
    }

    private function distinctColumnValues(string $column, array $defaults = []): array
    {
        $allowedColumns = ['source', 'page_type'];
        if (!in_array($column, $allowedColumns, true)) {
            return array_values(array_unique($defaults));
        }

        try {
            $stmt = $this->db->query('SELECT DISTINCT ' . $column . ' FROM customer_feedback WHERE ' . $column . " IS NOT NULL AND " . $column . " <> '' ORDER BY " . $column . ' ASC');
            $values = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable $exception) {
            security_log('Không thể lấy distinct feedback field', [
                'column' => $column,
                'error' => $exception->getMessage(),
            ]);
            $values = [];
        }

        $normalized = array_values(array_unique(array_filter(array_map(static fn($value) => sanitize_text((string) $value, 60), array_merge($defaults, $values)))));
        return $normalized;
    }

    private function ensureIndex(string $table, string $indexName, string $createSql): void
    {
        $stmt = $this->db->query("SHOW INDEX FROM {$table} WHERE Key_name = " . $this->db->quote($indexName));

        if ($stmt->fetch() === false) {
            $this->db->exec($createSql);
        }
    }
}
