<?php

declare(strict_types=1);

namespace Frank\Services;

use Frank\Core\Clock;
use Frank\Models\User;

/**
 * UserService
 *
 * Framework-owned service for business logic that concerns the user record
 * beyond raw DB access. Introduced in FrankPHP v1.3.2 as part of the
 * strict Model/Service separation refactor.
 *
 * Responsibility boundary:
 * - This service owns any operation on the users table that requires
 *   business logic, timestamp generation, or coordination between
 *   multiple model calls.
 * - The User model provides DB access only — it accepts pre-computed
 *   values as parameters and executes the query. It does not call Clock.
 *
 * Container registration: singleton in bootstrap.php.
 * Dependencies: User model (instantiated internally — lightweight, no DI needed).
 */
class UserService
{
    private User $userModel;

    public function __construct(?User $userModel = null)
    {
        $this->userModel = $userModel ?? new User();
    }

    // ----------------------------------------------------------------
    // Login tracking
    // ----------------------------------------------------------------

    /**
     * Record a successful login for a user.
     *
     * Called by AuthController immediately after session is established.
     * Clock::nowUtcString() is computed here (Service layer) and passed
     * as a plain string to the model — the model never calls Clock.
     *
     * Tenant-scoped: can only update a user belonging to the given tenant.
     *
     * @param int $userId
     * @param int $tenantId
     * @return bool  False signals a DB error; caller should treat as a
     *               system fault and may choose to abort the login.
     */
    public function recordLogin(int $userId, int $tenantId): bool
    {
        return $this->userModel->updateLastAccessed(
            $userId,
            $tenantId,
            Clock::nowUtcString()
        );
    }

    // ----------------------------------------------------------------
    // Settings
    // ----------------------------------------------------------------

    /**
     * Persist user preference columns.
     *
     * Owns the allowlist of writable columns — only those listed here can
     * be written via settings forms, regardless of what the caller passes.
     *
     * Owns the null-coercion rule: an empty string timezone value is set
     * to null so that the timezone resolution chain
     * (user → tenant → config → UTC) can fall through correctly.
     *
     * Computes $updatedAt via Clock and passes it as a string to the model.
     * The model receives a clean, validated column map and executes the query.
     *
     * @param int   $userId
     * @param int   $tenantId
     * @param array $data  Raw form/request values
     * @return bool
     */
    public function saveSettings(int $userId, int $tenantId, array $data): bool
    {
        $allowed = [
            'timezone',
        ];

        $columns = array_intersect_key($data, array_flip($allowed));

        // Empty string timezone reverts to null so that preference
        // inheritance (user → tenant → config → UTC) can resolve correctly.
        foreach (['timezone'] as $nullable) {
            if (array_key_exists($nullable, $columns) && trim((string) $columns[$nullable]) === '') {
                $columns[$nullable] = null;
            }
        }

        return $this->userModel->saveSettings(
            $userId,
            $tenantId,
            $columns,
            Clock::nowUtcString()
        );
    }
}
