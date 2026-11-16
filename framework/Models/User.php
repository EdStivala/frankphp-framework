<?php

declare(strict_types=1);

namespace Frank\Models;

use Frank\Core\BaseModel;

/**
 * User
 *
 * DB access layer for the users and login_codes tables. Framework-owned.
 *
 * SoC contract (enforced from FrankPHP v1.3.2):
 * - This model MUST NOT call Frank\Core\Clock.
 * - All timestamp values (now, expiresAt, cutoff, threshold) are computed
 *   by the calling Service and passed as plain string parameters.
 * - This model MUST NOT make business-logic decisions (rate-limit checks,
 *   hash verification, name derivation). Those belong in Services.
 * - Every public method does exactly one thing: execute a query and return
 *   a result.
 */
class User extends BaseModel
{
    protected string $table = 'users';

    // Core identity
    public ?int    $id            = null;
    public int     $tenant_id;
    public string  $name;
    public string  $email;
    public ?string $role          = 'user';
    public ?string $api_key_hash  = null;
    public ?string $created_at    = null; // UTC DATETIME
    public ?string $updated_at    = null; // UTC DATETIME
    public ?string $accessed_at   = null; // UTC DATETIME

    // User-level settings (NULL = inherit from tenant/app where applicable)
    public ?string $timezone      = null;

