# FrankPHP v3.0.0 Upgrade Notes

## Who needs this

Any install upgrading from 2.2.x. **This is a breaking release.** The database must be migrated, routes are removed and added, and some `app/` files must change. Unlike earlier releases, the v3.0.0 code is not safe on an un-migrated database: run the migration when you deploy.

## What changed

| Area | v2.2.x | v3.0.0 |
|---|---|---|
| Sign-in and sign-up | Passwords, a two-step `/signup`, forgot / reset password | One screen at `/login`: an emailed 6-digit code (15 minutes, 3 wrong guesses cancel it, 5 requests per hour per IP). Optional Continue with Google |
| New accounts | Created by `/signup` | An unknown email that verifies its code gets a new tenant and becomes its owner. Optional terms step first |
| Email addresses | Unique per tenant | Unique across the platform: one email = one user = one tenant |
| Staying signed in | PHP default session | 28 days, renewed while the person keeps using the app; sessions stored in `app/private/sessions` |
| Fresh install | One seeded tenant and two seeded users | Empty; the first sign-in creates the first tenant |
| Invites | None | Owners invite users to their tenant; the platform invites new owners (new tenant) |
| Support access | None | A platform user captures a tenant and acts as its owner; no record kept |
| CSRF | Written separately in each controller | `Session::csrfToken()` / `Session::csrfValid()` and the partial `Views/partials/csrf-field.php` |
| Users screen | Framework-owned | App-owned (`app/Controllers/UserManagementController.php`, `app/Services/UserManagementService.php`) |

Tables added: `login_codes`, `invites`. Tables removed: `password_reset_tokens`, `signup_tokens`. Column removed: `users.password_hash`. Full detail in `CHANGELOG.md` [3.0.0].

## Steps

1. **Back up the database.**

2. **Find duplicate emails and resolve them by hand.** One email can now exist only once:

   ```sql
   SELECT email, COUNT(*) FROM users GROUP BY email HAVING COUNT(*) > 1;
   ```

   The migration stops at its first step if any exist.

3. **Replace `framework/`** with the clean 3.0.0 copy and deploy.

4. **Run the migration** against the app database:

   ```text
   framework/sql/migrations/v3.0.0_passwordless.sql
   ```

   It adds the unique key on `users.email`, creates `login_codes` and `invites`, then drops `password_hash`, `password_reset_tokens` and `signup_tokens`. Nobody can sign in with a password after this.

5. **Copy the new sign-in views** `app/Views/auth/login.php`, `login-verify.php` and `login-terms.php` into your `app/Views/auth/`, and delete the old password and sign-up views (`signup.php`, `signup-verify.php`, `forgot-password.php`, `forgot-password-sent.php`, `reset-password.php`, `reset-password-error.php`). Remove any link or text in your own views about passwords, "forgot password" or `/signup`.

6. **Start sessions the v3 way.** In `app/public/index.php`, replace the `session_start()` block with:

   ```php
   \Frank\Core\Session::start();
   ```

7. **Rename email template overrides.** In `app/Config/config.php`, any `mail.templates` override keyed `signup_verification` becomes `login_code` (same method signatures). `password_reset` is no longer used.

8. **Session folder.** Make sure the web server user can create `app/private/sessions` (mode 0700). Everyone is signed out once, because sessions move to the new folder.

9. **Invites.** Copy `app/Views/auth/invite-welcome.php` and `invite-expired.php` into your `app/Views/auth/`. Sending invites is up to your app: the template's Users screen, `UserAdminController@sendInvite` and its route are a worked example.

10. **Continue with Google (optional).** Add the four `GOOGLE_*` / `MICROSOFT_*` keys from `app/env.example` to your `app/.env` (empty is fine; the Microsoft ones are stubs for a future release), add the `oauth` section to your `app/Config/config.php`, and copy the new `app/Views/auth/login.php` (it has the Google button). To turn Google on, register the app with Google, add `{your site}/login/google/callback` as an authorised redirect URI (exactly), and put the client ID and secret in `app/.env`.

11. **Site address and CSRF in your own sign-in views.** Replace any call to `EmailService::getBaseUrl()` with `\Frank\Core\Url::baseUrl()`. In your own copies of the sign-in views, replace the `$csrfToken` hidden input with:

    ```php
    <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
    ```

12. **Support access (optional).** The framework routes are there for every app; the screens are yours. To use the template's, copy the Capture Tenant form from `app/Views/users/tenant-edit.php`, the `Session::supportAccess()` block from `app/Views/partials/app-header.php`, the `topbar--support` class on the `<header>` in `app/Views/layouts/app-main.php`, and the `.topbar--support` rules from `app/public/assets/css/app-header.css`.

### Also required (from the Added and Changed sections of the CHANGELOG)

13. **CSRF on the settings forms.** `AccountSettingsController@save` and `TenantSettingsController@save` now refuse a post without a valid `csrf_token`. Add the partial from step 11 to your account and tenant settings forms, or those forms stop saving.

14. **Users screen.** `UserManagementController` and `UserManagementService` moved from the framework to the app template. If your app uses the Users screen, copy both files, the `GET /tenant/{tenant_id}/users` route and the two container bindings from the template's `app/bootstrap.php` into your own.

15. **Root address.** If your app has its own `/` route, make it send a signed-in person to their dashboard with the auth check only, as the template's `HomeController@root` does (`[$auth]`, not `[$tm, $auth]`). Otherwise `/` redirects to `/logout` and ends the 28-day login.

16. **`updated_at`.** `BaseModel::save()` now writes `updated_at` back as fetched. If one of your models declares `updated_at` on a table that uses `ON UPDATE CURRENT_TIMESTAMP`, MySQL no longer updates it: your Service must set it with `Clock::nowUtcString()` before `save()`.

## Check

- Sign in with an existing address: the code arrives, the right code signs you in, and you land on your dashboard.
- Sign in with a new address: a new tenant is created and you are its owner (terms step first if `signup.require_terms` is on).
- Three wrong codes cancel the code; `/signup` and `/forgot-password` return not-found.
- Account and tenant settings still save.
- If you use them: an invite email arrives and its link opens the welcome screen; Continue with Google signs you in; Capture Tenant turns the top bar red and Release Tenant brings you back.
