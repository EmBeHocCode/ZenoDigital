# Hướng Dẫn Tích Hợp AI Từ Bot Hiện Tại Sang ZenoxDigital

## Mục tiêu

Tài liệu này giúp dev webshop tại `C:\xampp\htdocs\ZenoxDigital` tích hợp AI theo hướng thực dụng, tận dụng kinh nghiệm và cơ chế tốt từ bot hiện tại ở `E:\ZALO-BotChat`, nhưng không bê nguyên khối code Zalo sang webshop.

Trước khi dùng tài liệu này, phải đọc:

- `C:\xampp\htdocs\ZenoxDigital\AGENTS.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\README_AI.md`

Mục tiêu nghiệp vụ:

- Chat bot trên web shop để tư vấn, chăm sóc khách hàng, trả lời FAQ, nhận feedback.
- AI hỗ trợ quản trị shop trong dashboard.
- AI đọc dữ liệu bán hàng, đơn hàng, sản phẩm để gợi ý bán hàng, khuyến mãi, nhập thêm nguồn hàng hoặc quản lý capacity.
- AI không đưa ra khuyến nghị làm shop lỗ.

## Kết luận ngắn cho dev

- Không copy nguyên `apps/bot/src/modules/gateway` hoặc toàn bộ bot Zalo vào webshop.
- Có thể và nên tái sử dụng cơ chế:
  - quản lý module và tool
  - session chat
  - AI provider
  - retry và rate-limit
  - mẫu web chat API
- Webshop này là PHP MVC, vì vậy phải tích hợp theo một trong hai hướng:
  - hướng nhanh: ZenoxDigital gọi bot AI như một service ngoài qua HTTP
  - hướng sạch lâu dài: tách `ai-core` dùng chung rồi viết adapter riêng cho PHP webshop

## Repo tham chiếu bắt buộc phải đọc

Dev webshop phải mở các file sau trong repo bot trước khi code:

- `E:\ZALO-BotChat\apps\bot\COMMAND_STATUS_REPORT.md`
- `E:\ZALO-BotChat\apps\bot\src\app\app.module.ts`
- `E:\ZALO-BotChat\apps\bot\src\core\plugin-manager\module-manager.ts`
- `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\ai.interface.ts`
- `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\providers\gemini\gemini.provider.ts`
- `E:\ZALO-BotChat\apps\bot\src\infrastructure\api\chat.api.ts`
- `E:\ZALO-BotChat\apps\bot\src\infrastructure\commands\commands.catalog.ts`
- `E:\ZALO-BotChat\apps\bot\src\modules\gateway\services\quick-command.handler.ts`
- `E:\ZALO-BotChat\apps\bot\src\modules\gateway\processors\message.processor.ts`

## Cái gì được bê cơ chế sang webshop

### 1. Kiến trúc module và tool

Nguồn tham chiếu:

- `E:\ZALO-BotChat\apps\bot\src\app\app.module.ts`
- `E:\ZALO-BotChat\apps\bot\src\core\plugin-manager\module-manager.ts`

Ý tưởng cần lấy:

- chia hệ thống AI theo module hoặc capability
- mỗi capability có tool riêng
- có registry để AI biết mình có thể gọi công cụ nào
- có thể bật hoặc tắt từng module

Áp dụng sang webshop:

- tạo `shop-support-tools`
- tạo `shop-admin-tools`
- tạo `analytics-tools`
- tạo `feedback-tools`

### 2. Session chat và web chat API

Nguồn tham chiếu:

- `E:\ZALO-BotChat\apps\bot\src\infrastructure\api\chat.api.ts`

Ý tưởng cần lấy:

- mỗi phiên chat có `sessionId`
- có API nhận tin nhắn, trả lời, giữ context
- có thể reset phiên

Áp dụng sang webshop:

- mỗi khách trên landing có một `sessionId` riêng
- mỗi admin trong dashboard có một `sessionId` riêng cho AI Copilot
- có API:
  - `POST /ai/chat/customer`
  - `POST /ai/chat/admin`
  - `DELETE /ai/chat/{sessionId}`

### 3. AI provider và retry

Nguồn tham chiếu:

- `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\ai.interface.ts`
- `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\providers\gemini\gemini.provider.ts`

Ý tưởng cần lấy:

- tách interface provider rõ ràng
- nếu quota hoặc rate-limit thì retry
- nếu cần đổi key thì có cơ chế xoay vòng

Áp dụng sang webshop:

