<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AiActorResolver;
use App\Services\AiSessionManager;
use App\Services\CustomerFeedbackService;

class FeedbackController extends Controller
{
    public function storeFromHeader(): void
    {
        $this->ensurePostApi();
        $this->ensurePublicFeedbackRateLimit();

        $payload = $this->requestPayload();
        $actor = (new AiActorResolver($this->config))->resolveFromSession();
        $actorId = (int) ($actor['actor_id'] ?? 0) > 0 ? (int) $actor['actor_id'] : null;
        $sessionManager = new AiSessionManager($this->config);
        $sessionId = $sessionManager->resolveSessionId(
            'header-feedback',
            '',
            'header-feedback-' . (session_id() ?: 'guest'),
            $actorId
        );

        try {
            $feedback = (new CustomerFeedbackService($this->config))->capture($payload, $actor, $sessionId, [
                'source' => 'storefront_header',
                'page_type' => 'storefront_header',
                'require_guest_contact' => true,
                'allow_guest_feedback' => true,
            ]);
        } catch (\InvalidArgumentException $exception) {
            $this->respondJson([
                'success' => false,
                'message' => $exception->getMessage(),
                'csrf_token' => csrf_token(),
            ], 422);
        } catch (\Throwable $exception) {
            security_log('Không thể lưu feedback từ header storefront', [
                'actor_id' => $actorId,
                'error' => $exception->getMessage(),
            ]);

            $this->respondJson([
                'success' => false,
                'message' => 'Hệ thống chưa lưu được feedback lúc này. Vui lòng thử lại sau ít phút.',
                'csrf_token' => csrf_token(),
            ], 500);
        }

        $this->respondJson([
            'success' => true,
            'message' => 'Đã ghi nhận góp ý của bạn. Cảm ơn bạn đã phản hồi cho ZenoxDigital.',
            'data' => [
                'feedbackId' => (int) ($feedback['id'] ?? 0),
                'feedbackCode' => (string) ($feedback['feedback_code'] ?? ''),
                'feedbackType' => (string) ($feedback['feedback_type'] ?? 'general'),
                'sentiment' => (string) ($feedback['sentiment'] ?? 'neutral'),
                'severity' => (string) ($feedback['severity'] ?? 'low'),
                'source' => (string) ($feedback['source'] ?? 'storefront_header'),
                'pageType' => (string) ($feedback['page_type'] ?? 'storefront_header'),
                'needsFollowUp' => !empty($feedback['needs_follow_up']),
            ],
            'csrf_token' => csrf_token(),
        ]);
    }

    private function requestPayload(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    private function ensurePostApi(): void
    {
        if (!is_post()) {
            $this->respondJson([
                'success' => false,
                'message' => 'Method not allowed.',
                'csrf_token' => csrf_token(),
            ], 405);
        }
    }

    private function ensurePublicFeedbackRateLimit(): void
    {
        $result = $this->consumeRateLimit('public-feedback', 'public_feedback');

        if (!$result['allowed']) {
            header('Retry-After: ' . (int) ($result['retry_after'] ?? 60));
            $this->respondJson([
                'success' => false,
                'message' => 'Bạn đang gửi feedback quá nhanh. Vui lòng thử lại sau ít giây.',
                'retry_after' => (int) ($result['retry_after'] ?? 60),
                'csrf_token' => csrf_token(),
            ], 429);
        }
    }

    private function respondJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
