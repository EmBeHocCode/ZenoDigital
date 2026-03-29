# Checklist Phase Tính Năng AI Cho ZenoxDigital

## Mục đích

Tài liệu này là checklist triển khai thực tế cho AI trong ZenoxDigital.

Dùng tài liệu này để:

- chia việc theo phase
- theo dõi tiến độ
- xác định blocker dữ liệu
- review trước khi merge

Tài liệu này phải được dùng cùng với:

- `C:\xampp\htdocs\ZenoxDigital\AGENTS.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\README_AI.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_PORTING_GUIDE.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_CONTEXT_MAP.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_TASK_TEMPLATE.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_IMPLEMENTATION_STATUS.md`

## Cách dùng

Với mỗi phase:

1. đọc mục tiêu phase
2. tick từng đầu việc
3. ghi rõ file đã tạo hoặc sửa
4. ghi blocker nếu thiếu dữ liệu hoặc thiếu API
5. chỉ chuyển phase khi phần `Điều kiện hoàn thành` đã đạt

## Trạng thái chuẩn

Chỉ dùng 4 trạng thái:

- `Chưa làm`
- `Đang làm`
- `Chờ dữ liệu`
- `Hoàn thành`

## Bảng tổng quan phase

| Phase | Tên phase | Trạng thái | Ưu tiên |
|---|---|---|---|
| 0 | Chuẩn bị hạ tầng AI | DONE | Rất cao |
| 1 | Chatbot CSKH cho landing | DONE | Rất cao |
| 2 | Feedback và hỗ trợ sau bán | DONE | Cao |
| 3 | Tra cứu đơn hàng và tài khoản | DONE | Cao |
| 4 | Admin AI Copilot | DONE | Rất cao |
| 5 | Gợi ý bán hàng và khuyến mãi | DONE | Cao |
| 6 | Phân tích hàng bán chậm và capacity | Chưa làm | Trung bình |
| 7 | Guardrail lợi nhuận, không lỗ | Chưa làm | Rất cao |
| 8 | Báo cáo điều hành và action plan | Chưa làm | Trung bình |

---

## Phase 0: Chuẩn bị hạ tầng AI

### Mục tiêu

Tạo nền kỹ thuật để những phase sau gắn vào ổn định.

### Checklist

- [x] Đã đọc `AGENTS.md`
- [x] Đã đọc `AI_PORTING_GUIDE.md`
- [x] Đã đọc `AI_PORTING_GUIDE.md` phần phase
- [x] Đã xác nhận đây là PHP MVC, không phải Node/Next
- [x] Đã quyết định dùng `AI bridge service` hay `shared core`
- [x] Đã thêm biến env cho AI vào `config/config.php` và `C:\xampp\htdocs\ZenoxDigital\.env.example`
- [x] Đã tạo `app/Services/AiBridgeService.php`
- [x] Đã tạo `app/Services/AiContextBuilder.php`
- [x] Đã tạo `app/Services/AiGuardService.php`
- [x] Đã tạo `app/Services/AiSessionManager.php`
- [x] Đã thêm route API nền cho chat khách
- [x] Đã thêm route API nền cho chat admin
- [x] Đã bind `sessionId` AI về server-side, không tin `session_id` do client tự chọn
- [x] Đã có rate limit riêng cho API AI khách và admin
- [x] Guardrail admin chặn cứng `capacity/tồn kho`; còn `khuyến mãi/upsell/giá vốn` phải trả ở mức sơ bộ khi schema chưa đủ dữ liệu
- [x] Đã có test request đơn giản gửi sang AI và nhận phản hồi

### File dự kiến đụng tới

- `C:\xampp\htdocs\ZenoxDigital\config\routes.php`
- `C:\xampp\htdocs\ZenoxDigital\config\config.php`
- `C:\xampp\htdocs\ZenoxDigital\config\ai_capabilities.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiBridgeService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiGuardService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiSessionManager.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\AiController.php`

### Điều kiện hoàn thành

- có request test thành công
- không để lộ API key ra client
- service được tách khỏi controller
- session AI được bind server-side
- endpoint AI có rate limit JSON rõ ràng
- admin AI không suy luận lời lỗ/capacity khi schema còn thiếu cột
- response metadata phải có `provider`, `is_fallback`, `mode`, `source`
- [x] Đã verify được `REAL BRIDGE`

### Ghi chú blocker

- Blocker API: chưa test runtime cho endpoint admin vì cần phiên admin hợp lệ
- Blocker UI:

