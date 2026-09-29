=== Yacht Booking System – Boat Charter Booking Plugin ===
Contributors: magepeopleteam, aamahin
Tags: yacht booking, boat rental, charter booking, booking system, marina
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html


Turn your WordPress site into a yacht and boat charter booking platform. Show your fleet, check availability, take bookings and get paid online.

== Description ==

**Yacht Booking System by MagePeople** helps yacht owners, charter companies and marinas sell boat trips directly from their own WordPress website. Add your fleet and your rates, and guests can search, check availability, book and pay – without a middleman and without leaving your site.

Whether you rent out a single sailboat by the hour or run a fleet of luxury yachts with multi-day charters, the plugin gives you a complete booking engine that is simple to set up and easy for your guests to use.

The free plugin includes the **full booking engine** and **four payment methods**. An optional **Pro add-on** adds branded PDF tickets and invoices, quayside check-in, an email template editor and more.

= Why choose Yacht Booking System? =

* **Built for charters** – six ready-made booking types, from hourly trips to multi-day voyages.
* **Guests book in seconds** – a clean booking form with live pricing and a slide-in booking drawer.
* **Get paid your way** – Offline, Stripe, PayPal and WooCommerce, with optional deposits.
* **Easy to manage** – a friendly admin with a dashboard, calendar, guest list and bookings list.
* **No lock-in** – your bookings and guest data stay in your own WordPress database.
* **Developer friendly** – hooks, a REST API and JavaScript extension points for custom work.

= Features =

**Fleet management**

* A searchable fleet listing and a detail page for every yacht.
* A simple four-step yacht editor: Basic Info, Specs & Capacity, Pricing & Availability, and Review & Publish.
* Photos and gallery, build year, capacity, cabins, crew size and length.
* Departure point on a map – search for the marina or drop a pin.
* Yacht classes and occasion tags to help guests find the right boat.
* Frequently Asked Questions for each yacht.
* Related yachts to keep guests browsing.
* Import six demo yachts with one click so you can try everything out quickly.

