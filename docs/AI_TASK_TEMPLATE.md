# Mẫu Task Giao Cho Codex Dev Webshop

## Mục đích

Đây là bộ prompt mẫu để giao việc cho Codex dev webshop.

Nguyên tắc:

- dùng trực tiếp
- ít phải sửa
- luôn ép đọc đúng tài liệu trước
- luôn bám phase

## Quy tắc bảo vệ tài liệu

Không được tự ý sửa các file tài liệu chuẩn sau nếu người dùng không yêu cầu cập nhật docs:

- `C:\xampp\htdocs\ZenoxDigital\AGENTS.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\README_AI.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_PORTING_GUIDE.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_CONTEXT_MAP.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_TASK_TEMPLATE.md`

Chỉ được cập nhật thường xuyên hai file:

- `C:\xampp\htdocs\ZenoxDigital\docs\AI_IMPLEMENTATION_STATUS.md`
- `C:\xampp\htdocs\ZenoxDigital\docs\AI_FEATURE_PHASES_CHECKLIST.md`

Mỗi lần hoàn thành xong một phase, bắt buộc:

1. cập nhật `AI_IMPLEMENTATION_STATUS.md`
2. cập nhật `AI_FEATURE_PHASES_CHECKLIST.md`

## Prompt gốc bắt buộc

```text
Đọc các file sau trước khi làm:
- C:\xampp\htdocs\ZenoxDigital\AGENTS.md
- C:\xampp\htdocs\ZenoxDigital\docs\README_AI.md
- C:\xampp\htdocs\ZenoxDigital\docs\AI_PORTING_GUIDE.md
- C:\xampp\htdocs\ZenoxDigital\docs\AI_FEATURE_PHASES_CHECKLIST.md
- C:\xampp\htdocs\ZenoxDigital\docs\AI_CONTEXT_MAP.md

Sau khi đọc xong:
1. xác định phase đang làm
2. xác định file sẽ tạo hoặc sửa
3. triển khai trực tiếp
4. không tự ý sửa các file note chuẩn
5. sau khi hoàn thành phase phải cập nhật lại:
   - C:\xampp\htdocs\ZenoxDigital\docs\AI_IMPLEMENTATION_STATUS.md
   - C:\xampp\htdocs\ZenoxDigital\docs\AI_FEATURE_PHASES_CHECKLIST.md

Không được copy nguyên logic Zalo-specific sang webshop.
Không được tự ý sửa AGENTS.md, README_AI.md, AI_PORTING_GUIDE.md, AI_CONTEXT_MAP.md, AI_TASK_TEMPLATE.md nếu chưa có yêu cầu cập nhật tài liệu.
```

## Mẫu task Phase 0

```text
Đọc toàn bộ bộ tài liệu AI của ZenoxDigital trước.

Triển khai Phase 0: Chuẩn bị hạ tầng AI.

Yêu cầu:
- tạo AiBridgeService
- tạo AiContextBuilder
- tạo AiGuardService
- thêm route API nền cho chat khách và chat admin
- dùng biến môi trường cho AI bridge
- không để lộ API key ở JS hoặc view
- không tự ý sửa các file note chuẩn ngoài status và checklist

Khi xong:
- liệt kê file đã tạo
- liệt kê file đã sửa
- ghi tình trạng vào AI_IMPLEMENTATION_STATUS.md
- cập nhật AI_FEATURE_PHASES_CHECKLIST.md đúng phase 0
```

## Mẫu task Phase 1

```text
Đọc AGENTS.md và toàn bộ docs AI trước.

Triển khai Phase 1: Chatbot CSKH cho landing.

Yêu cầu:
- tạo widget chat nổi cho khách
- gắn vào layout main
- tạo controller và API chat cho khách
- AI tư vấn sản phẩm, trả lời FAQ, gợi ý sản phẩm phù hợp
- giữ giao diện gọn, không che CTA mua hàng
- không tự ý sửa các file note chuẩn ngoài status và checklist

Khi xong:
- nêu cách test
- cập nhật AI_IMPLEMENTATION_STATUS.md
- cập nhật AI_FEATURE_PHASES_CHECKLIST.md đúng phase 1
```

## Mẫu task Phase 2

```text
Đọc AGENTS.md và docs AI trước.

Triển khai Phase 2: Feedback và hỗ trợ sau bán.

Yêu cầu:
- tạo bảng feedback nếu chưa có
- tạo API lưu feedback
- AI biết xác nhận đã nhận feedback
- admin có thể xem feedback mới
- không tự ý sửa các file note chuẩn ngoài status và checklist

Khi xong:
- liệt kê schema thay đổi
- liệt kê model/controller/view đã tạo hoặc sửa
- cập nhật AI_IMPLEMENTATION_STATUS.md
- cập nhật AI_FEATURE_PHASES_CHECKLIST.md đúng phase 2
```

## Mẫu task Phase 3

```text
Đọc AGENTS.md và docs AI trước.

Triển khai Phase 3: Tra cứu đơn hàng và tài khoản.

Yêu cầu:
- cho khách tra cứu tình trạng đơn hàng
- nếu user đã đăng nhập, chỉ cho xem đơn của chính mình
- không để lộ dữ liệu người khác
- AI có thể hướng dẫn cơ bản về lịch sử mua và ví
- không tự ý sửa các file note chuẩn ngoài status và checklist

Khi xong:
- nêu rõ rule phân quyền
- nêu rõ cách xác minh truy vấn
- cập nhật AI_IMPLEMENTATION_STATUS.md
- cập nhật AI_FEATURE_PHASES_CHECKLIST.md đúng phase 3
```

