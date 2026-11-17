<?php

declare(strict_types=1);

namespace App\Controllers;

use Frank\Core\BaseController;
use Frank\Core\Request;
use Frank\Core\Response;
use Frank\Core\Session;
use Frank\Models\Tenant;
use Frank\Models\User;
use Frank\Services\InviteService;
use Frank\Services\TenantService;
use Frank\Services\UserService;

/**
 * UserAdminController (v3.0.0 template app — a worked EXAMPLE, app-owned)
 *
 * Shows how an app uses the framework's user and tenant operations:
 *   UserService::deleteUser(), TenantService::deleteTenant().
 * The framework enforces the data rules inside those operations (the owner is
 * never deleted by them, only the owner can delete the tenant).
 * Everything else here — who may call them, the confirmations, the warnings,
 * the redirects — is this app's decision. Change it freely.
 *
 * The example's rules:
 *   - user: delete their own account (Account Settings shows it to role user only)
 *   - owner: delete the tenant (warning screen, type the tenant's name, then delete)
 *   - owner / platform: send an invite from the Users screen (InviteService)
 *
 * Every POST here checks the CSRF token ($_SESSION['csrf_token'], hash_equals),
 * the same mechanism as the login forms.
 *
 * Routes (registered in app/bootstrap.php with [$tm, $auth, $json]):
 *   POST /tenant/{tenant_id}/users/invite
 *   POST /tenant/{tenant_id}/account/delete
 *   GET  /tenant/{tenant_id}/tenant-delete
 *   POST /tenant/{tenant_id}/tenant-delete
 */
class UserAdminController extends BaseController
{
    public function __construct(
        private UserService   $userService,
        private TenantService $tenantService,
        private InviteService $inviteService
    ) {
    }

    // ----------------------------------------------------------------
    // Invite (platform / owner) — the invite is saved and the invite email
    // sent (InviteService::createInvite). The link itself is handled by the
    // framework's AuthController (/invite/{tenant_id}/{token}).
    // ----------------------------------------------------------------

    /**
     * POST /tenant/{tenant_id}/users/invite — field: email
     *
     * The example's rules: an owner sends 'join_tenant' (the person joins the
     * owner's tenant as 'user'); a 'platform' user sends 'new_tenant' (the
     * person gets a new tenant as 'owner'). The invite always carries the
     * sender's tenant.
     */
    public function sendInvite(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;
        $users  = "/tenant/{$tenant['id']}/users";

        if (!$this->canEditUsers($user)) {
            return Response::redirect("/tenant/{$tenant['id']}/dashboard?error=forbidden");
        }

        $data = $this->body($request);
        if (!$this->validCsrf($data)) {
            return Response::redirect($users . '?error=invalid_request');
        }

        $kind   = ($user['role'] ?? '') === 'platform'
            ? InviteService::KIND_NEW_TENANT
            : InviteService::KIND_JOIN_TENANT;
        $result = $this->inviteService->createInvite(
            (string) ($data['email'] ?? ''),
            (int) $tenant['id'],
            $kind,
            (int) $user['id']
        );

        if (!$result->success) {
            $error = match ($result->errorType) {
                'validation'     => 'invalid_email',
                'account_exists' => 'account_exists',
                'invite_open'    => 'invite_open',
                'email_failed'   => 'invite_email_failed',
                default          => 'invite_failed',
            };
            return Response::redirect($users . '?error=' . $error);
        }

        return Response::redirect($users . '?success=invited');
    }

    // ----------------------------------------------------------------
    // Edit a user (platform / owner)
    // ----------------------------------------------------------------

    /** GET /tenant/{tenant_id}/users/edit?user_id=…&user_tenant_id=… */
    public function showUserEdit(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;

        if (!$this->canEditUsers($user)) {
            return Response::redirect("/tenant/{$tenant['id']}/dashboard?error=forbidden");
        }

        $targetTenantId = $this->targetTenantId($user, $tenant, (int) ($request->get['user_tenant_id'] ?? 0));

        $target = User::find((int) ($request->get['user_id'] ?? 0), $targetTenantId);
        if ($target === null) {
            return Response::redirect("/tenant/{$tenant['id']}/users");
        }

        return $this->view('users/user-edit', [
            'title'   => 'Edit User',
            'pageTag' => 'Users',
            'tenant'  => $tenant,
            'user'    => $user,
            'target'  => $target,
        ]);
    }

    /** POST /tenant/{tenant_id}/users/save — fields: user_id, user_tenant_id, name */
    public function saveUser(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;

        if (!$this->canEditUsers($user)) {
            return Response::redirect("/tenant/{$tenant['id']}/dashboard?error=forbidden");
        }

        $data     = $this->body($request);
        $userId   = (int) ($data['user_id'] ?? 0);
        $tenantId = $this->targetTenantId($user, $tenant, (int) ($data['user_tenant_id'] ?? 0));
        $edit     = "/tenant/{$tenant['id']}/users/edit?user_id={$userId}&user_tenant_id={$tenantId}";

        if (!$this->validCsrf($data)) {
            return Response::redirect($edit . '&error=invalid_request');
        }

        $ok = $this->userService->saveSettings($userId, $tenantId, ['name' => $data['name'] ?? '']);

        return Response::redirect($edit . ($ok ? '&success=1' : '&error=save_failed'));
    }

