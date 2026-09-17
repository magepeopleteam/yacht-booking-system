<?php
namespace MageYaBo\Notifications;

use MageYaBo\Booking\AddonRepository;
use MageYaBo\Booking\BookingRepository;
use MageYaBo\Booking\GuestRepository;
use MageYaBo\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's mail pipeline, and the one email it sends on its own: the
 * guest's booking confirmation.
 *
 * The pipeline is deliberately separate from the content. This class owns what
 * a `{tag}` means, which address mail comes from, and the single point every
 * send is announced at - so anything that adds an email gets consistent
 * tags, a consistent sender and a consistent log entry without restating any
 * of it. The Pro add-on uses exactly this: it swaps the body in through
 * `mageyabo_email_template` and sends its own types through `send()`, rather
 * than building a second mailer beside this one.
 *
 * Content resolution for the confirmation: a yacht with its own confirmation
 * body (Step 4 of the wizard) overrides everything; otherwise the wording
 * stored in Settings is used; otherwise the built-in default below.
 */
class BookingEmailer {

	const TYPE_CONFIRMATION = 'booking_confirmation';

	public static function register() {
		add_action( 'mageyabo_after_booking_created', array( __CLASS__, 'on_booking_created' ), 10, 2 );
		add_action( 'mageyabo_after_booking_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 3 );
	}

	public static function on_booking_created( $booking_id, $fields ) {
		self::maybe_send_confirmation( $booking_id, $fields['status'] ?? 'pending' );
	}

	public static function on_status_changed( $booking_id, $new_status, $old_status ) {
		if ( $new_status === $old_status ) {
			return;
		}

		self::maybe_send_confirmation( $booking_id, $new_status );
	}

	/**
	 * Both creation and a later status change can be the "confirmed" moment,
	 * depending on how the site's payment flow is set up, so the trigger is
	 * the status rather than the event.
	 */
	private static function maybe_send_confirmation( $booking_id, $status ) {
		$trigger_statuses = (array) Settings::get( 'email_trigger_statuses', array( 'pending' ) );

		if ( ! in_array( $status, $trigger_statuses, true ) ) {
			return;
		}

		self::send( $booking_id, self::TYPE_CONFIRMATION );
	}

	/**
	 * Sends one email about one booking.
	 *
	 * Public because it is the supported way for an add-on to send mail about
	 * a booking: pass a type, get this plugin's tags, sender and logging.
	 *
	 * @param int    $booking_id
	 * @param string $type      Template type. Anything beyond the built-in
	 *                          confirmation has to be supplied by a
	 *                          `mageyabo_email_template` filter.
	 * @param string $recipient Overrides the guest's own address - used for
	 *                          operator-facing mail.
	 *
	 * @return bool Whether wp_mail() accepted it.
	 */
	public static function send( $booking_id, $type = self::TYPE_CONFIRMATION, $recipient = '' ) {
		if ( ! Settings::get( 'email_enabled', true ) ) {
			return false;
		}

		$booking = BookingRepository::find( $booking_id );

		if ( ! $booking ) {
			return false;
		}

		$guest     = GuestRepository::find( (int) $booking['guest_id'] ) ?: array();
		$recipient = $recipient ?: ( $guest['email'] ?? '' );

		if ( ! is_email( $recipient ) ) {
			return false;
		}

		$template = self::template_for( $type, $booking );

		// An empty body means "this site does not send this email" - a blank
		// message is worse than none.
		if ( '' === trim( wp_strip_all_tags( (string) $template['body'] ) ) ) {
			return false;
		}

		return self::dispatch( $recipient, $template, $booking, $guest, $type );
	}

	/**
	 * The wording that would actually be used for one email.
	 *
	 * Public so the test-email preview resolves exactly what a real send
	 * would, add-on templates included, instead of keeping its own idea of
	 * what the confirmation says.
	 *
	 * @return array{subject: string, body: string}
	 */
	public static function template_for( $type, array $booking = array() ) {
		$template = array(
			'subject' => '',
			'body'    => '',
		);

		if ( self::TYPE_CONFIRMATION === $type ) {
			$template['subject'] = (string) Settings::get( 'email_subject', '' );
			$template['body']    = (string) Settings::get( 'email_body', '' );

			if ( '' === trim( $template['body'] ) ) {
				$template = self::default_confirmation();
			}
		}

		/**
		 * The seam the Pro add-on's editable templates arrive through, and the
		 * only way a type this plugin has no built-in body for gets one.
		 *
		 * @param array  $template {subject, body}.
		 * @param string $type
		 * @param array  $booking
		 */
		$template = (array) apply_filters( 'mageyabo_email_template', $template, $type, $booking );

		// Applied last, deliberately. A yacht-specific body is documented as
		// overriding everything, so it has to beat an add-on's editable
		// template too - resolving it before the filter would mean installing
		// the add-on silently discarded every per-yacht email on the site.
		//
		// It is a full override: its own subject (or the global one, if the
		// yacht left that blank) goes with it, rather than mixing a global
		// body with a yacht subject.
		if ( self::TYPE_CONFIRMATION === $type && ! empty( $booking['yacht_id'] ) ) {
			$yacht_subject = trim( (string) get_post_meta( (int) $booking['yacht_id'], 'mageyabo_confirmation_email_subject', true ) );
			$yacht_body    = trim( (string) get_post_meta( (int) $booking['yacht_id'], 'mageyabo_confirmation_email_body', true ) );

			if ( '' !== $yacht_body ) {
				$template['body'] = $yacht_body;

				if ( '' !== $yacht_subject ) {
					$template['subject'] = $yacht_subject;
				}
			}
		}

		return $template;
	}

	/**
	 * The built-in confirmation, used when nothing has been customised. Plain
	 * and short on purpose: it has to read sensibly on a site that never opens
	 * the email settings at all.
	 */
	private static function default_confirmation() {
		return array(
			'subject' => __( 'Your booking at {site_name} is confirmed', 'magepeople-yacht-booking-system' ),
			'body'    => __(
				'<p>Hi {guest_name},</p>
<p>Thank you for your booking. Here are the details:</p>
<p><strong>{yacht_name}</strong><br />
Booking reference: {booking_id}<br />
Starts: {start_date} at {start_time}<br />
Ends: {end_date} at {end_time}<br />
Guests: {guest_count}</p>
<p>Total: {total_price}<br />
Paid: {amount_paid}<br />
Balance due: {balance_due}</p>
<p>You can view your booking here: {confirmation_url}</p>
<p>We look forward to welcoming you aboard.</p>
<p>{site_name}</p>',
				'magepeople-yacht-booking-system'
			),
		);
	}

	private static function dispatch( $recipient, array $template, array $booking, array $guest, $type ) {
		$tags = self::build_tags( $booking, $guest, (int) $booking['yacht_id'] );

		$subject = strtr( (string) $template['subject'], $tags );
		$body    = strtr( (string) $template['body'], $tags );

		$from_name    = Settings::get( 'email_from_name', '' ) ?: get_bloginfo( 'name' );
		$from_address = Settings::get( 'email_from_address', '' ) ?: get_option( 'admin_email' );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_address ),
		);

