<?php

namespace Frank\Core;

/**
* Container
*
* A minimal, explicit dependency injection container for FrankPHP.
*
* Design principles (consistent with the framework):
*   - No reflection, no magic, no autowiring
*   - Every binding is a plain PHP closure — readable, debuggable
*   - singleton() calls the factory once; subsequent calls return
*     the cached instance
*   - bind() calls the factory fresh on every make() call
*   - singleton() and bind() both throw if $id is already registered —
*     re-registering the same id is never a silent, invisible overwrite
*   - The container passes itself to each factory so services can
*     resolve their own dependencies without nesting in bootstrap.php
*
* Registering a singleton (the normal case for services):
*
*   $container->singleton(EmailService::class, function($c) use ($config) {
*       return EmailService::fromConfig($config['mail']);
*   });
*
* Registering a per-request factory (uncommon — prefer singletons):
*
*   $container->bind(SomeService::class, function($c) {
*       return new SomeService($c->make(EmailService::class));
*   });
*
* Deliberately replacing an already-registered binding — e.g. an
* application overriding a framework service's behaviour:
*
*   $container->override(UserService::class, function($c) {
*       return new CustomUserService($c->make(User::class));
*   });
*
* Resolving:
*
*   $service = $container->make(EmailService::class);
*/
class Container
{
/** @var array<string, callable> */
	private array $bindings = [];

/** @var array<string, mixed> */
	private array $instances = [];

	// ----------------------------------------------------------------
	// Registration
	// ----------------------------------------------------------------

/**
* Register a factory that is called once; the result is cached
* and returned on every subsequent make() call.
*
* Use this for stateless services (EmailService, PasswordResetService,
* etc.) — the default choice for almost everything.
*/
	public function singleton(string $id, callable $factory): void
	{
		if ($this->has($id)) {
			throw new \RuntimeException(
				"Container: a binding is already registered for \"{$id}\".\n" .
				"Use \$container->override() if you intend to deliberately replace it."
			);
		}

		$this->bindings[$id] = function (Container $c) use ($id, $factory) {
			if (!array_key_exists($id, $this->instances)) {
				$this->instances[$id] = $factory($c);
			}
			return $this->instances[$id];
		};
	}

/**
* Register a factory that is called fresh on every make() call.
*
* Use when the resolved object must NOT be shared across callers
* (e.g. a stateful per-request object). Rare in practice.
*/
	public function bind(string $id, callable $factory): void
	{
		if ($this->has($id)) {
			throw new \RuntimeException(
				"Container: a binding is already registered for \"{$id}\".\n" .
				"Use \$container->override() if you intend to deliberately replace it."
			);
		}

		$this->bindings[$id] = $factory;
	}

/**
* Deliberately replace an already-registered binding.
*
* Explicit, self-documenting alternative to singleton()/bind() for the one
* legitimate case where a later registration should intentionally take
* over an earlier one — e.g. an application replacing a framework
* service's behaviour behind an unmodified framework controller. Throws
* if no binding is registered yet for $id; override() replaces an
* existing binding, it does not create a new one.
*
* Must be called before any make() call for $id could possibly have
* happened — in practice this means from app/bootstrap.php, during the
* registration phase, before the router dispatches a request.
*
* Behaves like singleton() (factory runs once, cached) by default. Pass
* $shared = false for bind()-style fresh-per-call behaviour instead.
*/
	public function override(string $id, callable $factory, bool $shared = true): void
	{
		if (!$this->has($id)) {
			throw new \RuntimeException(
				"Container: cannot override \"{$id}\" — no binding is registered for it yet.\n" .
				"Use \$container->singleton() or ->bind() to register a new binding."
			);
		}

		unset($this->instances[$id]);

		if ($shared) {
			$this->bindings[$id] = function (Container $c) use ($id, $factory) {
				if (!array_key_exists($id, $this->instances)) {
					$this->instances[$id] = $factory($c);
				}
				return $this->instances[$id];
			};
		} else {
			$this->bindings[$id] = $factory;
		}
	}

	// ----------------------------------------------------------------
	// Resolution
	// ----------------------------------------------------------------

/**
* Resolve a binding by id (typically a fully-qualified class name).
*
* @throws \RuntimeException if no binding is registered for $id
*/
	public function make(string $id): mixed
	{
		if (!array_key_exists($id, $this->bindings)) {
			throw new \RuntimeException(
			"Container: no binding registered for \"{$id}\".\n" .
			"Register it in bootstrap.php with \$container->singleton() or ->bind()."
			);
		}

		return ($this->bindings[$id])($this);
	}

/**
* Check whether a binding is registered (used by Router).
*/
	public function has(string $id): bool
	{
		return array_key_exists($id, $this->bindings);
	}
}