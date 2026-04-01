<?php

namespace App\Services;

class SePayService
{
    public function parseIncomingPayload(string $rawBody, array $fallbackPost = []): array
    {
        $rawBody = trim($rawBody);
        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return is_array($fallbackPost) ? $fallbackPost : [];
    }

    public function eventKey(array $payload, string $rawBody = ''): string
    {
        foreach (['id', 'transaction_id', 'reference', 'referenceCode', 'gatewayTransactionId', 'code'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return 'sepay:' . $value;
            }
        }

        return 'sepay:' . sha1($rawBody !== '' ? $rawBody : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function externalReference(array $payload): string
    {
        foreach (['id', 'transaction_id', 'reference', 'referenceCode', 'gatewayTransactionId'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function isIncomingTransfer(array $payload): bool
    {
        $transferType = strtolower(trim((string) ($payload['transferType'] ?? $payload['transfer_type'] ?? $payload['type'] ?? '')));
        if ($transferType === '') {
            return true;
        }

        foreach (['out', 'outgoing', 'debit', 'withdraw'] as $negativeType) {
            if (str_contains($transferType, $negativeType)) {
                return false;
            }
        }

        return true;
    }

    public function extractAmount(array $payload): float
    {
        foreach (['transferAmount', 'transfer_amount', 'amount', 'gatewayAmount'] as $key) {
            if (!isset($payload[$key])) {
                continue;
            }

            $rawValue = trim((string) $payload[$key]);
            if ($rawValue === '') {
                continue;
            }

            $amount = $this->normalizeAmountString($rawValue);
            if ($amount > 0) {
                return $amount;
            }
        }

        return 0;
    }

    private function normalizeAmountString(string $rawValue): float
    {
        $value = preg_replace('/[^\d.,-]+/', '', $rawValue);
        if ($value === null || $value === '') {
            return 0;
        }

        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');
            if ($lastComma !== false && $lastDot !== false) {
                if ($lastComma > $lastDot) {
                    $value = str_replace('.', '', $value);
                    $value = str_replace(',', '.', $value);
                } else {
                    $value = str_replace(',', '', $value);
                }
            }
        } elseif ($hasComma) {
            if (preg_match('/,\d{1,2}$/', $value) === 1) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($hasDot) {
            if (preg_match('/\.\d{3}(?:\.|$)/', $value) === 1 && preg_match('/\.\d{1,2}$/', $value) !== 1) {
                $value = str_replace('.', '', $value);
            }
        }

        return round((float) $value, 2);
    }

    public function extractTransactionCode(array $payload): string
    {
        foreach (['code', 'content', 'description', 'transferContent', 'transfer_content', 'reference', 'referenceCode'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            if (preg_match('/\b((?:wal-[a-z0-9-]{6,})|(?:zno\d{4}))\b/i', $value, $matches)) {
                return strtolower((string) $matches[1]);
            }
        }

        return '';
    }
}
