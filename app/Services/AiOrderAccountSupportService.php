<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;

class AiOrderAccountSupportService
{
    private array $config;
    private Order $orderModel;
    private User $userModel;
    private WalletTransaction $walletModel;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->orderModel = new Order($config);
        $this->userModel = new User($config);
        $this->walletModel = new WalletTransaction($config);
    }

    public function resolve(string $message, array $actor, array $languageAnalysis = []): array
    {
        if (!empty($actor['is_backoffice_actor'])) {
            return $this->emptyResult();
        }

        $interactionType = $this->detectInteractionType($message, $languageAnalysis);
        if ($interactionType === null) {
            return $this->emptyResult();
        }

        return match ($interactionType) {
            'order_lookup' => $this->resolveOrderLookup($message, $actor, $languageAnalysis),
            'purchase_history' => $this->resolvePurchaseHistory($actor),
            'wallet_summary' => $this->resolveWalletSummary($actor),
            default => $this->emptyResult(),
        };
    }

    private function resolveOrderLookup(string $message, array $actor, array $languageAnalysis): array
    {
        $texts = $this->collectLookupTexts($message, $languageAnalysis);
        $orderCode = $this->extractOrderCode($texts);
        $lookupEmail = $this->extractEmail($texts);
        $actorType = (string) ($actor['actor_type'] ?? 'guest');
        $actorId = (int) ($actor['actor_id'] ?? 0);

        if ($actorType === 'customer' && $actorId > 0) {
            $order = $orderCode !== ''
                ? $this->orderModel->findByOrderCodeForUser($orderCode, $actorId)
                : $this->latestUserOrder($actorId);

            if (!$order) {
                $structuredReply = $orderCode !== ''
                    ? 'Mình chưa thấy mã đơn này trong tài khoản hiện tại. Bạn kiểm tra lại mã đơn hoặc mở lịch sử mua để Meow dò đúng đơn cho bạn nhé.'
                    : 'Hiện tài khoản của bạn chưa có đơn hàng nào để mình tra cứu.';

                return $this->buildSupportResult(
                    'order_lookup',
                    'not_found',
                    $structuredReply,
                    [
                        'verification_state' => 'owner_only',
                        'lookup_mode' => 'authenticated_owner',
                        'order_code' => $orderCode,
                    ]
                );
            }

            $summary = implode("\n", [
                'Mình đã kiểm tra dữ liệu thật trong tài khoản của bạn:',
                '- Mã đơn: ' . (string) ($order['order_code'] ?? ''),
                '- Trạng thái: ' . $this->orderStatusLabel((string) ($order['status'] ?? '')),
                '- Thời gian: ' . (string) ($order['created_at'] ?? ''),
                '- Tổng tiền: ' . format_money((float) ($order['total_amount'] ?? 0)),
            ]);

            return $this->buildSupportResult(
                'order_lookup',
                'success',
                $summary,
                [
                    'verification_state' => 'owner_verified',
                    'lookup_mode' => 'authenticated_owner',
                    'order_code' => (string) ($order['order_code'] ?? ''),
                    'order' => $this->compactOrder($order),
                ],
                [
                    'lookup_verified' => true,
                    'structured_data_present' => true,
                ]
            );
        }

        if ($orderCode === '' || $lookupEmail === '') {
            return $this->buildSupportResult(
                'order_lookup',
                'verification_required',
                'Để tra cứu an toàn khi chưa đăng nhập, Meow cần cả mã đơn và email đặt hàng. Bạn có thể nhắn theo dạng `ORD-...` kèm `email@...` hoặc đăng nhập để mình xem đúng đơn của bạn.',
                [
                    'verification_state' => 'required',
                    'lookup_mode' => 'guest_verification',
                    'order_code' => $orderCode,
                    'lookup_email' => $lookupEmail,
                    'required_fields' => ['order_code', 'email'],
                ]
            );
        }

        $order = $this->orderModel->findByOrderCodeAndEmail($orderCode, $lookupEmail);
        if (!$order) {
            return $this->buildSupportResult(
                'order_lookup',
                'verification_failed',
                'Mình chưa thể xác minh đơn với thông tin hiện tại. Bạn kiểm tra lại mã đơn và email đặt hàng, hoặc đăng nhập để Meow tra đúng đơn của bạn nhé.',
                [
                    'verification_state' => 'failed',
                    'lookup_mode' => 'guest_verification',
                    'order_code' => $orderCode,
                    'lookup_email' => $lookupEmail,
                ]
            );
        }

        $summary = implode("\n", [
            'Mình đã xác minh đơn bằng mã đơn và email đặt hàng:',
            '- Mã đơn: ' . (string) ($order['order_code'] ?? ''),
            '- Trạng thái: ' . $this->orderStatusLabel((string) ($order['status'] ?? '')),
            '- Thời gian: ' . (string) ($order['created_at'] ?? ''),
            '- Tổng tiền: ' . format_money((float) ($order['total_amount'] ?? 0)),
        ]);

        return $this->buildSupportResult(
            'order_lookup',
            'success',
            $summary,
            [
                'verification_state' => 'guest_verified',
                'lookup_mode' => 'guest_verification',
                'order_code' => (string) ($order['order_code'] ?? ''),
                'order' => $this->compactOrder($order),
            ],
            [
                'lookup_verified' => true,
                'structured_data_present' => true,
            ]
        );
    }

    private function resolvePurchaseHistory(array $actor): array
    {
        $actorId = (int) ($actor['actor_id'] ?? 0);
        if ((string) ($actor['actor_type'] ?? 'guest') !== 'customer' || $actorId <= 0) {
            return $this->buildSupportResult(
                'purchase_history',
                'login_required',
                'Để xem lịch sử mua thật của tài khoản, bạn cần đăng nhập trước. Sau khi đăng nhập, Meow sẽ chỉ đọc đúng các đơn thuộc tài khoản của bạn.',
                [
                    'verification_state' => 'login_required',
                    'lookup_mode' => 'authenticated_only',
                ]
            );
        }

        $orders = array_slice($this->orderModel->byUser($actorId), 0, 5);
        if ($orders === []) {
            return $this->buildSupportResult(
                'purchase_history',
                'no_orders',
                'Hiện tài khoản của bạn chưa có lịch sử mua hàng nào để mình tóm tắt.',
                [
                    'verification_state' => 'owner_verified',
                    'lookup_mode' => 'authenticated_owner',
                    'orders' => [],
                ]
            );
        }

        $lines = ['Đây là các đơn gần nhất trong tài khoản của bạn:'];
        foreach ($orders as $order) {
            $lines[] = '- ' . (string) ($order['order_code'] ?? '')
                . ' | ' . $this->orderStatusLabel((string) ($order['status'] ?? ''))
                . ' | ' . format_money((float) ($order['total_amount'] ?? 0))
                . ' | ' . (string) ($order['created_at'] ?? '');
        }

        return $this->buildSupportResult(
            'purchase_history',
            'success',
            implode("\n", $lines),
            [
                'verification_state' => 'owner_verified',
                'lookup_mode' => 'authenticated_owner',
                'orders' => array_map(fn(array $order): array => $this->compactOrder($order), $orders),
            ],
            [
                'lookup_verified' => true,
                'structured_data_present' => true,
            ]
        );
    }

    private function resolveWalletSummary(array $actor): array
    {
        $actorId = (int) ($actor['actor_id'] ?? 0);
        if ((string) ($actor['actor_type'] ?? 'guest') !== 'customer' || $actorId <= 0) {
            return $this->buildSupportResult(
                'wallet_summary',
                'login_required',
                'Thông tin ví và lịch sử mua là dữ liệu cá nhân, nên bạn cần đăng nhập trước để Meow xem đúng tài khoản của bạn.',
                [
                    'verification_state' => 'login_required',
                    'lookup_mode' => 'authenticated_only',
                ]
            );
        }

        $user = $this->userModel->find($actorId);
        $wallet = $this->walletModel->summaryByUser($actorId);
        $transactions = array_slice($this->walletModel->recentByUser($actorId, 3), 0, 3);

        if (!$user) {
            return $this->buildSupportResult(
                'wallet_summary',
                'not_found',
                'Mình chưa đọc được dữ liệu tài khoản lúc này. Bạn thử tải lại trang hoặc đăng nhập lại giúp mình nhé.',
                [
                    'verification_state' => 'owner_verified',
                    'lookup_mode' => 'authenticated_owner',
                ]
            );
        }

        $summaryLines = [
            'Đây là thông tin tài khoản mình vừa đọc từ hệ thống:',
            '- Tài khoản: ' . (string) ($user['full_name'] ?? 'N/A'),
            '- Email: ' . (string) ($user['email'] ?? 'N/A'),
            '- Số dư hiện tại: ' . format_money((float) ($wallet['current_balance'] ?? 0)),
            '- Tổng nạp: ' . format_money((float) ($wallet['total_deposit'] ?? 0)),
            '- Đã chi: ' . format_money((float) ($wallet['display_spent'] ?? 0)),
        ];

        if (!empty($wallet['latest_activity_at'])) {
            $summaryLines[] = '- Hoạt động gần nhất: ' . (string) $wallet['latest_activity_at'];
        }

        return $this->buildSupportResult(
            'wallet_summary',
            'success',
            implode("\n", $summaryLines),
            [
                'verification_state' => 'owner_verified',
                'lookup_mode' => 'authenticated_owner',
                'account' => [
                    'full_name' => (string) ($user['full_name'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                    'wallet_balance' => (float) ($user['wallet_balance'] ?? 0),
                    'created_at' => (string) ($user['created_at'] ?? ''),
                ],
                'wallet' => [
                    'current_balance' => (float) ($wallet['current_balance'] ?? 0),
                    'total_deposit' => (float) ($wallet['total_deposit'] ?? 0),
                    'display_spent' => (float) ($wallet['display_spent'] ?? 0),
                    'deposit_count' => (int) ($wallet['deposit_count'] ?? 0),
                    'latest_activity_at' => $wallet['latest_activity_at'] ?? null,
                ],
                'recent_transactions' => array_map(fn(array $row): array => $this->compactTransaction($row), $transactions),
            ],
            [
                'lookup_verified' => true,
                'structured_data_present' => true,
            ]
        );
    }

    private function buildSupportResult(string $interactionType, string $lookupState, string $structuredReply, array $supportPayload = [], array $metaExtra = []): array
    {
        $supportPayload = array_merge([
            'lookup_type' => $interactionType,
            'lookup_state' => $lookupState,
            'message' => $structuredReply,
        ], $supportPayload);

        return [
            'handled' => true,
            'interaction_type' => $interactionType,
            'structured_reply' => $structuredReply,
            'context_patch' => [
                'customer_account_support' => $supportPayload,
            ],
            'meta' => array_merge([
                'interaction_type' => $interactionType,
                'lookup_type' => $interactionType,
                'lookup_state' => $lookupState,
                'verification_state' => (string) ($supportPayload['verification_state'] ?? ''),
            ], $metaExtra),
        ];
    }

    private function emptyResult(): array
    {
        return [
            'handled' => false,
            'interaction_type' => null,
            'structured_reply' => '',
            'context_patch' => [],
            'meta' => [],
        ];
    }

    private function detectInteractionType(string $message, array $languageAnalysis): ?string
    {
        $intent = trim((string) ($languageAnalysis['intent_guess'] ?? ''));
        if (in_array($intent, ['order_lookup', 'purchase_history', 'wallet_summary'], true)) {
            return $intent;
        }

        $haystack = $this->ascii((string) ($languageAnalysis['normalized_text'] ?? $message));
        if ($haystack === '') {
            return null;
        }

        if ($this->containsAny($haystack, [
            'ma don', 'kiem tra don', 'check don', 'trang thai don',
            'don cua toi', 'don gan day', 'don gan nhat', 'tra cuu don hang', 'ord',
        ])) {
            return 'order_lookup';
        }

        if ($this->containsAny($haystack, [
            'lich su mua', 'lich su don', 'da mua gi', 'mua gan day',
        ])) {
            return 'purchase_history';
        }

        if ($this->containsAny($haystack, [
            'wallet', 'so du', 'vi con bao nhieu', 'vi cua toi',
            'tong nap', 'da chi', 'lich su giao dich',
        ])) {
            return 'wallet_summary';
        }

        return null;
    }

    private function collectLookupTexts(string $message, array $languageAnalysis): array
    {
        $texts = [];
        $candidates = [
            $message,
            (string) ($languageAnalysis['original_text'] ?? ''),
            (string) ($languageAnalysis['normalized_text'] ?? ''),
        ];

        foreach ((array) ($languageAnalysis['recent_messages'] ?? []) as $row) {
            if (is_array($row) && (string) ($row['role'] ?? '') === 'user') {
                $candidates[] = (string) ($row['text'] ?? '');
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = sanitize_text((string) $candidate, 500);
            if ($candidate !== '') {
                $texts[] = $candidate;
            }
        }

        return array_values(array_unique($texts));
    }

    private function extractOrderCode(array $texts): string
    {
        $patterns = [
            '/\bORD(?:-[A-Z0-9]{2,})+\b/i',
            '/\bORD(?:[\s\-][A-Z0-9]{2,})+\b/i',
        ];

        foreach ($texts as $text) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text, $matches) !== 1) {
                    continue;
                }

                $candidate = strtoupper(trim((string) ($matches[0] ?? '')));
                $candidate = preg_replace('/\s+/', '-', $candidate) ?? $candidate;
                $candidate = preg_replace('/^ORD-?/', 'ORD-', $candidate) ?? $candidate;
                $candidate = preg_replace('/-+/', '-', $candidate) ?? $candidate;

                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private function extractEmail(array $texts): string
    {
        foreach ($texts as $text) {
            if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $text, $matches) !== 1) {
                continue;
            }

            $email = strtolower(trim((string) ($matches[0] ?? '')));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return '';
    }

    private function latestUserOrder(int $userId): ?array
    {
        $orders = $this->orderModel->byUser($userId);
        if ($orders === []) {
            return null;
        }

        $first = (array) ($orders[0] ?? []);
        $orderCode = (string) ($first['order_code'] ?? '');

        return $orderCode !== '' ? $this->orderModel->findByOrderCodeForUser($orderCode, $userId) : null;
    }

    private function compactOrder(array $order): array
    {
        return [
            'order_code' => (string) ($order['order_code'] ?? ''),
            'status' => (string) ($order['status'] ?? ''),
            'status_label' => $this->orderStatusLabel((string) ($order['status'] ?? '')),
            'created_at' => (string) ($order['created_at'] ?? ''),
            'total_amount' => (float) ($order['total_amount'] ?? 0),
        ];
    }

    private function compactTransaction(array $transaction): array
    {
        return [
            'transaction_code' => (string) ($transaction['transaction_code'] ?? ''),
            'transaction_type' => (string) ($transaction['transaction_type'] ?? ''),
            'direction' => (string) ($transaction['direction'] ?? ''),
            'amount' => (float) ($transaction['amount'] ?? 0),
            'status' => (string) ($transaction['status'] ?? ''),
            'description' => (string) ($transaction['description'] ?? ''),
            'created_at' => (string) ($transaction['created_at'] ?? ''),
        ];
    }

    private function orderStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'paid' => 'Đã thanh toán',
            'processing' => 'Đang xử lý',
            'completed' => 'Hoàn thành',
            'pending' => 'Chờ xử lý',
            'cancelled', 'canceled' => 'Đã hủy',
            default => $status !== '' ? ucfirst($status) : 'Không rõ',
        };
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

    private function ascii(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        if ($value === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = str_replace(['đ', 'Đ'], 'd', $value);
        $value = preg_replace('/[^a-z0-9@\.\s\-]/', ' ', strtolower($value)) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
