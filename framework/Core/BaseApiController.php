<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

namespace Frank\Core;

/**
* BaseApiController
*
* Shared base for API controllers.
* Designed to match existing MVC + middleware architecture.
*
* Assumptions:
* - Tenant ID is present in $request->routeParams['tenant_id']
* - ApiAuthMiddleware sets $request->user
* - JsonBodyParserMiddleware may populate parsed body
*/
abstract class BaseApiController
{
/**
* Resolve tenant ID (authoritative)
*/
	protected function tenantId($request, array $args = []): int
	{
		if (!empty($args['tenant_id'])) {
			return (int)$args['tenant_id'];
		}

		if (isset($request->routeParams['tenant_id'])) {
			return (int)$request->routeParams['tenant_id'];
		}

		throw new \RuntimeException('Tenant not specified');
	}

/**
* Parse JSON request body safely
*/
	protected function body($request): array
	{
		// Preferred: JsonBodyParserMiddleware-parsed body
		if (!empty($request->bodyParams) && is_array($request->bodyParams)) {
			return $request->bodyParams;
		}

		// Fallback: PSR-style getParsedBody if present
		if (method_exists($request, 'getParsedBody')) {
			$body = $request->getParsedBody();
			if (is_array($body))
				return $body;
		}

		// Fallback: raw body (Request uses rawBody)
		$raw = $request->rawBody ?? ($request->body ?? null);
		if (!$raw && method_exists($request, 'getBody')) {
			$raw = (string)$request->getBody();
		}

		if ($raw) {
			$decoded = json_decode($raw, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				return $decoded;
			}
		}

		return [];
	}

/**
* Standard JSON response
*/
	protected function json($data, int $status = 200): void
	{
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);
	}

/**
* Standard API error response
*/
	protected function error(string $message, int $status = 400): void
	{
		$this->json([
			'success' => false,
			'error'   => $message
		], $status);
	}

/**
* Syncfusion DataManager response helper
*/
	protected function dmResponse(array $rows, int $count, int $status = 200): void
	{
		$this->json([
			'result' => $rows,
			'count'  => $count
		], $status);
	}

/**
* Get authenticated API user (set by ApiAuthMiddleware)
*/
	protected function user($request)
	{
		return $request->user ?? null;
	}

/**
* Enforce role(s)
*/
	protected function requireRole($request, $roles): bool
	{
		$user = $this->user($request);
		$roles = is_array($roles) ? $roles : [$roles];

		if (!$user || empty($user['role'])) {
			$this->error('Forbidden', 403);
			return false;
		}

		if (!in_array($user['role'], $roles, true)) {
			$this->error('Insufficient permissions', 403);
			return false;
		}

		return true;
	}

/**
* Parse Syncfusion DataManager parameters
*/
	protected function dmParams($request): array
	{
		$body = $this->body($request);

		$skip = (int)($body['skip'] ?? 0);
		$take = (int)($body['take'] ?? 1000);
		if ($take <= 0)
			$take = 1000;

		$sortCol = 'id';
		$sortDir = 'ASC';

		if (!empty($body['sorted'][0])) {
			$sortCol = $body['sorted'][0]['name'] ?? 'id';
			$sortDir = strtoupper($body['sorted'][0]['direction'] ?? 'ASC');
			$sortDir = $sortDir === 'DESC' ? 'DESC' : 'ASC';
		}

		return [
			'skip'    => $skip,
			'take'    => $take,
			'sortCol' => $sortCol,
			'sortDir' => $sortDir,
			'filters' => $body['where']  ?? [],
			'search'  => $body['search'] ?? null,
			'raw'     => $body
		];
	}
}
