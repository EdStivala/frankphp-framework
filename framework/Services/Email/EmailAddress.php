<?php

declare(strict_types=1);

namespace Frank\Services\Email;

/**
 * EmailAddress (v3.0.0)
 *
 * The one place that defines how an email address is normalised and what
 * counts as a valid one. Framework-owned; app code may call it too.
 *
 * Used by sign-in (LoginCodeService), invites (InviteService), tenant naming
 * (TenantNameGenerator) and the send check (EmailMessage). users.email is
 * unique across the platform, so every check against it must normalise the
 * address the same way — always go through here, never inline.
 */
final class EmailAddress
{
    /** users.email is VARCHAR(255). */
    public const MAX_LENGTH = 255;

    /** Trim and lower-case. */
    public static function normalise(string $email): string
    {
        return strtolower(trim($email));
    }

    /** Valid format and no longer than MAX_LENGTH. Pass a normalised address. */
    public static function isValid(string $email): bool
    {
        return strlen($email) <= self::MAX_LENGTH
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
