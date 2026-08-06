# FrankPHP v1.3.0 Existing Data Migration Notes

## Platform-wide email uniqueness

FrankPHP v1.3.0 keeps user email uniqueness platform-wide. The `users.email` column must be globally unique across the database, not unique only within a tenant.

Before running the migration, check for duplicate emails:

```sql
SELECT email, COUNT(*) AS duplicate_count, GROUP_CONCAT(id ORDER BY id) AS user_ids
FROM users
GROUP BY email
HAVING COUNT(*) > 1;
```

If this returns rows, the migration deliberately stops before adding `UNIQUE(email)`. Resolve each duplicate first by renaming, merging, or deleting the duplicate account records. The migration cannot safely decide that automatically because each duplicated email represents an identity/account ownership decision.

## Existing timestamp data

FrankPHP v1.3.0 introduces the rule: **UTC at rest, local at the edges**.

All persisted framework timestamps should be stored as UTC `DATETIME` values. Application code should generate and convert timestamps through `App\Core\Clock`.

### Existing `TIMESTAMP` columns

The migration sets:

```sql
SET time_zone = '+00:00';
```

before converting framework `TIMESTAMP` columns to `DATETIME`. This preserves the UTC interpretation of existing timestamp values as read by the UTC migration session.

### Existing `DATETIME` columns

MySQL `DATETIME` has no timezone metadata. If historical values were already UTC, no data correction is needed.

If an existing app stored local wall-clock values in `DATETIME`, the migration cannot detect that. You must correct those values manually using the known source timezone. Example:

```sql
UPDATE some_table
SET created_at = CONVERT_TZ(created_at, 'Europe/London', '+00:00')
WHERE created_at IS NOT NULL;
```

Only do this when you know the previous values were stored in that local timezone. Do not apply timezone conversion blindly.

## Date-only values

Date-only values such as birthdays, anniversary dates, or plain due dates should remain `DATE`, not `DATETIME`. They are calendar dates, not instants in time.

## Files produced

- `schema_v1.3.0.sql` — corrected fresh-install schema with global `UNIQUE(email)`.
- `migration_v_1.3.0.sql` — existing-install migration script for core framework tables.
- `CODEBASE_v1.3.0.md` — updated framework contract documenting platform-wide email uniqueness.
- `CHANGELOG_v1.3.0.md` — updated human-readable changelog entry.
