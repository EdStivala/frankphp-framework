<?php

declare(strict_types=1);

namespace Frank\Services;

use Frank\Core\Clock;
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
 * All four KPI stats are derived from the single allByTenant() query —
 * no additional DB round-trips are made.
 */
class UserManagementService
{
    public function __construct(private User $userModel) {}

    /**
     * Fetch and prepare all data for the user management dashboard.
     *
     * Returns a ServiceResult with:
     *   - users              array   Display-ready user rows (see below)
     *   - totalUsers         int     Total users in this tenant
     *   - activePercent      int     % of users who have logged in at least once (0–100)
     *   - neverLoggedIn      int     Count of users where accessed_at IS NULL
     *   - inactiveThirtyDays int     Count of users active before but silent for 30+ days
     *
     * Each row in `users` contains:
     *   id, email, name, role, joinedLabel, lastLoginLabel, hasLoggedIn
     *
     * @param int   $tenantId
     * @param array $user    Authenticated user array (timezone resolution)
     * @param array $tenant  Current tenant array (timezone resolution)
     * @param array $config  App config array (timezone resolution)
     */
    public function getUserDashboard(int $tenantId, array $user, array $tenant, array $config): ServiceResult
    {
        $timezone      = Clock::resolveTimezone($user, $tenant, $config);
        $inactiveCutoff = Clock::utcOffsetString('-30 days');

        $rawUsers = $this->userModel->allByTenant($tenantId);

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
        ]);
    }
}
