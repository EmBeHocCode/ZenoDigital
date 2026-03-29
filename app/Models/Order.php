<?php

namespace App\Models;

use App\Core\Model;

class Order extends Model
{
    private string $lastError = '';

    public function __construct(array $config)
    {
        parent::__construct($config);

        // Added: ensure wallet ledger schema is ready before checkout spends balance.
        new WalletTransaction($config);
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function createProductOrder(int $userId, int $productId, float $totalAmount, string $status = 'paid'): int|false
    {
        $this->lastError = '';
        $totalAmount = round($totalAmount, 2);
        if ($userId <= 0 || $productId <= 0 || $totalAmount <= 0) {
            $this->lastError = 'invalid_payload';
            return false;
        }

        try {
            $this->db->beginTransaction();

            // Added: lock wallet balance so order creation and wallet deduction stay atomic.
            $userStmt = $this->db->prepare('SELECT id, COALESCE(wallet_balance, 0) AS wallet_balance
                FROM users
                WHERE id = :user_id AND deleted_at IS NULL
                LIMIT 1
                FOR UPDATE');
            $userStmt->execute(['user_id' => $userId]);
            $user = $userStmt->fetch();

            if (!$user) {
                $this->lastError = 'user_not_found';
                $this->db->rollBack();
                return false;
            }

            $balanceBefore = (float) ($user['wallet_balance'] ?? 0);
            if ($balanceBefore < $totalAmount) {
                $this->lastError = 'insufficient_balance';
                $this->db->rollBack();
                return false;
            }

            $balanceAfter = $balanceBefore - $totalAmount;
            $orderCode = $this->generateOrderCode();
            $walletTransactionCode = $this->generateWalletTransactionCode();

            $orderStmt = $this->db->prepare('INSERT INTO orders (
                    user_id,
                    order_code,
                    total_amount,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    :user_id,
                    :order_code,
                    :total_amount,
                    :status,
                    NOW(),
                    NOW()
                )');
            $orderStmt->execute([
                'user_id' => $userId,
                'order_code' => $orderCode,
                'total_amount' => $totalAmount,
                'status' => $status,
            ]);

            $orderId = (int) $this->db->lastInsertId();

            $itemStmt = $this->db->prepare('INSERT INTO order_items (
                    order_id,
                    product_id,
                    quantity,
                    unit_price,
                    total_price,
                    created_at
                ) VALUES (
                    :order_id,
                    :product_id,
                    :quantity,
                    :unit_price,
                    :total_price,
                    NOW()
                )');
            $itemStmt->execute([
                'order_id' => $orderId,
                'product_id' => $productId,
                'quantity' => 1,
                'unit_price' => $totalAmount,
                'total_price' => $totalAmount,
            ]);

            $balanceStmt = $this->db->prepare('UPDATE users
                SET wallet_balance = :wallet_balance, updated_at = NOW()
                WHERE id = :user_id');
            $balanceStmt->execute([
                'wallet_balance' => $balanceAfter,
                'user_id' => $userId,
            ]);

            // Added: persist wallet spend history for order payments.
            $walletStmt = $this->db->prepare('INSERT INTO wallet_transactions (
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
            $walletStmt->execute([
                'user_id' => $userId,
                'transaction_code' => $walletTransactionCode,
                'transaction_type' => 'spend',
                'payment_method' => 'wallet',
                'direction' => 'debit',
                'amount' => $totalAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'description' => 'Thanh toán đơn hàng ' . $orderCode,
            ]);

            $this->db->commit();
            return $orderId;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->lastError = 'order_create_failed';

            security_log('Không thể tạo đơn hàng checkout', [
                'user_id' => $userId,
                'product_id' => $productId,
                'amount' => $totalAmount,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function paginated(string $search, string $status, int $page, int $perPage): array
    {
        $where = ['o.deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $where[] = '(o.order_code LIKE :search OR u.full_name LIKE :search OR u.email LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($status !== '') {
            $where[] = 'o.status = :status';
            $params['status'] = $status;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM orders o LEFT JOIN users u ON u.id = o.user_id {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $meta = paginate_meta($total, $page, $perPage);

        $stmt = $this->db->prepare("SELECT o.*, u.full_name, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id {$whereSql} ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $meta['per_page'], \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $meta['offset'], \PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'meta' => $meta];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT o.*, u.full_name, u.email, u.phone, u.address FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = :id AND o.deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        return $this->hydrateItems($order);
    }

    public function findByOrderCode(string $orderCode): ?array
    {
        $orderCode = strtoupper(trim($orderCode));
        if ($orderCode === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT o.*, u.full_name, u.email, u.phone, u.address
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            WHERE o.order_code = :order_code
              AND o.deleted_at IS NULL
            LIMIT 1');
        $stmt->execute(['order_code' => $orderCode]);
        $order = $stmt->fetch();

        return $order ? $this->hydrateItems($order) : null;
    }

    public function findByOrderCodeForUser(string $orderCode, int $userId): ?array
    {
        $orderCode = strtoupper(trim($orderCode));
        if ($orderCode === '' || $userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT o.*, u.full_name, u.email, u.phone, u.address
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            WHERE o.order_code = :order_code
              AND o.user_id = :user_id
              AND o.deleted_at IS NULL
            LIMIT 1');
        $stmt->execute([
            'order_code' => $orderCode,
            'user_id' => $userId,
        ]);
        $order = $stmt->fetch();

        return $order ? $this->hydrateItems($order) : null;
    }

    public function findByOrderCodeAndEmail(string $orderCode, string $email): ?array
    {
        $orderCode = strtoupper(trim($orderCode));
        $email = strtolower(trim($email));

        if ($orderCode === '' || $email === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT o.*, u.full_name, u.email, u.phone, u.address
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            WHERE o.order_code = :order_code
              AND LOWER(u.email) = :email
              AND o.deleted_at IS NULL
              AND u.deleted_at IS NULL
            LIMIT 1');
        $stmt->execute([
            'order_code' => $orderCode,
            'email' => $email,
        ]);
        $order = $stmt->fetch();

        return $order ? $this->hydrateItems($order) : null;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE orders SET deleted_at = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function byUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE user_id = :user_id AND deleted_at IS NULL ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function latestCompletedOrderForUserProduct(int $userId, int $productId): ?array
    {
        $stmt = $this->db->prepare("SELECT
                o.id,
                o.order_code,
                o.total_amount,
                o.status,
                o.created_at
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            WHERE o.user_id = :user_id
              AND oi.product_id = :product_id
              AND o.deleted_at IS NULL
              AND o.status IN ('paid', 'processing', 'completed')
            ORDER BY o.created_at DESC, o.id DESC
            LIMIT 1");
        $stmt->execute([
            'user_id' => $userId,
            'product_id' => $productId,
        ]);

        return $stmt->fetch() ?: null;
    }

    public function countAll(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) AS total FROM orders WHERE deleted_at IS NULL');
        return (int) $stmt->fetch()['total'];
    }

    public function totalRevenue(): float
    {
        $stmt = $this->db->query("SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM orders WHERE status IN ('paid', 'processing', 'completed') AND deleted_at IS NULL");
        return (float) $stmt->fetch()['revenue'];
    }

    public function latest(int $limit = 5): array
    {
        $stmt = $this->db->prepare('SELECT order_code, total_amount, status, created_at FROM orders WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function revenueByMonth(int $months = 6): array
    {
        $stmt = $this->db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COALESCE(SUM(total_amount),0) AS total
            FROM orders
            WHERE deleted_at IS NULL
              AND status IN ('paid', 'processing', 'completed')
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC");
        $stmt->bindValue(':months', $months, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS total FROM orders WHERE status = :status AND deleted_at IS NULL');
        $stmt->execute(['status' => $status]);
        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function todayRevenue(): float
    {
        $stmt = $this->db->query("SELECT COALESCE(SUM(total_amount), 0) AS total
            FROM orders
            WHERE status IN ('paid', 'processing', 'completed')
              AND deleted_at IS NULL
              AND DATE(created_at) = CURDATE()");
        return (float) ($stmt->fetch()['total'] ?? 0);
    }

    public function todayOrdersCount(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) AS total FROM orders WHERE deleted_at IS NULL AND DATE(created_at) = CURDATE()');
        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function statusBreakdown(): array
    {
        $stmt = $this->db->query('SELECT status, COUNT(*) AS total FROM orders WHERE deleted_at IS NULL GROUP BY status');
        return $stmt->fetchAll() ?: [];
    }

    public function latestByFilters(array $filters, int $limit = 5): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        [$dateSql, $dateParams] = $this->buildDatePresetClause((string) ($filters['date_preset'] ?? ''));
        if ($dateSql !== '') {
            $where[] = $dateSql;
            $params = array_merge($params, $dateParams);
        }

        $sql = 'SELECT order_code, total_amount, status, created_at
            FROM orders
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY created_at DESC, id DESC
            LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function summaryByFilters(array $filters): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        [$dateSql, $dateParams] = $this->buildDatePresetClause((string) ($filters['date_preset'] ?? ''));
        if ($dateSql !== '') {
            $where[] = $dateSql;
            $params = array_merge($params, $dateParams);
        }

        $stmt = $this->db->prepare('SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(total_amount), 0) AS total_amount
            FROM orders
            WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);

        return $stmt->fetch() ?: [
            'total_orders' => 0,
            'total_amount' => 0,
        ];
    }

    public function ordersByMonth(int $months = 6): array
    {
        $stmt = $this->db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
            FROM orders
            WHERE deleted_at IS NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC");
        $stmt->bindValue(':months', $months, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function generateOrderCode(): string
    {
        do {
            $code = 'ORD-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $stmt = $this->db->prepare('SELECT id FROM orders WHERE order_code = :order_code LIMIT 1');
            $stmt->execute(['order_code' => $code]);
            $exists = $stmt->fetch() !== false;
        } while ($exists);

        return $code;
    }

    private function buildDatePresetClause(string $datePreset): array
    {
        $datePreset = trim(strtolower($datePreset));

        return match ($datePreset) {
            'today' => ['DATE(created_at) = CURDATE()', []],
            'last_7_days' => ['created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', []],
            default => ['', []],
        };
    }

    private function hydrateItems(array $order): array
    {
        $itemStmt = $this->db->prepare('SELECT oi.*, p.name AS product_name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = :order_id');
        $itemStmt->execute(['order_id' => (int) ($order['id'] ?? 0)]);
        $order['items'] = $itemStmt->fetchAll();

        return $order;
    }

    private function generateWalletTransactionCode(): string
    {
        do {
            $code = 'WAL-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $stmt = $this->db->prepare('SELECT id FROM wallet_transactions WHERE transaction_code = :transaction_code LIMIT 1');
            $stmt->execute(['transaction_code' => $code]);
            $exists = $stmt->fetch() !== false;
        } while ($exists);

        return $code;
    }
}
