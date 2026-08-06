<?php

declare(strict_types=1);

namespace Frank\Controllers;

use Frank\Core\BaseController;
use Frank\Core\Request;
use Frank\Core\Response;
use Frank\Services\UserService;

/**
 * AccountSettingsController
 *
 * Per-user account settings read/write. Any authenticated user may view
 * and edit their own account — no role restriction, unlike
 * TenantSettingsController.
 *
 * Delegates all writes to UserService — this controller does not decide
 * which columns are writable and does not touch the User model directly.
 * $request->user is the already-loaded user array (set by AuthMiddleware).
 *
 * Routes:
 *   GET  /tenant/{tenant_id}/account
 *   POST /tenant/{tenant_id}/account/save
 */
class AccountSettingsController extends BaseController
{
    public function __construct(private UserService $userService)
    {
    }

    // ----------------------------------------------------------------
    // GET – the user row already contains all settings columns
    // ----------------------------------------------------------------
    public function index(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;

        return $this->view('settings/account-settings', [
            'title'    => 'Account Settings',
            'tenant'   => $tenant,
            'user'     => $user,
            'settings' => $user, // the user row IS the settings record
            'pageTag'  => 'AccountSettings',
        ]);
    }

    // ----------------------------------------------------------------
    // POST – delegate to UserService, which owns the allowlist and
    // null-coercion rules and computes the UTC updated_at via Clock
    // ----------------------------------------------------------------
    public function save(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;

        $ok = $this->userService->saveSettings((int) $user['id'], (int) $tenant['id'], $request->post);

        return Response::redirect(
            "/tenant/{$tenant['id']}/account?" .
            ($ok ? 'success=1' : 'error=save_failed')
        );
    }
}
