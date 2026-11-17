<?php
/**
 * app/bootstrap.php — application bootstrap.
 *
 * $container, $router, and $config are already in scope here, provided by
 * framework/bootstrap.php before this file is required. Register your own
 * App\* container bindings and routes below, then return $router.
 *
 * To replace a framework-registered route with your own, use
 * $router->override(...) instead of $router->add(...).
 */

// App-owned schema — for tables that extend the framework's Tenants/Users
// with application-specific data (see codebase.md §16.7, "Extending
// Framework-Owned Tables"). No-op until app/sql/schema.app.sql actually
// exists. Once you add one, change the sentinel table name below to a
// real table it creates.
$appSchemaPath = APP_BASE_DIR . '/app/sql/schema.app.sql';
if (file_exists($appSchemaPath)) {
    \Frank\Core\Database::runSchemaFileIfMissingTable($appSchemaPath, 'CHANGE_ME_sentinel_table');
}

// Starter dashboard — HomeController ships as app-owned demo content.
// Replace with application-specific content (see Views/home/dashboard.php).
// The root address: a logged-in person goes straight to their own dashboard
// (v3.0.0 — people stay logged in for 28 days); anyone else is sent to /login
// by the auth check.
$router->add('GET', '/',                              'App\\Controllers\\HomeController@root',      [$auth]);
$router->add('GET', '/tenant/{tenant_id}/dashboard',   'App\\Controllers\\HomeController@dashboard', [$tm, $auth]);

// Public legal pages — Terms & Conditions placeholder linked from the terms
// step of the sign-in screen (FrankPHP 3.0, only when signup.require_terms is on). Replace Views/auth/terms.php with your real terms.
$container->bind(\App\Controllers\LegalController::class, function ($c) use ($config) {
    return new \App\Controllers\LegalController($config);
});
$router->add('GET', '/terms', 'App\\Controllers\\LegalController@terms', []);

// Users screen — app-owned (moved from the framework in v3.0.0). Change who
// sees it and what it shows freely.
$container->singleton(\App\Services\UserManagementService::class, function ($c) {
    return new \App\Services\UserManagementService(new \Frank\Models\Tenant());
});
$container->bind(\App\Controllers\UserManagementController::class, function ($c) use ($config) {
    return new \App\Controllers\UserManagementController(
        $c->make(\App\Services\UserManagementService::class),
        $config,
    );
});
$router->add('GET', '/tenant/{tenant_id}/users', 'App\\Controllers\\UserManagementController@index', [$tm, $auth]);

// User and tenant administration — a worked example of the framework's user and
// tenant operations (delete my account, delete the tenant). App-owned: change the rules, screens and friction freely. See
// Controllers/UserAdminController.php.
$container->bind(\App\Controllers\UserAdminController::class, function ($c) {
    return new \App\Controllers\UserAdminController(
        $c->make(\Frank\Services\UserService::class),
        $c->make(\Frank\Services\TenantService::class),
    );
});
$router->add('GET',  '/tenant/{tenant_id}/users/edit',      'App\\Controllers\\UserAdminController@showUserEdit',     [$tm, $auth]);
$router->add('POST', '/tenant/{tenant_id}/users/save',      'App\\Controllers\\UserAdminController@saveUser',         [$tm, $auth, $json]);
$router->add('POST', '/tenant/{tenant_id}/users/delete',    'App\\Controllers\\UserAdminController@deleteUser',       [$tm, $auth, $json]);
$router->add('GET',  '/tenant/{tenant_id}/tenants/edit',    'App\\Controllers\\UserAdminController@showTenantEdit',   [$tm, $auth]);
$router->add('POST', '/tenant/{tenant_id}/tenants/save',    'App\\Controllers\\UserAdminController@saveTenant',       [$tm, $auth, $json]);
$router->add('POST', '/tenant/{tenant_id}/tenants/delete',  'App\\Controllers\\UserAdminController@deleteTenantAsPlatform', [$tm, $auth, $json]);
$router->add('POST', '/tenant/{tenant_id}/account/delete',  'App\\Controllers\\UserAdminController@deleteMyAccount',  [$tm, $auth, $json]);
$router->add('GET',  '/tenant/{tenant_id}/tenant-delete',   'App\\Controllers\\UserAdminController@showTenantDelete', [$tm, $auth]);
$router->add('POST', '/tenant/{tenant_id}/tenant-delete',   'App\\Controllers\\UserAdminController@deleteTenant',     [$tm, $auth, $json]);

// Example:
// $container->bind(\App\Controllers\InvoiceController::class, function ($c) {
//     return new \App\Controllers\InvoiceController();
// });
// $router->add('GET', '/tenant/{tenant_id}/invoices', 'App\\Controllers\\InvoiceController@index', [$tm, $auth]);

return $router;
