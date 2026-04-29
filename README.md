# ZenoDigital

[Tiếng Việt](#tiếng-việt) | [English](#english)

---

## Tiếng Việt

## 1. Tổng quan dự án

**ZenoDigital** là một webshop dịch vụ số theo định hướng cloud-first, xây dựng trên:

- PHP MVC (custom)
- PDO (MySQL/MariaDB)
- Bootstrap 5
- JavaScript thuần (`public/assets/js`)

Mục tiêu của dự án:

1. **Học thuật / đồ án**: có kiến trúc, module nghiệp vụ, bảo mật, tích hợp AI có guardrail.
2. **Portfolio xin việc**: thể hiện năng lực backend + frontend + tích hợp dịch vụ ngoài + AI workflow.
3. **Nền tảng mở rộng**: có tổ chức service/module rõ ràng để phát triển tiếp.

---

## 2. Các phân hệ chính

### 2.1 Storefront (khách hàng)

- Danh mục và sản phẩm dịch vụ số
- Tìm kiếm, lọc, chi tiết sản phẩm
- Đăng ký/đăng nhập, hồ sơ, lịch sử đơn
- Ví điện tử và giao dịch
- Widget AI Meow cho hỗ trợ khách

### 2.2 Backoffice/Admin

- Dashboard KPI vận hành
- Quản lý: products, categories, orders, users, coupons, feedback, settings
- Theo dõi thanh toán ví (SePay flow)
- Audit log, SQL Manager, health guard
- Meow Copilot cho admin/staff

### 2.3 Bảo mật và governance

- CSRF token
- Rate limiting (auth, AI endpoints, feedback)
- Session tracking
- 2FA (TOTP + backup codes)
- Role/scope-based access control
- Admin audit log

---

## 3. AI trong ZenoxDigital: làm được gì và hoạt động ra sao

## 3.1 Mục tiêu AI

AI trong repo này là trợ lý vận hành có kiểm soát, **không phải AI tự quyết toàn bộ hệ thống**.

Nguyên tắc cốt lõi:

- Không bịa số liệu
- Không bỏ qua phân quyền backend
- Không thực hiện mutation nguy hiểm nếu chưa qua xác nhận
- Khi thiếu dữ liệu thì trả lời rõ đang thiếu gì

## 3.2 Luồng AI cho khách (Customer Widget)

**UI**:

- Widget nổi ở layout chính
- File giao diện: `app/Views/partials/ai_chat_widget.php`
- JS/CSS: `public/assets/js/ai-chat-widget.js`, `public/assets/css/ai-chat-widget.css`

**API**:

- `POST /ai/chat/customer`
- `POST /ai/feedback/customer`

**Khả năng chính**:

- FAQ
- Tư vấn chọn sản phẩm cơ bản
- Gợi ý hướng mua hàng
- Hỗ trợ feedback sau mua
- Hỗ trợ tra cứu đơn/tài khoản trong phạm vi an toàn

## 3.3 Luồng AI cho admin (Meow Copilot)

**UI**:

- Panel trong admin layout
- File giao diện: `app/Views/partials/admin_ai_panel.php`
- JS/CSS: `public/assets/js/admin-ai-panel.js`, `public/assets/css/admin-ai-panel.css`

**API**:

- `POST /admin/ai/chat`
- `GET /admin/ai/summary`
- `GET /admin/ai/session`
- `GET /admin/ai/progress`

**Khả năng chính**:

- Tóm tắt dashboard
- Trả lời nhanh các intent vận hành (đơn pending, feedback, coupon, top sản phẩm)
- Gợi ý bán hàng/khuyến mãi mức sơ bộ
- Gợi ý capacity/restock khi dữ liệu đủ
- Mutation guardrail theo vòng đời `preview -> confirm -> execute`

## 3.4 Các service AI cốt lõi

- `AiBridgeService`: gọi bridge AI, retry/fallback
- `AiContextBuilder`: build context theo actor/scope
- `AiGuardService`: guardrail dữ liệu + capability/scope
- `AdminAiIntentService`: intent routing cho admin
- `AiSalesRecommendationService`: recommendation (push/upsell/promotion/capacity)
- `AdminAiSessionService`, `AdminAiProgressService`: session/progress tracking

---

## 4. Chi tiết phase AI (roadmap + trạng thái)

| Phase | Nội dung | Trạng thái hiện tại | Ghi chú triển khai |
|---|---|---|---|
| 0 | Hạ tầng AI (bridge/context/guard/routes/widget skeleton) | DONE | Đã có service nền và wiring public/admin |
| 1 | Chatbot CSKH cho storefront | DONE | Widget public hoạt động, có session + prompt profile |
| 2 | Feedback và hỗ trợ sau bán | DONE | Có capture feedback thật + admin xem feedback |
| 3 | Tra cứu đơn hàng/tài khoản | DONE | Có ownership guard ở backend |
| 4 | Admin AI Copilot dashboard | DONE | Có session persistence + progress + direct intent |
| 5 | Gợi ý bán hàng/khuyến mãi | DONE (mức sơ bộ) | Có push/upsell/promotion/coupon action, vẫn tôn trọng data gaps |
| 6 | Phân tích bán chậm/tồn/capacity | PARTIAL | Đã có schema + intent + recommendation; chất lượng phụ thuộc dữ liệu nhập thật |
| 7 | Guardrail lợi nhuận, không lỗ | PARTIAL | Đã có guardrail và field checks; cần dữ liệu vận hành đầy đủ để tính chuẩn |
| 8 | Báo cáo điều hành + action plan | ROADMAP | Chưa hoàn tất full pipeline |

## 4.1 Điều kiện dữ liệu cho Phase 6/7

Các cột trọng yếu trong `products`:

- `product_type`
- `stock_qty`
- `capacity_limit`
- `capacity_used`
- `reorder_point`
- `supplier_name`
- `lead_time_days`
- `cost_price`
- `min_margin_percent`
- `platform_fee_percent`
- `payment_fee_percent`
- `ads_cost_per_order`
- `delivery_cost`

Nếu thiếu cột hoặc cột chưa có dữ liệu vận hành, AI sẽ trả warning/refusal tương ứng.

---

## 5. Cách sử dụng AI chi tiết

## 5.1 Dùng AI cho khách

1. Mở trang chủ hoặc trang sản phẩm.
2. Bấm mở widget Meow.
3. Chọn prompt nhanh hoặc nhập câu hỏi.
4. Có thể gửi feedback ngay trong widget.
5. Nếu cần reset phiên, dùng nút reset trong widget.

Ví dụ câu hỏi:

- "Có gói VPS nào phù hợp web nhỏ?"
- "Đơn ORD-xxxx của tôi đang ở đâu?"
- "Mình muốn góp ý về tốc độ bàn giao"

## 5.2 Dùng AI cho admin

1. Đăng nhập backoffice và mở `/admin`.
2. Mở Meow Copilot từ dashboard.
3. Đặt câu hỏi theo intent vận hành.
4. Với thao tác thay đổi dữ liệu, kiểm tra preview trước khi confirm.

Ví dụ câu hỏi:

- "Xem nhanh đơn pending hôm nay"
- "Top sản phẩm nào đang bán chạy?"
- "Coupon hiện tại thế nào?"
- "Có cảnh báo nhập hàng/capacity nào không?"

---

## 6. Cấu hình và chạy local

## 6.1 Yêu cầu

- PHP 8.x
- MySQL/MariaDB
- Apache (XAMPP/Laragon)

## 6.2 Cài đặt

```bash
git clone https://github.com/EmBeHocCode/ZenoDigital.git
cd ZenoDigital
```

1. Tạo `.env` từ `.env.example`.
2. Cấu hình `APP_URL`, `DB_*`.
3. Import `database/schema.sql`.
4. Với DB cũ, chạy thêm:
   - `database/20260422_add_phase67_product_columns.sql`
5. Truy cập:
   - `http://localhost/ZenoxDigital/public`

## 6.3 Cấu hình AI bridge

Trong `.env` (ví dụ):

```env
AI_ENABLED=true
AI_PROVIDER=bridge
AI_BRIDGE_URL=http://your-ai-bridge/api/web-chat
AI_BRIDGE_KEY=your-secret
AI_CHAT_TIMEOUT=20
AI_BRIDGE_RETRIES=1
AI_BRIDGE_ALLOW_LOCAL_FALLBACK=true
```

---

## 7. Lộ trình phát triển (giai đoạn tiếp theo)

## Giai đoạn A - Data quality hardening

- Hoàn thiện dữ liệu thật cho các cột phase 6/7
- Thêm cảnh báo nhập liệu thiếu ở admin products
- Chuẩn hóa dữ liệu lead time/supplier/cost

## Giai đoạn B - Phase 6/7 production-ready

- Tăng độ chính xác capacity alert
- Chuẩn hóa rule lợi nhuận theo từng loại sản phẩm
- Bổ sung kiểm thử service-level cho recommendation/guardrail

## Giai đoạn C - Phase 8

- Executive report ngày/tuần
- Action queue tự động theo ưu tiên
- Checklist hành động cho vận hành

---

## 8. Phù hợp cho học thuật, xin việc, đồ án

### 8.1 Học thuật/đồ án

- Có hệ thống đa actor (guest/customer/staff/admin)
- Có module nghiệp vụ + bảo mật + tích hợp payment + AI guardrail
- Dễ chuyển thành báo cáo kiến trúc và demo flow

### 8.2 Xin việc/portfolio

- Thể hiện full-stack triển khai thực tế
- Có quy tắc an toàn AI thay vì chatbot demo thuần
- Có tổ chức service/controller rõ ràng, dễ review code

---

## 9. Tài liệu liên quan

- `docs/AI_PORTING_GUIDE.md`
- `docs/AI_IMPLEMENTATION_STATUS.md`
- `docs/AI_FEATURE_PHASES_CHECKLIST.md`
- `docs/README_AI.md`

---

## 10. License

MIT - xem `LICENSE`.

---

## English

## 1. Project overview

**ZenoxDigital** is a cloud-first digital services webshop built with:

- Custom PHP MVC
- PDO (MySQL/MariaDB)
- Bootstrap 5
- Vanilla JavaScript

Project goals:

1. **Academic/capstone** usage
2. **Job portfolio** showcasing practical full-stack engineering
3. **Extensible codebase** for future development

---

## 2. Main modules

### 2.1 Storefront

- Digital service catalog and product detail pages
- Auth/profile/order history
- Wallet and transactions
- Customer AI widget

### 2.2 Backoffice/Admin

- KPI dashboard
- Product/category/order/user/coupon/feedback/settings management
- Payment and wallet monitoring
- Audit log + SQL Manager + health guards
- Admin AI Copilot

### 2.3 Security

- CSRF protection
- Rate limiting
- Session tracking
- 2FA (TOTP + backup codes)
- Role/scope-based access control

---

## 3. AI capabilities and architecture

## 3.1 AI scope

The AI layer is operationally useful but constrained by guardrails.  
It does **not** bypass permissions and does **not** fabricate missing data.

## 3.2 Customer AI flow

UI:

- `app/Views/partials/ai_chat_widget.php`
- `public/assets/js/ai-chat-widget.js`
- `public/assets/css/ai-chat-widget.css`

APIs:

- `POST /ai/chat/customer`
- `POST /ai/feedback/customer`

Core abilities:

- FAQ and product guidance
- Basic order/account support in safe scope
- Feedback capture

## 3.3 Admin AI flow

UI:

- `app/Views/partials/admin_ai_panel.php`
- `public/assets/js/admin-ai-panel.js`
- `public/assets/css/admin-ai-panel.css`

APIs:

- `POST /admin/ai/chat`
- `GET /admin/ai/summary`
- `GET /admin/ai/session`
- `GET /admin/ai/progress`

Core abilities:

- Dashboard summaries
- Fast intent responses
- Preliminary sales/coupon recommendations
- Capacity/restock insights when data is sufficient
- Guarded mutation lifecycle: `preview -> confirm -> execute`

## 3.4 Core AI services

- `AiBridgeService`
- `AiContextBuilder`
- `AiGuardService`
- `AdminAiIntentService`
- `AiSalesRecommendationService`
- `AdminAiSessionService`
- `AdminAiProgressService`

---

## 4. Detailed AI phase status

| Phase | Scope | Current status | Notes |
|---|---|---|---|
| 0 | AI infrastructure | DONE | Core bridge/context/guard wiring is in place |
| 1 | Storefront chatbot | DONE | Public widget + customer chat route active |
| 2 | Feedback support | DONE | Real feedback persistence and admin visibility |
| 3 | Order/account support | DONE | Ownership-safe support flow |
| 4 | Admin Copilot | DONE | Session persistence + progress + direct intents |
| 5 | Sales/promo suggestions | DONE (preliminary) | Works with guardrails and data-gap notes |
| 6 | Slow-moving/capacity analysis | PARTIAL | Schema + intent + recommendation wiring done; quality depends on real data |
| 7 | Profit-safe guardrails | PARTIAL | Guard checks implemented; requires complete operational finance data |
| 8 | Executive action planning | ROADMAP | Not fully implemented yet |

### 4.1 Data prerequisites (Phase 6/7)

Required product fields:

- `product_type`, `stock_qty`, `capacity_limit`, `capacity_used`
- `reorder_point`, `supplier_name`, `lead_time_days`
- `cost_price`, `min_margin_percent`, `platform_fee_percent`, `payment_fee_percent`, `ads_cost_per_order`, `delivery_cost`

If fields are missing or operationally empty, AI will warn/refuse instead of guessing.

---

## 5. How to use AI

## 5.1 Customer usage

1. Open home/product pages.
2. Launch Meow widget.
3. Ask FAQ/product/order/account questions.
4. Submit post-purchase feedback if needed.

## 5.2 Admin usage

1. Login and open `/admin`.
2. Open Meow Copilot.
3. Ask operational prompts (pending orders, top products, coupons, capacity).
4. For mutations, use preview then confirmation flow.

---

## 6. Local setup

Requirements:

- PHP 8.x
- MySQL/MariaDB
- Apache (XAMPP/Laragon)

Setup:

```bash
git clone https://github.com/EmBeHocCode/ZenoDigital.git
cd ZenoDigital
```

1. Create `.env` from `.env.example`
2. Configure app + DB settings
3. Import `database/schema.sql`
4. For existing DBs, run migration:
   - `database/20260422_add_phase67_product_columns.sql`
5. Open:
   - `http://localhost/ZenoxDigital/public`

---

## 7. Development roadmap

### Stage A - Data quality hardening

- Fill real operational values for phase 6/7 fields
- Improve admin input quality checks
- Standardize supplier/lead-time/cost data

### Stage B - Production-ready phase 6/7

- Improve capacity signal confidence
- Refine product-type-specific profit rules
- Add service-level tests for guardrail/recommendation logic

### Stage C - Phase 8 completion

- Daily/weekly executive reporting
- Prioritized action queue
- Action checklist for operators

---

## 8. Academic and portfolio value

- **Academic/capstone**: multi-actor architecture + security + payment + AI guardrails
- **Portfolio/job applications**: practical full-stack system with constrained AI operations

---

## 9. Related docs

- `docs/AI_PORTING_GUIDE.md`
- `docs/AI_IMPLEMENTATION_STATUS.md`
- `docs/AI_FEATURE_PHASES_CHECKLIST.md`
- `docs/README_AI.md`

---

## 10. License

MIT - see `LICENSE`.

