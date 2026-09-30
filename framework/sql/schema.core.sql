-- FrankPHP schema.core.sql — framework-owned tables only.
--
-- Run once, automatically, on first boot by Core\Database::connect() via
-- Database::runSchemaFileIfMissingTable(), guarded on the 'users' table.
-- Not an incremental migration system — see codebase.md for the policy on
-- evolving an already-created schema (framework tables are immutable from
-- the app's perspective; extend via an app-owned joined table instead).
--
-- Date/time standard:
--   UTC at rest. Local at the edges.
--
-- All persisted application timestamps use DATETIME and are treated as UTC.
-- Runtime code should write explicit UTC values using Frank\Core\Clock.
-- DEFAULT (UTC_TIMESTAMP()) is retained as a safety fallback for manual SQL,
-- seeds, and first-boot schema creation.
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
  password_hash         VARCHAR(255) NULL,
  role                  VARCHAR(50) NOT NULL DEFAULT 'user',
  api_key_hash          CHAR(64) NULL,
  timezone              VARCHAR(100) NULL,
  created_at            DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
  updated_at            DATETIME NULL DEFAULT NULL,
  accessed_at           DATETIME NULL DEFAULT NULL,
  terms_accepted_at     DATETIME NULL DEFAULT NULL,
  terms_version         VARCHAR(50) NULL DEFAULT NULL,

  UNIQUE KEY uq_users_tenant_email (tenant_id, email),
  UNIQUE KEY uq_users_api_key_hash (api_key_hash),
  INDEX idx_users_tenant_role (tenant_id, role),
  INDEX idx_users_tenant_name (tenant_id, name),

  CONSTRAINT fk_users_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  tenant_id  INT NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL DEFAULT NULL,

  INDEX idx_password_reset_token_hash (token_hash),
  INDEX idx_password_reset_expires_at (expires_at),
  INDEX idx_password_reset_user_id (user_id),
  INDEX idx_password_reset_tenant_id (tenant_id),

  CONSTRAINT fk_password_reset_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_password_reset_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS signup_tokens (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(255) NOT NULL,
  code_hash     VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  tenant_id     INT NULL DEFAULT NULL,
  expires_at    DATETIME NOT NULL,
  used_at       DATETIME NULL DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
  ip_address    VARCHAR(45) NULL DEFAULT NULL,
  user_agent    VARCHAR(512) NULL DEFAULT NULL,
  terms_accepted_at DATETIME NULL DEFAULT NULL,
  terms_version     VARCHAR(50) NULL DEFAULT NULL,

  INDEX idx_signup_tokens_email (email),
  INDEX idx_signup_tokens_expires_at (expires_at),
  INDEX idx_signup_tokens_used_at (used_at),
  INDEX idx_signup_tokens_created_at (created_at),
  INDEX idx_signup_tokens_ip_created (ip_address, created_at),

  CONSTRAINT fk_signup_tokens_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Email verification codes for new account signups. Separate from password_reset_tokens for independent abuse monitoring.';

INSERT INTO tenants (name, slug, timezone, date_format, created_at)
VALUES ('Tenant 1 FrankPHP', 'tenant-1-frankphp', 'Europe/London', 'DD/MM/YYYY', UTC_TIMESTAMP());

INSERT INTO users (tenant_id, email, name, password_hash, role, created_at)
VALUES
  (1, 'owner@tenant1.com', 'Demo Owner', '$2a$12$w2aRcA4FARcJE.oQaznxseHHBm1uXkRs43N4zbqcnltaBKXvOsywa', 'owner', UTC_TIMESTAMP()),
  (1, 'user@tenant1.com', 'Demo User', '$2a$12$w2aRcA4FARcJE.oQaznxseHHBm1uXkRs43N4zbqcnltaBKXvOsywa', 'user', UTC_TIMESTAMP());
