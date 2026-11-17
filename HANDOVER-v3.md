# v3.0.0 hand-over note

Written 2026-11-16, updated 2026-11-17 at the end of the second session, so a
new session can start from the facts. Nothing here is a decision; every open
item is the owner's call.

## Where the code is

- Branch `v3.0.0`. Not merged to `main`, not pushed.
  - `08336c0` sprint 1: passwordless email-code login (tested and approved).
  - `0c7f09b` sprint 2 checkpoint (tested by the owner; sprint 2 NOT finished).
  - Everything from the 2026-11-17 session is UNCOMMITTED on top of `0c7f09b`.
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
4. Sprint 2 screens and business rules — IN PROGRESS (see below).

## Done in the 2026-11-17 session (uncommitted, tested by the owner unless noted)

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
- The top-bar user drop-down "Account" item still points nowhere (`href="#"`).
- `admin`/`platform` (and any other non-`user` role) have no way to delete
  their own account — by the owner's instruction for platform; not discussed
  for others since `admin` was removed.
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
1. Single delete uses the browser's own confirm box, not the existing pop-up (still true for Delete my account and Edit User delete).
7. Attempts by a role without access are sent to the dashboard with "forbidden".
8. Name is its own form with its own "Save name" button.
9. Name required, trimmed, max 255; a bad name shows the general "could not save" message.
11. After deleting your own account you land on `/login`.
12. Typed tenant name must match exactly, including capitals and spaces.
13. Tenant delete entry is a link on Tenant Settings, owner only.
14. Screen titled "Delete Organisation"; after the delete you land on `/login`.
15. Refusal reason names (e.g. `owner_requires_tenant_delete`, `role_protected`).
16. Role trimmed, 1–50 characters, owner/platform compared ignoring capitals; no list of valid roles.
17. All on-screen wording and messages.
18. Web addresses of the new actions and names of the new files.
19. `app/MYAPP.md` only partly updated (roles section, 2026-11-17).
20. A refused settings save shows "Invalid request. Please try again."
21. The CSRF check is written separately in three controllers.
22. Stale `/signup` changelog line removed.

2026-11-17 session (uncommitted):
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
