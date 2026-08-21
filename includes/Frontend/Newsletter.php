<?php
namespace Ybs\Frontend;

use WP_REST_Server;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A simple email capture (spec 4.5) - stores to its own table and exposes a
 * filter so a future ESP integration (Pro or a site's own code) can react
 * without touching this class.
 */
class Newsletter {

	public static function register() {
		add_shortcode( 'ybs_newsletter', array( __CLASS__, 'render' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'ybs/v1',
			'/newsletter/subscribe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'subscribe' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function subscribe( WP_REST_Request $request ) {
		global $wpdb;

		$email = sanitize_email( $request->get_param( 'email' ) );

		if ( ! $email || ! is_email( $email ) ) {
			return new \WP_Error( 'ybs_invalid_email', __( 'Please provide a valid email address.', 'yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$table = $wpdb->prefix . 'ybs_newsletter_subscribers';

		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (email, subscribed_at) VALUES (%s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email,
				current_time( 'mysql' )
			)
		);

		/**
		 * Lets a site (or a future ESP add-on) react to a new subscriber.
		 *
		 * @param string $email
		 */
		do_action( 'ybs_newsletter_subscribed', $email );

		return rest_ensure_response( array( 'subscribed' => true ) );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'       => __( 'Stay in the loop', 'yacht-booking-system' ),
				'placeholder' => __( 'Your email address', 'yacht-booking-system' ),
			),
			$atts,
			'ybs_newsletter'
		);

		wp_enqueue_script( 'ybs-frontend' );
		wp_enqueue_style( 'ybs-frontend' );

		ob_start();
		?>
		<div class="ybs-newsletter" data-ybs-newsletter>
			<p class="ybs-newsletter__title"><?php echo esc_html( $atts['title'] ); ?></p>
			<form class="ybs-newsletter__form">
				<input type="email" required placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" class="ybs-newsletter__input" />
				<button type="submit" class="ybs-btn is-primary"><?php esc_html_e( 'Subscribe', 'yacht-booking-system' ); ?></button>
			</form>
			<p class="ybs-newsletter__message" hidden></p>
		</div>
		<?php
		return ob_get_clean();
	}
}
