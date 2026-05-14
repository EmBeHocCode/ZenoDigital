# ZenoxDigital

ZenoxDigital là dự án webshop dịch vụ số theo hướng cloud hosting, xây dựng bằng PHP MVC thuần, PDO, Bootstrap 5 và JavaScript thuần. Repo này được dùng để học lập trình nghiêm túc, làm tài liệu đối chiếu khi phát triển đồ án, và làm nền tảng thực hành triển khai hệ thống thực tế.

> Đây không phải landing page demo đơn giản. Dự án có storefront, backoffice, ví điện tử, quản trị dữ liệu, bảo mật, AI chatbot/Copilot, SQL Manager và các tài liệu triển khai AI đi kèm.

## Mục Tiêu

- Học cách xây dựng sản phẩm web hoàn chỉnh bằng PHP MVC.
- Hiểu luồng dữ liệu từ route, controller, model, view đến database.
- Thực hành quản trị sản phẩm, đơn hàng, user, coupon, feedback và cấu hình hệ thống.
- Tích hợp AI vào sản phẩm theo hướng có kiểm soát, không bịa dữ liệu.
- Có nền tảng để phát triển thành đồ án hoặc portfolio kỹ thuật.

## Công Nghệ

- PHP 8.x
- MySQL hoặc MariaDB
- PDO
- Bootstrap 5
- JavaScript thuần
- Apache/XAMPP hoặc VPS Ubuntu + Apache/Nginx
- AI bridge bên ngoài qua HTTP JSON

## Tính Năng Chính

### Storefront

- Trang chủ ZenoDigital với hero banner/slider.
- Danh sách sản phẩm dịch vụ số/cloud/VPS.
- Trang chi tiết sản phẩm.
- Đăng ký, đăng nhập, hồ sơ người dùng.
- Ví điện tử và lịch sử giao dịch.
- Checkout sản phẩm.
- Feedback nhanh từ header và widget AI.

### Admin Backoffice

- Dashboard quản trị.
- Quản lý sản phẩm.
- Quản lý danh mục.
- Quản lý banner trang chủ.
- Quản lý đơn hàng.
- Quản lý người dùng.
- Quản lý coupon.
- Quản lý rank/chương trình thưởng.
- Quản lý feedback khách hàng.
- Quản lý thanh toán/ví.
- Cài đặt hệ thống.
- Audit log.
- SQL Manager.

### SQL Manager

SQL Manager được thiết kế giống database client hiện đại:

- Database Explorer dạng tree.
- Browse dữ liệu bảng.
- Structure/columns/indexes/foreign keys/triggers.
- SQL console read-only cho truy vấn an toàn.
- Import SQL/CSV/JSON.
- Export SQL tương thích phpMyAdmin.
- Inline edit cell giống Navicat/TablePlus.
- Resize columns và resize panel.
- Scroll ngang/dọc riêng trong workspace.
- Tool sửa lỗi mojibake tiếng Việt trong database.

Tool sửa dữ liệu lỗi font:

```bash
C:\xampp\php\php.exe tools\repair_db_mojibake.php
C:\xampp\php\php.exe tools\repair_db_mojibake.php --fix
```

## AI Trong Dự Án

AI trong ZenoxDigital là lớp trợ lý có guardrail, không thay backend permission và không tự suy đoán dữ liệu thiếu.

### Customer AI Widget

File chính:

- `app/Controllers/AiController.php`
- `app/Services/AiBridgeService.php`
- `app/Services/AiContextBuilder.php`
- `app/Services/AiGuardService.php`
- `app/Views/partials/ai_chat_widget.php`
- `public/assets/js/ai-chat-widget.js`
- `public/assets/css/ai-chat-widget.css`

Route:

- `POST /ai/chat/customer`
- `POST /ai/feedback/customer`

Khả năng:

- Trả lời FAQ cơ bản.
- Tư vấn sản phẩm.
- Nhận feedback.
- Hỗ trợ tra cứu đơn/tài khoản trong phạm vi an toàn.

### Admin Meow Copilot

File chính:

- `app/Controllers/Admin/AiController.php`
- `app/Services/AdminAiIntentService.php`
- `app/Services/AdminAiMutationService.php`
- `app/Services/AdminAiSessionService.php`
- `app/Views/partials/admin_ai_panel.php`
- `public/assets/js/admin-ai-panel.js`
- `public/assets/css/admin-ai-panel.css`

