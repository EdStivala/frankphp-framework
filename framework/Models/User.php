<?php

declare(strict_types=1);

namespace Frank\Models;

use Frank\Core\BaseModel;

/**
 * User
 *
 * DB access layer for the users, password_reset_tokens, and signup_tokens
 * tables. Framework-owned.
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
    public string  $password_hash;
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
     * Update the password hash for a user.
     *
     * $updatedAt is computed by PasswordResetService via Clock.
     *
     * @param int    $userId
     * @param string $passwordHash  bcrypt hash
     * @param string $updatedAt     UTC DATETIME string from PasswordResetService
     */
    public function updatePasswordHash(int $userId, string $passwordHash, string $updatedAt): bool
    {
        $stmt = $this->db->prepare('
            UPDATE users
               SET password_hash = :password_hash,
                   updated_at    = :updated_at
             WHERE id = :id
        ');

        return $stmt->execute([
            'password_hash' => $passwordHash,
            'updated_at'    => $updatedAt,
            'id'            => $userId,
        ]);
    }

    /**
     * Insert a new user row and return its id.
     *
     * $name and $createdAt are computed by SignupService — name derivation
     * is business logic, timestamp generation belongs in the service.
     *
     * @param int    $tenantId
     * @param string $email
     * @param string $name         Derived by SignupService from email local-part
     * @param string $passwordHash bcrypt hash
     * @param string $role
     * @param string $createdAt    UTC DATETIME string from SignupService
     * @return int|null  New user id, or null on failure
     */
    public function insertUser(
        int    $tenantId,
        string $email,
        string $name,
        string $passwordHash,
        string $role,
        string $createdAt
    ): ?int {
        $stmt = $this->db->prepare('
            INSERT INTO users
                (tenant_id, email, name, password_hash, role, created_at)
            VALUES
                (:tenant_id, :email, :name, :password_hash, :role, :created_at)
        ');

        $ok = $stmt->execute([
            'tenant_id'     => $tenantId,
            'email'         => $email,
            'name'          => $name,
            'password_hash' => $passwordHash,
            'role'          => $role,
            'created_at'    => $createdAt,
        ]);

        return $ok ? (int) $this->db->lastInsertId() : null;
    }

    // ----------------------------------------------------------------
    // User management queries (v1.4)
    // ----------------------------------------------------------------

    /**
     * Return all users for a tenant ordered by creation date descending.
     *
     * Used by UserManagementService to build the admin user dashboard.
     * Returns only the columns needed for display — not password_hash or api_key_hash.
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
    // Password reset token methods
    // ----------------------------------------------------------------

    /**
     * Insert a new password reset token.
     *
     * $expiresAt and $createdAt are computed by PasswordResetService via Clock.
     */
    public function insertPasswordResetToken(
        int    $userId,
        int    $tenantId,
        string $tokenHash,
        string $expiresAt,
        string $createdAt
    ): bool {
        $stmt = $this->db->prepare('
            INSERT INTO password_reset_tokens
                (user_id, tenant_id, token_hash, expires_at, created_at)
            VALUES
                (:user_id, :tenant_id, :token_hash, :expires_at, :created_at)
        ');

        return $stmt->execute([
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * Return all unexpired, unused password reset tokens with joined user data.
     *
     * $now is computed by PasswordResetService via Clock.
     *
     * @param string $now  UTC DATETIME string — tokens with expires_at > $now are returned
     */
    public function findValidPasswordResetTokens(string $now): array
    {
        $stmt = $this->db->prepare('
            SELECT prt.*, u.id AS user_id, u.email, u.tenant_id, u.name
              FROM password_reset_tokens prt
              JOIN users u ON prt.user_id = u.id
             WHERE prt.expires_at > :now
               AND prt.used_at IS NULL
        ');
        $stmt->execute(['now' => $now]);
        return $stmt->fetchAll();
    }

    /**
     * Mark a single password reset token as used.
     *
     * $usedAt is computed by PasswordResetService via Clock.
     */
    public function markPasswordResetTokenAsUsed(int $tokenId, string $usedAt): bool
    {
        $stmt = $this->db->prepare('
            UPDATE password_reset_tokens
               SET used_at = :used_at
             WHERE id = :id
        ');

        return $stmt->execute([
            'used_at' => $usedAt,
            'id'      => $tokenId,
        ]);
    }

    /**
     * Mark all unused password reset tokens for a user as used.
     *
     * $usedAt is computed by PasswordResetService via Clock.
     */
    public function invalidateAllUserPasswordResetTokens(int $userId, string $usedAt): bool
    {
        $stmt = $this->db->prepare('
            UPDATE password_reset_tokens
               SET used_at = :used_at
             WHERE user_id = :user_id
               AND used_at IS NULL
        ');

        return $stmt->execute([
            'used_at'  => $usedAt,
            'user_id'  => $userId,
        ]);
    }

    /**
     * Count password reset tokens created for a user after $threshold.
     *
     * $threshold is computed by PasswordResetService via Clock.
     *
     * @param int    $userId
     * @param string $threshold  UTC DATETIME string — only tokens created after this are counted
     */
    public function countRecentPasswordResetTokens(int $userId, string $threshold): int
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) AS count
              FROM password_reset_tokens
             WHERE user_id = :user_id
               AND created_at > :threshold
        ');
        $stmt->execute([
            'user_id'   => $userId,
            'threshold' => $threshold,
        ]);

        $result = $stmt->fetch();
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Delete expired password reset tokens older than $cutoff.
     *
     * $cutoff is computed by PasswordResetService via Clock.
     *
     * @param string $cutoff  UTC DATETIME string
     * @return int  Number of rows deleted
     */
    public function deleteExpiredPasswordResetTokens(string $cutoff): int
    {
        $stmt = $this->db->prepare('
            DELETE FROM password_reset_tokens
             WHERE expires_at < :cutoff
        ');
        $stmt->execute(['cutoff' => $cutoff]);
        return $stmt->rowCount();
    }

    // ----------------------------------------------------------------
    // Signup token methods
    // ----------------------------------------------------------------

    /**
     * Insert a new signup token.
     *
     * $expiresAt and $createdAt are computed by SignupService via Clock.
     */
    public function insertSignupToken(
        string $email,
        string $codeHash,
        string $passwordHash,
        string $expiresAt,
        string $createdAt,
        string $ipAddress,
        string $userAgent
    ): bool {
        $stmt = $this->db->prepare('
            INSERT INTO signup_tokens
                (email, code_hash, password_hash, expires_at, created_at, ip_address, user_agent)
            VALUES
                (:email, :code_hash, :password_hash, :expires_at, :created_at, :ip_address, :user_agent)
        ');

        return $stmt->execute([
            'email'         => $email,
            'code_hash'     => $codeHash,
            'password_hash' => $passwordHash,
            'expires_at'    => $expiresAt,
            'created_at'    => $createdAt,
            'ip_address'    => substr($ipAddress, 0, 45),
            'user_agent'    => substr($userAgent, 0, 512),
        ]);
    }

    /**
     * Mark all unused signup tokens for an email address as used.
     *
     * $usedAt is computed by SignupService via Clock.
     */
    public function invalidatePendingSignupTokens(string $email, string $usedAt): bool
    {
        $stmt = $this->db->prepare('
            UPDATE signup_tokens
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
     * Mark a single signup token as used.
     *
     * $usedAt is computed by SignupService via Clock.
     */
    public function markSignupTokenUsed(int $tokenId, string $usedAt): bool
    {
        $stmt = $this->db->prepare('
            UPDATE signup_tokens
               SET used_at = :used_at
             WHERE id = :id
        ');

        return $stmt->execute([
            'used_at' => $usedAt,
            'id'      => $tokenId,
        ]);
    }

    /**
     * Return unexpired, unused signup token candidate rows for an email.
     *
     * Returns raw rows — hash verification (password_verify) is the
     * responsibility of SignupService, not this model.
     *
     * $now is computed by SignupService via Clock.
     *
     * @param string $email
     * @param string $now   UTC DATETIME string
     * @return array  Up to 10 candidate rows ordered newest first
     */
    public function findPendingSignupTokens(string $email, string $now): array
    {
        $stmt = $this->db->prepare('
            SELECT *
              FROM signup_tokens
             WHERE email = :email
               AND expires_at > :now
               AND used_at IS NULL
             ORDER BY created_at DESC
             LIMIT 10
        ');
        $stmt->execute([
            'email' => $email,
            'now'   => $now,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Return the most recent unexpired, unused signup token for an email.
     *
     * $now is computed by SignupService via Clock.
     *
     * @param string $email
     * @param string $now   UTC DATETIME string
     */
    public function findMostRecentPendingSignupToken(string $email, string $now): ?array
    {
        $stmt = $this->db->prepare('
            SELECT *
              FROM signup_tokens
             WHERE email = :email
               AND expires_at > :now
               AND used_at IS NULL
             ORDER BY created_at DESC
             LIMIT 1
        ');
        $stmt->execute([
            'email' => $email,
            'now'   => $now,
        ]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Count signup token rows created from an IP address after $threshold.
     *
     * Returns a count — the rate-limit decision (>= maxAttempts) is made
     * by SignupService, not this model.
     *
     * $threshold is computed by SignupService via Clock.
     *
     * @param string $ipAddress
     * @param string $threshold  UTC DATETIME string
     * @return int
     */
    public function countRecentSignupTokensByIp(string $ipAddress, string $threshold): int
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) AS cnt
              FROM signup_tokens
             WHERE ip_address = :ip
               AND created_at > :threshold
        ');
        $stmt->execute([
            'ip'        => $ipAddress,
            'threshold' => $threshold,
        ]);

        $row = $stmt->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Delete expired signup tokens older than $cutoff.
     *
     * $cutoff is computed by SignupService via Clock.
     *
     * @param string $cutoff  UTC DATETIME string
     * @return int  Number of rows deleted
     */
    public function deleteExpiredSignupTokens(string $cutoff): int
    {
        $stmt = $this->db->prepare('
            DELETE FROM signup_tokens
             WHERE expires_at < :cutoff
        ');
        $stmt->execute(['cutoff' => $cutoff]);
        return $stmt->rowCount();
    }
}
