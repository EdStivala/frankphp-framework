<?php

declare(strict_types=1);

namespace App\Controllers;

use Frank\Core\BaseController;
use Frank\Core\Request;
use Frank\Core\Response;
use Frank\Core\Session;
use Frank\Services\TenantService;
use Frank\Services\UserService;

/**
 * UserAdminController (v3.0.0 template app — a worked EXAMPLE, app-owned)
 *
 * Shows how an app uses the framework's user and tenant operations:
 *   UserService::deleteUser(), UserService::changeRole(),
 *   TenantService::deleteTenant().
 * The framework enforces the data rules inside those operations (the owner is
 * never deleted or re-roled by them, only the owner can delete the tenant).
 * Everything else here — who may call them, the confirmations, the warnings,
 * the redirects — is this app's decision. Change it freely.
 *
 * The example's rules:
 *   - owner or admin: delete a user in their own tenant, change a user's role
 *   - platform: the same two actions on user and admin rows in every tenant
 *   - anyone: delete their own account (an owner is sent into the tenant-delete
 *     flow instead — the owner's account only goes with the whole tenant)
 *   - owner: delete the tenant (warning screen, type the tenant's name, then delete)
 *
 * Every POST here checks the CSRF token ($_SESSION['csrf_token'], hash_equals),
 * the same mechanism as the login forms.
 *
 * Routes (registered in app/bootstrap.php with [$tm, $auth, $json]):
 *   POST /tenant/{tenant_id}/users/delete
 *   POST /tenant/{tenant_id}/users/role
 *   POST /tenant/{tenant_id}/account/delete
 *   GET  /tenant/{tenant_id}/tenant-delete
 *   POST /tenant/{tenant_id}/tenant-delete
 */
class UserAdminController extends BaseController
{
    /** The roles this app lets an owner/admin assign (the role selector offers the same two). */
    private const ASSIGNABLE_ROLES = ['user', 'admin'];

    public function __construct(
        private UserService   $userService,
        private TenantService $tenantService
    ) {
    }

    // ----------------------------------------------------------------
    // Users screen actions (owner / admin)
    // ----------------------------------------------------------------

    /** POST /tenant/{tenant_id}/users/delete — field: user_id */
    public function deleteUser(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;
        $users  = "/tenant/{$tenant['id']}/users";

        if (!$this->canManageUsers($user)) {
            return Response::redirect("/tenant/{$tenant['id']}/dashboard?error=forbidden");
        }

        $data = $this->body($request);
        if (!$this->validCsrf($data)) {
            return Response::redirect($users . '?error=invalid_request');
        }

        $targetId = (int) ($data['user_id'] ?? 0);

        // Deleting your own account lives on the account screen.
        if ($targetId === (int) $user['id']) {
            return Response::redirect($users . '?error=use_account_settings');
        }

        $result = $this->userService->deleteUser($this->targetTenantId($user, $tenant, $data), $targetId);

        if (!$result->success) {
            return Response::redirect($users . '?error=' . match ($result->errorType) {
                'owner_requires_tenant_delete' => 'owner_cannot_be_deleted',
                'not_found'                    => 'user_not_found',
                default                        => 'delete_failed',
            });
        }

        return Response::redirect($users . '?success=user_deleted');
    }

    /** POST /tenant/{tenant_id}/users/role — fields: user_id, role */
    public function changeRole(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;
        $users  = "/tenant/{$tenant['id']}/users";

        if (!$this->canManageUsers($user)) {
            return Response::redirect("/tenant/{$tenant['id']}/dashboard?error=forbidden");
        }

        $data = $this->body($request);
        if (!$this->validCsrf($data)) {
            return Response::redirect($users . '?error=invalid_request');
        }

        $role = (string) ($data['role'] ?? '');
        if (!in_array($role, self::ASSIGNABLE_ROLES, true)) {
            return Response::redirect($users . '?error=role_invalid');
        }

        $result = $this->userService->changeRole($this->targetTenantId($user, $tenant, $data), (int) ($data['user_id'] ?? 0), $role);

        if (!$result->success) {
            return Response::redirect($users . '?error=' . match ($result->errorType) {
                'not_found'      => 'user_not_found',
                'role_protected' => 'role_protected',
                default          => 'role_failed',
            });
        }

        return Response::redirect($users . '?success=role_changed');
    }

