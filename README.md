# ZenoxDigital

ZenoxDigital is a PHP MVC web application for selling Cloud VPS, Cloud Server, and digital services. The project includes a public storefront, user account area, wallet flow, admin dashboard, banner management, footer/settings management, security features, and AI-assisted customer/admin workflows.

ZenoxDigital là ứng dụng web PHP MVC dùng để kinh doanh Cloud VPS, Cloud Server và các dịch vụ số. Dự án có storefront cho khách hàng, khu vực tài khoản, ví điện tử, dashboard quản trị, quản lý banner, quản lý footer/settings, bảo mật và các tính năng hỗ trợ AI.

## Tiếng Việt

### Tổng quan

ZenoxDigital được xây dựng theo hướng dễ học, dễ sửa và dễ triển khai lên hosting hoặc VPS. Dự án không phụ thuộc framework lớn; luồng xử lý được chia rõ theo mô hình MVC:

- `public/index.php`: entry point của ứng dụng.
- `config/routes.php`: khai báo route.
- `app/Controllers`: xử lý request.
- `app/Models`: thao tác database bằng PDO.
- `app/Views`: giao diện PHP view.
- `app/Services`: nghiệp vụ phức tạp như AI, thanh toán, import/export và guardrail.
- `database`: schema và migration SQL.

### Công nghệ

- PHP 8.x
- MySQL hoặc MariaDB
- PDO
- Bootstrap 5
- JavaScript thuần
- Apache/XAMPP hoặc VPS Ubuntu + Apache/Nginx
- AI Bridge qua HTTP JSON

### Tính năng chính

Storefront:

- Trang chủ Cloud VPS/Cloud Server.
- Hero banner slider lấy dữ liệu từ database.
- Danh sách gói VPS, lọc danh mục và xem chi tiết sản phẩm.
- FAQ, affiliate, reseller và footer CTA.
- Đăng ký, đăng nhập, quên mật khẩu.
- Hồ sơ người dùng, avatar, ảnh bìa và bảo mật tài khoản.
- Ví điện tử, lịch sử giao dịch và checkout.
- Chatbot AI hỗ trợ khách hàng và feedback nhanh.

Admin:

- Dashboard tổng quan.
- Quản lý sản phẩm, danh mục và banner.
- Quản lý đơn hàng, user, thanh toán, coupon và rank.
- Quản lý feedback, audit log và SQL Manager.
- Quản lý thông tin footer/site settings trong dashboard.
- Admin AI Copilot hỗ trợ tóm tắt và gợi ý vận hành.

Bảo mật:

- CSRF token cho form POST.
- PDO prepared statements.
- Password hashing.
- Session hardening.
- Rate limiting.
- Phân quyền admin/user.
- 2FA, backup codes và quản lý phiên đăng nhập.
- Audit log cho thao tác quản trị.

### Cài đặt local với XAMPP

1. Clone repo:

```bash
git clone https://github.com/EmBeHocCode/ZenoxDigital.git
cd ZenoxDigital
```

2. Tạo file cấu hình:

```bash
copy .env.example .env
```

3. Sửa `.env` theo máy local:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/ZenoxDigital/public

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=digital_market
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

4. Tạo database `digital_market`, sau đó import:

```text
database/schema.sql
```

Nếu cần cập nhật các tính năng bổ sung, chạy thêm các migration:

```text
database/20260328_add_cloud_products.sql
database/20260422_add_phase67_product_columns.sql
database/20260514_create_hero_banners.sql
database/20260514_add_student_vps_products.sql
database/20260515_footer_settings.sql
```

5. Mở website:

```text
http://localhost/ZenoxDigital/public
```

### Cấu hình upload

Thư mục upload mặc định:

```text
public/uploads
```

Ảnh slide/banner landing page:

```text
public/assets/images/slides
```

Database chỉ nên lưu tên file hoặc đường dẫn tương đối, không lưu đường dẫn tuyệt đối trên Windows.

### AI Bridge

ZenoxDigital không chạy model AI trực tiếp trong PHP. Ứng dụng build context và gọi sang AI bridge thông qua HTTP.

Cấu hình mẫu:

```env
AI_ENABLED=true
AI_PROVIDER=bridge
AI_BRIDGE_URL=http://127.0.0.1:10001/api/web-chat
AI_BRIDGE_KEY=your-secret
AI_CHAT_TIMEOUT=20
AI_BRIDGE_RETRIES=1
AI_BRIDGE_ALLOW_LOCAL_FALLBACK=true
```

Nếu bridge chưa chạy, có thể bật local fallback để test giao diện, route, session và guardrail.

### Triển khai lên hosting/VPS

- Upload source lên hosting hoặc VPS.
- Trỏ document root về `public`.
- Tạo database và import `database/schema.sql`.
- Tạo `.env` riêng cho production.
- Tắt debug trên production:

