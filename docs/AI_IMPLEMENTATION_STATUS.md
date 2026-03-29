# Tình Trạng Triển Khai AI Cho ZenoxDigital

## Mục đích

File này dùng để ghi trạng thái triển khai thực tế của AI trong ZenoxDigital.

Mỗi khi bắt đầu hoặc kết thúc một phase, dev phải cập nhật file này.

## Thông tin chung

- Project: `C:\xampp\htdocs\ZenoxDigital`
- Repo bot tham chiếu: `E:\ZALO-BotChat`
- Bộ tài liệu chuẩn:
  - `C:\xampp\htdocs\ZenoxDigital\AGENTS.md`
  - `C:\xampp\htdocs\ZenoxDigital\docs\README_AI.md`
  - `C:\xampp\htdocs\ZenoxDigital\docs\AI_PORTING_GUIDE.md`
  - `C:\xampp\htdocs\ZenoxDigital\docs\AI_FEATURE_PHASES_CHECKLIST.md`
  - `C:\xampp\htdocs\ZenoxDigital\docs\AI_CONTEXT_MAP.md`
  - `C:\xampp\htdocs\ZenoxDigital\docs\AI_TASK_TEMPLATE.md`

## Trạng thái tổng quan

| Phase | Tên phase | Trạng thái | Người làm | Ghi chú ngắn |
|---|---|---|---|---|
| 0 | Chuẩn bị hạ tầng AI | DONE | Codex | Hạ tầng `AI bridge/context/guard` đã hoàn chỉnh, bridge runtime cục bộ đã cấu hình và verify trả `REAL BRIDGE` qua metadata |
| 1 | Chatbot CSKH cho landing | DONE | Codex | Widget chat nổi, route khách, metadata runtime và phản hồi hội thoại đã verify chạy qua `REAL BRIDGE` với 5 câu hỏi khác nhau |
| 2 | Feedback và hỗ trợ sau bán | DONE | Codex | Feedback lưu thật vào `customer_feedback`, actor context phân biệt `guest/customer/admin/management`, widget public đổi `conversation_mode` theo session backend, AI xác nhận qua `REAL BRIDGE`, admin xem lại được tại `/admin/feedback` |
| 3 | Tra cứu đơn hàng và tài khoản | DONE | Codex | Tra cứu đơn/ví/lịch sử mua đã nối vào chatbot bằng dữ liệu thật, ownership giữ ở backend và đã verify `REAL BRIDGE` cho guest + customer |
| 4 | Admin AI Copilot | DONE | Codex | Panel Copilot đã có progress state thật, direct-read cho intent admin rõ, freshness theo `version stamp + TTL ngắn`, phân vai `admin/staff` bằng backend permission và nay giữ được session chat DB-backed qua reload/reset |
| 5 | Gợi ý bán hàng và khuyến mãi | DONE | Codex | Copilot admin đã gợi ý push/upsell/homepage/coupon sơ bộ từ `Product + Order + Coupon`, ưu tiên Cloud/VPS và đã verify `REAL BRIDGE` |
| 6 | Phân tích hàng bán chậm và capacity | Chưa làm |  |  |
| 7 | Guardrail lợi nhuận, không lỗ | Chưa làm |  |  |
| 8 | Báo cáo điều hành và action plan | Chưa làm |  |  |

## Phiên làm việc hiện tại

- Phase đang làm: `Phase 4 enhancement - Admin AI Copilot multi-module mutation guardrails`
- Trạng thái: `DONE`
- Người làm: `Codex`
- Bắt đầu lúc: `2026-03-29`
- Mục tiêu buổi này: `Mở rộng Meow Copilot từ order-only sang quản trị đa module với permission, preview, confirm, execute, audit và module health guardrails`

## Checklist đọc tài liệu

- [x] Đã đọc `AGENTS.md`
- [x] Đã đọc `docs/README_AI.md`
- [x] Đã đọc `docs/AI_PORTING_GUIDE.md`
- [x] Đã đọc `docs/AI_FEATURE_PHASES_CHECKLIST.md`
- [x] Đã đọc `docs/AI_CONTEXT_MAP.md`
- [x] Đã dùng `docs/AI_TASK_TEMPLATE.md` hoặc prompt tương đương

## File đã tạo

- `C:\xampp\htdocs\ZenoxDigital\.env`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiInputNormalizerService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiPersonaService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiActorResolver.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiSalesRecommendationService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiProgressService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiDataFreshnessService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiIntentService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiModulePermissionService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiMutationDraftService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiMutationService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiSessionService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiOrderAccountSupportService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\AiChatSession.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\AiChatMessage.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\CustomerFeedback.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\CustomerFeedbackService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\FeedbackController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\feedback\index.php`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\js\admin-ai-panel.js`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\css\admin-ai-panel.css`

## File đã sửa

- `C:\xampp\htdocs\ZenoxDigital\database\schema.sql`
- `C:\xampp\htdocs\ZenoxDigital\app\Helpers\functions.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Core\Auth.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\User.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Coupon.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\AuthController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\ProfileController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\UserController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\DashboardController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Category.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Order.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Product.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiActorResolver.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiInputNormalizerService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiPersonaService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiBridgeService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiGuardService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiOrderAccountSupportService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiIntentService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiModulePermissionService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiMutationDraftService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiMutationService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\SchemaHealthService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiSessionService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\AiChatSession.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\AiChatMessage.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\config\ai_capabilities.php`
- `C:\xampp\htdocs\ZenoxDigital\config\routes.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\auth\register.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\profile\index.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\users\form.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\users\index.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\main.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\admin.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\home\index.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\products\show.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\ai_chat_widget.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\admin_ai_panel.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\admin_sidebar.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\admin_topbar.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\dashboard\index.php`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\css\ai-chat-widget.css`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\css\admin-ai-panel.css`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\js\ai-chat-widget.js`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\js\admin-ai-panel.js`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_IMPLEMENTATION_STATUS.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_FEATURE_PHASES_CHECKLIST.md`

## Migration hoặc thay đổi schema

- Đã thêm bảng `ai_chat_sessions`
  - lưu `session_key`, `user_id`, `channel`, `conversation_mode`, `actor_role`, `status`, `pending_request_id`, `created_at`, `last_activity_at`, `expires_at`, `updated_at`
