<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserManagementService;
use Frank\Core\BaseController;
use Frank\Core\Request;
use Frank\Core\Response;

/**
 * UserManagementController
 *
 * App-owned (template app) — introduced in the framework in v1.4, moved to
 * the app in v3.0.0 so an app can change who sees the screen and what it shows.
 * Serves the user management dashboard at
 * GET /tenant/{tenant_id}/users
 *
 * Access is restricted to users with role 'platform' or 'owner'.
 * 'owner' sees their own tenant only; 'platform' sees all
 * tenants (v2.2.0). Any other role is redirected with ?error=forbidden.
 * All data preparation is delegated to UserManagementService.
 */
class UserManagementController extends BaseController
{
    public function __construct(
        private UserManagementService $userManagementService,
        private array $config
    ) {}

    public function index(Request $request, array $params): mixed
    {
        $user   = $request->user;
        $tenant = $request->tenant;

        if (!in_array($user['role'] ?? '', ['platform', 'owner'], true)) {
            return Response::redirect('/tenant/' . $tenant['id'] . '/dashboard?error=forbidden');
        }

        $result = $this->userManagementService->getUserDashboard(
            (int) $tenant['id'],
            $user,
            $tenant,
            $this->config
        );

        return $this->view('users/management-dashboard', [
            'title'              => 'Users',
            'pageTag'            => 'Users',
            'tenant'             => $tenant,
            'user'               => $user,
            'users'              => $result->get('users', []),
            'me'                 => $result->get('me'),
            'totalUsers'         => $result->get('totalUsers', 0),
            'activePercent'      => $result->get('activePercent', 0),
            'neverLoggedIn'      => $result->get('neverLoggedIn', 0),
            'inactiveThirtyDays' => $result->get('inactiveThirtyDays', 0),
            'crossTenant'        => $result->get('crossTenant', false),
        ]);
    }
}
