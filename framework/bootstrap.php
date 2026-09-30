<?php
/**
 * The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
 * Copyright (c) 2026 Ed Stivala Limited
 * License: MIT
 */

/**
 * bootstrap.php — framework bootstrap (v2.0.0 architecture)
 *
 * This file is framework-owned. It never contains application routes or
 * application container bindings — those belong in app/bootstrap.php,
 * which this file hard-requires as its final step.
 *
 * Namespace contract: framework classes live under Frank\ (this file's own
 * directory tree); application classes live under App\ (APP_BASE_DIR/app).
 * Two independent, single-path PSR-4 mappings — see the autoloader below.
 * No searching, no fallback: a class's namespace alone determines where it
 * is found.
 */

// ----------------------------------------------------------------
// 1. Namespace-partitioned autoloader — must come first so Env and
//    Clock can resolve. Two single-path mappings, not a search: Frank\
//    always resolves against this file's own directory (the framework
//    itself); App\ always resolves against APP_BASE_DIR/app (the
//    consuming application, defined by its own public/index.php before
//    this file is required).
// ----------------------------------------------------------------
spl_autoload_register(function ($class) {
    $roots = [
        'Frank\\' => __DIR__,
        'App\\'   => APP_BASE_DIR . '/app',
    ];

    foreach ($roots as $prefix => $root) {
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file     = $root . '/' . str_replace('\\', '/', $relative) . '.php';

        if (file_exists($file)) {
            require $file;
        }

        return;
    }
});

// ----------------------------------------------------------------
// 2. Load .env — must happen before config.php is required
// ----------------------------------------------------------------
\Frank\Core\Env::load(APP_BASE_DIR . '/app/.env');

// ----------------------------------------------------------------
// 3. Config, timezone, DB
// ----------------------------------------------------------------
$config = require APP_BASE_DIR . '/app/Config/config.php';

// Runtime default is only a safety fallback for PHP functions. Persisted
// timestamps must still be generated through Frank\Core\Clock as UTC.
date_default_timezone_set(\Frank\Core\Clock::resolveTimezone(null, null, $config));

\Frank\Core\Database::connect($config['db']);

// ----------------------------------------------------------------
// 4. View resolution root. Core\BaseController::view() always resolves
//    against APP_VIEWS_DIR — every project's Views/ is seeded once from
//    this framework's own default Views/ at install time (by the
//    install tooling, not at request time) and is then entirely the
//    app's own; the framework never reads views live from its own copy.
//    Declared once, here, for both bootstrap files to share.
// ----------------------------------------------------------------
define('APP_VIEWS_DIR', APP_BASE_DIR . '/app/Views');

// Framework version, read once from framework/VERSION. Available to any
// view as the FRANK_VERSION constant — e.g. app-nav.php's sidebar footer.
define('FRANK_VERSION', trim(file_get_contents(__DIR__ . '/VERSION')));

// ----------------------------------------------------------------
// 5. Container — built after config so closures can capture $config
// ----------------------------------------------------------------
$container = new \Frank\Core\Container();

// EmailService — reads SMTP credentials from config, shared instance
$container->singleton(
    \Frank\Services\Email\EmailService::class,
    function ($c) use ($config) {
        return \Frank\Services\Email\EmailService::fromConfig($config['mail']);
    }
);

// PasswordResetService — depends on EmailService
$container->singleton(
    \Frank\Services\PasswordResetService::class,
    function ($c) {
        return new \Frank\Services\PasswordResetService(
            userModel:    new \Frank\Models\User(),
            emailService: $c->make(\Frank\Services\Email\EmailService::class),
        );
    }
);

// SignupTenantResolver — v2.1.0 policy point: which tenant + role a new
// signup gets. Default creates a new tenant with the user as owner. Apps
// replace it via $container->override() in app/bootstrap.php.
$container->singleton(
    \Frank\Services\SignupTenantResolver::class,
    function ($c) {
        return new \Frank\Services\CreateTenantForSignup(
            $c->make(\Frank\Services\TenantService::class),
        );
    }
);

// SignupService — depends on User model, EmailService and SignupTenantResolver.
// v1.3.2: accepts User model (not raw PDO) — all DB access via model methods.
// v2.1.0: opt-in terms acceptance from $config['signup'] (missing = off, so
// upgraded apps without the config section behave exactly like v2.0). When
// the flag is on, verify once — lazily, only on signup routes — that the
// v2.1.0 migration has been applied, and fail with a clear message if not.
$container->singleton(
    \Frank\Services\SignupService::class,
    function ($c) use ($config) {
        $signup       = $config['signup'] ?? [];
        $requireTerms = (bool) ($signup['require_terms'] ?? false);

        if ($requireTerms) {
            $check = \Frank\Core\Database::getPdo()->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME IN ('users', 'signup_tokens')
                    AND COLUMN_NAME IN ('terms_accepted_at', 'terms_version')"
            );
            if ((int) $check->fetchColumn() < 4) {
                throw new \LogicException(
                    'signup.require_terms is enabled but the v2.1.0 migration has not been applied — ' .
                    'run framework/sql/migrations/v2.1.0_signup_terms.sql against the app database.'
                );
            }
        }

        return new \Frank\Services\SignupService(
            userModel:      new \Frank\Models\User(),
            emailService:   $c->make(\Frank\Services\Email\EmailService::class),
            tenantResolver: $c->make(\Frank\Services\SignupTenantResolver::class),
            requireTerms:   $requireTerms,
            termsVersion:   isset($signup['terms_version']) ? (string) $signup['terms_version'] : null,
        );
    }
);

