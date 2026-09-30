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

    /**
     * Create a new tenant and return its id.
     *
     * Owns slug uniqueness: $baseSlug is suffixed -2, -3, ... until free.
     * The UNIQUE index on tenants.slug is the final guard — a concurrent
     * insert that wins the race triggers a retry with the next suffix.
     *
     * Safe to call inside a Database::transaction() — a duplicate-key
     * failure rolls back only the failed statement in InnoDB.
     *
     * Introduced in FrankPHP v2.1.0 (tenant-on-signup).
     *
     * @param string      $name          Display name (max 150 chars)
     * @param string      $baseSlug      Slug candidate, e.g. from TenantNameGenerator
     * @param string|null $companyEmail
     * @return int  New tenant id
     * @throws \RuntimeException when no unique slug could be claimed
     */
    public function create(string $name, string $baseSlug, ?string $companyEmail = null): int
    {
        $name     = mb_substr(trim($name), 0, 150);
        $baseSlug = TenantNameGenerator::slugify($baseSlug) ?: 'workspace';
        $now      = Clock::nowUtcString();

        $suffix = 1;
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $slug = $this->nextFreeSlug($baseSlug, $suffix);

            try {
                $id = $this->tenantModel->insertTenant($name, $slug, $companyEmail, $now);
                if ($id) {
                    return $id;
                }
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                // Lost a race for this slug — try the next suffix.
            }

            $suffix = $this->suffixOf($slug, $baseSlug) + 1;
        }

        throw new \RuntimeException('Unable to allocate a unique tenant slug for: ' . $baseSlug);
    }

    /**
     * First free slug at or after $suffix. Suffix 1 means the bare base slug.
     */
    private function nextFreeSlug(string $baseSlug, int $suffix): string
    {
        while (true) {
            $slug = $suffix <= 1 ? $baseSlug : "{$baseSlug}-{$suffix}";
            if (!$this->tenantModel->slugExists($slug)) {
                return $slug;
            }
            $suffix++;
        }
    }

    private function suffixOf(string $slug, string $baseSlug): int
    {
        return $slug === $baseSlug ? 1 : (int) substr($slug, strlen($baseSlug) + 1);
    }
}
