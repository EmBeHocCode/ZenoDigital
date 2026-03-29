-- Digital Market Pro - MySQL schema + seed
CREATE DATABASE IF NOT EXISTS digital_market CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE digital_market;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS ai_chat_messages;
DROP TABLE IF EXISTS ai_chat_sessions;
DROP TABLE IF EXISTS customer_feedback;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS user_sessions;
DROP TABLE IF EXISTS user_login_activities;
DROP TABLE IF EXISTS user_backup_codes;
DROP TABLE IF EXISTS wallet_transactions;
DROP TABLE IF EXISTS user_rank_coupons;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS settings;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(80) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    gender ENUM('male','female','other','unknown') NOT NULL DEFAULT 'unknown',
    birth_date DATE NULL,
    address VARCHAR(255) NULL,
    wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
    password VARCHAR(255) NOT NULL,
    two_factor_secret VARCHAR(64) NULL,
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    two_factor_confirmed_at DATETIME NULL,
    two_factor_last_used_at DATETIME NULL,
    avatar VARCHAR(255) NULL,
    banner_media VARCHAR(255) NULL,
    banner_media_type ENUM('image','video') NULL,
    banner_media_meta TEXT NULL,
    status ENUM('active','blocked') DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    price DECIMAL(15,2) NOT NULL DEFAULT 0,
    short_description VARCHAR(255) NULL,
    description TEXT NULL,
    specs TEXT NULL,
    image VARCHAR(255) NULL,
    stock_status ENUM('in_stock','out_of_stock') DEFAULT 'in_stock',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_code VARCHAR(60) NOT NULL UNIQUE,
    total_amount DECIMAL(15,2) NOT NULL,
    status ENUM('pending','paid','processing','completed','cancelled') DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE ai_chat_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_key VARCHAR(120) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    channel VARCHAR(30) NOT NULL,
    conversation_mode VARCHAR(40) NOT NULL,
    actor_role VARCHAR(60) NOT NULL,
    status ENUM('active','reset','expired','closed') NOT NULL DEFAULT 'active',
    pending_request_id VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    last_activity_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_ai_chat_sessions_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_ai_chat_sessions_user_active (user_id, channel, conversation_mode, status, last_activity_at),
    INDEX idx_ai_chat_sessions_expires (expires_at)
);

CREATE TABLE ai_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_session_id INT NOT NULL,
    role ENUM('user','assistant','system') NOT NULL,
    content TEXT NOT NULL,
    meta_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_ai_chat_messages_session FOREIGN KEY (chat_session_id) REFERENCES ai_chat_sessions(id) ON DELETE CASCADE,
    INDEX idx_ai_chat_messages_session (chat_session_id, id)
);

CREATE TABLE customer_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feedback_code VARCHAR(60) NOT NULL UNIQUE,
    user_id INT NULL,
    order_id INT NULL,
    product_id INT NULL,
    ai_session_id VARCHAR(120) NOT NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'ai_widget',
    page_type VARCHAR(40) NOT NULL DEFAULT 'storefront',
    feedback_type ENUM('general','product','delivery','payment','support','system_bug') NOT NULL DEFAULT 'general',
    sentiment ENUM('positive','neutral','negative') NOT NULL DEFAULT 'neutral',
    severity ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    rating TINYINT UNSIGNED NULL,
    status ENUM('new','reviewing','resolved','closed') NOT NULL DEFAULT 'new',
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
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id),
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE user_backup_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    code_hint VARCHAR(20) NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_user_backup_codes_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE user_login_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    device VARCHAR(180) NOT NULL,
    location VARCHAR(120) NOT NULL,
    logged_in_at DATETIME NOT NULL,
    CONSTRAINT fk_user_login_activities_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    device VARCHAR(180) NOT NULL,
    created_at DATETIME NOT NULL,
    last_activity_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_code VARCHAR(60) NOT NULL UNIQUE,
    transaction_type ENUM('deposit','spend','refund','adjustment') NOT NULL DEFAULT 'deposit',
    payment_method VARCHAR(40) NOT NULL DEFAULT 'manual',
    direction ENUM('credit','debit') NOT NULL DEFAULT 'credit',
    amount DECIMAL(15,2) NOT NULL,
    balance_before DECIMAL(15,2) NOT NULL DEFAULT 0,
    balance_after DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('pending','completed','failed') NOT NULL DEFAULT 'completed',
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_wallet_transactions_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE user_rank_coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rank_key VARCHAR(30) NOT NULL,
    coupon_code VARCHAR(64) NOT NULL UNIQUE,
    discount_percent INT NOT NULL,
    issued_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_user_rank_coupons_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT uq_user_rank_once UNIQUE (user_id, rank_key)
);

