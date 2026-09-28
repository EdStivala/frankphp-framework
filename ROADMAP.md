# FrankPHP Roadmap — Working Notes

Raw backlog of everything identified so far. Not yet clustered, prioritised,
or assigned to versions except where explicitly noted below — that's a
separate pass.

---

## Signup & Tenant Creation

- Real tenant creation on signup completion. `SignupService::completeSignup()`
  currently hardcodes `$defaultTenantId = 1` for every signup — this is
  actively blocking real signups on existing apps today, not a theoretical
  gap.
- Needs a swappable policy point, not a hardcoded rule: framework default
  should be "signup creates a new tenant, signer becomes owner" (self-serve
  SaaS), but must be overridable for invite-into-existing-tenant business
  models (e.g. a coach/client app where a client shouldn't spawn their own
  tenant).
- SSO/OAuth for signup (previously discussed, not yet scoped).
- Org-email auto-recognition (join an existing tenant by email domain,
  previously discussed, not yet scoped).
- Mandatory T&Cs checkbox on signup — currently missing. Touches the same
  flow/views as the above, worth considering together rather than separately.
- `app/Views/layouts/legal.php` is currently broken — references two
  non-existent includes (`../elements/g_tag.html`, `../elements/cookieBanner.html`).

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

## Future Product Features

From the new positioning notes — not yet scoped:

- Stripe subscription billing integration.
- API-first architecture + recipes for connecting a native iOS Swift app to
  a FrankPHP backend (framework provides the wiring, not the mobile code).
- Syncfusion component integration ("bring your own licence").
