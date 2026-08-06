<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

namespace Frank\Middleware;

use Frank\Core\MiddlewareInterface;
use Frank\Core\Request;
use Frank\Core\Response;
use Frank\Models\User;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next)
    {
        // simple session-based auth
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id'])) {
            // redirect to login (keep intended path)
            $intended = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            Response::redirect('/login?next=' . $intended);
        }
        // load user
        $userModel = new User();
        $user = $userModel->findById((int)$_SESSION['user_id']);
        if (!$user) {
            // invalid session
            unset($_SESSION['user_id']);
            Response::redirect('/login');
        }
        // ensure tenant match if route has tenant
        if (isset($request->routeParams['tenant_id']) && (int)$request->routeParams['tenant_id'] !== (int)$user['tenant_id']) {
            echo 'Forbidden: user not in tenant';
            http_response_code(403);
            return;
        }
        $request->user = $user;
        return $next($request);
    }
}
