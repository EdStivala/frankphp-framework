# FrankPHP v2.1.0 Migration Notes

## Who needs this

Only **existing installs** upgrading from v2.0.x. A fresh install gets everything from `sql/schema.core.sql` on first boot and needs no action. Running the script on one is a harmless no-op.

## What changes in the database

Four nullable columns, no data changes:

| Table | Column | Type |
|---|---|---|
| `users` | `terms_accepted_at` | `DATETIME NULL` (after `accessed_at`) |
| `users` | `terms_version` | `VARCHAR(50) NULL` |
| `signup_tokens` | `terms_accepted_at` | `DATETIME NULL` (after `user_agent`) |
| `signup_tokens` | `terms_version` | `VARCHAR(50) NULL` |

They record Terms & Conditions acceptance at signup: the UTC moment the user submitted signup step 1, and the app's terms version at that time. See `codebase.md` §16.9.

## How to run it

Run `framework/sql/migrations/v2.1.0_signup_terms.sql` against each environment's app database, with your usual MySQL client or:

```bash
mysql -u <user> -p <database> < framework/sql/migrations/v2.1.0_signup_terms.sql
```

The script is **idempotent**. Each `ALTER TABLE` is guarded by an `information_schema.COLUMNS` check, so running it twice (or on a fresh install) changes nothing the second time. It sets `time_zone = '+00:00'` for the session, following the v1.3.0 convention.

Check afterwards:

```sql
SHOW COLUMNS FROM users LIKE 'terms_%';
SHOW COLUMNS FROM signup_tokens LIKE 'terms_%';
```

Each should return two rows.

## Existing users

Existing users keep `NULL` in both columns, meaning "acceptance not recorded". **Do not backfill.** A made-up acceptance timestamp is worse than none. If you need existing users to accept your terms, that is a re-acceptance flow, which is future work and not part of this release.

## Order of operations

1. Deploy the v2.1.0 `framework/` folder.
2. Run the migration script.
3. **Then**, optionally, enable the feature in `app/Config/config.php`:
   ```php
   'signup' => ['require_terms' => true, 'terms_version' => '2026-10'],
   ```

Steps 1 and 2 can happen in either order while the flag is off: the v2.1.0 code never writes the new columns unless `require_terms` is on. If the flag is enabled before the migration, the signup pages fail with a `LogicException` naming this script. Nothing else is affected.

## Also in this release: signup behaviour change

No migration is needed for this, but check it before deploying. From v2.1.0 **every new signup creates its own tenant and becomes its `owner`**. Previously every signup joined tenant 1 as `user`. Existing tenants and users are untouched. If your app needs a different rule, override `SignupTenantResolver` in `app/bootstrap.php` before deploying (`codebase.md` §16.9).