- Đã thêm bảng `ai_chat_messages`
  - lưu `chat_session_id`, `role`, `content`, `meta_json`, `created_at`
- Đã thêm bảng `customer_feedback`
- Bảng này lưu `feedback_code`, `user_id`, `order_id`, `product_id`, `ai_session_id`, `feedback_type`, `sentiment`, `severity`, `needs_follow_up`, `message`, `status`
- Đã nâng bảng `users` với:
  - `gender ENUM('male','female','other','unknown') NOT NULL DEFAULT 'unknown'`
  - `birth_date DATE NULL`
- `User::ensureProfileMediaSchema()` hiện tự thêm 2 cột trên cho DB local nếu schema cũ chưa có
- Đã nâng seed role nền:
  - `roles` có thêm `staff` với `id=3`
  - `User::ensureBaseRoles()` tự đảm bảo DB local cũ có đủ `admin`, `user`, `staff`

## API hoặc route đã thêm

- `POST /ai/chat/customer`
- `POST /ai/feedback/customer`
- `POST /admin/ai/chat`
- `GET /admin/ai/session`
- `GET /admin/ai/summary`
- `GET /admin/ai/progress`
- `GET /admin/feedback`

## UI đã thêm

- Widget chat nổi cho khách ở `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\ai_chat_widget.php`
- Hero trigger mở chat ở `C:\xampp\htdocs\ZenoxDigital\app\Views\home\index.php`
- Asset frontend cho widget tại `C:\xampp\htdocs\ZenoxDigital\public\assets\js\ai-chat-widget.js` và `C:\xampp\htdocs\ZenoxDigital\public\assets\css\ai-chat-widget.css`
- Form feedback nhỏ ngay trong widget chat để khách gửi góp ý/hỗ trợ sau bán
- Trang admin xem feedback tại `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\feedback\index.php`
- Panel `Meow Copilot` cho backoffice tại `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\admin_ai_panel.php`
- Asset admin copilot tại `C:\xampp\htdocs\ZenoxDigital\public\assets\js\admin-ai-panel.js` và `C:\xampp\htdocs\ZenoxDigital\public\assets\css\admin-ai-panel.css`
- Admin copilot nay tự restore phiên chat gần nhất sau reload hoặc đóng/mở popup, và có session status nhẹ trong meta bar
- Dashboard admin đã render theo quyền backend thực:
  - `admin` thấy đủ KPI nhạy cảm
  - `staff` chỉ thấy dashboard vận hành + Copilot với dữ liệu giới hạn

## Dữ liệu đang dùng cho AI

- Repo đích: `C:\xampp\htdocs\ZenoxDigital`
- Repo bot tham chiếu: `E:\ZALO-BotChat`
- Bộ docs AI nội bộ trong `C:\xampp\htdocs\ZenoxDigital\docs`

## Context phân vai hiện tại

- `auth_state`
- `actor_type`
- `actor_role`
- `role_group`
- `actor_name`
- `actor_short_name`
- `actor_id`
- `actor_gender`
- `actor_birth_date`
- `actor_age`
- `role_name`
- `is_admin`
- `is_staff`
- `is_management_role`
- `is_backoffice_actor`
- `is_customer`
- `is_guest`
- `is_authenticated`
- `trusted_identity`
- `safe_addressing`
- `support_scope`
- `conversation_mode`
- `resolution_reason` khi rơi vào `unknown`

## Rule phân quyền Phase 3

- `guest`: chỉ được tra cứu đơn khi cung cấp đủ `mã đơn + email đặt hàng`; nếu thiếu hoặc sai xác minh thì chatbot chỉ trả lời an toàn và không hé lộ đơn có tồn tại hay không
- `customer`: chỉ được đọc đơn, lịch sử mua và ví của chính tài khoản đang đăng nhập thông qua `user_id` từ session/auth backend
- `admin/backoffice`: không đi qua nhánh Phase 3 customer lookup; widget public nếu đăng nhập admin sẽ chuyển sang `admin_copilot`
- Mọi rule xác thực đều nằm ở backend service/model, không tin role hay owner từ client

## Rule phân quyền Phase 4

- `customer`: không vào được dashboard admin và không dùng được endpoint `admin/ai/*`
- `admin`: vào được dashboard admin, dùng được `Meow Copilot`, xem KPI tài chính/người dùng/rank và các khu quản trị sâu
- `staff`: có role thật trong DB (`roles.id = 3`), vào được dashboard admin và dùng được `Meow Copilot`, nhưng chỉ trong phạm vi vận hành:
  - được xem snapshot/tóm tắt về `đơn hàng`, `sản phẩm`, `coupon`, `feedback`
  - không được xem `doanh thu`, `tăng trưởng user`, `rank`, `SQL Manager`, `settings`, `audit`
  - nếu hỏi vượt quyền trong Copilot, backend trả guardrail refusal thay vì để bridge suy diễn
- `management role` tương lai:
  - pipeline `AiActorResolver` + `AiPersonaService` + `AiContextBuilder` đã mở sẵn
  - khi role này được thêm vào DB/policy thật ở phase sau, Copilot dùng lại cùng cơ chế mà không phải viết lại từ đầu
- Mutation matrix Phase 4 hiện tại:
  - `products`: read + create/update/delete qua preview/confirm
  - `categories`: read + create/update/delete qua preview/confirm
  - `orders`: read + update status + delete mềm qua preview/confirm
  - `coupons`: read + create/update status/delete qua preview/confirm
  - `feedback`: read + update workflow qua preview/confirm
  - `users`: read + khóa/mở + đổi role + delete mềm qua preview/confirm
  - `settings`: read + preview-only cho safe settings
  - `rank`: read + preview-only cho rank config
  - `payments`: read-only
  - `audit`: read-only
  - `sql manager`: chỉ read/tóm tắt health, không mở đường write

## Prompt/persona webshop hiện tại

