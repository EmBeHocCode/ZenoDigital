<?php

namespace App\Services;

class AiInputNormalizerService
{
    private const TOKEN_MAP = [
        'ko' => 'không',
        'k' => 'không',
        'kh' => 'không',
        'hk' => 'không',
        'hog' => 'không',
        'hong' => 'không',
        'hongg' => 'không',
        'hongg?' => 'không',
        'hong?' => 'không',
        'hongn' => 'không',
        'hongn?' => 'không',
        'honggk' => 'không',
        'honggg' => 'không',
        'hongggo' => 'không',
        'hongggg' => 'không',
        'hongggg?' => 'không',
        'honggg?' => 'không',
        'khum' => 'không',
        'hông' => 'không',
        'khong' => 'không',
        'dc' => 'được',
        'dk' => 'được',
        'đc' => 'được',
        'đk' => 'được',
        'oki' => 'ok',
        'oke' => 'ok',
        'okela' => 'ok',
        'ukii' => 'ok',
        'ukk' => 'ok',
        'okk' => 'ok',
        'j' => 'gì',
        'z' => 'vậy',
        'v' => 'vậy',
        'mik' => 'mình',
        'mk' => 'mình',
        'tui' => 'tôi',
        'toy' => 'tôi',
        'mng' => 'mọi người',
        'mn' => 'mọi người',
        'nt' => 'nhắn tin',
        'tl' => 'trả lời',
        'rep' => 'trả lời',
        'ib' => 'nhắn riêng',
        'inb' => 'nhắn riêng',
        'mún' => 'muốn',
        'mun' => 'muốn',
        'muon' => 'muốn',
        'ad' => 'admin',
        'a' => 'anh',
        'e' => 'em',
        'b' => 'bạn',
        'chao' => 'chào',
        'goi' => 'gói',
        'nao' => 'nào',
        'on' => 'ổn',
        'don' => 'đơn',
        'hang' => 'hàng',
        're' => 'rẻ',
        'con' => 'còn',
        'be' => 'bé',
        'tuvan' => 'tư vấn',
    ];

    private const PHRASE_PATTERNS = [
        '/\bcheck\s+đơn\b/u' => 'kiểm tra đơn hàng',
        '/\bcheck\s+tài\s*khoản\b/u' => 'kiểm tra tài khoản',
        '/\bship\s+lẹ\s+không\b/u' => 'bàn giao nhanh không',
        '/\bship\s+le\s+không\b/u' => 'bàn giao nhanh không',
        '/\bship\s+nhanh\s+không\b/u' => 'bàn giao nhanh không',
        '/\bgóp\s*ý\b/u' => 'góp ý',
        '/\btư\s*vấn\b/u' => 'tư vấn',
        '/\bví\s+còn\s+bao\s+nhiêu\b/u' => 'ví còn bao nhiêu',
        '/\bsố\s+dư\b/u' => 'số dư',
        '/\bđã\s+chi\b/u' => 'đã chi',
        '/\blịch\s+sử\s+mua\b/u' => 'lịch sử mua',
        '/\bđơn\s+gần\s+nhất\b/u' => 'đơn gần nhất',
        '/\bđơn\s+gần\s+đây\b/u' => 'đơn gần đây',
        '/\brẻ\s+mà\s+ổn\b/u' => 'giá mềm nhưng ổn',
        '/\bgói\s+rẻ\s+mà\s+ổn\b/u' => 'gói giá mềm nhưng ổn',
        '/\bcòn\s+hàng\b/u' => 'còn hàng',
        '/\badmin\s+ơi\b/u' => 'admin ơi',
        '/\bchào\s+bé\b/u' => 'chào bé',
        '/\bgói\s+nào\s+ổn\s+vậy\b/u' => 'gói nào ổn vậy',
        '/\bweb\s+bán\s+hàng\b/u' => 'web bán hàng',
    ];

