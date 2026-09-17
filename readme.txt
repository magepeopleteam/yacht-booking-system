=== MagePeople Yacht Booking System ===
Contributors: magepeopleteam, aamahin
Tags: yacht booking, boat rental, charter booking, booking system, woocommerce
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

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

= Optional extras and discounts =

Sell add-ons alongside the charter - catering, a skipper, water toys, whatever
you offer. Each add-on is priced once in the catalogue and assigned to the
yachts that offer it, so changing a price is one edit, not one per yacht.

Issue discount codes with a percentage or fixed amount off, an optional
minimum spend, a usage limit, a validity window, and an optional restriction
to particular yachts.

= Deposits =

Take a percentage or a fixed amount up front and settle the balance later.
The payment method is only asked for the deposit; the booking still records
the full total, and the guest sees the balance on their confirmation and in
their emails. Individual yachts can override the fleet-wide setting or opt
out of deposits entirely.

= Check-in =

Every booking gets a check-in code, printed on the guest's confirmation page.
The Check-in screen takes that code - typed, pasted, or sent by a barcode
scanner - and shows who is booked, what they paid and what is still owed
before you stamp them aboard, then ashore again at the end.

= Take payments your way =

* **Offline / bank transfer** — mark paid manually
* **PayPal**
* **Stripe**
* **WooCommerce** — mirror each booking into a WooCommerce order and use any gateway you already have

= Frontend =

* `[mageyabo_yacht_list search="yes"]` — searchable, filterable fleet listing with grid and list views
* `[mageyabo_booking_form]` — booking form with live price calculation
* `[mageyabo_yacht_search]` — yacht search with date, guest, class, occasion and price filters
* `[mageyabo_booking_confirmation]` — the page guests land on after paying, created for you at activation, showing the booking, what was paid, what is still due and the check-in code
* A full single-yacht details page template, overridable from your theme
* Gutenberg block for the booking form

= Emails =

Four automatic emails, each with its own editable template and a long list of
dynamic tags (guest name, yacht, dates, extras, deposit, balance and more):

* **Booking confirmation** to the guest, overridable per yacht
* **New booking alert** to you, so a sale never goes unnoticed
* **Payment received** receipt to the guest
* **Cancellation notice** to the guest

Every send is written to a log you can read in the admin, so "did the guest
ever get their confirmation?" is a question you can actually answer. Send a
test email to yourself before going live.

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

== Source code and build process ==

Nothing in this plugin is obfuscated, and no compiled file ships without its
source. All source code is included within the plugin.

= JavaScript and CSS source files =

Every compiled asset in `assets/build/` has its human-readable, unminified
source bundled inside the plugin:

* `assets/build/admin.js` — source: all `.js` files under `assets/admin/src/`, entry point `assets/admin/src/index.js`
* `assets/build/style-admin.css` — source: `assets/admin/src/style.css`
* `assets/build/frontend.js` — source: all `.js` files under `assets/frontend/src/`, entry point `assets/frontend/src/index.js`
* `assets/build/style-frontend.css` — source: `assets/frontend/src/style.css`
* `assets/build/booking-block.js` — source: `assets/frontend/src/block/`, entry point `assets/frontend/src/block/index.js`

The webpack manifest files (`assets/build/*.asset.php`) are auto-generated
by `@wordpress/scripts` and list the dependency and version maps for each
bundle.

= Build tools =

`package.json`, `package-lock.json` and `webpack.config.js` ship with the
plugin. To regenerate every bundle from source:

`npm install`
`npm run build`

That runs `wp-scripts build` (@wordpress/scripts, webpack) using the entry
points declared in `webpack.config.js`, writing output to `assets/build/`.
Use `npm run start` for a watching development build.

= PHP source =

