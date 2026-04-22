# ZenoxDigital

[Tiếng Việt](#tiếng-việt) | [English](#english)

---

## Tiếng Việt

### 1) Giới thiệu nhanh

ZenoxDigital là webshop dịch vụ số (cloud-first) xây bằng **PHP MVC + PDO + Bootstrap 5 + JavaScript thuần**.  
Dự án có 2 mặt:

- **Storefront** cho khách hàng
- **Backoffice/Admin** cho vận hành

Mục tiêu dự án:

- học thuật (báo cáo, đồ án, thuyết trình)
- portfolio xin việc (full-stack thực chiến)
- mã nguồn tham chiếu để mở rộng tiếp

### 2) Tính năng chính

- Quản lý sản phẩm, danh mục, đơn hàng, người dùng, coupon
- Ví điện tử, giao dịch ví, flow nạp qua SePay
- Đăng nhập Google OAuth, bảo mật CSRF/rate-limit/2FA/audit
- SQL Manager + schema health guard
- AI `Meow` cho khách và admin

### 3) AI hoạt động như thế nào

#### 3.1 Luồng khách hàng (public widget)

- UI: widget nổi ở layout chính
- API: `POST /ai/chat/customer`
- Có gửi feedback: `POST /ai/feedback/customer`
- AI dùng context sản phẩm + tài khoản (nếu đã đăng nhập)
- Hỗ trợ FAQ, tư vấn sản phẩm, tra cứu đơn/tài khoản cơ bản

#### 3.2 Luồng admin (Meow Copilot)

- UI: panel AI trong dashboard admin
- API:
  - `POST /admin/ai/chat`
  - `GET /admin/ai/summary`
  - `GET /admin/ai/session`
  - `GET /admin/ai/progress`
- Có cơ chế:
  - session persistence
  - intent nhanh (direct read)
  - mutation guardrail: `preview -> confirm -> execute`

#### 3.3 Guardrail dữ liệu (rất quan trọng)

AI **không được bịa số liệu**. Nếu thiếu dữ liệu thật thì trả cảnh báo rõ.

- Nhóm capacity/tồn kho: cần `product_type`, `stock_qty`, `capacity_limit`, `capacity_used`
- Nhóm lợi nhuận: cần `cost_price`, `min_margin_percent`, `platform_fee_percent`, `payment_fee_percent`, `ads_cost_per_order`, `delivery_cost`

### 4) Trạng thái phase AI hiện tại

- Phase 0-5: đã có
- Phase 6-7: đã mở schema + wiring, kết quả phân tích phụ thuộc dữ liệu vận hành nhập thực tế
- Phase 8: roadmap tiếp theo

Tài liệu chi tiết:

- `docs/AI_PORTING_GUIDE.md`
- `docs/AI_IMPLEMENTATION_STATUS.md`
- `docs/AI_FEATURE_PHASES_CHECKLIST.md`

### 5) Cài đặt nhanh

Yêu cầu:

- PHP 8.x
- MySQL/MariaDB
- Apache (XAMPP/Laragon)

Các bước:

1. Clone

```bash
git clone https://github.com/EmBeHocCode/ZenoDigital.git
cd ZenoDigital
```

2. Tạo `.env` từ `.env.example`, cấu hình DB + APP URL

3. Import DB nền

- `database/schema.sql`

4. Nếu DB cũ chưa có cột Phase 6/7, chạy thêm migration:

- `database/20260422_add_phase67_product_columns.sql`

5. Chạy web qua thư mục `public`

```text
http://localhost/ZenoxDigital/public
```

### 6) Cách dùng AI

#### Cho khách

1. Mở trang chủ hoặc trang sản phẩm
2. Bấm widget Meow
3. Hỏi FAQ / tư vấn sản phẩm / tra cứu đơn cơ bản
4. Gửi feedback trực tiếp từ widget

#### Cho admin

1. Đăng nhập admin, vào `/admin`
2. Mở Meow Copilot
3. Hỏi nhanh: đơn pending, top sản phẩm, coupon, feedback, capacity
4. Với câu lệnh thay đổi dữ liệu, dùng flow xác nhận của hệ thống

### 7) Mục đích sử dụng dự án

- **Học thuật/đồ án:** đủ chiều sâu kiến trúc, bảo mật, AI guardrail, tích hợp payment
- **Xin việc:** thể hiện năng lực backend + frontend + tích hợp AI có kiểm soát
- **Nghiên cứu mở rộng:** có nền module/service rõ ràng để phát triển tiếp

### 8) License

MIT - xem `LICENSE`.

---

## English

### 1) Quick overview

ZenoxDigital is a cloud-first digital services webshop built with **PHP MVC + PDO + Bootstrap 5 + vanilla JS**.  
It has two main surfaces:

- **Storefront** for customers
- **Backoffice/Admin** for operations

Project goals:

- academic/capstone usage
- job-seeking portfolio
- extensible reference codebase

### 2) Core features

- Product/category/order/user/coupon management
- Wallet and SePay top-up flow
- Google OAuth, CSRF/rate-limit/2FA/audit
- SQL Manager + schema health guard
- AI layer (`Meow`) for both customer and admin flows

### 3) How AI works

#### 3.1 Customer AI widget

- UI: floating chat widget on public pages
- API: `POST /ai/chat/customer`
- Feedback API: `POST /ai/feedback/customer`
- Uses product/account context (when authenticated)
- Supports FAQ, product guidance, and basic order/account support

#### 3.2 Admin AI Copilot

- UI: admin dashboard AI drawer
- APIs:
  - `POST /admin/ai/chat`
  - `GET /admin/ai/summary`
  - `GET /admin/ai/session`
  - `GET /admin/ai/progress`
- Includes:
  - session persistence
  - direct-read intents
  - guarded mutations via `preview -> confirm -> execute`

#### 3.3 Data guardrails

AI must not fabricate numbers. It explicitly reports missing/insufficient data.

- Capacity-related fields: `product_type`, `stock_qty`, `capacity_limit`, `capacity_used`
- Profit-related fields: `cost_price`, `min_margin_percent`, `platform_fee_percent`, `payment_fee_percent`, `ads_cost_per_order`, `delivery_cost`

### 4) AI phase status

- Phases 0-5: implemented
- Phases 6-7: schema + wiring implemented; analysis quality depends on real operational data
- Phase 8: upcoming roadmap

Detailed docs:

- `docs/AI_PORTING_GUIDE.md`
- `docs/AI_IMPLEMENTATION_STATUS.md`
- `docs/AI_FEATURE_PHASES_CHECKLIST.md`

### 5) Quick start

Requirements:

- PHP 8.x
- MySQL/MariaDB
- Apache (XAMPP/Laragon)

Steps:

1. Clone

```bash
git clone https://github.com/EmBeHocCode/ZenoDigital.git
cd ZenoDigital
```

2. Create `.env` from `.env.example` and configure DB + app URL

3. Import base schema:

- `database/schema.sql`

4. For existing databases, run Phase 6/7 migration:

- `database/20260422_add_phase67_product_columns.sql`

5. Serve from `public` and open:

```text
http://localhost/ZenoxDigital/public
```

### 6) AI usage

Customer:

1. Open homepage/product page
2. Launch Meow widget
3. Ask FAQ/product/order/account questions
4. Submit feedback directly from the widget

Admin:

1. Login and open `/admin`
2. Open Meow Copilot
3. Ask for pending orders, top products, coupons, feedback, capacity signals
4. Use confirmation flow for data-changing actions

### 7) Project use cases

- **Academic/capstone:** architecture, security, AI guardrails, payment integration
- **Job portfolio:** practical full-stack + controlled AI integration
- **Further development:** modular service structure ready for extension

### 8) License

MIT - see `LICENSE`.

