# CODEBASE.md
>
> The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
> Copyright (c) 2026 Ed Stivala Limited
> License: MIT
> **AI INSTRUCTIONS:** 
> 1. Copy the entirety of this file and paste it into your first prompt with Claude, ChatGPT, or any LLM. 
> 2. Once pasted, use this prompt to start building: 
> 
> *"I am using FrankPHP. Based on this CODEBASE.md, help me scaffold a new Controller for [Your Feature] that handles [X] and uses the [Y] middleware. Ensure it follows the hydration and response patterns defined in the core."*

> Framework version: 1.4.0
> Last updated: 2026/06/09
> 
> **How to use this file:** Paste it at the start of any Claude conversation before describing your task.
> Add your application-specific section at the bottom as you build. Keep it accurate — Claude trusts this document.

---

## 1. What This Framework Is

A lightweight, multi-tenant PHP MVC framework. No Laravel, no Symfony, no Composer dependencies. Everything is hand-rolled and explicit. The design philosophy is: readable over clever, explicit over magic.

**Key characteristics:**
- PSR-4 autoloading, namespace root `App\`
- Multi-tenancy is a first-class citizen — every authenticated route is tenant-scoped
- Middleware pipeline handles auth, tenant resolution, and JSON parsing before the controller is touched
- Models handle DB access only — no business logic, no Clock calls
- Business logic lives exclusively in Services — including all timestamp generation via Clock
- Views use output buffering into a layout file — no templating engine
- A minimal explicit DI container wires services and controllers in `bootstrap.php`

---

## 2. Directory Structure

```
/
├── .env                    # Environment credentials — DB, mail, secrets. Never committed
├── .env.example            # Committed template — documents every required key, no real values
├── bootstrap.php           # Autoloader, .env load, DB init, container bindings, all route definitions — returns $router
├── Config/
│   └── config.php          # Non-secret app options, reads from $_ENV populated by .env — returns array, including app timezone fallback
├── Core/
│   ├── BaseController.php  # view() helper — resolves and renders view files
│   ├── BaseModel.php       # fill(), save(), delete(), find(), all(), where(), hydration
│   ├── Clock.php           # UTC timestamp generation, UTC/local conversion, timezone validation, timezone resolution
│   ├── Container.php       # DI container — singleton(), bind(), make(), has()
│   ├── Database.php        # PDO singleton — connect() and getPdo()
│   ├── Env.php             # .env file loader — populates $_ENV before config.php is required
│   ├── MiddlewareInterface.php  # handle(Request, callable $next)
│   ├── Request.php         # Wraps $_GET, $_POST, $_SERVER, rawBody, bodyParams, user, tenant
│   ├── Response.php        # Static: json(), view(), redirect()
│   └── Router.php          # add(), dispatch(), middleware pipeline builder — accepts Container
├── Controllers/
│   ├── AuthController.php              # Login, logout, forgot/reset password — injects PasswordResetService + UserService
│   ├── HomeController.php              # Dashboard landing page
│   ├── SignupController.php            # Two-step email-verified signup — injects SignupService
│   ├── TenantSettingsController.php    # Tenant settings read/write — admin/owner only
│   ├── AccountSettingsController.php   # Per-user account settings read/write
│   ├── UserManagementController.php    # Admin/owner user management dashboard — injects UserManagementService + config (v1.4)
│   └── Api/V1/                         # API controllers — use apiAuth middleware
├── Helpers/
│   └── media.php           # Application media helper functions — required in bootstrap.php
├── Middleware/
│   ├── AuthMiddleware.php          # Session check, loads $request->user
│   ├── TenantMiddleware.php        # Resolves tenant_id param, loads $request->tenant
│   ├── JsonBodyParserMiddleware.php # Parses raw JSON body into $request->bodyParams
│   └── ApiAuthMiddleware.php       # API key auth — validates api_key_hash, loads $request->user
├── Models/
│   ├── User.php            # Extends BaseModel — DB access only. Covers users, password_reset_tokens, signup_tokens tables. Never calls Clock.
│   └── Tenant.php          # Standalone (does not extend BaseModel) — DB access only. findById, findAll, saveSettings. Never calls Clock.
├── Services/               # Business logic — return ServiceResult objects. Own all Clock calls.
│   ├── Email/
│   │   ├── EmailService.php        # SMTP dispatch via PHPMailer — fromConfig(), send*() convenience methods
│   │   ├── EmailMessage.php        # Value object passed to EmailService::send()
│   │   └── EmailResult.php         # Result object returned by EmailService::send()
│   ├── PasswordResetService.php    # requestPasswordReset(), validateToken(), resetPassword()
│   ├── SignupService.php           # initiateSignup(), completeSignup(), resendCode()
│   ├── UserService.php             # recordLogin(), saveSettings() — framework user business logic
│   ├── TenantService.php           # saveSettings() — framework tenant business logic
│   ├── UserManagementService.php   # getUserDashboard() — admin user dashboard KPIs and user list (v1.4)
│   └── ServiceResult.php           # Standardised result object for all services
├── Views/
│   ├── layouts/
│   │   ├── app-main.php    # Authenticated app shell — topbar, sidebar nav, page-content-area. All authenticated views require this layout.
│   │   ├── authViews.php   # Unauthenticated shell — Bootstrap only, no nav. Used for login, signup, password reset.
│   │   └── legal.php       # Minimal shell for legal/static pages
│   ├── auth/               # login.php, forgot-password.php, forgot-password-sent.php, reset-password.php, reset-password-error.php, signup.php, signup-verify.php
│   ├── home/
│   │   └── dashboard.php   # Default tenant dashboard — replace with application-specific content
│   ├── users/
│   │   └── management-dashboard.php # Admin/owner user management dashboard (v1.4)
│   ├── settings/           # Tenant settings and account settings views
│   ├── modals/             # Reusable modal partial views
│   └── partials/
│       ├── app-header.php  # Topbar HTML — included by app-main.php
│       ├── app-nav.php     # Sidebar nav — included by app-main.php. Add new nav items here.
│       ├── gtm_head.html   # Google Tag Manager head snippet
│       └── gtm_body.html   # Google Tag Manager body snippet
├── sql/
│   └── schema.core.sql     # Auto-executed on first boot if users table missing. Contains seed data.
└── public/
    └── assets/
        ├── css/
        │   ├── app-core.css            # Design tokens, global components: cards, buttons, forms, tabs, modals
        │   ├── app-header.css          # Topbar, logo, dropdown menus, header icon buttons
        │   ├── app-nav.css             # Sidebar navigation layout and states
        │   ├── app-icons.css           # Custom icon font classes (icon-*)
        │   ├── app-pages-settings.css  # Settings vnav, content panels, toggle rows, scope badges, alerts
        │   ├── app-pages-users.css     # User dashboard: .stat-card KPI tiles, .users-table, .role-badge variants (v1.4)
        │   └── authViews.css           # .login-card sizing only
        ├── js/
        │   ├── this-site-nav.js        # Sidebar collapse/expand behaviour
        │   └── sf/                     # Syncfusion field mask helpers
        └── vendor/                     # CDN-vendored libraries (Bootstrap, Bootstrap Icons, Swiper, Syncfusion)
