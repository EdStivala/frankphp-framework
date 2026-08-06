# FrankPHP v1.3.0 Base Install Migration Review

## Files reviewed

- `Tenant.php`
- `User.php`
- `bootstrap.php`
- `schema.sql`

## v1.3.0 migration objective

FrankPHP v1.3.0 introduces `App\Core\Clock` and the framework date/time standard:

> UTC at rest. Local at the edges.

Framework code should generate persisted timestamps through `Clock`, convert local user/tenant/app input to UTC before storage, convert UTC values to resolved local time before display, and calculate query boundaries in UTC.

## Key changes applied

### `User.php`

- Added `use App\Core\Clock;`.
- Replaced private/local UTC helpers with `Clock::nowUtcString()` and `Clock::utcOffsetString()`.
- Removed direct `DateTimeImmutable` / `DateTimeZone` usage from the model.
- Fixed the malformed SQL in `deleteExpiredPasswordResetTokens()`.
- Kept `findByEmail()` platform-wide, consistent with global `UNIQUE(email)`.

### `Tenant.php`

- Added `use App\Core\Clock;`.
- Delegated effective timezone resolution to `Clock::resolveTimezone()`.
- Kept tenant settings persistence explicit and allowlist-based.

### `bootstrap.php`

- Updated framework version reference to `1.3.0`.
- Imported `App\Core\Clock`.
- Set PHP runtime timezone from `config['timezone']`, falling back through `Clock::timezone()`.
- Did not add `Clock` to the container because it is a stateless static core utility.

### `schema.sql`

- Added v1.3.0 date/time standard comments.
- Ensured framework timestamp columns use UTC `DATETIME`.
- Retained fallback `DEFAULT (UTC_TIMESTAMP())` for first-boot/manual SQL safety.
- Fixed malformed seed timestamp literals.
- Normalised seed roles to `owner` and `user`.
- Kept platform-wide `UNIQUE(email)` as the framework identity rule.
- Added columns currently required by the uploaded `Tenant.php` and `User.php` settings methods.

## Important correction

FrankPHP platform identity requires globally unique user email addresses. The correct schema is:

```sql
UNIQUE KEY uq_users_email (email)
```

not tenant-scoped uniqueness such as:

```sql
UNIQUE KEY uq_users_tenant_email (tenant_id, email)
```

Tenant isolation is still enforced through `tenant_id`, middleware, and model queries, but login identity is platform-wide.

## Existing-install migration

Use `migration_v_1.3.0.sql` for existing installations. Do not replace an existing live schema wholesale with the fresh-install `schema_v1.3.0.sql`.

The migration script includes:

- preflight duplicate email detection;
- guarded column additions;
- UTC-session timestamp standardisation;
- conversion of framework `TIMESTAMP` columns to `DATETIME` where present;
- global `UNIQUE(email)` enforcement;
- post-migration review queries.

## Existing data warning

The migration cannot infer historical timezone semantics from existing `DATETIME` values. If prior application code stored local wall-clock values in `DATETIME`, those values must be manually normalised to UTC using the known source timezone before relying on v1.3.0 UTC filtering/display.
