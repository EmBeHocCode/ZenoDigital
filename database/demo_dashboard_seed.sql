START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;

DELETE cf
FROM customer_feedback cf
WHERE cf.feedback_code LIKE 'FDB-PPT-%';

DELETE oi
FROM order_items oi
INNER JOIN orders o ON o.id = oi.order_id
WHERE o.order_code LIKE 'ORD-PPT-%';

DELETE FROM orders
WHERE order_code LIKE 'ORD-PPT-%';

DELETE FROM coupons
WHERE code LIKE 'PPT%';

DELETE FROM users
WHERE username LIKE 'ppt_demo_%';

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO users (
    role_id,
    full_name,
    username,
    email,
    phone,
    gender,
    birth_date,
    address,
    wallet_balance,
    password,
    avatar,
    status,
    created_at,
    updated_at
)
VALUES
    (2, 'Le Minh Quan', 'ppt_demo_quan', 'ppt.quan@zenodigital.local', '0901112201', 'male', '2000-04-12', 'Thu Duc, TP.HCM', 250000.00, '$2y$10$sp4LxH/XH3o2VV7tvxUEreBnT8T8S5Q/rbW4eUEDzcJxm4vuWQ3Qe', NULL, 'active', '2026-01-06 09:15:00', '2026-01-06 09:15:00'),
    (2, 'Tran Khanh Vy', 'ppt_demo_vy', 'ppt.vy@zenodigital.local', '0901112202', 'female', '2001-08-20', 'Go Vap, TP.HCM', 180000.00, '$2y$10$sp4LxH/XH3o2VV7tvxUEreBnT8T8S5Q/rbW4eUEDzcJxm4vuWQ3Qe', NULL, 'active', '2026-01-14 15:40:00', '2026-01-14 15:40:00'),
    (2, 'Pham Hoang Nam', 'ppt_demo_nam', 'ppt.nam@zenodigital.local', '0901112203', 'male', '1999-11-02', 'Bien Hoa, Dong Nai', 320000.00, '$2y$10$sp4LxH/XH3o2VV7tvxUEreBnT8T8S5Q/rbW4eUEDzcJxm4vuWQ3Qe', NULL, 'active', '2026-02-03 10:05:00', '2026-02-03 10:05:00'),
    (2, 'Nguyen Duc Long', 'ppt_demo_long', 'ppt.long@zenodigital.local', '0901112204', 'male', '1998-01-17', 'Da Nang', 95000.00, '$2y$10$sp4LxH/XH3o2VV7tvxUEreBnT8T8S5Q/rbW4eUEDzcJxm4vuWQ3Qe', NULL, 'active', '2026-02-17 14:25:00', '2026-02-17 14:25:00'),
    (2, 'Hoang Gia Han', 'ppt_demo_han', 'ppt_demo_han@zenodigital.local', '0901112205', 'female', '2002-06-28', 'Can Tho', 410000.00, '$2y$10$sp4LxH/XH3o2VV7tvxUEreBnT8T8S5Q/rbW4eUEDzcJxm4vuWQ3Qe', NULL, 'active', '2026-03-08 11:10:00', '2026-03-08 11:10:00'),
    (2, 'Bui Gia Khanh', 'ppt_demo_khanh', 'ppt.khanh@zenodigital.local', '0901112206', 'male', '2001-12-09', 'Thu Dau Mot, Binh Duong', 150000.00, '$2y$10$sp4LxH/XH3o2VV7tvxUEreBnT8T8S5Q/rbW4eUEDzcJxm4vuWQ3Qe', NULL, 'active', '2026-03-28 08:45:00', '2026-03-28 08:45:00');

INSERT INTO coupons (
    code,
    description,
    discount_percent,
    max_uses,
    used_count,
    expires_at,
    status,
    created_at,
    updated_at
)
VALUES
    ('PPTWELCOME10', 'Coupon onboarding cho khách mới mua gói cloud đầu tiên', 10, 50, 6, '2026-04-10 23:59:59', 'active', '2026-03-18 09:00:00', '2026-03-31 08:00:00'),
    ('PPTCLOUD15', 'Coupon chiến dịch ngắn hạn cho nhóm Cloud VPS / Cloud Server', 15, 25, 18, '2026-04-03 23:59:59', 'active', '2026-03-27 10:00:00', '2026-03-31 08:00:00'),
    ('PPTMARCHDONE', 'Coupon tháng 3 đã ngừng áp dụng sau khi đạt giới hạn sử dụng', 12, 40, 40, '2026-03-20 23:59:59', 'inactive', '2026-03-01 08:30:00', '2026-03-21 09:30:00');

