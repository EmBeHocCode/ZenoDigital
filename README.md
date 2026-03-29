# ZenoxDigital

ZenoxDigital là một web platform bán dịch vụ số theo định hướng cloud-first, tập trung vào các gói Cloud Server / VPS và đi kèm một hệ thống quản trị backoffice tương đối đầy đủ. Dự án được xây dựng theo kiến trúc PHP MVC, có storefront cho khách hàng, admin dashboard cho vận hành, cùng các thành phần AI hỗ trợ tư vấn và quản trị dữ liệu theo ngữ cảnh thực tế của shop.

README này được viết theo hướng phù hợp để:

- đưa lên GitHub như một project showcase
- phục vụ báo cáo học thuật / đồ án
- thể hiện năng lực full-stack, tư duy hệ thống và tổ chức nghiệp vụ khi dùng trong CV hoặc portfolio

## Mục lục

- [1. Tổng quan dự án](#1-tổng-quan-dự-án)
- [2. Mục tiêu dự án](#2-mục-tiêu-dự-án)
- [3. Tính năng chính](#3-tính-năng-chính)
- [4. Kiến trúc hệ thống](#4-kiến-trúc-hệ-thống)
- [5. Công nghệ sử dụng](#5-công-nghệ-sử-dụng)
- [6. Các module nghiệp vụ](#6-các-module-nghiệp-vụ)
- [7. AI / Meow Copilot](#7-ai--meow-copilot)
- [8. Điểm nhấn kỹ thuật cho học thuật và CV](#8-điểm-nhấn-kỹ-thuật-cho-học-thuật-và-cv)
- [9. Cài đặt và chạy local](#9-cài-đặt-và-chạy-local)
- [10. Hướng dẫn sử dụng nhanh](#10-hướng-dẫn-sử-dụng-nhanh)
- [11. Hình ảnh minh họa](#11-hình-ảnh-minh-họa)
- [12. Roadmap](#12-roadmap)
- [13. Đóng góp](#13-đóng-góp)
- [14. Giấy phép](#14-giấy-phép)

## 1. Tổng quan dự án

ZenoxDigital mô phỏng một hệ thống web shop dịch vụ số có cấu trúc tương đối đầy đủ, trong đó mảng sản phẩm cốt lõi là Cloud/VPS. Ngoài storefront cho khách hàng, dự án còn phát triển một dashboard quản trị để xử lý sản phẩm, đơn hàng, người dùng, coupon, feedback, cài đặt và một lớp AI Copilot hỗ trợ truy vấn, phân tích và thao tác backoffice theo quyền.

Về mặt sản phẩm, dự án hướng tới việc trả lời 3 câu hỏi:

- một web shop dịch vụ số cần những module nghiệp vụ nào để vận hành thực tế
- làm thế nào để tổ chức code theo hướng dễ mở rộng hơn so với việc viết dồn logic vào controller
- AI có thể hỗ trợ vận hành dashboard như một copilot nội bộ đến mức nào nếu bị ràng buộc bởi quyền, dữ liệu thật và guardrail

## 2. Mục tiêu dự án

### Mục tiêu học thuật

- Xây dựng một hệ thống web có đầy đủ lớp `frontend - business logic - admin dashboard - data management`
- Mô hình hóa các thực thể nghiệp vụ cốt lõi như sản phẩm, danh mục, đơn hàng, người dùng, coupon, feedback, ví và giao dịch
- Thực hành phân tách mã nguồn theo `Controller / Model / Service` trong một codebase PHP MVC thuần
- Bổ sung các yếu tố hệ thống như phân quyền, session, rate limit, CSRF, audit log và module health

### Mục tiêu thực tiễn

- Tạo một storefront có thể trình bày sản phẩm số theo hướng dễ mua, dễ lọc và dễ hiểu
- Xây dựng một dashboard admin đủ rõ để quản trị dữ liệu vận hành
- Tích hợp AI vào hai ngữ cảnh thực tế:
  - chatbot hỗ trợ khách hàng
  - copilot hỗ trợ quản trị trong dashboard admin
- Dùng dữ liệu thật của shop để trả lời, thay vì chỉ hiển thị các phản hồi mẫu

## 3. Tính năng chính

### Khách hàng

- Trang chủ giới thiệu sản phẩm và nhóm dịch vụ theo hướng cloud-first
- Danh sách sản phẩm có tìm kiếm, lọc, sắp xếp và phân trang
- Trang chi tiết sản phẩm có mô tả, thông số, gallery và gợi ý sản phẩm liên quan
- Đăng ký, đăng nhập, quên mật khẩu và đăng nhập Google OAuth
- Hồ sơ người dùng với thông tin cá nhân, avatar, lịch sử mua hàng và khu vực ví
- Security Center với đổi mật khẩu, quản lý phiên, 2FA và một số thao tác bảo mật nhạy cảm
- Chatbot hỗ trợ khách hàng và tiếp nhận feedback từ giao diện public

### Admin Dashboard

- Dashboard tổng quan với KPI vận hành và các block dữ liệu chính
- Quản lý sản phẩm: CRUD, trạng thái hiển thị, ảnh đại diện và gallery
- Quản lý danh mục: CRUD và chặn xóa khi còn liên kết dữ liệu
- Quản lý đơn hàng: xem danh sách, lọc trạng thái, xem chi tiết, cập nhật trạng thái, xóa mềm
- Quản lý người dùng: tạo/sửa/xóa, phân quyền, khóa/mở tài khoản
- Quản lý coupon: tạo, bật/tắt, theo dõi tình trạng sử dụng
- Quản lý feedback khách hàng
- Quản lý cài đặt hệ thống, rank, audit log và SQL Manager

### AI Copilot

- Meow chatbot cho khách hàng trên storefront
- Meow Copilot cho admin dashboard với progress state, data freshness và role-aware behavior
- Fast-path đọc dữ liệu thật cho các intent rõ như đơn pending, doanh thu, feedback, coupon, top sản phẩm
- Mutation admin có kiểm soát theo flow `preview -> confirm -> execute -> audit`

### Công cụ quản trị dữ liệu

- SQL Manager để đọc dữ liệu, preview thao tác SQL và kiểm tra schema theo guardrail
- Audit log để theo dõi hành động quản trị
- Module health / schema health để ngăn thao tác trên module có trạng thái không an toàn

## 4. Kiến trúc hệ thống

ZenoxDigital đi theo kiến trúc PHP MVC thuần, kết hợp thêm lớp `Service` để giảm việc controller ôm quá nhiều logic.

### Cấu trúc chính

```text
ZenoxDigital
├─ public/              # entrypoint, assets, uploads
├─ app/
│  ├─ Core/             # App, Controller, Model, Database, Auth
│  ├─ Controllers/      # controller storefront và admin
│  ├─ Models/           # truy cập dữ liệu qua PDO
│  ├─ Services/         # business services, AI services, guardrails
│  ├─ Helpers/          # helper chung, security helpers, formatting
│  └─ Views/            # layouts, partials, storefront, admin dashboard
├─ config/              # config app, routes, capabilities
├─ database/            # schema SQL
└─ docs/                # tài liệu AI và implementation notes
```

### Phân lớp xử lý

- `Views / frontend`: render giao diện storefront và dashboard admin
- `Controllers`: nhận request, điều phối luồng, trả view hoặc JSON
- `Models`: tương tác database bằng PDO prepared statements
- `Services`: xử lý nghiệp vụ và AI-related logic như context, guardrail, progress, mutation workflow
- `Admin Dashboard`: vùng backoffice riêng cho vận hành
- `AI services`: tập trung các logic như actor resolution, context building, bridge integration, permission map, mutation draft và recommendation

### Tư duy triển khai

- Tránh để controller chứa toàn bộ business logic
- Tách riêng phần AI để không trộn lẫn với flow CRUD truyền thống
- Giữ quyền kiểm soát mutation ở backend, không để AI trở thành đường ghi dữ liệu tự do

## 5. Công nghệ sử dụng

Stack dưới đây phản ánh đúng những gì dự án đang dùng:

- PHP 8.x
- PHP MVC thuần
- MySQL / MariaDB
- PDO Prepared Statements
- HTML5 / CSS3
- Bootstrap 5
- JavaScript thuần
- Google OAuth 2.0
- Session-based authentication
- AI bridge integration cho chatbot / copilot

## 6. Các module nghiệp vụ

- `Products`: quản lý danh sách dịch vụ số, giá, mô tả, specs, ảnh và trạng thái hiển thị
- `Categories`: phân nhóm sản phẩm để storefront và admin quản lý dễ hơn
- `Orders`: lưu và theo dõi đơn hàng, trạng thái xử lý, tổng tiền, item đã mua
- `Users`: quản lý tài khoản, role, trạng thái, hồ sơ và các thao tác admin
- `Coupons`: tạo và quản lý mã giảm giá theo trạng thái, thời hạn và giới hạn sử dụng
- `Customer Feedback`: tiếp nhận góp ý/hỗ trợ sau bán từ phía khách hàng
- `Wallet / Transactions`: quản lý số dư và lịch sử giao dịch ví
- `Payments`: khu vực quản trị thanh toán/giao dịch trong dashboard
- `Settings`: các cấu hình hệ thống và thông tin website
- `Rank Management`: cấu hình ngưỡng/rule xếp hạng người dùng
- `Audit Log`: lưu vết hành động quản trị phục vụ kiểm tra và truy vết
- `SQL Manager`: công cụ hỗ trợ đọc dữ liệu và kiểm tra schema theo guardrail
- `AI Copilot`: lớp hỗ trợ AI cho storefront và admin dashboard

## 7. AI / Meow Copilot

AI là một phần quan trọng của dự án, nhưng được triển khai theo hướng có kiểm soát thay vì “chatbot nói chung chung”.

### AI hiện có trong hệ thống

- `Chatbot khách hàng`: hỗ trợ hỏi đáp, tư vấn sản phẩm, tra cứu ngữ cảnh cơ bản và nhận feedback
- `Admin AI Copilot`: hỗ trợ đọc dữ liệu shop, trả lời câu hỏi vận hành, gợi ý bán hàng và xử lý một số mutation backoffice theo quyền

### Những điểm đáng chú ý

- Role-aware behavior:
  - phân biệt `guest`, `customer`, `admin`, `staff`
  - thay đổi giọng điệu, phạm vi dữ liệu và quyền thao tác theo actor thực tế
- Data-aware:
  - sử dụng dữ liệu thật từ các model của shop
  - ưu tiên fast-path backend cho các câu hỏi rõ ràng
- Guardrail-aware:
  - không cho AI mutation bừa
  - dùng `permission + preview + confirm + audit + module health`
- Context-aware:
  - có admin context builder, session persistence, progress state và freshness ngắn hạn

### Giới hạn hiện tại

- Một số module rủi ro cao vẫn được giữ ở `preview-only` hoặc `read-only`
- Các câu hỏi về lợi nhuận sâu, margin hoặc capacity chưa thể trả lời đầy đủ nếu schema chưa có đủ dữ liệu nền
- AI không thay thế logic nghiệp vụ chính; mọi ghi dữ liệu vẫn phải đi qua backend rule hiện có

## 8. Điểm nhấn kỹ thuật cho học thuật và CV

Đây là những điểm có giá trị khi dùng dự án cho báo cáo, portfolio hoặc phỏng vấn:

- Xây dựng một hệ thống web bán dịch vụ số có cả storefront và backoffice
- Tổ chức code theo `Controller / Model / Service` thay vì chỉ ghép trực tiếp vào view/controller
- Thiết kế role-based access cho khách hàng, admin và staff
- Quản lý session, CSRF, rate limit, 2FA và các thao tác nhạy cảm
- Mô hình hóa dữ liệu nghiệp vụ tương đối đầy đủ cho e-commerce dạng digital services
- Thiết kế admin dashboard với các module CRUD, thống kê và công cụ vận hành
- Tích hợp AI theo hai ngữ cảnh thực tế: hỗ trợ khách hàng và copilot nội bộ
- Áp dụng guardrail cho AI mutation: permission, preview, confirm, execute, audit
- Theo dõi module health để giảm rủi ro thao tác vào dữ liệu/schemas không an toàn
- Tư duy cloud-first trong định vị sản phẩm, recommendation và homepage focus

### Giá trị học thuật

- Thể hiện khả năng phân tích và mô hình hóa nghiệp vụ
- Cho thấy cách chuyển từ yêu cầu chức năng sang hệ thống có cấu trúc
- Có thể dùng làm minh chứng cho các chủ đề:
  - phân tích thiết kế hệ thống thông tin
  - lập trình web MVC
  - quản lý dữ liệu và phân quyền
  - tích hợp AI vào ứng dụng quản trị

### Giá trị cho CV / tuyển dụng

- Thể hiện năng lực full-stack theo hướng sản phẩm
- Có minh chứng về dashboard, CRUD, auth, security, AI integration và service-layer design
- Cho thấy khả năng nghĩ theo hệ thống thay vì chỉ làm UI đơn lẻ
- Phù hợp để trình bày ở các vị trí:
  - backend PHP developer
  - full-stack web developer
  - software engineer làm sản phẩm nội bộ / dashboard / admin tools

## 9. Cài đặt và chạy local

### Yêu cầu

- PHP 8.x
- MySQL hoặc MariaDB
- Apache (XAMPP hoặc Laragon)

### Các bước cơ bản

1. Clone hoặc copy source vào web root.

```bash
git clone <your-repo-url>
cd ZenoxDigital
```

2. Tạo database và import schema.

- Import file `database/schema.sql`

3. Tạo file môi trường.

- Copy `.env.example` thành `.env`
- Cập nhật tối thiểu:
  - `APP_URL`
  - `DB_HOST`
  - `DB_PORT`
  - `DB_NAME`
  - `DB_USER`
  - `DB_PASS`
  - `UPLOAD_PATH`

4. Nếu dùng AI bridge, cấu hình thêm:

- `AI_ENABLED=true`
- `AI_PROVIDER=bridge`
- `AI_BRIDGE_URL=...`
- `AI_BRIDGE_KEY=...`
- `AI_BRIDGE_ALLOW_LOCAL_FALLBACK=true|false`

Nếu chưa dùng AI bridge thật, có thể:

- đặt `AI_ENABLED=false` để tắt AI
- hoặc giữ `AI_ENABLED=true` và dùng `AI_BRIDGE_ALLOW_LOCAL_FALLBACK=true` trong môi trường phát triển

5. Nếu dùng Google OAuth, cấu hình:

- `GOOGLE_OAUTH_ENABLED=true`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI`

6. Bật Apache `mod_rewrite` và chạy project tại:

```text
http://localhost/ZenoxDigital/public
```

### Tài khoản demo

Nếu dùng seed/schema local mặc định hiện tại:

- `admin@local.test / 123456`
- `user@local.test / 123456`

## 10. Hướng dẫn sử dụng nhanh

### Storefront

1. Vào trang chủ để xem nhóm dịch vụ chính
2. Mở danh sách sản phẩm để lọc theo nhu cầu
3. Xem chi tiết gói dịch vụ
4. Đăng ký hoặc đăng nhập để thao tác với tài khoản
5. Dùng chatbot nếu cần hỏi nhanh hoặc gửi feedback

### Admin Dashboard

1. Đăng nhập bằng tài khoản admin
2. Truy cập dashboard tại `/admin`
3. Kiểm tra các module như sản phẩm, đơn hàng, coupon, người dùng, feedback
4. Mở `Meow Copilot` để:
   - hỏi dữ liệu vận hành
   - xem recommendation
   - preview và xác nhận một số thao tác quản trị

## 11. Hình ảnh minh họa

Phần này nên được bổ sung screenshot khi đưa dự án lên GitHub hoặc dùng cho báo cáo.

Gợi ý các ảnh nên chụp:

- Homepage / storefront
- Trang danh sách sản phẩm
- Trang chi tiết sản phẩm
- Admin dashboard overview
- Product management
- Meow Copilot panel
- SQL Manager

Placeholder đề xuất:

```text
docs/screenshots/homepage.png
docs/screenshots/admin-dashboard.png
docs/screenshots/meow-copilot.png
docs/screenshots/sql-manager.png
```

## 12. Roadmap

- Hoàn thiện sâu hơn payment management và flow giao dịch
- Mở rộng AI mutation cho nhiều tác vụ quản trị hơn nhưng vẫn giữ guardrail
- Bổ sung analytics và reporting sâu hơn cho dashboard
- Tăng chất lượng recommendation cho nhóm Cloud/VPS
- Mở rộng health monitoring và observability cho các module dữ liệu
- Chuẩn hóa testing, static analysis và CI workflow nếu public repo

## 13. Đóng góp

Hiện tại dự án phù hợp nhất cho mục đích:

- học tập
- nghiên cứu đồ án
- portfolio cá nhân

Nếu bạn muốn dùng hoặc mở rộng dự án:

- tạo issue để trao đổi hướng phát triển
- fork repo cho mục đích học tập
- bổ sung test hoặc refactor theo từng module thay vì sửa dồn một lần

## 14. Giấy phép

Repo hiện chưa gắn file `LICENSE` riêng. Nếu đưa public trên GitHub để cho phép tái sử dụng rõ ràng, nên bổ sung một giấy phép chính thức như `MIT` hoặc `Apache-2.0`.

---

ZenoxDigital không chỉ là một giao diện bán hàng, mà là một bài tập xây dựng hệ thống tương đối hoàn chỉnh: có storefront, có backoffice, có quản trị dữ liệu, có bảo mật nền, và có AI Copilot được ràng buộc bởi dữ liệu thật, quyền hạn và guardrail vận hành.
