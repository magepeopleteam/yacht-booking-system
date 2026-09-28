<?php
namespace MageYaBo\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce checkout inside the booking drawer.
 *
 * When WooCommerce checkout is on, "Book Now" opens the same drawer as the
 * native flow with the booking summary; "Confirm Booking" adds the charter to
 * the cart over AJAX (`?wc-ajax=mageyabo_add_to_cart`) and the drawer then
 * loads the real checkout page in an iframe, flagged `mageyabo_embed=1`. That
 * flag swaps the theme for a bare canvas (no header, footer or admin bar) and
 * is carried through placing the order, so the thank-you page renders in the
 * drawer too and tells the parent page the booking went through.
 *
 * Gateways that send the guest to their own site (PayPal and the like) will
 * not load inside a frame, so those redirects break out to the whole tab.
 */
class WooCommerceDrawer {

	const FLAG = 'mageyabo_embed';

	public static function register() {
		add_action( 'wc_ajax_mageyabo_add_to_cart', array( __CLASS__, 'add_to_cart' ) );

		add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar' ), 99 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'template_include', array( __CLASS__, 'canvas_template' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'print_embed_styles' ), 99 );
		add_action( 'wp_footer', array( __CLASS__, 'print_order_received_signal' ), 99 );

		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'print_flag_field' ) );
		add_filter( 'woocommerce_get_checkout_order_received_url', array( __CLASS__, 'keep_flag_on_thank_you' ) );
		add_filter( 'woocommerce_payment_successful_result', array( __CLASS__, 'break_out_of_frame' ), 99 );
		add_action( 'template_redirect', array( __CLASS__, 'serve_breakout' ), 0 );
	}

	/**
	 * The checkout address the drawer loads.
	 */
	public static function checkout_url() {
		return function_exists( 'wc_get_checkout_url' ) ? add_query_arg( self::FLAG, '1', wc_get_checkout_url() ) : '';
	}

	/**
	 * The AJAX endpoint "Confirm Booking" posts the charter to.
	 */
	public static function add_to_cart_url() {
		return class_exists( '\WC_AJAX' ) ? \WC_AJAX::get_endpoint( 'mageyabo_add_to_cart' ) : '';
	}

	/**
	 * This page is the checkout or thank-you page, loaded in the drawer.
	 */
	public static function is_embedded() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
		if ( empty( $_GET[ self::FLAG ] ) || ! function_exists( 'is_checkout' ) ) {
			return false;
		}

		return is_checkout() || is_order_received_page();
	}

	/**
	 * This request came from the drawer's checkout - including the
	 * `?wc-ajax=checkout` post that places the order, which carries the flag
	 * as a hidden field (and, as a fallback, in its referer).
	 */
	private static function request_is_from_drawer() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- display flag only.
		if ( ! empty( $_REQUEST[ self::FLAG ] ) ) {
			return true;
		}

		$referer = wp_get_referer();

		if ( ! $referer ) {
			return false;
		}

		$query = array();
		wp_parse_str( (string) wp_parse_url( $referer, PHP_URL_QUERY ), $query );

		return ! empty( $query[ self::FLAG ] );
	}

	/**
	 * Adds the charter to the cart and answers in JSON: the checkout URL, or
	 * WooCommerce's own reasons for refusing it (slot taken, too many guests,
	 * and so on) so the drawer can show them instead of a broken checkout.
	 */
	public static function add_to_cart() {
		if ( ! WooCommerceGateway::is_active() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'messages' => array( __( 'Online checkout is not available right now.', 'magepeople-yacht-booking-system' ) ) ), 400 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- like WooCommerce's own add-to-cart AJAX: public, and every value is re-validated below.
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id || ! WooCommerceProduct::get_yacht_id( $product_id ) ) {
			wp_send_json_error( array( 'messages' => array( __( 'This yacht cannot be booked online.', 'magepeople-yacht-booking-system' ) ) ), 400 );
		}

		// WooCommerce's "coming soon" mode hides the checkout from visitors
		// (store managers can still preview it). Say so here, rather than
		// loading a "launching soon" page into the drawer.
		if ( 'yes' === get_option( 'woocommerce_coming_soon' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'messages' => array( __( 'Online checkout is not open yet. Please contact us to book this yacht.', 'magepeople-yacht-booking-system' ) ) ), 403 );
		}

		wc_clear_notices();

		// The same checks WooCommerce's add-to-cart form runs, which is where
		// the booking is validated and priced (see WooCommerceGateway).
		$passed = (bool) apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, 1, 0, array() );
		$key    = false;

		if ( $passed ) {
			// One charter per checkout: a guest who went back and changed the
			// date replaces the booking they had, rather than paying for both.
			foreach ( WC()->cart->get_cart() as $cart_key => $item ) {
				if ( ! empty( $item['mageyabo_booking'] ) ) {
					WC()->cart->remove_cart_item( $cart_key );
				}
			}

			$key = WC()->cart->add_to_cart( $product_id, 1 );
		}

		if ( ! $passed || ! $key ) {
			$messages = array_map(
				static function ( $notice ) {
					return wp_strip_all_tags( is_array( $notice ) ? ( $notice['notice'] ?? '' ) : (string) $notice );
				},
				wc_get_notices( 'error' )
			);
			wc_clear_notices();

			wp_send_json_error(
				array( 'messages' => array_values( array_filter( $messages ) ) ?: array( __( 'The booking could not be added to your cart. Please try again.', 'magepeople-yacht-booking-system' ) ) ),
				400
			);
		}

		// No "added to your cart" banner on top of the checkout.
		wc_clear_notices();

		wp_send_json_success( array( 'checkout_url' => self::checkout_url() ) );
	}

	public static function hide_admin_bar( $show ) {
		return self::is_embedded() ? false : $show;
	}

	public static function body_class( $classes ) {
		if ( self::is_embedded() ) {
			$classes[] = 'mageyabo-embedded-checkout';
		}

		return $classes;
	}

	/**
	 * A bare page - wp_head/wp_footer still run, so WooCommerce's scripts,
	 * the payment gateways and the theme's form styling all load - with no
	 * theme header or footer around the checkout.
	 */
	public static function canvas_template( $template ) {
		if ( ! self::is_embedded() ) {
			return $template;
		}

		/**
		 * Keep the theme's own template around the embedded checkout (the
		 * inline styles still hide its header and footer).
		 *
		 * @param bool   $use_canvas
		 * @param string $template
		 */
		if ( ! apply_filters( 'mageyabo_embedded_checkout_use_canvas', true, $template ) ) {
			return $template;
		}

		return MAGEYABO_PLUGIN_DIR . 'templates/embedded-checkout.php';
	}

	/**
	 * Belt and braces for themes that print their header from a hook the
	 * canvas cannot skip, and for sites that turn the canvas off.
	 */
	public static function print_embed_styles() {
		if ( ! self::is_embedded() ) {
			return;
		}
		?>
		<style id="mageyabo-embedded-checkout">
			html { margin-top: 0 !important; }
			body.mageyabo-embedded-checkout { margin: 0; padding: 0; background: #fff; }
			body.mageyabo-embedded-checkout #wpadminbar,
			body.mageyabo-embedded-checkout > header,
			body.mageyabo-embedded-checkout > footer,
			body.mageyabo-embedded-checkout header.wp-block-template-part,
			body.mageyabo-embedded-checkout footer.wp-block-template-part,
			body.mageyabo-embedded-checkout [class*="site-header"],
			body.mageyabo-embedded-checkout [class*="site-footer"],
			body.mageyabo-embedded-checkout #masthead,
			body.mageyabo-embedded-checkout #colophon,
			body.mageyabo-embedded-checkout .woocommerce-breadcrumb,
			body.mageyabo-embedded-checkout .entry-title,
			body.mageyabo-embedded-checkout .wp-block-post-title,
			body.mageyabo-embedded-checkout .page-title { display: none !important; }
			.mageyabo-embed { box-sizing: border-box; max-width: 100%; margin: 0; padding: 20px 24px 32px; }
			.mageyabo-embedded-checkout .woocommerce .col2-set,
			.mageyabo-embedded-checkout .woocommerce-page .col2-set { display: block; width: 100%; }
			.mageyabo-embedded-checkout .col2-set .col-1,
			.mageyabo-embedded-checkout .col2-set .col-2 { float: none; width: 100%; max-width: 100%; }
			.mageyabo-embedded-checkout form.checkout { display: block; }
			.mageyabo-embedded-checkout #order_review_heading,
			.mageyabo-embedded-checkout #order_review { float: none; width: 100%; }
			@media (max-width: 600px) { .mageyabo-embed { padding: 16px 16px 24px; } }
		</style>
		<?php
	}

	/**
	 * The checkout posts over `?wc-ajax=checkout`; this field carries the
	 * flag on that post so the thank-you page stays embedded too.
	 */
	public static function print_flag_field() {
		if ( self::is_embedded() ) {
			printf( '<input type="hidden" name="%s" value="1" />', esc_attr( self::FLAG ) );
		}
	}

	public static function keep_flag_on_thank_you( $url ) {
		return self::request_is_from_drawer() ? add_query_arg( self::FLAG, '1', $url ) : $url;
	}

	/**
	 * An off-site payment page will not load inside the drawer's frame, so
	 * send the guest there through a same-site page that moves the whole tab.
	 * The target is kept server-side for ten minutes under a random token,
	 * never passed in the address, so the hop cannot be used as an open
	 * redirect.
	 */
	public static function break_out_of_frame( $result ) {
		if ( empty( $result['redirect'] ) || ! self::request_is_from_drawer() ) {
			return $result;
		}

		$target_host = wp_parse_url( $result['redirect'], PHP_URL_HOST );
		$site_host   = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! $target_host || $target_host === $site_host ) {
			return $result;
		}

		$token = wp_generate_password( 24, false );
		set_transient( 'mageyabo_breakout_' . $token, esc_url_raw( $result['redirect'] ), 10 * MINUTE_IN_SECONDS );

		$result['redirect'] = add_query_arg( 'mageyabo_breakout', $token, home_url( '/' ) );

		return $result;
	}

	public static function serve_breakout() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a one-time random token, looked up server-side.
		if ( empty( $_GET['mageyabo_breakout'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token  = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['mageyabo_breakout'] ) );
		$target = $token ? get_transient( 'mageyabo_breakout_' . $token ) : '';

		if ( ! $target ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		delete_transient( 'mageyabo_breakout_' . $token );
		nocache_headers();
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="robots" content="noindex"><title><?php esc_html_e( 'Redirecting to payment…', 'magepeople-yacht-booking-system' ); ?></title></head>
		<body style="font-family:system-ui,sans-serif;text-align:center;padding:40px 16px">
			<p><?php esc_html_e( 'Taking you to the secure payment page…', 'magepeople-yacht-booking-system' ); ?></p>
			<p><a href="<?php echo esc_url( $target ); ?>" target="_top"><?php esc_html_e( 'Continue to payment', 'magepeople-yacht-booking-system' ); ?></a></p>
			<script>window.top.location.href = <?php echo wp_json_encode( esc_url_raw( $target ) ); ?>;</script>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * On the embedded thank-you page: tell the drawer the booking is done,
	 * and make the page's own links (view order, account) leave the frame.
	 */
	public static function print_order_received_signal() {
		if ( ! self::is_embedded() || ! is_order_received_page() ) {
			return;
		}

		global $wp;

		$order_id = isset( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : 0;
		?>
		<script>
			( function () {
				document.querySelectorAll( '.mageyabo-embed a[href]' ).forEach( function ( link ) {
					if ( ! link.target ) {
						link.target = '_top';
					}
				} );

				if ( window.parent && window.parent !== window ) {
					window.parent.postMessage( { type: 'mageyabo:order-received', orderId: <?php echo (int) $order_id; ?> }, window.location.origin );
				}
			}() );
		</script>
		<?php
	}
}
