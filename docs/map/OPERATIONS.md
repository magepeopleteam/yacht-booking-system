# Map — operations

How to build, check, release and run the plugin. Names only here, never secret values.

| Task | Where | Notes |
|---|---|---|
| Local setup | Any local WordPress (5.9+, PHP 8.0+) with this folder in `wp-content/plugins/` | `npm install` for the build; `vendor/` is committed, so Composer is only needed to add classes. WooCommerce is optional. |
| Build | `package.json` (`wp-scripts`), [webpack.config.js](../../webpack.config.js) | `npm run build` writes `assets/build/`; commit it with the source. `yacht-single.*` and `embedded-checkout.css` are plain files, not built. |
| Checks before a PR | — | `php -l` on changed PHP, `npm run build` without errors, then a browser pass: yacht page, booking drawer, confirmation, the admin screen you touched. No automated tests exist. |
| CI | None | There is no `.github/` workflow; nothing runs on push or PR. |
| Packaging | [.distignore](../../.distignore) | Keep all runtime/source/build files. Demo JPEG originals and admin screenshots stay out of compact ZIPs; manifest URLs resolve full-quality originals from the pinned `magepeopleteam/dummy-images` revision. Imported photos remain in local uploads. No image resizing or quality change. |
| Release | Plugin header `Version` and `MAGEYABO_VERSION` in [magepeople-yacht-booking-system.php](../../magepeople-yacht-booking-system.php); `Stable tag` and `== Changelog ==` in [readme.txt](../../readme.txt) | Keep all four in step - the header, `MAGEYABO_VERSION`, `Stable tag` and the top changelog entry always carry the same number. |
| Rollback | Git history | Revert the commit and rebuild the zip; database migrations only add, so an older build keeps working. |
| Database | [includes/Install/Migrator.php](../../includes/Install/Migrator.php) | Tables: `mageyabo_bookings`, `_guests`, `_addons`, `_booking_addons`, `_yacht_addons`, `_pricing_rules`, `_discount_codes` (built-in coupons; `mageyabo_coupons` is the Pro add-on's), `_newsletter_subscribers`. Schema version `MAGEYABO_DB_VERSION`. |
| Configuration | [includes/Settings.php](../../includes/Settings.php) | One option, `mageyabo_settings`; defaults and sanitising live there. |
| Theme preview | `assets/admin/images/yachtiva-demo.png`, `Install\DemoAssets` | Actual public `https://wpyacht.com/` screenshot captured 2026-11-10 through the Thum.io screenshot API; source keeps the original PNG, compact ZIPs display the identical hosted original. No runtime screenshot-service request. Refresh when the demo design changes. Purchase URL is replaceable through `mageyabo_theme_purchase_url`; default is MagePeople checkout for EDD download 144408, price ID 1. |
| Secrets (names) | `mageyabo_settings` keys | `paypal_client_id`, `paypal_secret`, `stripe_publishable_key`, `stripe_secret_key`, `stripe_webhook_secret`. Never commit or print values. |
| External services | [readme.txt](../../readme.txt) § External services | PayPal and Stripe only when enabled; OpenStreetMap tiles (front end), Nominatim (admin address search), GitHub original demo/help images. |
| Scheduled jobs | [includes/Cron/Maintenance.php](../../includes/Cron/Maintenance.php) | Daily `mageyabo_daily_maintenance`: anonymises guests past `retention_months`. |
| Pages created on activation | `Migrator::create_pages()` | Search Yacht, Yacht List, Booking Confirmation; IDs kept in `mageyabo_*_page_id` options. |
| Uninstall | [uninstall.php](../../uninstall.php) | Removes data only when `remove_data_on_uninstall` is on. |
