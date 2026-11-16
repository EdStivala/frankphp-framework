-- FrankPHP v3.0.0 migration — passwordless sign-in (emailed 6-digit code).
--
-- For EXISTING installs only. Fresh installs get this schema from
-- schema.core.sql on first boot; running this there is a harmless no-op.
--
-- STOP-ON-DUPLICATES: step 1 makes users.email globally unique (one email =
-- one user = one tenant). It FAILS if the same email address exists in more
-- than one tenant. That failure is intended: nothing after step 1 runs until
-- you resolve the duplicates by hand (delete or change one of the rows). List
-- them first with:
--
--   SELECT email, COUNT(*) AS users, GROUP_CONCAT(tenant_id ORDER BY tenant_id) AS tenant_ids
--     FROM users
--    GROUP BY email
--   HAVING COUNT(*) > 1;
--
-- Changes, in this order:
--   1. users.email                   UNIQUE KEY uq_users_email added
--   1b. users (tenant_id, email)     old v2 UNIQUE KEY uq_users_tenant_email dropped
--                                    (superseded; a migrated database must
--                                    match a fresh v3 install exactly)
--   2. login_codes                   table created
--   3. users.password_hash           column dropped
--   4. password_reset_tokens         table dropped
--   5. signup_tokens                 table dropped
--
-- Steps 3-5 are destructive and cannot be undone: after them nobody can sign
-- in with a password (passwords no longer exist in v3) and pending sign-up /
-- reset tokens are gone. Take a backup first.
--
-- Idempotent: each change is guarded (information_schema check, or
-- IF [NOT] EXISTS), so the script is safe to run more than once.
--
-- Run against the app database, e.g.:
--   mysql -u <user> -p <database> < framework/sql/migrations/v3.0.0_passwordless.sql
--
-- Order: deploy v3.0.0 code, run this script. The old sign-in screens and the
-- v2 code are not compatible with the migrated schema.

SET time_zone = '+00:00';

-- 1. users.email unique (fails here if duplicate emails exist — by design)
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)',
    'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'uq_users_email');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1b. old per-tenant unique key dropped (superseded by uq_users_email).
--     Runs only after step 1 succeeded, so email uniqueness is never left
--     unenforced. The tenant foreign key keeps its index via
--     idx_users_tenant_role / idx_users_tenant_name.
SET @sql = (SELECT IF(COUNT(*) > 0,
    'ALTER TABLE users DROP INDEX uq_users_tenant_email',
    'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'uq_users_tenant_email');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. login_codes
SET @sql = (SELECT IF(COUNT(*) = 0,
    'CREATE TABLE login_codes (
       id         INT AUTO_INCREMENT PRIMARY KEY,
       email      VARCHAR(255) NOT NULL,
       code_hash  VARCHAR(255) NOT NULL,
       attempts   INT NOT NULL DEFAULT 0,
       expires_at DATETIME NOT NULL,
       used_at    DATETIME NULL DEFAULT NULL,
       created_at DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
       ip_address VARCHAR(45) NULL DEFAULT NULL,
       user_agent VARCHAR(512) NULL DEFAULT NULL,
       INDEX idx_login_codes_email (email),
       INDEX idx_login_codes_expires_at (expires_at),
       INDEX idx_login_codes_ip_created (ip_address, created_at)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'DO 0')
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_codes');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. users.password_hash dropped
SET @sql = (SELECT IF(COUNT(*) = 1,
    'ALTER TABLE users DROP COLUMN password_hash',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_hash');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. password_reset_tokens dropped
DROP TABLE IF EXISTS password_reset_tokens;

-- 5. signup_tokens dropped
DROP TABLE IF EXISTS signup_tokens;