```

---

## 3. Request Lifecycle

```
index.php (entry point)
  └── sets APP_BASE_DIR constant
  └── requires bootstrap.php → gets $router
        └── PSR-4 autoloader registered
        └── Env::load() reads .env → populates $_ENV (must run before config.php)
        └── config.php required → reads from $_ENV
        └── Database::connect() called with config values
        └── Container created → framework services registered as singletons
        └── Router created → receives Container
  └── $router->dispatch(new Request())
        └── matches route pattern
        └── builds middleware pipeline (outermost first, innermost last)
        └── each middleware calls $next($request) to continue
        └── Router checks Container for controller binding before falling back to new $controller()
        └── controller method receives (Request $request, array $params)
        └── returns Response::view() / Response::json() / Response::redirect()
```

**Middleware execution order for a standard tenant route `[$tm, $auth]`:**
1. `TenantMiddleware` — resolves `{tenant_id}` from route params, loads tenant row, sets `$request->tenant`
2. `AuthMiddleware` — checks `$_SESSION['user_id']`, loads user row, validates user belongs to tenant, sets `$request->user`
3. Controller method executes

**If any middleware fails:** it calls `Response::redirect()` or `http_response_code()` and returns without calling `$next` — the pipeline stops.

---

## 4. Routing

All routes are defined in `bootstrap.php`. The router is returned and dispatched from the entry point.

```php
$router->add(METHOD, PATTERN, HANDLER, [MIDDLEWARE]);
```

**Handler formats:**
```php
'ControllerName@method'                    // shorthand — resolves to App\Controllers\ControllerName
'App\\Controllers\\Full\\Path@method'      // fully qualified
```

**Route parameter syntax:** `{param_name}` — captured into `$request->routeParams['param_name']`

**Middleware shorthands** (defined as variables in bootstrap.php):
```php
$tm      = 'TenantMiddleware';
$auth    = 'AuthMiddleware';
$apiAuth = 'ApiAuthMiddleware';
$json    = 'JsonBodyParserMiddleware';
```

**Standard middleware combinations:**
- Public page: `[]`
- Public POST (login): `[$json]`
- Authenticated tenant page: `[$tm, $auth]`
- Authenticated tenant POST: `[$tm, $auth, $json]`
- API endpoint: `[$tm, $apiAuth, $json]`

**Important:** `$json` populates `$request->bodyParams`. Without it, POST body is only available via `$_POST`.

---

## 5. Controllers

Extend `BaseController`. Receive `(Request $request, array $params)`.

```php
namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Request;
use App\Core\Response;

class ExampleController extends BaseController
{
    public function index(Request $request, array $params): mixed
    {
        $tenant = $request->tenant; // set by TenantMiddleware
        $user   = $request->user;   // set by AuthMiddleware

        return $this->view('section/view-name', [
            'title'  => 'Page Title',
            'tenant' => $tenant,
            'user'   => $user,
        ]);
    }
}
```

**Controllers with service dependencies** receive them via constructor injection (wired in `bootstrap.php`). See Section 8.2.

```php
class AuthController extends BaseController
{
    public function __construct(
        private PasswordResetService $passwordResetService,
        private UserService          $userService
    ) {}
}
```

**`$this->view($name, $params)`** resolves to `Views/$name.php`, extracts `$params` into scope, and requires the file. The view file is responsible for buffering its own content and requiring a layout.

**Reading request data:**
```php
$request->bodyParams           // parsed JSON or $_POST (requires $json middleware)
$request->post                 // raw $_POST
$request->get                  // $_GET
$request->routeParams          // URL segment params e.g. ['tenant_id' => '1']
$request->input('key', $default) // checks bodyParams → post → get in order
$request->header('Name')       // request header
$request->tenant               // tenant array (set by TenantMiddleware)
$request->user                 // user array (set by AuthMiddleware)
```

---

## 6. Views and Layouts

Views use PHP output buffering. The pattern is consistent across all views:

```php
<?php
$title = 'Page Title';
ob_start();
?>
<!-- HTML content here — $tenant, $user, and any passed params are in scope -->
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app-main.php'; // or authViews.php
?>
```

**Layouts:**
- `app-main.php` — full authenticated shell. Renders `$content` inside `.page-content-area`. Includes header and nav partials. Expects `$title`, `$tenant`, `$user` in scope.
- `authViews.php` — minimal unauthenticated shell. Renders `$content` only. Used for login, password reset pages.

**Variables available in views** (via `extract()` in `Response::view()`): everything passed in the params array to `$this->view()`.

**View responsibility boundary.** Views output variables; they do not compute them. This is a hard framework rule that applies to every application built on FrankPHP:

- A view **may** branch on a boolean flag that has already been resolved by the Service or Controller (`$canEdit`, `$hasPriorData`, `$canFinish`).
- A view **must not** derive or compute that flag itself.
- A view **may** echo a scalar value (`$prefillReps`, `$statusLabel`).
- A view **must not** select that scalar by choosing between two data sources (`$prior['actual_reps'] ?? $current['target_reps']`).
- Any `??`, ternary, or conditional that decides *which data wins* is business logic. It belongs in the Service. The Service delivers a complete, flat set of named, ready-to-render values; the view echoes them.

A view that resolves priority between data sources must be refactored — this is not an acceptable deviation and must not be recorded in MYAPP.md as one. The correct fix is always to push the resolution into the Service and pass the result as a named scalar.

---

## 7. Models

### The Model SoC Contract

**Models handle DB access only.** This is a hard framework rule introduced in v1.3.2 and applies to every model in the framework and every application model built on top of it.

- A model **must not** call `App\Core\Clock`.
- A model **must not** make business-logic decisions (rate-limit comparisons, hash verification, name derivation, field priority resolution).
- All timestamp values (`now`, `expiresAt`, `cutoff`, `threshold`) are computed by the calling Service via Clock and passed to model methods as plain `string` parameters.
- Model method signatures make this explicit — any method needing a timestamp accepts it as a named `string` parameter.

**The correct pattern:**
```php
// In a Service:
$now = Clock::nowUtcString();
$this->userModel->markSignupTokenUsed($tokenId, $now); // model receives string, never calls Clock

