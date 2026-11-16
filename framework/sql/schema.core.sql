-- FrankPHP schema.core.sql — framework-owned tables only.
--
-- Run once, automatically, on first boot by Core\Database::connect() via
-- Database::runSchemaFileIfMissingTable(), guarded on the 'users' table.
-- A fresh install starts empty (v3.0.0): no seeded tenant, no seeded users.
-- The first person to verify an emailed code creates the first tenant.
-- Not an incremental migration system — see codebase.md for the policy on
-- evolving an already-created schema (framework tables are immutable from
-- the app's perspective; extend via an app-owned joined table instead).
--
-- Date/time standard:
--   UTC at rest. Local at the edges.
--
-- All persisted application timestamps use DATETIME and are treated as UTC.
-- Runtime code should write explicit UTC values using Frank\Core\Clock.
-- DEFAULT (UTC_TIMESTAMP()) is retained as a safety fallback for manual SQL
-- and first-boot schema creation.
--
-- Do not use MySQL TIMESTAMP for FrankPHP application timestamps unless a
-- deliberate deviation is documented. TIMESTAMP performs implicit timezone
-- conversion through the MySQL session timezone; DATETIME stores the exact
-- UTC value supplied by the application.

CREATE TABLE IF NOT EXISTS tenants (
  id                  INT PRIMARY KEY AUTO_INCREMENT,
  name                VARCHAR(150) NOT NULL,
  slug                VARCHAR(150) NOT NULL UNIQUE,
  company_email       VARCHAR(255) NULL,
  timezone            VARCHAR(100) NULL,
  date_format         VARCHAR(50) NULL,
  created_at          DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
  updated_at          DATETIME NULL DEFAULT NULL,
  INDEX idx_tenants_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id                    INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id             INT NOT NULL,
  email                 VARCHAR(255) NOT NULL,
  name                  VARCHAR(255) NOT NULL,
  role                  VARCHAR(50) NOT NULL DEFAULT 'user',
  api_key_hash          CHAR(64) NULL,
  timezone              VARCHAR(100) NULL,
  created_at            DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
  updated_at            DATETIME NULL DEFAULT NULL,
  accessed_at           DATETIME NULL DEFAULT NULL,
  terms_accepted_at     DATETIME NULL DEFAULT NULL,
  terms_version         VARCHAR(50) NULL DEFAULT NULL,

  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_api_key_hash (api_key_hash),
  INDEX idx_users_tenant_role (tenant_id, role),
  INDEX idx_users_tenant_name (tenant_id, name),

  CONSTRAINT fk_users_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time sign-in codes (v3.0.0). One screen signs people in and signs new
-- people up: an emailed 6-digit code is the only credential. Codes are stored
-- hashed (password_hash()). attempts counts guesses against this code; 3 wrong
-- guesses cancel it (used_at is set). The (ip_address, created_at) index serves
-- the per-IP request limit. All datetimes are UTC, written by the Service.
CREATE TABLE IF NOT EXISTS login_codes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