    public function normalize(string $message, array $recentMessages = [], array $actor = [], string $conversationMode = 'customer_support'): array
    {
        $originalText = trim(preg_replace('/\s+/u', ' ', $message) ?? '');
        $normalizedText = $this->normalizeTokens($originalText);
        $normalizedText = $this->normalizePhrases($normalizedText);
        $recentMessages = $this->sanitizeRecentMessages($recentMessages);
        $intent = $this->guessIntent($originalText, $normalizedText, $recentMessages, $conversationMode);
        $confidence = $this->estimateConfidence($originalText, $normalizedText, $intent, $recentMessages);
        $requiresClarification = $confidence < 0.55;
        $slangHits = $this->detectSlangHits($originalText, $normalizedText);

        if ($intent === 'negation_reply' && !empty($recentMessages)) {
            $requiresClarification = false;
            $confidence = max($confidence, 0.68);
        }

        if ($intent === 'greeting' || $intent === 'feedback_capture' || $intent === 'product_advice' || $intent === 'order_lookup' || $intent === 'purchase_history' || $intent === 'wallet_summary') {
            $confidence = max($confidence, 0.75);
            $requiresClarification = false;
        }

        return [
            'original_text' => $originalText,
            'normalized_text' => $normalizedText !== '' ? $normalizedText : $originalText,
            'intent_guess' => $intent,
            'confidence' => round($confidence, 2),
            'requires_clarification' => $requiresClarification,
            'slang_hits' => $slangHits,
            'recent_messages' => $recentMessages,
            'context_hint' => $this->buildContextHint($intent, $recentMessages),
        ];
    }

    private function normalizeTokens(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $working = mb_strtolower($text, 'UTF-8');
        $tokens = preg_split('/(\s+)/u', $working, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $normalized = [];

        foreach ($tokens as $token) {
            if ($token === '' || preg_match('/^\s+$/u', $token)) {
                $normalized[] = $token;
                continue;
            }

            $leading = '';
            $trailing = '';
            $core = $token;

            if (preg_match('/^[^\p{L}\p{N}]+/u', $core, $match)) {
                $leading = $match[0];
                $core = substr($core, strlen($leading));
            }

            if (preg_match('/[^\p{L}\p{N}]+$/u', $core, $match)) {
                $trailing = $match[0];
                $core = substr($core, 0, -strlen($trailing));
            }

            if ($core === '') {
                $normalized[] = $token;
                continue;
            }

            $ascii = $this->ascii($core);
            $replacement = self::TOKEN_MAP[$ascii] ?? $core;
            $normalized[] = $leading . $replacement . $trailing;
        }

        return trim(preg_replace('/\s+/u', ' ', implode('', $normalized)) ?? '');
    }

    private function normalizePhrases(string $text): string
    {
        $normalized = $text;

        foreach (self::PHRASE_PATTERNS as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized) ?? $normalized;
        }

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');
    }

    private function guessIntent(string $originalText, string $normalizedText, array $recentMessages, string $conversationMode): string
    {
        $rawHaystack = mb_strtolower($normalizedText !== '' ? $normalizedText : $originalText, 'UTF-8');
        $haystack = $this->ascii($rawHaystack);

        if ($haystack === '') {
            return 'unknown';
        }

        if (preg_match('/\b(chao|hello|hi|alo|hey)\b/u', $haystack)) {
            return 'greeting';
        }

        if (preg_match('/\b(gop y|feedback|phan hoi|chua hai long|ho tro them)\b/u', $haystack)) {
            return 'feedback_capture';
        }

        if ($this->containsAny($rawHaystack, [
            'tra cứu đơn hàng',
            'check đơn hàng',
            'kiểm tra đơn',
            'mã đơn',
            'đơn gần đây',
            'đơn gần nhất',
            'đơn của tôi',
        ]) || $this->containsAny($haystack, [
            'tra cuu don hang',
            'check don hang',
            'kiem tra don',
            'ma don',
            'don gan day',
            'don gan nhat',
            'don cua toi',
            'ord-',
            'ord ',
        ])) {
            return 'order_lookup';
        }

        if (preg_match('/\b(check don hang|kiem tra don|tra cuu don hang|ma don|ord|don gan day)\b/u', $haystack)) {
            return 'order_lookup';
        }

        if ($this->containsAny($rawHaystack, [
            'lịch sử mua',
            'lịch sử đơn',
            'mua gần đây',
            'đã mua gì',
        ]) || $this->containsAny($haystack, [
            'lich su mua',
            'lich su don',
            'mua gan day',
            'da mua gi',
        ])) {
            return 'purchase_history';
        }

        if (preg_match('/\b(lich su mua|lich su don|don gan nhat|mua gan day|da mua gi)\b/u', $haystack)) {
            return 'purchase_history';
        }

        if (preg_match('/\b(con hang|het hang|available)\b/u', $haystack)) {
            return 'availability_check';
        }

        if (preg_match('/\b(ship le|ban giao nhanh|bao lau|nhan dich vu|giao nhanh)\b/u', $haystack)) {
            return 'delivery_speed';
        }

        if (preg_match('/\b(thanh toan|wallet|so du|nap tien|nap so du)\b/u', $haystack)) {
            return 'payment_help';
        }

        if ($this->containsAny($rawHaystack, [
            'ví còn bao nhiêu',
            'ví của tôi',
            'tổng nạp',
            'đã chi',
            'lịch sử giao dịch',
            'tài khoản của tôi',
            'số dư',
        ]) || preg_match('/\b(vi con bao nhieu|vi cua toi|tong nap|da chi|lich su giao dich|tai khoan cua toi)\b/u', $haystack)) {
            return 'wallet_summary';
        }

        if (preg_match('/\b(vps|server|goi nao|tu van|web ban hang|gia mem|re ma on|goi re)\b/u', $haystack)) {
            return 'product_advice';
        }

        if (preg_match('/^\b(ok|oke|okela|ukii|um|uhm|umm|um)\b$/u', $haystack)) {
            return 'acknowledgement';
        }

        if (preg_match('/^\b(khong a|khong|hong a|khum a|hog a)\b$/u', $haystack)) {
            return !empty($recentMessages) ? 'negation_reply' : 'negation';
        }

        if (preg_match('/^\b(vay ha|z ha|uh)\b$/u', $haystack)) {
            return !empty($recentMessages) ? 'context_reaction' : 'unclear_short';
        }

        if (mb_strlen($normalizedText !== '' ? $normalizedText : $originalText, 'UTF-8') <= 8) {
            return !empty($recentMessages) ? 'short_contextual' : 'unclear_short';
        }

        return $conversationMode === 'customer_support' ? 'general_customer_query' : 'general_backoffice_query';
    }

