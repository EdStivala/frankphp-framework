<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Organization
 *
 * EXAMPLE MODEL — one part of the scaffold/examples/organization/ recipe,
 * demonstrating the BaseApiController / Syncfusion DataManager pattern.
 * Not a framework primitive. Safe to delete the whole organization/
 * folder if you don't need this example.
 *
 * DB access only, consistent with the framework's Model SoC contract —
 * no Clock calls. The only "decision" made here is a safe column
 * allowlist for sorting/filtering, to avoid trusting client input
 * directly in SQL.
 */
class Organization
{
    /** Columns callers may sort or filter on. */
    private const SORTABLE_COLUMNS = ['id', 'name', 'created_at'];

    /**
     * Returns [rows, totalCount] for a tenant-scoped, paged, sorted,
     * optionally filtered and searched list of organizations — the shape
     * a Syncfusion DataManager READ endpoint needs in a single query.
     *
     * @param array<int, array{field?: string, value?: mixed}> $filters
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    public static function findPagedByTenant(
        int $tenantId,
        int $skip,
        int $take,
        string $sortCol,
        string $sortDir,
        array $filters = [],
        ?string $search = null
    ): array {
        $pdo = Database::getPdo();

        $sortCol = in_array($sortCol, self::SORTABLE_COLUMNS, true) ? $sortCol : 'id';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        $where    = ['tenant_id = :tenant_id'];
        $bindings = ['tenant_id' => $tenantId];

        foreach ($filters as $index => $filter) {
            $field = $filter['field'] ?? '';
            if (!in_array($field, self::SORTABLE_COLUMNS, true)) {
                continue; // unknown/unsafe filter column — ignore rather than guess
            }
            $placeholder = "filter_{$index}";
            $where[]                = "{$field} = :{$placeholder}";
            $bindings[$placeholder] = $filter['value'] ?? null;
        }

        if ($search !== null && trim($search) !== '') {
            $where[]           = 'name LIKE :search';
            $bindings['search'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM organizations WHERE {$whereSql}");
        $countStmt->execute($bindings);
        $count = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT id, tenant_id, name, created_at, updated_at
             FROM organizations
             WHERE {$whereSql}
             ORDER BY {$sortCol} {$sortDir}
             LIMIT :take OFFSET :skip"
        );
        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('take', $take, PDO::PARAM_INT);
        $stmt->bindValue('skip', $skip, PDO::PARAM_INT);
        $stmt->execute();

        return [$stmt->fetchAll(), $count];
    }
}
