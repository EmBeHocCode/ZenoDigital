<?php

namespace App\Models;

use App\Core\Model;

class Category extends Model
{
    public function storefrontGroups(?array $categories = null): array
    {
        $categories = is_array($categories) ? array_values($categories) : $this->all();
        $cloud = [];
        $secondary = [];

        foreach ($categories as $category) {
            if ($this->isCloudStorefrontCategory($category)) {
                $cloud[] = $category;
                continue;
            }

            $secondary[] = $category;
        }

        usort($cloud, fn (array $left, array $right): int => $this->storefrontPriority($left) <=> $this->storefrontPriority($right));
        usort($secondary, static fn (array $left, array $right): int => strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? '')));

        return [
            'cloud' => $cloud,
            'secondary' => $secondary,
        ];
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function paginated(string $search, int $page, int $perPage): array
    {
        $where = 'WHERE deleted_at IS NULL';
        $params = [];

        if ($search !== '') {
            $where .= ' AND name LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM categories {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $meta = paginate_meta($total, $page, $perPage);

        $sql = "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS product_count
                FROM categories c
                {$where}
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $meta['per_page'], \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $meta['offset'], \PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'meta' => $meta];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM categories WHERE slug = :slug AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetch() ?: null;
    }

    public function findByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM categories WHERE LOWER(name) = LOWER(:name) AND deleted_at IS NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute(['name' => $name]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare('INSERT INTO categories (name, slug, description, created_at, updated_at) VALUES (:name, :slug, :description, NOW(), NOW())');
        return $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('UPDATE categories SET name = :name, slug = :slug, description = :description, updated_at = NOW() WHERE id = :id');
        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
        ]);
    }

    public function canDelete(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS total FROM products WHERE category_id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        return ((int) $stmt->fetch()['total']) === 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE categories SET deleted_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function topCategories(int $limit = 5): array
    {
        $stmt = $this->db->prepare("SELECT c.id, c.name,
                COUNT(DISTINCT p.id) AS product_total,
                COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS sold_qty
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id AND p.deleted_at IS NULL
            LEFT JOIN order_items oi ON oi.product_id = p.id
            LEFT JOIN orders o ON o.id = oi.order_id
                AND o.deleted_at IS NULL
                AND o.status IN ('paid', 'processing', 'completed')
            WHERE c.deleted_at IS NULL
            GROUP BY c.id, c.name
            ORDER BY sold_qty DESC, product_total DESC
            LIMIT :limit");
        $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function isCloudStorefrontCategory(array $category): bool
    {
        $haystack = mb_strtolower(trim(implode(' ', [
            (string) ($category['name'] ?? ''),
            (string) ($category['slug'] ?? ''),
            (string) ($category['description'] ?? ''),
        ])), 'UTF-8');

        if ($haystack === '') {
            return false;
        }

        foreach (['game', 'wallet', 'steam', 'sim', 'thẻ', 'the-', 'card', 'license'] as $negativeKeyword) {
            if (str_contains($haystack, $negativeKeyword)) {
                return false;
            }
        }

        foreach (['cloud', 'vps', 'máy chủ ảo', 'virtual private server'] as $positiveKeyword) {
            if (str_contains($haystack, $positiveKeyword)) {
                return true;
            }
        }

        return false;
    }

    private function storefrontPriority(array $category): int
    {
        $name = mb_strtolower((string) ($category['name'] ?? ''), 'UTF-8');
        $slug = mb_strtolower((string) ($category['slug'] ?? ''), 'UTF-8');
        $haystack = $name . ' ' . $slug;

        if (str_contains($haystack, 'cloud server') && str_contains($haystack, 'vps')) {
            return 0;
        }

        if (str_contains($haystack, 'cloud')) {
            return 1;
        }

        if (str_contains($haystack, 'vps')) {
            return 2;
        }

        return 10;
    }
}