###  Explore The Demo:
🌐 [Live Demo](https://wpyacht.com/)


**Flexible booking types and modes**

* Six booking types: hourly, half-day, morning slot, evening/sunset slot, full day and multi-day. Each has its own rate and time window.
* Leave a rate empty and that booking type is simply not offered.
* **Full charter** – guests book the whole yacht.
* **Shared charter** – sell individual seats priced per guest. Seats can't be oversold.
* **Both** – offer full charter and shared seats on the same yacht.

**Smart availability and pricing rules**

* Minimum notice (in hours) so you're never booked at the last minute.
* Buffer time between bookings (in minutes) for cleaning and turnaround.
* Minimum and maximum duration for hourly charters.
* Off-days and blocked date ranges.
* Seasonal price adjustments.
* Live price updates as the guest changes date, time, guests or extras.
* The booking form automatically picks the first available slot that meets your rules.

**Add-ons and extras**

* Sell optional extras such as catering, a skipper or water toys.
* Offer each add-on only on the yachts you choose.

**Deposits**

* Take a deposit as a percentage or a fixed amount.
* Set one fleet-wide rule, give a single yacht its own deposit, or switch deposits off for that yacht.
* Deposits work with Offline, Stripe and PayPal payments.

**Coupons**

* Create percentage or fixed-amount discount codes.
* Optional minimum spend, usage limit, start and expiry dates.
* Limit a code to particular yachts, or switch it off without deleting it.
* Guests see clear messages if a code isn't valid.

**A smooth guest experience**

* Booking drawer with a progress bar: Summary, Checkout, Confirmed.
* Booking summary with a full price breakdown.
* Confirmation screen with the booking reference and a ticket card.
* A secure confirmation page that shows the amount paid, any balance due and the check-in code.
* Confirmation emails sent to the guest, with a template you can customise for each yacht.
* Responsive layout that works on phones, tablets and desktops.

**Admin tools**

* Dashboard, Yachts, Bookings, Calendar, Guests, Add-ons, Coupons and Settings.
* Built-in User Guide inside the admin.
* New-booking alert emails to you and your team.
* Email tags for personalised messages, such as guest name, yacht name, dates, totals and balance due.

**Privacy and data control**

* Choose how many months to keep guest data before it is anonymised.
* Optionally remove all plugin data when the plugin is uninstalled.

= Accept payments online =

Choose any mix of these payment methods under **Yacht Booking → Settings → Payments**:

* **Offline** – guests pay later by bank transfer or at the marina. You mark the booking as paid. Deposits supported.
* **Stripe** – a secure card form right inside the booking drawer. Payments are confirmed by Stripe webhook, and the confirmation page double-checks so guests never see a false "awaiting payment". Deposits supported.
* **PayPal** – guests pay on PayPal and return to your confirmation page. Sandbox and live modes. Deposits supported.
* **WooCommerce** *(optional)* – use WooCommerce checkout inside the drawer with any WooCommerce payment gateway. WooCommerce always charges the full order total, so deposits don't apply.

= Shortcodes =

Place the plugin anywhere on your site with simple shortcodes:

* `[mageyabo_yacht_list]` – the fleet listing with class filters and a card grid. Add `class="sailing-yacht"` to start on a class tab, or `occasion="sunset"` to show only that occasion.
* `[mageyabo_yacht_search]` – a search bar (day or hourly charter, location, dates, guests and price) with results below. The date filters by real availability, dropping yachts with no bookable slot that day; pre-fill it with `date="today"` (or `date="YYYY-MM-DD"`) and use `autoload="no"` to wait for the guest to search before results appear.
* `[mageyabo_booking_form]` – the booking form and drawer for a chosen yacht.
* `[mageyabo_booking_confirmation]` – the page guests see after booking or paying.
* `[mageyabo_newsletter]` – a simple email sign-up box.
* A Gutenberg block for the booking form.

Three pages – **Search Yacht**, **Yacht List** and **Booking Confirmation** – are created for you when you activate the plugin. You can rename them freely.

= Yacht Booking System Pro (optional add-on) =

Want to go further? **Yacht Booking System Pro** is a separate add-on that works alongside the free plugin and adds:

* **Documents** – a full-detail branded PDF ticket and invoice for every booking, plus booking exports to CSV or a printable PDF report. Guests can download them from the confirmation screen and page with no login.
* **Check-in** – board guests at the quayside by scanning or typing their ticket code, see who is aboard now, and keep a filterable boarding history.
* **Emails** – edit the wording of every email, add new-booking alerts for your team, payment receipts and cancellation notices, and keep a log of every email sent.
* **Coupons** – Pro's own coupon system, with the same options as the built-in one.

= Built for developers =

Yacht Booking System is designed to be extended:

* Dozens of WordPress actions and filters for pricing, quotes, booking data, payments, emails and admin screens.
* A REST API under `mageyabo/v1` for yachts, availability, quotes, add-ons and bookings.
* JavaScript registries to extend the booking form and the admin app.
* A theme-overridable template: copy `templates/single-yacht.php` to `yourtheme/magepeople-yacht-booking-system/single-yacht.php` and edit your copy.

The Pro add-on uses only these public extension points, so your own add-on can do everything it does.

= Perfect for =

* Yacht charter companies and boat rental businesses
* Marinas and sailing clubs
* Boat tour operators and day-trip providers
* Party boat, sunset cruise and fishing charter businesses
* Independent yacht owners who want to accept bookings online

== Installation ==

= Requirements =

* WordPress 5.9 or later
* PHP 8.0 or later
* WooCommerce is optional

= Install the plugin =

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install it from **Plugins → Add New**.
2. Activate **Yacht Booking System** through the **Plugins** menu in WordPress.
3. Go to **Yacht Booking → Settings** and set your currency, tax rate and payment methods.
4. Go to **Yacht Booking → Yachts** and add your first yacht, or import the sample fleet to try things out.
5. Visit the **Yacht List** page on your site to see your fleet live.

= Using Pro features =

Install and activate **Yacht Booking System Pro** as well. It needs the free plugin to stay active.

== Frequently Asked Questions ==

= Do I need WooCommerce to use this plugin? =

No. WooCommerce is optional. The plugin has its own booking engine and works with Offline, Stripe and PayPal payments on its own. If you prefer, you can turn on WooCommerce checkout and use any WooCommerce payment gateway.

= Can I sell individual seats instead of the whole yacht? =

Yes. Set the yacht's **Booking Mode** to **Shared** or **Both**. Seats are priced per guest and can't be oversold.

= What booking types are available? =

Hourly, half-day, morning slot, evening/sunset slot, full day and multi-day. Each has its own rate and time window. Leave a rate empty to not offer that type.

= Can I take a deposit? =

Yes, with Offline, Stripe and PayPal. Set a fleet-wide deposit under **Settings → Deposits & Extras**, or give each yacht its own deposit or turn deposits off for it. WooCommerce checkout always charges the full order total, so deposits don't apply there.

= Can I stop two guests booking the same yacht at the same time? =

Yes. The plugin checks for clashes with existing bookings, and you can add a buffer between bookings and a minimum notice period. Off-days and blocked date ranges are respected too.

= Can I offer extras like catering or a skipper? =

Yes. Create add-ons under **Yacht Booking → Add-ons** and choose which yachts offer them.

= Can I offer discount codes? =

Yes. Create percentage or fixed-amount coupons under **Yacht Booking → Coupons**. When Pro is active, its coupon system takes over so a booking is never discounted twice. With WooCommerce checkout switched on, discounts are handled by WooCommerce coupons.

= How do guests find their booking after paying? =

They land on the **Booking Confirmation** page, which shows their booking, amount paid, any balance due and their check-in code. The page link is also included in the confirmation email and carries a secret key, so it can't be guessed.

= Guests land on my home page after paying. What happened? =

The Booking Confirmation page was probably deleted. Deactivate and reactivate the plugin to recreate it.

= I see "This booking does not meet the minimum notice period". =

The chosen start time is sooner than the yacht's **Minimum Notice**. Pick a later time, or lower the notice in the yacht's **Pricing & Availability** step.

= I see "This time is too close to another booking for this yacht". =

The slot falls inside the **Buffer Between Bookings** around another charter. Pick another time, or shorten the buffer.

= I see "Please choose an available payment method". =

No payment method is enabled. Turn one on under **Yacht Booking → Settings → Payments**.

= I see "The Composer autoloader is missing". =

The `vendor` folder didn't upload. Re-upload the full plugin, or run `composer install` in the plugin folder.

= Can I customise the confirmation email? =

Yes. Edit the global email under **Settings → Email**, or override it for a single yacht in the last step of the yacht editor using one of the presets (Classic, Celebration, Corporate, Short). Pro adds a full editor for every email.

= Can I change how the yacht page looks? =

Yes. Copy `templates/single-yacht.php` to `yourtheme/magepeople-yacht-booking-system/single-yacht.php` and edit your copy. Your changes stay safe when the plugin updates.

= Is my data removed if I delete the plugin? =

Only if you turn on **Remove all plugin data when uninstalled** under **Settings → Data & Privacy** first. Otherwise your data is kept.

= Can I limit how long guest data is stored? =

Yes. Under **Settings → Data & Privacy** you can choose how many months to keep guest data before it is anonymised.

= Is the plugin available in my language? =

The plugin is translation-ready, so you can translate it with any standard WordPress translation tool.

= Does it work with any theme? =

Yes. The plugin is designed to work with any properly coded WordPress theme.

= Where can I get help? =

Use the support forum on the plugin's WordPress.org page, or check the built-in **User Guide** under **Yacht Booking**.

== Screenshots ==

1. Yacht fleet listing with class filter pills and card grid.
2. Yacht search bar with Day and Hourly charter toggle.
3. Single yacht page with the booking form and live pricing.
4. Booking drawer – summary, details and payment.
5. Booking confirmation screen with ticket card and check-in code.
6. Yacht editor – Pricing & Availability step.
7. Admin dashboard and bookings list.
8. Booking calendar view.
9. Payment settings for Offline, Stripe, PayPal and WooCommerce.

== External Services ==

This plugin can connect to third-party services when you turn them on. Nothing is sent to these services unless you enable the related feature.

= Stripe =

Used to take card payments inside the booking drawer when Stripe is enabled under **Settings → Payments**. Payment details are sent directly to Stripe when a guest pays, and Stripe sends payment confirmations back to your site by webhook.

* Service: [Stripe](https://stripe.com)
* Terms of service: https://stripe.com/legal
* Privacy policy: https://stripe.com/privacy

= PayPal =

Used to take payments when PayPal is enabled under **Settings → Payments**. The guest is sent to PayPal to complete the payment and then returns to your Booking Confirmation page. Booking and payment details needed to process the payment are shared with PayPal.

* Service: [PayPal](https://www.paypal.com)
* Terms of service: https://www.paypal.com/legalhub
* Privacy policy: https://www.paypal.com/privacy

= Map and marina search =

The yacht editor's departure point search and map may load map data from an external mapping provider when you search for a marina or drop a pin. **[Add your map provider name, terms and privacy policy links here.]**

= Appneck =

This plugin uses the [Appneck](https://appneck.com) SDK to collect some telemetry data upon your confirmation, to troubleshoot problems faster and make product improvements. Appneck does not gather any data by default; the SDK only starts gathering basic telemetry data when you allow it via the admin notice.

* Service: [Appneck](https://appneck.com)
* Privacy policy: https://appneck.com/privacy-policy/


== Changelog ==

= 1.3.1 =
* New: added the Appneck SDK for opt-in telemetry and update tracking. It does not collect any data by default; it only starts after you confirm via the admin notice. See External Services in this readme.

= 1.3.0 =
* New: slide-in booking drawer. "Book Now" opens a drawer instead of a popup,
  with the booking summary (yacht, date, charter, guests, extras, live price
  breakdown) above the guest details, coupon and payment steps, and the total
  and Confirm button pinned to the bottom. Focus is trapped while it is open
  and handed back on close, and the drawer takes the page's accent colour.
* New: built-in coupon codes. Percentage or fixed discounts with a minimum
  spend, usage limit, validity dates and a per-yacht restriction, managed on a
  new Coupons screen and re-checked when the booking is created. Steps aside
  when the Pro add-on provides its own coupon system. Adds a
  `mageyabo_discount_codes` table and a `coupon_code` column on bookings
  (database version 6 - upgraded automatically).
* New: WooCommerce checkout inside the drawer. "Confirm Booking" adds the
  charter to the cart over AJAX and loads the real checkout in an iframe on a
  bare canvas, with the thank-you page staying in the drawer. Off-site
  gateways break out of the frame through a one-time token, and add-to-cart
  errors are shown in the drawer with a retry that replaces the earlier yacht
  booking in the cart.
* New: guest ticket and invoice download links on the booking success screen,
  the confirmation page and the WooCommerce thank-you page, supplied by the
  Pro add-on through the `mageyabo_booking_guest_documents` filter.
* New: the booking form defaults the date to the first day that meets the
  yacht's notice period and moves to the next free slot when that one is
  taken; the hours field starts at the yacht's minimum and stops at its
  maximum. Only booking types a yacht actually has a price for are offered.
* New: the Dates field on `[mageyabo_yacht_search]` now filters by real
  availability. Yachts with no bookable slot that day - off-days, a clashing
  booking, an unmet notice period, a mode with no price - are dropped from the
  results, and a malformed or past date is rejected instead of quietly
  ignored. Pre-fill the field with `date="today"` (or `date="YYYY-MM-DD"`),
  and use `autoload="no"` to show results only after the guest searches.
* New: `[mageyabo_yacht_list]` accepts `class=""` to start on a class tab and
  `occasion=""` to scope the listing to one occasion.
* Tweak: the yacht editor has a full-width header with Update/Publish, an FAQ
  accordion, a selectable booking mode with price panels for that mode only,
  a formatted review step, confirmation email presets and rounded fields.
* Tweak: the sample fleet import now matches the demo fleet - full
  descriptions, map pins, amenities, per-yacht FAQs, every rate and booking
  rule, and any missing classes or occasions are created. Each sample yacht
  also imports with its own five-photo gallery; photos are sideloaded once and
  reused on a retry, and a failed import rolls back rather than leaving a
  partial fleet.
* Tweak: the admin sidebar no longer lists a locked row per Pro screen
  (Check-in, Coupons, Emails, Documents). There is now a single "Pro Features"
  item opening one page that describes everything the Pro add-on unlocks, and
  it disappears once the add-on claims those screens.
* Tweak: the admin app is centered at 80% of the window, the yacht wizard's
  step header has a modern card treatment, and the Featured Image picker sits
  directly under the Publish box instead of at the bottom of the sidebar.
* Tweak: responsive fixes for the yacht page, the yacht list and the booking
  form; yacht length is shown in feet to match the admin field.
* Docs: this readme has been rewritten, and the repo now carries an agent
  guide plus product and operations maps under `docs/map/`.

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
