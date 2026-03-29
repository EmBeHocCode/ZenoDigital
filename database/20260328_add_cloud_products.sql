START TRANSACTION;

INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at)
SELECT c.id, 'Cloud Server Starter 1', 'cloud-server-starter-1', 179000, 'Gói cloud tiết kiệm cho website mới chạy', 'Phù hợp landing page, website giới thiệu và môi trường test cần uptime ổn định.', 'CPU: 2vCore\nRAM: 4GB\nSSD: 60GB\nBandwidth: 2TB\nIP: 1 IPv4\nLocation: VN', NULL, 'in_stock', 'active', NOW(), NOW()
FROM categories c
WHERE c.slug = 'vps-cloud-server'
  AND c.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = 'cloud-server-starter-1')
LIMIT 1;

INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at)
SELECT c.id, 'VPS Ryzen Basic', 'vps-ryzen-basic', 329000, 'Ryzen entry cho web bán hàng và automation nhẹ', 'Phù hợp WordPress, Laravel nhỏ và tác vụ bot nội bộ cần tốc độ đơn nhân tốt.', 'CPU: 4vCore Ryzen\nRAM: 8GB\nSSD NVMe: 120GB\nBandwidth: 3TB\nIP: 1 IPv4\nLocation: VN', NULL, 'in_stock', 'active', NOW(), NOW()
FROM categories c
WHERE c.slug = 'vps-cloud-server'
  AND c.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = 'vps-ryzen-basic')
LIMIT 1;

INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at)
SELECT c.id, 'Cloud Server Business 4', 'cloud-server-business-4', 449000, 'Cấu hình cân bằng cho web bán hàng, CRM mini và API', 'Phù hợp website traffic vừa, backend dịch vụ và môi trường production gọn.', 'CPU: 4vCore\nRAM: 8GB\nSSD NVMe: 160GB\nBandwidth: 4TB\nIP: 1 IPv4\nLocation: SG', NULL, 'in_stock', 'active', NOW(), NOW()
FROM categories c
WHERE c.slug = 'vps-cloud-server'
  AND c.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = 'cloud-server-business-4')
LIMIT 1;

INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at)
SELECT c.id, 'Cloud Server NVMe 8GB', 'cloud-server-nvme-8gb', 499000, 'Ưu tiên NVMe cho database nhỏ và workload đọc ghi nhanh', 'Phù hợp website nội dung lớn, database nhỏ và ứng dụng cần disk tốc độ cao.', 'CPU: 4vCore\nRAM: 8GB\nSSD NVMe: 200GB\nBandwidth: 4TB\nIP: 1 IPv4\nLocation: VN / SG', NULL, 'in_stock', 'active', NOW(), NOW()
FROM categories c
WHERE c.slug = 'vps-cloud-server'
  AND c.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = 'cloud-server-nvme-8gb')
LIMIT 1;

INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at)
SELECT c.id, 'Cloud Server High CPU', 'cloud-server-high-cpu', 629000, 'Tối ưu CPU cho queue, automation và backend xử lý nhiều tác vụ', 'Phù hợp crawler, API nhiều request và workload cần tài nguyên xử lý ổn định.', 'CPU: 6vCore High Clock\nRAM: 8GB\nSSD NVMe: 180GB\nBandwidth: 4TB\nIP: 1 IPv4\nLocation: US', NULL, 'in_stock', 'active', NOW(), NOW()
FROM categories c
WHERE c.slug = 'vps-cloud-server'
  AND c.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = 'cloud-server-high-cpu')
LIMIT 1;

INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at)
SELECT c.id, 'Cloud Server High RAM', 'cloud-server-high-ram', 729000, 'RAM lớn cho cache, app nhiều tiến trình và database vừa', 'Phù hợp Redis, app PHP/Node nhiều worker và dịch vụ cần giữ nhiều dữ liệu trong memory.', 'CPU: 4vCore\nRAM: 16GB\nSSD NVMe: 220GB\nBandwidth: 5TB\nIP: 1 IPv4\nLocation: VN', NULL, 'in_stock', 'active', NOW(), NOW()
FROM categories c
WHERE c.slug = 'vps-cloud-server'
  AND c.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = 'cloud-server-high-ram')
LIMIT 1;

INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at)
SELECT c.id, 'VPS Ryzen Pro', 'vps-ryzen-pro', 699000, 'Ryzen mạnh hơn cho production, game backend và automation', 'Phù hợp website doanh thu thật, app nội bộ nhiều user và workload cần độ phản hồi tốt.', 'CPU: 6vCore Ryzen\nRAM: 16GB\nSSD NVMe: 240GB\nBandwidth: 5TB\nIP: 1 IPv4\nLocation: SG', NULL, 'in_stock', 'active', NOW(), NOW()
FROM categories c
WHERE c.slug = 'vps-cloud-server'
  AND c.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM products p WHERE p.slug = 'vps-ryzen-pro')
LIMIT 1;

COMMIT;