---

## Phase 1: Chatbot CSKH cho landing

### Mục tiêu

Cho khách truy cập web hỏi và được tư vấn mua hàng ngay trên site.

### Checklist

- [x] Đã tạo `app/Controllers/AiController.php`
- [x] Đã tạo partial `app/Views/partials/ai_chat_widget.php`
- [x] Đã tạo `public/assets/js/ai-chat-widget.js`
- [x] Đã tạo `public/assets/css/ai-chat-widget.css`
- [x] Đã gắn widget vào `app/Views/layouts/main.php`
- [x] Chat không che CTA chính
- [x] Chat hoạt động ở trang chủ
- [x] Chat hoạt động ở trang sản phẩm
- [x] AI trả lời được FAQ cơ bản
- [x] AI gợi ý được sản phẩm theo nhu cầu
- [x] AI có thể dẫn người dùng tới trang sản phẩm phù hợp
- [x] AI trả lời bằng tiếng Việt gọn, rõ, lịch sự
- [x] Widget sync `sessionId` từ server khi chat/reset để đi đúng nền Phase 0
- [x] Đã có lớp backend normalizer để hiểu không dấu, viết tắt, teencode/slang phổ biến trước khi gửi sang bridge
- [x] Đã truyền `original_text`, `normalized_text`, `intent_guess`, `context_hint`, `recent_messages` sang AI bridge
- [x] Prompt bridge đã được cô đọng để không vượt giới hạn `message <= 8000` của web-chat bridge
- [x] Tên bot hiển thị trên widget đã thống nhất là `Meow`
- [x] Welcome text, quick suggestions và utility menu đổi theo role thật từ backend
- [x] Prompt chatbot đã chuyển sang kiểu nhiều lớp để giảm cảm giác canned response

### File dự kiến đụng tới

- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\main.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\ai_chat_widget.php`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\js\ai-chat-widget.js`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\css\ai-chat-widget.css`

### Điều kiện hoàn thành

- khách hỏi về VPS, wallet, server game đều có hướng trả lời hợp lý
- widget responsive trên desktop và mobile
- có loading state và xử lý lỗi cơ bản
- response hội thoại chính phải đi qua `REAL BRIDGE`, không được chỉ chạy bằng `local fallback`
- phải verify rõ metadata `provider`, `is_fallback`, `mode`, `source`
- [x] Đã test tối thiểu 5 câu hỏi khác nhau qua webshop endpoint và metadata đều trả `is_fallback=false`
- [x] Đã test các câu slang bắt buộc như `chào bé`, `hog á`, `gói nào ổn z`, `còn hàng ko`, `ship lẹ hog`, `e mún góp ý`, `ad ơi check đơn e`

### Ghi chú blocker

- FAQ còn thiếu: chưa có bộ FAQ mở rộng ngoài 2 câu cơ bản đang lấy từ context builder
- Sản phẩm mẫu còn thiếu:
- Context còn thiếu: không còn blocker kỹ thuật cho Phase 1; bridge thật đã cấu hình và verify qua local bot API

---

## Phase 2: Feedback và hỗ trợ sau bán

### Mục tiêu

Nhận feedback và hỗ trợ sau mua hàng qua AI.

### Checklist

- [x] Đã thiết kế bảng `customer_feedback`
- [x] Đã có API lưu feedback
- [x] AI biết xác nhận đã ghi nhận feedback
- [x] AI phân loại feedback cơ bản
- [x] Có nơi cho admin xem feedback
- [x] Có thể lưu nội dung góp ý, mức độ, trạng thái xử lý
- [x] Nếu feedback tiêu cực thì gợi ý hỗ trợ thêm mà không bịa ticket/SLA
- [x] Chatbot phân vai dựa trên session/auth backend, không tin role/name do client gửi
- [x] Guest được nhận diện đúng và chỉ dùng xưng hô an toàn, không giả vờ biết tên hay lịch sử đơn
- [x] Customer đăng nhập được nhận diện đúng và có thể gọi bằng tên thật từ server
- [x] Admin đi qua route backoffice được nhận diện đúng ngữ cảnh quản trị
- [x] Widget public cũng chọn `conversation_mode` theo session backend, nên admin/backoffice không còn bị chào kiểu CSKH khi mở landing hoặc trang sản phẩm
- [x] Kiến trúc đã sẵn để map thêm `staff` và role quản trị khác ở phase sau mà không phải viết lại phân vai
- [x] Conversation mode đã tách rõ `customer_support`, `admin_copilot`, `staff_support`, `management_support`
- [x] Persona nền `Nguyễn Thanh Hà` đã được rewrite cho webshop, không copy nguyên hành vi Zalo-specific

### File dự kiến đụng tới

- `C:\xampp\htdocs\ZenoxDigital\database\schema.sql`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\CustomerFeedback.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\CustomerFeedbackService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiActorResolver.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiPersonaService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\FeedbackController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\feedback\index.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\partials\ai_chat_widget.php`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\js\ai-chat-widget.js`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\css\ai-chat-widget.css`

