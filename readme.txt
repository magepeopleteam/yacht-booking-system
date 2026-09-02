=== MagePeople Yacht Booking System – Yacht Charter & Boat Rental Booking ===
Contributors: magepeopleteam, aamahin
Tags: yacht booking, boat rental, charter booking, booking system, woocommerce
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Yacht and boat charter booking for WordPress. Manage your fleet, take hourly, half-day, daily and multi-day bookings, and get paid — free.

== Description ==

**MagePeople Yacht Booking System** turns any WordPress site into a yacht charter booking platform. Add your fleet, set your rates, and let visitors check availability and book online — with no third-party booking service in the middle.

Built for yacht charter companies, boat rental businesses, sailing schools, party-boat operators and marina agencies.

= Manage your fleet =

Each yacht is added through a guided four-step editor:

* **Basic info** — description, photo gallery, departure point on a map, FAQ
* **Specs & capacity** — length, cabins, crew size, build year, guest capacity
* **Pricing & availability** — rates per booking type, operating hours, notice period, buffer between charters, duration limits
* **Review & publish** — confirmation email and booking call-to-action for that yacht

= Six booking types =

Hourly, half-day, morning slot, evening/sunset slot, full day and multi-day — each with its own rate and its own operating time window.

= Full charter or shared seats =

Sell the whole yacht as a private charter, or sell it by the seat for shared trips — or allow both on the same yacht. Shared bookings are priced per guest and seat availability is tracked automatically, so a slot can never be oversold.

= Availability that actually protects your schedule =

* Minimum notice period before a charter can start
* Buffer time between charters for turnaround
* Minimum and maximum charter duration
* Off-days and blocked date ranges
* Race-safe seat claiming, so two people booking the last seat at the same moment can't both succeed

= Take payments your way =

* **Offline / bank transfer** — mark paid manually
* **PayPal**
* **Stripe**
* **WooCommerce** — mirror each booking into a WooCommerce order and use any gateway you already have

= Frontend =

* `[yacht-list search="yes"]` — searchable, filterable fleet listing with grid and list views
* `[ybs_booking_form]` — booking form with live price calculation
* `[ybs_yacht_search]` — yacht search with date, guest, class, occasion and price filters
* A premium single-yacht details page template, overridable from your theme
* Gutenberg block for the booking form

= Confirmation emails =

Set a global confirmation email with dynamic tags (guest name, yacht, dates, total, and more), override it per yacht, and choose which booking statuses trigger it. Send a test email to yourself before going live.

= Privacy =

Optional automatic anonymization of guest records after a configurable retention period, and an opt-in "remove all data on uninstall" setting.

== External services ==

This plugin does not connect to any external service by default.

If you enable the PayPal or Stripe payment method in the plugin settings, booking payments are sent to that provider so the payment can be processed:

* **PayPal** — booking amount, currency and booking reference are sent to PayPal when a customer chooses PayPal at checkout. [Terms of Service](https://www.paypal.com/us/legalhub/useragreement-full) | [Privacy Policy](https://www.paypal.com/us/legalhub/privacy-full)
* **Stripe** — booking amount, currency and booking reference are sent to Stripe when a customer chooses Stripe at checkout. [Terms of Service](https://stripe.com/legal/ssa) | [Privacy Policy](https://stripe.com/privacy)

The departure-point map uses OpenStreetMap:

* **OpenStreetMap tiles** — when a yacht has a location set, the visitor's browser requests map tiles from OpenStreetMap's tile servers, which receives the visitor's IP address and the map area being viewed. This happens on the front end only on pages that display a yacht map. [Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/) | [Privacy Policy](https://wiki.osmfoundation.org/wiki/Privacy_Policy)
* **OpenStreetMap Nominatim** — in the admin only, when you type an address into the location search while editing a yacht, that search text is sent to Nominatim to look up coordinates. [Usage Policy](https://operations.osmfoundation.org/policies/nominatim/) | [Privacy Policy](https://wiki.osmfoundation.org/wiki/Privacy_Policy)

== Credits ==

This plugin bundles [Leaflet](https://leafletjs.com/) (BSD-2-Clause) for its maps, and the [Plus Jakarta Sans](https://github.com/tokotype/PlusJakartaSans) typeface (SIL Open Font License 1.1). Both licenses ship with the plugin.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it through **Plugins → Add New**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Yacht Booking → Settings** and set your currency, tax rate and payment method.
4. Go to **Yacht Booking → Yachts → Add Yacht** and add your first yacht.
5. Put `[yacht-list search="yes"]` on a page to show your fleet.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

No. WooCommerce is optional. The plugin includes its own offline, PayPal and Stripe payment options. If you do have WooCommerce active, you can mirror bookings into WooCommerce orders and use any gateway WooCommerce supports.

= Can I sell individual seats instead of the whole yacht? =

Yes. Each yacht can be set to full charter, shared (per seat), or both. Shared bookings are priced per guest and remaining seats are shown to the customer.

= Can different booking types have different prices? =

Yes. Hourly, half-day, morning slot, evening slot, full day and multi-day each have their own rate, and each fixed-schedule type has its own operating time window.

= Can I stop bookings on certain dates? =

Yes. Add off-day rules under **Settings → Pricing Rules** to block dates or date ranges, and set per-yacht notice periods and buffers.

= Can I customize the single yacht page? =

Yes. Copy `templates/single-yacht.php` from the plugin into `yourtheme/magepeople-yacht-booking-system/single-yacht.php` and edit it there — your copy is used instead of the plugin's.

= Is the booking data removed when I uninstall? =

Only if you ask for it. Enable "Remove all plugin data when uninstalled" under **Settings → Privacy** before deleting the plugin. Otherwise your yachts, bookings and settings are left untouched.

== Screenshots ==

1. The fleet listing with search, class filters and grid/list views.
2. The single yacht page with gallery, specs and booking widget.
3. The yacht editor — pricing and availability step.
4. Bookings list in the admin dashboard.
5. Booking calendar.
6. Settings — payments.

== Changelog ==

= 1.0.0 =
* Initial release.
