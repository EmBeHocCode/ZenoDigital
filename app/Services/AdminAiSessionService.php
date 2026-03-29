<?php

namespace App\Services;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;

class AdminAiSessionService
{
    private array $config;
    private AiChatSession $sessionModel;
    private AiChatMessage $messageModel;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->sessionModel = new AiChatSession($config);
        $this->messageModel = new AiChatMessage($config);
    }

    public function restoreOrCreate(int $userId, string $actorRole, string $conversationMode, string $channel = 'admin'): array
    {
        $expiredCount = $this->sessionModel->expireStaleSessions($userId, $channel, $conversationMode);

        $session = $this->sessionModel->findActiveByUser($userId, $channel, $conversationMode);
        $createdNew = false;
        $resumeReason = 'active';

        if (!$session) {
            $createdNew = true;
            $resumeReason = $expiredCount > 0 ? 'expired' : 'new';
            $session = $this->createSession($userId, $actorRole, $conversationMode, $channel);
        } else {
            $this->sessionModel->touchSession((int) $session['id'], $this->nextExpiryAt());
            $session = $this->sessionModel->findById((int) $session['id']) ?? $session;
            $this->sessionModel->closeOtherActiveSessions($userId, $channel, $conversationMode, (int) $session['id']);
        }

        $messages = $this->messageModel->recentBySession((int) ($session['id'] ?? 0), $this->restoreLimit());

        return [
            'session' => $session,
            'messages' => $messages,
            'created_new' => $createdNew,
            'resume_reason' => $resumeReason,
            'resume_notice' => $this->buildResumeNotice($resumeReason, $session, $createdNew),
        ];
    }

    public function resetSession(int $userId, string $actorRole, string $conversationMode, string $channel = 'admin'): array
    {
        $current = $this->sessionModel->findActiveByUser($userId, $channel, $conversationMode);
        if ($current) {
            $this->sessionModel->updateStatus((int) $current['id'], 'reset');
        }

        $next = $this->createSession($userId, $actorRole, $conversationMode, $channel);

        return [
            'previous_session' => $current,
            'session' => $next,
            'messages' => [],
            'created_new' => true,
            'resume_reason' => 'reset',
            'resume_notice' => 'Đã bắt đầu một phiên copilot mới.',
        ];
    }

    public function markPending(string $sessionKey, int $userId, string $channel, string $requestId): void
    {
        $session = $this->requireOwnedSession($sessionKey, $userId, $channel);
        $this->sessionModel->markPending((int) $session['id'], $requestId, $this->nextExpiryAt());
    }

    public function clearPending(string $sessionKey, int $userId, string $channel): void
    {
        $session = $this->requireOwnedSession($sessionKey, $userId, $channel);
        $this->sessionModel->clearPending((int) $session['id'], $this->nextExpiryAt());
    }

    public function appendMessage(string $sessionKey, int $userId, string $channel, string $role, string $content, array $meta = []): void
    {
        $session = $this->requireOwnedSession($sessionKey, $userId, $channel);
        $this->messageModel->create([
            'chat_session_id' => (int) $session['id'],
            'role' => $this->normalizeRole($role),
            'content' => sanitize_text($content, 12000),
            'meta_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->sessionModel->touchSession((int) $session['id'], $this->nextExpiryAt());
    }

    public function recentConversationWindow(string $sessionKey, int $userId, string $channel, int $limit = 6): array
    {
        $session = $this->requireOwnedSession($sessionKey, $userId, $channel);
        $rows = $this->messageModel->recentBySession((int) $session['id'], max(1, $limit));
        $messages = [];

        foreach ($rows as $row) {
            $role = (string) ($row['role'] ?? 'user');
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $text = sanitize_text((string) ($row['content'] ?? ''), 500);
            if ($text === '') {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'text' => $text,
            ];
        }

        return array_slice($messages, -max(1, $limit));
    }

    public function toClientPayload(array $restored, string $conversationMode): array
    {
        $session = (array) ($restored['session'] ?? []);
        $messages = array_map(function (array $row): array {
            $meta = json_decode((string) ($row['meta_json'] ?? ''), true);
            return [
                'role' => (string) ($row['role'] ?? 'assistant'),
                'text' => (string) ($row['content'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'meta' => is_array($meta) ? $meta : [],
            ];
        }, (array) ($restored['messages'] ?? []));

        return [
            'sessionId' => (string) ($session['session_key'] ?? ''),
            'conversationMode' => $conversationMode,
            'createdAt' => (string) ($session['created_at'] ?? ''),
            'lastActivityAt' => (string) ($session['last_activity_at'] ?? ''),
            'expiresAt' => (string) ($session['expires_at'] ?? ''),
            'status' => (string) ($session['status'] ?? 'active'),
            'pendingRequestId' => $session['pending_request_id'] ?? null,
            'messages' => $messages,
            'createdNew' => !empty($restored['created_new']),
            'resumeReason' => (string) ($restored['resume_reason'] ?? 'active'),
            'resumeNotice' => (string) ($restored['resume_notice'] ?? ''),
            'ttlSeconds' => $this->ttlSeconds(),
        ];
    }

    private function createSession(int $userId, string $actorRole, string $conversationMode, string $channel): array
    {
        $createdAt = date('Y-m-d H:i:s');
        return $this->sessionModel->create([
            'session_key' => $this->generateSessionKey($userId),
            'user_id' => $userId,
            'channel' => $channel,
            'conversation_mode' => $conversationMode,
            'actor_role' => trim($actorRole) !== '' ? trim($actorRole) : 'admin',
            'status' => 'active',
            'pending_request_id' => null,
            'created_at' => $createdAt,
            'last_activity_at' => $createdAt,
            'expires_at' => $this->nextExpiryAt(),
            'updated_at' => $createdAt,
        ]);
    }

    private function buildResumeNotice(string $resumeReason, array $session, bool $createdNew): string
    {
        if ($createdNew) {
            if ($resumeReason === 'expired') {
                return 'Phiên cũ đã hết hạn. Hệ thống đã tạo phiên mới để bạn làm việc tiếp.';
            }

            return 'Đã tạo phiên copilot mới cho lần làm việc này.';
        }

        if (!empty($session['pending_request_id'])) {
            return 'Đang tiếp tục phiên gần nhất. Lượt trước có yêu cầu đang xử lý hoặc bị gián đoạn, nếu chưa thấy kết quả bạn có thể hỏi lại.';
        }

        return 'Đang tiếp tục phiên copilot gần nhất.';
    }

    private function requireOwnedSession(string $sessionKey, int $userId, string $channel): array
    {
        $normalizedKey = trim($sessionKey);
        if ($normalizedKey === '') {
            throw new \RuntimeException('Thiếu session key cho copilot.');
        }

        $session = $this->sessionModel->findBySessionKey($normalizedKey, $userId, $channel);
        if (!$session) {
            throw new \RuntimeException('Không tìm thấy phiên copilot hợp lệ.');
        }

        if ((string) ($session['status'] ?? '') !== 'active') {
            throw new \RuntimeException('Phiên copilot hiện tại không còn hiệu lực.');
        }

        if (strtotime((string) ($session['expires_at'] ?? '')) <= time()) {
            $this->sessionModel->updateStatus((int) $session['id'], 'expired');
            throw new \RuntimeException('Phiên copilot đã hết hạn.');
        }

        return $session;
    }

    private function generateSessionKey(int $userId): string
    {
        $prefix = trim((string) config('ai.admin_session_prefix', 'admin-dashboard'));
        return $prefix . '-' . $userId . '-' . substr(bin2hex(random_bytes(8)), 0, 16);
    }

    private function normalizeRole(string $role): string
    {
        return in_array($role, ['user', 'assistant', 'system'], true) ? $role : 'system';
    }

    private function nextExpiryAt(): string
    {
        return date('Y-m-d H:i:s', time() + $this->ttlSeconds());
    }

    private function ttlSeconds(): int
    {
        return max(1800, (int) config('ai.admin_session_ttl_seconds', 43200));
    }

    private function restoreLimit(): int
    {
        return max(10, (int) config('ai.admin_session_restore_limit', 30));
    }
}
