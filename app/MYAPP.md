# MYAPP.md
> Attach alongside CODEBASE.md at the start of every session.
> This file describes only the application layer. Framework conventions, framework tables, and framework routes are documented in CODEBASE.md and are not repeated here.
> Last updated: [date] by [person or agent]

---

## Application Overview

**Name:** [App Name]
**Type:** [e.g. Multi-tenant SaaS / Internal tool / Client portal]
**Interfaces:** [e.g. Web frontend (PHP views) + REST API]
**Namespace root:** `App\` — PSR-4, consistent with framework
**Status:** [e.g. Active development / Production]

### What it does
[2–4 sentences describing what the application does at a domain level. Written for an AI agent that has no prior context — be specific about the domain, not just the technology.]

---

## Roles

The framework provides `role` on the `users` table with values `platform`, `owner`, `admin`, `user`. From FrankPHP 2.1, a self-service signup creates its own tenant and that user is its `owner` (CODEBASE.md §16.9). If this app places signups differently, record the `SignupTenantResolver` override under Known Deviations.

This app uses roles as follows:

| Role | Permissions |
|------|-------------|
| `owner` | [Describe what an owner can do in this application. By default, the person who signed up and created the tenant] |
| `admin` | [Same as owner, or describe any difference] |
| `user` | [Describe what a standard user can do] |

Role checks follow the framework pattern — done in controllers, not middleware:
```php
if (!in_array($user['role'] ?? '', ['admin', 'owner'], true)) {
    return Response::redirect('/tenant/' . $tenant['id'] . '/dashboard?error=forbidden');
}
```

---

## Directory Tree

> List only additions beyond the framework defaults. Framework-provided files are documented in CODEBASE.md §2.

```
Controllers/
└── [Add application controllers here]

Controllers/Api/V1/
└── [Add API controllers here]

Models/
└── [Add application models here]

Services/
└── [Add application services here]

Views/
└── [Add application view directories and files here]

assets/css/
└── [Add application CSS files here]
```

---

## Data Model

> Framework tables (`tenants`, `users`, `password_reset_tokens`) are documented in CODEBASE.md §15. Do not repeat them here.
> Add one entry per application-specific table.

### `[table_name]`

| Column | Type | Notes |
|--------|------|-------|
| id | int PK | |
| tenant_id | int FK | tenants.id |
| [column] | [type] | [notes] |
| created_at | datetime | |
| updated_at | datetime | |

---

## Route Inventory

> Framework default routes are documented in CODEBASE.md §15. Do not repeat them here.
> List only routes registered by this application. Keep in sync with `bootstrap.php`.

### Web Routes

| Method | Pattern | Controller@method | Middleware |
|--------|---------|-------------------|------------|
| [METHOD] | /tenant/{tenant_id}/[path] | [Controller@method] | [tm, auth] |

### API Routes

> Base path: `/api/v1/tenant/{tenant_id}/`
> All API routes use `[$tm, $apiAuth, $json]` middleware unless noted.

| Method | Pattern | Controller@method |
|--------|---------|-------------------|
| [METHOD] | /api/v1/tenant/{tenant_id}/[path] | [Controller@method] |

**API response envelope:**
```json
{ "success": true, "data": {} }
{ "success": false, "error": "Human readable message", "code": "MACHINE_READABLE_CODE" }
```

---

## Models

> Each model extends `BaseModel` and follows the typed-property pattern from CODEBASE.md §7.
> Document any custom methods added beyond BaseModel defaults.

```php
// [ModelName] — [brief description]
class [ModelName] extends BaseModel
{
    protected string $table = '[table_name]';

    public ?int    $id         = null;
    public int     $tenant_id;
    public string  $[field];
    // ...
    public ?string $created_at = null;
    public ?string $updated_at = null;
}
```

**Custom methods:**
- `[ModelName]::[methodName]([params])` — [what it does and returns]

---

## Services

> Each service lives in `App\Services\` and returns `ServiceResult` objects.
> See CODEBASE.md §8 for the `ServiceResult` interface.

### [ServiceName]

| Method | Returns on success | Returns on failure |
|--------|-------------------|-------------------|
| `[methodName(params)]` | `success=true, get('[key]')` | `success=false, message=...` |

---

## Business Rules

> Rules that are not obvious from the schema. The AI agent must respect these when writing new features.
> Add rules here as they are discovered or decided — do not leave them implicit in code only.

- [Rule 1]
- [Rule 2]
- Signup terms acceptance: [Does this app set `signup.require_terms` in `Config/config.php`? If so, give the current `terms_version`, where the terms/privacy pages live, and anything the checkbox also confirms, e.g. age. See CODEBASE.md §16.9]

---

## CSS Conventions

> Follows the decision order in CODEBASE.md §11: Bootstrap utility → `app-core.css` → feature CSS file → add new reusable class.
> Never write inline styles. Never create single-use CSS classes.

**Application feature CSS files:**

| File | Scope |
|------|-------|
| `[filename].css` | [What views and components this file covers] |

---

## Current Development Focus

> Update this section at the start of each major piece of work.
> This is the first thing an AI agent should read to understand what is being actively built.

- **Current feature:** [What is being built right now]
- **Working files:** [Which files are being actively modified]
- **Patterns to follow:** [Reference a specific existing controller, service, or view to follow]
- **Known issues / tech debt:** [Anything relevant the agent should know]
- **Do not touch:** [Files or areas that are stable and should not be refactored]

---

## Known Deviations From Framework Conventions

> If anything in this application intentionally breaks the patterns described in CODEBASE.md, document it here.
> This prevents an AI agent from trying to "fix" intentional decisions.

- [None yet — add as they arise]

---

## Glossary

> Define domain terms that an AI agent cannot infer from the code alone.
> This is especially important for multi-tenant or domain-specific applications.

| Term | Meaning |
|------|---------|
| Tenant | [e.g. A gym, team, or organisation — one isolated account with its own users and data] |
| [Term] | [Definition] |
