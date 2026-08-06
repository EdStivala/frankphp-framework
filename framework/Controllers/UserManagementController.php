<?php

declare(strict_types=1);

namespace Frank\Controllers;

use Frank\Core\BaseController;
use Frank\Core\Request;
use Frank\Core\Response;
use Frank\Services\UserManagementService;

/**
 * UserManagementController
 *
 * Framework controller introduced in v1.4.
 * Serves the admin/owner user management dashboard at
 * GET /tenant/{tenant_id}/users
 *
 * Access is restricted to users with role 'admin' or 'owner'.
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

        if (!in_array($user['role'] ?? '', ['admin', 'owner'], true)) {
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
            'totalUsers'         => $result->get('totalUsers', 0),
            'activePercent'      => $result->get('activePercent', 0),
            'neverLoggedIn'      => $result->get('neverLoggedIn', 0),
            'inactiveThirtyDays' => $result->get('inactiveThirtyDays', 0),
        ]);
    }
}