PHP classes are autoloaded by Composer from `includes/` (PSR-4 namespace
`MageYaBo\`). The autoloader in `vendor/` is generated from the shipped
`composer.json` with:

`composer dump-autoload`

= Third-party libraries =

Leaflet (BSD-2-Clause) and Plus Jakarta Sans (SIL Open Font License 1.1)
are bundled unmodified in `assets/frontend/vendor/leaflet/` and
`assets/frontend/fonts/` respectively. Each carries its own upstream
license file.

= Public repository =

Development happens in the open at
https://github.com/magepeopleteam/yacht-booking-system

== Credits ==

This plugin bundles [Leaflet](https://leafletjs.com/) (BSD-2-Clause) for its maps, and the [Plus Jakarta Sans](https://github.com/tokotype/PlusJakartaSans) typeface (SIL Open Font License 1.1). Both licenses ship with the plugin.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it through **Plugins → Add New**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Yacht Booking → Settings** and set your currency, tax rate and payment method.
4. Go to **Yacht Booking → Yachts → Add New Yacht** and add your first yacht.
5. Put `[mageyabo_yacht_list search="yes"]` on a page to show your fleet.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

No. WooCommerce is optional. The plugin includes its own offline, PayPal and Stripe payment options. If you do have WooCommerce active, you can mirror bookings into WooCommerce orders and use any gateway WooCommerce supports.

= Can I sell individual seats instead of the whole yacht? =

Yes. Each yacht can be set to full charter, shared (per seat), or both. Shared bookings are priced per guest and remaining seats are shown to the customer.

= Can different booking types have different prices? =

Yes. Hourly, half-day, morning slot, evening slot, full day and multi-day each have their own rate, and each fixed-schedule type has its own operating time window.

= Can I stop bookings on certain dates? =

Yes. Add off-day rules under **Settings → Pricing Rules** to block dates or date ranges, and set per-yacht notice periods and buffers.

= Can I take a deposit instead of the full amount? =

Yes. Under **Settings → Deposits & Extras**, choose a percentage or a fixed
amount. The gateway is only asked for the deposit; the booking records the
full total and shows the guest their remaining balance. A yacht can override
this or switch deposits off for itself.

Deposits apply to the built-in Offline, PayPal and Stripe methods. The
WooCommerce path always charges the full amount, because WooCommerce collects
the order total at checkout.

= Can I sell extras like catering or a skipper? =

Yes. Create them under **Yacht Booking → Add-ons**, then tick the ones each
yacht offers in that yacht's Pricing step. Guests pick them on the booking
form and the price updates live.

= Where do guests land after paying? =

On the **Booking Confirmation** page, created automatically when you activate
the plugin. It shows the booking, the amount paid, any balance due and the
check-in code. The link is keyed to the booking's own token, so it cannot be
guessed from a booking number.

= Can I customize the single yacht page? =

Yes. Copy `templates/single-yacht.php` from the plugin into `yourtheme/magepeople-yacht-booking-system/single-yacht.php` and edit it there — your copy is used instead of the plugin's.

= Is the booking data removed when I uninstall? =

Only if you ask for it. Enable "Remove all plugin data when uninstalled" under **Settings → Data & Privacy** before deleting the plugin. Otherwise your yachts, bookings and settings are left untouched.

== Changelog ==

= 1.2.0 =
* Coupons, guest check-in and the email template editor have moved into the
  separate Yacht Booking System Pro add-on. Nothing is deleted by the update:
  existing coupons, email templates, the mail log and every check-in stamp stay
  in the database, and come back as soon as the add-on is installed.
* This plugin still sends the guest's booking confirmation on its own. The
  add-on is what makes its wording editable and adds the operator alert,
  payment receipt, cancellation notice and send log.
* New: add-ons can extend the booking form, the bookings list, the quote and
  the mail pipeline through documented filters, instead of needing changes
  here - including actions on a booking row, which the list and the details
  panel render as buttons.
* Fix: the booking details panel no longer shows check-in buttons when no
  add-on provides check-in; they posted to a route that did not exist.
* The booking details panel (the eye button on the Bookings list, and a click
  on a booking row) has moved into the Pro add-on. Without it the eye button
  is shown locked and leads to what the panel offers. No booking data changes:
  internal notes and payment references stay on the booking.
* The Check-in column on the Bookings list carries a Pro tag when the add-on is
  not installed, linking to what check-in does, instead of a dash on every row.
* Fix: the admin app now starts once the page has finished loading, so an
  add-on's screens are always registered before the first render - on a slow
  connection a Pro screen could briefly show its locked teaser.

= 1.1.0 =
* Fix: a WooCommerce coupon applied at checkout is now recorded on the booking.
  The booking kept the pre-discount quote while the order charged less, so
  dashboard revenue overstated every order a coupon had touched.
* Fix: date and time fields no longer render with the calendar icon sitting on
  top of the text, or greyed out as though disabled.
* New: the Coupons screen says so when WooCommerce checkout is handling
  payment, since WooCommerce applies its own coupons and codes created here
  are never offered to a guest.
* New: booking confirmation page. PayPal and Stripe now return guests to a real
  confirmation instead of the site's home page.
* New: "new booking" alert email to the operator.
* New: booking details panel in the admin, with the full price breakdown,
  payment reference, add-on lines, timestamps and internal notes.
* New: add-ons - a catalogue of optional extras, assignable per yacht and
  selectable on the booking form.
* New: coupon codes, with percentage or fixed discounts, minimum spend, usage
  limits, validity dates and per-yacht restrictions.
* New: deposits - take a percentage or fixed amount up front, with a per-yacht
  override.
* New: check-in and check-out, with a check-in code on every booking and a
  dedicated boarding-desk screen - including a History tab logging every stamp,
  filterable by guest, yacht, date range and whether they are still aboard,
  and headline counts for who is aboard now and who is still to board today.
* New: editable templates for all four automatic emails, plus a send log.
* New: pricing rules can now be limited to particular days of the week and to a
  single yacht from the admin - the engine already supported both.
* Fix: switching email template, or leaving a screen holding a rich-text
  editor, could crash that screen with "Failed to execute 'removeChild' on
  'Node'". The classic editor now owns its own DOM subtree rather than
  handing React's node to TinyMCE.
* New: Clear history on the check-in log, scoped to the filters on screen and
  confirmed with a real count (and a warning when any of them are aboard).
* Tweak: the bookings list's Actions column uses icon buttons instead of text.
* Tweak: the Pricing Rules screen is laid out properly - the rule form is
  grouped instead of seven controls on one line, weekdays are toggle pills,
  and the rule you are building is described in plain words before you save it.
* Fix: the live quote on a yacht page now stacks its line items instead of
  squeezing them onto one row, and the "Estimated total" label no longer
  appears on unrelated notices such as the confirmation page's status banner.
  That label is now translatable.
* Fix: bookings and guests now show the booking's own reference (YB-000123)
  instead of a bare dash. Only bookings taken through WooCommerce ever had an
  order number, so every other booking looked like it was missing one.
* Fix: changing a booking's status now moves its payment status with it.
  Marking an offline booking "Completed" used to leave it flagged unpaid, so
  the guest's confirmation, the check-in desk and the emails all kept showing a
  balance due on a booking that was settled. Existing bookings are repaired on
  upgrade.
* Fix: a cancelled booking no longer shows as confirmed on the guest's
  confirmation page.

= 1.0.0 =
* Initial release.
