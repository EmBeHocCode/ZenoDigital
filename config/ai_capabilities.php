<?php

return [
    'customer' => [
        'faq_support' => [
            'label' => 'Hỗ trợ FAQ',
            'description' => 'Trả lời câu hỏi cơ bản về sản phẩm, thanh toán và quy trình mua hàng.',
            'requires_admin' => false,
            'requires_financial_data' => false,
        ],
        'product_advisor' => [
            'label' => 'Tư vấn sản phẩm',
            'description' => 'Gợi ý sản phẩm phù hợp theo nhu cầu khách truy cập.',
            'requires_admin' => false,
            'requires_financial_data' => false,
        ],
        'order_lookup' => [
            'label' => 'Tra cứu đơn hàng',
            'description' => 'Tra cứu trạng thái đơn hàng bằng dữ liệu thật, có ràng buộc ownership và xác minh an toàn.',
            'requires_admin' => false,
            'requires_financial_data' => false,
        ],
        'wallet_support' => [
            'label' => 'Hỗ trợ ví và tài khoản',
            'description' => 'Giải thích số dư, tổng nạp, đã chi và hoạt động ví bằng dữ liệu thật của tài khoản.',
            'requires_admin' => false,
            'requires_financial_data' => false,
        ],
        'purchase_history' => [
            'label' => 'Lịch sử mua',
            'description' => 'Tóm tắt các đơn gần đây của chính tài khoản đã đăng nhập.',
            'requires_admin' => false,
            'requires_financial_data' => false,
        ],
    ],
    'admin' => [
        'dashboard_summary' => [
            'label' => 'Tóm tắt dashboard',
            'description' => 'Tóm tắt KPI quản trị từ dữ liệu thật của shop.',
            'requires_admin' => true,
            'requires_financial_data' => false,
        ],
        'order_overview' => [
            'label' => 'Tổng quan đơn hàng',
            'description' => 'Tóm tắt đơn chờ xử lý, trạng thái đơn và đơn mới gần đây.',
            'requires_admin' => true,
            'requires_financial_data' => false,
        ],
        'product_overview' => [
            'label' => 'Tổng quan sản phẩm',
            'description' => 'Tóm tắt sản phẩm mới, sản phẩm bán chạy và xu hướng danh mục.',
            'requires_admin' => true,
            'requires_financial_data' => false,
        ],
        'coupon_overview' => [
            'label' => 'Tổng quan coupon',
            'description' => 'Đánh giá nhanh tình trạng coupon hoạt động và coupon sắp hết hạn.',
            'requires_admin' => true,
            'requires_financial_data' => false,
        ],
        'feedback_overview' => [
            'label' => 'Tổng quan feedback',
            'description' => 'Tóm tắt phản hồi mới, phản hồi tiêu cực và các case cần follow-up.',
            'requires_admin' => true,
            'requires_financial_data' => false,
        ],
        'pending_orders' => [
            'label' => 'Đơn chờ xử lý',
            'description' => 'Liệt kê đơn cần follow-up sớm.',
            'requires_admin' => true,
            'requires_financial_data' => false,
        ],
        'promotion_advisor' => [
            'label' => 'Tư vấn khuyến mãi',
            'description' => 'Đề xuất khuyến mãi sơ bộ nhưng phải tôn trọng guardrail tài chính.',
            'requires_admin' => true,
            'requires_financial_data' => true,
        ],
        'capacity_advisor' => [
            'label' => 'Tư vấn tồn kho/capacity',
            'description' => 'Đánh giá nguy cơ thiếu mã, thiếu ví hoặc thiếu slot capacity.',
            'requires_admin' => true,
            'requires_financial_data' => true,
        ],
    ],
];
