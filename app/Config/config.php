<?php

/**
 * Config/config.php — Typed configuration adapter
 *
 * This file does NOT contain credentials. It reads from environment
 * variables (loaded from .env by Env::load() in bootstrap.php) and
 * returns a structured array for use throughout the application.
 *
 * To add a new setting:
 *  1. Add the key to .env.example (with a placeholder value)
 *  2. Add it to your .env
 *  3. Reference it here via \Frank\Core\Env::get() or ::require()
 */

use Frank\Core\Env;

return [

    // ----------------------------------------------------------------
    // Application
    // ----------------------------------------------------------------
    'app' => [
        'name' => Env::get('APP_NAME', 'FrankPHP App'),
    ],

    'timezone' => Env::get('APP_TIMEZONE', 'UTC'),

    // ----------------------------------------------------------------
    // Database
    // ----------------------------------------------------------------
    'db' => [
        'host'     => Env::require('DB_HOST'),
        'database' => Env::require('DB_NAME'),
        'dsn'      => sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Env::require('DB_HOST'),
            Env::get('DB_PORT', '3306'),
            Env::require('DB_NAME')
        ),
        'user'     => Env::require('DB_USER'),
        'pass'     => Env::require('DB_PASS'),
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],

    // ----------------------------------------------------------------
    // Email (SMTP)
    // ----------------------------------------------------------------
    'mail' => [
        'host'         => Env::require('MAIL_HOST'),
        'port'         => (int) Env::get('MAIL_PORT', '587'),
        'username'     => Env::require('MAIL_USERNAME'),
        'password'     => Env::require('MAIL_PASSWORD'),
        'from_address' => Env::require('MAIL_FROM_ADDRESS'),
        'from_name'    => Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'FrankPHP App')),
        'reply_to'     => Env::get('MAIL_REPLY_TO', Env::require('MAIL_FROM_ADDRESS')),
        'admin_email'  => Env::require('MAIL_SITE_ADMIN'),

        // Optional per-app overrides for the framework's required email
        // templates (signup verification, password reset, platform-owner
        // alert). See codebase.md §16.8. Leave empty to use the framework
        // defaults — nothing here is required.
        'templates' => [
            // 'signup_verification'        => \App\Email\Templates\MySignupVerificationTemplate::class,
            // 'password_reset'              => \App\Email\Templates\MyPasswordResetTemplate::class,
            // 'platform_owner_signup_alert' => \App\Email\Templates\MyPlatformOwnerAlertTemplate::class,
        ],
    ],

    // ----------------------------------------------------------------
    // Signup — Terms & Conditions acceptance (FrankPHP 2.1+, see
    // codebase.md §16.9). Opt-in: omit this section (or set require_terms
    // to false) to leave the feature off.
    //
    // require_terms  true = the framework rejects signups that don't post
    //                terms_accepted=1, and records the acceptance time (UTC)
    //                and terms_version on the user. Requires the v2.1.0
    //                migration on existing installs
    //                (framework/sql/migrations/v2.1.0_signup_terms.sql).
    // terms_version  Your current terms version, stored with each acceptance.
    //                Change it when your terms change.
    // ----------------------------------------------------------------
    'signup' => [
        'require_terms' => true,
        'terms_version' => '2026-10',
    ],

    // ----------------------------------------------------------------
    // Cron
    // ----------------------------------------------------------------
    'cron' => [
        'secret' => Env::require('CRON_SECRET'),
    ],

    // ----------------------------------------------------------------
    // Frontend licence keys
    // Consumed in your layout view to build the APP_CONFIG JS block.
    // ----------------------------------------------------------------
    'frontend' => [
        'syncfusion_key' => Env::require('SYNCFUSION_LICENSE_KEY'),
    ],

];