    // ----------------------------------------------------------------
    // User finders
    // ----------------------------------------------------------------

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :e');
        $stmt->execute(['e' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function findApiKeyHash(string $hash): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE api_key_hash = :hash LIMIT 1');
        $stmt->execute(['hash' => $hash]);
        return $stmt->fetch() ?: null;
    }

    // ----------------------------------------------------------------
    // User writes
    // ----------------------------------------------------------------

    /**
     * Record the UTC timestamp of a successful login.
     *
     * $accessedAt is computed by UserService::recordLogin() via Clock.
     * Tenant-scoped — can only update a user belonging to the given tenant.
     *
     * @param int    $userId
     * @param int    $tenantId
     * @param string $accessedAt  UTC DATETIME string from UserService
     */
    public function updateLastAccessed(int $userId, int $tenantId, string $accessedAt): bool
    {
        $stmt = $this->db->prepare('
            UPDATE users
               SET accessed_at = :accessed_at
             WHERE id = :id AND tenant_id = :tenant_id
        ');

        return $stmt->execute([
            'accessed_at' => $accessedAt,
            'id'          => $userId,
            'tenant_id'   => $tenantId,
        ]);
    }

    /**
     * Persist a pre-filtered, pre-validated set of user preference columns.
     *
     * The $columns array must already be filtered to the allowlist and have
     * nullable fields resolved by UserService::saveSettings() before this
     * method is called. This model applies no allowlist and makes no
     * business-logic decisions about the data.
     *
     * $updatedAt is a UTC DATETIME string computed by UserService via Clock.
     *
     * @param int    $userId
     * @param int    $tenantId
     * @param array  $columns   Validated column => value pairs, ready to persist
     * @param string $updatedAt UTC DATETIME string
     */
    public function saveSettings(int $userId, int $tenantId, array $columns, string $updatedAt): bool
    {
        if (empty($columns)) {
            return true;
        }

        $columns['updated_at'] = $updatedAt;

        $setClauses = implode(', ', array_map(
            static fn (string $col): string => "$col = :$col",
            array_keys($columns)
        ));

        $columns['id']        = $userId;
        $columns['tenant_id'] = $tenantId;

        $stmt = $this->db->prepare(
            "UPDATE users SET $setClauses WHERE id = :id AND tenant_id = :tenant_id"
        );

        return $stmt->execute($columns);
    }

    /**
     * Insert a new user row and return its id.
     *
     * $name and $createdAt are computed by LoginCodeService — name derivation
     * is business logic, timestamp generation belongs in the service.
     * users.email is globally unique (v3.0.0): inserting an address that
     * already exists throws PDOException (SQLSTATE 23000).
     *
     * @param int    $tenantId
     * @param string $email
     * @param string $name         Derived by LoginCodeService from email local-part
     * @param string $role
     * @param string $createdAt    UTC DATETIME string from LoginCodeService
     * @param string|null $termsAcceptedAt  v2.1.0 — UTC DATETIME of T&C acceptance
     * @param string|null $termsVersion     v2.1.0 — app's terms version string
     * @return int|null  New user id, or null on failure
     *
     * Migration safety (v2.1.0): the terms columns are only written when
     * $termsAcceptedAt is non-null, so installs that have not yet run
     * sql/migrations/v2.1.0_signup_terms.sql keep working while the
     * signup.require_terms flag is off. For the same reason the terms
     * columns are deliberately NOT public properties (BaseModel::save()
     * persists every public property).
     */
    public function insertUser(
        int    $tenantId,
        string $email,
        string $name,
        string $role,
        string $createdAt,
        ?string $termsAcceptedAt = null,
        ?string $termsVersion = null
    ): ?int {
        $params = [
            'tenant_id'  => $tenantId,
            'email'      => $email,
            'name'       => $name,
            'role'       => $role,
            'created_at' => $createdAt,
        ];

        if ($termsAcceptedAt !== null) {
            $params['terms_accepted_at'] = $termsAcceptedAt;
            $params['terms_version']     = $termsVersion;
        }

        $stmt = $this->db->prepare($this->buildInsert('users', array_keys($params)));
        $ok   = $stmt->execute($params);

        return $ok ? (int) $this->db->lastInsertId() : null;
    }

    /**
     * INSERT statement for a fixed, code-defined column list (never user input).
     *
     * @param string[] $columns
     */
    private function buildInsert(string $table, array $columns): string
    {
        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );
    }

    // ----------------------------------------------------------------
    // User management queries (v1.4)
    // ----------------------------------------------------------------

    /**
     * Return all users for a tenant ordered by creation date descending.
     *
     * Used by UserManagementService to build the admin user dashboard.
     * Returns only the columns needed for display — not api_key_hash.
     *
     * @param int $tenantId
     * @return array
     */
    public function allByTenant(int $tenantId): array
    {
        $stmt = $this->db->prepare('
            SELECT id, tenant_id, email, name, role, created_at, accessed_at
              FROM users
             WHERE tenant_id = :tenant_id
             ORDER BY created_at DESC
        ');
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }

    // ----------------------------------------------------------------
    // Login code methods (v3.0.0)
    // ----------------------------------------------------------------

    /**
     * Insert a new login code. $codeHash is password_hash() of the plain code;
     * $expiresAt and $createdAt are UTC strings computed by LoginCodeService.
     */
    public function insertLoginCode(
        string $email,
        string $codeHash,
        string $expiresAt,
        string $createdAt,
        string $ipAddress,
        string $userAgent
    ): bool {
        $stmt = $this->db->prepare('
            INSERT INTO login_codes
                (email, code_hash, expires_at, created_at, ip_address, user_agent)
            VALUES
                (:email, :code_hash, :expires_at, :created_at, :ip_address, :user_agent)
        ');

        return $stmt->execute([
            'email'      => $email,
            'code_hash'  => $codeHash,
            'expires_at' => $expiresAt,
            'created_at' => $createdAt,
            'ip_address' => substr($ipAddress, 0, 45),
            'user_agent' => substr($userAgent, 0, 512),
        ]);
    }

    /**
     * Cancel every unused login code for an email address.
     *
     * $usedAt is computed by LoginCodeService via Clock.
     */
    public function invalidatePendingLoginCodes(string $email, string $usedAt): bool
    {
        $stmt = $this->db->prepare('
            UPDATE login_codes
               SET used_at = :used_at
             WHERE email = :email
               AND used_at IS NULL
        ');

        return $stmt->execute([
            'used_at' => $usedAt,
            'email'   => $email,
        ]);
    }

    /**
     * Mark a single login code as used (consumed or cancelled).
     *
     * Only an unused code is updated, so the return value is a claim: true
     * for exactly one caller even if two requests race on the same code.
     *
     * $usedAt is computed by LoginCodeService via Clock.
     */
    public function markLoginCodeUsed(int $codeId, string $usedAt): bool
    {
        $stmt = $this->db->prepare('
            UPDATE login_codes
               SET used_at = :used_at
             WHERE id = :id
               AND used_at IS NULL
        ');
        $stmt->execute([
            'used_at' => $usedAt,
            'id'      => $codeId,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Add one guess to an unused code, but only while it has fewer than
     * $maxAttempts. Atomic, so parallel guesses cannot exceed the cap.
     *
     * Returns false when no guess was recorded (code used, cancelled or already
     * at the cap). The cap value is a business rule supplied by the service.
     */
    public function incrementLoginCodeAttempts(int $codeId, int $maxAttempts): bool
    {
        $stmt = $this->db->prepare('
            UPDATE login_codes
               SET attempts = attempts + 1
             WHERE id = :id
               AND used_at IS NULL
               AND attempts < :max_attempts
        ');
        $stmt->execute([
            'id'           => $codeId,
            'max_attempts' => $maxAttempts,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Return the most recent unexpired, unused login code for an email.
     *
     * Returns the raw row — hash verification (password_verify) is the
     * responsibility of LoginCodeService, not this model.
     *
     * $now is computed by LoginCodeService via Clock.
     */
    public function findMostRecentPendingLoginCode(string $email, string $now): ?array
    {
        $stmt = $this->db->prepare('
            SELECT *
              FROM login_codes
             WHERE email = :email
               AND expires_at > :now
               AND used_at IS NULL
             ORDER BY created_at DESC, id DESC
             LIMIT 1
        ');
        $stmt->execute([
            'email' => $email,
            'now'   => $now,
        ]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Count login codes requested from an IP address after $threshold.
     *
     * Returns a count — the rate-limit decision (>= maxRequests) is made
     * by LoginCodeService, not this model.
     *
     * $threshold is computed by LoginCodeService via Clock.
     */
    public function countRecentLoginCodesByIp(string $ipAddress, string $threshold): int
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) AS cnt
              FROM login_codes
             WHERE ip_address = :ip
               AND created_at > :threshold
        ');
        $stmt->execute([
            'ip'        => substr($ipAddress, 0, 45),
            'threshold' => $threshold,
        ]);

        $row = $stmt->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Delete login codes that expired before $cutoff.
     *
     * $cutoff is computed by LoginCodeService via Clock.
     *
     * @return int  Number of rows deleted
     */
    public function deleteExpiredLoginCodes(string $cutoff): int
    {
        $stmt = $this->db->prepare('
            DELETE FROM login_codes
             WHERE expires_at < :cutoff
        ');
        $stmt->execute(['cutoff' => $cutoff]);
        return $stmt->rowCount();
    }
}
