# Security Hardening Notes

## Đã harden trong source

- SQL Injection:
  - Giữ toàn bộ truy vấn ở `app/Models` theo prepared statements (`PDO::prepare`, bind params).
  - Không dùng nối chuỗi trực tiếp từ input vào SQL order/filter động.

- XSS:
  - Duy trì helper `e()` cho output HTML.
  - Các input chính được sanitize/giới hạn độ dài trước khi lưu.

- CSRF:
  - Thêm middleware CSRF toàn cục cho mọi request `POST` tại `public/index.php`.
  - Bổ sung token hidden (`csrf_field()`) cho toàn bộ form POST.

- Session security:
  - `session.use_strict_mode=1`, `cookie_httponly=true`, `use_only_cookies=1`, `SameSite=Lax`.
  - Tự động timeout session theo `SESSION_IDLE_TIMEOUT`.
  - Regenerate session ID khi login; logout hủy session/cookie sạch.

- Password security:
  - Dùng `password_hash` + `password_verify`.
  - Bổ sung chính sách mật khẩu mạnh hơn (>=8, có hoa/thường/số).
  - Tự migrate hash cũ khi user login (`password_needs_rehash`).

- AuthN/AuthZ:
  - Route nhạy cảm vẫn dùng `requireAuth` / `requireAdmin`.
  - Bổ sung validate ID/trạng thái ở các action admin quan trọng.

- Input validation:
  - Thêm helper validate số/range/enum/text.
  - Ràng buộc filter/search/status/role/phone/url/email ở server side.

- Upload security:
  - Upload ảnh xác thực cả extension + MIME thật (`finfo`).
  - Đổi tên ngẫu nhiên bằng `random_bytes`.
  - Giới hạn dung lượng theo config.
  - Chặn executable trong `public/uploads/.htaccess`.

- Error handling:
  - Ẩn chi tiết lỗi ở production (`APP_DEBUG=false`).
  - Log lỗi server-side, trả message chung cho client.

- Security headers:
  - Thêm CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
  - Bật HSTS khi HTTPS.
  - No-cache cho phiên đăng nhập.

- Brute force / rate limit:
  - Rate limiting theo IP cho login/register/forgot-password.

- Config security:
  - Hỗ trợ load `.env` và dùng env var cho thông tin nhạy cảm.
  - Thêm file mẫu `.env.example`.

- Apache hardening:
  - Cập nhật `.htaccess` root/public: chặn truy cập file nhạy cảm, tắt directory listing.

## Giới hạn hiện tại (chưa thể tuyệt đối)

- Rate limit đang lưu bằng session/in-memory, chưa dùng Redis/DB tập trung.
- CSP cho phép `'unsafe-inline'` để không làm vỡ UI hiện tại; production nên tiến tới nonce/hash.
- Chưa có module thanh toán/coupon hoàn chỉnh để harden toàn bộ luồng integrity giá.
- Đã có 2FA TOTP cho account settings + login challenge.
- Chưa có backup codes, reset self-service hoặc cơ chế mã khôi phục khi người dùng mất thiết bị.

## Checklist deploy production

1. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_HTTPS_ONLY=true`.
2. Cấu hình HTTPS chuẩn + redirect HTTP -> HTTPS ở web server/reverse proxy.
3. Đặt `UPLOAD_PATH` đúng quyền ghi, không cho execute script.
4. Không commit file `.env` thật lên git.
5. Cấp quyền file tối thiểu (`644` file, `755` directory; tránh `777`).
6. Theo dõi log web/app, bật rotation và cảnh báo bất thường.
7. Backup DB định kỳ + kiểm thử khôi phục.
8. Nếu traffic cao, chuyển rate limit sang Redis.
9. Thêm WAF/CDN rule cho bot, brute-force và payload bất thường.
10. Test lại tất cả form POST sau khi đổi domain/base URL.