- tạo lớp `AiBridgeService` hoặc `AiProviderService`
- hỗ trợ model chính và model fallback
- log lỗi gọn, không lộ API key

### 4. Command catalog và capability map

Nguồn tham chiếu:

- `E:\ZALO-BotChat\apps\bot\src\infrastructure\commands\commands.catalog.ts`

Ý tưởng cần lấy:

- thay vì để AI làm bừa, cần có danh sách năng lực rõ ràng
- mỗi năng lực có mô tả, input, alias, ví dụ

Áp dụng sang webshop:

- tạo `app/Config/AiCapabilityCatalog.php` hoặc `config/ai_capabilities.php`
- các capability nên có:
  - tư vấn sản phẩm
  - tra cứu đơn hàng
  - nhận feedback
  - gợi ý sản phẩm liên quan
  - tóm tắt dashboard
  - gợi ý khuyến mãi
  - cảnh báo hàng bán chậm
  - gợi ý nhập thêm nguồn hàng hoặc capacity

## Cái gì không được bê nguyên

### 1. Gateway Zalo

Không bê nguyên:

- `E:\ZALO-BotChat\apps\bot\src\modules\gateway\processors\message.processor.ts`
- `E:\ZALO-BotChat\apps\bot\src\modules\gateway\services\quick-command.handler.ts`

Lý do:

- gắn chặt với `threadId`, nhóm chat, tag, reply, quote, sticker, reaction
- webshop không có khái niệm Zalo group

### 2. Prompt phong cách Zalo

Không bê nguyên prompt chat kiểu Zalo vì webshop cần:

- lịch sự hơn
- ít slang hơn
- trả lời hướng bán hàng, hỗ trợ mua hàng
- tránh tông “bạn bè / nhóm chat”

### 3. Command xã hội hoặc quản trị nhóm

Không áp dụng trực tiếp:

- `kick`
- `mute`
- `replyai` kiểu nhóm
- `all`, `allan`
- card cho nhóm chat Zalo

## Hai hướng triển khai

## Hướng A: Nhanh nhất để chạy sớm

ZenoxDigital gọi bot AI hiện tại như một service ngoài qua HTTP.

### Cách làm

1. Giữ bot AI chạy riêng.
2. Webshop PHP gọi sang bot API.
3. PHP chịu trách nhiệm lấy dữ liệu shop và ghép vào prompt trước khi gửi.
4. Bot AI trả text hoặc JSON.
5. Webshop render ra giao diện chat.

### Ưu điểm

- nhanh có kết quả
- ít sửa bot
- ít đụng vào kiến trúc PHP hiện tại

### Nhược điểm

- chưa tối ưu dài hạn
- tool gọi dữ liệu shop sẽ hơi vòng nếu để toàn bộ ở service ngoài

### Khi nào nên dùng

- cần ra bản demo hoặc MVP sớm
- muốn thử nghiệm AI CSKH trước

## Hướng B: Sạch và tốt cho dài hạn

Tách lõi AI từ bot Node thành package hoặc service chung, sau đó viết adapter riêng cho webshop.

### Cách làm

1. Tách `module manager`, `AI provider`, `session`, `tool execution` thành lõi chung.
2. Webshop PHP chỉ gọi lõi đó qua HTTP hoặc một service trung gian.
3. Viết tool dành riêng cho shop.

### Ưu điểm

- sạch
- dễ mở rộng
- ít rủi ro mang logic Zalo sang shop

### Nhược điểm

- mất thời gian hơn

### Khuyến nghị

Nên làm theo lộ trình:

- Phase đầu dùng Hướng A
- khi AI bắt đầu ổn định thì nâng lên Hướng B

## Kiến trúc đề xuất cho ZenoxDigital

```text
Khách truy cập web
    -> widget chat trên landing / trang sản phẩm
    -> PHP controller nhận request
    -> PHP service chuẩn hóa context
    -> AI service
    -> trả lời lại cho khách

Admin dashboard
    -> AI Copilot trong trang admin
    -> PHP controller nhận request
    -> PHP service gom dữ liệu dashboard, đơn hàng, sản phẩm, coupon
    -> AI service
    -> trả lời theo ngữ cảnh quản trị
```

## Vị trí nên gắn AI vào giao diện hiện tại

## 1. Landing và trang người dùng

Nguồn giao diện:

- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\main.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\home\index.php`

Nên thêm:

- một nút chat nổi ở góc phải dưới
- một popup chat hoặc drawer chat
- trên trang chi tiết sản phẩm có thể thêm khối “Hỏi AI về sản phẩm này”

Không nên:

- chèn chatbot to đùng vào giữa hero
- để chat che CTA mua hàng

## 2. Admin dashboard

Nguồn giao diện:

- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\admin.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\dashboard\index.php`

Nên thêm:

- nút `AI Copilot` ở topbar hoặc cạnh `Quick actions`
- panel trượt bên phải
- hoặc một card “Hỏi AI về dashboard”

## Cấu trúc file nên thêm trong ZenoxDigital

Tạo mới:

```text
C:\xampp\htdocs\ZenoxDigital\app\Controllers\AiController.php
C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\AiController.php
C:\xampp\htdocs\ZenoxDigital\app\Services\AiBridgeService.php
C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php
C:\xampp\htdocs\ZenoxDigital\app\Services\AiGuardService.php
C:\xampp\htdocs\ZenoxDigital\app\Views\partials\ai_chat_widget.php
C:\xampp\htdocs\ZenoxDigital\app\Views\partials\admin_ai_panel.php
C:\xampp\htdocs\ZenoxDigital\public\assets\js\ai-chat-widget.js
C:\xampp\htdocs\ZenoxDigital\public\assets\js\admin-ai-panel.js
C:\xampp\htdocs\ZenoxDigital\public\assets\css\ai-chat-widget.css
C:\xampp\htdocs\ZenoxDigital\public\assets\css\admin-ai-panel.css
```

Lưu ý:

- `app\Services` hiện chưa có, tạo mới là hợp lý
- không nhét toàn bộ logic AI vào controller

## Route cần thêm

Sửa:

- `C:\xampp\htdocs\ZenoxDigital\config\routes.php`

Đề xuất thêm:

```php
['POST', '/ai/chat/customer', 'AiController@customerChat'],
['POST', '/ai/feedback', 'AiController@feedback'],
['POST', '/ai/chat/admin', 'Admin\\AiController@chat'],
['GET', '/admin/ai/summary', 'Admin\\AiController@summary'],
```

## Layout cần chỉnh

### `main.php`

Sửa:

- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\main.php`

Việc cần làm:

- include partial `ai_chat_widget.php` trước `</body>`
- load CSS và JS cho widget chat

### `admin.php`

Sửa:

- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\admin.php`

Việc cần làm:

- include partial `admin_ai_panel.php`
- load CSS và JS cho admin copilot

## Mô hình tính năng AI theo phase

## Phase 1: Chatbot CSKH cho khách

Mục tiêu:

- trả lời FAQ
- tư vấn chọn sản phẩm
- hỗ trợ điều hướng
- nhận feedback

Input dữ liệu:

- danh sách sản phẩm
- category
- FAQ tĩnh
- thông tin liên hệ

File cần đọc:

- `C:\xampp\htdocs\ZenoxDigital\app\Models\Product.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Category.php`
- `C:\xampp\htdocs\ZenoxDigital\config\config.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\home\index.php`

Kết quả đầu ra:

- trả lời ngắn, rõ
- gợi ý đúng sản phẩm
- có CTA dẫn sang trang chi tiết sản phẩm

## Phase 2: Feedback và tra cứu đơn hàng

Mục tiêu:

- nhận phản hồi khách
- lưu feedback
- tra cứu tình trạng đơn hàng

Input dữ liệu:

- order code
- email hoặc user session
- lịch sử đơn hàng

File cần đọc:

- `C:\xampp\htdocs\ZenoxDigital\app\Models\Order.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\User.php`

Nên thêm:

- bảng `customer_feedback`
- bảng `ai_chat_logs`

## Phase 3: AI Copilot cho admin

Mục tiêu:

- tóm tắt dashboard
- cảnh báo đơn chờ xử lý
- gợi ý sản phẩm nên đẩy bán
- tóm tắt coupon

Nguồn dữ liệu:

- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\dashboard\index.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Product.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Order.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Coupon.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\RankProgram.php`

Kết quả đầu ra nên có:

- tóm tắt doanh thu
- sản phẩm bán chạy
- sản phẩm bán chậm
- danh mục cần chú ý
- đề xuất hành động tiếp theo

## Phase 4: Gợi ý bán hàng và khuyến mãi

Mục tiêu:

- gợi ý combo
- gợi ý up-sell
- gợi ý khuyến mãi
- nhưng không làm lỗ

Ràng buộc:

- nếu chưa có `giá vốn`, AI chỉ được gợi ý ở mức tương đối, không được khẳng định lãi thật
- nếu chưa có phí nền tảng và chi phí vận hành, AI phải ghi rõ đây là gợi ý sơ bộ

## Phase 5: Gợi ý nhập hàng hoặc capacity

Mục tiêu:

- với hàng số hoặc dịch vụ, AI phải phân biệt:
  - hàng có mã
  - hàng có ví/quỹ
  - hàng dạng server / VPS / cloud capacity

Không được dùng một logic “nhập kho” cho tất cả.

## Tool nên có cho webshop

Danh sách tool tối thiểu:

- `searchProducts`
- `getProductDetail`
- `suggestProductsByNeed`
- `checkOrderStatus`
- `listPendingOrders`
- `saveCustomerFeedback`
- `getDashboardSummary`
- `getTopSellingProducts`
- `getSlowMovingProducts`
- `getCouponSummary`
- `suggestPromotionPlan`
- `estimateProfitImpact`
- `suggestRestockOrCapacityPlan`

## Dữ liệu hiện tại có gì và thiếu gì

## Đã có

Từ schema hiện tại:

- bảng `products`
- bảng `orders`
- bảng `order_items`
- bảng `users`
- bảng `wallet_transactions`
- bảng `user_rank_coupons`

Nguồn:

- `C:\xampp\htdocs\ZenoxDigital\database\schema.sql`

## Chưa đủ để AI tính lời lỗ chuẩn

Cần bổ sung cho bảng `products`:

- `cost_price` hoặc `gia_von`
- `stock_qty`
- `reorder_point`
- `supplier_name`
- `lead_time_days`
- `min_margin_percent`
- `product_type`
- `capacity_limit`
- `capacity_used`
- `is_digital_code`

Cần bổ sung thêm nếu muốn tính đúng lợi nhuận:

- `platform_fee_percent`
- `payment_fee_percent`
- `ads_cost_per_order`
- `delivery_cost`

## SQL migration đề xuất

Dev có thể tạo migration hoặc cập nhật schema theo hướng:

```sql
ALTER TABLE products
ADD COLUMN cost_price DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER price,
ADD COLUMN stock_qty INT NOT NULL DEFAULT 0 AFTER stock_status,
ADD COLUMN reorder_point INT NOT NULL DEFAULT 0 AFTER stock_qty,
ADD COLUMN supplier_name VARCHAR(180) NULL AFTER reorder_point,
ADD COLUMN lead_time_days INT NOT NULL DEFAULT 0 AFTER supplier_name,
ADD COLUMN min_margin_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER lead_time_days,
ADD COLUMN product_type ENUM('digital_code','wallet','capacity','service') NOT NULL DEFAULT 'service' AFTER min_margin_percent,
ADD COLUMN capacity_limit INT NOT NULL DEFAULT 0 AFTER product_type,
ADD COLUMN capacity_used INT NOT NULL DEFAULT 0 AFTER capacity_limit;
```

## Guardrail bắt buộc cho AI

AI không được:

- tự kết luận là một chương trình khuyến mãi chắc chắn có lời nếu không có `giá vốn`
- tự kết luận nên giảm giá sâu khi chưa tính hết phí
- tự gợi ý bán dưới giá vốn

AI phải:

- ghi rõ “ước tính”
- ưu tiên an toàn tài chính
- ưu tiên giảm giá cho hàng bán chậm hơn là giảm đại trà

## Prompt vai trò nên dùng

## Prompt cho AI CSKH

Nguyên tắc:

- trả lời lịch sự, ngắn, rõ
- tập trung giải quyết nhu cầu mua hàng
- không nói lan man
- nếu chưa đủ dữ liệu, hỏi lại 1 đến 2 câu ngắn
- luôn ưu tiên gợi ý sản phẩm phù hợp
- khi có thể, dẫn khách sang trang sản phẩm

## Prompt cho AI quản trị

Nguyên tắc:

- trả lời như trợ lý vận hành shop
- ưu tiên dữ liệu thực
- chỉ ra tín hiệu quan trọng trước
- nêu rõ rủi ro
- với khuyến mãi hoặc nhập hàng, phải cân nhắc lời lỗ
- nếu thiếu dữ liệu, nói rõ thiếu trường nào

## Hướng triển khai chi tiết cho dev

## Bước 1: Tạo lớp bridge AI

Tạo:

- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiBridgeService.php`

Nhiệm vụ:

- gọi HTTP sang AI service
- truyền `sessionId`
- truyền `message`
- nhận `reply`
- xử lý timeout và fallback

Nếu dùng bot service ngoài:

- cấu hình `.env`
  - `AI_BRIDGE_URL`
  - `AI_BRIDGE_KEY`
  - `AI_CHAT_TIMEOUT`

## Bước 2: Tạo context builder

Tạo:

- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php`

Nhiệm vụ:

- với khách: gom thông tin sản phẩm, category, FAQ
- với admin: gom KPI dashboard, top sản phẩm, đơn chờ xử lý, coupon

Không gửi toàn bộ database vào AI.

Chỉ gửi context rút gọn, ví dụ:

- top 10 sản phẩm bán chạy
- 10 đơn gần đây
- sản phẩm liên quan
- thông tin đơn hàng cần tra cứu

## Bước 3: Tạo controller cho khách

Tạo:

- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\AiController.php`

Các action tối thiểu:

- `customerChat()`
- `feedback()`

## Bước 4: Tạo controller cho admin

Tạo:

- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\AiController.php`

Các action tối thiểu:

- `chat()`
- `summary()`
- `promotionSuggestion()`

## Bước 5: Gắn widget vào layout chính

Sửa:

- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\main.php`

Thêm:

- partial widget
- css widget
- js widget

## Bước 6: Gắn copilot vào dashboard admin

Sửa:

- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\admin.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\dashboard\index.php`

Thêm:

- nút mở AI Copilot
- panel chat cho admin
- khối gợi ý nhanh:
  - tóm tắt hôm nay
  - đơn cần xử lý
  - sản phẩm nên đẩy bán
  - cảnh báo doanh thu thấp

## Giao diện AI nên làm thế nào

## Với landing

Nên có:

- icon chat nổi
- popup nhẹ
- câu mở đầu như:
  - “Bạn cần tư vấn VPS, server game hay wallet?”
  - “Mình có thể giúp chọn gói phù hợp.”

## Với admin

Nên có:

- panel trượt bên phải
- prompt gợi ý nhanh
- nút action sẵn:
  - “Tóm tắt dashboard hôm nay”
  - “Sản phẩm nào nên khuyến mãi”
  - “Đơn nào cần xử lý gấp”
  - “Hàng nào bán chậm”

## Card và bảng

Nếu có nội dung dạng tổng hợp hoặc đề xuất, nên dùng card.

Ví dụ:

- card `Tóm tắt hôm nay`
- card `Sản phẩm cần chú ý`
- card `Kế hoạch khuyến mãi`

Nếu có dữ liệu nhiều cột:

- dùng bảng HTML trong admin
- không nên ném nguyên khối văn bản dài

## Phân quyền sử dụng AI

Khách chưa đăng nhập:

- được dùng chat tư vấn cơ bản
- không được tra cứu đơn chi tiết nếu thiếu định danh

Khách đã đăng nhập:

- được tra cứu đơn hàng của chính mình
- được xem lịch sử giao dịch của chính mình

Admin:

- được dùng AI Copilot
- được hỏi về doanh thu, đơn hàng, coupon, sản phẩm

## Logging và bảo mật

Phải thêm:

- log câu hỏi AI
- log người dùng nào đã hỏi
- log câu trả lời
- log tool hoặc context nào đã được dùng

Không log:

- API key
- token bí mật
- dữ liệu nhạy cảm không cần thiết

Nên tạo:

- bảng `ai_chat_logs`
- bảng `customer_feedback`

## Checklist hoàn thành cho dev

## MVP

- có widget chat ngoài trang chủ
- có API chat cho khách
- có API chat cho admin
- AI trả lời được FAQ và tư vấn sản phẩm
- AI tóm tắt được dashboard

## Bản nâng cao

- AI tra cứu đơn hàng
- AI lưu feedback
- AI gợi ý khuyến mãi sơ bộ
- AI cảnh báo sản phẩm bán chậm

## Bản vận hành tốt

- có dữ liệu giá vốn và capacity
- AI có guardrail lời lỗ
- có audit log
- có fallback nếu AI service lỗi

## Prompt giao việc cho Codex dev webshop

```text
Đây là project webshop PHP MVC tại:
C:\xampp\htdocs\ZenoxDigital

Đây là repo bot AI tham chiếu:
E:\ZALO-BotChat