		$sent = wp_mail( $recipient, $subject, wpautop( $body ), $headers );

		/**
		 * Every send passes through here, successful or not - the Pro add-on's
		 * mail log is built entirely from this.
		 *
		 * @param bool   $sent
		 * @param string $recipient
		 * @param string $type
		 * @param array  $booking
		 * @param string $subject
		 */
		do_action( 'mageyabo_after_email_sent', (bool) $sent, $recipient, $type, $booking, $subject );

		return $sent;
	}

	/**
	 * The tag vocabulary, filled in for one booking.
	 *
	 * Public so an add-on's own templates resolve exactly the same tags to
	 * exactly the same values - two tag maps that drifted apart would mean a
	 * `{balance_due}` that meant one thing in one email and another elsewhere.
	 */
	public static function build_tags( array $booking, array $guest, $yacht_id ) {
		$currency_symbol = Settings::get( 'currency_symbol', '$' );
		$date_format     = get_option( 'date_format' );
		$time_format     = get_option( 'time_format' );

		$total   = (float) $booking['total_price'];
		$deposit = (float) ( $booking['deposit_amount'] ?? 0 );
		$paid    = \MageYaBo\Booking\PricingEngine::amount_paid( $booking );
		$balance = \MageYaBo\Booking\PricingEngine::balance_due( $booking );

		$money = static function ( $amount ) use ( $currency_symbol ) {
			return esc_html( $currency_symbol . number_format( (float) $amount, 2 ) );
		};

		// Escaped here rather than at the call site: these values are guest- and
		// post-supplied and get substituted into an HTML email body, so this is
		// their point of output.
		$tags = array(
			'{guest_name}'      => esc_html( $guest['name'] ?? '' ),
			'{guest_email}'     => esc_html( $guest['email'] ?? '' ),
			'{guest_phone}'     => esc_html( $guest['phone'] ?? '' ),
			'{yacht_name}'      => esc_html( get_the_title( $yacht_id ) ),
			'{booking_id}'      => esc_html( mageyabo_booking_reference( (int) $booking['id'] ) ),
			'{booking_type}'    => esc_html( ucwords( str_replace( '_', ' ', $booking['booking_type'] ) ) ),
			'{booking_mode}'    => esc_html( ucfirst( $booking['booking_mode'] ) ),
			'{start_date}'      => esc_html( date_i18n( $date_format, strtotime( $booking['start_datetime'] ) ) ),
			'{start_time}'      => esc_html( date_i18n( $time_format, strtotime( $booking['start_datetime'] ) ) ),
			'{end_date}'        => esc_html( date_i18n( $date_format, strtotime( $booking['end_datetime'] ) ) ),
			'{end_time}'        => esc_html( date_i18n( $time_format, strtotime( $booking['end_datetime'] ) ) ),
			'{guest_count}'     => (int) $booking['guest_count'],
			'{addons}'          => self::addons_html( (int) $booking['id'] ),
			'{addons_total}'    => $money( $booking['addons_total'] ?? 0 ),
			'{discount_total}'  => $money( $booking['discount_total'] ?? 0 ),
			'{tax_total}'       => $money( $booking['tax_total'] ?? 0 ),
			'{deposit_amount}'  => $money( $deposit ),
			'{amount_paid}'     => $money( $paid ),
			'{balance_due}'     => $money( $balance ),
			'{total_price}'     => $money( $total ),
			'{status}'          => esc_html( ucfirst( $booking['status'] ) ),
			'{payment_method}'  => esc_html( ucfirst( str_replace( '_', ' ', (string) $booking['payment_method'] ) ) ),
			'{payment_status}'  => esc_html( ucfirst( (string) $booking['payment_status'] ) ),
			'{ticket_code}'     => esc_html( (string) ( $booking['qr_token'] ?? '' ) ),
			'{confirmation_url}' => esc_url( mageyabo_confirmation_url( (int) $booking['id'], (string) ( $booking['qr_token'] ?? '' ) ) ),
			'{admin_booking_url}' => esc_url( admin_url( 'admin.php?page=magepeople-yacht-booking-system#/bookings' ) ),
			'{site_name}'       => esc_html( get_bloginfo( 'name' ) ),
			'{site_url}'        => esc_url( home_url( '/' ) ),
		);

		/**
		 * Lets an add-on contribute tags of its own - the Pro add-on adds
		 * `{coupon_code}` this way, since the free plugin has no coupons.
		 *
		 * @param array $tags
		 * @param array $booking
		 * @param array $guest
		 */
		return (array) apply_filters( 'mageyabo_email_tags', $tags, $booking, $guest );
	}

	/**
	 * Stand-in values for every tag when there's no real booking to pull
	 * from - used only by the "Send Test Email" preview.
	 *
	 * Kept beside build_tags() deliberately: a preview that sampled a
	 * different set of tags than a real send resolves would be a preview of
	 * nothing in particular.
	 */
	public static function sample_tags( $to = '' ) {
		$currency_symbol = Settings::get( 'currency_symbol', '$' );
		$date_format     = get_option( 'date_format' );
		$time_format     = get_option( 'time_format' );
		$now             = time();

		$money = static function ( $amount ) use ( $currency_symbol ) {
			return $currency_symbol . number_format( (float) $amount, 2 );
		};

		$tags = array(
			'{guest_name}'      => esc_html__( 'John Doe', 'magepeople-yacht-booking-system' ),
			'{guest_email}'     => esc_html( $to ?: 'john@example.com' ),
			'{guest_phone}'     => '+1 555 0100',
			'{yacht_name}'      => esc_html__( 'Sample Yacht', 'magepeople-yacht-booking-system' ),
			'{booking_id}'      => 'YB-012345',
			'{booking_type}'    => __( 'Daily', 'magepeople-yacht-booking-system' ),
			'{booking_mode}'    => __( 'Full', 'magepeople-yacht-booking-system' ),
			'{start_date}'      => date_i18n( $date_format, $now ),
			'{start_time}'      => date_i18n( $time_format, $now ),
			'{end_date}'        => date_i18n( $date_format, $now + DAY_IN_SECONDS ),
			'{end_time}'        => date_i18n( $time_format, $now + DAY_IN_SECONDS ),
			'{guest_count}'     => '4',
			'{addons}'          => '<ul><li>' . esc_html__( 'Catering package', 'magepeople-yacht-booking-system' ) . ' &times; 1 - ' . $money( 150 ) . '</li></ul>',
			'{addons_total}'    => $money( 150 ),
			'{discount_total}'  => $money( 0 ),
			'{tax_total}'       => $money( 0 ),
			'{deposit_amount}'  => $money( 300 ),
			'{amount_paid}'     => $money( 300 ),
			'{balance_due}'     => $money( 915 ),
			'{total_price}'     => $money( 1215 ),
			'{status}'          => __( 'Pending', 'magepeople-yacht-booking-system' ),
			'{payment_method}'  => __( 'Offline', 'magepeople-yacht-booking-system' ),
			'{payment_status}'  => __( 'Unpaid', 'magepeople-yacht-booking-system' ),
			'{ticket_code}'     => 'SAMPLETICKET0000',
			'{confirmation_url}' => esc_url( mageyabo_confirmation_url() ),
			'{admin_booking_url}' => esc_url( admin_url( 'admin.php?page=magepeople-yacht-booking-system#/bookings' ) ),
			'{site_name}'       => get_bloginfo( 'name' ),
			'{site_url}'        => home_url( '/' ),
		);

		/**
		 * Sample values for tags an add-on contributes via
		 * `mageyabo_email_tags`, so its own tags preview as something readable
		 * rather than being left in the body as literal braces.
		 *
		 * @param array  $tags
		 * @param string $to
		 */
		return (array) apply_filters( 'mageyabo_email_sample_tags', $tags, $to );
	}

	/**
	 * `{addons}` renders as a list, or as nothing at all when the booking has
	 * no extras - a template that always printed an empty <ul> would look
	 * broken on the majority of bookings.
	 */
	private static function addons_html( $booking_id ) {
		$lines = AddonRepository::for_booking( $booking_id );

		if ( ! $lines ) {
			return '';
		}

		$items = '';

		foreach ( $lines as $line ) {
			$items .= sprintf(
				'<li>%s &times; %d - %s</li>',
				esc_html( $line['name'] ),
				(int) $line['quantity'],
				esc_html( mageyabo_format_price( $line['subtotal'] ) )
			);
		}

		return '<ul>' . $items . '</ul>';
	}
}
