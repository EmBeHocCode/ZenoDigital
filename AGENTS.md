# AGENTS.md

## Chỉ thị bắt buộc cho mọi AI agent làm việc trong repo này

Trước khi phân tích, sửa code, thiết kế tính năng, hoặc đề xuất kiến trúc cho ZenoxDigital, agent phải đọc đầy đủ tài liệu sau:

- `C:\xampp\htdocs\ZenoxDigital\docs\README_AI.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_PORTING_GUIDE.md`

Nếu tác vụ liên quan đến AI, chatbot, trợ lý quản trị, tích hợp dữ liệu shop, hoặc tái sử dụng cơ chế từ bot hiện tại, agent bắt buộc phải dùng `AI_PORTING_GUIDE.md` làm nguồn định hướng chính trước khi đưa ra giải pháp.

Đặc biệt, agent phải đọc kỹ các phần sau trong `AI_PORTING_GUIDE.md`:

- `Lộ trình phase tính năng chi tiết`
- `Bộ prompt dùng ngay cho Codex dev webshop`

Và phải đối chiếu thêm checklist triển khai tại:

- `C:\xampp\htdocs\ZenoxDigital\docs\AI_FEATURE_PHASES_CHECKLIST.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_CONTEXT_MAP.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_TASK_TEMPLATE.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_IMPLEMENTATION_STATUS.md`

## Quy tắc thực hiện

1. Không copy nguyên khối bot Zalo vào webshop.
2. Chỉ tái sử dụng cơ chế phù hợp từ repo bot tham chiếu ở:
   - `E:\ZALO-BotChat\apps\bot\COMMAND_STATUS_REPORT.md`
   - `E:\ZALO-BotChat\apps\bot\src\infrastructure\api\chat.api.ts`
   - `E:\ZALO-BotChat\apps\bot\src\core\plugin-manager\module-manager.ts`
   - `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\ai.interface.ts`
   - `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\providers\gemini\gemini.provider.ts`
   - `E:\ZALO-BotChat\apps\bot\src\infrastructure\commands\commands.catalog.ts`
3. Mọi giải pháp phải phù hợp với stack hiện tại của ZenoxDigital:
   - PHP MVC
   - PDO
   - Bootstrap 5
   - JavaScript thuần ở `public/assets/js`
4. Không đề xuất logic AI kiểu Zalo-specific như:
   - group thread
   - tag người dùng
   - sticker, reaction, kick, mute
5. Nếu tính năng AI liên quan đến lợi nhuận, nhập hàng, khuyến mãi, agent phải kiểm tra dữ liệu hiện có trước.
6. Nếu thiếu dữ liệu như `giá vốn`, `tồn`, `capacity`, `lead time`, `margin tối thiểu`, agent phải nói rõ là còn thiếu và không được bịa ra kết luận lời/lỗ.

## Quy tắc bảo vệ file tài liệu

Các file tài liệu chuẩn sau được xem là file định hướng, không được tự ý chỉnh sửa nếu người dùng chưa yêu cầu cập nhật tài liệu:

- `C:\xampp\htdocs\ZenoxDigital\AGENTS.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\README_AI.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_PORTING_GUIDE.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_CONTEXT_MAP.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_TASK_TEMPLATE.md`

Các file được phép cập nhật thường xuyên theo tiến độ triển khai:

- `C:\xampp\htdocs\ZenoxDigital\docs\AI_IMPLEMENTATION_STATUS.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_FEATURE_PHASES_CHECKLIST.md`

Nếu hoàn thành một phase, agent bắt buộc phải cập nhật:

1. `AI_IMPLEMENTATION_STATUS.md`
2. `AI_FEATURE_PHASES_CHECKLIST.md`

## Ưu tiên kiến trúc

Khi triển khai AI trong repo này, ưu tiên theo thứ tự:

1. Chatbot CSKH cho khách truy cập web
2. Nhận feedback và tra cứu đơn hàng
3. AI Copilot cho admin dashboard
4. Gợi ý bán hàng và khuyến mãi có guardrail
5. Phân tích tồn kho, capacity, lợi nhuận khi dữ liệu đã đủ

## File trong webshop thường cần kiểm tra khi làm AI

- `C:\xampp\htdocs\ZenoxDigital\config\routes.php`
- `C:\xampp\htdocs\ZenoxDigital\config\config.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Core\Controller.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\main.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\admin.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\home\index.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\dashboard\index.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Product.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Order.php`
- `C:\xampp\htdocs\ZenoxDigital\database\schema.sql`

## Khi bắt đầu task AI

Agent nên tự xác nhận ngầm theo trình tự:

1. Đã đọc `docs/README_AI.md`
2. Đã đọc `AI_PORTING_GUIDE.md`
3. Đã đọc phần `Lộ trình phase tính năng chi tiết`
4. Đã đọc phần `Bộ prompt dùng ngay cho Codex dev webshop`
5. Đã đọc `AI_FEATURE_PHASES_CHECKLIST.md`
6. Đã đọc `AI_CONTEXT_MAP.md`
7. Đã đọc `AI_TASK_TEMPLATE.md`
8. Đã xác định đây là PHP MVC, không phải Node/Next
9. Đã phân biệt phần nào dùng tham chiếu từ bot, phần nào phải viết mới cho webshop
10. Đã kiểm tra schema và dữ liệu sẵn có trước khi đề xuất logic quản trị hoặc lợi nhuận

## Ghi chú

`agents/README.md` có nhắc tới file `../AGENTS.md`. File này chính là nguồn chỉ thị gốc cho agent trong repo ZenoxDigital.

## Xác minh repo tham chiếu

- Đã xác minh repo bot tham chiếu hiện tại tồn tại tại: `E:\ZALO-BotChat`
- Repo này chỉ dùng làm nguồn tham chiếu cơ chế và kiến trúc AI
- Không được copy nguyên logic Zalo-specific hoặc gateway Zalo sang webshop PHP MVC này
