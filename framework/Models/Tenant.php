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
 *
 * Why: BaseModel is built for records that belong to a tenant. Its finders
 * find(), all() and where() always add "AND tenant_id = ...". The tenants
 * table has no tenant_id column, because a tenant is the thing other records
 * belong to, so those inherited methods would fail on it. Tenant therefore
 * has its own methods for each operation below.
 */
class Tenant
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getPdo();
    }

    /**
     * Load a tenant by id. Its own method because BaseModel::find() adds
     * "AND tenant_id = ..." and the tenants table has no tenant_id.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Load every tenant. Its own method because BaseModel::all() lists the
     * records of one tenant, and the tenants table has no tenant_id.
     */
    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM tenants ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function slugExists(string $slug): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM tenants WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Insert a new tenant row and return its id.
     *
     * Its own method: Tenant does not extend BaseModel (see the class comment),
     * so there is no inherited save(). BaseModel::save() would not suit this
     * anyway: it is INSERT ... ON DUPLICATE KEY UPDATE, so a slug that collides
     * would overwrite the existing tenant instead of failing, and
     * TenantService::create() relies on that failure to try the next slug.
     *
     * $slug must already be resolved to a unique value by TenantService.
     * A PDOException (SQLSTATE 23000) is thrown if it collides — the service
     * owns the retry decision.
     *
     * $createdAt is a UTC DATETIME string computed by TenantService via Clock.
     *
     * @return int|null  New tenant id, or null on failure
     */
    public function insertTenant(
        string  $name,
        string  $slug,
        ?string $companyEmail,
        string  $createdAt
    ): ?int {
        $stmt = $this->db->prepare('
            INSERT INTO tenants
                (name, slug, company_email, created_at)
            VALUES
                (:name, :slug, :company_email, :created_at)
        ');

        $ok = $stmt->execute([
            'name'          => $name,
            'slug'          => $slug,
            'company_email' => $companyEmail,
            'created_at'    => $createdAt,
        ]);

        return $ok ? (int) $this->db->lastInsertId() : null;
    }

    /**
     * Persist a pre-filtered, pre-validated set of tenant settings columns.
     *
     * Its own method: Tenant does not extend BaseModel (see the class comment),
     * so there is no inherited save().
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
     * Delete a tenant row. Its own method: Tenant does not extend BaseModel
     * (see the class comment), so there is no inherited delete().
     *
     * The foreign key fk_users_tenant (ON DELETE CASCADE)
     * removes every user in the tenant, so no separate user delete is made.
     * DB access only — who may delete a tenant is decided by
     * TenantService::deleteTenant().
     *
     * @return bool  True when exactly one tenant row was deleted
     */
    public function deleteTenant(int $tenantId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $tenantId]);

        return $stmt->rowCount() === 1;
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