```env
APP_ENV=production
APP_DEBUG=false
APP_HTTPS_ONLY=true
```

- Đảm bảo các thư mục runtime có quyền ghi:

```text
public/uploads
storage/sessions
storage/cli-sessions
storage/admin-ai/cache
storage/admin-ai/progress
```

### Lưu ý bảo mật

- Không commit file `.env`.
- Không đưa API key, database password hoặc OAuth secret lên GitHub.
- Đổi `APP_KEY` khi triển khai production.
- Sao lưu database trước khi chạy migration trên server thật.
- Chỉ cấp quyền admin cho tài khoản tin cậy.

## English

### Overview

ZenoxDigital is a lightweight PHP MVC application for Cloud VPS, Cloud Server, and digital-service commerce. It is designed to be easy to learn, easy to maintain, and practical enough for local development, shared hosting, or VPS deployment.

Main structure:

- `public/index.php`: application entry point.
- `config/routes.php`: route definitions.
- `app/Controllers`: request handling.
- `app/Models`: PDO-based database models.
- `app/Views`: PHP views and layouts.
- `app/Services`: business logic, AI services, payment services, import/export, and guardrails.
- `database`: schema and SQL migrations.

### Tech Stack

- PHP 8.x
- MySQL or MariaDB
- PDO
- Bootstrap 5
- Vanilla JavaScript
- Apache/XAMPP or Ubuntu VPS with Apache/Nginx
- External AI Bridge over HTTP JSON

### Key Features

Storefront:

- Cloud VPS / Cloud Server homepage.
- Database-driven hero banner slider.
- Product catalog, category filtering, and product detail pages.
- FAQ, affiliate section, reseller section, and footer CTA.
- Register, login, and forgot password.
- User profile, avatar, cover image, account security.
- Wallet, transaction history, and checkout.
- Customer AI chatbot and quick feedback flow.

Admin:

- Admin dashboard.
- Product, category, and banner management.
- Order, user, payment, coupon, and rank management.
- Feedback management, audit logs, and SQL Manager.
- Footer/site settings management from the dashboard.
- Admin AI Copilot for summaries and operational suggestions.

Security:

- CSRF protection for POST forms.
- PDO prepared statements.
- Password hashing.
- Session hardening.
- Rate limiting.
- Admin/user authorization.
- 2FA, backup codes, and session management.
- Audit logs for admin actions.

### Local Setup With XAMPP

1. Clone the repository:

```bash
git clone https://github.com/EmBeHocCode/ZenoxDigital.git
cd ZenoxDigital
```

2. Create environment file:

```bash
copy .env.example .env
```

3. Update `.env` for local development:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/ZenoxDigital/public

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=digital_market
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

4. Create the `digital_market` database and import:

```text
database/schema.sql
```

Optional migrations:

```text
database/20260328_add_cloud_products.sql
database/20260422_add_phase67_product_columns.sql
database/20260514_create_hero_banners.sql
database/20260514_add_student_vps_products.sql
database/20260515_footer_settings.sql
```

5. Open the app:

```text
http://localhost/ZenoxDigital/public
```

### Upload Configuration

Default upload directory:

```text
public/uploads
```

Landing page slide/banner images:

```text
public/assets/images/slides
```

The database should store only filenames or relative paths, not absolute Windows paths.

### AI Bridge

ZenoxDigital does not run AI models directly inside PHP. The app builds context and calls an external AI bridge through HTTP.

Example:

```env
AI_ENABLED=true
AI_PROVIDER=bridge
AI_BRIDGE_URL=http://127.0.0.1:10001/api/web-chat
AI_BRIDGE_KEY=your-secret
AI_CHAT_TIMEOUT=20
AI_BRIDGE_RETRIES=1
AI_BRIDGE_ALLOW_LOCAL_FALLBACK=true
```

If the bridge is not running, local fallback can be enabled to test UI, routes, sessions, and guardrails.

### Deployment Notes

- Upload the source code to hosting or a VPS.
- Point the document root to `public`.
- Create the database and import `database/schema.sql`.
- Create a production `.env`.
- Disable debug in production:

```env
APP_ENV=production
APP_DEBUG=false
APP_HTTPS_ONLY=true
```

- Ensure these directories are writable:

```text
public/uploads
storage/sessions
storage/cli-sessions
storage/admin-ai/cache
storage/admin-ai/progress
```

### Security Notes

- Do not commit `.env`.
- Do not push API keys, database passwords, or OAuth secrets to GitHub.
- Change `APP_KEY` for production.
- Back up the database before running migrations on a real server.
- Grant admin access only to trusted users.

## License

This project is provided for learning, product prototyping, and practical deployment practice. See `LICENSE` for license details.