INSERT INTO roles (id, name) VALUES (1, 'admin'), (2, 'user'), (3, 'staff');

INSERT INTO users (role_id, full_name, username, email, phone, address, password, avatar, status, created_at, updated_at)
VALUES
(1, 'Administrator', 'admin', 'admin@local.test', '0900000001', 'HCM City', '$2y$10$sp4LxH/XH3o2VV7tvxUEreBnT8T8S5Q/rbW4eUEDzcJxm4vuWQ3Qe', NULL, 'active', NOW(), NOW()),
(2, 'Nguyen Van A', 'nguyenvana', 'user@local.test', '0900000002', 'Ha Noi', '$2y$10$sp4LxH/XH3o2VV7tvxUEreBnT8T8S5Q/rbW4eUEDzcJxm4vuWQ3Qe', NULL, 'active', NOW(), NOW());

INSERT INTO categories (name, slug, description, created_at, updated_at)
VALUES
('VPS / Cloud Server', 'vps-cloud-server', 'Dịch vụ máy chủ ảo hiệu năng cao', NOW(), NOW()),
('Server Game', 'server-game', 'Dịch vụ máy chủ game ổn định', NOW(), NOW()),
('SIM số / SIM data', 'sim-so-sim-data', 'SIM số đẹp và data tốc độ cao', NOW(), NOW()),
('Wallet Steam', 'wallet-steam', 'Nạp ví Steam nhanh chóng', NOW(), NOW()),
('Thẻ game / Thẻ nạp', 'the-game-the-nap', 'Mã thẻ đa nền tảng', NOW(), NOW());

