<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

namespace Frank\Core;

/**
 * Env — Zero-dependency .env file loader
 *
 * Parses a .env file and populates $_ENV, $_SERVER, and putenv().
 * Written for FrankPHP: no Composer, no external dependencies.
 *
 * Handles:
 *  - Full-line comments  (# comment)
 *  - Inline comments     (KEY=value # comment)
 *  - Quoted values       (KEY="hello world" or KEY='hello world')
 *  - Values containing = (KEY=base64==  — safe due to explode limit)
 *  - Server/Docker precedence: existing env vars are never overwritten,
 *    allowing production containers to inject real credentials while
 *    .env serves as a dev-only convenience file.
 *
 * Usage (in bootstrap.php, before anything else):
 *   \Frank\Core\Env::load(APP_BASE_DIR . '/app/.env');
 *
 * Retrieval:
 *   Env::get('DB_HOST');
 *   Env::get('DB_PORT', '3306');   // with default
 */
class Env
{
    /**
     * Load and parse the .env file at the given path.
     *
     * @throws \RuntimeException if the file does not exist
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException(
                ".env file not found at: {$path}\n" .
                "Copy .env.example to .env and fill in your values."
            );
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip full-line comments and lines without an = sign
            if (str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            // Split on the FIRST = only — protects base64 and DSN values
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip inline comments: KEY=value # this is a comment
            // Only strip if the # is preceded by a space, so URLs aren't broken
            if (str_contains($value, ' #')) {
                $value = trim(explode(' #', $value, 2)[0]);
            }

            // Strip matching surrounding quotes: "value" or 'value'
            if (preg_match('/^(["\'])(.*)\1$/s', $value, $matches)) {
                $value = $matches[2];
            }

            // Docker / server env vars take precedence — never overwrite them
            if (array_key_exists($key, $_ENV)) {
                continue;
            }

            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    /**
     * Retrieve an environment value by key, with an optional default.
     *
     * @param  string $key
     * @param  mixed  $default  Returned if the key is not set
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Retrieve a value and throw if it is missing or empty.
     * Use for credentials that have no sensible default.
     *
     * @throws \RuntimeException
     */
    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new \RuntimeException(
                "Required environment variable \"{$key}\" is not set. " .
                "Check your .env file against .env.example."
            );
        }
        return $value;
    }
}
