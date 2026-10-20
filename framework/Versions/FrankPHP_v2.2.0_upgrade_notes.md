# FrankPHP v2.2.0 Upgrade Notes

## Who needs this

Any install upgrading from 2.1.x. There are no database changes and no route changes.

## What changed

On the Users dashboard (`/tenant/{tenant_id}/users`), what you see now depends on your role:

| Role | Users menu option | Users listed |
|---|---|---|
| `platform` | Shown | Every user in every tenant, with a Tenant column |
| `owner` / `admin` | Shown | Own tenant only (unchanged) |
| `user` | Hidden | No access (redirected with `?error=forbidden`, unchanged) |

The all-tenants view depends only on the logged-in user's role. It is built from the existing single-tenant queries (`Tenant::findAll()` plus `User::allByTenant()` for each tenant).

## Steps

1. **Replace `framework/`** with the clean 2.2.0 copy.

2. **Update your app views.** These files belong to your app, so replacing `framework/` doesn't change them.

   `app/Views/users/management-dashboard.php`: the view now receives `$crossTenant` (bool), and each user row has `tenantName`.

   ```php
   <p class="text-muted mb-0" style="font-size:0.8125rem;"><?= $crossTenant ? 'All tenants' : htmlspecialchars($tenant['name'] ?? '') ?></p>
   ...
   <th>Name</th>
   <?php if ($crossTenant) : ?><th>Tenant</th><?php endif; ?>
   ...
   <td><?= htmlspecialchars($row['name']) ?></td>
   <?php if ($crossTenant) : ?><td><?= htmlspecialchars($row['tenantName']) ?></td><?php endif; ?>
   ```

   `app/Views/partials/app-nav.php` (the sidebar **Users** item) and `app/Views/partials/app-header.php` (the **Manage Users** item and the divider above it):

   ```php
   <?php if (in_array($user['role'] ?? '', ['platform', 'admin', 'owner'], true)) : ?>
       ... link markup ...
   <?php endif; ?>
   ```

   If you skip this step, the dashboard still works. Platform users get every tenant's users but no Tenant column, and the `user` role still sees the links but is refused on click.

   If you copy in the `management-dashboard.php` change and your app overrides `UserManagementController`, also do step 4. Otherwise the page gives undefined-variable warnings.

3. **Custom service wiring (rare).** If your `app/bootstrap.php` builds `UserManagementService` itself, add the new argument:

   ```php
   new \Frank\Services\UserManagementService(
       userModel: new \Frank\Models\User(),
       tenantModel: new \Frank\Models\Tenant(),
   );
   ```

4. **Controller override.** If your `app/bootstrap.php` replaces `UserManagementController` using `$container->override()`, and your subclass defines its own `index()`, the framework's `index()` no longer runs, so `crossTenant` never reaches the view. Add this line to your `index()` view params. It's copied from `framework/Controllers/UserManagementController.php` line 57, where `$result` is what `getUserDashboard()` returns:

   ```php
   'crossTenant'        => $result->get('crossTenant', false),
   ```

   This is the only view param added in 2.2.0. Without it, the updated `management-dashboard.php` reads an undefined `$crossTenant`. PHP gives undefined-variable warnings, and platform users see the current tenant's name instead of "All tenants", with no Tenant column.

## Check

- Platform login: Users lists users from several tenants, with the Tenant column.
- Owner or admin login: Users lists their own tenant only.
- User login: no Users link, and `/tenant/{id}/users` redirects with `?error=forbidden`.
