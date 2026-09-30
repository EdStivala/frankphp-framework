-- FrankPHP v2.1.0 migration — signup terms acceptance columns.
--
-- For EXISTING installs only. Fresh installs get these columns from
-- schema.core.sql on first boot; running this there is a harmless no-op.
--
-- Adds:
--   users.terms_accepted_at          DATETIME NULL
--   users.terms_version              VARCHAR(50) NULL
--   signup_tokens.terms_accepted_at  DATETIME NULL
--   signup_tokens.terms_version      VARCHAR(50) NULL
--
-- Idempotent: each ALTER is guarded by an information_schema check, so the
-- script is safe to run more than once. No data changes — existing users
-- keep NULL (acceptance not recorded). Do NOT backfill.
--
-- Order: deploy v2.1.0 code, run this script, THEN (optionally) enable
-- $config['signup']['require_terms'] in app/Config/config.php.
--
-- Run against the app database, e.g.:
--   mysql -u <user> -p <database> < framework/sql/migrations/v2.1.0_signup_terms.sql
--
-- See framework/Versions/FrankPHP_v2.1.0_migration_notes.md.

SET time_zone = '+00:00';

-- users.terms_accepted_at
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE users ADD COLUMN terms_accepted_at DATETIME NULL DEFAULT NULL AFTER accessed_at',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'terms_accepted_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users.terms_version
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE users ADD COLUMN terms_version VARCHAR(50) NULL DEFAULT NULL AFTER terms_accepted_at',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'terms_version');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- signup_tokens.terms_accepted_at
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE signup_tokens ADD COLUMN terms_accepted_at DATETIME NULL DEFAULT NULL AFTER user_agent',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'signup_tokens' AND COLUMN_NAME = 'terms_accepted_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- signup_tokens.terms_version
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE signup_tokens ADD COLUMN terms_version VARCHAR(50) NULL DEFAULT NULL AFTER terms_accepted_at',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'signup_tokens' AND COLUMN_NAME = 'terms_version');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
