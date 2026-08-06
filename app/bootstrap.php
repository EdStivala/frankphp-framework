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

// Starter dashboard — HomeController ships as app-owned demo content.
// Replace with application-specific content (see Views/home/dashboard.php).
$router->add('GET', '/',                              'App\\Controllers\\HomeController@dashboard', [$tm, $auth]);
$router->add('GET', '/tenant/{tenant_id}/dashboard',   'App\\Controllers\\HomeController@dashboard', [$tm, $auth]);

// Example:
// $container->bind(\App\Controllers\InvoiceController::class, function ($c) {
//     return new \App\Controllers\InvoiceController();
// });
// $router->add('GET', '/tenant/{tenant_id}/invoices', 'App\\Controllers\\InvoiceController@index', [$tm, $auth]);

return $router;
