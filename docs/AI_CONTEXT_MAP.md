# Bản Đồ Context AI: Từ Bot Hiện Tại Sang ZenoxDigital

## Mục đích

Tài liệu này giúp dev biết:

- file nào trong bot hiện tại là nguồn tham chiếu
- file nào trong webshop nên sửa hoặc tạo mới
- cái gì dùng lại về mặt cơ chế
- cái gì phải viết mới hoàn toàn

## Quy tắc chung

- Không copy nguyên logic gateway Zalo sang webshop.
- Chỉ lấy cơ chế, pattern, interface, flow xử lý.
- Webshop là PHP MVC nên mọi adapter phải viết mới theo stack hiện tại.

## Bảng map tổng quát

| Nhu cầu trong webshop | File tham chiếu từ bot | Nên tái sử dụng cái gì | File đích trong webshop |
|---|---|---|---|
| Chatbot CSKH web | `E:\ZALO-BotChat\apps\bot\src\infrastructure\api\chat.api.ts` | flow session chat, API input/output, tool-call follow-up | `app/Controllers/AiController.php`, `app/Services/AiBridgeService.php` |
| Module và tool registry | `E:\ZALO-BotChat\apps\bot\src\core\plugin-manager\module-manager.ts` | pattern chia module, đăng ký tool, bật/tắt capability | `app/Services/AiContextBuilder.php`, `config/ai_capabilities.php` |
| Interface AI provider | `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\ai.interface.ts` | kiểu interface cho generate, stream, session | `app/Services/AiBridgeService.php` |
| Retry và rate-limit khi gọi model | `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\providers\gemini\gemini.provider.ts` | retry, fallback, reset session khi lỗi | `app/Services/AiBridgeService.php` |
| Capability catalog | `E:\ZALO-BotChat\apps\bot\src\infrastructure\commands\commands.catalog.ts` | pattern mô tả capability và ví dụ dùng | `config/ai_capabilities.php` hoặc `docs/AI_CAPABILITY_LIST.md` |
| Chat admin trên web | `E:\ZALO-BotChat\apps\web\src\app\chat\page.tsx` và `apps\bot\src\infrastructure\api\chat.api.ts` | flow tách session admin riêng | `app/Controllers/Admin/AiController.php`, `app/Views/partials/admin_ai_panel.php` |
| Tóm tắt dashboard | `E:\ZALO-BotChat\apps\bot\src\modules\gateway\services\quick-command.handler.ts` | pattern lấy dữ liệu, format output, card summary | `app/Services/AiContextBuilder.php`, `app/Controllers/Admin/AiController.php` |
| Prompt nhiều vai trò | prompt trong bot hiện tại | ý tưởng tách prompt theo channel/role | `app/Services/AiPromptFactory.php` nếu cần tạo mới |

## File bot dùng làm tham chiếu mạnh

### 1. `chat.api.ts`

Đường dẫn:

- `E:\ZALO-BotChat\apps\bot\src\infrastructure\api\chat.api.ts`

Lấy gì:

- sessionId
- reset session
- build prompt theo channel
- follow-up khi AI gọi tool

Không lấy nguyên:

- text prompt mang màu của bot hiện tại nếu không phù hợp shop

### 2. `module-manager.ts`

Đường dẫn:

- `E:\ZALO-BotChat\apps\bot\src\core\plugin-manager\module-manager.ts`

Lấy gì:

- cách chia năng lực theo module
- cách đăng ký công cụ
- cách bật hoặc tắt capability

Áp dụng:

- chia capability theo:
  - customer support
  - order support
  - admin analytics
  - promotion advisor
  - inventory hoặc capacity advisor

### 3. `ai.interface.ts`

Đường dẫn:

- `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\ai.interface.ts`

Lấy gì:

- interface trừu tượng cho provider
- response chuẩn
- media part nếu sau này mở rộng

Áp dụng:

- PHP service có cùng tư duy:
  - request chuẩn
  - response chuẩn
  - provider chính
  - provider fallback

### 4. `gemini.provider.ts`

Đường dẫn:

- `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\providers\gemini\gemini.provider.ts`

Lấy gì:

- retry
- xử lý rate limit
- reset session khi hỏng
- logging gọn

Áp dụng:

- `AiBridgeService.php` phải có timeout, retry nhẹ, fallback và error message an toàn

### 5. `commands.catalog.ts`

Đường dẫn:

- `E:\ZALO-BotChat\apps\bot\src\infrastructure\commands\commands.catalog.ts`

Lấy gì:

- tư duy capability catalog
- mô tả command, alias, example

Áp dụng:

- làm catalog năng lực AI cho webshop, ví dụ:
  - `product_advisor`
  - `faq_support`
  - `order_lookup`
  - `feedback_capture`
  - `dashboard_summary`
  - `promotion_advisor`
  - `profit_guard`

## File bot không được bê nguyên

### Không dùng nguyên:

- `E:\ZALO-BotChat\apps\bot\src\modules\gateway\processors\message.processor.ts`
- `E:\ZALO-BotChat\apps\bot\src\modules\gateway\services\quick-command.handler.ts`

### Lý do:

- gắn chặt với Zalo
- có mention, group thread, reply quote, reaction, sticker, mute, kick
- không phù hợp web shop

## Map file đích cụ thể trong ZenoxDigital

## 1. AI cho khách truy cập

### File nên tạo

- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiBridgeService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\ai_chat_widget.php`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\js\ai-chat-widget.js`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\css\ai-chat-widget.css`

### File nên sửa

- `C:\xampp\htdocs\ZenoxDigital\config\routes.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\main.php`

## 2. AI cho admin dashboard

### File nên tạo

- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\admin_ai_panel.php`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\js\admin-ai-panel.js`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\css\admin-ai-panel.css`

### File nên sửa

- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\admin.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\dashboard\index.php`

## 3. Dữ liệu và guardrail

### File nên tạo

- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiGuardService.php`
- `C:\xampp\htdocs\ZenoxDigital\config\ai_capabilities.php`

### File nên sửa

- `C:\xampp\htdocs\ZenoxDigital\database\schema.sql`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Product.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Order.php`

## Map capability theo phase

| Phase | Capability chính | Dữ liệu chính | UI chính |
|---|---|---|---|
| 0 | AI bridge | config, env | chưa cần UI |
| 1 | product advisor, faq support | products, categories | widget chat landing |
| 2 | feedback capture | feedback table | widget chat + admin view |
| 3 | order lookup | orders, users, wallet | widget chat |
| 4 | dashboard summary | products, orders, coupons | admin AI panel |
| 5 | promotion advisor | orders, products, coupons | admin AI panel |
| 6 | inventory hoặc capacity advisor | product_type, stock hoặc capacity | admin AI panel |
| 7 | profit guard | cost_price, margin | admin AI panel |
| 8 | executive report | aggregated summaries | admin AI panel |

## Checklist dùng file này

- [ ] Đã xác định phase hiện tại
- [ ] Đã chọn đúng file bot làm tham chiếu
- [ ] Đã chọn đúng file webshop cần sửa
- [ ] Đã tránh copy logic Zalo-specific
- [ ] Đã note lại capability đang triển khai vào status file
