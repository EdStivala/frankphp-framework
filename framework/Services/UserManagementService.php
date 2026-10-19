<?php

declare(strict_types=1);

namespace Frank\Services;

use Frank\Core\Clock;
use Frank\Models\Tenant;
use Frank\Models\User;

/**
 * UserManagementService
 *
 * Framework service introduced in v1.4.
 *
 * Provides the data payload for the admin/owner user management dashboard.
 * Owns all Clock calls for this feature. Formats all display values so the
 * view receives flat, named scalars and performs no computation.
 *
 * All four KPI stats are derived from the allByTenant() rows already
 * fetched — no additional DB round-trips are made.
 *
 * Scope (v2.2.0): a 'platform' user sees every tenant's users; every other
 * role sees only its own tenant. The platform view reuses Tenant::findAll()
 * and User::allByTenant() — one tenant-scoped query per tenant — so no
 * unscoped User query exists.
 */
class UserManagementService
{
    public function __construct(
        private User $userModel,
        private Tenant $tenantModel
    ) {}

    /**
     * Fetch and prepare all data for the user management dashboard.
     *
     * Returns a ServiceResult with:
     *   - users              array   Display-ready user rows (see below)
     *   - totalUsers         int     Total users in scope
     *   - activePercent      int     % of users who have logged in at least once (0–100)
     *   - neverLoggedIn      int     Count of users where accessed_at IS NULL
     *   - inactiveThirtyDays int     Count of users active before but silent for 30+ days
     *   - crossTenant        bool    True when the list spans all tenants (platform role)
     *
     * Each row in `users` contains:
     *   id, email, name, role, tenantName, joinedLabel, lastLoginLabel, hasLoggedIn
     *
     * @param int   $tenantId
     * @param array $user    Authenticated user array (scope + timezone resolution)
     * @param array $tenant  Current tenant array (timezone resolution)
     * @param array $config  App config array (timezone resolution)
     */
    public function getUserDashboard(int $tenantId, array $user, array $tenant, array $config): ServiceResult
    {
        $timezone      = Clock::resolveTimezone($user, $tenant, $config);
        $inactiveCutoff = Clock::utcOffsetString('-30 days');

        $crossTenant = ($user['role'] ?? '') === 'platform';
        $tenants     = $crossTenant
            ? $this->tenantModel->findAll()
            : [['id' => $tenantId, 'name' => $tenant['name'] ?? '']];

        // Reuses Tenant::findAll() and User::allByTenant() — every query stays tenant-scoped
        $rawUsers = [];
        foreach ($tenants as $t) {
            foreach ($this->userModel->allByTenant((int) $t['id']) as $row) {
                $row['tenant_name'] = $t['name'] ?? '';
                $rawUsers[]         = $row;
            }
        }

        $totalUsers         = count($rawUsers);
        $activeCount        = 0;
        $neverLoggedIn      = 0;
        $inactiveThirtyDays = 0;
        $users              = [];

        foreach ($rawUsers as $row) {
            $hasLoggedIn = $row['accessed_at'] !== null;

            if (!$hasLoggedIn) {
                $neverLoggedIn++;
            } else {
                $activeCount++;
                if ($row['accessed_at'] < $inactiveCutoff) {
                    $inactiveThirtyDays++;
                }
            }

            $users[] = [
                'id'             => $row['id'],
                'email'          => $row['email'],
                'name'           => $row['name'],
                'role'           => $row['role'] ?? 'user',
                'tenantName'     => $row['tenant_name'],
                'joinedLabel'    => Clock::formatUtcForTimezone($row['created_at'], $timezone, 'j M Y'),
                'lastLoginLabel' => $hasLoggedIn
                    ? Clock::formatUtcForTimezone($row['accessed_at'], $timezone, 'j M Y, H:i')
                    : 'Never logged in',
                'hasLoggedIn'    => $hasLoggedIn,
            ];
        }

        $activePercent = $totalUsers > 0 ? (int) round($activeCount / $totalUsers * 100) : 0;

        return ServiceResult::success('', [
            'users'              => $users,
            'totalUsers'         => $totalUsers,
            'activePercent'      => $activePercent,
            'neverLoggedIn'      => $neverLoggedIn,
            'inactiveThirtyDays' => $inactiveThirtyDays,
            'crossTenant'        => $crossTenant,
        ]);
    }
}
