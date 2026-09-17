<?php
namespace MageYaBo\Frontend;

use MageYaBo\Booking\AddonRepository;
use MageYaBo\Booking\BookingRepository;
use MageYaBo\Booking\GuestRepository;
use MageYaBo\Booking\PricingEngine;
use MageYaBo\Payments\StripeGateway;
use MageYaBo\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where a guest lands after paying.
 *
 * Both hosted gateways used to return the browser to the site root with
 * `?mageyabo_payment=success` on it and nothing anywhere read that, so a
 * guest who had just paid was dropped on the home page with no
 * acknowledgement. They now return here, to the page the installer creates,
 * and this shortcode renders the booking they just made.
 *
 * Access is by `qr_token`, never by booking id alone: the id is a small
 * sequential integer and this page prints a guest's name, phone number and
 * itinerary.
 */
class BookingConfirmation {

	const SHORTCODE = 'mageyabo_booking_confirmation';

	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Resolves the booking named in the query string, if the key matches.
	 *
	 * @return array|null
	 */
	private static function requested_booking() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a read-only lookup authorised by the booking's own secret token, not by a form submission.
		$booking_id = isset( $_GET['mageyabo_booking'] ) ? absint( wp_unslash( $_GET['mageyabo_booking'] ) ) : 0;
		$key        = isset( $_GET['mageyabo_key'] ) ? sanitize_text_field( wp_unslash( $_GET['mageyabo_key'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $booking_id || '' === $key ) {
			return null;
		}

		$booking = BookingRepository::find( $booking_id );

		// hash_equals(), not ==: this is a secret compared against
		// attacker-supplied input, so the comparison must not leak how much
		// of the token was right through how long it took.
		if ( ! $booking || ! hash_equals( (string) $booking['qr_token'], $key ) ) {
			return null;
		}

		return $booking;
	}

	/**
	 * Stripe's embedded checkout returns before the webhook necessarily
	 * has - ask Stripe directly so the guest is not told "awaiting payment"
	 * for a payment they just completed. The webhook remains the source of
	 * truth; this only brings the good news forward.
	 */
	private static function maybe_reconcile( array $booking ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only; the value is handed straight back to Stripe to look up.
		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';

		if ( '' === $session_id || 'paid' === $booking['payment_status'] ) {
			return $booking;
		}

		StripeGateway::reconcile_session( (int) $booking['id'], $session_id );

		return BookingRepository::find( (int) $booking['id'] ) ?: $booking;
	}

	public static function render() {
		wp_enqueue_style( 'mageyabo-frontend' );

		$booking = self::requested_booking();

		if ( ! $booking ) {
			return '<div class="ybs-confirmation ybs-notice is-info">' .
				esc_html__( 'We could not find that booking. Please use the link from your confirmation email.', 'magepeople-yacht-booking-system' ) .
				'</div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only branch selector from the gateway's own return URL.
		$outcome = isset( $_GET['mageyabo_payment'] ) ? sanitize_key( wp_unslash( $_GET['mageyabo_payment'] ) ) : '';

		if ( 'cancelled' !== $outcome ) {
			$booking = self::maybe_reconcile( $booking );
		}

		$guest    = GuestRepository::find( (int) $booking['guest_id'] ) ?: array();
		$addons   = AddonRepository::for_booking( (int) $booking['id'] );
		$currency = Settings::get( 'currency_symbol', '$' );

		$total   = (float) $booking['total_price'];
		$deposit = (float) $booking['deposit_amount'];
		$paid    = PricingEngine::amount_paid( $booking );
		$balance = PricingEngine::balance_due( $booking );

		ob_start();
		?>
		<div class="ybs-confirmation">
			<?php echo wp_kses_post( self::banner( $booking, $outcome ) ); ?>

			<div class="ybs-confirmation__card">
				<div class="ybs-confirmation__head">
					<h3><?php echo esc_html( get_the_title( (int) $booking['yacht_id'] ) ); ?></h3>
					<span class="ybs-badge status-<?php echo esc_attr( $booking['status'] ); ?>">
						<?php echo esc_html( ucfirst( str_replace( '-', ' ', $booking['status'] ) ) ); ?>
					</span>
				</div>

				<dl class="ybs-confirmation__details">
					<dt><?php esc_html_e( 'Booking reference', 'magepeople-yacht-booking-system' ); ?></dt>
					<dd><?php echo esc_html( mageyabo_booking_reference( (int) $booking['id'] ) ); ?></dd>

					<dt><?php esc_html_e( 'Guest', 'magepeople-yacht-booking-system' ); ?></dt>
					<dd><?php echo esc_html( $guest['name'] ?? '' ); ?></dd>

					<dt><?php esc_html_e( 'Charter', 'magepeople-yacht-booking-system' ); ?></dt>
					<dd>
						<?php echo esc_html( mageyabo_format_datetime( $booking['start_datetime'] ) ); ?>
						&ndash;
						<?php echo esc_html( mageyabo_format_datetime( $booking['end_datetime'] ) ); ?>
					</dd>

					<dt><?php esc_html_e( 'Guests', 'magepeople-yacht-booking-system' ); ?></dt>
					<dd><?php echo esc_html( (int) $booking['guest_count'] ); ?></dd>

					<?php if ( $addons ) : ?>
						<dt><?php esc_html_e( 'Extras', 'magepeople-yacht-booking-system' ); ?></dt>
						<dd>
							<?php foreach ( $addons as $addon ) : ?>
								<div>
									<?php
									printf(
										'%s &times; %d &mdash; %s',
										esc_html( $addon['name'] ),
										(int) $addon['quantity'],
										esc_html( mageyabo_format_price( $addon['subtotal'], $currency ) )
									);
									?>
								</div>
							<?php endforeach; ?>
						</dd>
					<?php endif; ?>

					<?php if ( ! empty( $booking['coupon_code'] ) ) : ?>
						<dt><?php esc_html_e( 'Coupon', 'magepeople-yacht-booking-system' ); ?></dt>
						<dd>
							<?php echo esc_html( $booking['coupon_code'] ); ?>
							(&minus;<?php echo esc_html( mageyabo_format_price( $booking['discount_total'], $currency ) ); ?>)
						</dd>
					<?php endif; ?>

					<dt><?php esc_html_e( 'Total', 'magepeople-yacht-booking-system' ); ?></dt>
					<dd><strong><?php echo esc_html( mageyabo_format_price( $total, $currency ) ); ?></strong></dd>

					<?php if ( $deposit > 0 && $deposit < $total ) : ?>
						<dt><?php esc_html_e( 'Deposit', 'magepeople-yacht-booking-system' ); ?></dt>
						<dd><?php echo esc_html( mageyabo_format_price( $deposit, $currency ) ); ?></dd>
					<?php endif; ?>

					<dt><?php esc_html_e( 'Paid so far', 'magepeople-yacht-booking-system' ); ?></dt>
					<dd><?php echo esc_html( mageyabo_format_price( $paid, $currency ) ); ?></dd>

					<?php if ( $balance > 0 ) : ?>
						<dt><?php esc_html_e( 'Balance due', 'magepeople-yacht-booking-system' ); ?></dt>
						<dd><?php echo esc_html( mageyabo_format_price( $balance, $currency ) ); ?></dd>
					<?php endif; ?>
				</dl>

				<?php if ( 'offline' === $booking['payment_method'] && Settings::get( 'offline_instructions', '' ) ) : ?>
					<div class="ybs-confirmation__instructions">
						<h4><?php esc_html_e( 'Payment instructions', 'magepeople-yacht-booking-system' ); ?></h4>
						<?php echo wp_kses_post( wpautop( Settings::get( 'offline_instructions', '' ) ) ); ?>
					</div>
				<?php endif; ?>

				<div class="ybs-confirmation__ticket">
					<span class="ybs-confirmation__ticket-label"><?php esc_html_e( 'Check-in code', 'magepeople-yacht-booking-system' ); ?></span>
					<code class="ybs-confirmation__ticket-code"><?php echo esc_html( $booking['qr_token'] ); ?></code>
					<p class="ybs-hint"><?php esc_html_e( 'Show this code to the crew when you board.', 'magepeople-yacht-booking-system' ); ?></p>
					<?php
					/**
					 * Renders a scannable version of the check-in code. The
					 * plugin ships no barcode/QR encoder, so this is the seam
					 * an add-on uses to draw one.
					 *
					 * @param string $html    Markup to print under the code.
					 * @param array  $booking The booking row.
					 */
					echo wp_kses_post( apply_filters( 'mageyabo_booking_ticket_qr', '', $booking ) );
					?>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * The one-line verdict at the top: what the gateway said, tempered by
	 * what the booking record actually shows. A PayPal "success" return is
	 * only the guest's word for it until the IPN lands, so it deliberately
	 * says "confirming" rather than "paid".
	 */
	private static function banner( array $booking, $outcome ) {
		$paid = PricingEngine::balance_due( $booking ) <= 0 && PricingEngine::amount_paid( $booking ) > 0;

		// The booking's own status outranks the payment state. An operator who
		// cancels a paid booking leaves payment_status at 'paid', and telling
		// the guest their charter is confirmed because of that would be worse
		// than saying nothing.
		if ( in_array( $booking['status'], array( 'cancelled', 'refunded' ), true ) ) {
			return '<div class="ybs-notice is-error"><strong>' .
				esc_html__( 'This booking has been cancelled.', 'magepeople-yacht-booking-system' ) .
				'</strong> ' .
				esc_html__( 'Please get in touch if you were not expecting this.', 'magepeople-yacht-booking-system' ) .
				'</div>';
		}

		if ( 'cancelled' === $outcome && ! $paid ) {
			return '<div class="ybs-notice is-error">' .
				esc_html__( 'Payment was cancelled. Your booking is being held as unpaid - you can pay again or contact us to arrange another method.', 'magepeople-yacht-booking-system' ) .
				'</div>';
		}

		if ( $paid ) {
			return '<div class="ybs-notice is-success"><strong>' .
				esc_html__( 'Payment received.', 'magepeople-yacht-booking-system' ) .
				'</strong> ' .
				esc_html__( 'Your booking is confirmed and a confirmation email is on its way.', 'magepeople-yacht-booking-system' ) .
				'</div>';
		}

		if ( in_array( $outcome, array( 'success', 'return' ), true ) ) {
			return '<div class="ybs-notice is-info"><strong>' .
				esc_html__( 'Thank you.', 'magepeople-yacht-booking-system' ) .
				'</strong> ' .
				esc_html__( "We're confirming your payment with the provider - this page will show it as soon as it clears, and you'll get an email either way.", 'magepeople-yacht-booking-system' ) .
				'</div>';
		}

		return '<div class="ybs-notice is-info"><strong>' .
			esc_html__( 'Your booking is reserved.', 'magepeople-yacht-booking-system' ) .
			'</strong> ' .
			esc_html__( 'It is awaiting payment.', 'magepeople-yacht-booking-system' ) .
			'</div>';
	}
}
