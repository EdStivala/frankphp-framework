<?php

declare(strict_types=1);

namespace Frank\Core;

/**
 * Url — the site's own address (v3.0.0).
 *
 * The one place the framework decides whether the visit is https and builds
 * the site's base address. Used for absolute links (invite emails, the Google
 * return address) and by Session for the Secure cookie flag, so both always
 * agree. Replaces EmailService::getBaseUrl() (removed in v3.0.0).
 */
final class Url
{
    private function __construct()
    {
    }

    /**
     * True when the visit is https: the server says so, the port is 443, or a
     * proxy in front of PHP says so (X-Forwarded-Proto).
     */
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /** The site's base address, e.g. https://yoursite.com (no trailing slash). */
    public static function baseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return (self::isHttps() ? 'https' : 'http') . '://' . $host;
    }
}
