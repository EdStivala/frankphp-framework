<?php

declare(strict_types=1);

namespace Frank\Models;

use Frank\Core\Clock;
use Frank\Core\Database;
use PDO;

/**
 * Tenant
 *
 * DB access layer for the tenants table. Framework-owned.
 *
 * SoC contract (enforced from FrankPHP v1.3.2):
 * - This model MUST NOT call Frank\Core\Clock.
 * - All timestamp values are computed by the calling Service and passed
 *   as plain string parameters.
 * - Every public method does exactly one thing: execute a query and
 *   return a result.
 *
 * Note: Tenant does not extend BaseModel — it is a standalone PDO class.
 */
class Tenant
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getPdo();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM tenants ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    /**
     * Persist a pre-filtered, pre-validated set of tenant settings columns.
     *
     * The $columns array must already be filtered to the allowlist and have
     * nullable fields resolved by TenantService::saveSettings() before this
     * method is called. This model applies no allowlist and makes no
     * business-logic decisions about the data.
     *
     * $updatedAt is a UTC DATETIME string computed by TenantService via Clock.
     *
     * @param int    $tenantId
     * @param array  $columns   Validated column => value pairs, ready to persist
     * @param string $updatedAt UTC DATETIME string
     */
    public function saveSettings(int $tenantId, array $columns, string $updatedAt): bool
    {
        if (empty($columns)) {
            return true;
        }

        $columns['updated_at'] = $updatedAt;

        $setClauses = implode(', ', array_map(
            static fn (string $col): string => "$col = :$col",
            array_keys($columns)
        ));

        $columns['id'] = $tenantId;

        $stmt = $this->db->prepare("UPDATE tenants SET $setClauses WHERE id = :id");
        return $stmt->execute($columns);
    }

    /**
     * Backwards-compatible wrapper for legacy call sites.
     * Prefer Clock::resolveTimezone($user, $tenant, $config) in new code.
     */
    public static function effectiveTimezone(?string $userTz, ?string $tenantTz, ?array $config = null): string
    {
        return Clock::resolveTimezone(
            ['timezone' => $userTz],
            ['timezone' => $tenantTz],
            $config
        );
    }

    public static function effectiveDateFormat(?string $userFmt, ?string $tenantFmt, ?array $config = null): string
    {
        $format = $userFmt ?? $tenantFmt ?? ($config['date_format'] ?? null);
        return is_string($format) && trim($format) !== '' ? $format : 'DD/MM/YYYY';
    }
}
