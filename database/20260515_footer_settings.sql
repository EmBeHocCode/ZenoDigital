INSERT INTO settings (setting_key, setting_value, updated_at)
VALUES
('telegram_url', '', NOW()),
('youtube_url', '', NOW()),
('contact_email_secondary', '', NOW()),
('footer_cta_kicker', 'Cloud VPS cho học tập và vận hành', NOW()),
('footer_cta_title', 'Sẵn sàng triển khai Cloud VPS chỉ từ 35.000đ/tháng?', NOW()),
('footer_cta_description', 'Phù hợp cho học tập, website, bot, game server và các dự án cá nhân.', NOW()),
('footer_cta_primary_label', 'Xem gói VPS', NOW()),
('footer_cta_primary_url', '/#cloud-vps-plans', NOW()),
('footer_cta_secondary_label', 'Liên hệ tư vấn', NOW()),
('footer_cta_secondary_url', '', NOW()),
('footer_social_note', 'Theo dõi kênh hỗ trợ để nhận cập nhật dịch vụ, ưu đãi cloud và thông báo vận hành.', NOW()),
('footer_bottom_note', 'Cloud VPS, dịch vụ số và hỗ trợ kỹ thuật cho sản phẩm thực tế.', NOW()),
('footer_product_links', 'VPS Giá Rẻ|/#student-vps-plans\nCloud VPS|/#cloud-vps-plans\nCloud Server|products?q=server\nGame Server|products?q=game\nAI GPU|products?q=gpu\nSIM Số|products?q=sim', NOW()),
('footer_support_links', 'Hướng dẫn sử dụng|/#vps-guides\nFAQ|/#faq\nTicket hỗ trợ|#contact\nLiên hệ|#contact', NOW()),
('footer_policy_links', 'Điều khoản dịch vụ|/#faq\nChính sách bảo mật|/#faq\nChính sách hoàn tiền|/#payment-methods', NOW()),
('footer_service_commitments', 'Kích hoạt tự động trong vài phút\nBảo mật nhiều lớp\nHỗ trợ kỹ thuật 24/7\nHạ tầng ổn định cho production', NOW()),
('footer_payment_methods', 'VietQR|fas fa-qrcode\nMoMo|fas fa-wallet\nZaloPay|fas fa-bolt\nVisa|fab fa-cc-visa\nMastercard|fab fa-cc-mastercard', NOW())
ON DUPLICATE KEY UPDATE updated_at = updated_at;