INSERT INTO orders (
    user_id,
    order_code,
    total_amount,
    status,
    created_at,
    updated_at
)
VALUES
    ((SELECT id FROM users WHERE username = 'ppt_demo_quan' LIMIT 1), 'ORD-PPT-260109-A1', 179000.00, 'paid', '2026-01-09 09:30:00', '2026-01-09 09:35:00'),
    ((SELECT id FROM users WHERE username = 'ppt_demo_vy' LIMIT 1), 'ORD-PPT-260118-A2', 329000.00, 'completed', '2026-01-18 14:20:00', '2026-01-18 17:00:00'),
    ((SELECT id FROM users WHERE username = 'ppt_demo_nam' LIMIT 1), 'ORD-PPT-260127-A3', 449000.00, 'paid', '2026-01-27 20:15:00', '2026-01-27 20:40:00'),
    ((SELECT id FROM users WHERE username = 'ppt_demo_quan' LIMIT 1), 'ORD-PPT-260206-B1', 508000.00, 'completed', '2026-02-06 08:50:00', '2026-02-06 10:00:00'),
    ((SELECT id FROM users WHERE username = 'ppt_demo_long' LIMIT 1), 'ORD-PPT-260214-B2', 499000.00, 'processing', '2026-02-14 16:45:00', '2026-02-14 18:00:00'),
    ((SELECT id FROM users WHERE username = 'ppt_demo_han' LIMIT 1), 'ORD-PPT-260222-B3', 629000.00, 'paid', '2026-02-22 11:10:00', '2026-02-22 11:50:00'),
    ((SELECT id FROM users WHERE username = 'ppt_demo_han' LIMIT 1), 'ORD-PPT-260226-B4', 729000.00, 'completed', '2026-02-26 13:35:00', '2026-02-26 16:05:00'),
    ((SELECT id FROM users WHERE username = 'ppt_demo_vy' LIMIT 1), 'ORD-PPT-260305-C1', 699000.00, 'completed', '2026-03-05 09:05:00', '2026-03-05 11:40:00'),
    ((SELECT id FROM users WHERE username = 'ppt_demo_nam' LIMIT 1), 'ORD-PPT-260314-C2', 658000.00, 'paid', '2026-03-14 15:10:00', '2026-03-14 15:30:00'),
    ((SELECT id FROM users WHERE username = 'ppt_demo_long' LIMIT 1), 'ORD-PPT-260321-C3', 200000.00, 'cancelled', '2026-03-21 10:00:00', '2026-03-21 10:20:00'),
    ((SELECT id FROM users WHERE username = 'ppt_demo_long' LIMIT 1), 'ORD-PPT-260324-C4', 678000.00, 'completed', '2026-03-24 19:25:00', '2026-03-24 21:00:00'),
    ((SELECT id FROM users WHERE username = 'ppt_demo_han' LIMIT 1), 'ORD-PPT-260330-C5', 629000.00, 'pending', '2026-03-30 10:40:00', '2026-03-30 10:40:00'),
    ((SELECT id FROM users WHERE username = 'ppt_demo_khanh' LIMIT 1), 'ORD-PPT-260331-C6', 329000.00, 'pending', '2026-03-31 08:20:00', '2026-03-31 08:20:00');

INSERT INTO order_items (
    order_id,
    product_id,
    quantity,
    unit_price,
    total_price,
    created_at
)
VALUES
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260109-A1' LIMIT 1), 6, 1, 179000.00, 179000.00, '2026-01-09 09:30:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260118-A2' LIMIT 1), 7, 1, 329000.00, 329000.00, '2026-01-18 14:20:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260127-A3' LIMIT 1), 8, 1, 449000.00, 449000.00, '2026-01-27 20:15:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260206-B1' LIMIT 1), 6, 1, 179000.00, 179000.00, '2026-02-06 08:50:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260206-B1' LIMIT 1), 7, 1, 329000.00, 329000.00, '2026-02-06 08:50:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260214-B2' LIMIT 1), 9, 1, 499000.00, 499000.00, '2026-02-14 16:45:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260222-B3' LIMIT 1), 10, 1, 629000.00, 629000.00, '2026-02-22 11:10:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260226-B4' LIMIT 1), 11, 1, 729000.00, 729000.00, '2026-02-26 13:35:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260305-C1' LIMIT 1), 12, 1, 699000.00, 699000.00, '2026-03-05 09:05:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260314-C2' LIMIT 1), 7, 2, 329000.00, 658000.00, '2026-03-14 15:10:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260321-C3' LIMIT 1), 5, 1, 200000.00, 200000.00, '2026-03-21 10:00:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260324-C4' LIMIT 1), 6, 1, 179000.00, 179000.00, '2026-03-24 19:25:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260324-C4' LIMIT 1), 9, 1, 499000.00, 499000.00, '2026-03-24 19:25:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260330-C5' LIMIT 1), 10, 1, 629000.00, 629000.00, '2026-03-30 10:40:00'),
    ((SELECT id FROM orders WHERE order_code = 'ORD-PPT-260331-C6' LIMIT 1), 7, 1, 329000.00, 329000.00, '2026-03-31 08:20:00');

