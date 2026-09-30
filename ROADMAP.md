# FrankPHP Roadmap — Working Notes

Raw backlog of everything identified so far. Not yet clustered, prioritised,
or assigned to versions except where explicitly noted below — that's a
separate pass.

---

## Signup & Tenant Creation

- SSO/OAuth for signup (previously discussed, not yet scoped).
- Org-email auto-recognition (join an existing tenant by email domain,
  previously discussed, not yet scoped).
- Invite flow: invite a user into an existing tenant, and change a user's role.
  Since v2.1.0 every signup creates its own tenant, so colleagues who sign up
  separately end up in separate tenants.
- Resend code is broken on `app/Views/auth/signup-verify.php`. The resend
  `<form>` is nested inside the verify form (invalid HTML), so the Resend
  button submits to `/signup/verify` instead of `/signup/resend`.
- No CSRF protection on the signup / verify / resend forms (login has it).
- `platform` role isn't cross-tenant yet: `AuthMiddleware` 403s on a tenant
  mismatch, and `TenantSettingsController` only admits `admin`/`owner`.
- Terms re-acceptance when `signup.terms_version` changes, plus a consent
  audit log (IP / user agent). See also **User Payments & Gates**.
- Captcha / honeypot on signup. Signups now create tenants, so spam costs
  more than it did.

## UI Content Cleanup

- `app-nav.php`'s two sidebar "popout" panels (`popout-organisations`,
  `popout-people`) are live, working UI — wired up by `this-site-nav.js`, not
  dead code — but their content is placeholder junk: one links to
  `https://bbc.co.uk` labeled "New Chat"; the other has a typo ("Peoplee")
  and lists things ("Templates", "Collections") that don't correspond to any
  real feature. Needs a decision: keep the popout mechanism with real
  content, or remove it.
- `app/Views/home/dashboard.php`'s "My Tasks" card is a large block of
  leftover CRM demo content (`$todayTasks`/`$upcomingTasks`/`$overdueTasks`,
  task filter tabs, `markTaskComplete()`) that `HomeController` never
  actually supplies data for — it silently renders as empty states rather
  than erroring, so it's easy to miss. Should be stripped back to a genuinely
  minimal starter dashboard (matching the plain "Card One / Card Two"
  placeholders already sitting next to it).

## Documentation

- Update n3wmedia.com itself to reflect the move to GitHub.

## Ongoing Maintenance (not version-specific)

- Vendored PHPMailer is stale (6.4.1). No Composer/Dependabot watching it —
  checking upstream for security releases should become a routine step when
  cutting each framework release, not a one-off.

## App Migration

- Migrate the existing FrankPHP apps (2–3 of them, still on the old mixed-zip
  model) onto the 2.0.0 framework/app split.

## User Payments & Gates

Large enhancement project, not yet scoped. Hooks FrankPHP up to Stripe and
builds a subscription and privileges framework around the User.

- Stripe subscription billing integration: plans, checkout, customer portal,
  webhooks, subscription state kept in sync with the tenant/user.
- Privilege gates driven by subscription: which features, limits and roles
  a user or tenant is entitled to, enforced consistently (middleware /
  service checks) rather than ad-hoc `in_array` role checks in controllers.
- `User::findTermsAcceptance(int $userId): ?array`: a supported read-only
  accessor for the v2.1.0 consent columns (`terms_accepted_at`,
  `terms_version`). These columns are deliberately **not** public properties
  on `User`, because `BaseModel::save()` persists every public property and
  would overwrite recorded consent with NULL from a partially-filled object.
  The accessor gives apps a way to read consent that never writes it.
  Belongs here because terms version checks (re-acceptance gates) are
  a natural part of the gating work.

## Migration & Upgrade Tooling

- Build and distribute a structured migration and upgrade tool. Framework
  schema changes currently ship as manual, hand-run SQL plus a notes file
  (`framework/Versions/`, and from v2.1.0 `framework/sql/migrations/`).
  `schema.core.sql` only runs on first boot. That was acceptable when
  FrankPHP was more rudimentary, but it doesn't scale as it evolves into a
  more sophisticated SaaS micro framework. Needs at minimum:
  - a record of which migrations have been applied (e.g. a
    `framework_migrations` table),
  - ordered, idempotent, versioned migration files,
  - a runner (CLI, and/or a guarded boot-time check),
  - an upgrade path covering both framework code replacement and schema,
  - a clear policy for app-owned migrations alongside framework ones.
- Once this exists, retire the per-release manual SQL + notes pattern and
  the per-feature "is the migration applied?" boot checks (the first of
  these ships in v2.1.0 for `signup.require_terms`).

## Future Product Features

From the new positioning notes — not yet scoped:

- API-first architecture + recipes for connecting a native iOS Swift app to
  a FrankPHP backend (framework provides the wiring, not the mobile code).
- Syncfusion component integration ("bring your own licence").