### Điều kiện hoàn thành

- feedback được lưu bền vững
- admin xem lại được feedback mới
- AI không mất ngữ cảnh sau khi khách gửi phản hồi
- phải phân biệt được `guest`, `customer`, `admin` bằng session backend thật
- phải có `conversation_mode` để public widget đổi đúng giọng guest/customer/admin copilot
- nếu chưa xác định chắc danh tính thì phải fallback về ngữ cảnh an toàn
- customer chỉ được gọi theo tên thật phía server hoặc cách xưng an toàn, không được hardcode tên mẫu
- response hội thoại chính sau khi lưu feedback phải đi qua `REAL BRIDGE`
- metadata phải trả rõ `provider`, `is_fallback`, `mode`, `source`
- [x] Đã verify feedback runtime trả `is_fallback=false`
- [x] Đã verify đủ 3 case runtime: `guest`, `customer`, `admin`

### Ghi chú blocker

- Không còn blocker kỹ thuật cho Phase 2
- Local fallback vẫn chỉ là cơ chế dự phòng, không phải luồng chính của feedback
- Chưa có user `staff` thật để verify runtime, nhưng resolver/context đã sẵn cho role này khi Phase 4 bổ sung

---

## Phase 3: Tra cứu đơn hàng và tài khoản

### Mục tiêu

Cho khách tra cứu đơn và hỏi các vấn đề tài khoản cơ bản.

### Checklist

- [x] AI tra cứu được đơn theo mã đơn bằng dữ liệu thật trong `Order`
- [x] Nếu người dùng đã đăng nhập thì chỉ xem đơn của chính họ qua `user_id` backend
- [x] Nếu chưa đăng nhập thì phải có kiểm tra định danh an toàn bằng `mã đơn + email đặt hàng`
- [x] AI trả được trạng thái đơn, thời gian tạo và tổng tiền khi xác minh hợp lệ
- [x] AI hướng dẫn cơ bản về ví và giao dịch bằng dữ liệu thật từ `User` + `WalletTransaction`
- [x] AI tóm tắt được lịch sử mua gần đây cho tài khoản đã đăng nhập
- [x] Không lộ dữ liệu người khác
- [x] Có chặn truy vấn tùy tiện bằng mã đơn đoán mò
- [x] Phần dữ liệu cốt lõi được backend dựng `structured_reply`, còn follow-up vẫn đi qua `REAL BRIDGE`
- [x] Schema `users` đã có `gender` và `birth_date`
- [x] Form đăng ký lưu được `gender` và `birth_date`
- [x] Profile người dùng xem/sửa được `gender` và `birth_date`
- [x] Admin user management xem/sửa được `gender` và `birth_date`
- [x] Google login nếu thiếu dữ liệu hồ sơ thì mặc định `gender=unknown`, `birth_date=null`
- [x] AI context có `actor_gender`, `actor_birth_date`, `actor_age`
- [x] Chatbot chỉ dùng `anh/chị/em` khi backend có dữ liệu tin cậy; còn lại fallback `bạn/mình`
- [x] Kiến trúc role vẫn mở cho `staff` và management role sau này, không hardcode chết chỉ `admin/user`

### File dự kiến đụng tới

- `C:\xampp\htdocs\ZenoxDigital\app\Models\Order.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\User.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\WalletTransaction.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Helpers\functions.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiOrderAccountSupportService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiInputNormalizerService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiPersonaService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiBridgeService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiActorResolver.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\AuthController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\ProfileController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\UserController.php`
- `C:\xampp\htdocs\ZenoxDigital\config\ai_capabilities.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\auth\register.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\profile\index.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\users\form.php`

### Điều kiện hoàn thành

