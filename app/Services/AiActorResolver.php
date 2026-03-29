<?php

namespace App\Services;

use App\Core\Auth;
use App\Models\User;

class AiActorResolver
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

    private array $config;
    private AiPersonaService $personaService;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->personaService = new AiPersonaService($config);
    }

    public function resolveFromSession(): array
    {
        if (!Auth::check()) {
            return $this->guestActor();
        }

        $sessionUserId = Auth::id();
        if (($sessionUserId ?? 0) <= 0) {
            return $this->unknownActor('missing_session_user');
        }

        return $this->resolveByUserId((int) $sessionUserId);
    }

    public function resolveByUserId(?int $userId): array
    {
        if (($userId ?? 0) <= 0) {
            return $this->guestActor();
        }

        $userModel = new User($this->config);
        $user = $userModel->find((int) $userId);

        if (!$user || (($user['status'] ?? 'active') !== 'active')) {
            return $this->unknownActor('user_not_found_or_inactive');
        }

        return $this->buildActorFromUser($user);
    }

    public function isBackofficeActor(array $actor): bool
    {
        if (array_key_exists('is_backoffice_actor', $actor)) {
            return !empty($actor['is_backoffice_actor']);
        }

        if ($this->personaService->isBackofficeMode((string) ($actor['conversation_mode'] ?? ''))) {
            return true;
        }

        return in_array((string) ($actor['actor_type'] ?? 'unknown'), ['admin', 'staff', 'management'], true);
    }

    private function buildActorFromUser(array $user): array
    {
        $roleName = strtolower(trim((string) ($user['role_name'] ?? '')));
        $actorType = $this->mapRoleToActorType($roleName);
        $roleGroup = $this->resolveRoleGroup($actorType);
        $isBackofficeActor = in_array($actorType, ['admin', 'staff', 'management'], true);
        $fullName = sanitize_text((string) ($user['full_name'] ?? ''), 120);
        $shortName = $this->extractShortName($fullName);
        $gender = normalize_user_gender((string) ($user['gender'] ?? 'unknown'));
        $birthDate = normalize_birth_date((string) ($user['birth_date'] ?? ''));
        $age = calculate_age_from_birth_date($birthDate);

        return [
            'auth_state' => 'authenticated',
            'actor_type' => $actorType,
            'actor_role' => $roleName !== '' ? $roleName : 'unknown',
            'role_group' => $roleGroup,
            'actor_name' => $fullName !== '' ? $fullName : null,
            'actor_short_name' => $shortName !== '' ? $shortName : null,
            'actor_id' => (int) ($user['id'] ?? 0),
            'actor_gender' => $gender,
            'actor_birth_date' => $birthDate,
            'actor_age' => $age,
            'role_name' => $roleName !== '' ? $roleName : 'unknown',
            'is_admin' => $actorType === 'admin',
            'is_staff' => $actorType === 'staff',
            'is_management_role' => $this->isManagementRole($actorType),
            'is_backoffice_actor' => $isBackofficeActor,
            'is_customer' => $actorType === 'customer',
            'is_guest' => false,
            'is_authenticated' => true,
            'trusted_identity' => true,
            'safe_addressing' => $this->resolveSafeAddressing($actorType, $gender, $age),
            'support_scope' => $this->resolveSupportScope($actorType),
            'conversation_mode' => $this->resolveConversationMode($actorType),
        ];
    }

    private function guestActor(): array
    {
        return [
            'auth_state' => 'guest',
            'actor_type' => 'guest',
            'actor_role' => 'guest',
            'role_group' => 'public',
            'actor_name' => null,
            'actor_short_name' => null,
            'actor_id' => null,
            'actor_gender' => 'unknown',
            'actor_birth_date' => null,
            'actor_age' => null,
            'role_name' => null,
            'is_admin' => false,
            'is_staff' => false,
            'is_management_role' => false,
            'is_backoffice_actor' => false,
            'is_customer' => false,
            'is_guest' => true,
            'is_authenticated' => false,
            'trusted_identity' => false,
            'safe_addressing' => 'bạn',
            'support_scope' => 'public',
            'conversation_mode' => $this->personaService->normalizeConversationMode('customer_support'),
        ];
    }

    private function unknownActor(string $reason): array
    {
        return [
            'auth_state' => 'unknown',
            'actor_type' => 'unknown',
            'actor_role' => 'unknown',
            'role_group' => 'safe',
            'actor_name' => null,
            'actor_short_name' => null,
            'actor_id' => null,
            'actor_gender' => 'unknown',
            'actor_birth_date' => null,
            'actor_age' => null,
            'role_name' => null,
            'is_admin' => false,
            'is_staff' => false,
            'is_management_role' => false,
            'is_backoffice_actor' => false,
            'is_customer' => false,
            'is_guest' => false,
            'is_authenticated' => false,
            'trusted_identity' => false,
            'safe_addressing' => 'bạn',
            'support_scope' => 'safe',
            'conversation_mode' => $this->personaService->normalizeConversationMode('customer_support'),
            'resolution_reason' => $reason,
        ];
    }

    private function resolveConversationMode(string $actorType): string
    {
        return match ($actorType) {
            'admin' => 'admin_copilot',
            'staff' => 'staff_support',
            'management' => 'management_support',
            default => 'customer_support',
        };
    }

    private function mapRoleToActorType(string $roleName): string
    {
        if ($roleName === '') {
            return 'unknown';
        }

        if ($this->matchesRoleKeyword($roleName, self::ADMIN_ROLE_KEYWORDS)) {
            return 'admin';
        }

        if ($this->matchesRoleKeyword($roleName, self::STAFF_ROLE_KEYWORDS)) {
            return 'staff';
        }

        if ($this->matchesRoleKeyword($roleName, self::MANAGEMENT_ROLE_KEYWORDS)) {
            return 'management';
        }

        return 'customer';
    }

    private function resolveRoleGroup(string $actorType): string
    {
        return match ($actorType) {
            'admin', 'management' => 'management',
            'staff' => 'operations',
            'customer' => 'customer',
            'guest' => 'public',
            default => 'safe',
        };
    }

    private function isManagementRole(string $actorType): bool
    {
        return in_array($actorType, ['admin', 'management'], true);
    }

    private function resolveSupportScope(string $actorType): string
    {
        return match ($actorType) {
            'admin', 'staff', 'management' => 'backoffice',
            'customer' => 'customer',
            'guest' => 'public',
            default => 'safe',
        };
    }

    private function resolveSafeAddressing(string $actorType, string $gender, ?int $age): string
    {
        if ($actorType === 'guest' || !in_array($actorType, ['customer', 'admin', 'staff', 'management'], true)) {
            return 'bạn';
        }

        if ($age !== null && $age >= 0 && $age < 18) {
            return 'em';
        }

        return match ($gender) {
            'male' => 'anh',
            'female' => 'chị',
            default => 'bạn',
        };
    }

    private function matchesRoleKeyword(string $roleName, array $keywords): bool
    {
        if ($roleName === '') {
            return false;
        }

        $normalizedRole = strtolower(trim(str_replace(['-', '_'], ' ', $roleName)));
        $paddedRole = ' ' . $normalizedRole . ' ';

        foreach ($keywords as $keyword) {
            $normalizedKeyword = strtolower(trim(str_replace(['-', '_'], ' ', (string) $keyword)));
            if ($normalizedKeyword === '') {
                continue;
            }

            if ($normalizedRole === $normalizedKeyword || str_contains($paddedRole, ' ' . $normalizedKeyword . ' ')) {
                return true;
            }

            if (str_contains($roleName, (string) $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function extractShortName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName)) ?: [];
        $parts = array_values(array_filter(array_map(static fn($part) => trim((string) $part), $parts)));

        if (!$parts) {
            return '';
        }

        for ($index = count($parts) - 1; $index >= 0; $index--) {
            $candidate = (string) ($parts[$index] ?? '');
            if ($candidate !== '' && preg_match('/\p{L}/u', $candidate)) {
                return $candidate;
            }
        }

        return (string) ($parts[count($parts) - 1] ?? '');
    }
}
