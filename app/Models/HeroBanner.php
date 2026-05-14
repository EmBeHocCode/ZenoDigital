<?php

namespace App\Models;

use App\Core\Model;

class HeroBanner extends Model
{
    private static bool $schemaReady = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->ensureSchema();
        $this->seedDefaultSlides();
    }

    public function active(): array
    {
        $stmt = $this->db->query("SELECT *
            FROM hero_banners
            WHERE status = 'active' AND deleted_at IS NULL
            ORDER BY display_order ASC, id ASC");

        return $stmt->fetchAll() ?: [];
    }

    public function paginated(string $search, int $page, int $perPage): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $where[] = '(title LIKE :search OR subtitle LIKE :search OR link_url LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM hero_banners {$whereSql}");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);
        $meta = paginate_meta($total, $page, $perPage);

        $stmt = $this->db->prepare("SELECT *
            FROM hero_banners
            {$whereSql}
            ORDER BY display_order ASC, id ASC
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

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM hero_banners WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare('INSERT INTO hero_banners
            (title, subtitle, image_path, link_label, link_url, display_order, status, created_at, updated_at)
            VALUES
            (:title, :subtitle, :image_path, :link_label, :link_url, :display_order, :status, NOW(), NOW())');

        $ok = $stmt->execute([
            'title' => $data['title'],
            'subtitle' => $data['subtitle'],
            'image_path' => $data['image_path'],
            'link_label' => $data['link_label'],
            'link_url' => $data['link_url'],
            'display_order' => $data['display_order'],
            'status' => $data['status'],
        ]);

        return $ok ? (int) $this->db->lastInsertId() : false;
    }

    public function update(int $id, array $data): bool
    {
        $data['id'] = $id;
        $stmt = $this->db->prepare('UPDATE hero_banners SET
                title = :title,
                subtitle = :subtitle,
                image_path = :image_path,
                link_label = :link_label,
                link_url = :link_url,
                display_order = :display_order,
                status = :status,
                updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');

        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE hero_banners SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }

    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE hero_banners
            SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END,
                updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL");

        return $stmt->execute(['id' => $id]);
    }

    public function imageExists(string $fileName): bool
    {
        $fileName = basename(trim($fileName));
        if ($fileName === '') {
            return false;
        }

        return is_file(BASE_PATH . '/public/assets/images/slides/' . $fileName);
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS hero_banners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(180) NOT NULL,
                subtitle VARCHAR(500) NULL,
                image_path VARCHAR(255) NOT NULL,
                link_label VARCHAR(80) NULL,
                link_url VARCHAR(500) NULL,
                display_order INT NOT NULL DEFAULT 0,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                INDEX idx_hero_banners_status_order (status, display_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Throwable $exception) {
            security_log('Khong the khoi tao bang hero_banners', ['error' => $exception->getMessage()]);
        }

        self::$schemaReady = true;
    }

    private function seedDefaultSlides(): void
    {
        try {
            $count = (int) ($this->db->query('SELECT COUNT(*) AS total FROM hero_banners')->fetch()['total'] ?? 0);
            if ($count > 0) {
                return;
            }

            $cloudLink = $this->categoryLink(['cloud', 'vps']) ?: '#cloud-vps-plans';
            $gameLink = $this->categoryLink(['game']) ?: '#game-server';

            $defaults = [
                [
                    'title' => 'VPS tốc độ cao',
                    'subtitle' => 'Hiệu năng vượt trội cho website, app và workload cần phản hồi nhanh.',
                    'image_path' => 'slide1.png',
                    'link_label' => 'Xem gói ngay',
                    'link_url' => $cloudLink,
                    'display_order' => 1,
                ],
                [
                    'title' => 'Cloud VPS SSD NVMe',
                    'subtitle' => 'Lưu trữ NVMe tốc độ cao, ổn định và an toàn cho hệ thống online.',
                    'image_path' => 'slide2.png',
                    'link_label' => 'Xem gói NVMe',
                    'link_url' => $this->appendQuery($cloudLink, ['q' => 'NVMe']),
                    'display_order' => 2,
                ],
                [
                    'title' => 'Game Server',
                    'subtitle' => 'Máy chủ game tối ưu hiệu năng, kết nối ổn định và bảo vệ Anti-DDoS.',
                    'image_path' => 'slide3.png',
                    'link_label' => 'Khám phá Game Server',
                    'link_url' => $gameLink,
                    'display_order' => 3,
                ],
                [
                    'title' => 'Cloud cho doanh nghiệp',
                    'subtitle' => 'Giải pháp cloud ổn định, bảo mật và dễ mở rộng cho vận hành thực tế.',
                    'image_path' => 'slide4.png',
                    'link_label' => 'Tư vấn ngay',
                    'link_url' => '#business-cloud',
                    'display_order' => 4,
                ],
            ];

            foreach ($defaults as $banner) {
                if (!$this->imageExists($banner['image_path'])) {
                    continue;
                }

                $banner['status'] = 'active';
                $this->create($banner);
            }
        } catch (\Throwable $exception) {
            security_log('Khong the seed hero banners mac dinh', ['error' => $exception->getMessage()]);
        }
    }

    private function categoryLink(array $keywords): string
    {
        $stmt = $this->db->query('SELECT id, name, slug, description FROM categories WHERE deleted_at IS NULL ORDER BY id ASC');
        $categories = $stmt->fetchAll() ?: [];

        foreach ($categories as $category) {
            $haystack = strtolower(trim(implode(' ', [
                (string) ($category['name'] ?? ''),
                (string) ($category['slug'] ?? ''),
                (string) ($category['description'] ?? ''),
            ])));

            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($haystack, strtolower($keyword))) {
                    return 'products?' . http_build_query(['category_id' => (int) $category['id']]);
                }
            }
        }

        return '';
    }

    private function appendQuery(string $url, array $query): string
    {
        if ($url === '' || str_starts_with($url, '#')) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($query);
    }
}
