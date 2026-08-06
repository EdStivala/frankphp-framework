# Changelog

## [2.0.0] - 2026-08-06

### Human-readable summary

FrankPHP 2.0.0 is the **framework/application split**: the single project folder is now two independent sibling folders, `framework/` and `app/`, each with its own PSR-4 namespace root (`Frank\` and `App\`, no searching or fallback between them). `framework/` contains nothing but framework-owned code and is meant to be replaced wholesale on every future update. `app/` — routes, business logic, and now also `Views/`, `public/`, `Config/config.php`, `.env`, and `MYAPP.md`, all of which used to live inside the framework folder — is never touched by an update once a developer has customized it. This is the precursor to publishing FrankPHP as a public GitHub repository rather than a Gumroad zip.

Every framework class was renamed from `App\` to `Frank\`. `HomeController` — the starter dashboard controller — moved to `app/Controllers/` as app-owned demo content, since every real application replaces it. Several real bugs were found and fixed along the way, not just moved: a schema-path resolution bug, a double database-seeding bug, an infinite-redirect-loop bug in `TenantSettingsController`'s forbidden-access check, and hardcoded BitFitter branding sitting in framework defaults (`EmailMessage`'s sender fallback). The `Container` and `Router` both gained an explicit `override()` method — `singleton()`/`bind()`/`add()` now throw on a duplicate id/route instead of silently allowing one framework component to shadow another, and `override()` is the one deliberate way to replace one on purpose. `EmailService`'s three framework-required email flows (signup verification, password reset, platform-owner alert) are now overridable per-application via `config('mail.templates')`, without ever editing the framework's own template files.

A broken, unused feature (`cron_task_reminders.php` — referenced a non-existent `Task` model and was hardcoded to a different project's domain) was removed outright rather than migrated. `codebase.md` itself received a full accuracy pass — the directory structure, route tables, and numerous `App\Core\...` references throughout the document were still describing the pre-split, single-folder layout and have been corrected.

### Added

- **`app/` as a sibling of `framework/`** — the application half of the split: `Controllers/`, `Models/`, `Services/`, `Presenters/` (empty, ready for application code), plus `Views/`, `public/`, `Config/config.php`, `.env`/`env.example`, `MYAPP.md`, and `Scaffold/examples/` (cookbook-style reference implementations — organization, media, form-presenter, video-library — moved out of live framework code, not deleted).
- `Controllers/AccountSettingsController.php` — per-user account settings, no role restriction (distinct from `TenantSettingsController`, which is admin/owner-only).
- `Container::override(id, factory, shared = true)` and `Router::override(method, pattern, handler, middleware)` — the one supported way to deliberately replace a framework-registered binding or route from application code.
- `Database::runSchemaFileIfMissingTable()` promoted to a general-purpose primitive — applications may now call it a second time for their own `app/sql/schema.app.sql`, guarded by an application-chosen sentinel table. Wired into `app/bootstrap.php` behind a `file_exists()` guard, so it's a no-op until an application actually creates that file.
- `config('mail.templates')` — optional per-application overrides for the three framework-required email templates (signup verification, password reset, platform-owner alert). Framework ships working defaults; nothing needs configuring for a fresh install to send correct email.
- `codebase.md` §16.7 "Extending Framework-Owned Tables" — documents the supported pattern (a satellite table with a foreign key, never editing `tenants`/`users` directly) for applications that need to attach their own data to a tenant or user.
- `codebase.md` §16.8 "Overriding Framework Email Templates" — documents the `mail.templates` mechanism above, including the exact static method signature each override must implement.
- `FRANK_VERSION` constant, read once from `framework/VERSION`, shown in the starter app's sidebar footer.
- `Services/Email/Templates/PlatformOwnerSignupAlertTemplate.php` — replaces `TenantOwnerAdvisoryTemplate.php` (renamed; see Changed).
- `sql/schema.core.sql` — replaces `sql/schema.sql` (renamed; see Changed).

### Changed

- Every framework class renamed from `App\` to `Frank\` — `Core/`, `Controllers/`, `Middleware/`, `Models/`, `Services/`. Two independent, single-path PSR-4 mappings (`Frank\` → `framework/`, `App\` → `app/`); a class's namespace alone determines where it's found, no searching.
- All framework routes now consistently use fully-qualified `Frank\Controllers\...` handler strings and `Frank\Middleware\...` middleware strings. Previously an inconsistent mix of bare shorthand and `App\`-prefixed strings, some of which were already wrong before this release.
- `framework/bootstrap.php` now hard-requires `app/bootstrap.php` as its final step, passing `$container`/`$router`/`$config` already in scope. A missing `app/bootstrap.php` is a fatal boot error by design.
- `Core/Database.php` — fixed a schema-path resolution bug (was resolving one directory too high); removed a redundant hardcoded seed-data `INSERT` that ran alongside `schema.core.sql`'s own seed data, which caused double-seeding. `schema.core.sql`'s seed data (one tenant, two users, password `password`) is now the sole source.
- `Core/BaseController::view()` simplified — no more namespace branching; always resolves against `APP_VIEWS_DIR`, a constant defined once in `framework/bootstrap.php`.
- `Controllers/TenantSettingsController.php` — fixed a real bug: the forbidden-access check redirected a rejected user back to the exact same gated route, causing an infinite redirect loop for any non-admin/owner user who hit it (browsers reported this as a connection failure, not a permissions error). Now redirects to the tenant dashboard, matching the pattern already used correctly in `UserManagementController`.
- `Services/Email/EmailMessage.php` — removed hardcoded `noreply@bitfitter.me` / `BitFitter` / `hello@bitfitter.me` constructor defaults. `from` and `fromName` are now required arguments; every call site already supplied them explicitly, so this is a dead-code removal, not a behavior change — but it closes off a landmine for any future caller that forgot to.
- `Services/Email/EmailService.php` — the three framework-required send methods now resolve their template class through `config('mail.templates')` before falling back to the framework default (see Added). `sendPlatformOwnerSignupAlert()` renamed from `sendTenantOwnerAdvisory()` — it alerts a single platform-wide operator address (`MAIL_SITE_ADMIN`), not a per-tenant owner, which the old name implied incorrectly.
- `tools/build_release.php` — `required_files`/`exclude_paths` updated to match the post-split `framework/` structure. Previously still expected `Config/config.php`, `Views/`, `public/`, `private/`, and `Helpers/` to exist inside `framework/` — none of them do anymore.
- `codebase.md` — full accuracy pass. The directory structure (§2) was completely rewritten for the split; numerous `App\Core\...` references throughout the document (Clock, Container, BaseController, BaseModel, Database) corrected to `Frank\Core\...`; removed two phantom references to a `UserController.php`/`GET /me` route that don't exist in the real codebase; corrected the dashboard route's ownership (app-owned, not framework-owned) in three places; documented `Container::override()`/`Router::override()`, which had never been written up.

### Removed

- `cron_task_reminders.php` and `Services/Email/Templates/TaskReminderEmailTemplate.php` — a broken, unused feature. Referenced a `Task` model that doesn't exist and was hardcoded to an unrelated project's domain. Deleted outright rather than migrated.
- `POST /api/v1/tenant/{tenant_id}/organizations` (`Api\V1\OrganizationController`) is no longer a live framework route — the controller moved to `app/Scaffold/examples/organization/` as a reference implementation. An application that wants this endpoint now copies the example in rather than getting it by default.

### Note on this release's scope

This entry covers `framework/` only, per §15.3 — that's what `CHANGELOG.md` tracks. The starter content shipped in `app/` (branding genericized, several broken image paths and a dead-title bug fixed, a forbidden-access flash-message convention established) changed too, but as application-owned content it isn't itemized here.

## [1.4.0] - 2026-06-09

### Human-readable summary
FrankPHP 1.4.0 introduces the framework's first built-in admin view: the **User Management Dashboard**, available to any user with role `owner` or `admin` at `GET /tenant/{tenant_id}/users`.

The dashboard provides a tenant-scoped view of all users with four KPI stat cards and a full sortable user table. All data preparation follows the existing Service → Controller → View contract — the view receives flat, named scalars and performs no computation.

A new framework service (`UserManagementService`) and controller (`UserManagementController`) are added as framework-owned components. The `User` model gains one new query method. A new CSS file (`app-pages-users.css`) introduces reusable stat card and user table classes. The sidebar nav gains a Users entry, visible to all authenticated users but the dashboard redirects non-admin/owner roles to the dashboard with an error.

This release also corrects long-standing inaccuracies in `CODEBASE.md`: the directory structure, framework table schemas, design token values, and container bindings table are all brought up to date. The mandatory documentation rule (§15.3) is extended to cover framework changes (CODEBASE.md + CHANGELOG.md) as a first-class obligation alongside application changes (MYAPP.md).

### Added

- `Services/UserManagementService.php` — new framework-owned service singleton.
  Public method: `getUserDashboard(int $tenantId, array $user, array $tenant, array $config): ServiceResult`
  - Fetches all users for the tenant via `User::allByTenant()` in a single query.
  - Computes four KPI stats from that result set with no additional DB round-trips:
    - `totalUsers` — total user count for the tenant
    - `activePercent` — percentage of users who have logged in at least once (accessed_at IS NOT NULL)
    - `neverLoggedIn` — count of users where accessed_at IS NULL
    - `inactiveThirtyDays` — count of users who have logged in before but not in the last 30 days (Clock::utcOffsetString('-30 days') cutoff)
  - Resolves display timezone via `Clock::resolveTimezone()`.
  - Formats `created_at` and `accessed_at` for each user row into display-ready strings via `Clock::formatUtcForTimezone()`. Sets `lastLoginLabel` to `'Never logged in'` and `hasLoggedIn` to `false` for null `accessed_at`.
  - Returns `ServiceResult` with `users` (display-ready rows), `totalUsers`, `activePercent`, `neverLoggedIn`, `inactiveThirtyDays`.
  - Registered as a singleton in `bootstrap.php`.

- `Controllers/UserManagementController.php` — new framework-owned controller.
  Method: `index(Request $request, array $params)`
  - Role-gates to `admin` and `owner` — non-privileged users are redirected to dashboard with `?error=forbidden`.
  - Delegates entirely to `UserManagementService::getUserDashboard()`.
  - Passes five flat variables to the view: `users`, `totalUsers`, `activePercent`, `neverLoggedIn`, `inactiveThirtyDays`.
  - Injects `UserManagementService` and `$config` via constructor.
  - Registered as a `bind()` (per-request) in `bootstrap.php`.

- `Views/users/management-dashboard.php` — new framework view.
  - Four stat cards in a responsive 2×2 (mobile) / 4-across (desktop) grid using `.stat-card`.
  - Full user table using `.users-table` with columns: Email, Name, Role (`.role-badge`), Joined, Last Login.
  - Empty-state message when tenant has no users.
  - Uses `app-main.php` layout. Sets `$pageTag = 'Users'` to activate the nav entry.
  - All displayed values are pre-computed scalars — no computation, no Clock calls, no business logic.

- `public/assets/css/app-pages-users.css` — new framework CSS file.
  Reusable classes introduced:
  - `.stat-card` — KPI summary tile (white card, border, hover shadow)
  - `.stat-card-icon` — square icon container within a stat card
  - `.stat-card-icon--primary` — brand-primary glass background variant
  - `.stat-card-icon--success` — green background variant
  - `.stat-card-icon--warning` — amber background variant
  - `.stat-card-icon--danger` — red background variant
  - `.stat-card-body` — text container within a stat card
  - `.stat-card-value` — large numeric value display
  - `.stat-card-label` — small descriptive label below value
  - `.users-table` — full-width tenant user list table
  - `.users-table .col-email` — bold email cell
  - `.users-table .col-date` — muted date cell
  - `.users-table .col-never` — italic muted cell for "Never logged in"
  - `.role-badge` — pill label for user role display
  - `.role-badge--owner` — brand-primary glass variant
  - `.role-badge--admin` — green variant
  - `.role-badge--user` — neutral grey variant

- `User::allByTenant(int $tenantId): array` — new model method.
  Returns all user rows for a tenant (`id`, `tenant_id`, `email`, `name`, `role`, `created_at`, `accessed_at`) ordered by `created_at DESC`. Sensitive columns (`password_hash`, `api_key_hash`) are excluded. Used exclusively by `UserManagementService`.

- Route `GET /tenant/{tenant_id}/users` → `UserManagementController@index` with `[$tm, $auth]` middleware.

- Sidebar nav entry `Users` added as the last item in `Views/partials/app-nav.php`.
  Uses Bootstrap Icon `bi-person`. Active state triggered by `$pageTag == 'Users'`.

- `app-main.php` `<head>` now includes `app-pages-users.css`.

### Changed

- `bootstrap.php` — two new registrations:
  - `UserManagementService::class` registered as singleton (depends on `new User()`).
  - `UserManagementController::class` registered as bind (injects `UserManagementService` + `$config`).
  - Route `GET /tenant/{tenant_id}/users` registered.
  - Version comment updated to v1.4.0.

- `sql/schema.sql` — version comment updated to v1.4.0. No schema changes.

- `CODEBASE.md` — comprehensive documentation corrections:
  - §2 Directory Structure: fully updated to reflect actual filesystem (public/ tree, all controllers, Helpers/, Views/users/, Views/home/, Views/modals/, Views/partials/ with gtm files, js/ files, vendor/ directory).
  - §8.2 Container table: `UserManagementService` singleton and `UserManagementController` bind added.
  - §12 CSS file table: `app-pages-users.css` added.
  - §12 Design tokens: corrected to actual values (`--brand-primary: #2B3A67`, `--brand-primary-dark: #2CB7B1`, `--brand-primary-light: #F7C700`); semantic UI mapping tokens added (`--app-bg`, `--text-main`, `--text-heading`, `--border-default`); danger variants added.
  - §15.2 Framework-owned list: `UserManagementController` and `UserManagementService` added.
  - §15.3 Mandatory Documentation Rule: extended with a second table covering framework change obligations (CODEBASE.md + CHANGELOG.md), making framework documentation a first-class requirement alongside application documentation.
  - §16.1 `tenants` table: schema corrected — `company_email`, `timezone`, `date_format` columns added; incorrect `settings` column removed.
  - §16.1 `users` table: schema corrected — `api_key_hash` column added; incorrect `date_format` and `settings` columns removed; precise types and constraints added.
  - §16.1 `signup_tokens` table: added in full — was entirely absent from previous documentation.
  - §16.2 Framework Models: `User` model method list updated — `findApiKeyHash()`, `findAllWithTaskRemindersEnabled()`, `allByTenant()` added.
  - §16.4 Framework Default Routes: `/tenant/{tenant_id}/users` added.
  - §16.5 Framework Services: `UserManagementService` added.
  - Framework version updated to `1.4.0`.

### Build rules introduced / clarified
- Framework changes (controllers, services, routes, CSS, schema) require CODEBASE.md and CHANGELOG.md updates in the same deliverable — not as a follow-up step.
- Documentation completeness is verified against the actual filesystem, not inferred from memory. When CODEBASE.md diverges from the codebase, correct it immediately.
- The `signup_tokens.tenant_id` column is NULL for the full lifetime of an unverified token. Do not attempt per-tenant scoping of signup token queries — it is structurally impossible before signup completes.

---

## [1.3.2] - 2026-06-09

### Human-readable summary
FrankPHP 1.3.2 enforces a strict Model/Service separation of concerns (SoC) across
the entire framework. The headline change is that **models are now pure DB access
layers** — they never call `App\Core\Clock` and they contain no business logic.

Prior to this release, `User.php` and `Tenant.php` called `Clock::nowUtcString()` and
`Clock::utcOffsetString()` internally to generate timestamps for INSERT and UPDATE
statements. `SignupService` duplicated all signup token DB access using raw PDO rather
than delegating to `User` model methods, and those queries used SQL `NOW()` — a
framework rule violation. Several model methods also contained business logic
(hash verification, rate-limit decisions, name derivation) that belongs in Services.

This release corrects all of the above and introduces `UserService` as a new
framework-owned service to own business logic that concerns the user record.

### Added
- `Services/UserService.php` — new framework-owned service. Introduced to own
  business logic for the user record that does not belong in a model or controller.
  Public methods:
  - `recordLogin(int $userId, int $tenantId): bool` — computes UTC timestamp via
    Clock, passes it to `User::updateLastAccessed()`. Called by `AuthController`
    after a successful login.
  - `saveSettings(int $userId, int $tenantId, array $data): bool` — owns the
    writable column allowlist and empty-string-to-null coercion rules for user
    preferences. Computes UTC timestamp via Clock, passes clean column map and
    timestamp to `User::saveSettings()`.
  Registered as a singleton in `bootstrap.php`.

- `Services/TenantService.php` — new framework-owned service. Parallel to
  `UserService`. Owns business logic for the tenant record.
  Public methods:
  - `saveSettings(int $tenantId, array $data): bool` — owns the writable column
    allowlist and empty-string-to-null coercion rules for tenant settings.
    Computes UTC timestamp via Clock, passes clean column map and timestamp to
    `Tenant::saveSettings()`.
  Registered as a singleton in `bootstrap.php`.

- `User::insertUser(tenantId, email, name, passwordHash, role, createdAt)` — new
  model method replacing the private `createUser()` helper that previously lived
  inside `SignupService`. The `$name` and `$createdAt` parameters are computed by
  `SignupService` (business logic and Clock stay in the service).

- `User::findPendingSignupTokens(email, now)` — replaces `findValidSignupToken()`.
  Returns raw candidate rows; hash verification (`password_verify`) is done in
  `SignupService::findAndVerifyToken()`, not in the model.

- `User::countRecentSignupTokensByIp(ipAddress, threshold)` — replaces
  `isSignupIpRateLimited()`. Returns a count; the rate-limit decision (`>= max`)
  is made in `SignupService`, not in the model.

### Changed

**`Models/User.php`**
- All `Clock::nowUtcString()` and `Clock::utcOffsetString()` calls removed.
- All methods that previously generated timestamps internally now accept a
  pre-computed UTC string parameter from the calling Service:
  - `updateLastAccessed(userId, tenantId, accessedAt)` — `$accessedAt` passed in
  - `saveSettings(userId, tenantId, data, updatedAt)` — `$updatedAt` passed in
  - `updatePasswordHash(userId, passwordHash, updatedAt)` — `$updatedAt` passed in
  - `insertPasswordResetToken(userId, tenantId, tokenHash, expiresAt, createdAt)` — both timestamps passed in
  - `findValidPasswordResetTokens(now)` — `$now` passed in (was computed internally)
  - `markPasswordResetTokenAsUsed(tokenId, usedAt)` — `$usedAt` passed in
  - `invalidateAllUserPasswordResetTokens(userId, usedAt)` — `$usedAt` passed in
  - `countRecentPasswordResetTokens(userId, threshold)` — `$threshold` passed in (was computed internally)
  - `deleteExpiredPasswordResetTokens(cutoff)` — `$cutoff` passed in (was computed internally)
  - `insertSignupToken(email, codeHash, passwordHash, expiresAt, createdAt, ipAddress, userAgent)` — both timestamps passed in
  - `invalidatePendingSignupTokens(email, usedAt)` — `$usedAt` passed in
  - `markSignupTokenUsed(tokenId, usedAt)` — `$usedAt` passed in
  - `findMostRecentPendingSignupToken(email, now)` — `$now` passed in
  - `deleteExpiredSignupTokens(cutoff)` — `$cutoff` passed in
- `findValidSignupToken()` removed — replaced by `findPendingSignupTokens(email, now)`;
  hash verification moved to `SignupService`.
- `isSignupIpRateLimited()` removed — replaced by `countRecentSignupTokensByIp(ip, threshold)`;
  the rate-limit decision moved to `SignupService`.
- `createSignupUser()` removed — replaced by `insertUser(...)`; name derivation moved
  to `SignupService::deriveNameFromEmail()`.

**`Models/Tenant.php`**
- `saveSettings(tenantId, data)` signature changed to
  `saveSettings(tenantId, data, updatedAt)`. `$updatedAt` is now computed by the
  calling Service and passed in. `Tenant` no longer calls Clock.

**`Services/SignupService.php`**
- Constructor changed: accepts `?User $userModel` instead of `?PDO $pdo`.
  All DB access now goes through `User` model methods.
- All private raw-PDO helpers removed (`insertToken`, `invalidatePendingTokens`,
  `markTokenUsed`, `findValidToken`, `findMostRecentPendingToken`,
  `isIpRateLimited`, `createUser`). These were duplicating model functionality.
- SQL `NOW()` and `DATE_SUB(NOW(), ...)` removed from all queries. All temporal
  boundaries are now PHP strings produced by Clock in this service.
- `password_verify()` hash check extracted into private `findAndVerifyToken()` —
  correctly lives in the service, not the model.
- `deriveNameFromEmail()` extracted as a private method — name derivation is
  business logic and lives in the service, not the model.
- `cleanupExpiredTokens()` now delegates to `User::deleteExpiredSignupTokens(cutoff)`.
- Unused `sendCoachAdvisory()` private method (duplicate of EmailService method)
  removed.

**`Services/PasswordResetService.php`**
- `generateToken()` updated: now passes both `$expiresAt` and `$createdAt` to
  `User::insertPasswordResetToken()` (previously only `$expiresAt` was passed,
  `created_at` was generated by Clock inside the model).
- `findValidToken()` updated: computes `$now` via Clock here and passes it to
  `User::findValidPasswordResetTokens($now)`.
- `isRateLimited()` updated: computes `$threshold` via Clock here and passes it to
  `User::countRecentPasswordResetTokens($userId, $threshold)`.
- `resetPassword()` updated: computes `$now` once via Clock and passes it to
  both `markPasswordResetTokenAsUsed` and `invalidateAllUserPasswordResetTokens`.
- `cleanupExpiredTokens()` updated: computes `$cutoff` via Clock here and passes
  it to `User::deleteExpiredPasswordResetTokens($cutoff)`.

**`Controllers/AuthController.php`**
- Now injects `UserService` via constructor (alongside existing `PasswordResetService`).
- `login()` method: replaced direct `$userModel->updateLastAccessed()` call with
  `$this->userService->recordLogin()`. The controller no longer touches the User
  model directly for post-login tracking.

**`bootstrap.php`**
- `UserService` registered as a singleton.
- `SignupService` singleton updated: passes `new User()` instead of `Database::getPdo()`.
- `AuthController` binding updated: now injects `UserService` as second argument.

**`CODEBASE.md`**
- Section 1: Key characteristics updated — models described as DB-access-only with
  no Clock calls; Services described as owning all Clock calls.
- Section 2: Directory listing updated — User.php, Tenant.php, UserService.php descriptions revised.
- Section 7: Model SoC Contract subsection added, defining the hard rule that models
  never call Clock and never contain business logic decisions. Tenant saveSettings
  signature updated.
- Section 8: Services intro updated — explicitly states Services own all Clock calls.
- Section 8.2: Container table updated — UserService added; SignupService dependency
  updated; AuthController injection updated.
- Section 10.3 and 10.8: Clock usage rules tightened — explicitly state Clock is
  called from Services only, never from Models.
- Section 11: Login flow description updated to reference UserService::recordLogin().
- Section 13: Security notes updated — timestamp generation described as Service-layer
  responsibility passed down to models as strings.
- Section 15.2: UserService added to framework-owned file list.
- Section 16.2: Framework Models table updated with full revised method signatures.
- Section 16.3: Clock utility notes updated — "Called from Services only".
- Section 16.5: Framework Services table updated — UserService added; SignupService
  and PasswordResetService notes updated.
- Framework version updated to `1.3.2`. Last updated changed to `2026/06/09`.

### Fixed
- `User` model was calling `Clock::nowUtcString()` and `Clock::utcOffsetString()`
  in 17 locations — violating the Model SoC contract. All removed.
- `Tenant` model was calling `Clock::nowUtcString()` in `saveSettings()`. Removed.
- `SignupService` duplicated all signup token DB access in private raw-PDO methods,
  bypassing the User model entirely. All duplicates removed; service now delegates
  to User model methods.
- `SignupService` private helpers used SQL `NOW()` and `DATE_SUB(NOW(), ...)` for
  temporal comparisons — violating the framework no-SQL-NOW() rule. Replaced with
  Clock-computed UTC boundary strings.
- `SignupService::findValidToken()` performed `password_verify()` inside what was
  effectively a model-level query — business logic in the wrong layer. Moved to
  service-level `findAndVerifyToken()`.
- `SignupService::isIpRateLimited()` made the rate-limit decision (`>= maxAttempts`)
  inside what was a model-level operation. Decision moved to service; model now
  returns a count.
- `SignupService::createUser()` derived the display name from the email local-part —
  business logic in what was a model method. Moved to `deriveNameFromEmail()` in
  the service.
- `User::saveSettings()` contained an allowlist (`$allowed`), `array_intersect_key`
  filtering, and empty-string-to-null coercion — all business-logic decisions in
  the wrong layer. All three moved to `UserService::saveSettings()`. The model
  now receives a pre-validated column map and executes the query.
- `Tenant::saveSettings()` had the same three violations. All three moved to
  `TenantService::saveSettings()`. The model now receives a pre-validated column
  map and executes the query.
- `AuthController::login()` called `$userModel->updateLastAccessed()` directly — a
  controller bypassing the service layer for a business operation. Replaced with
  `$this->userService->recordLogin()`.

### Build rules introduced / clarified
- Models must never call `App\Core\Clock`. This is a hard framework rule.
- All Clock calls live in Services. Timestamp strings are passed down to model
  methods as explicit `string` parameters.
- Models must not contain business-logic decisions: allowlists, field filtering,
  null-coercion rules, rate-limit comparisons, hash verification, name derivation,
  or field priority resolution. These belong in Services.
- SQL `NOW()` and `DATE_SUB(NOW(), ...)` must not appear in any model or service
  query. All temporal boundaries are PHP strings produced by Clock.
- Controllers must not call model methods for operations that carry business logic.
  Route through a Service instead.

---

## [1.3.1] - 2026-06-08

### Human-readable summary
FrankPHP 1.3.1 contains a minor fix to Bootstrap. A routing line was ommited that mean URL requests to the public route (index.php) were not being resolved.

This release also enhances the `users` table to record when a user last logged in successfully, in preparation for the User Management View, and rationalises a handful of fields that had been added to the core schema in error.

### Added
- `User::updateLastAccessed()` — new model method to store the time and date of a user's last successful login.
- `schema.sql` — `users` table gains an `accessed_at` field.

### Changed
- Framework version updated from `1.3.0` to `1.3.1`.
- Bootstrap.php routing line added to Public to resolve calls to \
- `AuthController.php` updated to call `User::updateLastAccessed()`.

### Fixed
- `schema.sql` — removed extraneous fields that had been added to core system tables in error.

## [1.3.0] - 2026-06-06

### Human-readable summary
FrankPHP 1.3.0 introduces the framework's first formal date/time handling standard.

The headline change is `App\Core\Clock`, a small framework-owned utility that makes FrankPHP explicit about a rule that every application needs: persisted timestamps are stored in UTC, while user, tenant, and application timezones are used at the input, output, and query-boundary edges.

This release is primarily a framework hygiene and portability release. It reduces the risk of timezone drift between applications, servers, databases, and users.

### Added
- `Core/Clock.php` — stateless static framework utility for date/time handling.
- UTC timestamp generation through:
  - `Clock::nowUtc()`
  - `Clock::nowUtcString()`
  - `Clock::utcOffset()`
  - `Clock::utcOffsetString()`
- UTC normalisation helpers:
  - `Clock::toUtc()`
  - `Clock::toUtcString()`
  - `Clock::parseUtc()`
- Timezone validation and resolution helpers:
  - `Clock::isValidTimezone()`
  - `Clock::timezone()`
  - `Clock::resolveTimezone()`
- Local input conversion helpers:
  - `Clock::localInputToUtc()`
  - `Clock::localInputToUtcString()`
- UTC-to-local output helpers:
  - `Clock::utcToTimezone()`
  - `Clock::formatUtcForTimezone()`
  - `Clock::localDateFromUtc()`
- Local date query-boundary helpers:
  - `Clock::utcStartOfLocalDate()`
  - `Clock::utcEndOfLocalDate()`
  - `Clock::utcRangeForLocalDate()`
  - `Clock::utcRangeForLocalDates()`
- Local "now" helpers for display/input defaults only:
  - `Clock::todayForTimezone()`
  - `Clock::nowForTimezone()`
  - `Clock::nowForTimezoneString()`
- CODEBASE.md Section 10: Date and Time Handling.
- CODEBASE.md Section 16.3: Framework Core Utilities.
- Framework rule: "UTC at rest. Local at the edges."
- Formal timezone resolution order:
  1. `user.timezone`
  2. `tenant.timezone`
  3. `config['timezone']`
  4. `UTC`
- Migration guidance for existing MySQL `TIMESTAMP` columns.

### Changed
- Framework version updated from `1.2.1` to `1.3.0`.
- CODEBASE.md `Last updated` changed to `2026/06/06`.
- Directory structure in CODEBASE.md now includes `Core/Clock.php`.
- `Config/config.php` description now documents the application timezone fallback role.
- Database section now states that persisted application timestamps should be generated by application code using `App\Core\Clock`.
- Database section now warns against SQL `NOW()` / `CURRENT_TIMESTAMP` for framework-managed and application-managed timestamps.
- Security notes now include the UTC timestamp storage rule.
- AI build rules now classify `Core/Clock.php` as framework-owned.
- Dependency Injection documentation now clarifies that `Clock` does not require a container binding while it remains a stateless static utility.
- Deliberate omissions now clarify that FrankPHP does not depend on an external date/time library.
- Framework table documentation now marks timestamp columns as UTC where applicable.
- Framework model documentation now notes that `User` framework timestamp writes should use `App\Core\Clock`.
- Framework default route and service sections renumbered because Date and Time Handling is now a first-class CODEBASE section.

### Build rules introduced
- Use `App\Core\Clock` for persisted timestamp generation.
- Store exact instants as UTC `DATETIME`.
- Use `DATE` for date-only values with no time-of-day meaning.
- Do not use SQL `NOW()` / `CURRENT_TIMESTAMP` for application-managed timestamps.
- Do not calculate local date windows directly in SQL.
- Convert local user/tenant/app input into UTC before storage.
- Convert UTC values into the resolved local timezone before display.
- Resolve timezone explicitly using `Clock::resolveTimezone($user, $tenant, $config)`.
- Do not let `Clock` read from `$_SESSION`, request globals, middleware state, controller globals, or the database.
- Do not call `Clock` from views.
- Services must prepare display-ready date labels for views.
- Views must not perform timezone conversion, timestamp comparison, date fallback logic, or relative date labelling.
- Document intentional application-level deviations in `MYAPP.md`.

### Migration notes
- Existing applications should add `Core/Clock.php` before refactoring date/time code.
- Existing private helpers such as `utcNow()` may temporarily delegate to `Clock::nowUtcString()` during migration.
- Search existing code for:
  - `date(`
  - `time(`
  - `strtotime(`
  - `new DateTime(`
  - `new DateTimeImmutable(`
  - SQL `NOW()`
  - SQL `CURRENT_TIMESTAMP`
- Replace persisted timestamp generation with `Clock`.
- Replace relative SQL date filters with UTC boundary values calculated in PHP through `Clock`.
- Existing MySQL `TIMESTAMP` columns should be reviewed and classified before conversion.
- For application-managed timestamps, FrankPHP prefers UTC `DATETIME`.
- When converting MySQL `TIMESTAMP` to `DATETIME`, run the migration session in UTC:

```sql
SET time_zone = '+00:00';
```

- Do not blindly convert date-only values; use `DATE` for values such as birthdays and local calendar-only due dates.
- If historical values were stored in local wall-clock time by mistake, perform a deliberate data correction instead of assuming they are already UTC.

### Fixed
- No runtime bug fix is included in this documentation release by itself.
- This release prevents future timezone and server portability bugs by defining a single framework-wide date/time contract.


## [1.2.1] - 2026-05-14
### Added
- No Added

### Changed
- 'config.php' admin_email added to SMTP mail array from MAIL_SITE_ADMIN
- '.env' MAIL_SITE_ADMIN=admin@yourdomain.com 

### Fixed
- 'SignupService::initiateSignup' to call utilise EMailService and Admin Email correctly

## [1.2.0] - 2026-05-13
### Added
- `Core/Container.php` — minimal explicit DI container (`singleton()`, `bind()`, `make()`, `has()`)
- `SignupController` added to framework-owned controller list (was already present but undocumented)
- `SignupService` and `PasswordResetService` registered as container singletons in `bootstrap.php`
- `AuthController` and `SignupController` registered as container bindings in `bootstrap.php`
- `EmailService::sendSignupVerification()` — sends verification code during signup flow
- `EmailService::sendCoachAdvisory()` — sends tenant-owner advisory on signup events; reads `MAIL_ADVISORY_TO` from `.env`
- `.env.example` key: `MAIL_ADVISORY_TO`
- Section 8.2 (DI Container) added to CODEBASE.md
- Section 15.4 (Framework Services) added to CODEBASE.md
- Signup routes added to Section 15.3 route table in CODEBASE.md

### Changed
- `Core/Router.php` — constructor now accepts optional `Container`; `resolveHandler()` checks container before falling back to `new $controller()`
- `bootstrap.php` — container created and populated after config load; `Router` receives container
- `Controllers/AuthController.php` — `PasswordResetService` injected via constructor; removed 4× inline `new PasswordResetService()`
- `Controllers/SignupController.php` — `SignupService` injected via constructor; removed 3× inline `new SignupService()`
- `Services/PasswordResetService.php` — removed broken zero-argument `new EmailService()` fallback; missing `EmailService` now throws `LogicException`
- `Services/SignupService.php` — removed broken zero-argument `new EmailService()` fallback; removed hardcoded SMTP credentials from `sendVerificationEmail()` and `sendCoachAdvisory()`; both methods now delegate to injected `EmailService`
- Section 3 (Request Lifecycle) updated to include container creation step
- Section 5 (Controllers) updated with constructor injection pattern
- Section 8 (Services) expanded with full `ServiceResult` interface, `EmailService` reference table, and DI container documentation
- Section 12 (Security Notes) updated — SMTP credentials flow documented
- Section 13 (Deliberate Omissions) — DI container entry removed (now implemented)
- Section 14.2 updated — `SignupController` added to framework-owned list; application container bindings noted as application-owned
- Section 14.3 updated — container bindings and email send methods added to mandatory documentation table
- Section 14.5 updated — framework email methods excluded from MYAPP.md scope

### Fixed
- `EmailService` construction failure: `PasswordResetService` and `SignupService` previously fell back to `new EmailService()` with no arguments, which broke after `EmailService` constructor was made to require SMTP configuration. Container singletons eliminate the fallback entirely.
- Hardcoded SMTP credentials removed from `SignupService` — credentials now flow exclusively from `.env` via `config['mail']` → `EmailService::fromConfig()` → container singleton

## [1.1.1] - 2026-05-08
### Added
- MYAPP.md created as a template and added to distribution

### Changed
- CODEBASE.md made section 14 into AI instructions for creating MYAPP
- CODEBASE.md section 15 documenting framework tables around User / tenant management

### Fixed
- No Fixes

## [1.1.0] - 2026-05-06
### Added
- Framework Config Management for FrankPHP Core and App using .env
- Incorporated EMAIL credentials into single .env
- Incorporated SynchFusion License Key into main .env

### Changed
- No Changed

### Fixed
- No Fixes
