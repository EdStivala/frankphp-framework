<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

namespace Frank\Middleware;

use Frank\Core\MiddlewareInterface;
use Frank\Core\Request;
use Frank\Models\Tenant;
use Frank\Core\Response;

class TenantMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next)
    {
        $params = $request->routeParams;
		if (!isset($params['tenant_id'])) {
			// No tenant specifed so we fail the Validation and force logout
			header('location: /logout');
			return;
		} 
		// return $next($request);
        
        $tenantModel = new Tenant();
        $t = $tenantModel->findById((int)$params['tenant_id']);
        if (!$t) {
            http_response_code(404);
            echo 'Tenant not found';
            return;
        }
        $request->tenant = $t;
        return $next($request);
    }
}