Route:

- `POST /admin/ai/chat`
- `GET /admin/ai/summary`
- `GET /admin/ai/session`
- `GET /admin/ai/progress`

Khả năng:

- Tóm tắt dashboard.
- Xem đơn pending, feedback, coupon, sản phẩm.
- Gợi ý bán hàng/khuyến mãi có guardrail.
- Gợi ý capacity/restock khi dữ liệu đủ.
- Mutation theo luồng `preview -> confirm -> execute`.

### AI Bridge

Repo PHP này không chạy model AI trực tiếp. ZenoxDigital build context và gọi sang AI bridge bên ngoài qua `AI_BRIDGE_URL`.

Cấu hình mẫu:

```env
AI_ENABLED=true
AI_PROVIDER=zia-bot-bridge
AI_BRIDGE_URL=http://127.0.0.1:10001/api/web-chat
AI_BRIDGE_KEY=your-secret
AI_CHAT_TIMEOUT=20
AI_BRIDGE_RETRIES=1
AI_BRIDGE_ALLOW_LOCAL_FALLBACK=true
AI_CUSTOMER_SESSION_PREFIX=customer-web
AI_ADMIN_SESSION_PREFIX=admin-dashboard
```

Nếu bridge chưa chạy, hệ thống có thể dùng `local-fallback` để test UI, route, session và guardrail.

## Kiến Trúc Thư Mục

```text
app/
  Controllers/          Controller public
  Controllers/Admin/    Controller admin
  Core/                 App, Controller, Model, Database, Auth
  Helpers/              Helper dùng chung
  Models/               Model PDO
  Services/             Business service, AI service, import/export service
  Views/                View PHP
config/
  config.php            Cấu hình app/database/security
  routes.php            Route map
database/
  schema.sql            Schema chính + seed
  *.sql                 Migration bổ sung
docs/
  README_AI.md
  AI_PORTING_GUIDE.md
  AI_FEATURE_PHASES_CHECKLIST.md
  AI_CONTEXT_MAP.md
  AI_TASK_TEMPLATE.md
  AI_IMPLEMENTATION_STATUS.md
public/
  index.php             Entry point
  assets/css            CSS frontend/admin
  assets/js             JavaScript frontend/admin
  assets/images         Ảnh tĩnh
tools/
  repair_db_mojibake.php
```

## Cài Đặt Local Bằng XAMPP

### 1. Clone repo

```bash
git clone https://github.com/EmBeHocCode/ZenoxDigital.git
cd ZenoxDigital
```

### 2. Tạo file `.env`

```bash
copy .env.example .env
```

Sửa các biến quan trọng:

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

Không commit `.env` thật lên GitHub.

### 3. Tạo database

Mở phpMyAdmin hoặc MySQL CLI, import:

```text
database/schema.sql
```

Nếu cần dữ liệu/migration mới, chạy thêm:

```text
database/20260328_add_cloud_products.sql
database/20260422_add_phase67_product_columns.sql
database/20260514_create_hero_banners.sql
database/20260514_add_student_vps_products.sql
```

### 4. Chạy app

Với XAMPP:

```text
http://localhost/ZenoxDigital/public
```

Admin:

```text
http://localhost/ZenoxDigital/public/admin
```

## Tài Khoản Và Phân Quyền

Schema có bảng:

- `roles`
- `users`
- `user_sessions`
- `user_login_activities`
- `user_backup_codes`

Phân quyền admin/staff được xử lý trong:

- `app/Core/Auth.php`
- `app/Views/partials/admin_sidebar.php`
- các controller trong `app/Controllers/Admin`

Nếu quên mật khẩu admin khi học local, có thể reset bằng SQL hoặc dùng màn admin user nếu còn tài khoản quản trị khác.

## Database

Các bảng chính:

- `users`
- `roles`
- `categories`
- `products`
- `product_images`
- `orders`
- `order_items`
- `wallet_transactions`
- `coupons`
- `user_rank_coupons`
- `customer_feedback`
- `ai_chat_sessions`
- `ai_chat_messages`
- `settings`
- `admin_audit_logs`
- `hero_banners`

Database dùng:

```sql
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci
```

## Bảo Mật

Dự án đã có các lớp bảo vệ cơ bản:

