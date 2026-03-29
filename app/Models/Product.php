<?php

namespace App\Models;

use App\Core\Model;

class Product extends Model
{
    public function featured(int $limit = 8): array
    {
        $stmt = $this->db->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.status = "active" AND p.deleted_at IS NULL ORDER BY p.created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function featuredByCategoryIds(array $categoryIds, int $limit = 8): array
    {
        $categoryIds = array_values(array_unique(array_map('intval', array_filter($categoryIds, static fn ($value) => (int) $value > 0))));
        if ($categoryIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($categoryIds as $index => $categoryId) {
            $key = ':category_' . $index;
            $placeholders[] = $key;
            $params[$key] = $categoryId;
        }

        $sql = 'SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.status = "active"
                AND p.deleted_at IS NULL
                AND p.category_id IN (' . implode(', ', $placeholders) . ')
            ORDER BY p.created_at DESC
            LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function paginated(array $filters, int $page, int $perPage): array
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['q'])) {
            $searchTerm = '%' . $filters['q'] . '%';
            $where[] = '(p.name LIKE :q_name OR p.short_description LIKE :q_short_description)';
            $params['q_name'] = $searchTerm;
            $params['q_short_description'] = $searchTerm;
        }

        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if ($filters['min_price'] !== '') {
            $where[] = 'p.price >= :min_price';
            $params['min_price'] = (float) $filters['min_price'];
        }

        if ($filters['max_price'] !== '') {
            $where[] = 'p.price <= :max_price';
            $params['max_price'] = (float) $filters['max_price'];
        }

        if (!empty($filters['cpu'])) {
            $where[] = 'p.specs LIKE :cpu';
            $params['cpu'] = '%CPU: ' . $filters['cpu'] . '%';
        }

        if (!empty($filters['ram'])) {
            $where[] = 'p.specs LIKE :ram';
            $params['ram'] = '%RAM: ' . $filters['ram'] . '%';
        }

        if (!empty($filters['disk'])) {
            $where[] = '(p.specs LIKE :disk_nvme OR p.specs LIKE :disk_ssd)';
            $params['disk_nvme'] = '%NVMe%';
            $params['disk_ssd'] = '%SSD%';
        }

        if (!empty($filters['location'])) {
            $where[] = 'p.specs LIKE :location';
            $params['location'] = '%' . $filters['location'] . '%';
        }

        if (!empty($filters['plan_type'])) {
            $where[] = 'p.name LIKE :plan_type';
            $params['plan_type'] = '%' . $filters['plan_type'] . '%';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM products p {$whereSql}");
        $this->bindPaginatedParams($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetch()['total'];

        $meta = paginate_meta($total, $page, $perPage);

        $sort = 'p.created_at DESC';
        if (($filters['sort'] ?? '') === 'price_asc') {
            $sort = 'p.price ASC';
        } elseif (($filters['sort'] ?? '') === 'price_desc') {
            $sort = 'p.price DESC';
        } elseif (($filters['sort'] ?? '') === 'popular') {
            $sort = 'p.created_at DESC';
        }

        $sql = "SELECT p.*, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                {$whereSql}
                ORDER BY {$sort}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $this->bindPaginatedParams($stmt, $params);
        $stmt->bindValue(':limit', $meta['per_page'], \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $meta['offset'], \PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'meta' => $meta];
    }

    private function bindPaginatedParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = $key === 'category_id' ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $type);
        }
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.description AS category_description FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = :id AND p.deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.description AS category_description
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.slug = :slug AND p.deleted_at IS NULL
            LIMIT 1');
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetch() ?: null;
    }

    public function findByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.description AS category_description
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE LOWER(p.name) = LOWER(:name) AND p.deleted_at IS NULL
            ORDER BY p.id DESC
            LIMIT 1');
        $stmt->execute(['name' => $name]);

        return $stmt->fetch() ?: null;
    }

    public function related(int $categoryId, int $exceptId, int $limit = 4): array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE category_id = :category_id AND id != :except_id AND status = "active" AND deleted_at IS NULL ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':category_id', $categoryId, \PDO::PARAM_INT);
        $stmt->bindValue(':except_id', $exceptId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare('INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at) VALUES (:category_id, :name, :slug, :price, :short_description, :description, :specs, :image, :stock_status, :status, NOW(), NOW())');
        $ok = $stmt->execute($data);
        return $ok ? (int) $this->db->lastInsertId() : false;
    }

    public function update(int $id, array $data): bool
    {
        $data['id'] = $id;
        $stmt = $this->db->prepare('UPDATE products SET category_id = :category_id, name = :name, slug = :slug, price = :price, short_description = :short_description, description = :description, specs = :specs, image = :image, stock_status = :stock_status, status = :status, updated_at = NOW() WHERE id = :id');
        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE products SET deleted_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function saveImages(int $productId, array $images): void
    {
        if (!$images) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO product_images (product_id, image_path, created_at) VALUES (:product_id, :image_path, NOW())');

        foreach ($images as $imagePath) {
            $stmt->execute([
                'product_id' => $productId,
                'image_path' => $imagePath,
            ]);
        }
    }

    public function images(int $productId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM product_images WHERE product_id = :product_id ORDER BY id DESC');
        $stmt->execute(['product_id' => $productId]);
        return $stmt->fetchAll();
    }

    public function countAll(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) AS total FROM products WHERE deleted_at IS NULL');
        return (int) $stmt->fetch()['total'];
    }

    public function latest(int $limit = 5): array
    {
        $stmt = $this->db->prepare('SELECT id, name, price, created_at FROM products WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function topSelling(int $limit = 5): array
    {
        $stmt = $this->db->prepare("SELECT p.id, p.name, c.name AS category_name,
                COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS sold_qty,
                COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.total_price ELSE 0 END), 0) AS sold_revenue
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN order_items oi ON oi.product_id = p.id
            LEFT JOIN orders o ON o.id = oi.order_id
                AND o.deleted_at IS NULL
                AND o.status IN ('paid', 'processing', 'completed')
            WHERE p.deleted_at IS NULL
            GROUP BY p.id, p.name, c.name
            ORDER BY sold_qty DESC, sold_revenue DESC
            LIMIT :limit");
        $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }
}
