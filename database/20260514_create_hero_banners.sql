CREATE TABLE IF NOT EXISTS hero_banners (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO hero_banners (title, subtitle, image_path, link_label, link_url, display_order, status, created_at, updated_at)
SELECT 'VPS tốc độ cao', 'Hiệu năng vượt trội cho website, app và workload cần phản hồi nhanh.', 'slide1.png', 'Xem gói ngay', 'products?category_id=1', 1, 'active', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM hero_banners WHERE image_path = 'slide1.png' AND deleted_at IS NULL);

INSERT INTO hero_banners (title, subtitle, image_path, link_label, link_url, display_order, status, created_at, updated_at)
SELECT 'Cloud VPS SSD NVMe', 'Lưu trữ NVMe tốc độ cao, ổn định và an toàn cho hệ thống online.', 'slide2.png', 'Xem gói NVMe', 'products?category_id=1&q=NVMe', 2, 'active', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM hero_banners WHERE image_path = 'slide2.png' AND deleted_at IS NULL);

INSERT INTO hero_banners (title, subtitle, image_path, link_label, link_url, display_order, status, created_at, updated_at)
SELECT 'Game Server', 'Máy chủ game tối ưu hiệu năng, kết nối ổn định và bảo vệ Anti-DDoS.', 'slide3.png', 'Khám phá Game Server', 'products?category_id=2', 3, 'active', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM hero_banners WHERE image_path = 'slide3.png' AND deleted_at IS NULL);

INSERT INTO hero_banners (title, subtitle, image_path, link_label, link_url, display_order, status, created_at, updated_at)
SELECT 'Cloud cho doanh nghiệp', 'Giải pháp cloud ổn định, bảo mật và dễ mở rộng cho vận hành thực tế.', 'slide4.png', 'Tư vấn ngay', '#business-cloud', 4, 'active', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM hero_banners WHERE image_path = 'slide4.png' AND deleted_at IS NULL);
