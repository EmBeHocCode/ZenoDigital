<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\WalletTransaction;
use App\Services\SePayService;

class PaymentWebhookController extends Controller
{
    public function sepay(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $sepayConfig = sepay_config(true);
        $rawBody = (string) file_get_contents('php://input');
        $service = new SePayService();
        $payload = $service->parseIncomingPayload($rawBody, $_POST);
        $walletModel = new WalletTransaction($this->config);
        $eventKey = $service->eventKey($payload, $rawBody);

        if (empty($sepayConfig['enabled'])) {
            $walletModel->upsertWebhookEvent('sepay', $eventKey, $payload, 'ignored', null, 'sepay_disabled');
            $this->jsonResponse(200, ['success' => true, 'message' => 'ignored']);
        }

        $expectedToken = trim((string) ($sepayConfig['webhook_token'] ?? ''));
        $providedToken = trim((string) ($_GET['token'] ?? ''));

        if ($expectedToken !== '' && !hash_equals($expectedToken, $providedToken)) {
            security_log('Webhook SePay bị từ chối do token không hợp lệ', [
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
                'event_key' => $eventKey,
            ]);

            $this->jsonResponse(401, ['success' => false, 'message' => 'unauthorized']);
        }

        $walletModel->upsertWebhookEvent('sepay', $eventKey, $payload, 'received');

        if (!$service->isIncomingTransfer($payload)) {
            $walletModel->upsertWebhookEvent('sepay', $eventKey, $payload, 'ignored', null, 'not_incoming');
            $this->jsonResponse(200, ['success' => true, 'message' => 'ignored']);
        }

        $transactionCode = $service->extractTransactionCode($payload);
        if ($transactionCode === '') {
            $walletModel->upsertWebhookEvent('sepay', $eventKey, $payload, 'ignored', null, 'transaction_code_missing');
            $this->jsonResponse(200, ['success' => true, 'message' => 'ignored']);
        }

        $amount = $service->extractAmount($payload);
        if ($amount <= 0) {
            $walletModel->upsertWebhookEvent('sepay', $eventKey, $payload, 'ignored', $transactionCode, 'invalid_amount');
            $this->jsonResponse(200, ['success' => true, 'message' => 'ignored']);
        }

        $result = $walletModel->completePendingDepositByCode(
            $transactionCode,
            $amount,
            $payload,
            'sepay',
            $service->externalReference($payload)
        );

        $status = (string) ($result['status'] ?? 'error');
        if (in_array($status, ['completed', 'already_completed'], true)) {
            $walletModel->upsertWebhookEvent('sepay', $eventKey, $payload, 'completed', $transactionCode);
            $this->jsonResponse(200, [
                'success' => true,
                'message' => $status === 'completed' ? 'wallet_credited' : 'already_processed',
                'transaction_code' => $transactionCode,
            ]);
        }

        if (in_array($status, ['not_found', 'amount_mismatch', 'invalid_payload', 'user_not_found'], true)) {
            $walletModel->upsertWebhookEvent('sepay', $eventKey, $payload, 'ignored', $transactionCode, $status);
            $this->jsonResponse(200, [
                'success' => true,
                'message' => 'ignored',
                'reason' => $status,
            ]);
        }

        $walletModel->upsertWebhookEvent('sepay', $eventKey, $payload, 'failed', $transactionCode, (string) ($result['message'] ?? $status));
        $this->jsonResponse(500, [
            'success' => false,
            'message' => 'processing_failed',
        ]);
    }

    private function jsonResponse(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
