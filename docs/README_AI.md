# Bộ Tài Liệu AI Cho ZenoxDigital

## Mục đích

Đây là file index cho toàn bộ tài liệu AI trong ZenoxDigital.

Dev hoặc AI agent khi làm tính năng AI cho webshop phải đọc theo đúng thứ tự bên dưới để tránh:

- copy nhầm logic Zalo-specific
- làm sai stack PHP MVC hiện tại
- thiếu guardrail cho lời lỗ, khuyến mãi, nhập hàng
- code xong nhưng không bám đúng roadmap

## Thứ tự đọc bắt buộc

### 1. Chỉ thị gốc của repo

- `C:\xampp\htdocs\ZenoxDigital\AGENTS.md`

Mục đích:

- biết rule chung của repo
- biết phải đọc những tài liệu nào tiếp theo

### 2. Hướng dẫn port AI chính

- `C:\xampp\htdocs\ZenoxDigital\docs\AI_PORTING_GUIDE.md`

Mục đích:

- hiểu kiến trúc tổng thể
- biết cái gì được bê cơ chế từ bot hiện tại
- biết cái gì không được copy nguyên
- biết phase nào nên làm trước

### 3. Checklist triển khai theo phase

- `C:\xampp\htdocs\ZenoxDigital\docs\AI_FEATURE_PHASES_CHECKLIST.md`

Mục đích:

- dùng để tick tiến độ
- dùng để xác định phase đã đủ điều kiện hoàn thành chưa
- dùng để ghi blocker dữ liệu và blocker kỹ thuật

### 4. Bản đồ context bot sang webshop

- `C:\xampp\htdocs\ZenoxDigital\docs\AI_CONTEXT_MAP.md`

Mục đích:

- dev biết file nào trong bot hiện tại là nguồn tham chiếu
- dev biết file nào trong webshop nên sửa tương ứng

### 5. Mẫu task để giao cho Codex dev

- `C:\xampp\htdocs\ZenoxDigital\docs\AI_TASK_TEMPLATE.md`

Mục đích:

- copy-paste prompt giao việc nhanh
- giao phase đúng format
- giao task bugfix hoặc review nhất quán

### 6. File theo dõi tình trạng triển khai

- `C:\xampp\htdocs\ZenoxDigital\docs\AI_IMPLEMENTATION_STATUS.md`

Mục đích:

- ghi phase đang làm
- ghi người làm
- ghi quyết định kỹ thuật
- ghi blocker

## Repo bot tham chiếu

Nguồn bot AI tham chiếu:

- `E:\ZALO-BotChat`

File tham chiếu quan trọng:

- `E:\ZALO-BotChat\apps\bot\COMMAND_STATUS_REPORT.md`
- `E:\ZALO-BotChat\apps\bot\src\app\app.module.ts`
- `E:\ZALO-BotChat\apps\bot\src\core\plugin-manager\module-manager.ts`
- `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\ai.interface.ts`
- `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\providers\gemini\gemini.provider.ts`
- `E:\ZALO-BotChat\apps\bot\src\infrastructure\api\chat.api.ts`
- `E:\ZALO-BotChat\apps\bot\src\infrastructure\commands\commands.catalog.ts`

## File hiện tại trong webshop thường sẽ đụng tới

- `C:\xampp\htdocs\ZenoxDigital\config\routes.php`
- `C:\xampp\htdocs\ZenoxDigital\config\config.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Core\Controller.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Product.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Order.php`
- `C:\xampp\htdocs\ZenoxDigital\database\schema.sql`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\main.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\admin.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\home\index.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\dashboard\index.php`

## Luồng làm việc chuẩn

1. Đọc `AGENTS.md`
2. Đọc `AI_PORTING_GUIDE.md`
3. Đọc `AI_FEATURE_PHASES_CHECKLIST.md`
4. Đọc `AI_CONTEXT_MAP.md`
5. Copy prompt phù hợp từ `AI_TASK_TEMPLATE.md`
6. Triển khai phase
7. Ghi lại tiến độ vào `AI_IMPLEMENTATION_STATUS.md`

## Ghi chú

Nếu task có liên quan:

- AI chat ngoài landing
- AI copilot cho admin
- feedback
- tra cứu đơn hàng
- khuyến mãi
- nhập hàng
- lời lỗ

thì phải dùng trọn bộ tài liệu này, không được bỏ qua.