    // ----------------------------------------------------------------
    // Delete my own account (anyone)
    // ----------------------------------------------------------------

    /** POST /tenant/{tenant_id}/account/delete */
    public function deleteMyAccount(Request $request, array $params): mixed
    {
        $tenant  = $request->tenant;
        $user    = $request->user;
        $account = "/tenant/{$tenant['id']}/account";

        if (!$this->validCsrf($this->body($request))) {
            return Response::redirect($account . '?error=invalid_request');
        }

        // The owner's account only goes with the whole tenant: same path, same screens.
        if (($user['role'] ?? '') === 'owner') {
            return Response::redirect("/tenant/{$tenant['id']}/tenant-delete");
        }

        $result = $this->userService->deleteUser((int) $tenant['id'], (int) $user['id']);

        if (!$result->success) {
            return Response::redirect($account . '?error=delete_failed');
        }

        Session::destroy();
        return Response::redirect('/login');
    }

    // ----------------------------------------------------------------
    // Delete the tenant (owner)
    // ----------------------------------------------------------------

    /** GET /tenant/{tenant_id}/tenant-delete — the warning screen. */
    public function showTenantDelete(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;

        if (($user['role'] ?? '') !== 'owner') {
            return Response::redirect("/tenant/{$tenant['id']}/dashboard?error=forbidden");
        }

        return $this->view('settings/tenant-delete', [
            'title'   => 'Delete Organisation',
            'pageTag' => 'TenantDelete',
            'tenant'  => $tenant,
            'user'    => $user,
        ]);
    }

    /** POST /tenant/{tenant_id}/tenant-delete — field: tenant_name (must match exactly). */
    public function deleteTenant(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;
        $screen = "/tenant/{$tenant['id']}/tenant-delete";

        if (($user['role'] ?? '') !== 'owner') {
            return Response::redirect("/tenant/{$tenant['id']}/dashboard?error=forbidden");
        }

        $data = $this->body($request);
        if (!$this->validCsrf($data)) {
            return Response::redirect($screen . '?error=invalid_request');
        }

        // The server-side check that counts: the typed name must equal the tenant's name exactly.
        if (!hash_equals((string) $tenant['name'], (string) ($data['tenant_name'] ?? ''))) {
            return Response::redirect($screen . '?error=name_mismatch');
        }

        $result = $this->tenantService->deleteTenant($user, (int) $tenant['id']);

        if (!$result->success) {
            return Response::redirect($screen . '?error=delete_failed');
        }

        Session::destroy();
        return Response::redirect('/login');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function canManageUsers(array $user): bool
    {
        return in_array($user['role'] ?? '', ['owner', 'admin', 'platform'], true);
    }

    /**
     * The tenant the target user belongs to. An owner or admin only ever acts in
     * their own tenant. A platform user acts across all tenants, so the Users
     * screen posts the row's tenant id and that one is used.
     */
    private function targetTenantId(array $user, array $tenant, array $data): int
    {
        if (($user['role'] ?? '') === 'platform') {
            return (int) ($data['tenant_id'] ?? 0);
        }

        return (int) $tenant['id'];
    }

    private function body(Request $request): array
    {
        return $request->bodyParams ?? $request->post;
    }

    private function validCsrf(array $data): bool
    {
        Session::start();
        $stored = $_SESSION['csrf_token'] ?? '';

        return $stored !== '' && hash_equals($stored, (string) ($data['csrf_token'] ?? ''));
    }
}
