<?php

namespace App\Core;

class Auth
{
    private const ADMIN_ROLE_KEYWORDS = [
        'admin',
        'administrator',
        'super_admin',
        'superadmin',
    ];

    private const STAFF_ROLE_KEYWORDS = [
        'staff',
        'support',
        'support_staff',
        'operator',
        'moderator',
        'agent',
        'assistant',
    ];

    private const MANAGEMENT_ROLE_KEYWORDS = [
        'manager',
        'management',
        'owner',
        'director',
        'supervisor',
        'team_lead',
        'teamlead',
        'lead',
        'head',
        'coordinator',
        'chief',
    ];

    public static function check(): bool
    {
        return !empty($_SESSION['auth']);
    }

    public static function user(): ?array
    {
        return $_SESSION['auth'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['auth']['id']) ? (int) $_SESSION['auth']['id'] : null;
    }

    public static function roleName(): string
    {
        return strtolower(trim((string) (self::user()['role_name'] ?? 'user')));
    }

    public static function isAdmin(): bool
    {
        return self::matchesRole(self::roleName(), self::ADMIN_ROLE_KEYWORDS);
    }

    public static function isStaff(): bool
    {
        return self::matchesRole(self::roleName(), self::STAFF_ROLE_KEYWORDS);
    }

    public static function isManagementRole(): bool
    {
        return self::matchesRole(self::roleName(), self::MANAGEMENT_ROLE_KEYWORDS);
    }

    public static function isBackoffice(): bool
    {
        if (!self::check()) {
            return false;
        }

        return self::isAdmin() || self::isStaff() || self::isManagementRole();
    }

    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }

        $roleType = self::resolveRoleType();
        $permissions = match ($roleType) {
            'admin' => [
                'backoffice.dashboard',
                'backoffice.ai',
                'backoffice.data.products',
                'backoffice.data.orders',
                'backoffice.data.payments',
                'backoffice.data.coupons',
                'backoffice.data.feedback',
                'backoffice.data.finance',
                'backoffice.data.users',
                'backoffice.data.rank',
                'admin.dashboard.finance',
                'admin.dashboard.users',
                'admin.dashboard.rank',
                'admin.products.view',
                'admin.products.manage',
                'admin.categories.view',
                'admin.categories.manage',
                'admin.orders.view',
                'admin.orders.manage',
                'admin.payments.view',
                'admin.feedback.view',
                'admin.feedback.manage',
                'admin.coupons.view',
                'admin.coupons.manage',
                'admin.users.manage',
                'admin.settings.manage',
                'admin.sql.manage',
                'admin.ranks.manage',
                'admin.audit.view',
            ],
            'staff', 'management' => [
                'backoffice.dashboard',
                'backoffice.ai',
                'backoffice.data.products',
                'backoffice.data.orders',
                'backoffice.data.coupons',
                'backoffice.data.feedback',
            ],
            default => [],
        };

        return in_array($permission, $permissions, true);
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['auth'] = [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'avatar' => $user['avatar'] ?? null,
            'role_name' => $user['role_name'] ?? 'user',
            'status' => $user['status'] ?? 'active',
            'wallet_balance' => (float) ($user['wallet_balance'] ?? 0),
            'gender' => normalize_user_gender((string) ($user['gender'] ?? 'unknown')),
            'birth_date' => normalize_birth_date((string) ($user['birth_date'] ?? '')),
            'two_factor_enabled' => !empty($user['two_factor_enabled']),
            'session_token' => (string) ($user['session_token'] ?? ''),
        ];
        $_SESSION['_last_activity'] = time();
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_regenerate_id(true);
        session_destroy();
    }

    private static function resolveRoleType(): string
    {
        if (self::isAdmin()) {
            return 'admin';
        }

        if (self::isStaff()) {
            return 'staff';
        }

        if (self::isManagementRole()) {
            return 'management';
        }

        return 'customer';
    }

    private static function matchesRole(string $roleName, array $keywords): bool
    {
        if ($roleName === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim((string) $keyword));
            if ($keyword === '') {
                continue;
            }

            if ($roleName === $keyword || str_contains($roleName, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