    private function estimateConfidence(string $originalText, string $normalizedText, string $intent, array $recentMessages): float
    {
        $confidence = 0.4;

        if ($originalText !== $normalizedText && $normalizedText !== '') {
            $confidence += 0.18;
        }

        if (!empty($recentMessages)) {
            $confidence += 0.08;
        }

        $confidence += match ($intent) {
            'greeting', 'feedback_capture', 'order_lookup', 'purchase_history', 'wallet_summary', 'product_advice', 'delivery_speed', 'availability_check', 'payment_help' => 0.32,
            'negation_reply', 'context_reaction', 'acknowledgement' => 0.18,
            'general_customer_query', 'general_backoffice_query' => 0.12,
            default => 0,
        };

        if (mb_strlen($originalText, 'UTF-8') <= 6) {
            $confidence -= 0.08;
        }

        return max(0.1, min(0.95, $confidence));
    }

    private function detectSlangHits(string $originalText, string $normalizedText): array
    {
        $hits = [];
        $originalTokens = preg_split('/\s+/u', mb_strtolower($originalText, 'UTF-8')) ?: [];
        $normalizedTokens = preg_split('/\s+/u', mb_strtolower($normalizedText, 'UTF-8')) ?: [];

        foreach ($originalTokens as $index => $token) {
            $source = trim($token);
            $target = trim((string) ($normalizedTokens[$index] ?? ''));
            if ($source !== '' && $target !== '' && $source !== $target) {
                $hits[] = $source . ' => ' . $target;
            }
        }

        return array_values(array_unique($hits));
    }

    private function sanitizeRecentMessages(array $recentMessages): array
    {
        $sanitized = [];

        foreach (array_slice($recentMessages, -6) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $role = in_array((string) ($item['role'] ?? ''), ['user', 'assistant'], true)
                ? (string) $item['role']
                : 'user';
            $text = sanitize_text((string) ($item['text'] ?? ''), 500);

            if ($text === '') {
                continue;
            }

            $sanitized[] = [
                'role' => $role,
                'text' => $text,
            ];
        }

        return $sanitized;
    }

    private function buildContextHint(string $intent, array $recentMessages): string
    {
        if (empty($recentMessages)) {
            return '';
        }

        $lastMessage = (array) end($recentMessages);
        $lastRole = (string) ($lastMessage['role'] ?? 'user');
        $lastText = (string) ($lastMessage['text'] ?? '');

        return match ($intent) {
            'negation_reply' => 'Tin nhắn hiện tại có vẻ là câu phủ định/khước từ ngắn, nên cần bám vào câu trước đó trong hội thoại.',
            'context_reaction', 'short_contextual' => 'Tin nhắn hiện tại là phản ứng ngắn, nên ưu tiên hiểu theo mạch hội thoại gần nhất.',
            default => $lastText !== ''
                ? 'Ngữ cảnh gần nhất: ' . ($lastRole === 'assistant' ? 'bot vừa nói' : 'người dùng vừa nói') . ' "' . $lastText . '".'
                : '',
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
        $value = preg_replace('/[^a-z0-9\s]/', '', strtolower($value)) ?? $value;

        return trim($value);
    }
}