- đơn thật tra được trạng thái bằng dữ liệu DB
- khách lạ không xem được dữ liệu người khác nếu chưa qua xác minh `mã đơn + email`
- customer đăng nhập chỉ xem được đơn, lịch sử mua và ví của chính mình
- câu trả lời có định dạng rõ: mã đơn, trạng thái, thời gian, tổng tiền khi phù hợp
- phần hội thoại chính phải chạy `REAL BRIDGE`, không dùng fallback làm đường mặc định
- [x] Đã verify `REAL BRIDGE` cho guest tra cứu đơn qua chính endpoint webshop
- [x] Đã verify `REAL BRIDGE` cho customer bằng backend session-auth với user thật

### Ghi chú blocker

- Không còn blocker kỹ thuật cho Phase 3
- Seed credential customer trong local không còn khớp DB, nên nhánh customer được verify bằng backend session-auth script với user thật thay vì login form mặc định

---

## Phase 4: Admin AI Copilot

### Mục tiêu

Đưa AI vào dashboard admin để tóm tắt và hỗ trợ quyết định.

### Checklist

- [x] Đã hoàn thiện `app/Controllers/Admin/AiController.php`
- [x] Đã hoàn thiện `app/Views/partials/admin_ai_panel.php`
- [x] Đã tạo `public/assets/js/admin-ai-panel.js`
- [x] Đã tạo `public/assets/css/admin-ai-panel.css`
- [x] Đã gắn nút mở AI vào layout admin
- [x] AI đọc được KPI chính từ dữ liệu thật trong dashboard stats
- [x] AI tóm tắt được dashboard theo quyền backend thật
- [x] AI nêu được đơn chờ xử lý
- [x] AI nêu được top sản phẩm
- [x] AI nêu được tình trạng coupon
- [x] AI nêu được feedback mới khi actor có quyền xem
- [x] Output gọn, dễ đọc, ưu tiên card/list ngắn trong panel
- [x] UI chat admin hiện ngay progress state khi vừa gửi câu hỏi
- [x] Progress state thật tối thiểu đi qua `Bot đang kiểm dữ liệu...` và `Bot đang thống kê và gửi đến...`
- [x] Session copilot được lưu thật ở backend, không chỉ giữ tạm ở frontend
- [x] Reload dashboard hoặc mở lại popup vẫn khôi phục được lịch sử chat gần nhất
- [x] Reset tạo session mới thật sự và không tiếp tục dùng ngữ cảnh cũ
- [x] Session gắn với `admin/staff user_id` và không lẫn giữa các tài khoản
- [x] Session có TTL và tự tạo phiên mới khi hết hạn
- [x] History dùng cửa sổ message gần nhất để giữ ngữ cảnh mà không làm request phình vô hạn
- [x] Nếu intent admin đã đủ rõ thì bot chạy luôn, không hỏi lại xác nhận
- [x] Nếu thiếu tham số quan trọng thật sự thì bot chỉ hỏi lại đúng 1 câu ngắn
- [x] Admin AI context có freshness theo `version stamp + TTL ngắn`, không giữ cache mù quá lâu
- [x] `GET /admin/ai/progress` poll được trong lúc `POST /admin/ai/chat` còn đang xử lý
- [x] Chỉ `admin/staff` hợp lệ mới vào được endpoint `admin/ai/*`
- [x] `customer` không vào được dashboard admin và không dùng được admin copilot
- [x] Đã có role `staff` thật trong schema/model, không giả lập chỉ bằng view
- [x] `staff` được dùng Copilot ở scope vận hành giới hạn, không mặc định có toàn quyền như `admin`
- [x] Câu hỏi doanh thu/user metrics/rank/system của `staff` bị backend guardrail chặn đúng quyền
- [x] Admin/staff summary và chat đã verify `REAL BRIDGE`
- [x] Mutation admin AI đã mở rộng từ order-only sang nhiều module với service riêng cho permission/draft/execute
- [x] Có `preview -> confirm -> execute` cho mutation và pending draft bị khóa theo đúng session admin hiện tại
- [x] Mọi mutation qua AI đều ghi `admin_audit_logs` với `source=admin_ai`
- [x] `products`, `categories`, `orders`, `coupons`, `feedback`, `users` đã có mutation path an toàn qua model/business logic hiện có
- [x] `settings` và `rank` hiện được giữ ở `preview_only` thay vì execute trực tiếp
- [x] `payments`, `audit`, `sql manager` hiện chỉ read/tóm tắt, không mở SQL write path cho AI
- [x] Module unhealthy sẽ bị chặn write ở backend, không ném lỗi SQL thô ra UI chat

