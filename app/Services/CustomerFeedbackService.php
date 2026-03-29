<?php

namespace App\Services;

use App\Models\CustomerFeedback;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class CustomerFeedbackService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function capture(array $payload, array $actorContext, string $aiSessionId, array $options = []): array
    {
        $message = sanitize_text((string) ($payload['feedback_message'] ?? $payload['message'] ?? ''), 2000);
        if ($message === '') {
            throw new \InvalidArgumentException('Nội dung feedback không được để trống.');
        }

        $rating = validate_int_range($payload['rating'] ?? 0, 0, 5, 0);
        $productId = validate_int_range($payload['product_id'] ?? 0, 0, 999999999, 0);
        $source = validate_enum(
            sanitize_text((string) ($options['source'] ?? $payload['source'] ?? 'ai_widget'), 40),
            ['ai_widget', 'storefront_header'],
            'ai_widget'
        );
        $pageType = validate_enum(
            sanitize_text((string) ($options['page_type'] ?? $payload['page_type'] ?? ($source === 'storefront_header' ? 'storefront_header' : 'storefront')), 40),
            ['storefront', 'product', 'profile', 'support', 'other', 'storefront_header'],
            $source === 'storefront_header' ? 'storefront_header' : 'storefront'
        );
        $feedbackType = $this->detectFeedbackType(
            sanitize_text((string) ($payload['feedback_type'] ?? ''), 40),
            $message
        );
        $sentiment = $this->detectSentiment(
            sanitize_text((string) ($payload['sentiment'] ?? ''), 20),
            $message,
            $rating
        );
        $severity = $this->detectSeverity(
            sanitize_text((string) ($payload['severity'] ?? ''), 20),
            $feedbackType,
            $sentiment,
            $message
        );
        $needsFollowUp = $this->shouldFollowUp($feedbackType, $sentiment, $severity);
        $trustedActorId = (int) ($actorContext['actor_id'] ?? 0);
        $trustedActorType = (string) ($actorContext['actor_type'] ?? 'guest');
        $requireGuestContact = !empty($options['require_guest_contact']);
        $allowGuestFeedback = !array_key_exists('allow_guest_feedback', $options) || !empty($options['allow_guest_feedback']);

        $productModel = new Product($this->config);
        $orderModel = new Order($this->config);
        $userModel = new User($this->config);
        $feedbackModel = new CustomerFeedback($this->config);

        $product = $productId > 0 ? $productModel->find($productId) : null;
        if (!$product) {
            $productId = 0;
        }

        $user = ($trustedActorId > 0 && in_array($trustedActorType, ['customer', 'admin', 'staff', 'management'], true))
            ? $userModel->find($trustedActorId)
            : null;
        $order = ($user && $productId > 0)
            ? $orderModel->latestCompletedOrderForUserProduct((int) ($user['id'] ?? 0), $productId)
            : null;
        $guestName = $user ? '' : sanitize_text((string) ($payload['full_name'] ?? $payload['customer_name'] ?? $payload['name'] ?? ''), 120);
        $guestEmail = $user ? '' : sanitize_text((string) ($payload['email'] ?? $payload['customer_email'] ?? ''), 120);

        if (!$user && !$allowGuestFeedback) {
            throw new \InvalidArgumentException('Bạn cần đăng nhập để gửi feedback này.');
        }

        if (!$user && $requireGuestContact) {
            if ($guestName === '') {
                throw new \InvalidArgumentException('Vui lòng nhập họ tên để gửi feedback.');
            }

            if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Email khách vãng lai không hợp lệ.');
            }
        }

        $created = $feedbackModel->create([
            'user_id' => $user ? (int) ($user['id'] ?? 0) : null,
            'order_id' => (int) ($order['id'] ?? 0) > 0 ? (int) $order['id'] : null,
            'product_id' => $productId > 0 ? $productId : null,
            'ai_session_id' => sanitize_text($aiSessionId, 120),
            'source' => $source,
            'page_type' => $pageType,
            'feedback_type' => $feedbackType,
            'sentiment' => $sentiment,
            'severity' => $severity,
            'rating' => $rating > 0 ? $rating : null,
            'status' => 'new',
            'needs_follow_up' => $needsFollowUp ? 1 : 0,
            'message' => $message,
            'admin_note' => null,
            'customer_name' => $user
                ? sanitize_text((string) ($user['full_name'] ?? ''), 120)
                : ($guestName !== '' ? $guestName : null),
            'customer_email' => $user
                ? sanitize_text((string) ($user['email'] ?? ''), 120)
                : ($guestEmail !== '' ? $guestEmail : null),
        ]);

        if ($created === false) {
            throw new \RuntimeException('Không thể lưu feedback vào hệ thống.');
        }

        return [
            'id' => (int) ($created['id'] ?? 0),
            'feedback_code' => (string) ($created['feedback_code'] ?? ''),
            'message' => $message,
            'feedback_type' => $feedbackType,
            'sentiment' => $sentiment,
            'severity' => $severity,
            'rating' => $rating > 0 ? $rating : null,
            'needs_follow_up' => $needsFollowUp,
            'product_id' => $productId > 0 ? $productId : null,
            'product_name' => (string) ($product['name'] ?? ''),
            'order_id' => (int) ($order['id'] ?? 0),
            'order_code' => (string) ($order['order_code'] ?? ''),
            'source' => $source,
            'page_type' => $pageType,
            'customer_name' => $user
                ? sanitize_text((string) ($user['full_name'] ?? ''), 120)
                : $guestName,
            'customer_email' => $user
                ? sanitize_text((string) ($user['email'] ?? ''), 120)
                : $guestEmail,
        ];
    }

    private function detectFeedbackType(string $explicitType, string $message): string
    {
        $allowed = ['general', 'product', 'delivery', 'payment', 'support', 'system_bug'];
        if (in_array($explicitType, $allowed, true)) {
            return $explicitType;
        }

        $message = mb_strtolower($message, 'UTF-8');

        return match (true) {
            $this->containsAny($message, ['thanh toán', 'trừ tiền', 'ví', 'nạp tiền', 'hoàn tiền']) => 'payment',
            $this->containsAny($message, ['bàn giao', 'chậm', 'kích hoạt', 'chưa nhận', 'delay']) => 'delivery',
            $this->containsAny($message, ['lỗi hệ thống', 'bug', '500', '404', 'trắng trang', 'không tải được', 'không bấm được', 'sập']) => 'system_bug',
            $this->containsAny($message, ['lỗi', 'ticket', 'hỗ trợ', 'support', 'không dùng được', 'không truy cập']) => 'support',
            $this->containsAny($message, ['gói', 'cấu hình', 'sản phẩm', 'vps', 'server']) => 'product',
            default => 'general',
        };
    }

    private function detectSentiment(string $explicitSentiment, string $message, int $rating): string
    {
        if (in_array($explicitSentiment, ['positive', 'neutral', 'negative'], true)) {
            return $explicitSentiment;
        }

        if ($rating > 0) {
            return match (true) {
                $rating >= 4 => 'positive',
                $rating <= 2 => 'negative',
                default => 'neutral',
            };
        }

        $message = mb_strtolower($message, 'UTF-8');

        if ($this->containsAny($message, ['không hài lòng', 'tệ', 'rất chậm', 'lỗi', 'thất vọng', 'không ổn', 'khó chịu', 'không dùng được', 'bị trừ tiền'])) {
            return 'negative';
        }

        if ($this->containsAny($message, ['hài lòng', 'ổn', 'tốt', 'nhanh', 'tuyệt vời', 'mượt', 'ok', 'ổn áp'])) {
            return 'positive';
        }

        return 'neutral';
    }

    private function detectSeverity(string $explicitSeverity, string $feedbackType, string $sentiment, string $message): string
    {
        if (in_array($explicitSeverity, ['low', 'medium', 'high'], true)) {
            return $explicitSeverity;
        }

        $message = mb_strtolower($message, 'UTF-8');

        if ($this->containsAny($message, ['mất dữ liệu', 'khẩn', 'gấp', 'không truy cập', 'sập', 'downtime', 'lỗi thanh toán'])) {
            return 'high';
        }

        if ($feedbackType === 'system_bug') {
            return $sentiment === 'negative' ? 'high' : 'medium';
        }

        if ($sentiment === 'negative' || in_array($feedbackType, ['payment', 'support'], true)) {
            return 'medium';
        }

        return 'low';
    }

    private function shouldFollowUp(string $feedbackType, string $sentiment, string $severity): bool
    {
        if ($severity === 'high') {
            return true;
        }

        if ($sentiment === 'negative') {
            return true;
        }

        return in_array($feedbackType, ['payment', 'support', 'delivery', 'system_bug'], true);
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            $needle = trim((string) $needle);
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
