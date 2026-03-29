<?php

namespace App\Models;

use App\Core\Model;

class RankProgram extends Model
{
    private static bool $schemaReady = false;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->ensureSchema();
    }

    public function getRankSummary(int $userId): array
    {
        $stats = $this->purchaseStats($userId);
        $points = (int) floor(((float) ($stats['total_spent'] ?? 0)) / 1000);
        $rank = $this->resolveRank($points);
        $nextRank = $this->resolveNextRank($points);

        if (($rank['min_points'] ?? 0) > 0) {
            $this->issueCouponForRank($userId, $rank);
        }

        $progressPercent = 100;
        $pointsToNext = 0;

        if ($nextRank !== null) {
            $rangeStart = (int) ($rank['min_points'] ?? 0);
            $rangeEnd = (int) ($nextRank['min_points'] ?? 0);
            $range = max(1, $rangeEnd - $rangeStart);
            $progressPercent = (int) max(0, min(100, floor((($points - $rangeStart) / $range) * 100)));
            $pointsToNext = max(0, $rangeEnd - $points);
        }

        return [
            'points' => $points,
            'orders_count' => (int) ($stats['orders_count'] ?? 0),
            'total_spent' => (float) ($stats['total_spent'] ?? 0),
            'rank' => $rank,
            'next_rank' => $nextRank,
            'progress_percent' => $progressPercent,
            'points_to_next' => $pointsToNext,
            'coupons' => $this->recentCoupons($userId),
        ];
    }

    public function adminOverview(int $couponLimit = 8, int $topUserLimit = 8): array
    {
        $couponStatsStmt = $this->db->query('SELECT COUNT(*) AS total_coupons, SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) AS used_coupons
            FROM user_rank_coupons');
        $couponStats = $couponStatsStmt->fetch() ?: ['total_coupons' => 0, 'used_coupons' => 0];

        $latestCouponsStmt = $this->db->prepare('SELECT c.coupon_code, c.rank_key, c.discount_percent, c.issued_at, c.expires_at, c.is_used,
                u.full_name, u.email
            FROM user_rank_coupons c
            INNER JOIN users u ON u.id = c.user_id
            ORDER BY c.created_at DESC
            LIMIT :limit');
        $latestCouponsStmt->bindValue(':limit', $couponLimit, \PDO::PARAM_INT);
        $latestCouponsStmt->execute();
        $latestCoupons = $latestCouponsStmt->fetchAll() ?: [];

        $topUsersStmt = $this->db->prepare("SELECT u.id, u.full_name, u.email,
                COUNT(o.id) AS orders_count,
                COALESCE(SUM(o.total_amount), 0) AS total_spent,
                FLOOR(COALESCE(SUM(o.total_amount), 0) / 1000) AS points
            FROM users u
            LEFT JOIN orders o ON o.user_id = u.id
                AND o.deleted_at IS NULL
                AND o.status IN ('paid','processing','completed')
            WHERE u.deleted_at IS NULL
            GROUP BY u.id, u.full_name, u.email
            HAVING points > 0
            ORDER BY points DESC, total_spent DESC
            LIMIT :limit");
        $topUsersStmt->bindValue(':limit', $topUserLimit, \PDO::PARAM_INT);
        $topUsersStmt->execute();
        $topUsers = $topUsersStmt->fetchAll() ?: [];

        return [
            'total_coupons' => (int) ($couponStats['total_coupons'] ?? 0),
            'used_coupons' => (int) ($couponStats['used_coupons'] ?? 0),
            'active_coupons' => max(0, (int) ($couponStats['total_coupons'] ?? 0) - (int) ($couponStats['used_coupons'] ?? 0)),
            'latest_coupons' => $latestCoupons,
            'top_users' => $topUsers,
        ];
    }

    private function purchaseStats(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS orders_count, COALESCE(SUM(total_amount), 0) AS total_spent
            FROM orders
            WHERE user_id = :user_id AND status IN ('paid','processing','completed') AND deleted_at IS NULL");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch() ?: ['orders_count' => 0, 'total_spent' => 0];
    }

    private function rankDefinitions(): array
    {
        $uncommonPoints = max(100, (int) app_setting('rank_uncommon_points', (int) app_setting('rank_silver_points', 500)));
        $rarePoints = max($uncommonPoints + 100, (int) app_setting('rank_rare_points', (int) app_setting('rank_gold_points', 1500)));
        $epicPoints = max($rarePoints + 100, (int) app_setting('rank_epic_points', (int) app_setting('rank_platinum_points', 3000)));
        $legendaryPoints = max($epicPoints + 100, (int) app_setting('rank_legendary_points', (int) app_setting('rank_diamond_points', 6000)));
        $mythicPoints = max($legendaryPoints + 100, (int) app_setting('rank_mythic_points', max($legendaryPoints + 4000, 10000)));

        return [
            [
                'key' => 'common',
                'label' => 'Common',
                'min_points' => 0,
                'discount_percent' => 0,
                'coupon_ttl_days' => 0,
            ],
            [
                'key' => 'uncommon',
                'label' => 'Uncommon',
                'min_points' => $uncommonPoints,
                'discount_percent' => max(1, (int) app_setting('rank_uncommon_discount', (int) app_setting('rank_silver_discount', 3))),
                'coupon_ttl_days' => 30,
            ],
            [
                'key' => 'rare',
                'label' => 'Rare',
                'min_points' => $rarePoints,
                'discount_percent' => max(1, (int) app_setting('rank_rare_discount', (int) app_setting('rank_gold_discount', 5))),
                'coupon_ttl_days' => 45,
            ],
            [
                'key' => 'epic',
                'label' => 'Epic',
                'min_points' => $epicPoints,
                'discount_percent' => max(1, (int) app_setting('rank_epic_discount', (int) app_setting('rank_platinum_discount', 8))),
                'coupon_ttl_days' => 60,
            ],
            [
                'key' => 'legendary',
                'label' => 'Legendary',
                'min_points' => $legendaryPoints,
                'discount_percent' => max(1, (int) app_setting('rank_legendary_discount', (int) app_setting('rank_diamond_discount', 12))),
                'coupon_ttl_days' => 90,
            ],
            [
                'key' => 'mythic',
                'label' => 'Mythic',
                'min_points' => $mythicPoints,
                'discount_percent' => max(1, (int) app_setting('rank_mythic_discount', 18)),
                'coupon_ttl_days' => 120,
            ],
        ];
    }

    private function resolveRank(int $points): array
    {
        $definitions = $this->rankDefinitions();
        $current = $definitions[0];

        foreach ($definitions as $definition) {
            if ($points >= (int) $definition['min_points']) {
                $current = $definition;
            }
        }

        return $current;
    }

    private function resolveNextRank(int $points): ?array
    {
        foreach ($this->rankDefinitions() as $definition) {
            if ($points < (int) $definition['min_points']) {
                return $definition;
            }
        }

        return null;
    }

    private function issueCouponForRank(int $userId, array $rank): void
    {
        $rankKey = (string) ($rank['key'] ?? '');
        $discountPercent = (int) ($rank['discount_percent'] ?? 0);
        $ttlDays = (int) ($rank['coupon_ttl_days'] ?? 0);

        if ($rankKey === '' || $discountPercent <= 0 || $ttlDays <= 0) {
            return;
        }

        $couponAliases = $this->couponAliasesForRank($rankKey);
        $placeholders = [];
        $params = ['user_id' => $userId];
        foreach ($couponAliases as $index => $couponKey) {
            $paramName = 'rank_key_' . $index;
            $placeholders[] = ':' . $paramName;
            $params[$paramName] = $couponKey;
        }

        $existsStmt = $this->db->prepare('SELECT id FROM user_rank_coupons
            WHERE user_id = :user_id AND rank_key IN (' . implode(', ', $placeholders) . ')
            LIMIT 1');
        $existsStmt->execute($params);

        if ($existsStmt->fetch()) {
            return;
        }

        $couponCode = $this->generateCouponCode($rankKey);

        $insertStmt = $this->db->prepare('INSERT INTO user_rank_coupons (user_id, rank_key, coupon_code, discount_percent, issued_at, expires_at, created_at)
            VALUES (:user_id, :rank_key, :coupon_code, :discount_percent, NOW(), DATE_ADD(NOW(), INTERVAL :ttl_days DAY), NOW())');

        $insertStmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $insertStmt->bindValue(':rank_key', $rankKey);
        $insertStmt->bindValue(':coupon_code', $couponCode);
        $insertStmt->bindValue(':discount_percent', $discountPercent, \PDO::PARAM_INT);
        $insertStmt->bindValue(':ttl_days', $ttlDays, \PDO::PARAM_INT);
        $insertStmt->execute();
    }

    private function generateCouponCode(string $rankKey): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $rankKey), 0, 4));
        if ($prefix === '') {
            $prefix = 'RANK';
        }

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $code = $prefix . '-' . $random;

            $stmt = $this->db->prepare('SELECT id FROM user_rank_coupons WHERE coupon_code = :coupon_code LIMIT 1');
            $stmt->execute(['coupon_code' => $code]);
            if (!$stmt->fetch()) {
                return $code;
            }
        }

        return $prefix . '-' . strtoupper(substr(sha1((string) microtime(true)), 0, 8));
    }

    private function recentCoupons(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT coupon_code, rank_key, discount_percent, issued_at, expires_at, is_used
            FROM user_rank_coupons
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT 5');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll() ?: [];
    }

    private function couponAliasesForRank(string $rankKey): array
    {
        $aliases = [$rankKey];
        $legacyMap = [
            'common' => 'starter',
            'uncommon' => 'silver',
            'rare' => 'gold',
            'epic' => 'platinum',
            'legendary' => 'diamond',
        ];

        if (isset($legacyMap[$rankKey])) {
            $aliases[] = $legacyMap[$rankKey];
        }

        return array_values(array_unique(array_filter($aliases, static fn($value) => trim((string) $value) !== '')));
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS user_rank_coupons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                rank_key VARCHAR(30) NOT NULL,
                coupon_code VARCHAR(64) NOT NULL UNIQUE,
                discount_percent INT NOT NULL,
                issued_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                is_used TINYINT(1) NOT NULL DEFAULT 0,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_user_rank_coupons_user FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $indexCheckStmt = $this->db->prepare("SELECT COUNT(*) AS total
                FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'user_rank_coupons' AND index_name = 'uq_user_rank_once'");
            $indexCheckStmt->execute();
            $indexExists = (int) (($indexCheckStmt->fetch()['total'] ?? 0));

            if ($indexExists === 0) {
                $this->db->exec('ALTER TABLE user_rank_coupons ADD UNIQUE INDEX uq_user_rank_once (user_id, rank_key)');
            }
        } catch (\Throwable $exception) {
            security_log('Không thể khởi tạo schema rank coupons', ['error' => $exception->getMessage()]);
        }

        self::$schemaReady = true;
    }
}
