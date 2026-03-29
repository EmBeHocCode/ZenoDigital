<?php

namespace App\Models;

use App\Core\Model;
use PDOException;

class WalletTransaction extends Model
{
    private static bool $schemaReady = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->ensureSchema();
    }

    public function summaryByUser(int $userId): array
    {
        $balanceStmt = $this->db->prepare('SELECT COALESCE(wallet_balance, 0) AS wallet_balance FROM users WHERE id = :user_id AND deleted_at IS NULL LIMIT 1');
        $balanceStmt->execute(['user_id' => $userId]);
        $balanceRow = $balanceStmt->fetch() ?: ['wallet_balance' => 0];

        $summaryStmt = $this->db->prepare("SELECT
                COALESCE(SUM(CASE WHEN transaction_type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END), 0) AS total_deposit,
                COUNT(CASE WHEN transaction_type = 'deposit' AND status = 'completed' THEN 1 END) AS deposit_count,
                COALESCE(SUM(CASE WHEN direction = 'debit' AND status = 'completed' THEN amount ELSE 0 END), 0) AS total_spent,
                MAX(CASE WHEN transaction_type = 'deposit' AND status = 'completed' THEN created_at ELSE NULL END) AS latest_deposit_at,
                MAX(CASE WHEN status = 'completed' THEN created_at ELSE NULL END) AS latest_activity_at
            FROM wallet_transactions
            WHERE user_id = :user_id");
        $summaryStmt->execute(['user_id' => $userId]);
        $summary = $summaryStmt->fetch() ?: [];

        $currentBalance = (float) ($balanceRow['wallet_balance'] ?? 0);
        $totalDeposit = (float) ($summary['total_deposit'] ?? 0);
        $recordedSpent = (float) ($summary['total_spent'] ?? 0);
        $displaySpent = max(0, $totalDeposit - $currentBalance);

        return [
            'current_balance' => $currentBalance,
            'total_deposit' => $totalDeposit,
            'deposit_count' => (int) ($summary['deposit_count'] ?? 0),
            // Added: expose both recorded debit total and display-safe spent value tied to deposit/balance.
            'total_spent' => $recordedSpent,
            'display_spent' => $displaySpent,
            'latest_deposit_at' => $summary['latest_deposit_at'] ?? null,
            'latest_activity_at' => $summary['latest_activity_at'] ?? null,
        ];
    }

    public function recentByUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT *
            FROM wallet_transactions
            WHERE user_id = :user_id
            ORDER BY created_at DESC, id DESC
            LIMIT :limit');
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function recentForAdmin(int $limit = 20, string $status = ''): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($status !== '') {
            $where[] = 'wt.status = :status';
            $params['status'] = $status;
        }

        $stmt = $this->db->prepare('SELECT
                wt.*,
                u.full_name,
                u.email
            FROM wallet_transactions wt
            LEFT JOIN users u ON u.id = wt.user_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY wt.created_at DESC, wt.id DESC
            LIMIT :limit');

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, \PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function adminSummary(): array
    {
        $stmt = $this->db->query("SELECT
                COUNT(*) AS total_transactions,
                COALESCE(SUM(CASE WHEN transaction_type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END), 0) AS total_deposit,
                COALESCE(SUM(CASE WHEN direction = 'debit' AND status = 'completed' THEN amount ELSE 0 END), 0) AS total_debit,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_transactions,
                COUNT(CASE WHEN transaction_type = 'spend' AND status = 'completed' THEN 1 END) AS completed_order_payments
            FROM wallet_transactions");

        return $stmt->fetch() ?: [
            'total_transactions' => 0,
            'total_deposit' => 0,
            'total_debit' => 0,
            'pending_transactions' => 0,
            'completed_order_payments' => 0,
        ];
    }

    public function adminPaginated(string $search, string $transactionType, string $status, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(wt.transaction_code LIKE :search OR u.full_name LIKE :search OR u.email LIKE :search OR wt.description LIKE :search)';
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
        $total = (int) (($countStmt->fetch()['total'] ?? 0));

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
        $stmt->bindValue(':limit', $meta['per_page'], \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $meta['offset'], \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll() ?: [],
            'meta' => $meta,
        ];
    }

    public function createInstantDeposit(int $userId, float $amount, string $paymentMethod, string $description = ''): ?array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }

        try {
            $this->db->beginTransaction();

            $userStmt = $this->db->prepare('SELECT id, COALESCE(wallet_balance, 0) AS wallet_balance
                FROM users
                WHERE id = :user_id AND deleted_at IS NULL
                LIMIT 1
                FOR UPDATE');
            $userStmt->execute(['user_id' => $userId]);
            $user = $userStmt->fetch();

            if (!$user) {
                $this->db->rollBack();
                return null;
            }

            $balanceBefore = (float) ($user['wallet_balance'] ?? 0);
            $balanceAfter = $balanceBefore + $amount;
            $transactionCode = $this->generateTransactionCode();
            $safeDescription = trim($description) !== '' ? trim($description) : 'Nạp số dư tài khoản';

            $updateStmt = $this->db->prepare('UPDATE users
                SET wallet_balance = :wallet_balance, updated_at = NOW()
                WHERE id = :user_id');
            $updateStmt->execute([
                'wallet_balance' => $balanceAfter,
                'user_id' => $userId,
            ]);

            $insertStmt = $this->db->prepare('INSERT INTO wallet_transactions (
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
                    NOW(),
                    NOW()
                )');
            $insertStmt->execute([
                'user_id' => $userId,
                'transaction_code' => $transactionCode,
                'transaction_type' => 'deposit',
                'payment_method' => $paymentMethod,
                'direction' => 'credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'description' => $safeDescription,
            ]);

            $this->db->commit();

            return [
                'transaction_code' => $transactionCode,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'payment_method' => $paymentMethod,
                'description' => $safeDescription,
            ];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            security_log('Không thể tạo giao dịch nạp ví', [
                'user_id' => $userId,
                'amount' => $amount,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        try {
            $this->db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER address");

            $this->db->exec('CREATE TABLE IF NOT EXISTS wallet_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                transaction_code VARCHAR(60) NOT NULL UNIQUE,
                transaction_type ENUM(\'deposit\',\'spend\',\'refund\',\'adjustment\') NOT NULL DEFAULT \'deposit\',
                payment_method VARCHAR(40) NOT NULL DEFAULT \'manual\',
                direction ENUM(\'credit\',\'debit\') NOT NULL DEFAULT \'credit\',
                amount DECIMAL(15,2) NOT NULL,
                balance_before DECIMAL(15,2) NOT NULL DEFAULT 0,
                balance_after DECIMAL(15,2) NOT NULL DEFAULT 0,
                status ENUM(\'pending\',\'completed\',\'failed\') NOT NULL DEFAULT \'completed\',
                description VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_wallet_transactions_user FOREIGN KEY (user_id) REFERENCES users(id),
                INDEX idx_wallet_transactions_user_time (user_id, created_at),
                INDEX idx_wallet_transactions_user_type (user_id, transaction_type),
                INDEX idx_wallet_transactions_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } catch (PDOException $exception) {
            security_log('Không thể khởi tạo schema wallet', ['error' => $exception->getMessage()]);
        }

        self::$schemaReady = true;
    }

    private function generateTransactionCode(): string
    {
        do {
            $code = 'WAL-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $this->db->prepare('SELECT id FROM wallet_transactions WHERE transaction_code = :transaction_code LIMIT 1');
            $stmt->execute(['transaction_code' => $code]);
            $exists = $stmt->fetch() !== false;
        } while ($exists);

        return $code;
    }
}
