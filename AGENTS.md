# MagePeople Yacht Booking System — operating guide

A free WordPress plugin for yacht and boat charter booking: the yacht post type, the booking engine (pricing, availability, seats), four payment methods (Offline, PayPal, Stripe, WooCommerce), a React admin app and the front-end booking form and drawer. It must never break booking creation, prices, availability or payments. The paid add-on lives in its own repository (`yacht-booking-system-pro`) and extends this plugin only through the hooks and registries listed in [docs/map/PRODUCT.md](docs/map/PRODUCT.md).

## Read first
1. [docs/map/PRODUCT.md](docs/map/PRODUCT.md): where each area's code lives.
2. [docs/map/OPERATIONS.md](docs/map/OPERATIONS.md): build, checks, release, packaging, data.
3. [readme.txt](readme.txt): the user-facing feature list, FAQ and changelog.

When documents disagree, the code wins, then `readme.txt`, then these maps.

## Hard rules
- **Prefixes.** PHP namespace `MageYaBo\`; functions, hooks, options and tables `mageyabo_`; REST namespace `mageyabo/v1`; CSS classes `ybs-`; text domain `magepeople-yacht-booking-system`.
- **Security.** Sanitize input, escape output, and check a capability (`Capabilities::CAP_BOOKINGS`, `CAP_SETTINGS`, `CAP_PAYMENTS`) plus a nonce on every admin action. A REST route is public only when guests need it. A guest-facing link is authorised by a secret (the booking's `qr_token`, or an HMAC), never by a booking ID alone.
- **Extension seams, not Pro code.** Features that belong to the add-on stay out of this plugin. Add a filter, action or JS registry entry instead, and document it in the map.
- **Everything ships.** The release zip is the repository minus the entries in `.distignore`, including `assets/*/src`, `package.json` and `vendor/` (WordPress.org Guideline 4). Agent documentation (`AGENTS.md`, `CLAUDE.md`, `docs/`) is excluded there; keep it excluded.
- **Built assets are committed.** After changing anything in `assets/admin/src` or `assets/frontend/src`, run the build and commit `assets/build/` together with the source.
- **The autoloader is committed.** A new class under `includes/` needs `composer dump-autoload`, or, without Composer, matching entries in `vendor/composer/autoload_classmap.php` and `vendor/composer/autoload_static.php`.
- **PHP file shape.** Every PHP file starts with the `ABSPATH` guard, and every new folder gets the empty `index.php` the others have.
- **Data you create while testing** (bookings, guests, WooCommerce orders, test sessions) is deleted before you hand over. Don't change the site's settings to test; override them only for your own request.
- **Commit or push only when asked.**

## Workflow
- Default branch `main`. Larger work goes through a feature branch and PR (for example PR #4); small changes may land on `main` when the owner asks.
- Requires PHP 8.0 and WordPress 5.9 or later. Keep code working without WooCommerce, PayPal or Stripe configured.
- User-facing wording follows the plugin's plain, specific style; every string goes through the text domain.

## Commands
- `npm install`, then `npm run build` (`wp-scripts build` into `assets/build/`); `npm start` watches.
- `php -l <file>` on every PHP file you change (there is no automated test suite).
- `composer dump-autoload` after adding a class.

## Reflexes
| When you are about to… | Do this |
|---|---|
| add a PHP class | Put it in `includes/<Area>/` with namespace `MageYaBo\<Area>`, give a new folder its `index.php`, regenerate the autoloader. |
| add a REST route | Extend `Rest\Controller`, register under `mageyabo/v1`, and use a capability check as `permission_callback` unless guests need the route. |
| change the booking form or drawer | Edit `assets/frontend/src/booking-form.js`, `style.css` and the markup in `includes/Frontend/Shortcode.php`. The drawer is moved to `<body>`, so query it with the `find()` / `findAll()` helpers. |
| add an admin screen | Add a screen in `assets/admin/src/routes/` and wire it in `App.js`; add-ons use `mageyabo_admin_react_routes` plus `window.mageyaboAdmin.registerRoute`. |
| add a setting | Add its default and sanitising in `includes/Settings.php` and its field in `assets/admin/src/routes/Settings.js`. |
| change how a price is worked out | Change `Booking\PricingEngine` or hook `mageyabo_booking_price_components`; check the quote route and the drawer summary still agree. |
| change a database table | Bump `MAGEYABO_DB_VERSION` and add the migration in `includes/Install/Migrator.php`. |
| release | Follow the release row in [docs/map/OPERATIONS.md](docs/map/OPERATIONS.md). |

## Project map
Before opening files, read the router for the task, then only the files it points to:
- [docs/map/PRODUCT.md](docs/map/PRODUCT.md): where the code for each area lives, and the extension hooks.
- [docs/map/OPERATIONS.md](docs/map/OPERATIONS.md): local setup, build, checks, release, packaging, configuration, data.

The map guides what to read first. It never replaces the rules above.

**Keep the map true.** A change that adds, moves or removes a file a router names updates that router in the same change. `includes/` and `assets/` ship to users, so their routers live in `docs/map/`, never inside those folders. Routers stay about 15–35 lines, link only to files that exist, and never hold secrets. Reference material goes in `docs/`, not here.
