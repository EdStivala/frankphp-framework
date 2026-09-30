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
$router->add('GET', '/',                              'App\\Controllers\\HomeController@dashboard', [$tm, $auth]);
$router->add('GET', '/tenant/{tenant_id}/dashboard',   'App\\Controllers\\HomeController@dashboard', [$tm, $auth]);

// Public legal pages — Terms & Conditions placeholder linked from the signup
// form (FrankPHP 2.1+). Replace Views/auth/terms.php with your real terms.
$container->bind(\App\Controllers\LegalController::class, function ($c) use ($config) {
    return new \App\Controllers\LegalController($config);
});
$router->add('GET', '/terms', 'App\\Controllers\\LegalController@terms', []);

// Example:
// $container->bind(\App\Controllers\InvoiceController::class, function ($c) {
//     return new \App\Controllers\InvoiceController();
// });
// $router->add('GET', '/tenant/{tenant_id}/invoices', 'App\\Controllers\\InvoiceController@index', [$tm, $auth]);

return $router;