### File dự kiến đụng tới

- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\admin.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\dashboard\index.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiProgressService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiDataFreshnessService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiIntentService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\js\admin-ai-panel.js`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\css\admin-ai-panel.css`

### Điều kiện hoàn thành

- admin hỏi “hôm nay shop thế nào” có câu trả lời đúng dữ liệu
- không bịa doanh thu hoặc số đơn
- panel hoạt động ổn trên layout admin
- progress state phải dẫn tới kết quả thật hoặc lỗi rõ, không đứng im như treo
- intent rõ như `xem đơn pending`, `doanh thu hôm nay`, `feedback mới`, `coupon hiện tại`, `sản phẩm bán chạy` phải chạy luôn
- câu mơ hồ như `xem đơn hàng` chỉ được hỏi lại đúng 1 câu ngắn
- context admin phải refresh lại khi dữ liệu shop đổi ở các bảng trọng yếu
- `staff` có quyền thực tế để vào dashboard + Copilot nhưng bị giới hạn đúng dữ liệu vận hành
- câu trả lời phân tích dashboard đi qua `REAL BRIDGE`, không dựa chủ yếu vào template hardcode
- mutation admin AI không dùng SQL thô làm đường chính
- destructive action phải cần preview + confirm rõ trước khi execute
- phải có module permission rõ cho từng nhóm quản trị chính
- module unhealthy phải chặn được write path của AI
- [x] Đã verify `GET /admin/ai/summary` và `POST /admin/ai/chat` cho `admin`
- [x] Đã verify `GET /admin/ai/summary` và `POST /admin/ai/chat` cho `staff`
- [x] Đã verify smoke session lifecycle: restore cùng session, tách session theo user, tạo session mới khi expired
- [x] Đã verify câu hỏi vượt quyền của `staff` bị backend guardrail chặn
- [x] Đã verify `GET /admin/ai/progress` poll được khi chat admin đang chạy
- [x] Đã verify các case `xem đơn pending`, `doanh thu hôm nay`, `feedback mới có gì`, `coupon hiện tại thế nào`, `sản phẩm nào đang bán chạy`, `xem đơn hàng`, `xem đơn pending hôm nay`
- [x] Đã verify các case mutation/guardrail: `sửa giá sản phẩm`, `thêm danh mục`, `tạo coupon`, `khóa user`, `đổi trạng thái đơn`, `xử lý feedback`, `xem payment/wallet`, `staff không đủ quyền`, `destructive action cần confirm`, `module unhealthy bị chặn`

### Ghi chú blocker

- Không còn blocker kỹ thuật cho Phase 4
- `staff` đã được triển khai ở mức dashboard + Copilot vận hành giới hạn
- `management role` tương lai mới dừng ở mức kiến trúc/context/prompt, chưa có role DB thật để kiểm thử runtime
- `settings` và `rank` hiện là preview-only theo thiết kế guardrail, chưa mở execute trực tiếp qua AI
- `payments`, `audit`, `sql manager` hiện read-only theo policy an toàn, không phải lỗi thiếu route/controller

---

## Phase 5: Gợi ý bán hàng và khuyến mãi

### Mục tiêu

Cho AI đề xuất bán hàng thông minh hơn.

### Checklist

- [x] AI nhận diện được sản phẩm bán tốt
- [x] AI nhận diện được nhóm hàng nên đẩy
- [x] AI gợi ý combo hoặc upsell
- [x] AI gợi ý coupon sơ bộ
- [x] AI không khẳng định có lời nếu thiếu dữ liệu giá vốn
- [x] Admin nhìn câu trả lời là biết nên làm gì tiếp
- [x] Output có card hoặc bảng nếu danh sách dài
- [x] Đã ưu tiên Cloud/VPS làm core business trong recommendation
- [x] Đã dùng dữ liệu thật từ `Product`, `Order`, `Coupon`
- [x] Đã verify runtime câu trả lời chính đi qua `REAL BRIDGE`

### File dự kiến đụng tới

- `C:\xampp\htdocs\ZenoxDigital\app\Models\Product.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Order.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Coupon.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Category.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiGuardService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiBridgeService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiSalesRecommendationService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiPersonaService.php`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\js\admin-ai-panel.js`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\css\admin-ai-panel.css`

### Điều kiện hoàn thành

