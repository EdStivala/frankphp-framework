# Recipe: tenant-scoped API resource (Organization)

Demonstrates `Core\BaseApiController` and the Syncfusion DataManager
paging/sort/filter/search pattern for a tenant-scoped API resource.

## Files

```
Controllers/Api/V1/OrganizationController.php
Models/Organization.php
sql/organizations.example.sql
```

## To adopt

1. Copy `Controllers/` and `Models/` into your app's `app/` directory.
2. Run `sql/organizations.example.sql` against your database (or fold it
   into your app's own schema file).
3. Register the route (no container binding needed — the controller has
   no constructor dependencies):

   ```php
   $router->add(
       'POST',
       '/api/v1/tenant/{tenant_id}/organizations',
       'App\\Controllers\\Api\\V1\\OrganizationController@index',
       [$tm, $apiAuth, $json]
   );
   ```

4. Point a Syncfusion DataManager-backed grid at that URL.

## Notes

`Organization::findPagedByTenant()` allowlists which columns may be
sorted/filtered on (`SORTABLE_COLUMNS`) — extend that list if you add
columns to the `organizations` table.
