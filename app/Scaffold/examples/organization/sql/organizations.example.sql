-- ------------------------------------------------------------------
-- EXAMPLE TABLE — organizations
--
-- One part of the scaffold/examples/organization/ recipe — see this
-- folder's README.md. Not a framework-owned table. Run this against
-- your app's own schema (e.g. app/sql/schema.app.sql) if you keep it.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS organizations (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id  INT NOT NULL,
  name       VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
  updated_at DATETIME NULL DEFAULT NULL,

  INDEX idx_organizations_tenant (tenant_id),

  CONSTRAINT fk_organizations_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