INSERT INTO customer_feedback (
    feedback_code,
    user_id,
    order_id,
    product_id,
    ai_session_id,
    source,
    page_type,
    feedback_type,
    sentiment,
    severity,
    rating,
    status,
    needs_follow_up,
    message,
    admin_note,
    customer_name,
    customer_email,
    created_at,
    updated_at
)
VALUES
    (
        'FDB-PPT-260130-01',
        (SELECT id FROM users WHERE username = 'ppt_demo_quan' LIMIT 1),
        (SELECT id FROM orders WHERE order_code = 'ORD-PPT-260109-A1' LIMIT 1),
        6,
        'ppt-session-260130-01',
        'storefront_header',
        'storefront',
        'support',
        'neutral',
        'low',
        4,
        'closed',
        0,
        'Khách hàng hỏi thêm về thời gian bàn giao gói cloud starter sau khi thanh toán.',
        'Đã phản hồi qua email, khách xác nhận đã nhận được thông tin.',
        'Le Minh Quan',
        'ppt.quan@zenodigital.local',
        '2026-01-30 10:10:00',
        '2026-01-30 14:30:00'
    ),
    (
        'FDB-PPT-260224-02',
        (SELECT id FROM users WHERE username = 'ppt_demo_vy' LIMIT 1),
        (SELECT id FROM orders WHERE order_code = 'ORD-PPT-260222-B3' LIMIT 1),
        10,
        'ppt-session-260224-02',
        'ai_widget',
        'product',
        'product',
        'positive',
        'low',
        5,
        'resolved',
        0,
        'Khách hài lòng với hiệu năng của gói High CPU sau khi dùng cho backend automation.',
        'Giữ lại làm feedback tích cực minh họa cho nhóm cloud.',
        'Tran Khanh Vy',
        'ppt.vy@zenodigital.local',
        '2026-02-24 09:20:00',
        '2026-02-24 11:00:00'
    ),
    (
        'FDB-PPT-260312-03',
        (SELECT id FROM users WHERE username = 'ppt_demo_long' LIMIT 1),
        (SELECT id FROM orders WHERE order_code = 'ORD-PPT-260214-B2' LIMIT 1),
        9,
        'ppt-session-260312-03',
        'ai_widget',
        'profile',
        'payment',
        'negative',
        'high',
        2,
        'new',
        1,
        'Khách phản ánh cần xác nhận thanh toán nhanh hơn khi nạp ví để mua gói NVMe.',
        NULL,
        'Nguyen Duc Long',
        'ppt.long@zenodigital.local',
        '2026-03-12 20:15:00',
        '2026-03-12 20:15:00'
    ),
    (
        'FDB-PPT-260318-04',
        (SELECT id FROM users WHERE username = 'ppt_demo_han' LIMIT 1),
        NULL,
        10,
        'ppt-session-260318-04',
        'storefront_header',
        'storefront',
        'general',
        'neutral',
        'medium',
        3,
        'reviewing',
        1,
        'Khách muốn biết có thể nâng cấp từ High CPU lên Ryzen Pro mà giữ dữ liệu hay không.',
        'Đang chuẩn hóa quy trình tư vấn upgrade giữa các gói cloud.',
        'Hoang Gia Han',
        'ppt_demo_han@zenodigital.local',
        '2026-03-18 16:40:00',
        '2026-03-18 18:00:00'
    ),
    (
        'FDB-PPT-260330-05',
        (SELECT id FROM users WHERE username = 'ppt_demo_khanh' LIMIT 1),
        (SELECT id FROM orders WHERE order_code = 'ORD-PPT-260331-C6' LIMIT 1),
        7,
        'ppt-session-260330-05',
        'ai_widget',
        'support',
        'support',
        'negative',
        'medium',
        2,
        'new',
        1,
        'Khách đề nghị có thêm gợi ý cấu hình ngay trong lúc chọn gói VPS Ryzen Basic.',
        NULL,
        'Bui Gia Khanh',
        'ppt.khanh@zenodigital.local',
        '2026-03-30 21:05:00',
        '2026-03-30 21:05:00'
    );

COMMIT;