- có thể hỏi “nên khuyến mãi gì tuần này”
- AI có câu trả lời dựa trên sản phẩm và tình hình bán hàng
- có chú thích rõ nếu đây mới là gợi ý sơ bộ
- [x] Đã test `Tuần này nên đẩy gói nào?`
- [x] Đã test `Nên khuyến mãi gì cho nhóm Cloud VPS?`
- [x] Đã test `Có gói nào phù hợp làm upsell không?`
- [x] Đã test `Sản phẩm nào nên đưa lên homepage?`
- [x] Đã test `Có coupon nào đang nên bật hoặc nên tắt không?`
- [x] Đã test `Nếu chưa có giá vốn thì hiện tại em gợi ý được tới mức nào?`
- [x] Đã test `Nhóm sản phẩm nào đang phù hợp để chạy ưu đãi sơ bộ?`
- [x] Kết quả test trả `provider=zia-bot-bridge`, `is_fallback=false`, `mode=real_bridge`, `source=ai-bridge`

### Ghi chú blocker

- Thiếu cost price: chỉ cho phép gợi ý sơ bộ, không khẳng định lời/lỗ
- Thiếu fee: chưa thể xác định mức giảm tối đa an toàn
- Thiếu lịch sử khuyến mãi: coupon pilot phải chạy nhỏ và theo dõi thủ công
- Thiếu capacity fields: vẫn chưa làm được phần Phase 6

---

## Phase 6: Phân tích hàng bán chậm và capacity

### Mục tiêu

Cho AI phát hiện điểm nghẽn hàng hóa hoặc tài nguyên dịch vụ số.

### Checklist

- [ ] Đã có trường `product_type`
- [ ] Đã có `stock_qty` hoặc `capacity_limit/capacity_used`
- [ ] AI phân biệt được loại hàng số
- [ ] AI nêu được sản phẩm bán chậm
- [ ] AI nêu được capacity sắp đầy hoặc sắp thiếu
- [ ] AI gợi ý nhập thêm hoặc cân đối nguồn

### File dự kiến đụng tới

- `C:\xampp\htdocs\ZenoxDigital\database\schema.sql`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Product.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php`

### Điều kiện hoàn thành

- AI không dùng một công thức chung cho mọi sản phẩm
- AI có thể nói rõ cái nào là “hết mã”, cái nào là “hết slot capacity”

### Ghi chú blocker

- Thiếu product_type:
- Thiếu stock_qty:
- Thiếu capacity_limit:

---

## Phase 7: Guardrail lợi nhuận, không lỗ

### Mục tiêu

Không để AI gợi ý chiến lược bán hàng gây lỗ.

### Checklist

- [ ] Đã có `cost_price`
- [ ] Đã có `min_margin_percent`
- [ ] Có rule không đề xuất dưới giá vốn
- [ ] Có rule cảnh báo khi margin quá thấp
- [ ] Có log rõ khi AI từ chối đề xuất giảm giá
- [ ] AI nói rõ “thiếu dữ liệu” nếu chưa đủ cột

### File dự kiến đụng tới

- `C:\xampp\htdocs\ZenoxDigital\database\schema.sql`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiGuardService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php`

### Điều kiện hoàn thành

- admin hỏi “giảm giá còn 30% được không” thì AI biết chặn hoặc cảnh báo
- AI không bịa ra lãi giả

### Ghi chú blocker

- Thiếu min_margin_percent:
- Thiếu cost_price:
- Thiếu phí nền tảng:

---

## Phase 8: Báo cáo điều hành và action plan

### Mục tiêu

Biến AI từ chat trả lời thành trợ lý điều hành.

### Checklist

- [ ] AI tóm tắt ngày hoặc tuần
- [ ] AI nêu 3 đến 5 việc quan trọng nhất
- [ ] AI gom dữ liệu theo dạng card hoặc bảng
- [ ] AI đề xuất next actions
- [ ] Có thể dùng ngay trong dashboard

### File dự kiến đụng tới

- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\dashboard\index.php`

### Điều kiện hoàn thành

- admin mở dashboard và có thể dùng AI như trợ lý vận hành
- output có tính hành động, không chỉ mô tả

### Ghi chú blocker

- Thiếu reporting summary:
- Thiếu log:
- Thiếu dữ liệu lịch sử:

---

## Checklist review trước khi merge

- [ ] Đã đọc `AGENTS.md`
- [ ] Đã đọc `AI_PORTING_GUIDE.md`
- [ ] Đã đối chiếu phase hiện tại
- [ ] Không copy logic Zalo-specific
- [ ] Không để controller ôm hết logic
- [ ] Có service riêng cho AI bridge
- [ ] Có service riêng cho context builder
- [ ] Có guardrail nếu liên quan lời lỗ
- [ ] Không lộ API key ra JS client
- [ ] Có xử lý lỗi và timeout
- [ ] Có phân quyền khách và admin
- [ ] Có note blocker dữ liệu nếu schema chưa đủ

### Trạng thái review hiện tại

- [x] Đã đọc `AGENTS.md`
- [x] Đã đọc `AI_PORTING_GUIDE.md`
- [x] Đã đối chiếu phase hiện tại
- [x] Không copy logic Zalo-specific
- [x] Không để controller ôm hết logic
- [x] Có service riêng cho AI bridge
- [x] Có service riêng cho context builder
- [x] Có guardrail nếu liên quan lời lỗ
- [x] Không lộ API key ra JS client
- [x] Có xử lý lỗi và timeout
- [x] Có phân quyền khách và admin
- [x] Có note blocker dữ liệu nếu schema chưa đủ

## Nhật ký tiến độ

### Phase đang làm

- Phase: `4 - Admin AI Copilot multi-module mutation guardrails`
- Trạng thái: `DONE`
- Người làm: `Codex`
- Ngày bắt đầu: `2026-03-29`
- Ngày dự kiến xong: `2026-03-29`

### File đã tạo

- `C:\xampp\htdocs\ZenoxDigital\.env`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\CustomerFeedback.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\CustomerFeedbackService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiPersonaService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiSalesRecommendationService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiProgressService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiDataFreshnessService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiIntentService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiModulePermissionService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiMutationDraftService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiMutationService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiOrderAccountSupportService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\FeedbackController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\admin\feedback\index.php`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\js\admin-ai-panel.js`
- `C:\xampp\htdocs\ZenoxDigital\public\assets\css\admin-ai-panel.css`

### File đã sửa

- `C:\xampp\htdocs\ZenoxDigital\database\schema.sql`
- `C:\xampp\htdocs\ZenoxDigital\app\Core\Auth.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Category.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Order.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Product.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\User.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\Coupon.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiActorResolver.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiPersonaService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiBridgeService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiContextBuilder.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiGuardService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiOrderAccountSupportService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AiInputNormalizerService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiIntentService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiModulePermissionService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiMutationDraftService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiMutationService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\SchemaHealthService.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\AuthController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\AiController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Controllers\Admin\DashboardController.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\AiChatSession.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Models\AiChatMessage.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Services\AdminAiSessionService.php`
- `C:\xampp\htdocs\ZenoxDigital\config\ai_capabilities.php`
- `C:\xampp\htdocs\ZenoxDigital\config\routes.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\admin.php`
- `C:\xampp\htdocs\ZenoxDigital\app\Views\layouts\main.php`
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

### Blocker hiện tại

- Không còn blocker kỹ thuật cho hạng mục multi-module mutation guardrails của Admin AI Copilot
- Chưa chạy browser automation để click hết các case reload/đóng mở popup; hiện đã verify lifecycle bằng smoke backend + lint + wiring controller/js
- `REAL BRIDGE` vẫn là đường chính cho câu hỏi mở; `direct_admin_read` dùng cho các intent rõ cần phản hồi nhanh
- Capacity/tồn kho vẫn là blocker dữ liệu cho Phase 6 vì schema chưa có `stock_qty`, `capacity_limit`, `capacity_used`
- `settings` và `rank` là preview-only theo chủ đích guardrail
- `payments`, `audit`, `sql manager` đang read-only theo policy hiện tại

### Ghi chú bàn giao

- Phase 0, Phase 1, Phase 2, Phase 3, Phase 4 và Phase 5 đã đạt `DONE`
- Admin AI hiện đang chạy `REAL BRIDGE`
- Admin AI nay có thêm `direct_admin_read` cho câu hỏi đọc dữ liệu rõ ràng và `clarification` tối thiểu cho câu hỏi mơ hồ
- Admin AI nay giữ được session chat thật ở backend, restore lịch sử sau reload và reset bằng session mới đúng nghĩa
- Admin AI nay có thêm `AdminAiModulePermissionService`, `AdminAiMutationDraftService`, `AdminAiMutationService`
- Admin AI nay có mutation matrix nhiều module:
  - `products`, `categories`, `orders`, `coupons`, `feedback`, `users` => preview/confirm/execute
  - `settings`, `rank` => preview-only
  - `payments`, `audit`, `sql manager` => read-only
- Cách verify đã dùng:
  - xác minh bridge tham chiếu local sống tại `http://127.0.0.1:10001/health`
  - build `AiSalesRecommendationService` bằng DB thật để lấy push/upsell/coupon recommendation
  - đo lại prompt bridge, rút xuống khoảng `5881` ký tự để không vượt giới hạn `<= 8000`
  - test 7 câu hỏi nghiệm thu Phase 5 qua `AiBridgeService`
  - kiểm tra metadata trả về `provider=zia-bot-bridge`, `is_fallback=false`, `mode=real_bridge`, `source=ai-bridge`
  - kiểm tra thêm guardrail câu `capacity` vẫn bị backend chặn đúng
