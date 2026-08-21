<?php
namespace Ybs\Frontend;

use Ybs\PostTypes\Yacht;
use Ybs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders progressively-enhanced HTML: the markup here is enough to see the
 * yacht/date/guest fields without JS, but live pricing, availability, and
 * submission all require the enqueued frontend bundle.
 */
class Shortcode {

	public static function register() {
		add_shortcode( 'ybs_booking_form', array( __CLASS__, 'render_booking_form' ) );
		add_shortcode( 'ybs_yacht_search', array( __CLASS__, 'render_search' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_to_single_yacht' ) );
	}

	public static function register_assets() {
		$asset_file = YBS_PLUGIN_DIR . 'assets/build/frontend.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array( 'dependencies' => array(), 'version' => YBS_VERSION );

		wp_register_style( 'ybs-leaflet', YBS_PLUGIN_URL . 'assets/frontend/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_register_script( 'ybs-leaflet', YBS_PLUGIN_URL . 'assets/frontend/vendor/leaflet/leaflet.js', array(), '1.9.4', true );

		wp_register_script( 'ybs-frontend', YBS_PLUGIN_URL . 'assets/build/frontend.js', array_merge( $asset['dependencies'], array( 'ybs-leaflet' ) ), $asset['version'], true );
		wp_register_style( 'ybs-frontend', YBS_PLUGIN_URL . 'assets/build/style-frontend.css', array( 'ybs-leaflet' ), $asset['version'] );

		wp_localize_script(
			'ybs-frontend',
			'ybsFrontendConfig',
			array(
				'restRoot' => esc_url_raw( rest_url( 'ybs/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'currency' => Settings::get( 'currency_symbol', '$' ),
				'gateways' => \Ybs\Payments\Gateways::available(),
				'i18n'     => array(
					'selectYacht'   => __( 'Select a yacht', 'yacht-booking-system' ),
					'loading'       => __( 'Loading…', 'yacht-booking-system' ),
					'bookNow'       => __( 'Book Now', 'yacht-booking-system' ),
					'notAvailable'  => __( 'Not available for the selected time.', 'yacht-booking-system' ),
					'nearMe'          => __( 'Near me', 'yacht-booking-system' ),
					'guests'          => __( 'Guests', 'yacht-booking-system' ),
					'termsRequired'   => __( 'Please accept the terms and conditions.', 'yacht-booking-system' ),
					'noResults'       => __( 'No yachts matched your search.', 'yacht-booking-system' ),
					'searchFailed'    => __( 'Search failed. Please try again.', 'yacht-booking-system' ),
					'bookingConfirmed' => __( 'Thank you - your booking request has been received.', 'yacht-booking-system' ),
					'subscribeThanks' => __( 'Thanks for subscribing!', 'yacht-booking-system' ),
					'subscribeError'  => __( 'Something went wrong. Please try again.', 'yacht-booking-system' ),
				),
			)
		);
	}

	public static function render_booking_form( $atts ) {
		$atts = shortcode_atts( array( 'yacht_id' => 0 ), $atts, 'ybs_booking_form' );

		wp_enqueue_script( 'ybs-frontend' );
		wp_enqueue_style( 'ybs-frontend' );

		$yacht_id = (int) $atts['yacht_id'];
		$yachts   = $yacht_id ? array() : get_posts(
			array(
				'post_type'      => Yacht::POST_TYPE,
				'post_status'    => 'publish',
				'numberposts'    => -1,
			)
		);

		ob_start();
		?>
		<div class="ybs-booking-form" data-ybs-booking-form data-yacht-id="<?php echo esc_attr( $yacht_id ); ?>">
			<?php if ( ! $yacht_id ) : ?>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Yacht', 'yacht-booking-system' ); ?></label>
					<select class="ybs-bf-yacht">
						<option value=""><?php esc_html_e( 'Select a yacht', 'yacht-booking-system' ); ?></option>
						<?php foreach ( $yachts as $yacht ) : ?>
							<option value="<?php echo esc_attr( $yacht->ID ); ?>"><?php echo esc_html( $yacht->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<div class="ybs-field-row">
				<div class="ybs-field">
					<label><?php esc_html_e( 'Booking Type', 'yacht-booking-system' ); ?></label>
					<select class="ybs-bf-type">
						<option value="hourly"><?php esc_html_e( 'Hourly', 'yacht-booking-system' ); ?></option>
						<option value="half_day"><?php esc_html_e( 'Half-Day', 'yacht-booking-system' ); ?></option>
						<option value="morning_slot"><?php esc_html_e( 'Morning Slot', 'yacht-booking-system' ); ?></option>
						<option value="evening_slot"><?php esc_html_e( 'Evening / Sunset Slot', 'yacht-booking-system' ); ?></option>
						<option value="daily"><?php esc_html_e( 'Daily', 'yacht-booking-system' ); ?></option>
						<option value="multiday"><?php esc_html_e( 'Multi-Day', 'yacht-booking-system' ); ?></option>
					</select>
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Date', 'yacht-booking-system' ); ?></label>
					<input type="date" class="ybs-bf-date" />
				</div>
				<div class="ybs-field ybs-bf-hourly-fields">
					<label><?php esc_html_e( 'Start Time', 'yacht-booking-system' ); ?></label>
					<input type="time" class="ybs-bf-start-time" value="10:00" />
				</div>
				<div class="ybs-field ybs-bf-hourly-fields">
					<label><?php esc_html_e( 'Duration (hours)', 'yacht-booking-system' ); ?></label>
					<input type="number" class="ybs-bf-duration" min="1" value="2" />
				</div>
				<div class="ybs-field ybs-bf-multiday-fields" hidden>
					<label><?php esc_html_e( 'Nights', 'yacht-booking-system' ); ?></label>
					<input type="number" class="ybs-bf-nights" min="1" value="2" />
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Guests', 'yacht-booking-system' ); ?></label>
					<input type="number" class="ybs-bf-guests" min="1" value="1" />
				</div>
			</div>

			<div class="ybs-bf-price ybs-notice is-info" hidden></div>

			<div class="ybs-field-row">
				<div class="ybs-field">
					<label><?php esc_html_e( 'Full Name', 'yacht-booking-system' ); ?></label>
					<input type="text" class="ybs-bf-name" required />
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Email', 'yacht-booking-system' ); ?></label>
					<input type="email" class="ybs-bf-email" required />
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Phone', 'yacht-booking-system' ); ?></label>
					<input type="text" class="ybs-bf-phone" required />
				</div>
			</div>

			<div class="ybs-field">
				<label><?php esc_html_e( 'Payment Method', 'yacht-booking-system' ); ?></label>
				<select class="ybs-bf-payment"></select>
			</div>

			<div class="ybs-field">
				<label>
					<input type="checkbox" class="ybs-bf-terms" required />
					<?php esc_html_e( 'I accept the terms and conditions.', 'yacht-booking-system' ); ?>
				</label>
			</div>

			<div class="ybs-bf-error ybs-notice is-error" hidden></div>

			<button type="button" class="ybs-btn is-primary ybs-bf-submit"><?php esc_html_e( 'Book Now', 'yacht-booking-system' ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_search( $atts ) {
		wp_enqueue_script( 'ybs-frontend' );
		wp_enqueue_style( 'ybs-frontend' );

		$classes   = get_terms( array( 'taxonomy' => 'yacht_class', 'hide_empty' => false ) );
		$occasions = get_terms( array( 'taxonomy' => 'yacht_occasion', 'hide_empty' => false ) );

		ob_start();
		?>
		<div class="ybs-search" data-ybs-search>
			<div class="ybs-field-row">
				<div class="ybs-field">
					<label><?php esc_html_e( 'Date', 'yacht-booking-system' ); ?></label>
					<input type="date" class="ybs-search-date" />
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Guests', 'yacht-booking-system' ); ?></label>
					<input type="number" class="ybs-search-guests" min="1" />
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Class', 'yacht-booking-system' ); ?></label>
					<select class="ybs-search-class">
						<option value=""><?php esc_html_e( 'Any', 'yacht-booking-system' ); ?></option>
						<?php foreach ( $classes as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Occasion', 'yacht-booking-system' ); ?></label>
					<select class="ybs-search-occasion">
						<option value=""><?php esc_html_e( 'Any', 'yacht-booking-system' ); ?></option>
						<?php foreach ( $occasions as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Max Price', 'yacht-booking-system' ); ?></label>
					<input type="number" class="ybs-search-price-max" min="0" />
				</div>
				<div class="ybs-field ybs-search-nearme-field">
					<label>&nbsp;</label>
					<button type="button" class="ybs-btn ybs-search-nearme"><?php esc_html_e( 'Near Me', 'yacht-booking-system' ); ?></button>
				</div>
			</div>
			<button type="button" class="ybs-btn is-primary ybs-search-submit"><?php esc_html_e( 'Search Yachts', 'yacht-booking-system' ); ?></button>
			<div class="ybs-search-results"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Appends the departure-point map, FAQ, and booking form to a single
	 * yacht page automatically, so a site does not have to hand-place
	 * shortcodes on every yacht (spec 4.5's "embedded map on each yacht's
	 * listing page").
	 */
	public static function append_to_single_yacht( $content ) {
		if ( ! is_singular( Yacht::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$yacht_id = get_the_ID();
		$lat      = get_post_meta( $yacht_id, 'location_lat', true );
		$lng      = get_post_meta( $yacht_id, 'location_lng', true );
		$name     = get_post_meta( $yacht_id, 'location_name', true );
		$faq      = get_post_meta( $yacht_id, 'faq', true );
		$included = get_post_meta( $yacht_id, 'included_items', true );

		wp_enqueue_script( 'ybs-frontend' );
		wp_enqueue_style( 'ybs-frontend' );

		ob_start();

		if ( is_array( $included ) && $included ) {
			echo '<div class="ybs-yacht-included"><h3>' . esc_html__( 'Included in every charter', 'yacht-booking-system' ) . '</h3><ul>';
			foreach ( $included as $item ) {
				echo '<li>' . esc_html( is_array( $item ) ? ( $item['text'] ?? '' ) : $item ) . '</li>';
			}
			echo '</ul></div>';
		}

		printf(
			'<div class="ybs-availability-calendar" data-yacht-id="%d"><div class="ybs-availability-calendar__header"><button type="button" class="ybs-btn ybs-availability-calendar__prev">&larr;</button><span class="ybs-availability-calendar__label"></span><button type="button" class="ybs-btn ybs-availability-calendar__next">&rarr;</button></div><div class="ybs-availability-calendar__grid"></div></div>',
			esc_attr( $yacht_id )
		);

		if ( $lat && $lng ) {
			printf(
				'<div class="ybs-yacht-map" data-lat="%s" data-lng="%s" data-name="%s"></div>',
				esc_attr( $lat ),
				esc_attr( $lng ),
				esc_attr( $name )
			);
		}

		if ( is_array( $faq ) && $faq ) {
			echo '<div class="ybs-yacht-faq"><h3>' . esc_html__( 'Frequently Asked Questions', 'yacht-booking-system' ) . '</h3>';
			foreach ( $faq as $item ) {
				printf(
					'<details class="ybs-faq-item"><summary>%s</summary><div>%s</div></details>',
					esc_html( $item['question'] ?? '' ),
					wp_kses_post( $item['answer'] ?? '' )
				);
			}
			echo '</div>';
		}

		echo self::render_booking_form( array( 'yacht_id' => $yacht_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput

		return $content . ob_get_clean();
	}
}