- Tên bot hiển thị chính: `Meow`
- Persona nền: `Nguyễn Thanh Hà`
- Kiến trúc prompt hiện tại theo nhiều lớp:
  - `identity layer`: khóa cứng bot name/persona và thương hiệu `ZenoxDigital`
  - `voice & naturalness layer`: giảm cảm giác template, tránh giọng máy móc
  - `language understanding layer`: ưu tiên đọc `original_text`, `normalized_text`, `intent_guess`, `context_hint`, `recent_messages`
  - `conversation mode layer`: tách `customer_support`, `admin_copilot`, `staff_support`, `management_support`
  - `actor rules layer`: bám theo danh tính resolve từ backend session/auth thật
  - `context & guardrails layer`: không bịa dữ liệu và không dùng Zalo-specific behavior
  - `interaction layer`: riêng cho feedback capture và follow-up an toàn
- Cơ chế này lấy cảm hứng từ cách bot Zalo tổ chức prompt nhiều lớp, nhưng đã rewrite cho webshop PHP MVC và bỏ toàn bộ behavior Zalo-specific
- Đã bổ sung `AiInputNormalizerService` để chuẩn hóa:
  - không dấu
  - viết tắt
  - chat tắt
  - teencode/gen Z
  - phản ứng ngắn theo ngữ cảnh hội thoại gần nhất

## Dữ liệu còn thiếu

- Với các phase lợi nhuận, nhập hàng, khuyến mãi: schema hiện tại vẫn thiếu các cột như `cost_price`, `stock_qty`, `capacity_limit`, `lead_time_days`, `min_margin_percent`
- Phase 5 vì vậy chỉ triển khai được ở mức `gợi ý sơ bộ` cho push/upsell/coupon pilot/homepage
- `AiGuardService` hiện chỉ chặn cứng nhánh `capacity/tồn kho`; còn câu hỏi `khuyến mãi/upsell/giá vốn` được phép trả lời nhưng phải nói rõ giới hạn dữ liệu

## Trạng thái runtime AI chat

- Phase status hiện tại: `DONE`
- Chatbot runtime hiện tại: `REAL BRIDGE`
- FALLBACK: `Chỉ còn là cơ chế dự phòng`
- Provider runtime vừa verify: `zia-bot-bridge`
- Mode runtime vừa verify: `real_bridge`
- Source runtime vừa verify: `ai-bridge`

## Guardrail đã áp dụng

- Không copy nguyên logic Zalo-specific từ repo bot sang webshop
- Chỉ tái sử dụng cơ chế phù hợp và phải rewrite theo PHP MVC hiện tại
- Nếu task sau liên quan lợi nhuận, nhập hàng, khuyến mãi thì phải kiểm tra schema trước và không được bịa dữ liệu thiếu
- Không lộ `AI_BRIDGE_KEY` ra client; mọi gọi AI đều đi qua controller/service phía server
- Actor context được resolve từ backend session/auth thật qua `AiActorResolver`, không tin role/name do client tự gửi
- Đã tách rõ `guest`, `customer`, `admin`, `staff`, `management` theo session backend và có `conversation_mode` để cùng một widget public đổi đúng giọng điệu
- `conversation_mode` hiện tách rõ:
  - `customer_support`
  - `admin_copilot`
  - `staff_support`
  - `management_support`
- Nếu chưa xác định chắc danh tính thì AI phải fallback về ngữ cảnh an toàn `unknown`
- `sessionId` AI được bind về server-side qua `AiSessionManager`, không còn tin `session_id` do client tự chọn
- Có rate limit riêng cho `POST /ai/chat/customer` và các endpoint admin AI, trả JSON `429` kèm `retry_after`
- Admin AI sẽ trả lời `guardrail refusal` khi hỏi `capacity/tồn kho`
- Với câu hỏi `khuyến mãi`, `upsell`, `homepage`, `giá vốn`, Copilot sẽ trả về gợi ý sơ bộ dựa trên dữ liệu thật thay vì block cứng, đồng thời nêu rõ các cột còn thiếu
- Đã siết detector guardrail để không chặn nhầm câu hỏi quản trị vô hại chỉ vì substring ngắn như `lời`
- Response metadata nay trả rõ `provider`, `is_fallback`, `mode`, `source` để phân biệt `REAL BRIDGE`, `FALLBACK`, `GUARDRAIL`
- Widget public không còn mặc định chào admin như khách mua hàng; nếu session backend là backoffice thì chatbot sẽ dùng mode phù hợp (`admin_copilot`, `staff_support`, `management_support`)
- Badge, launcher, welcome text và quick suggestions của widget đã đồng bộ sang persona `Meow`
- Widget và controller nay truyền thêm `recent_messages` để bot hiểu các câu ngắn kiểu `hog á`, `z hả`, `okk` theo mạch chat gần nhất
- Prompt bridge đã được nén/cô đọng để luôn nằm dưới giới hạn `message <= 8000` của web-chat bridge, tránh lỗi `Dữ liệu không hợp lệ`
- Phase 3 chỉ trả dữ liệu tài khoản/đơn hàng khi backend xác minh ownership thành công; guest không thể dò đơn chỉ bằng mã đơn đoán mò
- `AiOrderAccountSupportService` tạo `structured_reply` từ dữ liệu thật rồi mới để bridge giải thích/follow-up, nên phần dữ liệu cốt lõi không bị bịa
- Chatbot không được đoán giới tính từ tên/email/avatar; `safe_addressing` giờ chỉ sinh từ dữ liệu backend tin cậy (`gender`, `birth_date`, `age`)
- Guest, Google login chưa bổ sung hồ sơ, hoặc user có `gender=unknown/other` đều mặc định quay về `bạn/mình`
- Mutation admin AI không đi qua SQL write tự do; mọi thao tác ghi đều tái dùng model/business path hiện có
- Mọi mutation qua AI đều đi theo vòng đời `preview -> confirm -> execute`, pending draft bị khóa theo đúng `session_key + actor_id`
- Reset session admin AI sẽ xóa luôn pending mutation draft để không confirm nhầm ở phiên mới
- `AdminAiModulePermissionService` map quyền theo từng module; `staff` không mặc định có quyền ghi chỉ vì vào được Copilot
- `ModuleHealthGuardService` tiếp tục là lớp chặn write/read khi module unhealthy; AI trả lời có kiểm soát thay vì rơi lỗi SQL thô
- `AdminAiMutationService` ghi audit bắt buộc với `source=admin_ai`, có `module`, `action`, `result`, `before`, `after` nếu phù hợp
- `settings` và `rank` hiện cố ý dừng ở `preview_only`; `payments`, `audit`, `sql` hiện cố ý giữ `read_only`
- Chỉ khi backend có dữ liệu đủ tin cậy thì AI mới dùng `anh/chị/em`
- Hệ thống role vẫn mở cho `staff` và `management role` tương lai; admin user management lấy role động từ DB nên không phải viết lại từ đầu khi thêm role mới
- Đã tách quyền `backoffice dashboard/AI data scope` khỏi quyền vào từng trang admin chi tiết:
  - `staff` có `dashboard + AI copilot`
  - `staff` không bị mặc định có quyền `settings/sql/audit/users`
