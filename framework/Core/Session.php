<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

namespace Frank\Core;

/**
 * Session — the one place a PHP session is started (v3.0.0).
 *
 * "Staying logged in" is 28 days, renewed while the person keeps using the
 * app (rolling). Clearing cookies logs them out.
 *
 * - Sessions are stored in APP_BASE_DIR/app/private/sessions, not PHP's shared
 *   default folder, so another site on the same PHP install cannot
 *   garbage-collect these sessions early with a shorter gc_maxlifetime.
 * - The cookie is HttpOnly, SameSite=Lax, and Secure when the request is HTTPS.
 * - For a logged-in session the cookie is re-issued with a fresh 28-day expiry
 *   at most once an hour, and a session whose last activity is older than
 *   28 days is destroyed.
 *
 * Session lifetimes are PHP runtime/cookie mechanics, not persisted
 * application timestamps, so this Core class uses time() directly. Nothing
 * here is written to the database.
 *
 * Always call Session::start(), never bare session_start().
 */
class Session
{
    public const LIFETIME_SECONDS = 2419200; // 28 days
    private const REFRESH_INTERVAL_SECONDS = 3600;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.gc_maxlifetime', (string) self::LIFETIME_SECONDS);
        self::useOwnSavePath();
        session_set_cookie_params(self::cookieParams(self::LIFETIME_SECONDS));
        session_start();

        if (empty($_SESSION['user_id'])) {
            return;
        }

        $now  = time();
        $last = (int) ($_SESSION['_last_activity'] ?? $now);

        if ($now - $last > self::LIFETIME_SECONDS) {
            self::destroy();
            session_id(session_create_id());
            session_start();
            return;
        }

        $_SESSION['_last_activity'] = $now;

        $refreshedAt = (int) ($_SESSION['_cookie_refreshed_at'] ?? 0);
        if ($now - $refreshedAt >= self::REFRESH_INTERVAL_SECONDS && !headers_sent()) {
            $params            = self::cookieParams(self::LIFETIME_SECONDS);
            $params['expires'] = $now + self::LIFETIME_SECONDS;
            unset($params['lifetime']);
            setcookie(session_name(), session_id(), $params);
            $_SESSION['_cookie_refreshed_at'] = $now;
        }
    }

    /**
     * End the current session completely (data, server file, browser cookie).
     */
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        $params   = session_get_cookie_params();
        if (!headers_sent()) {
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }
        session_destroy();
    }

    /**
     * The session's CSRF token, made once per session. The one place the
     * token is created; forms get it through Views/partials/csrf-field.php.
     */
    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * True when the posted 'csrf_token' in $data matches the session's CSRF
     * token. The one place the token is checked.
     */
    public static function csrfValid(array $data): bool
    {
        self::start();
        $stored = $_SESSION['csrf_token'] ?? '';
        return $stored !== '' && hash_equals($stored, (string) ($data['csrf_token'] ?? ''));
    }

    /**
     * True while a platform user is logged in as a tenant owner (support
     * access, v3.0.0). AuthController sets and clears session key 'support'.
     */
    public static function supportAccess(): bool
    {
        self::start();
        return !empty($_SESSION['support']);
    }

    /**
     * Cookie parameters for session_set_cookie_params(). start() reuses the
     * array for setcookie() by swapping 'lifetime' for an absolute 'expires'.
     */
    private static function cookieParams(int $lifetime): array
    {
        return [
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => Url::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private static function useOwnSavePath(): void
    {
        if (!defined('APP_BASE_DIR')) {
            return;
        }

        $dir = APP_BASE_DIR . '/app/private/sessions';

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            error_log('[Session] Could not create ' . $dir . ' — falling back to the PHP default session path.');
            return;
        }

        session_save_path($dir);
    }
}
