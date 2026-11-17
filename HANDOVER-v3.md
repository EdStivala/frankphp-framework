# v3.0.0 hand-over note

Written 2026-11-16, updated 2026-11-17 at the end of the second session, so a
new session can start from the facts. Nothing here is a decision; every open
item is the owner's call.

## Where the code is

- Branch `v3.0.0`. Not merged to `main`, not pushed.
  - `08336c0` sprint 1: passwordless email-code login (tested and approved).
  - `0c7f09b` sprint 2 checkpoint.
  - `e9ca98c`, `3a7b807` sprint 2 completed (2026-11-17; tested by the owner).
  - `99e9854` sprint 3: invites.
  - `37a263e` sprint 4 (in progress): Google sign-in, CSRF in Session, Url
    helper. Later sprint 4 changes are not committed (see the Sprint 4 section).
- Approved plan: `/Users/edstivala/.claude/plans/this-is-a-planning-adaptive-mitten.md`.
- Working rules are in the project memory (no choices without instruction,
  one small step at a time, the owner drives, check files rather than memory,
  edit only what is instructed). Browser use: allowed again from 2026-11-17
  (owner's instruction); the local dev site is `http://localhost:8888`.

## Sprint 2 repair plan (agreed four steps)

1. Fix the base class — DONE. `BaseModel::save()` writes `updated_at`; the
   Service sets it with `Clock` before `save()` (codebase.md §7 BaseModel, §8).
2. Remove `User` duplicates of `BaseModel` — DONE. Remaining `User` functions
   are the three cross-tenant finders (commented) and the seven `login_codes`
   functions (tech debt, recorded in ROADMAP.md).
3. Test scripts — DONE (closed by the owner). The two v3 test scripts
   (`login_code_test.php`, `user_tenant_operations_test.php`) were created by
   AI without the owner's instruction and have been deleted, with their
   references in codebase.md and CHANGELOG.md. Only `boot_layout_test.php`
   (v2.1.1) remains.
4. Sprint 2 screens and business rules — DONE (tested by the owner 2026-11-17).

**Sprint 2 is DONE.** Next per the plan: sprint 3, invites.

## Sprint 3 — invites (DONE, committed 2026-11-17)

Tested by the owner: sending an invite (owner and platform), the invite email,
the link opening (valid and invalid), accepting — platform invite creates a new
tenant and owner, owner invite creates a `user` in the inviting tenant. Sign-up
re-tested after account creation moved to `AccountService`. Role changes and
owner tenant delete (with clean-up) re-tested and still correct.

Not tested, skipped on the owner's instruction: accepting with terms off, and
an invite genuinely past its 7 days.

Owner decisions this sprint (now in the plan's Invites rules): no code to join
— the link proves the address and the address cannot be changed; a welcome
screen with one button (nothing created on open, because mail scanners open
links); the accepted invite is deleted, not marked used; a failed invite email
saves nothing; live-invite check within the sender's tenant only.

Reuse refactors this sprint: `EmailAddress` (email rules), `EmailService`
`sendTemplated()`, `AccountService` (account creation for sign-up and invites).

Docs: codebase.md (§2, §8.1, §8.2, §11, §14, §15, §16.1–16.5, §16.8–16.11) and
CHANGELOG.md updated for sprint 3. Docs site: not touched (sprint 6).

## Sprint 4 — Google and Microsoft (in progress; committed to 37a263e, later changes not committed)

- Login screen (`app/Views/auth/login.php`): "Continue with Google" and
  "Continue with Microsoft" buttons added below the email form, after an "or"
  divider; grey outline buttons with Bootstrap Icons, Google above Microsoft.
  Not wired yet (buttons do nothing). APPROVED by the owner 2026-11-17.
- Keys (owner decisions 2026-11-17): `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`,
  `MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET` in `app/.env` (placeholders
  in `app/env.example`). Keys are per app; a domain change does not need new
  keys, the production return address is added to the same registration.
- Return addresses (owner decision): `/login/google/callback` and
  `/login/microsoft/callback`.
- Config block named `'oauth'` in `app/Config/config.php` (owner decision),
  read with `Env::get()`.
- Keys are optional (owner decision): neither provider is required. A provider
  whose keys are not set keeps its button on the sign-in screen, greyed out
  (disabled), to show the option exists but is unavailable.
- Google keys obtained by the owner and set in `app/.env` (2026-11-17); the
  Google button is enabled, Microsoft stays disabled.
- Microsoft PARKED (owner decision 2026-11-17): the owner has no Azure account
  to register the app. Build Google now; come back to Microsoft at the end of v3.
  SUPERSEDED 2026-11-17 — Microsoft is LEFT OUT OF v3 (owner decision): a
  Microsoft registration needs an Entra tenant; the free Azure route needs a
  payment card (owner will not use it), Entra's "Try for free" has been
  withdrawn, and the M365 developer sandbox is gated. Tidy-up (owner
  instruction): the `MICROSOFT_*` key stubs stay in `app/env.example` (and the
  `microsoft` entry in config `oauth`); the Microsoft button is commented out
  in `app/Views/auth/login.php` (PHP comment, not sent to the browser) so a
  developer who sets the keys does not get a button that does nothing.
  `AuthController` still passes `microsoftEnabled` (unused for now).
  DOCS SITE (sprint 6): list Continue with Microsoft as a future enhancement.
- END OF SPRINT 4 — to investigate and amend (owner instruction 2026-11-17):
  `EmailService::getBaseUrl()`. It lives in the email class but is used for
  site addresses generally (invite links, the Google return address), and it
  judges https differently from `Session` (ignores the proxy header), so
  behind a proxy the Google return address could come out as `http://`.
  RESOLVED 2026-11-17 (owner: a Core helper like Clock; no back-compat, v3 is
  breaking): new `Frank\Core\Url` with `isHttps()` (moved from `Session`) and
  `baseUrl()`. `Session`, `InviteService` and the Google binding use it;
  `EmailService::getBaseUrl()` removed (apps calling it must switch to
  `Url::baseUrl()` — migration guide, sprint 6). The `Host` header point was
  set aside by the owner.
- Login buttons wired: Google is a link to `/login/google` when its keys are
  set; otherwise the disabled button. `AuthController` receives `$config`;
  `loginView()` passes `googleEnabled` / `microsoftEnabled` (`oauthKeysSet()`:
  both client ID and secret must be set).
- CSRF made once (owner instruction): `Session::csrfToken()` /
  `Session::csrfValid()`; every form uses `partials/csrf-field.php`; the four
  controllers' own copies removed (sprint 2 item 21 resolved).
- Google sign-in built without libraries (owner decision: FrankPHP keeps
  minimal dependencies): `Frank\Services\GoogleSignInService`
  (`authorizationRequest()`, `exchangeCode()`); routes `GET /login/google`
  (`googleStart`) and `GET /login/google/callback` (`googleCallback`); state
  and nonce in session key `google_signin`. A Google-confirmed address goes
  the same way as a correct code via `LoginCodeService::signInConfirmedEmail()`
  and `AuthController::continueConfirmedEmail()` (terms step, account
  creation, sign-in all reused). Google says not to use email as the unique
  identifier (`sub`); the owner's rule "same address = same account when the
  provider confirms it" stands and is built on `email_verified`.
- Agreed by the owner 2026-11-17 (were provisional): all names above plus
  `Url`, `isHttps()`, `baseUrl()`; both keys needed to enable a button;
  32-character state and nonce; 10-second limit on the token exchange; wording
  "Google did not confirm this email address." and reuse of "Invalid request.
  Please try again."; `signInConfirmedEmail()` living in `LoginCodeService`.
- Tested and passed by the owner 2026-11-17: buttons, and greyed out without
  keys; CSRF change; Google outbound step and Cancel at Google; Google sign-in
  for an existing person and a new person (terms on); email-code login
  re-test (known and unknown); invites and Google sign-in after the `Url`
  change.
- Not tested: an address Google did not confirm (hard to produce);
  `/login/google` typed in with no keys set (should return to `/login`).
- `codebase.md` and `CHANGELOG.md` updated for sprint 4 (2026-11-17).
- Still to do in sprint 4: the docs-site pages the plan names. Microsoft is
  out of v3 (see above).

## Done in the 2026-11-17 session (committed, tested by the owner)

- `UserService::changeRole()` sets `updated_at`.
- `Tenant.php`: comments explain why `Tenant` does not extend `BaseModel`.
- Role `admin` removed completely: Tenant Settings is owner only
  (`requireAdmin()` renamed `requireOwner()`); Users screen and its nav links
  are platform and owner only; `.role-badge--admin` removed; docs updated.
- `UserManagementController` and `UserManagementService` moved from the
  framework to the app (`App\Controllers`, `App\Services`); route and bindings
  now in `app/bootstrap.php`.
- `TenantService::deleteTenant()` accepts the tenant's owner OR a `platform` user.
- Users screen: the role drop-down, Change and Delete buttons (and their
  controller methods, helpers and routes) removed. New layout: "Your details"
  card on the left with a 2×2 block of stat cards beside it (stats still count
  the logged-in person); the table lists everyone else, each row with a round
  edit icon (`btn-circular`). Owner rows open Edit Tenant, other rows Edit User.
- New Edit User screen (`users/user-edit.php`, platform or owner): name, and
  delete the user. New Edit Tenant screen (`users/tenant-edit.php`, platform
  only): name, company email, timezone, date format, and delete the tenant by
  typing its name. Routes `…/users/edit|save|delete` and `…/tenants/edit|save|delete`.
- User Settings (was Account Settings): Delete my account shows for role `user`
  only; reached from the top-bar Profile item and the side nav "User Settings".
- Tenant Settings (was Organisation Settings): reached from the top-bar tenant
  Settings item and the side nav "Tenant Settings".
- codebase.md, CHANGELOG.md and `app/MYAPP.md` (roles section) updated for the above.

## Open items (not in any order of priority)

- Platform owner on owner rows — RESOLVED: platform deleting an owner deletes
  the whole tenant (Edit Tenant). The platform's own tenant is meant to contain
  nobody else; the dev database still has the old v2 demo users in it, and the
  owner said to ignore the demo data for now.
- Users screen design — DONE as described above.
- The top-bar user drop-down "Account" item pointed nowhere (`href="#"`) — DONE: removed.
- Deleting your own account — DONE: every remaining role is covered. `user`
  uses Delete my account (User Settings); `owner` deletes the tenant (Tenant
  Settings); `platform` can never delete themself (owner's decision).
- BitfitterV2: `workouts`, `workout_exercises` and `exercises` use
  `ON UPDATE CURRENT_TIMESTAMP` and their models declare `updated_at`. Their
  Services must set `updated_at`; to be handled at its migration (sprint 7).
- Sprint 2 documentation-site pages: deferred by the owner until the sprints
  settle (sprint 6).
- Later sprints per the plan: 3 invites, 4 Google/Microsoft, 5 support access,
  6 documentation, 7 migrate AppTracker - Dev then BitfitterV2.

## Behaviour built without the owner's decision (still to be put to the owner)

Sprint 1 (committed):
1. Sessions stored in `app/private/sessions`, falling back to the server default.
2. Login cookie renewed at most once an hour.
3. Cookie marked Secure on https; https partly judged from a proxy header.
4. Terms step expires 15 minutes after the code is verified.
5. If an account appears for the email during the terms step, the person is logged in.
6. Emails lower-cased and limited to 255 characters.
7. Request limit skipped when the visitor's IP address is unknown.
8. A new code cancels earlier unused codes for that email.
9. `login_codes` records IP address and browser.
10. Old codes are never cleaned up (`cleanupExpiredCodes()` exists, nothing calls it).
11. The 15 / 3 / 5 / 60 limits can be overridden when wiring the service.
12. Code email subject, wording and FrankPHP branding.
13. All on-screen messages in the login flow.
14. Wording of the alert emails to the platform owner.
15. Two ROADMAP items fixed unasked (resend button, CSRF on sign-up forms).
16. README.md and ROADMAP.md edited.

Sprint 2 (in `0c7f09b`) — items 2–6 and 10 no longer exist (removed 2026-11-17):
1. Single delete uses the browser's own confirm box, not the existing pop-up (Delete my account and Edit User delete) — DECIDED: keep the browser box; each real app can change it.
7. Attempts by a role without access are sent to the dashboard with "forbidden" — DECIDED: keep (framework convention, codebase.md §11).
8. Name is its own form with its own "Save name" button — DECIDED: keep.
9. Name required, trimmed, max 255; a bad name shows the general "could not save" message — DECIDED: keep.
11. After deleting your own account you land on `/login` — DECIDED: keep.
12. Typed tenant name must match exactly, including capitals and spaces — DECIDED: keep.
13. Tenant delete entry is a link on Tenant Settings, owner only — DECIDED: keep.
14. Screen titled "Delete Organisation"; after the delete you land on `/login` — DECIDED: keep (wording stays "Organisation").
15. Refusal reason names (e.g. `owner_requires_tenant_delete`, `role_protected`) — DECIDED: keep.
16. Role trimmed, 1–50 characters, owner/platform compared ignoring capitals; no list of valid roles — DECIDED: keep (`changeRole()` has no screen now; may be used later).
17. All on-screen wording and messages — DECIDED: keep.
18. Web addresses of the new actions and names of the new files — DECIDED: keep.
19. `app/MYAPP.md` only partly updated (roles section, 2026-11-17) — DECIDED: leave it (routes, services and tree not recorded there).
20. A refused settings save shows "Invalid request. Please try again." — DECIDED: keep.
21. The CSRF check is written separately in three controllers — RESOLVED
    2026-11-17 (owner: made once, in a consistent way): `Session::csrfToken()`
    makes the token and `Session::csrfValid()` checks it; every form uses
    `partials/csrf-field.php`; the four controllers' own copies are removed.
22. Stale `/signup` changelog line removed.

2026-11-17 session (committed):
1. Addresses and file names: `…/users/edit|save|delete`, `…/tenants/edit|save|delete`,
   `users/user-edit.php`, `users/tenant-edit.php`, method `deleteTenantAsPlatform`.
2. Edit User shows name only (no timezone); email and role read-only.
3. Edit Tenant shows all four Tenant Settings fields; delete is on the same
   screen, not a separate warning screen.
4. "Back to Users" button on both edit screens.
5. A user or tenant not found sends you back to the Users list without a message.
6. After saving you return to the edit screen with "Saved."; after deleting you
   land on the Users list with no message.
7. Wording of the edit screens' messages and the "Your details" card.
8. Card layout: one-third width with the 2×2 stats beside it on wide screens.
9. `Tenant.php` comment on `insertTenant()` includes an unconfirmed reason
   (an upsert would overwrite a tenant whose slug clashes); owner said it is not material.
10. The CHANGELOG `admin` entry says existing admins do not see Delete my account.

Sprint 3 (provisional — copied from existing patterns, not yet confirmed):
1. Invite email: subject "You've been invited", wording, one template for both
   kinds; says "join FrankPHP", not the tenant's name.
2. Expired screen wording ("This invite has expired." + contact line).
3. Welcome screen heading "Welcome aboard!" and both texts (terms on / off).
4. Accepting when the address got an account meanwhile: login screen, address
   filled in, "That email address already has an account. Please sign in."
5. Users-screen banner messages for invites; button label "Invite"; modal
   titles "Invite a new user" / "Invite a new tenant".
6. The opened invite stays in the session until accepted, found unusable, or logout.
7. A signed-in person who accepts an invite for another address becomes that account.
8. The DB transaction stays open while the invite email is sent (1–2 seconds).
9. Platform-owner alert says "created via invite" for invite accounts.
10. `EmailMessage::isValid()` now also enforces the 255-character limit.
11. Addresses and file names: `/invite/{tenant_id}/{token}`, `/invite/accept`,
    `auth/invite-welcome.php`, `auth/invite-expired.php`, `AccountService`,
    `JoinTenantAsUser`, `EmailAddress`.
12. Expired, never-used invites are never cleaned up (owner: stick to the plan).