- `Admin AI Copilot` dùng dữ liệu thật từ `Order`, `Product`, `Coupon`, `CustomerFeedback`, dashboard stats và scope backend
- `Admin AI Copilot` có guardrail backend riêng cho:
  - câu hỏi `capacity/tồn kho` khi schema chưa đủ dữ liệu
  - câu hỏi vượt phạm vi `staff` như doanh thu, user metrics, rank, system control
- `Admin AI Copilot` nay có `direct_admin_read` cho các intent admin rõ như:
  - `xem đơn pending`
  - `doanh thu hôm nay`
  - `feedback mới`
  - `coupon hiện tại`
  - `sản phẩm bán chạy`
- Với câu hỏi mơ hồ kiểu `xem đơn hàng`, backend chỉ hỏi lại đúng 1 câu ngắn thay vì lặp xác nhận
- `POST /admin/ai/chat` đã nhả session lock sớm để `GET /admin/ai/progress` poll được trong lúc request còn xử lý
- Progress state thật của admin copilot hiện có:
  - `Bot đang kiểm dữ liệu...`
  - `Bot đang thống kê và gửi đến...`
  - sau đó mới trả kết quả hoặc lỗi rõ
- Admin copilot nay có session persistence thật ở backend:
  - session gắn với `user_id`, `actor_role`, `conversation_mode`
  - lifecycle có `active`, `reset`, `expired`, `closed`
  - lịch sử message được lưu trong `ai_chat_messages` để restore sau reload
  - reset tạo `session_key` mới thật sự, không chỉ xóa state ở frontend
  - session dùng TTL mặc định `43200` giây và tự tạo phiên mới khi hết hạn
  - bot giữ ngữ cảnh hội thoại bằng `session_key` bridge + cửa sổ message gần nhất từ DB
- Freshness của admin context hiện dùng:
  - `version stamp` theo các bảng `products`, `categories`, `orders`, `order_items`, `coupons`, `users`, `customer_feedback`, `settings`
  - cache snapshot ngắn hạn
  - tự invalidated khi fingerprint dữ liệu đổi
- Đã thêm `AiSalesRecommendationService` để gom tín hiệu thật từ `products + orders + coupons` rồi đưa vào `AiContextBuilder`
- Gợi ý Phase 5 hiện dựa trên:
  - danh mục active thật
  - order hợp lệ `paid/processing/completed`
  - coupon hiện có/đang trống
  - ladder giá và cấu hình cloud đọc từ `specs`
- Đã sửa lại một số aggregate để không lẫn `pending` vào số bán/revenue (`Product::topSelling`, `Category::topCategories`, `Order::revenueByMonth`)
- `Admin AI Copilot` UI đã hiển thị khác nhau theo quyền:
  - `admin` thấy đủ snapshot + chỉ số nhạy cảm
  - `staff` thấy dashboard vận hành giới hạn và không còn link vượt quyền

## Blocker hiện tại

- Không còn blocker kỹ thuật cho multi-module mutation guardrails của Phase 4
- Chưa chạy browser automation để click đủ toàn bộ case reload/đóng mở popup; hiện đã verify lifecycle bằng smoke backend + lint + wiring controller/js
- Local fallback vẫn tồn tại như đường dự phòng kỹ thuật, nhưng `Admin AI Copilot` runtime đã verify chạy `REAL BRIDGE` cho bộ câu hỏi Phase 5
- Phần `capacity/tồn kho` vẫn chưa thể phân tích thật do schema thiếu `stock_qty`, `capacity_limit`, `capacity_used`
- `settings` và `rank` đang là preview-only theo chủ đích guardrail, không phải lỗi thiếu path kỹ thuật
- `payments`, `audit`, `sql manager` đang đọc/tóm tắt an toàn; chưa mở write path cho AI theo chủ đích policy

## Quyết định kỹ thuật quan trọng

- ZenoxDigital là repo đích triển khai
- `E:\ZALO-BotChat` là repo tham chiếu hợp lệ đã được xác minh tồn tại
- Đã chọn kiến trúc `AI bridge service` thay vì copy/shared core trực tiếp từ repo bot
- Dùng `AiContextBuilder` để đóng gói context webshop và `AiGuardService` để khóa guardrail dữ liệu
- Cho phép `local fallback` để test end-to-end khi bridge thật chưa được cấu hình
- Dùng `AiSessionManager` để quản lý session AI theo scope `customer/admin` ở phía server
- Tách rate-limit JSON API qua `Controller::consumeRateLimit()` để controller AI không phải redirect như flow login
- Không coi local fallback là điều kiện hoàn thành phase AI chat; fallback chỉ được xem là đường dự phòng kỹ thuật

## Câu hỏi mẫu đã test

- `Tư vấn giúp tôi gói VPS phù hợp cho web bán hàng`
  - Kết quả: bridge hỏi thêm traffic/nền tảng và gợi ý `VPS Basic 2vCPU`
- `Shop có hỗ trợ server game không?`
  - Kết quả: bridge dẫn thẳng tới sản phẩm `Game Server Minecraft`
- `Sau khi thanh toán bao lâu nhận dịch vụ?`
  - Kết quả: bridge trả lời thời gian bàn giao và gợi ý nhóm sản phẩm liên quan
- `Tôi muốn nạp số dư rồi mua hàng thì làm thế nào?`
  - Kết quả: bridge giải thích hướng mua/nạp thay vì trả mẫu text fallback cứng
