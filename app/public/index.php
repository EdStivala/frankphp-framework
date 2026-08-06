<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
*/

/* Enable for debugging on Prod Server - REMOVE FOR FLIGHT! */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('APP_BASE_DIR', dirname(__DIR__, 2));
$router = require_once APP_BASE_DIR . '/framework/bootstrap.php';

use Frank\Core\Request;

// Start session for UI auth
if (session_status() === PHP_SESSION_NONE) session_start();

/** @var \Frank\Core\Router $router */
$request = new Request();
$router->dispatch($request);