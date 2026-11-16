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

    // Terms & Conditions acceptance (v2.1.0 columns; public properties from v3.0.0)
    public ?string $terms_accepted_at = null; // UTC DATETIME
    public ?string $terms_version     = null;

    // ----------------------------------------------------------------
    // User finders — lookups ACROSS ALL TENANTS
    //
    // These exist because the inherited BaseModel finders cannot do them:
    // find(id, tenantId), all(tenantId) and where(tenantId, ...) always add
    // "AND tenant_id = ...", so they only work once the tenant is known.
    // Each method below is used at a point where the tenant is NOT yet
    // known, and the lookup is what identifies the user (and so the tenant).
    // Use the inherited tenant-scoped finders everywhere else.
    // ----------------------------------------------------------------

    /**
     * Load a user by id alone. Used by AuthMiddleware on every request: the
     * session holds only user_id, so the user must be loaded before their
     * tenant is known (the middleware then checks the tenant in the address
     * matches theirs).
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Load a user by email alone. Used by LoginCodeService at sign-in, where
     * the email address is all that is known. users.email is unique across
     * all tenants (one email = one user), so this is also the check that an
     * address has no account before a new one is created.
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :e');
        $stmt->execute(['e' => $email]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Load a user by the hash of their API key. Used by ApiAuthMiddleware:
     * an API request carries only the key, so the key is what identifies the
     * user and their tenant.
     */
    public function findApiKeyHash(string $hash): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE api_key_hash = :hash LIMIT 1');
        $stmt->execute(['hash' => $hash]);
        return $stmt->fetch() ?: null;
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