- `Tôi đang phân vân giữa VPS và server game, nên chọn gì nếu mới bắt đầu?`
  - Kết quả: bridge so sánh hướng dùng VPS với server game theo nhu cầu mới bắt đầu
- `Gói này phù hợp nhu cầu nào?` với `product_id=1`
  - Kết quả: bridge bám theo context sản phẩm `VPS Basic 2vCPU`
- `Mình muốn tra cứu đơn ORD-20260323-002 với email user@local.test`
  - Kết quả: guest đã xác minh đúng đơn thật, bridge trả `REAL BRIDGE` và hiển thị mã đơn, trạng thái, thời gian, tổng tiền
- `Đơn gần đây của tôi thế nào?`
  - Kết quả: customer đăng nhập chỉ xem được đơn gần nhất của chính mình qua `user_id` backend
- `Ví của tôi còn bao nhiêu?`
  - Kết quả: chatbot đọc số dư/tổng nạp/đã chi từ dữ liệu ví thật của tài khoản hiện tại
- `Guest chưa đăng nhập`
  - Kết quả: AI luôn dùng ngữ cảnh an toàn `bạn/mình`
- `User đăng ký thường có gender/birth_date`
  - Kết quả: profile/admin lưu thật vào `users`, AI context có `actor_gender`, `actor_birth_date`, `actor_age`
- `Google login chưa bổ sung hồ sơ`
  - Kết quả: backend để `gender=unknown`, `birth_date=null`, nên AI vẫn dùng `bạn/mình`
- `Admin`
  - Kết quả: vẫn giữ mode quản trị; nếu dữ liệu nhân xưng chưa đủ thì dùng `bạn/mình`, không đoán bừa thành anh/chị
- `Admin dashboard + Meow Copilot`
  - Kết quả: `GET /admin/ai/summary` trả JSON với `backoffice_scope=Admin toàn quyền`
  - `POST /admin/ai/chat` trả `provider=zia-bot-bridge`, `is_fallback=false`, `mode=real_bridge`, `source=ai-bridge`
- `Tuần này nên đẩy gói nào?`
  - Kết quả: bridge trả `REAL BRIDGE`, ưu tiên `VPS Basic 2vCPU` làm gói mồi cloud
- `Nên khuyến mãi gì cho nhóm Cloud VPS?`
  - Kết quả: bridge trả `REAL BRIDGE`, gợi ý coupon pilot 5% trong 7 ngày cho traffic cloud mới và nhắc rõ đây là gợi ý sơ bộ
- `Có gói nào phù hợp làm upsell không?`
  - Kết quả: bridge trả `REAL BRIDGE`, nêu được các ladder như `Cloud Server Starter 1 -> VPS Ryzen Basic`, `VPS Basic 2vCPU -> Cloud Server Business 4`
- `Sản phẩm nào nên đưa lên homepage?`
  - Kết quả: bridge trả `REAL BRIDGE`, ưu tiên `VPS Basic 2vCPU` + `Cloud Server Starter 1`
- `Có coupon nào đang nên bật hoặc nên tắt không?`
  - Kết quả: bridge trả `REAL BRIDGE`, xác nhận bảng coupon hiện trống nên chưa có coupon active/inactive để bật-tắt
- `Nếu chưa có giá vốn thì hiện tại em gợi ý được tới mức nào?`
  - Kết quả: bridge trả `REAL BRIDGE`, nói rõ chỉ dừng ở push/homepage/upsell/coupon pilot và không kết luận lời/lỗ
- `Nhóm sản phẩm nào đang phù hợp để chạy ưu đãi sơ bộ?`
  - Kết quả: bridge trả `REAL BRIDGE`, xác nhận `Cloud/VPS` là nhóm phù hợp nhất để test ưu đãi sơ bộ
- `Staff dashboard + Meow Copilot`
  - Kết quả: `GET /admin/ai/summary` trả JSON với `backoffice_scope=Staff vận hành giới hạn`
  - `POST /admin/ai/chat` cho câu hỏi vận hành trả `provider=zia-bot-bridge`, `is_fallback=false`, `mode=real_bridge`, `conversation_mode=staff_support`
  - `POST /admin/ai/chat` cho câu hỏi `Tóm tắt doanh thu hôm nay` bị backend guardrail chặn đúng quyền staff
- `Kiểm tra giúp tôi đơn ORD-DOES-NOT-EXIST`
  - Kết quả: trả lời an toàn, không lộ dữ liệu đơn hàng của người khác

## Kết quả verify

- Đã đọc đầy đủ 7 tài liệu theo thứ tự người dùng yêu cầu
- Đã xác minh đường dẫn repo bot tham chiếu `E:\ZALO-BotChat` tồn tại trên máy
- Đã đọc thêm các file tham chiếu Phase 1-2 trong repo bot:
  - `E:\ZALO-BotChat\apps\bot\COMMAND_STATUS_REPORT.md`
  - `E:\ZALO-BotChat\apps\bot\src\infrastructure\api\chat.api.ts`
  - `E:\ZALO-BotChat\apps\bot\src\core\plugin-manager\module-manager.ts`
  - `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\ai.interface.ts`
  - `E:\ZALO-BotChat\apps\bot\src\infrastructure\ai\providers\gemini\gemini.provider.ts`
  - `E:\ZALO-BotChat\apps\bot\src\infrastructure\commands\commands.catalog.ts`
- Đã xác minh bridge tham chiếu đang chạy local:
  - `GET http://127.0.0.1:10001/health` trả `status=ok`
- `POST http://127.0.0.1:10001/api/web-chat` trả phản hồi hội thoại thật khi gọi bằng bearer token server-side
- Đã debug nguyên nhân chatbot từng rơi về fallback trong lượt này:
  - Bridge `web-chat` chỉ nhận `message` tối đa `8000` ký tự
  - Prompt nhiều lớp + context JSON dài đã làm payload vượt ngưỡng
  - Đã nén prompt/context/meta để đưa đường chính quay lại `REAL BRIDGE`
- `php -l` pass cho:
  - `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\DashboardController.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\admin.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\admin_topbar.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\admin_ai_panel.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\dashboard\index.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Services\AiInputNormalizerService.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Models\CustomerFeedback.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Services\CustomerFeedbackService.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Services\AiBridgeService.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Controllers\AiController.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\FeedbackController.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\feedback\index.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\ai_chat_widget.php`
  - `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\admin_sidebar.php`
  - `C:\xampp\htdocs\ZenoxDigital\config\routes.php`