// WRONG — model must not do this:
public function markSignupTokenUsed(int $id): bool {
    // Clock::nowUtcString() here is a SoC violation
}
```

### BaseModel

All application models that map to a single DB table should extend `BaseModel`.

```php
namespace App\Models;
use App\Core\BaseModel;

class Thing extends BaseModel
{
    protected string $table = 'things'; // optional — defaults to lowercase classname + 's'

    // Declare all columns as typed public properties
    public ?int    $id         = null;
    public int     $tenant_id;
    public string  $name;
    public ?string $created_at = null;
}
```

**Key methods:**

| Method | Description |
|--------|-------------|
| `fill(array $data)` | Hydrates properties from array, casting to declared PHP types |
| `save()` | INSERT ... ON DUPLICATE KEY UPDATE — works for both create and update |
| `delete()` | DELETE by primary key, AND tenant_id if property exists |
| `find(int $id, int $tenantId)` | Returns hydrated object or null — tenant-scoped |
| `all(int $tenantId)` | Returns array of hydrated objects — tenant-scoped |
| `where(int $tenantId, string $col, mixed $val, string $op)` | Returns array of hydrated objects |

**Type casting in `fill()`:**
- `int`, `float`, `bool`, `string` — cast directly
- `float` — strips `$` and `,` (currency cleaning)
- `bool` — uses `filter_var` with `FILTER_VALIDATE_BOOLEAN`
- `DateTimeImmutable` / `DateTime` — constructed from value string
- `array` — parsed from JSON string or comma-separated string
- Nullable properties receiving `""`, `null`, or `"-1"` are set to `null`

**`save()` excludes** `id`, `created_at`, `updated_at`, `tenant_id` from the UPDATE clause (they are included in the INSERT).

### Tenant Model

`Tenant` does **not** extend `BaseModel`. It is a standalone class with its own PDO instance.

Key methods: `findById(int $id)`, `findAll()`, `saveSettings(int $tenantId, array $data, string $updatedAt)`.

`saveSettings()` has an internal allowlist of writable columns — only those columns can be updated via settings forms. The `$updatedAt` UTC string is computed by the calling Service and passed in — `Tenant` never calls Clock.

---

## 8. Services and ServiceResult

Business logic that is more than a single DB call lives in a Service class in `App\Services\`.

Services return a `ServiceResult` object.

**Services own all Clock calls.** Timestamp strings produced by Clock are passed down to model methods as plain parameters. Models never call Clock.

**ServiceResult interface:**
```php
$result->success          // bool
$result->message          // string — error or info message
$result->data             // array — full result payload
$result->get('key')       // retrieve a named value from the result payload
$result->get('key', $default)
$result->isValidationError()  // bool
$result->isRateLimitError()   // bool
$result->isSystemError()      // bool
```

**Controller pattern — delegate, don't decide:**
```php
// Services are injected — never constructed inside controller methods
$result = $this->myService->doSomething($input);

if ($result->success) {
    Response::redirect('/somewhere');
}

return $this->view('some/view', ['error' => $result->message]);
```

---

### 8.1 EmailService

`App\Services\Email\EmailService` is the single SMTP gateway for the entire application. It is always constructed via the DI container using `EmailService::fromConfig($config['mail'])` — credentials come from `.env`, never from call sites.

**Constructor:** Do not call directly. Use `fromConfig()` or the container.

**Convenience send methods (add new ones here as the app grows):**

| Method | Purpose |
|--------|---------|
| `sendPasswordReset(email, name, resetUrl)` | Password reset flow |
| `sendUserInvite(email, name, inviteUrl)` | User invitation |
| `sendNotification(email, name, subject, body)` | Generic notification |
| `sendSignupVerification(email, code, expiryMinutes)` | Signup email verification |
| `sendPlatformOwnerSignupAlert(triggeringEmail, event)` | Alerts the platform owner (`MAIL_SITE_ADMIN`) on a signup-related event — both a verification code being requested and an account being successfully created |

**Adding a new email type:** add a `send*()` method to `EmailService` that builds an `EmailMessage` internally and calls `$this->send()`. The call site (service or controller) passes only business data — never credentials, `from`, or SMTP details.

**`EmailMessage`** — value object: `to`, `toName`, `subject`, `htmlBody`, `textBody`, `from`, `fromName`, `replyTo`, `credentialsUserName`, `credentialsUserSecret`.

**`EmailResult`** — result object: `success` (bool), `message`, `emailId`, `error`. Static constructors: `EmailResult::success()`, `EmailResult::failure($error)`.

---

### 8.2 Dependency Injection Container

`App\Core\Container` is a minimal explicit DI container. No reflection, no autowiring. Every binding is a PHP closure registered in `bootstrap.php`.

**API:**

| Method | Behaviour |
|--------|-----------|
| `singleton(id, callable)` | Factory is called once; result is cached and reused on every `make()` |
| `bind(id, callable)` | Factory is called fresh on every `make()` — for per-request objects (rare) |
| `make(id)` | Resolve by id. Throws `RuntimeException` if no binding registered |
| `has(id)` | Returns bool — used by Router before falling back to `new $controller()` |

**The factory callable always receives the container as its argument**, so services that depend on other services call `$c->make(OtherService::class)` rather than nesting construction.

**What is registered in the container:**

| Id (class string) | Type | Why |
|---|---|---|
| `EmailService::class` | singleton | Config-driven SMTP credentials; shared across all services |
| `PasswordResetService::class` | singleton | Depends on EmailService |
| `SignupService::class` | singleton | Depends on User model + EmailService |
| `UserService::class` | singleton | Framework user business logic — recordLogin, saveSettings |
| `TenantService::class` | singleton | Framework tenant business logic — saveSettings allowlist and null-coercion |
| `UserManagementService::class` | singleton | Admin user dashboard data service — introduced v1.4 |
| `AuthController::class` | bind | Needs PasswordResetService + UserService injected |
| `SignupController::class` | bind | Needs SignupService injected |
| `UserManagementController::class` | bind | Needs UserManagementService + $config injected — introduced v1.4 |

Controllers are registered with `bind()` (not `singleton()`) because a fresh controller instance per request is the correct behaviour.

**What is NOT in the container:**
- Models — lightweight, `new Model()` in service constructors is fine
- Middleware — constructed by the Router pipeline; no injected dependencies
- `Request` / `Response` — per-request value objects
- `Clock` — stateless static core utility loaded by PSR-4, no binding required
- Controllers with no constructor dependencies — Router falls back to `new $controller()`

**Router integration:** `Router::__construct(?Container $container)`. On handler resolution, the Router calls `$container->has($controllerClass)` first. If a binding exists, it uses `$container->make()`. Otherwise it uses `new $controller()`. This means controllers without constructor dependencies require zero changes.

**Registering a new service (the full pattern):**

```php
// In bootstrap.php — after the existing singleton registrations

