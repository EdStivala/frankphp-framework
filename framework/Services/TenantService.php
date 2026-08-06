<?php

declare(strict_types=1);

namespace Frank\Services;

use Frank\Core\Clock;
use Frank\Models\Tenant;

/**
 * TenantService
 *
 * Framework-owned service for business logic that concerns the tenant record
 * beyond raw DB access. Introduced in FrankPHP v1.3.2 as part of the strict
 * Model/Service separation refactor.
 *
 * Responsibility boundary:
 * - Owns the allowlist of writable tenant settings columns.
 * - Owns null-coercion rules for optional fields.
 * - Computes timestamps via Clock and passes them as strings to the model.
 * - The Tenant model receives a clean, validated column map and executes the
 *   query — it makes no business-logic decisions.
 *
 * Container registration: singleton in bootstrap.php.
 * Dependencies: Tenant model (instantiated internally — lightweight, no DI needed).
 */
class TenantService
{
    private Tenant $tenantModel;

    public function __construct(?Tenant $tenantModel = null)
    {
        $this->tenantModel = $tenantModel ?? new Tenant();
    }

    /**
     * Persist tenant settings columns.
     *
     * Owns the allowlist of writable columns — only those listed here can
     * be written via settings forms, regardless of what the caller passes.
     *
     * Owns the null-coercion rule: empty string values for optional fields
     * are set to null before persistence so that downstream resolution
     * (e.g. timezone fallback to config → UTC) can function correctly.
     *
     * Computes $updatedAt via Clock and passes it as a string to the model.
     * The model receives a clean, validated column map and executes the query.
     *
     * @param int   $tenantId
     * @param array $data  Raw form/request values
     * @return bool
     */
    public function saveSettings(int $tenantId, array $data): bool
    {
        $allowed = [
            'name',
            'company_email',
            'timezone',
            'date_format',
        ];

        $columns = array_intersect_key($data, array_flip($allowed));

        // Optional fields: empty string reverts to null so downstream resolution
        // (e.g. timezone chain: tenant → config → UTC) can fall through correctly.
        $nullableFields = [
            'company_email',
            'timezone',
            'date_format',
        ];

        foreach ($nullableFields as $field) {
            if (array_key_exists($field, $columns) && trim((string) $columns[$field]) === '') {
                $columns[$field] = null;
            }
        }

        return $this->tenantModel->saveSettings(
            $tenantId,
            $columns,
            Clock::nowUtcString()
        );
    }
}