- `node --check` pass cho `C:\xampp\htdocs\ZenoxDigital\public\assets\js\ai-chat-widget.js`
- `node --check` pass cho `C:\xampp\htdocs\ZenoxDigital\public\assets\js\admin-ai-panel.js`
- Smoke test `POST /ai/feedback/customer` qua chính webshop endpoint xác nhận:
  - feedback tiêu cực trên landing được lưu DB và trả metadata `provider=zia-bot-bridge`, `is_fallback=false`, `mode=real_bridge`, `source=ai-bridge`
  - feedback tích cực ở `products/show/1` lưu đúng `product_id=1` và trả phản hồi hội thoại tự nhiên qua bridge thật
  - feedback cần follow-up ở `products/show/3` trả lời hỗ trợ thêm nhưng không còn bịa “tạo ticket”
- Query DB `customer_feedback` đã thấy dữ liệu thật được ghi nhận
- Đăng nhập admin thành công và `GET /admin/feedback` hiển thị được mã feedback mới ngay trên giao diện
- Đã sửa bug scope để widget trên landing không còn lấy nhầm `$product` từ vòng lặp và gắn sai `product_id`
- Đã verify lại `guest` qua `POST /ai/chat/customer`:
  - Bridge trả `is_fallback=false`
  - Chatbot tự nhận đây là khách chưa đăng nhập và dùng xưng hô trung tính, không xưng tên
- Đã verify lại `customer` bằng session user thật:
  - Đăng nhập user test `Tran Minh Kha`
  - `POST /ai/chat/customer` gọi đúng tên thật phía server và tự nhận `customer`
  - `POST /ai/feedback/customer` lưu feedback thật với `user_id=8`
- Đã verify lại `guest feedback`:
  - `POST /ai/feedback/customer` vẫn lưu được feedback
  - Query DB xác nhận `user_id=NULL`, không nhận nhầm danh tính
- Đã verify lại `admin`:
  - Đăng nhập `admin@local.test`
  - `POST /admin/ai/chat` nhận diện đúng ngữ cảnh quản trị và trả metadata `provider=zia-bot-bridge`, `is_fallback=false`, `mode=real_bridge`, `source=ai-bridge`
- Đã verify lại mandatory slang cases qua chính webshop endpoint với `REAL BRIDGE`:
  - `chào bé`
  - `hog á` (có recent context)
  - `gói nào ổn z`
  - `còn hàng ko`
  - `ship lẹ hog`
  - `e mún góp ý`
  - `ad ơi check đơn e`
  - `gói rẻ mà ổn có ko`
  - `tư vấn a con vps chạy web bán hàng`
- Đã verify `admin` đăng nhập nhưng chat ngay trên widget public:
  - metadata trả `actor_type=admin`, `conversation_mode=admin_copilot`
  - reply giữ đúng giọng trợ lý quản trị, không chuyển sang CSKH bán hàng
- Đã triển khai thêm role-aware prompt/welcome/quick suggestions cho widget public:
  - `guest` giữ giọng CSKH trung tính
  - `customer` đã đăng nhập có thể được gọi theo `safe_addressing` từ backend
  - `admin` dùng `admin_copilot`
  - `staff` dùng `staff_support`
  - `management` dùng `management_support`
- Đã học cơ chế prompt nhiều lớp từ repo bot tham chiếu:
  - đọc `prompts.ts`
  - đọc `character.ts`
  - đọc `gemini.provider.ts`
  - chỉ giữ cơ chế persona/naturalness/prompt layering, không copy reaction/sticker/quote/group behavior
- Verify lượt này:
  - `guest` qua `POST /ai/chat/customer` trả `provider=zia-bot-bridge`, `is_fallback=false`, `mode=real_bridge`, `source=ai-bridge`
  - reply đã dùng thương hiệu `ZenoxDigital` và tên bot `Meow`
  - `admin` đăng nhập rồi chat ngay trên public widget vẫn được nhận diện `admin_copilot` và trả lời theo giọng copilot nội bộ
- Verify Phase 3 lượt này:
  - `guest` qua chính webshop endpoint `POST /ai/chat/customer` với `_csrf` + session cookie:
    - nếu thiếu `mã đơn + email` thì bot yêu cầu xác minh an toàn
    - nếu gửi `ORD-20260323-002 + user@local.test` thì bridge trả `REAL BRIDGE` và hiện dữ liệu đơn thật
  - `customer` qua backend session-auth script dùng user thật `id=2`:
    - `Đơn gần đây của tôi thế nào?` => chỉ ra đúng đơn gần nhất thuộc tài khoản đó
    - `Ví của tôi còn bao nhiêu?` => trả đúng số dư/tổng nạp/đã chi từ DB
    - `Kiểm tra giúp tôi đơn ORD-DOES-NOT-EXIST` => không lộ dữ liệu đơn người khác
  - runtime verify của Phase 3:
    - `provider=zia-bot-bridge`
    - `is_fallback=false`
    - `mode=real_bridge`
    - `source=ai-bridge`
- Verify Phase 4 lượt này:
  - đăng nhập `admin@local.test / 123456`
  - `GET /admin/ai/summary` trả `200`, scope `Admin toàn quyền`, stats thật từ dashboard
  - `POST /admin/ai/chat` với câu `Tóm tắt nhanh dashboard hiện tại` trả:
    - `provider=zia-bot-bridge`
    - `is_fallback=false`
    - `mode=real_bridge`
    - `source=ai-bridge`
    - `actor_type=admin`
    - `conversation_mode=admin_copilot`
  - tạo user local `staff.phase4@local.test` với `role=staff` để verify runtime Phase 4
  - đăng nhập `staff.phase4@local.test / 123456`
  - `GET /admin/ai/summary` trả `200`, scope `Staff vận hành giới hạn`, `can_view_finance=false`, `can_view_orders=true`
  - `POST /admin/ai/chat` với câu `Xem nhanh đơn chờ xử lý hiện tại` trả:
    - `provider=zia-bot-bridge`
    - `is_fallback=false`
    - `mode=real_bridge`
    - `actor_type=staff`
    - `conversation_mode=staff_support`
  - `POST /admin/ai/chat` với câu `Tóm tắt doanh thu hôm nay` trả `guardrail`, bị chặn đúng quyền staff
  - `GET /admin` bằng session staff trả `200`, có `Meow Copilot`, không còn render link `admin/orders` và `admin/settings` trong dashboard HTML staff