- CSRF token.
- Session hardening.
- Rate limit cho auth, AI, feedback.
- Role/scope admin.
- 2FA TOTP và backup code.
- Audit log admin.
- Guardrail AI theo quyền.
- Không lưu secret trong JavaScript.

Các file không được commit:

- `.env`
- file export database trong `database/exports/`
- file upload trong `public/uploads/`
- file zip backup local.

## Các Route Quan Trọng

Public:

- `GET /`
- `GET /products`
- `GET /products/show/{id}`
- `POST /products/checkout/{id}`
- `GET /profile`
- `POST /profile/wallet/deposit`
- `POST /ai/chat/customer`
- `POST /ai/feedback/customer`
- `POST /feedback/header/store`

Admin:

- `GET /admin`
- `GET /admin/products`
- `GET /admin/categories`
- `GET /admin/banners`
- `GET /admin/users`
- `GET /admin/orders`
- `GET /admin/payments`
- `GET /admin/settings`
- `GET /admin/sql-manager`
- `GET /admin/coupons`
- `GET /admin/ranks`
- `GET /admin/audit-logs`
- `POST /admin/ai/chat`

## Quy Trình Làm Việc Gợi Ý Cho Người Học

1. Đọc `config/routes.php` để hiểu URL đi vào controller nào.
2. Đọc controller tương ứng trong `app/Controllers`.
3. Đọc model trong `app/Models` để hiểu query database.
4. Đọc view trong `app/Views` để hiểu dữ liệu được render ra UI.
5. Đọc JS/CSS trong `public/assets` để hiểu tương tác frontend.
6. Với AI, đọc `docs/README_AI.md` và `docs/AI_PORTING_GUIDE.md` trước khi sửa.

## Kiểm Tra Nhanh Trước Khi Push

Lint PHP từng file:

```bash
C:\xampp\php\php.exe -l app/Controllers/Admin/SqlManagerController.php
C:\xampp\php\php.exe -l app/Models/SqlManager.php
C:\xampp\php\php.exe -l app/Views/admin/sql_manager/index.php
```

Check JavaScript:

```bash
node --check public/assets/js/admin-sql-manager.js
```

Kiểm tra diff:

```bash
git diff --check
git status --short
```

## Deploy Lên VPS

Luồng deploy cơ bản:

1. Clone repo lên VPS.
2. Tạo `.env` production.
3. Import database.
4. Trỏ web server vào thư mục `public`.
5. Cấp quyền ghi cho thư mục runtime/upload nếu cần.
6. Bật HTTPS.
7. Cấu hình AI bridge nếu dùng AI thật.
8. Kiểm tra `/admin`, `/products`, `/ai/chat/customer`.

Ví dụ Apache/Nginx cần đặt document root:

```text
/path/to/ZenoxDigital/public
```

## Roadmap Đề Xuất

- Hoàn thiện dữ liệu thật cho cost/capacity/stock.
- Thêm test tự động cho service quan trọng.
- Tách migration rõ hơn theo version.
- Hoàn thiện báo cáo điều hành AI phase 8.
- Tối ưu phân quyền admin theo từng module.
- Chuẩn hóa quy trình deploy 24/7 bằng Docker Compose.

## Tài Liệu Liên Quan

- `AGENTS.md`
- `SECURITY_NOTES.md`
- `docs/README_AI.md`
- `docs/AI_PORTING_GUIDE.md`
- `docs/AI_CONTEXT_MAP.md`
- `docs/AI_TASK_TEMPLATE.md`
- `docs/AI_IMPLEMENTATION_STATUS.md`
- `docs/AI_FEATURE_PHASES_CHECKLIST.md`

## Ghi Chú Học Tập

Khi dùng repo này để làm đồ án, em nên trình bày theo 5 phần:

1. Bài toán và mục tiêu hệ thống.
2. Kiến trúc MVC và database.
3. Các phân hệ người dùng/admin.
4. Bảo mật, phân quyền, audit.
5. AI chatbot/Copilot và guardrail dữ liệu.

Nếu giáo viên hỏi “AI có tự quyết định dữ liệu không?”, câu trả lời đúng là: không. AI chỉ hỗ trợ tư vấn/gợi ý; backend vẫn kiểm soát quyền, context, dữ liệu và mutation.

## License

MIT. Xem file `LICENSE`.