- Điều chỉnh thêm trong lượt này:
  - thêm `AiSalesRecommendationService` để gom tín hiệu thật từ `Product + Order + Coupon`
  - cho panel admin render section `Cloud nên đẩy / Upsell cloud / Coupon - khuyến mãi / Giới hạn dữ liệu`
  - nới guardrail Phase 5: `khuyến mãi/upsell/giá vốn` trả lời sơ bộ, `capacity` vẫn block cứng
- Điều chỉnh thêm trong lượt này:
  - thêm `AdminAiProgressService` + route `GET /admin/ai/progress`
  - thêm `AdminAiDataFreshnessService` để fingerprint các bảng quản trị trọng yếu
  - thêm `AdminAiIntentService` để tách `execute ngay / hỏi lại 1 câu / pass-through bridge`
  - cho `POST /admin/ai/chat` nhả session lock sớm để polling không bị treo
- Điều chỉnh thêm trong lượt này:
  - thêm preview + confirm draft cho mutation admin AI
  - thêm audit bắt buộc `source=admin_ai` cho mọi mutation execute
  - thêm verify service-level theo transaction và verify HTTP thật cho `/admin/ai/chat`
- Có thể chuyển sang `Phase 6`
- Nếu làm phase 6-7 phải kiểm tra schema trước, không được bịa dữ liệu `cost_price`, `stock_qty`, `capacity_limit`, `lead_time_days`, `min_margin_percent`

---

## Hạng mục bổ sung ngoài phase AI: SQL Manager safety hardening

### Trạng thái

- Hạng mục: `Admin tooling / SQL Manager`
- Trạng thái: `Hoàn thành`
- Ngày ghi nhận: `2026-03-28`

### Checklist

- [x] Import SQL có preflight check trước khi chạy
- [x] Import DML thuần chạy trong transaction và rollback toàn bộ nếu lỗi
- [x] Import có DDL/mixed statement buộc phải hiện cảnh báo rollback không đảm bảo
- [x] UI SQL Manager hiển thị report import rõ kiểu phpMyAdmin-inspired
- [x] Không fake success nếu lỗi ở giữa file
- [x] Có trả `statement lỗi`, `line gần đúng`, `query preview`, `SQLSTATE`, `error_code`
- [x] Có health check schema cho `products`, `categories`, `orders`, `users`, `coupons`
- [x] Module unhealthy bị chặn thao tác đúng phạm vi thay vì làm sập toàn site
- [x] Public storefront và admin dashboard vẫn hoạt động tối đa có thể nếu lỗi chỉ nằm ở một module
- [x] Có audit log cho `sql_import_preflight`, `sql_import_success`, `sql_import_failed`

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

### Cách test đã dùng

- Import SQL đúng:
  - `UPDATE settings SET updated_at = updated_at WHERE 1 = 0;`
  - kỳ vọng: thành công, không warning sai
- Import SQL lỗi cú pháp:
  - `UPDATE settings SET updated_at = NOW( WHERE setting_key = 'x';`
  - kỳ vọng: báo lỗi rõ, không fake success, rollback thành công với DML thuần
- Import SQL lỗi schema sản phẩm:
  - `ALTER TABLE products DROP COLUMN price;`
  - kỳ vọng: hiện preflight warning, yêu cầu confirm, cảnh báo affected modules `products/categories`
- Xác nhận các phần khác của site vẫn hoạt động:
  - `login`
  - `profile`
  - `admin dashboard`
  - `orders`

### Ghi chú

- Đây là hạng mục admin tooling, không thay đổi trạng thái các phase AI hiện tại
- Bộ health guard hiện chưa bao gồm `customer_feedback`
