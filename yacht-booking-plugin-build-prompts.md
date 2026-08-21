# Yacht Booking System — Build Prompts (Free + Pro)

## Architecture Note (read before either prompt)

Pro is built as a **separate add-on plugin** (`yacht-booking-system-pro`) that:
- **Requires** `yacht-booking-system` (Free) to be installed and active.
- **Never forks or duplicates** Free's code — it hooks into Free's actions/filters and registers its own REST routes under the same namespace.
- Free must expose extension points (hooks/filters) even where Free itself has no UI for them, so Pro can plug in cleanly without touching Free's core files.

This means the Free prompt below must be built first, and must include the hook/filter points listed in its own section 7, even though Free doesn't use them — Pro depends on them.

---

# PROMPT 1 — FREE PLUGIN (`yacht-booking-system`)

## 1. Objective
Build a complete, standalone WordPress plugin for yacht/boat charter booking. Fully functional on its own — no feature should feel crippled or like a demo. This is the foundation Pro will extend.

## 2. Tech Stack
- PHP 8+, WordPress coding standards, OOP structure (namespaced classes, PSR-4 autoloading via Composer).
- Custom Post Type for Yachts.
- Custom DB tables for Bookings, Guests, Pricing Rules, Add-ons (schema below).
- REST API under namespace `ybs/v1`.
- Admin dashboard: single-page **React app** using `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch`, `@wordpress/data` (for state) — mounted on one top-level admin page, client-side routed. No legacy multi-page PHP admin screens.
- Frontend booking form: Gutenberg block + shortcode fallback, built with the same React stack where practical, output as static-friendly HTML for non-JS fallback where feasible.

## 3. Database Schema to Implement
Implement all tables below at plugin activation (via `dbDelta`), even though some columns are only used once Pro is active — this avoids a schema migration later.