- Verify kỹ thuật lượt này:
  - `php -l` pass cho `AiPersonaService`, `AiActorResolver`, `AiContextBuilder`, `AiBridgeService`, `AiController`, `Admin\AiController`, `CustomerFeedbackService`, `ai_chat_widget.php`, `home/index.php`
  - `node --check` pass cho `public/assets/js/ai-chat-widget.js`
- Verify Phase 4 session persistence lượt này:
  - smoke test backend `AdminAiSessionService`:
    - restore lại đúng `session_key` cũ sau khi append vài message
    - user khác nhận `session_key` khác, không lẫn dữ liệu
    - ép `expires_at` về quá khứ thì lần restore sau tạo session mới với `resume_reason=expired`
  - `php -l` pass cho:
    - `app/Controllers/Admin/AiController.php`
    - `app/Services/AdminAiSessionService.php`
    - `app/Models/AiChatSession.php`
    - `app/Models/AiChatMessage.php`
  - `node --check` pass cho `public/assets/js/admin-ai-panel.js`
  - admin panel đã đổi sang dùng `GET /admin/ai/session` để hydrate lịch sử từ backend sau reload
- Verify Phase 5 lượt này:
  - `AiSalesRecommendationService` build thành công từ DB thật và xác nhận:
    - `Cloud/VPS` chiếm `75%` catalog active (`9/12` SKU)
    - `VPS Basic 2vCPU` là gói cloud có tín hiệu bán thật mạnh nhất trong cửa sổ `30 ngày`
    - `coupons` hiện đang trống
  - Prompt bridge sau khi nén lại còn khoảng `5881` ký tự, không vượt giới hạn `<= 8000` của web-chat bridge
  - Test bộ câu hỏi bắt buộc Phase 5 qua `AiBridgeService` với ngữ cảnh admin:
    - `Tuần này nên đẩy gói nào?`
    - `Nên khuyến mãi gì cho nhóm Cloud VPS?`
    - `Có gói nào phù hợp làm upsell không?`
    - `Sản phẩm nào nên đưa lên homepage?`
    - `Có coupon nào đang nên bật hoặc nên tắt không?`
    - `Nếu chưa có giá vốn thì hiện tại em gợi ý được tới mức nào?`
    - `Nhóm sản phẩm nào đang phù hợp để chạy ưu đãi sơ bộ?`
  - Kết quả cả 7 câu đều trả:
    - `provider=zia-bot-bridge`
    - `is_fallback=false`
    - `mode=real_bridge`
    - `source=ai-bridge`
  - Guardrail capacity vẫn chặn đúng với câu kiểu `Có gói cloud nào sắp đầy capacity không?`
- Verify Admin Copilot progress/freshness lượt này:
  - `xem đơn pending` => `mode=direct_admin_read`, không hỏi lại
  - `doanh thu hôm nay` => `mode=direct_admin_read`, không hỏi lại
  - `feedback mới có gì` => `mode=direct_admin_read`, không hỏi lại
  - `coupon hiện tại thế nào` => `mode=direct_admin_read`, không hỏi lại
  - `sản phẩm nào đang bán chạy` => `mode=direct_admin_read`, không hỏi lại
  - `xem đơn hàng` => `mode=clarification`, chỉ hỏi đúng 1 câu ngắn
  - `xem đơn pending hôm nay` => chạy thẳng, không hỏi lại
  - query mở `Tuần này nên đẩy gói nào?` vẫn đi `REAL BRIDGE`
  - poll `GET /admin/ai/progress` thấy history step: `Bot đang kiểm dữ liệu...` -> `Bot đang thống kê và gửi đến...` -> `Bot đã trả kết quả.`
  - `POST /admin/ai/chat` với tin nhắn rỗng trả `422` và thông báo lỗi rõ
  - `AdminAiDataFreshnessService` đổi fingerprint thật khi mutate tạm thời `products/users/coupons` trong transaction và rollback về fingerprint cũ
  - `AiContextBuilder` cache hit ở lượt lặp, nhưng tự rebuild khi fingerprint dữ liệu đổi
- Verify multi-module mutation guardrails lượt này:
  - service-level smoke theo transaction, không làm bẩn DB:
    - `sửa giá sản phẩm Cloud Server Starter 1 thành 321000` => `mutation_preview` -> `product_updated`
    - `thêm sản phẩm AI Smoke Product giá 123000 vào danh mục VPS / Cloud Server` => `mutation_preview` -> `product_created`
    - `sửa mô tả sản phẩm Cloud Server Starter 1 thành ...` => `mutation_preview` -> `product_updated`
    - `thêm danh mục AI Smoke Category` => `mutation_preview` -> `category_created`
    - `tạo coupon SMOKEAI73 giảm 7%` => `mutation_preview` -> `coupon_created`
    - `khóa user user@local.test` => `mutation_preview` -> `user_updated`
    - `chuyển đơn ORD-20260323-001 sang đang xử lý` => `mutation_preview` -> `order_status_updated`
    - `xóa đơn ORD-20260323-001` => `mutation_preview` -> `order_deleted`
    - `feedback FDB-... đã xử lý` => `mutation_preview` -> `feedback_updated`
  - service-level read scope:
    - `xem giao dịch ví`, `xem cấu hình rank`, `xem cài đặt hệ thống`, `xem audit log`, `xem sql manager` đều trả `direct_admin_read`
  - service-level guardrails:
    - role `staff` thử `tạo coupon STAFFBLOCK73 giảm 5%` => `mutation_blocked`
    - inject module unhealthy cho `coupons` => `mutation_blocked`, message `Module Coupon đang tạm khóa để bảo vệ dữ liệu.`
    - `bật maintenance mode` => `mutation_preview_only`
    - `đổi rank rare điểm thành 2500` => `mutation_preview_only`
  - HTTP thật qua `POST /admin/ai/chat`:
    - `thêm sản phẩm HTTP Smoke Product giá 111000 vào danh mục VPS / Cloud Server` => `mode=mutation_preview`, `requires_confirmation=true`
    - `chuyển đơn ORD-20260323-001 sang đang xử lý` => `mode=mutation_preview`
    - `xác nhận` => `mode=direct_admin_action`, `mutation.type=order_status_updated`
    - đã gọi tiếp preview + confirm để đưa `ORD-20260323-001` về lại `paid`

