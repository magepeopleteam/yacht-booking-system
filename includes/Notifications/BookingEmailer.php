<?php
namespace Ybs\Notifications;

use Ybs\Booking\BookingRepository;
use Ybs\Booking\GuestRepository;
use Ybs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends the guest a booking confirmation email when a booking's status
 * enters one of the admin-configured trigger statuses (Settings >
 * `email_trigger_statuses`) - both on initial creation and on any later
 * status change, since either can be the "confirmed" moment depending on
 * how the site's payment flow is set up.
 *
 * Content resolution: a yacht with its own confirmation email body (Step 4
 * of the wizard) overrides the global template from Settings entirely;
 * otherwise the global template is used. Either way, `{tag}` placeholders
 * in the subject/body are replaced with the booking's real details.
 */
class BookingEmailer {

	public static function register() {
		add_action( 'ybs_after_booking_created', array( __CLASS__, 'on_booking_created' ), 10, 2 );
		add_action( 'ybs_after_booking_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 3 );
	}

	public static function on_booking_created( $booking_id, $fields ) {
		self::maybe_send( $booking_id, $fields['status'] ?? 'pending' );
	}

	public static function on_status_changed( $booking_id, $new_status, $old_status ) {
		if ( $new_status === $old_status ) {
			return;
		}

		self::maybe_send( $booking_id, $new_status );
	}

	private static function maybe_send( $booking_id, $status ) {
		if ( ! Settings::get( 'email_enabled', true ) ) {
			return;
		}

		$trigger_statuses = (array) Settings::get( 'email_trigger_statuses', array( 'pending' ) );

		if ( ! in_array( $status, $trigger_statuses, true ) ) {
			return;
		}

		self::send( $booking_id );
	}

	private static function send( $booking_id ) {
		$booking = BookingRepository::find( $booking_id );

		if ( ! $booking ) {
			return;
		}

		$guest = GuestRepository::find( (int) $booking['guest_id'] );

		if ( ! $guest || empty( $guest['email'] ) ) {
			return;
		}

		$yacht_id = (int) $booking['yacht_id'];

		$yacht_subject = trim( (string) get_post_meta( $yacht_id, 'confirmation_email_subject', true ) );
		$yacht_body    = trim( (string) get_post_meta( $yacht_id, 'confirmation_email_body', true ) );

		// A yacht-specific body is a deliberate full override - its own
		// subject (or the global one, if the yacht left that blank) goes
		// with it rather than mixing a global body with a yacht subject.
		$subject_template = '' !== $yacht_body && '' !== $yacht_subject
			? $yacht_subject
			: Settings::get( 'email_subject', '' );

		$body_template = '' !== $yacht_body ? $yacht_body : Settings::get( 'email_body', '' );

		if ( '' === trim( $body_template ) ) {
			return;
		}

		$tags = self::build_tags( $booking, $guest, $yacht_id );

		$subject = strtr( $subject_template, $tags );
		$body    = strtr( $body_template, $tags );

		$from_name    = Settings::get( 'email_from_name', '' ) ?: get_bloginfo( 'name' );
		$from_address = Settings::get( 'email_from_address', '' ) ?: get_option( 'admin_email' );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_address ),
		);

		wp_mail( $guest['email'], $subject, wpautop( $body ), $headers );
	}

	private static function build_tags( array $booking, array $guest, $yacht_id ) {
		$currency_symbol = Settings::get( 'currency_symbol', '$' );
		$date_format     = get_option( 'date_format' );
		$time_format     = get_option( 'time_format' );

		// Escaped here rather than at the call site: these values are guest- and
		// post-supplied and get substituted into an HTML email body, so this is
		// their point of output.
		return array(
			'{guest_name}'   => esc_html( $guest['name'] ?? '' ),
			'{guest_email}'  => esc_html( $guest['email'] ?? '' ),
			'{guest_phone}'  => esc_html( $guest['phone'] ?? '' ),
			'{yacht_name}'   => esc_html( get_the_title( $yacht_id ) ),
			'{booking_id}'   => (int) $booking['id'],
			'{booking_type}' => esc_html( ucwords( str_replace( '_', ' ', $booking['booking_type'] ) ) ),
			'{booking_mode}' => esc_html( ucfirst( $booking['booking_mode'] ) ),
			'{start_date}'   => esc_html( date_i18n( $date_format, strtotime( $booking['start_datetime'] ) ) ),
			'{start_time}'   => esc_html( date_i18n( $time_format, strtotime( $booking['start_datetime'] ) ) ),
			'{end_date}'     => esc_html( date_i18n( $date_format, strtotime( $booking['end_datetime'] ) ) ),
			'{end_time}'     => esc_html( date_i18n( $time_format, strtotime( $booking['end_datetime'] ) ) ),
			'{guest_count}'  => (int) $booking['guest_count'],
			'{total_price}'  => esc_html( $currency_symbol . number_format( (float) $booking['total_price'], 2 ) ),
			'{status}'       => esc_html( ucfirst( $booking['status'] ) ),
			'{site_name}'    => esc_html( get_bloginfo( 'name' ) ),
			'{site_url}'     => esc_url( home_url( '/' ) ),
		);
	}

	/**
	 * Stand-in values for every tag when there's no real booking to pull
	 * from - used only by the "Send Test Email" preview.
	 */
	public static function sample_tags( $to = '' ) {
		$currency_symbol = Settings::get( 'currency_symbol', '$' );
		$date_format      = get_option( 'date_format' );
		$time_format      = get_option( 'time_format' );
		$now              = time();

		return array(
			'{guest_name}'   => esc_html__( 'John Doe', 'magepeople-yacht-booking-system' ),
			'{guest_email}'  => esc_html( $to ?: 'john@example.com' ),
			'{guest_phone}'  => '+1 555 0100',
			'{yacht_name}'   => esc_html__( 'Sample Yacht', 'magepeople-yacht-booking-system' ),
			'{booking_id}'   => '12345',
			'{booking_type}' => __( 'Daily', 'magepeople-yacht-booking-system' ),
			'{booking_mode}' => __( 'Full', 'magepeople-yacht-booking-system' ),
			'{start_date}'   => date_i18n( $date_format, $now ),
			'{start_time}'   => date_i18n( $time_format, $now ),
			'{end_date}'     => date_i18n( $date_format, $now + DAY_IN_SECONDS ),
			'{end_time}'     => date_i18n( $time_format, $now + DAY_IN_SECONDS ),
			'{guest_count}'  => '4',
			'{total_price}'  => $currency_symbol . number_format( 1200, 2 ),
			'{status}'       => __( 'Pending', 'magepeople-yacht-booking-system' ),
			'{site_name}'    => get_bloginfo( 'name' ),
			'{site_url}'     => home_url( '/' ),
		);
	}
}
