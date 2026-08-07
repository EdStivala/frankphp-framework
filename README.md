# FrankPHP

**A Micro SaaS Business framework **

Laravel is amazing. I absolutely love Laravel. You can build *anything* in Laravel — and that's the problem. When what you actually need is a focused skeleton for a typical multi-tenant SaaS business, not a general-purpose toolkit, FrankPHP is built to deliver just that, without the complexity.

No Docker. No Composer. No build step. If you can FTP a file, you can deploy FrankPHP.

---

## What you get out of the box

FrankPHP ships with the parts every SaaS business needs and almost never wants to build from scratch:

- **Multi-tenant users and accounts** — tenants, users, roles, login, logout, email-verified signup, password reset, an admin user-management dashboard.
- **A framework/application split** — `framework/` is the reusable core, safely replaceable on every update. `app/` is entirely yours: your controllers, models, views, business logic. An update never touches it.
- **AI-native architecture** — `framework/codebase.md` is a machine-readable architecture contract, written so AI coding assistants (Claude, ChatGPT, or any LLM) can work on your FrankPHP project accurately from day one, not guess at your conventions.
- **API-first** — every framework flow is reachable through the same clean architecture your API controllers use, with an API auth middleware ready to go. Recipes for wiring a native iOS Swift app to your FrankPHP backend are coming soon — FrankPHP doesn't write your mobile code, but it makes the wiring simple and correct.
- **Syncfusion-ready** — bring your own Syncfusion licence and start using their component library inside a FrankPHP web app immediately.
- **Coming soon** — Stripe subscription billing, built around the framework's existing tenant/user ownership model.

## Getting started

### New install

1. Download the latest release from the [Releases page](../../releases) — grab the **Source code** zip or tarball, or `git clone` the repo directly. Either way you get both `framework/` and `app/`, a complete working starting point.
2. Copy `app/env.example` to `app/.env` and fill in your database and mail credentials.
3. Point your web server's document root at `app/public/`.
4. Visit the site — the framework creates its schema and seeds a starter tenant and two users automatically on first boot. Change the seed passwords before sharing any URL.

### Updating an existing install

1. Download the **framework-only** zip attached to the release you're updating to (not the full source zip — that one includes a copy of `app/` too, which you don't want here).
2. Replace your project's `framework/` folder with the contents of the zip.
3. Leave `app/` untouched. That's the whole point — your customizations are never overwritten.

## Requirements

- PHP 8.5+
- MySQL or MariaDB
- No Composer, no external dependencies

## Documentation

- [`framework/codebase.md`](framework/codebase.md) — the full architecture reference: routing, controllers, models, services, the framework/application ownership boundary, and the conventions an AI assistant (or a human) needs to work on this codebase correctly.
- [`framework/CHANGELOG.md`](framework/CHANGELOG.md) — release history.
- Full guides at [docs.n3wmedia.com](https://docs.n3wmedia.com).

## License

[MIT](LICENSE)

---

Built by [Ed Stivala](https://n3wmedia.com), N3WMedia Labs.
