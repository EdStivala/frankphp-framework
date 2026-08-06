<?php
namespace Frank\Controllers;

use Frank\Core\BaseController;
use Frank\Core\Request;
use Frank\Core\Response;
use Frank\Services\TenantService;

/**
 * TenantSettingsController
 *
 * Delegates all writes to TenantService — this controller does not decide
 * which columns are writable and does not touch the Tenant model directly.
 * $request->tenant is the already-loaded tenant array (set by TenantMiddleware).
 *
 * Routes:
 *   GET  /tenant/{tenant_id}/tenant-settings
 *   POST /tenant/{tenant_id}/tenant-settings/save
 */
class TenantSettingsController extends BaseController
{
    public function __construct(private TenantService $tenantService)
    {
    }

    private function requireAdmin(array $user, array $tenant): mixed
    {
        if (!in_array($user['role'] ?? '', ['admin', 'owner'], true)) {
            return Response::redirect(
                "/tenant/{$tenant['id']}/tenant-settings?error=forbidden"
            );
        }
        return null;
    }

    // ----------------------------------------------------------------
    // GET – the tenant row already contains all settings columns
    // ----------------------------------------------------------------
    public function index(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;

        if ($redirect = $this->requireAdmin($user, $tenant)) {
            return $redirect;
        }

        return $this->view('settings/tenant-settings', [
            'title'    => 'Organisation Settings',
            'tenant'   => $tenant,
            'user'     => $user,
            'settings' => $tenant,  // the tenant row IS the settings record
            'pageTag'  => 'TenantSettings',
        ]);
    }

    // ----------------------------------------------------------------
    // POST – delegate to TenantService, which owns the allowlist and
    // null-coercion rules and computes the UTC updated_at via Clock
    // ----------------------------------------------------------------
    public function save(Request $request, array $params): mixed
    {
        $tenant = $request->tenant;
        $user   = $request->user;

        if ($redirect = $this->requireAdmin($user, $tenant)) {
            return $redirect;
        }

        $ok = $this->tenantService->saveSettings((int)$tenant['id'], $request->post);

        return Response::redirect(
            "/tenant/{$tenant['id']}/tenant-settings?" .
            ($ok ? 'success=1' : 'error=save_failed')
        );
    }
}