- `wp_ybs_bookings` — full column set from the schema doc, including `payment_method`, `woo_order_id`, `checked_in_at`, `checked_out_at`, `qr_token` (unused by Free's UI, but present).
- `wp_ybs_guests`
- `wp_ybs_pricing_rules`
- `wp_ybs_addons`
- `wp_ybs_yacht_addons`
- `wp_ybs_booking_addons`
- `wp_ybs_email_templates` (table exists, unused until Pro activates)
- `wp_ybs_email_logs` (table exists, unused until Pro activates)

Yacht CPT (`yacht`) with postmeta: `capacity`, `cabins`, `crew_size`, `length`, `build_year`, `location_name` (marina/pier name), `location_lat`, `location_lng`, `base_price_hourly`, `base_price_daily`, `base_price_multiday`, `base_price_halfday`, `base_price_morning_slot`, `base_price_evening_slot`, `min_notice_hours`, `buffer_minutes`, `min_duration`, `max_duration`, `booking_mode`.

## 4. Features to Build

### 4.1 Yacht Management
- Add New Yacht as a **4-step wizard** (React, no page reloads between steps):
  1. Basic Info (name, description, gallery, **location** — structured field: marina/pier name + map pin picker (lat/lng, via an embeddable map component, e.g., Leaflet with OpenStreetMap tiles to avoid Google Maps API key/billing requirements unless the site already has one), **build year**, **yacht class/category** taxonomy selection, **occasion tags** taxonomy multi-select, **repeatable FAQ block** — question/answer pairs editable and reorderable)
  2. Specs & Capacity (capacity, cabins, crew, length, `booking_mode`: full/shared/both, **"included in every charter" repeatable text list**)
  3. Pricing & Availability (hourly/daily/multiday/**half-day**/**morning & evening slot** base rates, off-days, min notice, buffer, min/max duration)
  4. Review & Publish
- Register two new taxonomies: `yacht_class` (Comfort, Comfort Plus, Business, First Class, Party — admin-editable terms) and `yacht_occasion` (Birthday, Anniversary/Proposal, Corporate, Bachelorette, Wedding, Sunset Cocktail, etc. — admin-editable terms), both usable for frontend filtering.
- Yacht List view: table with thumbnail, name, capacity, class, status, quick edit/delete.
- Edit/Delete existing yachts.

### 4.2 Booking Engine
- Booking types: Hourly, **Half-Day (fixed duration, e.g., 4 hours, priced as its own tier rather than hourly × 4)**, **Morning Slot** and **Evening/Sunset Slot** (two named fixed time-window presets, e.g., Morning 8am–1pm and Evening 3pm–8pm, each with its own configurable rate — evening/sunset typically priced higher), Daily, Multi-Day — pricing calculated from the yacht's base rates + any active off-day/pricing-rule check.
- Full vs. Shared bookings: shared bookings check and decrement remaining capacity per time slot; full bookings block the whole slot.
- Booking rules enforced at submission: minimum notice period, buffer time between bookings, min/max duration.
- Guest info form (name, phone, email) + terms-acceptance checkbox, submitted via REST, creates/links a `wp_ybs_guests` record.
- Booking statuses: `pending → confirmed → paid → completed → cancelled → no_show`.

### 4.3 Payments (built-in, non-Woo)
- Offline Payment (mark as paid manually by admin).
- PayPal (standard checkout redirect or button integration).
- Stripe (Payment Intents / Checkout).
- Settings page to choose active payment method(s) and enter API keys.
- **WooCommerce integration (full feature parity, no Pro gate):** if WooCommerce is active and selected in Settings, each booking creates a mirrored `wc_order`; native Woo coupons, tax rules, shipping/handling (if relevant), and any Woo-compatible gateway all work without restriction.

### 4.4 Admin Dashboard (React app)
Build these screens, each a route in the single-page app:
1. **Dashboard** — quick stats: today's bookings, upcoming bookings, cancelled count (`/reports/summary` endpoint — counts only, no revenue).
2. **Yachts** — list/add/edit/delete (wizard above).
3. **Bookings** — list view; admin can change status inline from the list; **no booking-details page** (that's Pro — clicking a row should show a friendly "Upgrade to Pro to view full booking details" prompt, not an error).
4. **Calendar** — view-only visual calendar of bookings across yachts.
5. **Guests** — view-only list (name, phone, email, linked booking); no edit/view-details/delete actions in the UI; a guest disappears from the list automatically (soft-filtered, not hard-deleted) when their booking is cancelled.
6. **Settings** — payment method config, tax rate, off-days/basic pricing rules, data retention policy (auto-anonymize guest data after N months), **currency symbol/code selection (display only, no live conversion required)**.

### 4.5 Frontend
- Booking form (Gutenberg block + shortcode): date/time picker respecting off-days/buffers, guest-count selector for shared yachts, live price calculation (in the selected currency), guest info fields, terms checkbox, payment method selection.
- Yacht search/filter (date, guest count, price range, **yacht class, occasion tag, location/marina — with an optional "near me" distance filter using browser geolocation**).
- Live-availability calendar per yacht.
- **Embedded map on each yacht's listing page** showing its departure point (marina/pier pin).
- **Newsletter signup capture** — simple email opt-in field/widget (stores to a basic list table or hooks into a filter for future ESP integration).

## 5. REST API (Free scope)
Implement exactly as specified in the schema doc's API map, restricted to Free-tier fields/actions:
`/yachts`, `/yachts/{id}`, `/yachts/{id}/availability`, `/bookings` (list — limited fields, create), `/bookings/{id}/status`, `/guests` (list, view-only), `/reports/summary`, `/pricing-rules` (off-days + basic rules only), `/settings`.

Every route must check capabilities server-side (`current_user_can( 'manage_ybs_bookings' )` or similar custom capability) — never rely on the React UI alone to hide Pro-gated actions.

## 6. Non-Functional Requirements
- No jQuery-based admin UI; React only, fast perceived performance (skeleton loaders, optimistic status updates).
- Internationalization-ready (all strings wrapped for translation).
- GDPR: terms-acceptance timestamp stored per guest; data-retention cron job (WP Cron) to anonymize/delete old guest data per Settings.
- Uninstall routine: clean up tables/options only if user opts in (a "remove all data on uninstall" checkbox in Settings).

## 7. Extension Points Required for Pro (build these even though Free doesn't use them)
- Action hooks: `ybs_after_booking_created`, `ybs_after_booking_status_changed`, `ybs_after_guest_created`, `ybs_before_booking_total_calculated` (filterable), `ybs_admin_menu_registered`.
- Filter hooks: `ybs_booking_price_components` (so Pro can inject add-ons/deposits into price calc), `ybs_admin_react_routes` (so Pro can register new dashboard tabs like Check-in/Emails), `ybs_rest_namespace_routes` (so Pro can register additional REST routes cleanly), `ybs_guest_list_columns` (so Pro can add columns like check-in status).
- A simple internal license/tier-check function stub: `ybs_is_pro_active()` returning false in Free — Pro plugin overrides this via its own loader so Free's code never needs to know Pro's internals.

## 8. Acceptance Criteria
- A yacht owner can install Free alone, list a yacht, and take a real booking + real payment end-to-end (offline, PayPal, Stripe, or WooCommerce) with no errors and no visible "Pro" dead-ends except the intentional booking-details upsell prompt.
- All Free-tier items in the Feature Matrix (see main plan doc) work exactly as scoped — nothing extra, nothing missing.

---

# PROMPT 2 — PRO ADD-ON PLUGIN (`yacht-booking-system-pro`)

## 1. Objective
Build an add-on plugin that extends `yacht-booking-system` (Free) with the Pro feature set, using Free's hooks/filters/REST namespace rather than duplicating any core logic. Pro should refuse to activate (with a clear admin notice) if Free is not active or is below a minimum required version.

## 2. Tech Stack
Same as Free (PHP 8+, OOP, Composer autoload, React via `@wordpress/element` etc.) — Pro's React code registers additional routes/components into Free's existing admin app shell via the `ybs_admin_react_routes` filter, rather than mounting a second app.

## 3. Dependency Check
On activation, verify:
- `yacht-booking-system` is active (use `is_plugin_active()` / a defined constant Free exposes, e.g. `YBS_VERSION`).
- Version meets minimum requirement.
- If checks fail: deactivate self, show admin notice explaining Free is required (with a link to install it if missing).

## 4. Features to Build

### 4.1 User Registration Form Builder
- Admin UI (new dashboard tab "Form Builder") to add/remove/reorder custom fields on the guest booking form beyond name/phone/email (text, dropdown, checkbox, number field types).
- Custom field values stored against the guest/booking record (new `wp_ybs_guest_meta` table, or JSON column on `wp_ybs_guests` — implementer's choice, document it).

### 4.2 Full Booking Details Page
- New dashboard route: clicking a booking row opens a full details view — guest info, all custom fields, pricing breakdown (base + add-ons + tax + deposit − discount), payment status/history, internal notes field, status-change with optional auto-email trigger.

### 4.3 Guest List — Full CRUD + Filtering
- Extend the Free guest list screen (via `ybs_guest_list_columns` filter) to add: filter bar (by date range, yacht, status, checked-in/out), edit guest details, view full guest profile (booking history), delete guest (hard delete with confirmation).

### 4.4 PDF Ticketing
- Generate a per-guest PDF ticket (booking reference, yacht, date/time, guest name, QR code) on booking confirmation.
- Downloadable from the booking details page and guest profile; optionally auto-attached to confirmation email.
- Use a PHP PDF library (e.g., `dompdf` or `mpdf` via Composer).

### 4.5 Email System
- Send email to a single guest or bulk-select multiple guests from the Guest List screen.
- New dashboard tab "Emails" — compose screen with template picker, merge tags (`{guest_name}`, `{yacht_name}`, `{booking_date}`, etc.), send/schedule.
- Email Template manager (CRUD on `wp_ybs_email_templates`): confirmation, reminder, and custom template types, WYSIWYG body editor.
- Automated reminder email X hours before departure (configurable in Settings), sent via WP Cron, logged to `wp_ybs_email_logs`.

### 4.6 QR Code Check-In System
- On booking confirmation, generate a unique `qr_token`, encode into a QR code (embedded in the PDF ticket).
- New dashboard tab "Check-In" — camera-based or manual-entry QR scanner (browser `getUserMedia` + a JS QR-decoding library) that marks `checked_in_at` via `/guests/{id}/checkin`.
- Guest List filter by checked-in/out status.
- Support check-out marking (`checked_out_at`) for full-day/multi-day charters.

### 4.7 Pricing Extensions
- Add-ons/extras: global catalog CRUD (Settings → Add-ons) + per-yacht selection (extends the yacht wizard's step 4, via a filter Free exposes for wizard step content).
- Deposit / partial payment: configurable per yacht — collect deposit now, balance later, tracked via `payment_status = deposit_paid`.
- Coupon/discount codes for **built-in payment mode** (WooCommerce coupons already work in Free natively — this is only for Offline/PayPal/Stripe mode).
- Weekday/weekend and full seasonal pricing rule types (extends Free's basic off-day-only rules to the full `wp_ybs_pricing_rules` rule-type set).
- **Peak/surge date pricing** — a new rule type (`peak`) applying a percentage markup (admin-defined, e.g., 40–100%) to named high-demand dates (New Year's Eve, major local events, public holidays), stacked on top of any active seasonal rate rather than replacing it.

### 4.8 Content & Discovery Extensions
- **Per-yacht FAQ block** — repeatable Q&A field editable from the yacht edit screen (extends the Free yacht wizard via a filter), rendered on the frontend listing.
- **Related/similar yachts** — on each yacht's frontend page, show a manually-curated list (admin picks specific yachts) or an auto-suggested list (same `yacht_class` taxonomy term + similar capacity) — implementer's choice, should be configurable in Settings.
- **Reviews/rating display** — a simple admin-entered average rating + review count field per yacht (e.g., "4.9★, 1,378 reviews"), rendered on the frontend listing. No built-in review collection system — just a manually-maintained trust signal field.

### 4.9 Reporting
- Extend Free's Dashboard summary with a "Revenue & Analytics" panel: revenue over time, revenue by yacht, occupancy rate, average booking value — new `/reports/revenue` endpoint.
- Exportable booking calendar (CSV/PDF export of a date range).

## 5. REST API (Pro scope)
Register under the same `ybs/v1` namespace via the `ybs_rest_namespace_routes` filter:
`/bookings/{id}` (full details), `/guests/{id}` (get/edit/delete), `/guests/{id}/checkin`, `/reports/revenue`, `/addons`, `/pricing-rules` (full rule types), `/tickets/{booking_id}/pdf`, `/emails/send`, `/email-templates`.

All routes enforce their own capability checks — do not assume Free's checks are sufficient, since Pro routes expose more sensitive data (full guest PII, financials).

## 6. Non-Functional Requirements
- Zero core-file edits to Free — every extension via documented hooks/filters only, so Free can update independently without breaking Pro.
- Graceful degradation: if Pro is deactivated later, Free continues working standalone with no errors (Pro-only data like custom fields/QR tokens simply becomes inert, not deleted).
- Same coding standards, i18n, and capability-check rigor as Free.

## 7. Acceptance Criteria
- With Free + Pro both active, every item in the Pro column of the Feature Matrix works end-to-end (form builder → booking → PDF ticket → QR check-in → bulk email → revenue report).
- Deactivating Pro leaves Free fully functional with no fatal errors, no orphaned broken UI elements, and no data loss to Free-tier data.
- Reactivating Pro later restores full Pro functionality without needing to reconfigure anything (settings/data persist in the DB tables).

---

## How to Use These Two Prompts
Hand **Prompt 1** to a developer (or AI coding session) first and get it fully working and tested standalone. Only then hand **Prompt 2** to a second developer/session, pointing them to Free's actual hook/filter implementation (not just this spec) so Pro integrates against what was really built, not just what was planned.