Yêu cầu:
1. Không copy nguyên bot Zalo vào webshop.
2. Dùng repo bot làm nguồn tham chiếu kiến trúc và cơ chế AI.
3. Đọc các file:
   - E:\ZALO-BotChat\apps\bot\COMMAND_STATUS_REPORT.md
   - E:\ZALO-BotChat\apps\bot\src\infrastructure\api\chat.api.ts
   - E:\ZALO-BotChat\apps\bot\src\core\plugin-manager\module-manager.ts
   - E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\ai.interface.ts
   - E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\providers\gemini\gemini.provider.ts
   - E:\ZALO-BotChat\apps\bot\src\infrastructure\commands\commands.catalog.ts
4. Tích hợp AI vào ZenoxDigital theo phase:
   - chatbot CSKH
   - feedback
   - admin copilot
   - gợi ý khuyến mãi
   - gợi ý nhập hàng hoặc capacity
5. Tạo các file mới:
   - app/Controllers/AiController.php
   - app/Controllers/Admin/AiController.php
   - app/Services/AiBridgeService.php
   - app/Services/AiContextBuilder.php
   - app/Services/AiGuardService.php
   - app/Views/partials/ai_chat_widget.php
   - app/Views/partials/admin_ai_panel.php
   - public/assets/js/ai-chat-widget.js
   - public/assets/js/admin-ai-panel.js
   - public/assets/css/ai-chat-widget.css
   - public/assets/css/admin-ai-panel.css
6. Sửa:
   - config/routes.php
   - app/Views/layouts/main.php
   - app/Views/layouts/admin.php
   - app/Views/admin/dashboard/index.php
7. Giữ code sạch, tách controller khỏi service.
8. Nếu thiếu dữ liệu để tính lời lỗ, không được bịa; phải nêu rõ còn thiếu cột nào.
```

## Lộ trình phase tính năng chi tiết

## Phase 0: Chuẩn bị hạ tầng AI

Mục tiêu:

- tạo nền để các phase sau gắn vào mà không phải đập đi làm lại

Việc phải làm:

- tạo `app/Services/AiBridgeService.php`
- tạo `app/Services/AiContextBuilder.php`
- tạo `app/Services/AiGuardService.php`
- thêm config trong `.env` cho AI bridge
- thêm route cơ bản cho chat khách và chat admin
- tạo partial rỗng cho widget chat và panel admin

Kết quả chấp nhận:

- có thể gửi một câu test từ webshop sang AI service và nhận lại trả lời
- không hard-code API key ở view hoặc JS client

## Phase 1: Chatbot CSKH ngoài landing và trang sản phẩm

Mục tiêu:

- khách truy cập web có thể hỏi về sản phẩm, dịch vụ, FAQ

Tính năng:

- tư vấn chọn sản phẩm
- trả lời FAQ
- gợi ý sản phẩm liên quan
- điều hướng sang trang sản phẩm

UI:

- widget chat nổi trong `main.php`
- popup chat gọn nhẹ, không che CTA chính

File trọng tâm:

- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\main.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\home\index.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Product.php`

Kết quả chấp nhận:

- khách hỏi “có VPS nào phù hợp web nhỏ không” thì bot gợi ý đúng
- khách hỏi “mua wallet steam thế nào” thì bot trả lời đúng hướng
- khách hỏi FAQ thì bot trả lời ngắn gọn

## Phase 2: Feedback và hỗ trợ sau bán

Mục tiêu:

- khách có thể gửi feedback hoặc yêu cầu hỗ trợ

Tính năng:

- nhận feedback từ widget chat
- lưu feedback vào database
- phân loại feedback cơ bản: tích cực, trung tính, tiêu cực
- tạo ticket hỗ trợ nếu cần

Nên thêm:

- bảng `customer_feedback`
- bảng `support_tickets`

Kết quả chấp nhận:

- có thể lưu feedback từ khách
- admin xem được feedback mới
- AI biết nói “mình đã ghi nhận phản hồi của bạn”

## Phase 3: Tra cứu đơn hàng và hỗ trợ tài khoản

Mục tiêu:

- khách tra cứu được đơn hàng mà không cần nhắn admin thủ công

Tính năng:

- tra cứu tình trạng đơn hàng theo mã đơn
- nếu đã đăng nhập thì tra cứu đơn hàng của chính user
- hướng dẫn nạp ví hoặc kiểm tra giao dịch cơ bản

File trọng tâm:

- `C:\xampp\htdocs\ZenoxDigital\app\Models\Order.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\User.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\WalletTransaction.php`

