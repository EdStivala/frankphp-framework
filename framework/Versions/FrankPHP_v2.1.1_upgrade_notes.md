# FrankPHP v2.1.1 Upgrade Notes

## Who needs this

Any install upgrading to 2.1.x. It matters most if the site fails to boot after replacing `framework/`, with either of these errors:

- `.env file not found at: <project>/app/app/.env` (2.1.0)
- `APP_BASE_DIR points at the app/ folder ...` (2.1.1)

If you are on 2.1.0 and it boots, just replace `framework/`. 2.1.0 → 2.1.1 has no database changes.

## The contract (unchanged since v2.0.0)

`APP_BASE_DIR` is the **project root**, the folder that contains both `framework/` and `app/`:

```
project/            ← APP_BASE_DIR
├── framework/      ← replaced wholesale on every update
└── app/
    ├── .env
    ├── bootstrap.php
    ├── Config/config.php
    ├── Views/
    └── public/index.php
```

It is **not** the `app/` folder. That was the pre-2.0 single-folder layout.

## Fixing a site that fails to boot

1. **`app/public/index.php`**: the entry point must contain exactly:
   ```php
   define('APP_BASE_DIR', dirname(__DIR__, 2));
   $router = require_once APP_BASE_DIR . '/framework/bootstrap.php';
   ```
2. **App code**: run `grep -rn "APP_BASE_DIR" app/` and put `/app` in front of every app-relative path:
   - `APP_BASE_DIR . '/Views/layouts/x.php'` → `APP_VIEWS_DIR . '/layouts/x.php'`
   - `APP_BASE_DIR . '/Helpers/x.php'` → `APP_BASE_DIR . '/app/Helpers/x.php'`
   - The same for `/Config`, `/.env`, `/sql` and `/private`.
   - Paths meant to sit at the project root, for example a shared `storage/`, stay as they are.
3. **`framework/`**: replace it with the clean 2.1.1 copy. Don't carry local edits over. `framework/` must stay unmodified so future updates can replace it safely.

If you are coming from 2.0.x, also run the 2.1.0 database migration (`FrankPHP_v2.1.0_migration_notes.md`).

## Check

From `app/public`:

```bash
php -r 'define("APP_BASE_DIR", dirname(getcwd(), 2)); $r = require APP_BASE_DIR."/framework/bootstrap.php"; echo get_class($r), PHP_EOL;'
```

Expected output: `Frank\Core\Router`.