## Rủi ro còn lại

- Nếu vào Phase 6-7 mà không kiểm tra schema trước thì AI dễ bịa hoặc kết luận sai về lời lỗ/capacity
- Chưa có logging/audit riêng cho request-response AI, nên khi bridge thật gặp lỗi việc truy vết vẫn còn hạn chế
- Đã verify `staff` bằng account local thật, nhưng `management role` tương lai mới dừng ở mức kiến trúc/prompt/context, chưa có role DB thật để kiểm thử runtime
- Seed credential customer trong local hiện không còn khớp với DB, nên verify Phase 3 cho customer phải dùng backend session-auth script với user thật thay vì login form thường
- Phase 5 mới chỉ ở mức `preliminary recommendation`; chưa có `cost_price`, `promotion history`, `capacity fields` nên chưa thể tối ưu giảm giá sâu hoặc xác nhận lợi nhuận
- `settings` và `rank` hiện chưa execute trực tiếp qua AI vì đang cố ý giữ ở `preview_only`
- `payments`, `audit`, `sql manager` hiện chưa mở mutation path cho AI; chỉ đọc/tóm tắt theo guardrail

## Bàn giao cho lượt sau

- Phase 0, Phase 1, Phase 2, Phase 3, Phase 4 và Phase 5 đã đạt `DONE`
- Local fallback vẫn được giữ như đường dự phòng kỹ thuật, không còn là luồng mặc định của chat khách
- Phase 3 đã nối tra cứu đơn/ví/lịch sử mua vào chatbot bằng dữ liệu thật và giữ ownership hoàn toàn ở backend
- Phase 4 đã có `Admin AI Copilot` thật trong dashboard, kèm scope `admin/staff`
- Phase 4 enhancement mới nhất đã mở `preview/confirm/execute/audit` cho nhiều module admin thay vì chỉ order status
- Phase 5 đã có recommendation cloud-first cho admin bằng dữ liệu thật từ `Product + Order + Coupon`
- Có thể chuyển sang `Phase 6`
- Nếu đi vào phase 6-7 phải kiểm tra `database/schema.sql` trước để xác nhận dữ liệu `cost_price`, `stock_qty`, `capacity_limit`, `lead_time_days`, `min_margin_percent`

## Bổ sung ngoài phase AI: SQL Manager safety hardening

- Ngày ghi nhận: `2026-03-28`
- Loại hạng mục: `Admin tooling / SQL Manager`
- Trạng thái: `Hoàn thành`
- Mục tiêu:
  - làm import SQL báo lỗi rõ kiểu phpMyAdmin-inspired
  - không fake success khi import lỗi giữa chừng
  - cô lập lỗi vào đúng module bị ảnh hưởng
  - giữ storefront và admin dashboard hoạt động tối đa có thể

### File đã tạo

- `C:\xampp\htdocs\ZenoxDigital\app\Services\SchemaHealthService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\ModuleHealthGuardService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\SqlImportService.php`

### File đã sửa

- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\SqlManagerController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\sql_manager\index.php`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\js\admin-sql-manager.js`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\css\admin-sql-manager.css`
- `C:\xampp\htdocs\ZenoxDigital\config\routes.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\HomeController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\ProductController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\ProductController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\CategoryController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\OrderController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\CouponController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\UserController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\DashboardController.php`

### Cơ chế đã thêm

- `SqlImportService` parse file SQL theo từng statement, giữ `statement_number` và `start_line` gần đúng
- DML thuần (`INSERT/UPDATE/DELETE/REPLACE`) chạy trong transaction và rollback toàn bộ nếu lỗi
- File có DDL hoặc mixed statement sẽ bị preflight warning trước khi chạy, kèm cảnh báo MySQL có thể auto-commit
- UI import hiển thị rõ:
  - số statement thành công
  - statement lỗi
  - line gần đúng
  - query preview
  - database message
  - `SQLSTATE`
  - `error_code`
  - rollback có hỗ trợ hay không
  - module nào có thể bị ảnh hưởng
- `SchemaHealthService` kiểm tra integrity cho các module:
  - `products`
  - `categories`
  - `orders`
  - `users`
  - `coupons`
- `ModuleHealthGuardService` chặn an toàn read/write ở đúng controller/module khi schema của module đó bị hỏng

### Hành vi cô lập lỗi theo module

- Nếu schema `products` hỏng:
  - public product browsing / product detail / checkout bị chặn an toàn
  - admin product CRUD bị chặn an toàn
  - dashboard admin vẫn vào được
  - các module unrelated như `auth`, `profile`, `orders`, `users` vẫn chạy nếu schema của chính chúng còn healthy
- Nếu schema `categories`, `orders`, `coupons`, `users` hỏng:
  - chỉ controller/module tương ứng bị chặn thao tác
  - không làm trắng trang toàn site

### Verify đã chạy

- Import DML no-op:
  - thành công
  - `successful_statements = 1`
  - `rollback_supported = true`
- Import SQL lỗi cú pháp:
  - thất bại
  - trả `SQLSTATE = 42000`
  - trả `error_code = 1064`
  - rollback thành công với file DML thuần
- Import DDL preview:
  - trả `requires_confirmation = true`
  - affected modules gồm `products` và `categories`
  - cảnh báo rõ rollback không đảm bảo
- `php -l` pass cho toàn bộ PHP liên quan
- `node --check` pass cho `C:\xampp\htdocs\ZenoxDigital\public\assets\js\admin-sql-manager.js`

### Rủi ro còn lại

- Bộ module health hiện mới theo dõi `products`, `categories`, `orders`, `users`, `coupons`
- `customer_feedback` chưa được đưa vào health set riêng của SQL Manager ở lượt này
