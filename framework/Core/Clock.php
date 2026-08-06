<?php

declare(strict_types=1);

namespace Frank\Core;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

/**
 * Framework date/time utility.
 *
 * FrankPHP stores persisted timestamps as UTC DATETIME strings.
 *
 * Clock owns:
 * - UTC timestamp generation
 * - UTC/local timezone conversion
 * - timezone validation
 * - timezone resolution from explicit user, tenant, and config inputs
 * - UTC query boundaries derived from local dates
 *
 * Clock must not read from $_SESSION, $_SERVER, Request, the database,
 * or any other implicit global runtime state. Callers pass explicit context.
 */
final class Clock
{
    public const UTC = 'UTC';

    /**
     * Standard storage format for persisted DATETIME values.
     *
     * MySQL DATETIME-compatible format. Values produced in this format by
     * this class are UTC unless explicitly documented otherwise.
     */
    public const STORAGE_FORMAT = 'Y-m-d H:i:s';

    /**
     * Conservative default display format.
     *
     * Services may pass a different format depending on the application,
     * user preference, or tenant preference.
     */
    public const DISPLAY_FORMAT = 'Y-m-d H:i';

    private function __construct()
    {
        // Static framework utility. Do not instantiate.
    }

    /**
     * Return the current instant in UTC.
     */
    public static function nowUtc(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::UTC));
    }

    /**
     * Return the current instant as a UTC DATETIME storage string.
     */
    public static function nowUtcString(): string
    {
        return self::nowUtc()->format(self::STORAGE_FORMAT);
    }

    /**
     * Return a UTC DateTimeImmutable offset from now.
     *
     * Examples:
     * - Clock::utcOffset('+1 hour')
     * - Clock::utcOffset('-30 days')
     */
    public static function utcOffset(string $modifier): DateTimeImmutable
    {
        $dateTime = self::nowUtc()->modify($modifier);

        if ($dateTime === false) {
            throw new InvalidArgumentException("Invalid date/time modifier: {$modifier}");
        }

        return $dateTime;
    }

    /**
     * Return a UTC DATETIME storage string offset from now.
     *
     * Examples:
     * - Clock::utcOffsetString('+1 hour')
     * - Clock::utcOffsetString('-30 days')
     */
    public static function utcOffsetString(string $modifier): string
    {
        return self::utcOffset($modifier)->format(self::STORAGE_FORMAT);
    }

    /**
     * Convert any DateTimeInterface value to UTC.
     */
    public static function toUtc(DateTimeInterface $dateTime): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone(new DateTimeZone(self::UTC));
    }

    /**
     * Convert any DateTimeInterface value to a UTC DATETIME storage string.
     */
    public static function toUtcString(DateTimeInterface $dateTime): string
    {
        return self::toUtc($dateTime)->format(self::STORAGE_FORMAT);
    }

    /**
     * Parse a UTC storage value into a DateTimeImmutable.
     *
     * Null, empty string, and whitespace-only values return null.
     */
    public static function parseUtc(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return new DateTimeImmutable(trim($value), new DateTimeZone(self::UTC));
    }

    /**
     * Return true if the supplied timezone is a valid PHP/IANA timezone name.
     */
    public static function isValidTimezone(?string $timezone): bool
    {
        if ($timezone === null || trim($timezone) === '') {
            return false;
        }

        try {
            new DateTimeZone(trim($timezone));
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Return a DateTimeZone object.
     *
     * Invalid, null, or empty values fall back to UTC.
     */
    public static function timezone(?string $timezone): DateTimeZone
    {
        $timezone = trim((string) $timezone);

        if ($timezone === '' || !self::isValidTimezone($timezone)) {
            return new DateTimeZone(self::UTC);
        }

        return new DateTimeZone($timezone);
    }

    /**
     * Resolve the effective timezone from explicit context.
     *
     * Resolution order:
     * 1. user timezone
     * 2. tenant timezone
     * 3. application config timezone
     * 4. UTC
     *
     * Supported shapes:
     *
     * User:
     * - ['timezone' => 'Europe/London']
     * - ['settings' => '{"timezone":"Europe/London"}']
     *
     * Tenant:
     * - ['timezone' => 'Europe/London']
     * - ['settings' => '{"timezone":"Europe/London"}']
     *
     * Config:
     * - ['timezone' => 'Europe/London']
     * - ['app' => ['timezone' => 'Europe/London']]
     */
    public static function resolveTimezone(
        ?array $user = null,
        ?array $tenant = null,
        ?array $config = null
    ): string {
        $candidates = [
            self::timezoneFromArray($user),
            self::timezoneFromArray($tenant),
            self::timezoneFromConfig($config),
            self::UTC,
        ];

        foreach ($candidates as $candidate) {
            if (self::isValidTimezone($candidate)) {
                return trim((string) $candidate);
            }
        }

        return self::UTC;
    }

    /**
     * Convert a local user/tenant/app input value into a UTC DateTimeImmutable.
     *
     * If $inputFormat is provided, it must be a PHP date format accepted by
     * DateTimeImmutable::createFromFormat().
     *
     * Example:
     * - Clock::localInputToUtc('2026-06-06 09:30', 'Europe/London')
     * - Clock::localInputToUtc('06/06/2026 09:30', 'Europe/London', 'd/m/Y H:i')
     */
    public static function localInputToUtc(
        string $localValue,
        string $timezone,
        ?string $inputFormat = null
    ): DateTimeImmutable {
        $localValue = trim($localValue);

        if ($localValue === '') {
            throw new InvalidArgumentException('Local date/time input cannot be empty.');
        }

        $localTimezone = self::timezone($timezone);

        if ($inputFormat !== null && trim($inputFormat) !== '') {
            $dateTime = DateTimeImmutable::createFromFormat(
                trim($inputFormat),
                $localValue,
                $localTimezone
            );

            if ($dateTime === false) {
                throw new InvalidArgumentException(
                    "Invalid local date/time input '{$localValue}' for format '{$inputFormat}'."
                );
            }

            return $dateTime->setTimezone(new DateTimeZone(self::UTC));
        }

        try {
            return (new DateTimeImmutable($localValue, $localTimezone))
                ->setTimezone(new DateTimeZone(self::UTC));
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                "Invalid local date/time input '{$localValue}'.",
                0,
                $exception
            );
        }
    }

    /**
     * Convert a local user/tenant/app input value into a UTC DATETIME storage string.
     */
    public static function localInputToUtcString(
        string $localValue,
        string $timezone,
        ?string $inputFormat = null
    ): string {
        return self::localInputToUtc($localValue, $timezone, $inputFormat)
            ->format(self::STORAGE_FORMAT);
    }

    /**
     * Convert a UTC storage value into a DateTimeImmutable in the supplied timezone.
     */
    public static function utcToTimezone(
        string $utcValue,
        string $timezone
    ): DateTimeImmutable {
        $utcValue = trim($utcValue);

        if ($utcValue === '') {
            throw new InvalidArgumentException('UTC date/time value cannot be empty.');
        }

        try {
            return (new DateTimeImmutable($utcValue, new DateTimeZone(self::UTC)))
                ->setTimezone(self::timezone($timezone));
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                "Invalid UTC date/time value '{$utcValue}'.",
                0,
                $exception
            );
        }
    }

    /**
     * Format a UTC storage value for display in a local timezone.
     *
     * Null, empty string, and whitespace-only values return an empty string.
     * Product-specific labels such as "Never", "Today", "Yesterday", or
     * "Recently active" belong in Services, not in Clock.
     */
    public static function formatUtcForTimezone(
        ?string $utcValue,
        string $timezone,
        string $format = self::DISPLAY_FORMAT
    ): string {
        if ($utcValue === null || trim($utcValue) === '') {
            return '';
        }

        return self::utcToTimezone($utcValue, $timezone)->format($format);
    }

    /**
     * Convert a local calendar date into the UTC boundary for local 00:00:00.
     *
     * Useful for queries where the user selected a local date.
     *
     * Example:
     * - user selected 2026-06-06 in Europe/London
     * - query should use the UTC instant corresponding to local start of day
     */
    public static function utcStartOfLocalDate(string $date, string $timezone): string
    {
        $localDate = self::normaliseDateOnly($date);

        return (new DateTimeImmutable($localDate . ' 00:00:00', self::timezone($timezone)))
            ->setTimezone(new DateTimeZone(self::UTC))
            ->format(self::STORAGE_FORMAT);
    }

    /**
     * Convert a local calendar date into the UTC boundary for local 23:59:59.
     *
     * Useful for inclusive end-of-day SQL filters.
     */
    public static function utcEndOfLocalDate(string $date, string $timezone): string
    {
        $localDate = self::normaliseDateOnly($date);

        return (new DateTimeImmutable($localDate . ' 23:59:59', self::timezone($timezone)))
            ->setTimezone(new DateTimeZone(self::UTC))
            ->format(self::STORAGE_FORMAT);
    }

    /**
     * Return a UTC [start, end] range for a local calendar date.
     *
     * Returns:
     * [
     *     'start' => 'YYYY-MM-DD HH:MM:SS',
     *     'end'   => 'YYYY-MM-DD HH:MM:SS',
     * ]
     */
    public static function utcRangeForLocalDate(string $date, string $timezone): array
    {
        return [
            'start' => self::utcStartOfLocalDate($date, $timezone),
            'end'   => self::utcEndOfLocalDate($date, $timezone),
        ];
    }

    /**
     * Return a UTC [start, end] range for a local date range.
     *
     * Start is local start-of-day for $startDate.
     * End is local end-of-day for $endDate.
     */
    public static function utcRangeForLocalDates(
        string $startDate,
        string $endDate,
        string $timezone
    ): array {
        return [
            'start' => self::utcStartOfLocalDate($startDate, $timezone),
            'end'   => self::utcEndOfLocalDate($endDate, $timezone),
        ];
    }

    /**
     * Return the local date, YYYY-MM-DD, for a UTC storage value.
     */
    public static function localDateFromUtc(string $utcValue, string $timezone): string
    {
        return self::utcToTimezone($utcValue, $timezone)->format('Y-m-d');
    }

    /**
     * Return the current local date, YYYY-MM-DD, in the supplied timezone.
     */
    public static function todayForTimezone(string $timezone): string
    {
        return self::nowUtc()
            ->setTimezone(self::timezone($timezone))
            ->format('Y-m-d');
    }

    /**
     * Return the current local date/time in the supplied timezone.
     *
     * This is for display/input defaults only. Do not persist this value.
     */
    public static function nowForTimezone(string $timezone): DateTimeImmutable
    {
        return self::nowUtc()->setTimezone(self::timezone($timezone));
    }

    /**
     * Return the current local date/time as a formatted string.
     *
     * This is for display/input defaults only. Do not persist this value.
     */
    public static function nowForTimezoneString(
        string $timezone,
        string $format = self::DISPLAY_FORMAT
    ): string {
        return self::nowForTimezone($timezone)->format($format);
    }

    /**
     * Extract a timezone candidate from an array.
     *
     * Supports direct `timezone` and JSON `settings.timezone`.
     */
    private static function timezoneFromArray(?array $source): ?string
    {
        if ($source === null) {
            return null;
        }

        if (isset($source['timezone']) && is_string($source['timezone'])) {
            $timezone = trim($source['timezone']);

            if ($timezone !== '') {
                return $timezone;
            }
        }

        if (isset($source['settings'])) {
            $settings = $source['settings'];

            if (is_string($settings) && trim($settings) !== '') {
                $decoded = json_decode($settings, true);

                if (
                    is_array($decoded)
                    && isset($decoded['timezone'])
                    && is_string($decoded['timezone'])
                    && trim($decoded['timezone']) !== ''
                ) {
                    return trim($decoded['timezone']);
                }
            }

            if (
                is_array($settings)
                && isset($settings['timezone'])
                && is_string($settings['timezone'])
                && trim($settings['timezone']) !== ''
            ) {
                return trim($settings['timezone']);
            }
        }

        return null;
    }

    /**
     * Extract a timezone candidate from config.
     *
     * Supports:
     * - $config['timezone']
     * - $config['app']['timezone']
     */
    private static function timezoneFromConfig(?array $config): ?string
    {
        if ($config === null) {
            return null;
        }

        if (isset($config['timezone']) && is_string($config['timezone'])) {
            $timezone = trim($config['timezone']);

            if ($timezone !== '') {
                return $timezone;
            }
        }

        if (
            isset($config['app'])
            && is_array($config['app'])
            && isset($config['app']['timezone'])
            && is_string($config['app']['timezone'])
        ) {
            $timezone = trim($config['app']['timezone']);

            if ($timezone !== '') {
                return $timezone;
            }
        }

        return null;
    }

    /**
     * Validate and normalise a date-only value.
     *
     * Accepts only YYYY-MM-DD.
     */
    private static function normaliseDateOnly(string $date): string
    {
        $date = trim($date);

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(self::UTC));

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException(
                "Invalid date '{$date}'. Expected format is YYYY-MM-DD."
            );
        }

        return $date;
    }
}