// 1. Register the service
$container->singleton(\App\Services\BillingService::class, function ($c) use ($config) {
    return new \App\Services\BillingService(
        $c->make(\App\Services\Email\EmailService::class),
        \App\Core\Database::getPdo(),
        $config['billing']         // any config section the service needs
    );
});

// 2. Register the controller that depends on it (only if it has constructor dependencies)
$container->bind(\App\Controllers\BillingController::class, function ($c) {
    return new \App\Controllers\BillingController(
        $c->make(\App\Services\BillingService::class)
    );
});
```

**Registering a new email type** (the companion pattern to the above):
```php
// In EmailService.php — add a send*() method
public function sendInvoice(string $email, string $name, string $invoiceUrl): EmailResult
{
    $htmlBody = InvoiceEmailTemplate::buildHtml($name, $invoiceUrl);
    return $this->send(new EmailMessage(
        credentialsUserName:   $this->username,
        credentialsUserSecret: $this->password,
        to:      $email,
        subject: 'Your Invoice',
        htmlBody: $htmlBody,
        // ... standard fields from $this->*
    ));
}
```

---

## 9. Database

`Database::connect(array $config)` creates the PDO singleton. Called once in `bootstrap.php`. The config array is built from `$_ENV` values populated by `Env::load()` — credentials live in `.env`, not in `config.php` directly.

`Database::getPdo()` returns the singleton — called by `BaseModel::__construct()` and standalone models.

**On first boot:** if the `users` table does not exist, `schema.core.sql` is executed automatically via `Database::runSchemaFileIfMissingTable()` and seed data is inserted (one tenant, two seed users with password `password`).

**PDO settings:** `ERRMODE_EXCEPTION`, `FETCH_ASSOC`.

All queries use prepared statements. Multi-statement mode is enabled only during schema import, then disabled.

**Date/time storage rule:** persisted application timestamps must be generated in UTC by application code using `App\Core\Clock`. Do not use SQL `NOW()` / `CURRENT_TIMESTAMP` for framework-managed or application-managed timestamps unless a specific deviation is documented.

---

## 10. Date and Time Handling

FrankPHP v1.3.0 introduces `App\Core\Clock` as the framework-owned date/time utility.

The framework rule is:

> UTC at rest. Local at the edges.

This means:
- Persisted `DATETIME` values are stored as UTC unless a column explicitly documents otherwise.
- User, tenant, and application timezones are used to interpret inputs, prepare outputs, and calculate query boundaries.
- Timezone preferences are never stored into timestamp columns.
- Services prepare display-ready date/time values for views.
- Views must not perform timezone conversion, timestamp comparison, date fallback logic, or relative date labelling.

### 10.1 `App\Core\Clock`

`Clock` lives at:

```text
Core/Clock.php
```

Namespace:

```php
App\Core\Clock
```

It is loaded by the existing PSR-4 autoloader and does not require a container binding while it remains a stateless static framework utility.

`Clock` owns:
- UTC timestamp generation
- UTC offset calculation
- UTC/local conversion
- Local input parsing into UTC storage values
- UTC storage value formatting into local display values
- Timezone validation
- Timezone resolution from explicit user, tenant, and config arrays
- UTC query boundary calculation from local dates

`Clock` must not read from:
- `$_SESSION`
- `$_SERVER`
- global request state
- the database
- middleware state
- controller globals

Callers must pass explicit context.

### 10.2 Timezone Resolution

Resolved timezone order:

```text
user.timezone
→ tenant.timezone
→ config['timezone']
→ UTC
```

`Clock::resolveTimezone($user, $tenant, $config)` formalises this rule.

Supported sources:
- `users.timezone`
- `users.settings` JSON containing `timezone`
- `tenants.timezone` if present in an application/framework version
- `tenants.settings` JSON containing `timezone`
- `config['timezone']`, normally populated from `APP_TIMEZONE`
- fallback `UTC`

If a timezone value is missing, empty, or invalid, the next candidate is tried. If all candidates fail, UTC is used.

### 10.3 Storage Rules

All framework and application timestamp writes should use `Clock` **in the Service layer**:

```php
use App\Core\Clock;

$createdAt = Clock::nowUtcString();
$expiresAt = Clock::utcOffsetString('+1 hour');
$recentCutoff = Clock::utcOffsetString('-30 days');
```

Do not use this for persisted application timestamps:

```php
date('Y-m-d H:i:s');
time();
new DateTime('now');
```

Do not use this in SQL for framework-managed or application-managed timestamps:

```sql
NOW()
CURRENT_TIMESTAMP
```

Prefer bound UTC parameters:

```php
$cutoff = Clock::utcOffsetString('-30 days');