INSERT INTO products (category_id, name, slug, price, short_description, description, specs, image, stock_status, status, created_at, updated_at)
VALUES
(1, 'VPS Basic 2vCPU', 'vps-basic-2vcpu', 199000, 'Gói VPS cho website nhỏ', 'Phù hợp cho web doanh nghiệp, blog, app nội bộ.', 'CPU: 2vCore\nRAM: 4GB\nSSD: 80GB\nBandwidth: 2TB', NULL, 'in_stock', 'active', NOW(), NOW()),
(1, 'Cloud Server Pro', 'cloud-server-pro', 799000, 'Cloud server hiệu năng cao', 'Phù hợp chạy SaaS, API và hệ thống production.', 'CPU: 6vCore\nRAM: 16GB\nSSD NVMe: 300GB', NULL, 'in_stock', 'active', NOW(), NOW()),
(1, 'Cloud Server Starter 1', 'cloud-server-starter-1', 179000, 'Gói cloud tiết kiệm cho website mới chạy', 'Phù hợp landing page, website giới thiệu và môi trường test cần uptime ổn định.', 'CPU: 2vCore\nRAM: 4GB\nSSD: 60GB\nBandwidth: 2TB\nIP: 1 IPv4\nLocation: VN', NULL, 'in_stock', 'active', NOW(), NOW()),
(1, 'VPS Ryzen Basic', 'vps-ryzen-basic', 329000, 'Ryzen entry cho web bán hàng và automation nhẹ', 'Phù hợp WordPress, Laravel nhỏ và tác vụ bot nội bộ cần tốc độ đơn nhân tốt.', 'CPU: 4vCore Ryzen\nRAM: 8GB\nSSD NVMe: 120GB\nBandwidth: 3TB\nIP: 1 IPv4\nLocation: VN', NULL, 'in_stock', 'active', NOW(), NOW()),
(1, 'Cloud Server Business 4', 'cloud-server-business-4', 449000, 'Cấu hình cân bằng cho web bán hàng, CRM mini và API', 'Phù hợp website traffic vừa, backend dịch vụ và môi trường production gọn.', 'CPU: 4vCore\nRAM: 8GB\nSSD NVMe: 160GB\nBandwidth: 4TB\nIP: 1 IPv4\nLocation: SG', NULL, 'in_stock', 'active', NOW(), NOW()),
(1, 'Cloud Server NVMe 8GB', 'cloud-server-nvme-8gb', 499000, 'Ưu tiên NVMe cho database nhỏ và workload đọc ghi nhanh', 'Phù hợp website nội dung lớn, database nhỏ và ứng dụng cần disk tốc độ cao.', 'CPU: 4vCore\nRAM: 8GB\nSSD NVMe: 200GB\nBandwidth: 4TB\nIP: 1 IPv4\nLocation: VN / SG', NULL, 'in_stock', 'active', NOW(), NOW()),
(1, 'Cloud Server High CPU', 'cloud-server-high-cpu', 629000, 'Tối ưu CPU cho queue, automation và backend xử lý nhiều tác vụ', 'Phù hợp crawler, API nhiều request và workload cần tài nguyên xử lý ổn định.', 'CPU: 6vCore High Clock\nRAM: 8GB\nSSD NVMe: 180GB\nBandwidth: 4TB\nIP: 1 IPv4\nLocation: US', NULL, 'in_stock', 'active', NOW(), NOW()),
(1, 'Cloud Server High RAM', 'cloud-server-high-ram', 729000, 'RAM lớn cho cache, app nhiều tiến trình và database vừa', 'Phù hợp Redis, app PHP/Node nhiều worker và dịch vụ cần giữ nhiều dữ liệu trong memory.', 'CPU: 4vCore\nRAM: 16GB\nSSD NVMe: 220GB\nBandwidth: 5TB\nIP: 1 IPv4\nLocation: VN', NULL, 'in_stock', 'active', NOW(), NOW()),
(1, 'VPS Ryzen Pro', 'vps-ryzen-pro', 699000, 'Ryzen mạnh hơn cho production, game backend và automation', 'Phù hợp website doanh thu thật, app nội bộ nhiều user và workload cần độ phản hồi tốt.', 'CPU: 6vCore Ryzen\nRAM: 16GB\nSSD NVMe: 240GB\nBandwidth: 5TB\nIP: 1 IPv4\nLocation: SG', NULL, 'in_stock', 'active', NOW(), NOW()),
(2, 'Game Server Minecraft', 'game-server-minecraft', 259000, 'Server tối ưu cho Minecraft', 'Hỗ trợ cài plugin, backup tự động.', 'RAM: 8GB\nStorage: 120GB SSD\nDDoS Protection', NULL, 'in_stock', 'active', NOW(), NOW()),
(4, 'Steam Wallet 500K', 'steam-wallet-500k', 500000, 'Nạp Wallet Steam mệnh giá 500K', 'Kích hoạt nhanh trong vài phút.', 'Delivery: Instant\nRegion: VN', NULL, 'in_stock', 'active', NOW(), NOW()),
(5, 'Thẻ game đa nền tảng 200K', 'the-game-da-nen-tang-200k', 200000, 'Mã thẻ game tiện lợi', 'Hỗ trợ nhiều nhà phát hành game.', 'Card Type: Digital Code', NULL, 'in_stock', 'active', NOW(), NOW());

INSERT INTO orders (user_id, order_code, total_amount, status, created_at, updated_at)
VALUES
(2, 'ORD-20260323-001', 699000, 'paid', NOW(), NOW()),
(2, 'ORD-20260323-002', 200000, 'processing', NOW(), NOW()),
(2, 'ORD-20260323-003', 259000, 'completed', NOW(), NOW());

INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, created_at)
VALUES
(1, 1, 1, 199000, 199000, NOW()),
(1, 4, 1, 500000, 500000, NOW()),
(2, 5, 1, 200000, 200000, NOW()),
(3, 3, 1, 259000, 259000, NOW());

INSERT INTO settings (setting_key, setting_value, updated_at)
VALUES
('site_name', 'Digital Market Pro', NOW()),
('contact_email', 'support@digitalmarket.local', NOW()),
('contact_phone', '0900 000 999', NOW()),
('address', 'TP.HCM, Việt Nam', NOW()),
('footer_text', 'Nền tảng dịch vụ số hiện đại và an toàn.', NOW()),
('facebook_url', 'https://facebook.com', NOW()),
('zalo_url', 'https://zalo.me', NOW());

-- Demo credentials
-- admin@local.test / 123456
-- user@local.test / 123456