    /** POST /tenant/{tenant_id}/users/delete — fields: user_id, user_tenant_id */
    public function deleteUser(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;

        if (!$this->canEditUsers($user)) {
            return Response::redirect("/tenant/{$tenant['id']}/dashboard?error=forbidden");
        }

        $data     = $this->body($request);
        $userId   = (int) ($data['user_id'] ?? 0);
        $tenantId = $this->targetTenantId($user, $tenant, (int) ($data['user_tenant_id'] ?? 0));
        $edit     = "/tenant/{$tenant['id']}/users/edit?user_id={$userId}&user_tenant_id={$tenantId}";

        if (!$this->validCsrf($data)) {
            return Response::redirect($edit . '&error=invalid_request');
        }

        $result = $this->userService->deleteUser($tenantId, $userId);

        if (!$result->success) {
            return Response::redirect($edit . '&error=delete_failed');
        }

        return Response::redirect("/tenant/{$tenant['id']}/users");
    }

    // ----------------------------------------------------------------
    // Edit a tenant (platform)
    // ----------------------------------------------------------------

    /** GET /tenant/{tenant_id}/tenants/edit?edit_tenant_id=… — platform only */
    public function showTenantEdit(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;

        if (($user['role'] ?? '') !== 'platform') {
            return Response::redirect("/tenant/{$tenant['id']}/dashboard?error=forbidden");
        }

        $target = (new Tenant())->findById((int) ($request->get['edit_tenant_id'] ?? 0));
        if ($target === null) {
            return Response::redirect("/tenant/{$tenant['id']}/users");
        }

        return $this->view('users/tenant-edit', [
            'title'   => 'Edit Tenant',
            'pageTag' => 'Users',
            'tenant'  => $tenant,
            'user'    => $user,
            'target'  => $target,
        ]);
    }

    /** POST /tenant/{tenant_id}/tenants/save — fields: edit_tenant_id, name, company_email, timezone, date_format */
    public function saveTenant(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;

        if (($user['role'] ?? '') !== 'platform') {
            return Response::redirect("/tenant/{$tenant['id']}/dashboard?error=forbidden");
        }

        $data     = $this->body($request);
        $tenantId = (int) ($data['edit_tenant_id'] ?? 0);
        $edit     = "/tenant/{$tenant['id']}/tenants/edit?edit_tenant_id={$tenantId}";

        if (!$this->validCsrf($data)) {
            return Response::redirect($edit . '&error=invalid_request');
        }

        // TenantService::saveSettings() keeps only its allowed columns.
        $ok = $this->tenantService->saveSettings($tenantId, $data);

        return Response::redirect($edit . ($ok ? '&success=1' : '&error=save_failed'));
    }

    /** POST /tenant/{tenant_id}/tenants/delete — fields: edit_tenant_id, tenant_name (must match exactly) */
    public function deleteTenantAsPlatform(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;

        if (($user['role'] ?? '') !== 'platform') {
            return Response::redirect("/tenant/{$tenant['id']}/dashboard?error=forbidden");
        }

        $data     = $this->body($request);
        $tenantId = (int) ($data['edit_tenant_id'] ?? 0);
        $edit     = "/tenant/{$tenant['id']}/tenants/edit?edit_tenant_id={$tenantId}";

        if (!$this->validCsrf($data)) {
            return Response::redirect($edit . '&error=invalid_request');
        }

        // Same friction as the owner's flow: the typed name must equal the tenant's name exactly.
        $target = (new Tenant())->findById($tenantId);
        if ($target === null || !hash_equals((string) $target['name'], (string) ($data['tenant_name'] ?? ''))) {
            return Response::redirect($edit . '&error=name_mismatch');
        }

        $result = $this->tenantService->deleteTenant($user, $tenantId);

        if (!$result->success) {
            return Response::redirect($edit . '&error=delete_failed');
        }

        return Response::redirect("/tenant/{$tenant['id']}/users");
    }

    // ----------------------------------------------------------------
    // Delete my own account (role user only — the screen shows it to no other role)
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

    private function canEditUsers(array $user): bool
    {
        return in_array($user['role'] ?? '', ['platform', 'owner'], true);
    }

    /** Platform edits users in any tenant; an owner only in their own. */
    private function targetTenantId(array $user, array $tenant, int $requested): int
    {
        return ($user['role'] ?? '') === 'platform' ? $requested : (int) $tenant['id'];
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
