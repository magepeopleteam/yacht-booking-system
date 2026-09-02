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
		add_shortcode( 'yacht-list', array( __CLASS__, 'render_yacht_list' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_to_single_yacht' ) );
	}

	public static function register_assets() {
		$asset_file = YBS_PLUGIN_DIR . 'assets/build/frontend.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array( 'dependencies' => array(), 'version' => YBS_VERSION );

		wp_register_style( 'ybs-leaflet', YBS_PLUGIN_URL . 'assets/frontend/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_register_script( 'ybs-leaflet', YBS_PLUGIN_URL . 'assets/frontend/vendor/leaflet/leaflet.js', array(), '1.9.4', true );

		wp_register_script( 'ybs-frontend', YBS_PLUGIN_URL . 'assets/build/frontend.js', array_merge( $asset['dependencies'], array( 'ybs-leaflet' ) ), $asset['version'], true );
		wp_register_style( 'ybs-frontend', YBS_PLUGIN_URL . 'assets/build/style-frontend.css', array( 'ybs-leaflet', 'dashicons' ), $asset['version'] );

		wp_localize_script(
			'ybs-frontend',
			'ybsFrontendConfig',
			array(
				'restRoot' => esc_url_raw( rest_url( 'ybs/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'currency' => Settings::get( 'currency_symbol', '$' ),
				'gateways' => \Ybs\Payments\Gateways::available(),
				'i18n'     => array(
					'selectYacht'   => __( 'Select a yacht', 'magepeople-yacht-booking-system' ),
					'loading'       => __( 'Loading…', 'magepeople-yacht-booking-system' ),
					'bookNow'       => __( 'Book Now', 'magepeople-yacht-booking-system' ),
					'notAvailable'  => __( 'Not available for the selected time.', 'magepeople-yacht-booking-system' ),
					'nearMe'          => __( 'Near me', 'magepeople-yacht-booking-system' ),
					'guests'          => __( 'Guests', 'magepeople-yacht-booking-system' ),
					'seatsLeft'       => __( 'seats left', 'magepeople-yacht-booking-system' ),
					'termsRequired'   => __( 'Please accept the terms and conditions.', 'magepeople-yacht-booking-system' ),
					'noResults'       => __( 'No yachts matched your search.', 'magepeople-yacht-booking-system' ),
					'searchFailed'    => __( 'Search failed. Please try again.', 'magepeople-yacht-booking-system' ),
					'yachtAvailable'  => /* translators: %d: number of yachts found. */ __( '%d yacht available', 'magepeople-yacht-booking-system' ),
					'yachtsAvailable' => /* translators: %d: number of yachts found. */ __( '%d yachts available', 'magepeople-yacht-booking-system' ),
					'contactForPricing' => __( 'Contact for pricing', 'magepeople-yacht-booking-system' ),
					'photos'          => __( 'photos', 'magepeople-yacht-booking-system' ),
					'from'            => __( 'From', 'magepeople-yacht-booking-system' ),
					'perHour'         => __( 'hour', 'magepeople-yacht-booking-system' ),
					'guestsLabel'     => __( 'guests', 'magepeople-yacht-booking-system' ),
					'viewYacht'       => __( 'View', 'magepeople-yacht-booking-system' ),
					'bookingConfirmed' => __( 'Thank you - your booking request has been received.', 'magepeople-yacht-booking-system' ),
					'subscribeThanks' => __( 'Thanks for subscribing!', 'magepeople-yacht-booking-system' ),
					'subscribeError'  => __( 'Something went wrong. Please try again.', 'magepeople-yacht-booking-system' ),
				),
			)
		);
	}

	public static function render_booking_form( $atts ) {
		$atts = shortcode_atts( array( 'yacht_id' => 0 ), $atts, 'ybs_booking_form' );

		wp_enqueue_script( 'ybs-frontend' );
		wp_enqueue_style( 'ybs-frontend' );

		$yacht_id  = (int) $atts['yacht_id'];
		$yacht_mode = $yacht_id ? ( get_post_meta( $yacht_id, 'booking_mode', true ) ?: 'full' ) : '';
		$capacity  = $yacht_id ? (int) get_post_meta( $yacht_id, 'capacity', true ) : 0;
		$yachts    = $yacht_id ? array() : get_posts(
			array(
				'post_type'      => Yacht::POST_TYPE,
				'post_status'    => 'publish',
				'numberposts'    => -1,
			)
		);

		// mage-eventpress style: when WooCommerce checkout is enabled, the
		// details page posts straight into the Woo cart through the yacht's
		// hidden linked product instead of the REST booking endpoint.
		$wc_product_id = 0;

		if ( $yacht_id && \Ybs\Payments\WooCommerceGateway::is_active() ) {
			$wc_product_id = (int) \Ybs\Payments\WooCommerceProduct::get_product_id( $yacht_id );
		}

		ob_start();

		if ( $wc_product_id ) :
			?>
			<form
				method="post"
				action=""
				enctype="multipart/form-data"
				class="ybs-booking-form"
				data-ybs-booking-form
				data-ybs-wc="1"
				data-yacht-id="<?php echo esc_attr( $yacht_id ); ?>"
				data-capacity="<?php echo esc_attr( $capacity ); ?>"
				<?php echo $yacht_mode ? 'data-ybs-mode="' . esc_attr( $yacht_mode ) . '"' : ''; ?>
			>
				<input type="hidden" name="ybs_yacht_id" value="<?php echo esc_attr( $yacht_id ); ?>" />
				<input type="hidden" name="ybs_booking_type" value="hourly" />
				<input type="hidden" name="ybs_booking_mode" value="<?php echo esc_attr( 'both' === $yacht_mode ? 'full' : $yacht_mode ); ?>" />
				<input type="hidden" name="ybs_start_datetime" value="" />
				<input type="hidden" name="ybs_end_datetime" value="" />
				<input type="hidden" name="ybs_guest_count" value="1" />
		<?php else : ?>
			<div class="ybs-booking-form" data-ybs-booking-form data-yacht-id="<?php echo esc_attr( $yacht_id ); ?>" data-capacity="<?php echo esc_attr( $capacity ); ?>"<?php echo $yacht_mode ? ' data-ybs-mode="' . esc_attr( $yacht_mode ) . '"' : ''; ?>>
		<?php endif; ?>
			<?php if ( 'both' === $yacht_mode ) : ?>
				<div class="ybs-field">
					<label><?php esc_html_e( 'How do you want to book?', 'magepeople-yacht-booking-system' ); ?></label>
					<select class="ybs-bf-mode">
						<option value="full"><?php esc_html_e( 'Full Charter - whole yacht', 'magepeople-yacht-booking-system' ); ?></option>
						<option value="shared"><?php esc_html_e( 'Shared - per seat', 'magepeople-yacht-booking-system' ); ?></option>
					</select>
					<p class="ybs-hint"><?php esc_html_e( 'Once shared seats are booked for a time slot, that slot can only take further shared bookings.', 'magepeople-yacht-booking-system' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! $yacht_id ) : ?>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Yacht', 'magepeople-yacht-booking-system' ); ?></label>
					<select class="ybs-bf-yacht">
						<option value=""><?php esc_html_e( 'Select a yacht', 'magepeople-yacht-booking-system' ); ?></option>
						<?php foreach ( $yachts as $yacht ) : ?>
							<option value="<?php echo esc_attr( $yacht->ID ); ?>" data-capacity="<?php echo esc_attr( (int) get_post_meta( $yacht->ID, 'capacity', true ) ); ?>"><?php echo esc_html( $yacht->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<div class="ybs-field-row">
				<div class="ybs-field">
					<label><?php esc_html_e( 'Booking Type', 'magepeople-yacht-booking-system' ); ?></label>
					<select class="ybs-bf-type">
						<option value="hourly"><?php esc_html_e( 'Hourly', 'magepeople-yacht-booking-system' ); ?></option>
						<option value="half_day"><?php esc_html_e( 'Half-Day', 'magepeople-yacht-booking-system' ); ?></option>
						<option value="morning_slot"><?php esc_html_e( 'Morning Slot', 'magepeople-yacht-booking-system' ); ?></option>
						<option value="evening_slot"><?php esc_html_e( 'Evening / Sunset Slot', 'magepeople-yacht-booking-system' ); ?></option>
						<option value="daily"><?php esc_html_e( 'Daily', 'magepeople-yacht-booking-system' ); ?></option>
						<option value="multiday"><?php esc_html_e( 'Multi-Day', 'magepeople-yacht-booking-system' ); ?></option>
					</select>
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Date', 'magepeople-yacht-booking-system' ); ?></label>
					<input type="date" class="ybs-bf-date" />
				</div>
				<div class="ybs-field ybs-bf-hourly-fields">
					<label><?php esc_html_e( 'Start Time', 'magepeople-yacht-booking-system' ); ?></label>
					<input type="time" class="ybs-bf-start-time" value="10:00" />
				</div>
				<div class="ybs-field ybs-bf-hourly-fields">
					<label><?php esc_html_e( 'Duration (hours)', 'magepeople-yacht-booking-system' ); ?></label>
					<input type="number" class="ybs-bf-duration" min="1" value="2" />
				</div>
				<div class="ybs-field ybs-bf-multiday-fields" hidden>
					<label><?php esc_html_e( 'Nights', 'magepeople-yacht-booking-system' ); ?></label>
					<input type="number" class="ybs-bf-nights" min="1" value="2" />
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Guests', 'magepeople-yacht-booking-system' ); ?></label>
					<input type="number" class="ybs-bf-guests" min="1"<?php echo $capacity > 0 ? ' max="' . esc_attr( $capacity ) . '"' : ''; ?> value="1" />
				</div>
			</div>

			<div class="ybs-bf-price ybs-notice is-info" hidden></div>

			<?php if ( ! $wc_product_id ) : ?>
				<div class="ybs-field-row">
					<div class="ybs-field">
						<label><?php esc_html_e( 'Full Name', 'magepeople-yacht-booking-system' ); ?></label>
						<input type="text" name="ybs_name" class="ybs-bf-name" required />
					</div>
					<div class="ybs-field">
						<label><?php esc_html_e( 'Email', 'magepeople-yacht-booking-system' ); ?></label>
						<input type="email" name="ybs_email" class="ybs-bf-email" required />
					</div>
					<div class="ybs-field">
						<label><?php esc_html_e( 'Phone', 'magepeople-yacht-booking-system' ); ?></label>
						<input type="text" name="ybs_phone" class="ybs-bf-phone" required />
					</div>
				</div>

				<div class="ybs-field">
					<label><?php esc_html_e( 'Payment Method', 'magepeople-yacht-booking-system' ); ?></label>
					<select class="ybs-bf-payment"></select>
				</div>

				<div class="ybs-field">
					<label>
						<input type="checkbox" class="ybs-bf-terms" name="ybs_terms" value="1" required />
						<?php esc_html_e( 'I accept the terms and conditions.', 'magepeople-yacht-booking-system' ); ?>
					</label>
				</div>
			<?php else : ?>
				<p class="ybs-hint"><?php esc_html_e( 'Your details will be collected on the checkout page.', 'magepeople-yacht-booking-system' ); ?></p>
			<?php endif; ?>

			<div class="ybs-bf-error ybs-notice is-error" hidden></div>

			<?php if ( $wc_product_id ) : ?>
				<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $wc_product_id ); ?>" class="ybs-btn is-primary ybs-bf-submit"><?php esc_html_e( 'Book Now', 'magepeople-yacht-booking-system' ); ?></button>
			</form>
			<?php else : ?>
				<button type="button" class="ybs-btn is-primary ybs-bf-submit"><?php esc_html_e( 'Book Now', 'magepeople-yacht-booking-system' ); ?></button>
			</div>
			<?php endif; ?>
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
					<label><?php esc_html_e( 'Date', 'magepeople-yacht-booking-system' ); ?></label>
					<input type="date" class="ybs-search-date" />
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Guests', 'magepeople-yacht-booking-system' ); ?></label>
					<input type="number" class="ybs-search-guests" min="1" />
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Class', 'magepeople-yacht-booking-system' ); ?></label>
					<select class="ybs-search-class">
						<option value=""><?php esc_html_e( 'Any', 'magepeople-yacht-booking-system' ); ?></option>
						<?php foreach ( $classes as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Occasion', 'magepeople-yacht-booking-system' ); ?></label>
					<select class="ybs-search-occasion">
						<option value=""><?php esc_html_e( 'Any', 'magepeople-yacht-booking-system' ); ?></option>
						<?php foreach ( $occasions as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ybs-field">
					<label><?php esc_html_e( 'Max Price', 'magepeople-yacht-booking-system' ); ?></label>
					<input type="number" class="ybs-search-price-max" min="0" />
				</div>
				<div class="ybs-field ybs-search-nearme-field">
					<label>&nbsp;</label>
					<button type="button" class="ybs-btn ybs-search-nearme"><?php esc_html_e( 'Near Me', 'magepeople-yacht-booking-system' ); ?></button>
				</div>
			</div>
			<button type="button" class="ybs-btn is-primary ybs-search-submit"><?php esc_html_e( 'Search Yachts', 'magepeople-yacht-booking-system' ); ?></button>
			<div class="ybs-search-results"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * `[yacht-list search="yes"]` - a fleet search/listing page styled after
	 * the class-pill filter bar + photo-count-badged card grid pattern seen
	 * on dubaiyachtbooking.com's fleet page. `search="no"` renders just the
	 * grid (e.g. for a "Similar Yachts" style embed without the filter bar).
	 */
	public static function render_yacht_list( $atts ) {
		$atts = shortcode_atts(
			array(
				'search'   => 'yes',
				'per_page' => 9,
			),
			$atts,
			'yacht-list'
		);

		wp_enqueue_script( 'ybs-frontend' );
		wp_enqueue_style( 'ybs-frontend' );
		wp_enqueue_style( 'ybs-yl-font' );

		$show_search = 'yes' === $atts['search'] || '1' === (string) $atts['search'];
		$classes     = get_terms( array( 'taxonomy' => 'yacht_class', 'hide_empty' => false ) );
		$currency    = Settings::get( 'currency_symbol', '$' );

		// value = "min-max" ("" max means no ceiling) - parsed by yacht-list.js
		// into separate price_min/price_max REST params, since a flat "under
		// X" max alone can't express the top open-ended "10,000+" tier.
		$price_tiers = array(
			/* translators: %s: currency symbol. */
			'0-1000'     => sprintf( __( 'Under %s1,000 per hour', 'magepeople-yacht-booking-system' ), $currency ),
			/* translators: %1$s: currency symbol. */
			'1000-2500'  => sprintf( __( '%1$s1,000 to %1$s2,500 per hour', 'magepeople-yacht-booking-system' ), $currency ),
			/* translators: %1$s: currency symbol. */
			'2500-5000'  => sprintf( __( '%1$s2,500 to %1$s5,000 per hour', 'magepeople-yacht-booking-system' ), $currency ),
			/* translators: %1$s: currency symbol. */
			'5000-10000' => sprintf( __( '%1$s5,000 to %1$s10,000 per hour', 'magepeople-yacht-booking-system' ), $currency ),
			/* translators: %s: currency symbol. */
			'10000-'     => sprintf( __( '%s10,000+ per hour', 'magepeople-yacht-booking-system' ), $currency ),
		);

		ob_start();
		?>
		<div class="ybs-yl" data-ybs-yl data-per-page="<?php echo esc_attr( (int) $atts['per_page'] ); ?>">
			<?php if ( $show_search ) : ?>
				<div class="ybs-yl-bar">
					<div class="ybs-yl-bar__field">
						<span class="ybs-yl-bar__label">
							<?php esc_html_e( 'Dates', 'magepeople-yacht-booking-system' ); ?>
							<small><?php esc_html_e( '(sets season rates)', 'magepeople-yacht-booking-system' ); ?></small>
						</span>
						<input type="date" class="ybs-yl-date ybs-yl-bar__control" />
					</div>
					<div class="ybs-yl-bar__field">
						<span class="ybs-yl-bar__label"><?php esc_html_e( 'Guests', 'magepeople-yacht-booking-system' ); ?></span>
						<div class="ybs-yl-bar__stepper">
							<button type="button" class="ybs-yl-bar__step" data-step="-1" aria-label="<?php esc_attr_e( 'Fewer guests', 'magepeople-yacht-booking-system' ); ?>">&minus;</button>
							<b class="ybs-yl-guests" data-value="2">2</b>
							<button type="button" class="ybs-yl-bar__step" data-step="1" aria-label="<?php esc_attr_e( 'More guests', 'magepeople-yacht-booking-system' ); ?>">+</button>
						</div>
					</div>
					<div class="ybs-yl-bar__field">
						<span class="ybs-yl-bar__label"><?php esc_html_e( 'Price', 'magepeople-yacht-booking-system' ); ?></span>
						<select class="ybs-yl-price ybs-yl-bar__control">
							<option value=""><?php esc_html_e( 'Any price', 'magepeople-yacht-booking-system' ); ?></option>
							<?php foreach ( $price_tiers as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="ybs-yl-bar__submit">
						<button type="button" class="ybs-yl-search-btn" aria-label="<?php esc_attr_e( 'Search the fleet', 'magepeople-yacht-booking-system' ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
							<span><?php esc_html_e( 'Search', 'magepeople-yacht-booking-system' ); ?></span>
						</button>
					</div>
				</div>

				<?php if ( $classes ) : ?>
					<div class="ybs-yl-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter yachts by class', 'magepeople-yacht-booking-system' ); ?>">
						<button type="button" role="tab" aria-selected="true" class="ybs-yl-tab is-active" data-class=""><?php esc_html_e( 'All', 'magepeople-yacht-booking-system' ); ?></button>
						<?php foreach ( $classes as $term ) : ?>
							<button type="button" role="tab" aria-selected="false" class="ybs-yl-tab" data-class="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<div class="ybs-yl-toolbar">
				<div class="ybs-yl-summary"></div>
				<div class="ybs-yl-view-toggle" role="group" aria-label="<?php esc_attr_e( 'Switch view', 'magepeople-yacht-booking-system' ); ?>">
					<button type="button" class="ybs-yl-view-btn is-active" data-view="grid" aria-label="<?php esc_attr_e( 'Grid view', 'magepeople-yacht-booking-system' ); ?>" aria-pressed="true">
						<span class="dashicons dashicons-grid-view"></span>
					</button>
					<button type="button" class="ybs-yl-view-btn" data-view="list" aria-label="<?php esc_attr_e( 'List view', 'magepeople-yacht-booking-system' ); ?>" aria-pressed="false">
						<span class="dashicons dashicons-list-view"></span>
					</button>
				</div>
			</div>
			<div class="ybs-yl-grid"></div>
			<div class="ybs-yl-loadmore-wrap">
				<button type="button" class="ybs-yl-loadmore" hidden><?php esc_html_e( 'Load More Yachts', 'magepeople-yacht-booking-system' ); ?></button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Replaces a single yacht's post content with the full listing-page
	 * design: hero gallery, spec/stats bar, a two-column body (overview,
	 * included items, occasions, charter rates, map, similar yachts, FAQ
	 * on the left; a sticky rates + booking widget on the right). `$content`
	 * is already the fully-filtered post content by the time this runs, so
	 * it's used as-is for the Overview section rather than re-running it
	 * through `the_content` (which would recurse into this same filter).
	 */
	public static function append_to_single_yacht( $content ) {
		if ( ! is_singular( Yacht::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$yacht_id = get_the_ID();
		$currency = Settings::get( 'currency_symbol', '$' );

		$capacity      = (int) get_post_meta( $yacht_id, 'capacity', true );
		$cabins        = (int) get_post_meta( $yacht_id, 'cabins', true );
		$crew          = (int) get_post_meta( $yacht_id, 'crew_size', true );
		$length        = get_post_meta( $yacht_id, 'length', true );
		$build_year    = get_post_meta( $yacht_id, 'build_year', true );
		$location_name = get_post_meta( $yacht_id, 'location_name', true );
		$lat           = get_post_meta( $yacht_id, 'location_lat', true );
		$lng           = get_post_meta( $yacht_id, 'location_lng', true );
		$included      = get_post_meta( $yacht_id, 'included_items', true );
		$faq           = get_post_meta( $yacht_id, 'faq', true );
		$gallery_ids   = get_post_meta( $yacht_id, 'gallery', true );
		$classes       = wp_get_post_terms( $yacht_id, 'yacht_class' );
		$occasions     = wp_get_post_terms( $yacht_id, 'yacht_occasion' );
		$rates         = self::yacht_rates( $yacht_id );

		wp_enqueue_script( 'ybs-frontend' );
		wp_enqueue_style( 'ybs-frontend' );

		$gallery_ids = is_array( $gallery_ids ) ? array_filter( array_map( 'intval', $gallery_ids ) ) : array();
		$thumb_id    = get_post_thumbnail_id( $yacht_id );

		if ( $thumb_id && ! in_array( $thumb_id, $gallery_ids, true ) ) {
			array_unshift( $gallery_ids, $thumb_id );
		}

		$gallery_urls = array_values(
			array_filter( array_map( static fn( $id ) => wp_get_attachment_image_url( $id, 'large' ), $gallery_ids ) )
		);

		ob_start();
		?>
		<div class="ybs-yacht-page">
			<div class="ybs-yp-gallery" data-ybs-gallery>
				<?php if ( $gallery_urls ) : ?>
					<div class="ybs-yp-gallery__main">
						<img class="ybs-yp-gallery__main-img" src="<?php echo esc_url( $gallery_urls[0] ); ?>" alt="<?php echo esc_attr( get_the_title( $yacht_id ) ); ?>" />
						<?php if ( count( $gallery_urls ) > 1 ) : ?>
							<span class="ybs-yp-gallery__count">
								<span class="dashicons dashicons-camera"></span>
								<span class="ybs-yp-gallery__count-text"><?php echo esc_html( sprintf( '1 / %d', count( $gallery_urls ) ) ); ?></span>
							</span>
						<?php endif; ?>
					</div>
					<?php if ( count( $gallery_urls ) > 1 ) : ?>
						<div class="ybs-yp-gallery__thumbs">
							<?php foreach ( $gallery_urls as $i => $url ) : ?>
								<button type="button" class="ybs-yp-gallery__thumb<?php echo 0 === $i ? ' is-active' : ''; ?>" data-full="<?php echo esc_url( $url ); ?>">
									<img src="<?php echo esc_url( $url ); ?>" alt="" />
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				<?php else : ?>
					<div class="ybs-yp-gallery__placeholder"><span class="dashicons dashicons-palmtree"></span></div>
				<?php endif; ?>
			</div>

			<div class="ybs-yp-header">
				<?php if ( $classes ) : ?>
					<span class="ybs-yp-class-badge"><?php echo esc_html( $classes[0]->name ); ?></span>
				<?php endif; ?>
				<h2 class="ybs-yp-title"><?php echo esc_html( get_the_title( $yacht_id ) ); ?></h2>
				<div class="ybs-yp-stats">
					<?php if ( $capacity ) : ?>
						<span class="ybs-yp-stat"><span class="dashicons dashicons-groups"></span><?php echo esc_html( /* translators: %d: maximum number of guests. */ sprintf( __( '%d guests max', 'magepeople-yacht-booking-system' ), $capacity ) ); ?></span>
					<?php endif; ?>
					<?php if ( $length ) : ?>
						<span class="ybs-yp-stat"><span class="dashicons dashicons-leftright"></span><?php echo esc_html( /* translators: %s: yacht length in metres. */ sprintf( __( '%s m', 'magepeople-yacht-booking-system' ), $length ) ); ?></span>
					<?php endif; ?>
					<?php if ( $cabins ) : ?>
						<span class="ybs-yp-stat"><span class="dashicons dashicons-admin-home"></span><?php echo esc_html( /* translators: %d: number of cabins. */ sprintf( _n( '%d cabin', '%d cabins', $cabins, 'magepeople-yacht-booking-system' ), $cabins ) ); ?></span>
					<?php endif; ?>
					<?php if ( $crew ) : ?>
						<span class="ybs-yp-stat"><span class="dashicons dashicons-admin-users"></span><?php echo esc_html( /* translators: %d: number of crew. */ sprintf( _n( '%d crew', '%d crew', $crew, 'magepeople-yacht-booking-system' ), $crew ) ); ?></span>
					<?php endif; ?>
					<?php if ( $build_year ) : ?>
						<span class="ybs-yp-stat"><span class="dashicons dashicons-calendar-alt"></span><?php echo esc_html( $build_year ); ?></span>
					<?php endif; ?>
					<?php if ( $location_name ) : ?>
						<span class="ybs-yp-stat"><span class="dashicons dashicons-location"></span><?php echo esc_html( $location_name ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<div class="ybs-yp-body">
				<div class="ybs-yp-main">
					<section class="ybs-yp-section">
						<h3><?php echo esc_html( /* translators: %s: yacht name. */ sprintf( __( 'The %s Experience', 'magepeople-yacht-booking-system' ), get_the_title( $yacht_id ) ) ); ?></h3>
						<div class="ybs-yp-prose"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $content is already-filtered post content. ?></div>
					</section>

					<?php if ( is_array( $included ) && $included ) : ?>
						<section class="ybs-yp-section">
							<h3><?php esc_html_e( 'Included in every charter', 'magepeople-yacht-booking-system' ); ?></h3>
							<ul class="ybs-yp-checklist">
								<?php foreach ( $included as $item ) : ?>
									<li><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html( is_array( $item ) ? ( $item['text'] ?? '' ) : $item ); ?></li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>

					<?php if ( $occasions ) : ?>
						<section class="ybs-yp-section">
							<h3><?php esc_html_e( 'Occasions this yacht fits best', 'magepeople-yacht-booking-system' ); ?></h3>
							<div class="ybs-yp-tags">
								<?php foreach ( $occasions as $term ) : ?>
									<span class="ybs-yp-tag"><?php echo esc_html( $term->name ); ?></span>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endif; ?>

					<?php if ( $rates ) : ?>
						<section class="ybs-yp-section">
							<h3><?php esc_html_e( 'Charter Rates', 'magepeople-yacht-booking-system' ); ?></h3>
							<table class="ybs-yp-rates-table">
								<tbody>
									<?php foreach ( $rates as $rate ) : ?>
										<tr>
											<td>
												<?php echo esc_html( $rate['label'] ); ?>
												<?php if ( $rate['window'] ) : ?>
													<span class="ybs-yp-rate-window"><?php echo esc_html( $rate['window'][0] . ' – ' . $rate['window'][1] ); ?></span>
												<?php endif; ?>
											</td>
											<td class="ybs-yp-rate-amount"><?php echo esc_html( self::format_price( $rate['amount'], $currency ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</section>
					<?php endif; ?>

					<?php if ( $lat && $lng ) : ?>
						<section class="ybs-yp-section">
							<h3><?php esc_html_e( 'Location', 'magepeople-yacht-booking-system' ); ?></h3>
							<div class="ybs-yacht-map" data-lat="<?php echo esc_attr( $lat ); ?>" data-lng="<?php echo esc_attr( $lng ); ?>" data-name="<?php echo esc_attr( $location_name ); ?>"></div>
						</section>
					<?php endif; ?>

					<?php
					$similar = self::similar_yachts( $yacht_id, wp_list_pluck( $classes, 'term_id' ) );
					if ( $similar ) :
						?>
						<section class="ybs-yp-section">
							<h3><?php esc_html_e( 'Similar Yachts', 'magepeople-yacht-booking-system' ); ?></h3>
							<div class="ybs-search-results">
								<?php foreach ( $similar as $other ) : ?>
									<a class="ybs-yacht-card" href="<?php echo esc_url( get_permalink( $other ) ); ?>">
										<?php $other_thumb = get_the_post_thumbnail_url( $other, 'medium' ); ?>
										<?php if ( $other_thumb ) : ?>
											<img src="<?php echo esc_url( $other_thumb ); ?>" alt="" />
										<?php endif; ?>
										<div class="ybs-yacht-card__body">
											<h3><?php echo esc_html( get_the_title( $other ) ); ?></h3>
											<?php
											$other_rates = self::yacht_rates( $other->ID );
											if ( $other_rates ) :
												?>
												<div class="ybs-yacht-card__price">
													<?php echo esc_html( /* translators: %s: formatted lowest price. */ sprintf( __( 'From %s', 'magepeople-yacht-booking-system' ), self::format_price( min( wp_list_pluck( $other_rates, 'amount' ) ), $currency ) ) ); ?>
												</div>
											<?php endif; ?>
										</div>
									</a>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endif; ?>

					<?php if ( is_array( $faq ) && $faq ) : ?>
						<section class="ybs-yp-section">
							<h3><?php esc_html_e( 'Frequently Asked Questions', 'magepeople-yacht-booking-system' ); ?></h3>
							<div class="ybs-yacht-faq">
								<?php foreach ( $faq as $item ) : ?>
									<details class="ybs-faq-item">
										<summary><?php echo esc_html( $item['question'] ?? '' ); ?></summary>
										<div><?php echo wp_kses_post( $item['answer'] ?? '' ); ?></div>
									</details>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endif; ?>
				</div>

				<aside class="ybs-yp-sidebar">
					<div class="ybs-yp-sidebar__inner">
						<?php if ( $rates ) : ?>
							<span class="ybs-yp-sidebar__from">
								<?php echo esc_html( /* translators: %s: formatted lowest price. */ sprintf( __( 'From %s', 'magepeople-yacht-booking-system' ), self::format_price( min( wp_list_pluck( $rates, 'amount' ) ), $currency ) ) ); ?>
							</span>
						<?php endif; ?>

						<div class="ybs-yp-sidebar__form">
							<?php echo self::render_booking_form( array( 'yacht_id' => $yacht_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin-generated markup, escaped internally. ?>
						</div>
					</div>
				</aside>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * The non-empty priced booking types for a yacht, each paired with its
	 * operating time window where one applies (half day/morning/evening/daily
	 * have fixed windows; hourly and multi-day don't) - shared by the charter
	 * rates table, the sidebar summary, and "similar yachts" price tags.
	 */
	private static function yacht_rates( $yacht_id, $meta_prefix = 'base_price_' ) {
		$windows = Yacht::time_windows( $yacht_id );

		$defs = array(
			array( __( 'Hourly', 'magepeople-yacht-booking-system' ), 'hourly', null ),
			array( __( 'Half Day', 'magepeople-yacht-booking-system' ), 'halfday', $windows['half_day'] ),
			array( __( 'Morning Slot', 'magepeople-yacht-booking-system' ), 'morning_slot', $windows['morning_slot'] ),
			array( __( 'Evening / Sunset Slot', 'magepeople-yacht-booking-system' ), 'evening_slot', $windows['evening_slot'] ),
			array( __( 'Full Day', 'magepeople-yacht-booking-system' ), 'daily', $windows['daily'] ),
			array( __( 'Multi-Day (per night)', 'magepeople-yacht-booking-system' ), 'multiday', null ),
		);

		$rates = array();

		foreach ( $defs as $def ) {
			list( $label, $key, $window ) = $def;
			$amount                       = (float) get_post_meta( $yacht_id, $meta_prefix . $key, true );

			if ( $amount <= 0 ) {
				continue;
			}

			$rates[] = array(
				'label'  => $label,
				'amount' => $amount,
				'window' => $window,
			);
		}

		return $rates;
	}

	/**
	 * Up to 4 other published yachts sharing a class with this one, for the
	 * "Similar Yachts" section - real fleet data rather than placeholder content.
	 */
	private static function similar_yachts( $yacht_id, array $class_ids ) {
		if ( ! $class_ids ) {
			return array();
		}

		// Fetch one extra and drop the current yacht in PHP rather than asking
		// MySQL for a NOT IN: excluding a single id costs more in the query
		// than filtering a handful of rows does here.
		$query = new \WP_Query(
			array(
				'post_type'      => Yacht::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'yacht_class',
						'field'    => 'term_id',
						'terms'    => $class_ids,
					),
				),
			)
		);

		$posts = array_filter(
			$query->posts,
			static function ( $post ) use ( $yacht_id ) {
				return (int) $post->ID !== (int) $yacht_id;
			}
		);

		return array_slice( array_values( $posts ), 0, 4 );
	}

	/**
	 * Public wrapper so a theme's single-yacht override renders the same
	 * "Similar Yachts" set as the built-in template, instead of repeating
	 * the query.
	 *
	 * @param int   $yacht_id  Yacht being viewed; excluded from the results.
	 * @param int[] $class_ids `yacht_class` term ids to match on.
	 * @return \WP_Post[]
	 */
	public static function similar_yachts_public( $yacht_id, array $class_ids ) {
		return self::similar_yachts( $yacht_id, (array) $class_ids );
	}

	/**
	 * Public wrappers so custom single-yacht templates (plugin default or
	 * theme override) can reuse the exact same rate/price logic as the
	 * built-in renderer.
	 */
	public static function yacht_rates_public( $yacht_id ) {
		return self::yacht_rates( $yacht_id );
	}

	/**
	 * Per-seat rates entered under the "Shared" price grid in the wizard -
	 * empty unless the admin priced shared charters separately.
	 */
	public static function yacht_shared_rates_public( $yacht_id ) {
		return self::yacht_rates( $yacht_id, 'base_price_shared_' );
	}

	public static function format_price_public( $amount, $currency ) {
		return self::format_price( $amount, $currency );
	}

	private static function format_price( $amount, $currency ) {
		$amount  = (float) $amount;
		$decimals = ( fmod( $amount, 1.0 ) === 0.0 ) ? 0 : 2;

		return $currency . number_format( $amount, $decimals );
	}
}