// UserService — framework singleton introduced in v1.3.2.
// Owns business logic for the user record: recordLogin, saveSettings.
$container->singleton(
    \Frank\Services\UserService::class,
    function ($c) {
        return new \Frank\Services\UserService(
            userModel: new \Frank\Models\User(),
        );
    }
);

// TenantService — framework singleton introduced in v1.3.2.
// Owns business logic for the tenant record: saveSettings allowlist and
// null-coercion rules that previously lived incorrectly in Tenant model.
$container->singleton(
    \Frank\Services\TenantService::class,
    function ($c) {
        return new \Frank\Services\TenantService(
            tenantModel: new \Frank\Models\Tenant(),
        );
    }
);

// AuthController — needs PasswordResetService and UserService injected.
// v1.3.2: UserService added to injection (recordLogin moved out of controller).
$container->bind(
    \Frank\Controllers\AuthController::class,
    function ($c) {
        return new \Frank\Controllers\AuthController(
            $c->make(\Frank\Services\PasswordResetService::class),
            $c->make(\Frank\Services\UserService::class),
        );
    }
);

// SignupController — needs SignupService and UserService injected.
// v2.1.0: UserService added (auto-login after signup calls recordLogin).
$container->bind(
    \Frank\Controllers\SignupController::class,
    function ($c) {
        return new \Frank\Controllers\SignupController(
            $c->make(\Frank\Services\SignupService::class),
            $c->make(\Frank\Services\UserService::class),
        );
    }
);

// TenantSettingsController — needs TenantService injected
$container->bind(
    \Frank\Controllers\TenantSettingsController::class,
    function ($c) {
        return new \Frank\Controllers\TenantSettingsController(
            $c->make(\Frank\Services\TenantService::class)
        );
    }
);

// AccountSettingsController — needs UserService injected
$container->bind(
    \Frank\Controllers\AccountSettingsController::class,
    function ($c) {
        return new \Frank\Controllers\AccountSettingsController(
            $c->make(\Frank\Services\UserService::class)
        );
    }
);

// UserManagementService — v1.4 framework service for the admin user dashboard
$container->singleton(
    \Frank\Services\UserManagementService::class,
    function ($c) {
        return new \Frank\Services\UserManagementService(
            userModel: new \Frank\Models\User(),
        );
    }
);

// UserManagementController — injects service and config
$container->bind(
    \Frank\Controllers\UserManagementController::class,
    function ($c) use ($config) {
        return new \Frank\Controllers\UserManagementController(
            $c->make(\Frank\Services\UserManagementService::class),
            $config,
        );
    }
);

// ----------------------------------------------------------------
// 6. Router — receives the container
// ----------------------------------------------------------------
$router = new \Frank\Core\Router($container);

$tm      = 'Frank\\Middleware\\TenantMiddleware';
$auth    = 'Frank\\Middleware\\AuthMiddleware';
$apiAuth = 'Frank\\Middleware\\ApiAuthMiddleware';
$json    = 'Frank\\Middleware\\JsonBodyParserMiddleware';

// Public
$router->add('GET',  '/login',  'Frank\\Controllers\\AuthController@showLogin', []);
$router->add('POST', '/login',  'Frank\\Controllers\\AuthController@login',     [$json]);
$router->add('GET',  '/logout', 'Frank\\Controllers\\AuthController@logout',    []);

// Password reset
$router->add('GET',  '/forgot-password', 'Frank\\Controllers\\AuthController@showForgotPassword', []);
$router->add('POST', '/forgot-password', 'Frank\\Controllers\\AuthController@sendPasswordReset',  []);
$router->add('GET',  '/reset-password',  'Frank\\Controllers\\AuthController@showResetPassword',  []);
$router->add('POST', '/reset-password',  'Frank\\Controllers\\AuthController@resetPassword',      []);

// Signup
$router->add('GET',  '/signup',         'Frank\\Controllers\\SignupController@showSignup',     []);
$router->add('POST', '/signup',         'Frank\\Controllers\\SignupController@initiateSignup', [$json]);
$router->add('GET',  '/signup/verify',  'Frank\\Controllers\\SignupController@showVerify',     []);
$router->add('POST', '/signup/verify',  'Frank\\Controllers\\SignupController@completeSignup', [$json]);
$router->add('POST', '/signup/resend',  'Frank\\Controllers\\SignupController@resendCode',     [$json]);

// Tenant-scoped UI
$router->add('GET', '/tenant/{tenant_id}/users', 'Frank\\Controllers\\UserManagementController@index', [$tm, $auth]);

// Tenant settings (admin/owner only — enforced in the controller, not middleware)
$router->add('GET',  '/tenant/{tenant_id}/tenant-settings',      'Frank\\Controllers\\TenantSettingsController@index', [$tm, $auth]);
$router->add('POST', '/tenant/{tenant_id}/tenant-settings/save', 'Frank\\Controllers\\TenantSettingsController@save',  [$tm, $auth, $json]);

// Account settings (any authenticated user, editing their own account)
$router->add('GET',  '/tenant/{tenant_id}/account',      'Frank\\Controllers\\AccountSettingsController@index', [$tm, $auth]);
$router->add('POST', '/tenant/{tenant_id}/account/save', 'Frank\\Controllers\\AccountSettingsController@save',  [$tm, $auth, $json]);

// ----------------------------------------------------------------
// 7. Hand off to the application. $container, $router, and $config are
//    already in scope for app/bootstrap.php to use — it registers its
//    own App\* container bindings and routes (via $router->add(), or
//    $router->override() to deliberately replace a route registered
//    above) and returns $router. A missing app/bootstrap.php is a
//    fatal boot error by design — there is no shared file left for an
//    application route to accidentally land in the wrong half of.
// ----------------------------------------------------------------
return require APP_BASE_DIR . '/app/bootstrap.php';
