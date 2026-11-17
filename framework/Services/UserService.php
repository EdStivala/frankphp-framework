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
     * Sets accessed_at only; a login is not a change to the user's profile,
     * so updated_at is not changed. Uses the methods User inherits from
     * BaseModel: find() (tenant-scoped) then save(). Clock::nowUtcString()
     * is computed here (Service layer) — the model never calls Clock.
     *
     * @param int $userId
     * @param int $tenantId
     * @return bool  False signals a DB error; caller should treat as a
     *               system fault and may choose to abort the login.
     */
    public function recordLogin(int $userId, int $tenantId): bool
    {
        $user = User::find($userId, $tenantId);
        if ($user === null) {
            return false;
        }

        $user->accessed_at = Clock::nowUtcString();
        return $user->save();
    }

    // ----------------------------------------------------------------
    // Settings
    // ----------------------------------------------------------------

    /**
     * Persist user preference columns.
     *
     * Owns the allowlist of writable columns — only those listed here can
     * be written via settings forms, regardless of what the caller passes.
     * `email` is not on it: an email address cannot be changed here.
     *
     * Owns the null-coercion rule: an empty string timezone value is set
     * to null so that the timezone resolution chain
     * (user → tenant → config → UTC) can fall through correctly.
     *
     * Manages updated_at: sets it with Clock::nowUtcString() when there is
     * something to save. Uses the methods User inherits from BaseModel:
     * find() (tenant-scoped), fill(), save().
     *
     * @param int   $userId
     * @param int   $tenantId
     * @param array $data  Raw form/request values
     * @return bool
     */
    public function saveSettings(int $userId, int $tenantId, array $data): bool
    {
        $allowed = [
            'name',
            'timezone',
        ];

        $columns = array_intersect_key($data, array_flip($allowed));

        // Name (v3.0.0): trimmed, required, max 255 characters (the column
        // size). An invalid name fails the whole save — nothing is written.
        if (array_key_exists('name', $columns)) {
            if (!is_string($columns['name'])) {
                return false;
            }
            $name = trim($columns['name']);
            if ($name === '' || mb_strlen($name) > 255) {
                return false;
            }
            $columns['name'] = $name;
        }

        // Empty string timezone reverts to null so that preference
        // inheritance (user → tenant → config → UTC) can resolve correctly.
        foreach (['timezone'] as $nullable) {
            if (array_key_exists($nullable, $columns) && trim((string) $columns[$nullable]) === '') {
                $columns[$nullable] = null;
            }
        }

        if (empty($columns)) {
            return true;
        }

        $user = User::find($userId, $tenantId);
        if ($user === null) {
            return false;
        }

        $user->fill($columns);
        $user->updated_at = Clock::nowUtcString();
        return $user->save();
    }

    // ----------------------------------------------------------------
    // Operations (v3.0.0) — which roles may call these is the app's decision
    // ----------------------------------------------------------------

    /**
     * Delete one user in a tenant. Cannot be undone.
     *
     * Refuses when the target does not exist in $tenantId (failure type
     * 'not_found') and when the target's role is 'owner' (failure type
     * 'owner_requires_tenant_delete' — an owner is only removed by deleting
     * the whole tenant, TenantService::deleteTenant()).
     *
     * Does NOT decide who may delete users (owner, or "your own
     * account"): that check belongs to the calling app.
     */
    public function deleteUser(int $tenantId, int $targetUserId): ServiceResult
    {
        // Inherited from BaseModel: tenant-scoped load, a User object or null.
        $target = User::find($targetUserId, $tenantId);

        if ($target === null) {
            return ServiceResult::failure('User not found.', 'not_found');
        }

        if (strtolower((string) $target->role) === 'owner') {
            return ServiceResult::failure(
                "An owner's account can only be removed by deleting the tenant.",
                'owner_requires_tenant_delete'
            );
        }

        // Inherited from BaseModel: tenant-scoped delete.
        if (!$target->delete()) {
            return ServiceResult::failure('The user could not be deleted.', 'system');
        }

        return ServiceResult::success('User deleted.');
    }

    /**
     * Change one user's role in a tenant.
     *
     * Refuses (failure type in brackets):
     * - the target does not exist in $tenantId ('not_found');
     * - the target's current role is 'owner' or 'platform' ('role_protected');
     * - $newRole is 'owner' or 'platform' ('role_not_assignable');
     * - $newRole is empty or longer than 50 characters ('validation').
     *
     * It does NOT check $newRole against any list: which roles exist is the
     * app's business. It also does NOT decide who may change roles — that
     * check belongs to the calling app. The roles 'owner' and 'platform' are
     * only ever set or removed by hand in the database.
     */
    public function changeRole(int $tenantId, int $targetUserId, string $newRole): ServiceResult
    {
        // Inherited from BaseModel: tenant-scoped load, a User object or null.
        $target = User::find($targetUserId, $tenantId);

        if ($target === null) {
            return ServiceResult::failure('User not found.', 'not_found');
        }

        $protected = ['owner', 'platform'];

        if (in_array(strtolower((string) $target->role), $protected, true)) {
            return ServiceResult::failure(
                "The role of an {$target->role} account cannot be changed.",
                'role_protected'
            );
        }

        $newRole = trim($newRole);

        if ($newRole === '' || mb_strlen($newRole) > 50) {
            return ServiceResult::failure('A role must be between 1 and 50 characters.', 'validation');
        }

        if (in_array(strtolower($newRole), $protected, true)) {
            return ServiceResult::failure('That role cannot be assigned.', 'role_not_assignable');
        }

        // Inherited from BaseModel: save() writes the object's fields.
        $target->role       = $newRole;
        $target->updated_at = Clock::nowUtcString();
        if (!$target->save()) {
            return ServiceResult::failure('The role could not be changed.', 'system');
        }

        return ServiceResult::success('Role changed.', ['role' => $newRole]);
    }
}
