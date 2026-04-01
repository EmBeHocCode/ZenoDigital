<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class WalletTransaction extends Model
{
    private array $config;
    private static bool $schemaEnsured = false;

    public function __construct(array $config)
    {
        $this->config = $config;
        parent::__construct($config);
        $this->ensureSchema();
    }

    public function summaryByUser(int $userId): array
    {
        if ($userId <= 0) {
            return $this->emptySummary();
        }

        $userStmt = $this->db->prepare('SELECT COALESCE(wallet_balance, 0) AS wallet_balance FROM users WHERE id = :id LIMIT 1');
        $userStmt->execute(['id' => $userId]);
        $user = $userStmt->fetch() ?: ['wallet_balance' => 0];

        $stmt = $this->db->prepare("SELECT
                COALESCE(SUM(CASE WHEN transaction_type = 'deposit' AND direction = 'credit' AND status = 'completed' THEN amount ELSE 0 END), 0) AS total_deposit,
                COALESCE(SUM(CASE WHEN direction = 'debit' AND status = 'completed' THEN amount ELSE 0 END), 0) AS display_spent,
                SUM(CASE WHEN transaction_type = 'deposit' AND status = 'completed' THEN 1 ELSE 0 END) AS deposit_count,
                MAX(CASE WHEN transaction_type = 'deposit' AND status = 'completed' THEN created_at ELSE NULL END) AS latest_deposit_at,
                MAX(created_at) AS latest_activity_at
            FROM wallet_transactions
            WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $summary = $stmt->fetch() ?: [];

        return [
            'current_balance' => (float) ($user['wallet_balance'] ?? 0),
            'total_deposit' => (float) ($summary['total_deposit'] ?? 0),
            'display_spent' => (float) ($summary['display_spent'] ?? 0),
            'deposit_count' => (int) ($summary['deposit_count'] ?? 0),
            'latest_deposit_at' => $summary['latest_deposit_at'] ?? null,
            'latest_activity_at' => $summary['latest_activity_at'] ?? null,
        ];
    }

    public function recentByUser(int $userId, int $limit = 20): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT *
            FROM wallet_transactions
            WHERE user_id = :user_id
            ORDER BY created_at DESC, id DESC
            LIMIT :limit');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function createInstantDeposit(int $userId, float $amount, string $paymentMethod, string $note = ''): ?array
    {
        $amount = round($amount, 2);
        if ($userId <= 0 || $amount <= 0) {
            return null;
        }

        try {
            $this->db->beginTransaction();

            $user = $this->fetchUserForUpdate($userId);
            if (!$user) {
                $this->db->rollBack();
                return null;
            }

            $balanceBefore = (float) ($user['wallet_balance'] ?? 0);
            $balanceAfter = $balanceBefore + $amount;
            $transactionCode = $this->generateTransactionCode();
            $description = trim($note) !== ''
                ? 'Nạp ví tức thì: ' . $note
                : 'Nạp ví thủ công tức thì';

            $this->updateUserBalance($userId, $balanceAfter);
            $this->insertTransaction([
                'user_id' => $userId,
                'transaction_code' => $transactionCode,
                'transaction_type' => 'deposit',
                'payment_method' => $paymentMethod !== '' ? $paymentMethod : 'bank_transfer',
                'direction' => 'credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'description' => $description,
                'provider' => null,
                'external_reference' => null,
                'provider_payload' => null,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->commit();

            return $this->findByTransactionCodeForUser($transactionCode, $userId);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            security_log('Không thể tạo giao dịch nạp ví tức thì', [
                'user_id' => $userId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function createPendingDepositRequest(int $userId, float $amount, string $paymentMethod = 'bank_transfer', string $note = ''): ?array
    {
        $amount = round($amount, 2);
        if ($userId <= 0 || $amount <= 0) {
            return null;
        }

        try {
            $this->db->beginTransaction();

            $user = $this->fetchUserForUpdate($userId);
            if (!$user) {
                $this->db->rollBack();
                return null;
            }

            $currentBalance = (float) ($user['wallet_balance'] ?? 0);
            $transactionCode = $this->generateDepositTransferCode();
            $description = trim($note) !== ''
                ? 'Chờ nạp ví qua SePay: ' . $note
                : 'Chờ nạp ví qua SePay';

            $this->insertTransaction([
                'user_id' => $userId,
                'transaction_code' => $transactionCode,
                'transaction_type' => 'deposit',
                'payment_method' => $paymentMethod !== '' ? $paymentMethod : 'bank_transfer',
                'direction' => 'credit',
                'amount' => $amount,
                'balance_before' => $currentBalance,
                'balance_after' => $currentBalance,
                'status' => 'pending',
                'description' => $description,
                'provider' => 'sepay',
                'external_reference' => null,
                'provider_payload' => null,
                'completed_at' => null,
            ]);

            $this->db->commit();

            return $this->findByTransactionCodeForUser($transactionCode, $userId);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            security_log('Không thể tạo yêu cầu nạp ví chờ thanh toán', [
                'user_id' => $userId,
                'amount' => $amount,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function findByTransactionCodeForUser(string $transactionCode, int $userId): ?array
    {
        $transactionCode = strtolower(trim($transactionCode));
        if ($transactionCode === '' || $userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT *
            FROM wallet_transactions
            WHERE LOWER(transaction_code) = :transaction_code AND user_id = :user_id
            LIMIT 1');
        $stmt->execute([
            'transaction_code' => $transactionCode,
            'user_id' => $userId,
        ]);

        return $stmt->fetch() ?: null;
    }

    public function reconcilePendingDepositByUser(string $transactionCode, int $userId): ?array
    {
        $transaction = $this->findByTransactionCodeForUser($transactionCode, $userId);
        if (!$transaction || (string) ($transaction['status'] ?? '') !== 'pending') {
            return $transaction;
        }

        $provider = strtolower(trim((string) ($transaction['provider'] ?? '')));
        if ($provider !== '' && $provider !== 'sepay') {
            return $transaction;
        }

        $eventStmt = $this->db->prepare("SELECT *
            FROM payment_webhook_events
            WHERE provider = 'sepay'
              AND LOWER(transaction_code) = :transaction_code
              AND status = 'completed'
            ORDER BY updated_at DESC, id DESC
            LIMIT 1");
        $eventStmt->execute(['transaction_code' => strtolower(trim($transactionCode))]);
        $webhookEvent = $eventStmt->fetch();

        if (!$webhookEvent) {
            return $transaction;
        }

        $userStmt = $this->db->prepare('SELECT COALESCE(wallet_balance, 0) AS wallet_balance
            FROM users
            WHERE id = :id
            LIMIT 1');
        $userStmt->execute(['id' => $userId]);
        $user = $userStmt->fetch();
        if (!$user) {
            return $transaction;
        }

        $expectedBalance = round((float) ($transaction['balance_before'] ?? 0) + (float) ($transaction['amount'] ?? 0), 2);
        $currentBalance = round((float) ($user['wallet_balance'] ?? 0), 2);

        if ($currentBalance + 0.009 < $expectedBalance) {
            return $transaction;
        }

        $payload = trim((string) ($webhookEvent['payload'] ?? ''));
        $externalReference = trim((string) ($transaction['external_reference'] ?? ''));

        if ($externalReference === '') {
            $decodedPayload = json_decode($payload, true);
            if (is_array($decodedPayload)) {
                $externalReference = trim((string) ($decodedPayload['id'] ?? $decodedPayload['transaction_id'] ?? ''));
            }
        }

        $updateStmt = $this->db->prepare('UPDATE wallet_transactions
            SET status = :status,
                payment_method = :payment_method,
                balance_after = :balance_after,
                provider = :provider,
                external_reference = :external_reference,
                provider_payload = :provider_payload,
                completed_at = COALESCE(completed_at, NOW()),
                updated_at = NOW()
            WHERE id = :id
              AND status = :current_status');
        $updateStmt->execute([
            'status' => 'completed',
            'payment_method' => 'bank_transfer',
            'balance_after' => $currentBalance,
            'provider' => 'sepay',
            'external_reference' => $externalReference !== '' ? $externalReference : null,
            'provider_payload' => $payload !== '' ? $payload : ($transaction['provider_payload'] ?? null),
            'id' => (int) ($transaction['id'] ?? 0),
            'current_status' => 'pending',
        ]);

        return $this->findByTransactionCodeForUser($transactionCode, $userId);
    }

    public function latestPendingDepositByUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT *
            FROM wallet_transactions
            WHERE user_id = :user_id
              AND transaction_type = 'deposit'
              AND direction = 'credit'
              AND status = 'pending'
            ORDER BY created_at DESC, id DESC
            LIMIT 1");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch() ?: null;
    }

    public function latestWebhookEventByTransactionCode(string $provider, string $transactionCode): ?array
    {
        $provider = strtolower(trim($provider));
        $transactionCode = strtolower(trim($transactionCode));

        if ($provider === '' || $transactionCode === '') {
            return null;
        }

        $stmt = $this->db->prepare("SELECT *
            FROM payment_webhook_events
            WHERE provider = :provider
              AND LOWER(transaction_code) = :transaction_code
            ORDER BY updated_at DESC, id DESC
            LIMIT 1");
        $stmt->execute([
            'provider' => $provider,
            'transaction_code' => $transactionCode,
        ]);

        return $stmt->fetch() ?: null;
    }

    public function adminPaginated(string $search, string $transactionType, string $status, int $page, int $perPage): array
    {
        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(wt.transaction_code LIKE :search OR wt.description LIKE :search OR wt.external_reference LIKE :search OR u.full_name LIKE :search OR u.email LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($transactionType !== '') {
            $where[] = 'wt.transaction_type = :transaction_type';
            $params['transaction_type'] = $transactionType;
        }

        if ($status !== '') {
            $where[] = 'wt.status = :status';
            $params['status'] = $status;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total
            FROM wallet_transactions wt
            LEFT JOIN users u ON u.id = wt.user_id
            {$whereSql}");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $meta = paginate_meta($total, $page, $perPage);

        $stmt = $this->db->prepare("SELECT
                wt.*,
                u.full_name,
                u.email
            FROM wallet_transactions wt
            LEFT JOIN users u ON u.id = wt.user_id
            {$whereSql}
            ORDER BY wt.created_at DESC, wt.id DESC
            LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $meta['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $meta['offset'], PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll() ?: [],
            'meta' => $meta,
        ];
    }

    public function adminSummary(): array
    {
        $stmt = $this->db->query("SELECT
                COUNT(*) AS total_transactions,
                COALESCE(SUM(CASE WHEN transaction_type = 'deposit' AND direction = 'credit' AND status = 'completed' THEN amount ELSE 0 END), 0) AS total_deposit,
                COALESCE(SUM(CASE WHEN direction = 'debit' AND status = 'completed' THEN amount ELSE 0 END), 0) AS total_debit,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_transactions
            FROM wallet_transactions");

        return $stmt->fetch() ?: [
            'total_transactions' => 0,
            'total_deposit' => 0,
            'total_debit' => 0,
            'pending_transactions' => 0,
        ];
    }

    public function upsertWebhookEvent(
        string $provider,
        string $eventKey,
        array $payload,
        string $status = 'received',
        ?string $transactionCode = null,
        ?string $errorMessage = null
    ): void {
        if (trim($provider) === '' || trim($eventKey) === '') {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO payment_webhook_events (
                provider,
                event_key,
                transaction_code,
                status,
                payload,
                last_error,
                created_at,
                updated_at,
                processed_at
            ) VALUES (
                :provider,
                :event_key,
                :transaction_code,
                :status,
                :payload,
                :last_error,
                NOW(),
                NOW(),
                :processed_at
            ) ON DUPLICATE KEY UPDATE
                transaction_code = VALUES(transaction_code),
                status = VALUES(status),
                payload = VALUES(payload),
                last_error = VALUES(last_error),
                updated_at = NOW(),
                processed_at = VALUES(processed_at)');

        $processedAt = in_array($status, ['completed', 'ignored', 'failed'], true)
            ? date('Y-m-d H:i:s')
            : null;

        $stmt->execute([
            'provider' => $provider,
            'event_key' => $eventKey,
            'transaction_code' => $transactionCode,
            'status' => $status,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_error' => $errorMessage,
            'processed_at' => $processedAt,
        ]);
    }

    public function completePendingDepositByCode(
        string $transactionCode,
        float $amount,
        array $payload,
        string $provider = 'sepay',
        string $externalReference = ''
    ): array {
        $transactionCode = strtolower(trim($transactionCode));
        $amount = round($amount, 2);

        if ($transactionCode === '' || $amount <= 0) {
            return ['status' => 'invalid_payload'];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT *
                FROM wallet_transactions
                WHERE LOWER(transaction_code) = :transaction_code
                  AND transaction_type = 'deposit'
                  AND direction = 'credit'
                LIMIT 1
                FOR UPDATE");
            $stmt->execute(['transaction_code' => $transactionCode]);
            $transaction = $stmt->fetch();

            if (!$transaction) {
                $this->db->rollBack();
                return ['status' => 'not_found'];
            }

            if ((string) ($transaction['status'] ?? '') === 'completed') {
                $this->db->rollBack();
                return ['status' => 'already_completed', 'transaction' => $transaction];
            }

            $expectedAmount = round((float) ($transaction['amount'] ?? 0), 2);
            if (abs($expectedAmount - $amount) > 0.009) {
                $this->db->rollBack();
                return [
                    'status' => 'amount_mismatch',
                    'transaction' => $transaction,
                    'expected_amount' => $expectedAmount,
                    'received_amount' => $amount,
                ];
            }

            $userId = (int) ($transaction['user_id'] ?? 0);
            $user = $this->fetchUserForUpdate($userId);
            if (!$user) {
                $this->db->rollBack();
                return ['status' => 'user_not_found'];
            }

            $balanceBefore = (float) ($user['wallet_balance'] ?? 0);
            $balanceAfter = $balanceBefore + $amount;

            $this->updateUserBalance($userId, $balanceAfter);

            $updateStmt = $this->db->prepare('UPDATE wallet_transactions
                SET payment_method = :payment_method,
                    status = :status,
                    balance_before = :balance_before,
                    balance_after = :balance_after,
                    provider = :provider,
                    external_reference = :external_reference,
                    provider_payload = :provider_payload,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id');
            $updateStmt->execute([
                'payment_method' => 'bank_transfer',
                'status' => 'completed',
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'provider' => $provider,
                'external_reference' => $externalReference !== '' ? $externalReference : null,
                'provider_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => (int) ($transaction['id'] ?? 0),
            ]);

            $this->db->commit();

            $completed = $this->findByTransactionCodeForUser($transactionCode, $userId);

            return [
                'status' => 'completed',
                'transaction' => $completed,
                'balance_after' => $balanceAfter,
            ];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            security_log('Không thể hoàn tất nạp ví từ webhook SePay', [
                'transaction_code' => $transactionCode,
                'amount' => $amount,
                'provider' => $provider,
                'external_reference' => $externalReference,
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $this->db->exec("CREATE TABLE IF NOT EXISTS wallet_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            transaction_code VARCHAR(60) NOT NULL UNIQUE,
            transaction_type ENUM('deposit','spend','refund','adjustment') NOT NULL DEFAULT 'deposit',
            payment_method VARCHAR(40) NOT NULL DEFAULT 'manual',
            direction ENUM('credit','debit') NOT NULL DEFAULT 'credit',
            amount DECIMAL(15,2) NOT NULL,
            balance_before DECIMAL(15,2) NOT NULL DEFAULT 0,
            balance_after DECIMAL(15,2) NOT NULL DEFAULT 0,
            status ENUM('pending','completed','failed') NOT NULL DEFAULT 'completed',
            description VARCHAR(255) NULL,
            provider VARCHAR(40) NULL,
            external_reference VARCHAR(120) NULL,
            provider_payload LONGTEXT NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_wallet_user_created (user_id, created_at),
            INDEX idx_wallet_status_type (status, transaction_type),
            CONSTRAINT fk_wallet_transactions_user FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->exec("CREATE TABLE IF NOT EXISTS payment_webhook_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(40) NOT NULL,
            event_key VARCHAR(190) NOT NULL,
            transaction_code VARCHAR(60) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'received',
            payload LONGTEXT NULL,
            last_error VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            processed_at DATETIME NULL,
            UNIQUE KEY uq_payment_webhook_provider_event (provider, event_key),
            INDEX idx_payment_webhook_transaction_code (transaction_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->ensureColumn('wallet_transactions', 'provider', 'ALTER TABLE wallet_transactions ADD COLUMN provider VARCHAR(40) NULL AFTER description');
        $this->ensureColumn('wallet_transactions', 'external_reference', 'ALTER TABLE wallet_transactions ADD COLUMN external_reference VARCHAR(120) NULL AFTER provider');
        $this->ensureColumn('wallet_transactions', 'provider_payload', 'ALTER TABLE wallet_transactions ADD COLUMN provider_payload LONGTEXT NULL AFTER external_reference');
        $this->ensureColumn('wallet_transactions', 'completed_at', 'ALTER TABLE wallet_transactions ADD COLUMN completed_at DATETIME NULL AFTER provider_payload');

        self::$schemaEnsured = true;
    }

    private function ensureColumn(string $table, string $column, string $sql): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :schema
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name');
        $stmt->execute([
            'schema' => (string) ($this->config['db']['name'] ?? ''),
            'table_name' => $table,
            'column_name' => $column,
        ]);
        $exists = (int) ($stmt->fetch()['total'] ?? 0) > 0;

        if (!$exists) {
            $this->db->exec($sql);
        }
    }

    private function fetchUserForUpdate(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, COALESCE(wallet_balance, 0) AS wallet_balance
            FROM users
            WHERE id = :id AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE');
        $stmt->execute(['id' => $userId]);

        return $stmt->fetch() ?: null;
    }

    private function updateUserBalance(int $userId, float $balanceAfter): void
    {
        $stmt = $this->db->prepare('UPDATE users
            SET wallet_balance = :wallet_balance, updated_at = NOW()
            WHERE id = :id');
        $stmt->execute([
            'wallet_balance' => round($balanceAfter, 2),
            'id' => $userId,
        ]);
    }

    private function insertTransaction(array $payload): void
    {
        $stmt = $this->db->prepare('INSERT INTO wallet_transactions (
                user_id,
                transaction_code,
                transaction_type,
                payment_method,
                direction,
                amount,
                balance_before,
                balance_after,
                status,
                description,
                provider,
                external_reference,
                provider_payload,
                completed_at,
                created_at,
                updated_at
            ) VALUES (
                :user_id,
                :transaction_code,
                :transaction_type,
                :payment_method,
                :direction,
                :amount,
                :balance_before,
                :balance_after,
                :status,
                :description,
                :provider,
                :external_reference,
                :provider_payload,
                :completed_at,
                NOW(),
                NOW()
            )');

        $stmt->execute([
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'transaction_code' => (string) ($payload['transaction_code'] ?? ''),
            'transaction_type' => (string) ($payload['transaction_type'] ?? 'deposit'),
            'payment_method' => (string) ($payload['payment_method'] ?? 'manual'),
            'direction' => (string) ($payload['direction'] ?? 'credit'),
            'amount' => round((float) ($payload['amount'] ?? 0), 2),
            'balance_before' => round((float) ($payload['balance_before'] ?? 0), 2),
            'balance_after' => round((float) ($payload['balance_after'] ?? 0), 2),
            'status' => (string) ($payload['status'] ?? 'completed'),
            'description' => $payload['description'] ?? null,
            'provider' => $payload['provider'] ?? null,
            'external_reference' => $payload['external_reference'] ?? null,
            'provider_payload' => $payload['provider_payload'] ?? null,
            'completed_at' => $payload['completed_at'] ?? null,
        ]);
    }

    private function generateTransactionCode(): string
    {
        do {
            $code = 'WAL-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $stmt = $this->db->prepare('SELECT id FROM wallet_transactions WHERE transaction_code = :transaction_code LIMIT 1');
            $stmt->execute(['transaction_code' => $code]);
            $exists = $stmt->fetch() !== false;
        } while ($exists);

        return $code;
    }

    private function generateDepositTransferCode(): string
    {
        $attempts = 0;

        do {
            $code = 'zno' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $this->db->prepare('SELECT id FROM wallet_transactions WHERE LOWER(transaction_code) = :transaction_code LIMIT 1');
            $stmt->execute(['transaction_code' => strtolower($code)]);
            $exists = $stmt->fetch() !== false;
            $attempts++;
        } while ($exists && $attempts < 200);

        if ($exists) {
            throw new \RuntimeException('Không thể tạo mã nạp ví ngắn duy nhất. Bạn thử lại sau ít phút nhé.');
        }

        return strtolower($code);
    }

    private function emptySummary(): array
    {
        return [
            'current_balance' => 0,
            'total_deposit' => 0,
            'display_spent' => 0,
            'deposit_count' => 0,
            'latest_deposit_at' => null,
            'latest_activity_at' => null,
        ];
    }
}