Kết quả chấp nhận:

- hỏi “đơn ORD-xxxx của tôi tới đâu rồi” thì có trả lời
- khách chưa đăng nhập không được xem dữ liệu người khác

## Phase 4: Admin AI Copilot trong dashboard

Mục tiêu:

- admin có một trợ lý vận hành ngay trong dashboard

Tính năng:

- tóm tắt dashboard hôm nay
- nêu top sản phẩm bán chạy
- nêu đơn chờ xử lý
- tóm tắt coupon và trạng thái doanh thu

UI:

- nút `AI Copilot` trên layout admin
- panel chat trong dashboard admin

File trọng tâm:

- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\admin.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\dashboard\index.php`

Kết quả chấp nhận:

- admin bấm hỏi “hôm nay shop đang thế nào” là AI tóm tắt đúng
- AI không tự bịa số liệu không có trong dashboard

## Phase 5: Gợi ý bán hàng và khuyến mãi

Mục tiêu:

- AI gợi ý chiến dịch bán hàng, combo, upsell, cross-sell

Tính năng:

- gợi ý sản phẩm nên đẩy bán
- gợi ý nhóm hàng nên ghép combo
- gợi ý coupon hoặc khuyến mãi sơ bộ

Guardrail:

- nếu thiếu `giá vốn` thì phải ghi rõ chỉ là gợi ý marketing sơ bộ
- không được khẳng định chắc chắn có lời

Kết quả chấp nhận:

- AI đề xuất hợp lý cho admin
- không khuyến khích giảm giá vô tội vạ

## Phase 6: Phân tích hàng bán chậm, tồn và capacity

Mục tiêu:

- AI nhận diện sản phẩm bán chậm hoặc tài nguyên sắp cạn

Tính năng:

- nêu sản phẩm lâu không bán
- cảnh báo nguồn mã, ví hoặc slot capacity sắp thiếu
- gợi ý ưu tiên nhập thêm hoặc cân đối nguồn

Điều kiện dữ liệu:

- cần có `stock_qty` hoặc `capacity_limit/capacity_used`
- cần biết `product_type`

Kết quả chấp nhận:

- AI không dùng chung một công thức cho mọi loại hàng số
- có phân biệt `digital_code`, `wallet`, `capacity`, `service`

## Phase 7: Guardrail lợi nhuận và kiểm soát không lỗ

Mục tiêu:

- AI gợi ý an toàn về tài chính

Tính năng:

- tính sơ bộ biên lợi nhuận
- chặn đề xuất dưới giá vốn
- đưa cảnh báo nếu đề xuất đang có nguy cơ lỗ

Điều kiện dữ liệu:

- cần `cost_price`
- nên có phí thanh toán, phí nền tảng, chi phí khác

Kết quả chấp nhận:

- AI nói rõ vì sao không nên giảm giá quá sâu
- AI ưu tiên bảo toàn biên lợi nhuận tối thiểu

## Phase 8: Báo cáo điều hành và tác vụ hành động

Mục tiêu:

- AI không chỉ tóm tắt mà còn đưa được kế hoạch hành động

Tính năng:

- báo cáo ngày hoặc tuần
- gợi ý việc cần làm tiếp theo
- tạo checklist hành động cho admin

Ví dụ:

- “3 đơn cần xử lý ngay”
- “2 sản phẩm nên đẩy khuyến mãi”
- “1 danh mục đang giảm doanh thu”

Kết quả chấp nhận:

- admin có thể dùng AI như một trợ lý điều hành thật

## Bộ prompt dùng ngay cho Codex dev webshop

## Prompt 1: Prompt bắt đầu dự án AI

```text
Đây là project webshop PHP MVC tại:
C:\xampp\htdocs\ZenoxDigital

Bạn phải đọc trước:
- C:\xampp\htdocs\ZenoxDigital\AGENTS.md
- C:\xampp\htdocs\ZenoxDigital\docs\AI_PORTING_GUIDE.md

Đây là repo bot AI tham chiếu:
E:\ZALO-BotChat

Yêu cầu:
- Không copy nguyên bot Zalo vào webshop
- Chỉ tái sử dụng cơ chế phù hợp
- Mọi thay đổi phải bám đúng stack PHP MVC hiện tại
- Nếu task liên quan lời lỗ, nhập hàng, khuyến mãi thì phải kiểm tra schema trước, thiếu dữ liệu phải báo rõ

