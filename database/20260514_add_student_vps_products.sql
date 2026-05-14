SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
START TRANSACTION;

INSERT INTO categories (name, slug, description, created_at, updated_at)
SELECT 'VPS Giá Rẻ Cho Sinh Viên', 'vps-gia-re-cho-sinh-vien', 'Các gói Cloud VPS nhỏ gọn, chi phí thấp cho sinh viên học tập, làm đồ án và chạy thử nghiệm.', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE slug = 'vps-gia-re-cho-sinh-vien' AND deleted_at IS NULL
);

INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at)
SELECT c.id, 'Student Nano', 'student-nano', 35000, 'Gói rẻ nhất để học Linux, SSH và chạy web nhỏ.', 'Phù hợp sinh viên mới bắt đầu học VPS, deploy landing page, web tĩnh hoặc môi trường test rất nhẹ.', 'CPU: 1 vCPU\nRAM: 1 GB\nNVMe: 20 GB\nBandwidth: 1 TB\nIP: 1 IPv4\nLocation: VN', NULL, 'in_stock', 'active', NOW(), NOW()
FROM categories c
WHERE c.slug = 'vps-gia-re-cho-sinh-vien'
  AND c.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = 'student-nano' AND p.deleted_at IS NULL)
LIMIT 1;

INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at)
SELECT c.id, 'Student Basic', 'student-basic', 59000, 'Khởi đầu nhẹ nhàng cho bài tập web, bot nhỏ và demo API.', 'Phù hợp chạy project PHP/Node nhỏ, bot học tập, API mini hoặc website cá nhân ít truy cập.', 'CPU: 1 vCPU\nRAM: 2 GB\nNVMe: 30 GB\nBandwidth: 2 TB\nIP: 1 IPv4\nLocation: VN', NULL, 'in_stock', 'active', NOW(), NOW()
FROM categories c
WHERE c.slug = 'vps-gia-re-cho-sinh-vien'
  AND c.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = 'student-basic' AND p.deleted_at IS NULL)
LIMIT 1;

INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at)
SELECT c.id, 'Student Plus', 'student-plus', 89000, 'Cấu hình cân bằng cho đồ án web và môi trường dev nhỏ.', 'Phù hợp đồ án Laravel/PHP, WordPress nhẹ, API mini hoặc bot cần thêm CPU nhưng vẫn tiết kiệm.', 'CPU: 2 vCPU\nRAM: 2 GB\nNVMe: 40 GB\nBandwidth: 2 TB\nIP: 1 IPv4\nLocation: VN', NULL, 'in_stock', 'active', NOW(), NOW()
FROM categories c
WHERE c.slug = 'vps-gia-re-cho-sinh-vien'
  AND c.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = 'student-plus' AND p.deleted_at IS NULL)
LIMIT 1;

INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at)
SELECT c.id, 'Student Pro', 'student-pro', 129000, 'Gói lab cho đồ án nhóm, không thay thế Cloud Server production.', 'Phù hợp demo nhóm, web app nhỏ, database nhẹ và môi trường test dài ngày. Nếu chạy website thật hoặc production, nên chọn Cloud Server Starter 1.', 'CPU: 2 vCPU chia sẻ\nRAM: 3 GB\nNVMe: 50 GB\nBandwidth: 2 TB\nIP: 1 IPv4\nLocation: VN', NULL, 'in_stock', 'active', NOW(), NOW()
FROM categories c
WHERE c.slug = 'vps-gia-re-cho-sinh-vien'
  AND c.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = 'student-pro' AND p.deleted_at IS NULL)
LIMIT 1;

UPDATE categories
SET
    name = 'VPS Giá Rẻ Cho Sinh Viên',
    description = 'Các gói Cloud VPS nhỏ gọn, chi phí thấp cho sinh viên học tập, làm đồ án và chạy thử nghiệm.',
    updated_at = NOW()
WHERE slug = 'vps-gia-re-cho-sinh-vien'
  AND deleted_at IS NULL;

UPDATE products
SET
    price = 35000,
    short_description = 'Gói rẻ nhất để học Linux, SSH và chạy web nhỏ.',
    description = 'Phù hợp sinh viên mới bắt đầu học VPS, deploy landing page, web tĩnh hoặc môi trường test rất nhẹ.',
    specs = 'CPU: 1 vCPU\nRAM: 1 GB\nNVMe: 20 GB\nBandwidth: 1 TB\nIP: 1 IPv4\nLocation: VN',
    stock_status = 'in_stock',
    status = 'active',
    updated_at = NOW()
WHERE slug = 'student-nano'
  AND deleted_at IS NULL;

UPDATE products
SET
    price = 59000,
    short_description = 'Khởi đầu nhẹ nhàng cho bài tập web, bot nhỏ và demo API.',
    description = 'Phù hợp chạy project PHP/Node nhỏ, bot học tập, API mini hoặc website cá nhân ít truy cập.',
    specs = 'CPU: 1 vCPU\nRAM: 2 GB\nNVMe: 30 GB\nBandwidth: 2 TB\nIP: 1 IPv4\nLocation: VN',
    stock_status = 'in_stock',
    status = 'active',
    updated_at = NOW()
WHERE slug = 'student-basic'
  AND deleted_at IS NULL;

UPDATE products
SET
    price = 89000,
    short_description = 'Cấu hình cân bằng cho đồ án web và môi trường dev nhỏ.',
    description = 'Phù hợp đồ án Laravel/PHP, WordPress nhẹ, API mini hoặc bot cần thêm CPU nhưng vẫn tiết kiệm.',
    specs = 'CPU: 2 vCPU\nRAM: 2 GB\nNVMe: 40 GB\nBandwidth: 2 TB\nIP: 1 IPv4\nLocation: VN',
    stock_status = 'in_stock',
    status = 'active',
    updated_at = NOW()
WHERE slug = 'student-plus'
  AND deleted_at IS NULL;

UPDATE products
SET
    price = 129000,
    short_description = 'Gói lab cho đồ án nhóm, không thay thế Cloud Server production.',
    description = 'Phù hợp demo nhóm, web app nhỏ, database nhẹ và môi trường test dài ngày. Nếu chạy website thật hoặc production, nên chọn Cloud Server Starter 1.',
    specs = 'CPU: 2 vCPU chia sẻ\nRAM: 3 GB\nNVMe: 50 GB\nBandwidth: 2 TB\nIP: 1 IPv4\nLocation: VN',
    stock_status = 'in_stock',
    status = 'active',
    updated_at = NOW()
WHERE slug = 'student-pro'
  AND deleted_at IS NULL;

COMMIT;