$stmt = $pdo->prepare('
    SELECT *
    FROM users
    WHERE tenant_id = :tenant_id
      AND last_seen_at >= :cutoff
');

$stmt->execute([
    'tenant_id' => $tenantId,
    'cutoff' => $cutoff,
]);
```

### 10.4 Input Rules

User-entered date/time values are interpreted in the resolved local timezone and converted to UTC before storage.

```php
$timezone = Clock::resolveTimezone($request->user, $request->tenant, $config);

$startsAtUtc = Clock::localInputToUtcString(
    $request->input('starts_at'),
    $timezone,
    'Y-m-d H:i'
);
```

Date-only values with no time-of-day meaning must use `DATE`, not `DATETIME`.

Examples:
- birthday → `DATE`
- local calendar-only due date → `DATE`
- event starts at an exact instant → UTC `DATETIME`
- password reset expiry → UTC `DATETIME`

### 10.5 Output Rules

Stored UTC values are converted to the resolved local timezone before display.

```php
$timezone = Clock::resolveTimezone($user, $tenant, $config);

$lastSeenLabel = $row['last_seen_at']
    ? Clock::formatUtcForTimezone($row['last_seen_at'], $timezone, 'j M Y, H:i')
    : 'Never seen';
```

`Clock` may perform mechanical formatting. Product-specific labels such as `Never`, `Today`, `Yesterday`, `Recently active`, `Inactive`, or `Dormant` belong in Services because they are business/display semantics.

Views receive already-prepared values such as:

```php
'lastSeenLabel' => '6 Jun 2026, 14:30'
```

Views must not call `Clock`.

### 10.6 Query Boundary Rules

Local date filters must be converted into UTC boundaries before querying.

```php
$timezone = Clock::resolveTimezone($user, $tenant, $config);

$range = Clock::utcRangeForLocalDate('2026-06-06', $timezone);

$stmt = $pdo->prepare('
    SELECT *
    FROM users
    WHERE tenant_id = :tenant_id
      AND last_seen_at BETWEEN :start_utc AND :end_utc
');

$stmt->execute([
    'tenant_id' => $tenantId,
    'start_utc' => $range['start'],
    'end_utc' => $range['end'],
]);
```

Relative filters such as "last 30 days" must also calculate UTC boundaries in PHP through `Clock`.

### 10.7 MySQL `TIMESTAMP` Migration Rule

FrankPHP prefers UTC `DATETIME` for persisted application timestamps.

Existing applications using MySQL `TIMESTAMP` columns should not blindly convert every date-like field. First classify the column:

| Meaning | Preferred type |
|---------|----------------|
| Exact instant in time | UTC `DATETIME` |
| Expiry timestamp | UTC `DATETIME` |
| Login/activity timestamp | UTC `DATETIME` |
| External provider timestamp | Normalised UTC `DATETIME`, optional raw payload separately |
| Date-only value | `DATE` |

When converting existing MySQL `TIMESTAMP` columns to `DATETIME`, run the migration session in UTC:

```sql
SET time_zone = '+00:00';

ALTER TABLE example_table
  MODIFY created_at DATETIME NOT NULL,
  MODIFY updated_at DATETIME NULL;
```

If historical values were stored in local wall-clock time by mistake, a deliberate data correction step is required before or during migration. Do not assume the historical timezone.

### 10.8 Build Rules for Date/Time Code

When building or reviewing FrankPHP code:

- Use `App\Core\Clock` for persisted timestamps — in Services only.
- Models must never call Clock. Timestamps arrive as string parameters.
- Use UTC `DATETIME` for exact instants.
- Use `DATE` for date-only values.
- Do not use SQL `NOW()` / `CURRENT_TIMESTAMP` for application-managed timestamps.
- Do not perform timezone conversion in Views.
- Do not calculate "today", "last 30 days", or local date ranges directly in SQL.
- Resolve timezone explicitly with `Clock::resolveTimezone($user, $tenant, $config)`.
- Pass display-ready date labels from Services to Views.
- Document any intentional deviation in `MYAPP.md` for application code, or in `CHANGELOG.md` / `CODEBASE.md` for framework code.

---

## 11. Authentication and Multi-tenancy

**Session:** PHP native sessions. `$_SESSION['user_id']` is the only session value the framework sets.

**AuthMiddleware** on every protected route:
1. Starts session if not started
2. Redirects to `/login?next={current_url}` if `user_id` not in session
3. Loads user via `User::findById()`
4. If route has `{tenant_id}` param, verifies `user.tenant_id === route tenant_id` — returns 403 if mismatch
5. Sets `$request->user` (array)

**TenantMiddleware** resolves before AuthMiddleware in the stack:
1. Reads `{tenant_id}` from route params — redirects to `/logout` if missing
2. Loads tenant via `Tenant::findById()` — returns 404 if not found
3. Sets `$request->tenant` (array)

**Login flow:** `AuthController@login` → verifies email + `password_verify()` → sets `$_SESSION['user_id']` → calls `UserService::recordLogin()` which records the UTC login timestamp via Clock → redirects to `/tenant/{tenant_id}/dashboard`.

**Signup flow (two-step):**
1. `POST /signup` → `SignupController@initiateSignup` → validates, creates pending record, sends verification code via `EmailService::sendSignupVerification()`, stores `signup_email` in session
2. `POST /signup/verify` → `SignupController@completeSignup` → verifies code, creates user + tenant, logs user in

**Role checking** is done in controllers, not middleware. Pattern used in `TenantSettingsController`:
```php
if (!in_array($user['role'] ?? '', ['admin', 'owner'], true)) {
    return Response::redirect('/tenant/{id}/somewhere?error=forbidden');
}
```

---

## 12. CSS and Frontend Conventions

**Never write inline styles. Never create single-use CSS classes.**

**Decision order when styling anything:**
1. Standard Bootstrap 5 utility class
2. Existing class in `app-core.css`
3. Existing class in the relevant feature CSS file
4. Add a new generic reusable class to the appropriate CSS file

**CSS file responsibilities:**

| File | Scope |
|------|-------|
| `app-core.css` | Design tokens (`--brand-primary`, `--grey-*` scale), global components: cards, buttons, forms, tabs, task components, modals |
| `app-header.css` | Topbar, logo, dropdown menus, header icon buttons |
| `app-pages-settings.css` | Settings vnav, content panels, toggle rows, scope badges, settings alerts |
| `app-pages-users.css` | User management dashboard: `.stat-card` KPI tiles, `.users-table`, `.role-badge` variants. Introduced v1.4. |
| `authViews.css` | `.login-card` sizing only |

**Design tokens (defined in `:root` in `app-core.css`):**
```css
--brand-primary        /* #2B3A67 — dark navy */
--brand-primary-dark   /* #2CB7B1 — teal */
--brand-primary-light  /* #F7C700 — yellow */
--brand-primary-glass  /* 10% opacity of --brand-primary */
--color-success        /* #10B981 */
--color-warning        /* #F59E0B */
--color-danger         /* #EF4444 */
--color-danger-dark    /* #B91C1C */
--color-danger-light   /* #FEF2F2 */
--grey-50 through --grey-900  /* 10-step neutral scale */
--app-bg               /* var(--grey-50) — page background */
--sidebar-bg           /* var(--grey-100) */
--text-main            /* var(--grey-700) */
--text-heading         /* var(--grey-900) */
--border-default       /* var(--grey-200) */
--sidebar-width        /* 260px */
--sidebar-collapsed    /* 80px */
--topbar-height        /* 56px */
```

**Button classes** (from `app-core.css`): `.btn-brand-primary`, `.btn-outline-secondary`, `.btn-outline-primary`, `.btn-outline-danger`, `.btn-danger`, `.btn-circular`

**Card pattern:**
```html
<div class="card">
    <div class="card-header-modern">
        <h5>Title</h5>
        <button class="btn btn-brand-primary">Action</button>
    </div>
    <div class="card-body p-3">
        <!-- content -->
    </div>
</div>
```

**Settings page pattern:** `.settings-vnav` + `.settings-panel` + `.settings-group` + `.settings-toggle-row` — see `app-pages-settings.css`.

**External dependencies loaded via CDN:**
- Bootstrap 5.3.2 (CSS + JS bundle)
- Bootstrap Icons 1.10.5
- Syncfusion EJ2 32.1.19 (Bootstrap5 theme + ej2.min.js)

---

## 13. Security Notes

- `.env` file sits outside the public web root (`/public`) — credentials cannot be requested directly by a browser
- All DB queries use PDO prepared statements
- `password_hash()` / `password_verify()` for passwords
- `htmlspecialchars()` on all user-supplied output in views
- Tenant isolation enforced at middleware level (route param vs session user) and at model level (tenant_id in all queries)
- `saveSettings()` methods in both `User` and `Tenant` maintain explicit column allowlists — arbitrary columns cannot be written via settings forms
- Multi-statement PDO mode is disabled after schema import
- SMTP credentials live exclusively in `.env` and flow through `config['mail']` → `EmailService::fromConfig()` → the container singleton. They are never present in any service or controller file.
- Persisted timestamps are stored as UTC `DATETIME` values and generated through `App\Core\Clock` in the Service layer; models receive pre-computed UTC strings as parameters and never call Clock directly.

---

## 14. What The Framework Deliberately Does Not Include

- No ORM — queries are hand-written SQL via PDO
- No template engine — plain PHP views
- No event system
- No queue system
- No built-in CSRF protection — <!-- add if/when implemented -->
- No built-in rate limiting — <!-- add if/when implemented -->
- No Composer — zero external dependencies
- No external date/time library — UTC/local handling is provided by `App\Core\Clock` using native PHP date/time classes

---

---

## 15. AI Context Architecture — How To Work With This Project

> **This section is addressed to the AI agent or LLM working on this codebase.**
> It is not application documentation. It is an instruction set.

---

### 15.1 The Two-File Contract

Every FrankPHP project uses two authoritative context files:

| File | Owner | Purpose |
|------|-------|---------|
| `CODEBASE.md` | FrankPHP framework | Immutable framework contract — routing, middleware, patterns, conventions, deliberate omissions. Do not modify this file when building application features. |
| `MYAPP.md` | Application layer | Mutable application record — every route, model, service, table, business rule, and deviation added by this specific application. Must be kept current. |

You are reading CODEBASE.md. It describes the framework. Everything specific to the application built on top of it is in MYAPP.md. Both files must be attached at the start of every session.

---

### 15.2 Framework-Owned vs Application-Owned

Before writing or modifying any code, classify the work:

**Framework-owned (described in CODEBASE.md, Section 16):**
- `tenants`, `users`, `password_reset_tokens` tables and any future framework auth tables
- All models in `Models/User.php`, `Models/Tenant.php`
- All controllers in `Controllers/AuthController.php`, `Controllers/SignupController.php`, `Controllers/TenantSettingsController.php`, `Controllers/AccountSettingsController.php`, `Controllers/UserController.php`, `Controllers/UserManagementController.php`
- All framework default routes (login, logout, password reset, signup, dashboard, settings)
- The middleware stack (`AuthMiddleware`, `TenantMiddleware`, `JsonBodyParserMiddleware`, `ApiAuthMiddleware`)
- `Core/Clock.php`, `Core/Container.php`, and the service bindings registered in `bootstrap.php` for framework services
- `Services/UserService.php` — framework service for user business logic
- `Services/TenantService.php` — framework service for tenant business logic
- `Services/UserManagementService.php` — framework service for the admin user management dashboard (v1.4)

**Do not modify framework-owned files when building application features.** If a feature appears to require a change to a framework-owned file, stop and flag this to the developer before proceeding. Framework changes require a deliberate upgrade decision — they are not routine feature work.

**Application-owned (described in MYAPP.md):**
- Every table the application adds beyond the framework schema
- Every controller, model, service, and view the application adds
- Every route the application registers
- Application service bindings added to the container in `bootstrap.php`
- All business rules, role logic, and domain conventions
- Any intentional deviations from framework patterns

---

### 15.3 The Mandatory Documentation Rule

**Building a feature and updating documentation are a single deliverable. They are never separate.**

#### Application feature work → update MYAPP.md

When you add or modify application-owned code, MYAPP.md must be updated in the same response or commit:

| What you built | What you update in MYAPP.md |
|---------------|----------------------------|
| New database table | Add to Data Model section |
| New controller or controller method | Add to Route Inventory |
| New model with custom methods | Add to Models section |
| New service with public methods | Add to Services section |
| New container binding | Add to Services section (note singleton vs bind) |
| New email send method on EmailService | Add to Services section |
| New business rule discovered or enforced | Add to Business Rules section |
| Intentional deviation from a framework pattern | Add to Known Deviations section |
| New CSS file or new reusable class | Add to CSS Conventions section |
| Change to an existing route | Update Route Inventory |
| Change to an existing service method signature | Update Services section |

#### Framework work → update CODEBASE.md AND CHANGELOG.md

When you add or modify framework-owned code (anything listed in §15.2 as framework-owned), both files must be updated in the same response or commit:

| What you changed | What you update in CODEBASE.md | What you update in CHANGELOG.md |
|-----------------|-------------------------------|--------------------------------|
| New framework controller | §2 directory tree, §15.2 owned list, §16.4 route table | Added section |
| New framework service | §2 directory tree, §8.2 container table, §15.2 owned list, §16.5 service table | Added section |
| New framework model method | §16.2 model method list | Changed section |
| New/changed framework route | §16.4 route table | Added or Changed section |
| New CSS file | §2 directory tree, §12 CSS file table | Added section |
| Changed design token | §12 design token block | Changed section |
| Changed framework DB table | §16.1 table schema | Changed section |
| New framework DB table | §2 sql/ note, §16.1 new table | Added section |
| Changed container binding | §8.2 container table | Changed section |
| Framework version bump | Header version field | New version heading |

Partial updates are not acceptable. If you cannot update both files in the same pass, say so explicitly and provide the exact patches to be applied.

---

### 15.4 Development Mode Behaviour

Adapt your documentation output to the mode you are operating in:

**Chat LLM mode** (developer is pasting files into a chat interface)
- Produce all code as copy-pasteable blocks
- At the end of every response, produce a clearly labelled `MYAPP.md PATCH` block containing the exact lines to add, replace, or remove, with enough surrounding context that the developer can locate the correct position without ambiguity
- Do not assume the developer will infer what needs updating — state it explicitly

**Agentic mode** (Claude Code or equivalent — you have file system access)
- Apply MYAPP.md updates directly as part of the same task
- Do not treat documentation as a follow-up step
- Confirm in your summary which sections of MYAPP.md were modified and what changed

**Manual mode** (a human developer made changes without AI involvement)
- MYAPP.md is their responsibility to update
- Section structure and field meanings are defined in MYAPP.md itself
- The Getting Started docs explain the update workflow for human developers

---

### 15.5 What MYAPP.md Does Not Contain

Do not add the following to MYAPP.md — they belong in CODEBASE.md and are already documented there:

- Framework table schemas (`tenants`, `users`, `password_reset_tokens`)
- Framework default routes (auth, settings, dashboard, signup)
- Framework middleware descriptions
- BaseModel, BaseController, Container, or core class behaviour
- General framework conventions (these apply globally and are not re-stated per application)
- EmailService convenience methods for framework flows (password reset, signup verification, advisor)

If a feature depends on a framework-provided table or route, reference it by name in MYAPP.md where relevant (e.g. "foreign key to `users.id`") but do not re-document the framework schema.

---

### 15.6 Accuracy Is More Important Than Speed

If MYAPP.md is missing information you need to answer a question or build a feature correctly — say so before guessing. Ask the developer for the missing context. A wrong assumption that generates plausible-looking but incorrect code creates technical debt that is harder to fix than a brief clarification exchange.

MYAPP.md exists precisely to eliminate guessing. If it is incomplete, the right response is to flag the gap, not to fill it with inference.

---

## 16. Framework Tables, Schema, and Default Routes

> **This section is addressed to the AI agent or LLM.**
> These tables, models, and routes are owned by the FrankPHP framework. They are documented here so you understand what exists and how it behaves. You should not modify these as part of application feature work. If a feature appears to require changes here, flag it.

---

### 16.1 Framework Database Tables

#### `tenants`

| Column | Type | Notes |
|--------|------|-------|
| id | int PK AUTO_INCREMENT | |
| name | varchar(150) NOT NULL | Display name of the tenant |
| slug | varchar(150) NOT NULL UNIQUE | URL-safe identifier |
| company_email | varchar(255) | nullable |
| timezone | varchar(100) | nullable — used in Clock::resolveTimezone() fallback chain |
| date_format | varchar(50) | nullable |
| created_at | datetime NOT NULL | UTC — DEFAULT (UTC_TIMESTAMP()) as safety fallback |
| updated_at | datetime | nullable UTC |

#### `users`

| Column | Type | Notes |
|--------|------|-------|
| id | int PK AUTO_INCREMENT | |
| tenant_id | int NOT NULL FK | tenants.id — CASCADE DELETE |
| email | varchar(255) NOT NULL | unique per tenant (uq_users_tenant_email) |
| name | varchar(255) NOT NULL | |
| password_hash | varchar(255) | nullable |
| role | varchar(50) NOT NULL | `owner`, `admin`, `user` — default `user` |
| api_key_hash | char(64) | nullable — hashed API key for apiAuth middleware |
| timezone | varchar(100) | nullable — inherits from tenant if null |
| created_at | datetime NOT NULL | UTC |
| updated_at | datetime | nullable UTC |
| accessed_at | datetime | nullable UTC — last successful login, written by UserService::recordLogin() |

#### `password_reset_tokens`

| Column | Type | Notes |
|--------|------|-------|
| id | int PK AUTO_INCREMENT | |
| user_id | int NOT NULL FK | users.id — CASCADE DELETE |
| tenant_id | int NOT NULL FK | tenants.id — CASCADE DELETE |
| token_hash | varchar(255) NOT NULL | hashed reset token |
| created_at | datetime NOT NULL | UTC |
| expires_at | datetime NOT NULL | UTC |
| used_at | datetime | nullable UTC — set when token is consumed or invalidated |

#### `signup_tokens`

| Column | Type | Notes |
|--------|------|-------|
| id | int PK AUTO_INCREMENT | |
| email | varchar(255) NOT NULL | email address attempting signup |
| code_hash | varchar(255) NOT NULL | hashed verification code |
| password_hash | varchar(255) NOT NULL | pre-hashed password held until signup completes |
| tenant_id | int FK | nullable — NULL until signup completes; CASCADE DELETE |
| expires_at | datetime NOT NULL | UTC |
| used_at | datetime | nullable UTC — set by markSignupTokenUsed() or invalidatePendingSignupTokens() |
| created_at | datetime NOT NULL | UTC |
| ip_address | varchar(45) | nullable — for rate limiting in SignupService |
| user_agent | varchar(512) | nullable |

> **Note:** `signup_tokens.tenant_id` is NULL for the entire lifetime of an unverified token because no tenant exists until signup completes. This makes per-tenant scoping of unverified signup tokens structurally impossible — any query on this table that needs tenant context must join through `users` after completion.

**Schema initialisation:** If the `users` table does not exist on first boot, `sql/schema.core.sql` is executed automatically and seed data is inserted (one tenant, two seed users with password `password`). Change seed passwords before sharing any URL.

---

### 16.2 Framework Models

| Model | File | Notes |
|-------|------|-------|
| `User` | `Models/User.php` | Extends BaseModel. DB access only — never calls Clock. Methods accept pre-computed UTC timestamp strings from Services. Public methods: `findById()`, `findByEmail()`, `findApiKeyHash(hash)`, `allByTenant(tenantId)` *(v1.4)*, `updateLastAccessed(userId, tenantId, accessedAt)`, `saveSettings(userId, tenantId, data, updatedAt)`, `updatePasswordHash(userId, hash, updatedAt)`, `insertUser(...)`, `insertPasswordResetToken(...)`, `findValidPasswordResetTokens(now)`, `markPasswordResetTokenAsUsed(id, usedAt)`, `invalidateAllUserPasswordResetTokens(userId, usedAt)`, `countRecentPasswordResetTokens(userId, threshold)`, `deleteExpiredPasswordResetTokens(cutoff)`, `insertSignupToken(...)`, `invalidatePendingSignupTokens(email, usedAt)`, `markSignupTokenUsed(id, usedAt)`, `findPendingSignupTokens(email, now)`, `findMostRecentPendingSignupToken(email, now)`, `countRecentSignupTokensByIp(ip, threshold)`, `deleteExpiredSignupTokens(cutoff)`. |
| `Tenant` | `Models/Tenant.php` | Does **not** extend BaseModel. Standalone PDO class. DB access only — never calls Clock, applies no allowlist. Methods: `findById()`, `findAll()`, `saveSettings(tenantId, columns, updatedAt)`. `$columns` is a pre-validated map from `TenantService`; `$updatedAt` is a UTC string from `TenantService`. |

---

### 16.3 Framework Core Utilities

| Utility | File | Notes |
|---------|------|-------|
| `Clock` | `Core/Clock.php` | Stateless static date/time utility introduced in v1.3.0. Owns UTC timestamp generation, UTC/local conversion, timezone validation, timezone resolution, and UTC query boundary calculation. Loaded by PSR-4; no container binding required. Called from Services only — never from Models, Controllers, or Views. |

---

### 16.4 Framework Default Routes

These routes are registered in `bootstrap.php` and are part of the framework. Do not re-register them or modify their handlers as part of application feature work.

| Method | Pattern | Controller@method | Middleware |
|--------|---------|-------------------|------------|
| GET | /login | AuthController@showLogin | none |
| POST | /login | AuthController@login | json |
| GET | /logout | AuthController@logout | none |
| GET | /forgot-password | AuthController@showForgotPassword | none |
| POST | /forgot-password | AuthController@sendPasswordReset | none |
| GET | /reset-password | AuthController@showResetPassword | none |
| POST | /reset-password | AuthController@resetPassword | none |
| GET | /signup | SignupController@showSignup | none |
| POST | /signup | SignupController@initiateSignup | json |
| GET | /signup/verify | SignupController@showVerify | none |
| POST | /signup/verify | SignupController@completeSignup | json |
| POST | /signup/resend | SignupController@resendCode | json |
| GET | /tenant/{tenant_id}/dashboard | HomeController@dashboard | tm, auth |
| GET | /tenant/{tenant_id}/tenant-settings | TenantSettingsController@index | tm, auth |
| POST | /tenant/{tenant_id}/tenant-settings/save | TenantSettingsController@save | tm, auth, json |
| GET | /tenant/{tenant_id}/account | AccountSettingsController@index | tm, auth |
| POST | /tenant/{tenant_id}/account/save | AccountSettingsController@save | tm, auth, json |
| GET | /me | UserController@me | auth |
| GET | /tenant/{tenant_id}/users | UserManagementController@index | tm, auth |

---

### 16.5 Framework Services

These services are framework-owned and registered in the container by `bootstrap.php`. Do not modify them as part of application feature work.

| Service | Container type | Dependencies | Notes |
|---------|---------------|--------------|-------|
| `EmailService` | singleton | `$config['mail']` | SMTP gateway. All application email flows call `EmailService` send methods — never PHPMailer directly |
| `PasswordResetService` | singleton | `User` model, `EmailService` | Password reset token lifecycle. Owns Clock calls for all reset token timestamps. |
| `SignupService` | singleton | `User` model, `EmailService` | Two-step email-verified signup. Owns Clock calls for all signup token timestamps. Delegates all DB access to User model — no raw PDO. |
| `UserService` | singleton | `User` model | Introduced v1.3.2. Owns business logic for the user record: `recordLogin(userId, tenantId)`, `saveSettings(userId, tenantId, data)`. Owns allowlist and null-coercion for user preferences. Computes timestamps via Clock and passes them to the User model. |
| `TenantService` | singleton | `Tenant` model | Introduced v1.3.2. Owns business logic for the tenant record: `saveSettings(tenantId, data)`. Owns allowlist and null-coercion for tenant settings. Computes timestamps via Clock and passes them to the Tenant model. |
| `UserManagementService` | singleton | `User` model | Introduced v1.4. Provides the full data payload for the admin/owner user management dashboard: `getUserDashboard(tenantId, user, tenant, config)`. Owns all Clock calls for the feature (timezone resolution, 30-day inactivity cutoff via `Clock::utcOffsetString`, display-value formatting). All four KPI stats (`totalUsers`, `activePercent`, `neverLoggedIn`, `inactiveThirtyDays`) are derived from the single `allByTenant()` query — no additional DB round-trips. |

---

### 16.6 Framework Future Changes

FrankPHP may add or modify framework-owned tables, models, and routes in future versions (for example, adding OAuth2 or SSO support). When this happens:

- CODEBASE.md Section 16 will be updated to reflect the new schema
- A migration note will be included in the release
- MYAPP.md will not be affected unless the application has deviated from framework defaults (which should already be recorded in the MYAPP.md Known Deviations section)

This separation is what makes framework upgrades safe — application-layer documentation and framework-layer documentation do not overlap.
