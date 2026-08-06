<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

namespace Frank\Middleware; 

use Frank\Core\MiddlewareInterface;
use Frank\Models\User;

class ApiAuthMiddleware implements MiddlewareInterface
{
	
	public function handle($request, callable $next)
	{
		$key = $request->header('X-Api-Key')
		?: $request->header('Authorization');
		
		if (!$key) {
			return $this->deny(401, 'Missing API Key');
		}
		
		// Allow "Bearer <key>" or raw key
		if (preg_match('/^Bearer\s+(.*)$/i', $key, $m)) {
			$key = $m[1];
		}
		
		$hash = hash('sha256', trim($key));
		
		// load user
		$userModel = new User();
		$user = $userModel->findApiKeyHash($hash);
		
		if (!$user) {
			// invalid session
			return $this->deny(403, 'Forbidden: user mismatch');
		}
		
		// Enforce Tenant from Route
		if (isset($request->routeParams['tenant_id'])) {
			if ((int)$request->routeParams['tenant_id'] !== (int)$user['tenant_id']) {
				return $this->deny(403, 'Forbidden: tenant mismatch');
			}
		}
		
		// Attach User (similar to AuthMiddleware)
		$request->user = $user;
		return $next($request);		
	}
	
	protected function deny(int $status, string $message)
	{
		http_response_code($status); 
		header('Content-Type: application/json'); 
		echo json_encode([
			'success' => false, 
			'error' => $message
		]);
		return;
	}
}
?>