## Mẫu task Phase 4

```text
Đọc AGENTS.md và docs AI trước.

Triển khai Phase 4: Admin AI Copilot.

Yêu cầu:
- tạo admin AI panel
- gắn panel vào layout admin hoặc dashboard
- AI tóm tắt dashboard
- AI nêu đơn chờ xử lý, top sản phẩm, tình trạng coupon
- output dễ đọc, ưu tiên card hoặc list gọn
- không tự ý sửa các file note chuẩn ngoài status và checklist

Khi xong:
- nêu 5 câu hỏi mẫu admin có thể dùng
- cập nhật AI_IMPLEMENTATION_STATUS.md
- cập nhật AI_FEATURE_PHASES_CHECKLIST.md đúng phase 4
```

## Mẫu task Phase 5

```text
Đọc AGENTS.md và docs AI trước.

Triển khai Phase 5: Gợi ý bán hàng và khuyến mãi.

Yêu cầu:
- AI gợi ý sản phẩm nên đẩy bán
- AI gợi ý combo hoặc upsell
- AI gợi ý coupon hoặc khuyến mãi sơ bộ
- nếu thiếu giá vốn, phải ghi rõ đây chỉ là gợi ý sơ bộ
- không tự ý sửa các file note chuẩn ngoài status và checklist

Khi xong:
- nêu dữ liệu đã dùng
- nêu dữ liệu còn thiếu
- cập nhật AI_IMPLEMENTATION_STATUS.md
- cập nhật AI_FEATURE_PHASES_CHECKLIST.md đúng phase 5
```

## Mẫu task Phase 6

```text
Đọc AGENTS.md và docs AI trước.

Triển khai Phase 6: Phân tích hàng bán chậm và capacity.

Yêu cầu:
- kiểm tra schema hiện tại
- nếu chưa có product_type, stock_qty, capacity_limit, capacity_used thì phải nêu rõ
- AI phải phân biệt hàng số, hàng ví, capacity, dịch vụ
- không dùng một công thức chung cho tất cả
- không tự ý sửa các file note chuẩn ngoài status và checklist

Khi xong:
- nêu migration cần thêm
- cập nhật AI_IMPLEMENTATION_STATUS.md
- cập nhật AI_FEATURE_PHASES_CHECKLIST.md đúng phase 6
```

## Mẫu task Phase 7

```text
Đọc AGENTS.md và docs AI trước.

Triển khai Phase 7: Guardrail lợi nhuận, không lỗ.

Yêu cầu:
- kiểm tra cost_price, min_margin_percent, phí liên quan
- không cho AI đề xuất bán dưới giá vốn
- nếu dữ liệu chưa đủ, phải từ chối kết luận lời lỗ
- thêm cảnh báo rõ cho admin
- không tự ý sửa các file note chuẩn ngoài status và checklist

Khi xong:
- nêu rule guardrail đã áp dụng
- nêu dữ liệu còn thiếu
- cập nhật AI_IMPLEMENTATION_STATUS.md
- cập nhật AI_FEATURE_PHASES_CHECKLIST.md đúng phase 7
```

## Mẫu task Phase 8

```text
Đọc AGENTS.md và docs AI trước.

Triển khai Phase 8: Báo cáo điều hành và action plan.

Yêu cầu:
- AI tóm tắt theo ngày hoặc tuần
- AI đưa 3 đến 5 action tiếp theo
- output gọn và có tính hành động
- dùng trong admin dashboard
- không tự ý sửa các file note chuẩn ngoài status và checklist

Khi xong:
- nêu câu hỏi mẫu
- nêu output mẫu
- cập nhật AI_IMPLEMENTATION_STATUS.md
- cập nhật AI_FEATURE_PHASES_CHECKLIST.md đúng phase 8
```

## Mẫu task bugfix

```text
Đọc AGENTS.md và docs AI trước.

Sửa lỗi trong phần AI của ZenoxDigital.

Yêu cầu:
- tìm nguyên nhân gốc
- không sửa kiểu workaround hời hợt
- không phá logic phase hiện tại
- sau khi sửa phải cập nhật AI_IMPLEMENTATION_STATUS.md phần ghi chú bàn giao
- không tự ý sửa các file note chuẩn ngoài status và checklist

Khi xong:
- nêu nguyên nhân lỗi
- nêu file sửa
- nêu cách verify
- cập nhật AI_FEATURE_PHASES_CHECKLIST.md nếu bug ảnh hưởng trạng thái phase
```

## Mẫu task review

```text
Đọc AGENTS.md và docs AI trước.

Review phần AI của ZenoxDigital theo mindset code review.

Ưu tiên kiểm tra:
- bảo mật
- phân quyền
- tách controller và service
- lộ API key
- logic Zalo-specific bị bê nhầm
- bịa dữ liệu lời lỗ khi schema chưa đủ
- thiếu logging hoặc timeout

Nếu có findings, nêu theo mức độ nghiêm trọng.
Nếu không có findings, nêu rõ residual risk.
```

## Mẫu task cập nhật tài liệu

```text
Sau khi hoàn thành phase đang làm:
- cập nhật AI_IMPLEMENTATION_STATUS.md
- cập nhật AI_FEATURE_PHASES_CHECKLIST.md
- không sửa AGENTS.md, README_AI.md, AI_PORTING_GUIDE.md, AI_CONTEXT_MAP.md, AI_TASK_TEMPLATE.md nếu người dùng chưa yêu cầu cập nhật tài liệu

Không bỏ qua bước cập nhật tài liệu.
```
