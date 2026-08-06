<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

namespace Frank\Core;

class Router
{
	private array $routes = [];

	// ★ Accept an optional container
	public function __construct(private ?Container $container = null)
	{
	}

	public function add(string $method, string $pattern, $handler, array $middleware = []): void
	{
		$method = strtoupper($method);

		if ($this->findRouteIndex($method, $pattern) !== null) {
			throw new \RuntimeException(
				"Router: a route is already registered for {$method} {$pattern}. " .
				"Use \$router->override() if you intend to deliberately replace it."
			);
		}

		$this->routes[$method][] = [
			'pattern'    => $pattern,
			'handler'    => $handler,
			'middleware' => $middleware,
		];
	}

	/**
	* Deliberately replace an already-registered route.
	*
	* Explicit, self-documenting alternative to add() for the one legitimate
	* case where a later registration should intentionally take over an
	* earlier one (e.g. an application overriding a framework-registered
	* route). Throws if no route is registered yet for (METHOD, pattern) —
	* override() replaces an existing route, it does not create a new one.
	*/
	public function override(string $method, string $pattern, $handler, array $middleware = []): void
	{
		$method = strtoupper($method);
		$index = $this->findRouteIndex($method, $pattern);

		if ($index === null) {
			throw new \RuntimeException(
				"Router: cannot override {$method} {$pattern} — no route is registered for it yet. " .
				"Use \$router->add() to register a new route."
			);
		}

		$this->routes[$method][$index] = [
			'pattern'    => $pattern,
			'handler'    => $handler,
			'middleware' => $middleware,
		];
	}

	/**
	* Finds the array index of a registered route matching (method, pattern),
	* or null if none is registered. Used by add() and override() to detect
	* collisions explicitly rather than silently allowing duplicates.
	*/
	private function findRouteIndex(string $method, string $pattern): ?int
	{
		foreach ($this->routes[$method] ?? [] as $index => $route) {
			if ($route['pattern'] === $pattern) {
				return $index;
			}
		}
		return null;
	}

	public function dispatch(Request $request)
	{
		$method = strtoupper($request->method);
		$path   = rtrim($request->path, '/') ?: '/';

		if (!isset($this->routes[$method])) {
			http_response_code(405);
			echo 'Method Not Allowed';
			return;
		}

		foreach ($this->routes[$method] as $route) {
			$regex = $this->patternToRegex($route['pattern']);
			if (preg_match($regex, $path, $matches)) {
				$params = [];
				foreach ($matches as $k => $v) {
					if (!is_int($k))
						$params[$k] = $v;
				}
				$request->routeParams    = $params;
				$controllerCallable      = $this->resolveHandler($route['handler']);
				$pipeline                = $this->buildPipeline($route['middleware'], $controllerCallable);
				return $pipeline($request);
			}
		}

		http_response_code(404);
		echo 'Not Found in Router Table';
	}

	private function resolveHandler($handler): callable
	{
		if (is_callable($handler)) {
			return fn($req) => $handler($req, $req->routeParams);
		}

		if (is_string($handler) && strpos($handler, '@') !== false) {
			[$controller, $method] = explode('@', $handler);

			if (strpos($controller, '\\') === false) {
				$controller = "App\\Controllers\\{$controller}";
			}

			return function (Request $request) use ($controller, $method) {
				// ★ Use the container if a binding exists for this controller;
				//   fall back to new $controller() for controllers with no
				//   constructor dependencies (the common case).
				if ($this->container?->has($controller)) {
					$instance = $this->container->make($controller);
				} else {
					$instance = new $controller();
				}
				return $instance->$method($request, $request->routeParams);
			};
		}

		throw new \RuntimeException('Invalid handler');
	}

	private function buildPipeline(array $middlewareClasses, callable $controllerCallable): callable
	{
		$next = fn(Request $request) => $controllerCallable($request);
		foreach (array_reverse($middlewareClasses) as $mw) {
			$next = $this->wrapMiddleware($mw, $next);
		}
		return fn(Request $request) => $next($request);
	}

	private function wrapMiddleware(string $mwClass, callable $next): callable
	{
		return function (Request $request) use ($mwClass, $next) {
			$candidate = $mwClass;
			if (strpos($candidate, '\\') === false) {
				$candidate = "App\\Middleware\\{$candidate}";
			}
			if (!class_exists($candidate)) {
				throw new \RuntimeException("Middleware {$candidate} not found");
			}
			$mw = new $candidate();
			if (!($mw instanceof MiddlewareInterface)) {
				throw new \RuntimeException("{$candidate} must implement MiddlewareInterface");
			}
			return $mw->handle($request, $next);
		};
	}

	private function patternToRegex(string $pattern): string
	{
		$regex = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $pattern);
		return '#^' . rtrim($regex, '/') . '/?$#';
	}
}