Hãy:
1. đọc AGENTS.md
2. đọc AI_PORTING_GUIDE.md
3. xác định phase hiện tại cần làm
4. liệt kê file sẽ sửa
5. triển khai trực tiếp
```

## Prompt 2: Prompt làm Phase 1

```text
Hãy triển khai Phase 1 trong C:\xampp\htdocs\ZenoxDigital theo docs/AI_PORTING_GUIDE.md.

Mục tiêu:
- tạo chatbot CSKH trên landing và trang người dùng
- có widget chat nổi
- có API chat cho khách
- AI tư vấn sản phẩm, trả lời FAQ, gợi ý sản phẩm phù hợp

Yêu cầu:
- tạo file mới theo guide nếu cần
- sửa layout main để gắn widget
- giữ UI gọn, không che CTA mua hàng
- không dùng logic Zalo-specific
- tách controller khỏi service
- nếu cần API bridge thì đọc config từ .env

Khi xong, báo rõ:
- file đã tạo
- file đã sửa
- luồng hoạt động
- cách test
```

## Prompt 3: Prompt làm Phase 4

```text
Hãy triển khai Phase 4 trong C:\xampp\htdocs\ZenoxDigital theo docs/AI_PORTING_GUIDE.md.

Mục tiêu:
- thêm AI Copilot cho admin dashboard
- có nút mở panel AI trong layout admin
- AI tóm tắt dashboard, đơn chờ xử lý, top sản phẩm, coupon

Yêu cầu:
- gắn UI vào app/Views/layouts/admin.php hoặc admin/dashboard/index.php
- tạo API chat admin riêng
- dùng dữ liệu thật từ Product, Order, Coupon
- không bịa số liệu
- output nên gọn, rõ, ưu tiên card hoặc list dễ đọc

Khi xong, báo:
- file đã sửa
- câu hỏi mẫu admin có thể dùng
- các giới hạn dữ liệu hiện tại
```

## Prompt 4: Prompt làm Phase 5-7

```text
Hãy triển khai Phase 5 đến Phase 7 trong C:\xampp\htdocs\ZenoxDigital theo docs/AI_PORTING_GUIDE.md.

Mục tiêu:
- AI gợi ý khuyến mãi
- AI phát hiện hàng bán chậm hoặc capacity yếu
- AI có guardrail chống đề xuất gây lỗ

Yêu cầu:
- kiểm tra schema hiện tại trước
- nếu thiếu các cột như cost_price, stock_qty, product_type, capacity_limit, capacity_used thì phải nêu rõ và thêm migration đề xuất
- không khẳng định lãi nếu chưa đủ dữ liệu
- ưu tiên output theo card hoặc bảng trong admin

Khi xong, báo:
- dữ liệu đã dùng
- dữ liệu còn thiếu
- rủi ro nếu chạy với schema hiện tại
```

## Prompt 5: Prompt review trước khi merge

```text
Hãy review phần tích hợp AI của ZenoxDigital.

Bắt buộc kiểm tra:
- đã đọc AGENTS.md và docs/AI_PORTING_GUIDE.md chưa
- có copy nhầm logic Zalo-specific không
- controller có bị ôm quá nhiều logic không
- service có tách rõ context builder và AI bridge không
- có lộ API key ở JS hay view không
- có chặn quyền admin/customer đúng chưa
- có bịa dữ liệu lời lỗ không khi schema chưa đủ

Nếu có vấn đề, nêu findings theo mức độ nghiêm trọng.
```

## Kết luận cuối

Với ZenoxDigital hiện tại, hướng tốt nhất là:

- triển khai AI CSKH trên landing trước
- triển khai AI Copilot cho admin ngay sau đó
- dùng bot hiện tại làm nguồn tham chiếu và có thể làm AI bridge service ở phase đầu
- bổ sung dữ liệu `giá vốn`, `capacity`, `tồn`, `lead time`, `margin tối thiểu` trước khi làm AI tối ưu lợi nhuận ở mức nghiêm túc

Tài liệu này đủ để dev bắt đầu làm ngay mà không cần copy nguyên folder bot Zalo vào webshop.

Checklist theo dõi triển khai nằm tại:

- `C:\xampp\htdocs\ZenoxDigital\docs\AI_FEATURE_PHASES_CHECKLIST.md`

Các tài liệu đi kèm:

- `C:\xampp\htdocs\ZenoxDigital\docs\AI_CONTEXT_MAP.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_TASK_TEMPLATE.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_IMPLEMENTATION_STATUS.md`
