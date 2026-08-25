<?php
/**
 * Single yacht details page - premium template shipped with Yacht Booking
 * System. Copy this file to `yourtheme/yacht-booking-system/single-yacht.php`
 * to customize it; the plugin copy is used only when no theme override exists.
 *
 * Available in scope: the global WP post (a `yacht` post type).
 *
 * @package yacht-booking-system
 */

use Ybs\Frontend\Shortcode;
use Ybs\PostTypes\Yacht;
use Ybs\Settings;

defined( 'ABSPATH' ) || exit;

$yacht_id      = get_the_ID();
$currency      = Settings::get( 'currency_symbol', '$' );
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

$rates         = Shortcode::yacht_rates_public( $yacht_id );
$min_rate      = $rates ? min( wp_list_pluck( $rates, 'amount' ) ) : 0;
$booking_mode  = get_post_meta( $yacht_id, 'booking_mode', true ) ?: 'full';
$shared_rates  = 'both' === $booking_mode ? Shortcode::yacht_shared_rates_public( $yacht_id ) : array();

$gallery_ids = is_array( $gallery_ids ) ? array_values( array_filter( array_map( 'intval', $gallery_ids ) ) ) : array();
$thumb_id    = (int) get_post_thumbnail_id( $yacht_id );

if ( $thumb_id && ! in_array( $thumb_id, $gallery_ids, true ) ) {
	array_unshift( $gallery_ids, $thumb_id );
}

$gallery = array();

foreach ( $gallery_ids as $gid ) {
	$large = wp_get_attachment_image_url( $gid, 'large' );
	$full  = wp_get_attachment_image_url( $gid, 'full' );

	if ( $large ) {
		$gallery[] = array(
			'id'    => $gid,
			'large' => $large,
			'full'  => $full ? $full : $large,
			'alt'   => trim( get_post_meta( $gid, '_wp_attachment_image_alt', true ) ),
		);
	}
}

$similar = array();

if ( $classes ) {
	$query = new WP_Query(
		array(
			'post_type'      => Yacht::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 4,
			'post__not_in'   => array( $yacht_id ),
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'yacht_class',
					'field'    => 'term_id',
					'terms'    => wp_list_pluck( $classes, 'term_id' ),
				),
			),
		)
	);

	$similar = $query->posts;
}

\Ybs\Frontend\Templates::document_start();
?>

<div class="ybs-ys" id="ybs-single">

	<div class="ybs-ys-container">
		<nav class="ybs-ys-breadcrumb" data-ybs-reveal aria-label="<?php esc_attr_e( 'Breadcrumb', 'yacht-booking-system' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'yacht-booking-system' ); ?></a>
			<span class="ybs-ys-breadcrumb__sep">/</span>
			<a href="<?php echo esc_url( get_post_type_archive_link( Yacht::POST_TYPE ) ); ?>"><?php esc_html_e( 'Yachts', 'yacht-booking-system' ); ?></a>
			<span class="ybs-ys-breadcrumb__sep">/</span>
			<span class="ybs-ys-breadcrumb__current"><?php echo esc_html( get_the_title( $yacht_id ) ); ?></span>
		</nav>

		<?php if ( $gallery ) : ?>
			<section class="ybs-ys-gallery" data-ybs-reveal aria-label="<?php esc_attr_e( 'Photo gallery', 'yacht-booking-system' ); ?>">
				<div class="ybs-ys-gallery__grid" data-ybs-carousel>
					<?php foreach ( array_slice( $gallery, 0, 5 ) as $i => $img ) : ?>
						<button
							type="button"
							class="ybs-ys-gallery__tile<?php echo 0 === $i ? ' ybs-ys-gallery__tile--hero' : ''; ?>"
							data-ybs-lightbox-open="<?php echo esc_attr( (string) $i ); ?>"
							data-full-src="<?php echo esc_url( $img['full'] ); ?>"
						>
							<img
								src="<?php echo esc_url( $img['large'] ); ?>"
								alt="<?php echo esc_attr( $img['alt'] ? $img['alt'] : get_the_title( $yacht_id ) ); ?>"
								loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>"
							/>
							<?php if ( 4 === $i && count( $gallery ) > 5 ) : ?>
								<span class="ybs-ys-gallery__more">+<?php echo esc_html( count( $gallery ) - 5 ); ?></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>

					<span class="ybs-ys-gallery__counter" data-ybs-carousel-counter>1 / <?php echo esc_html( count( $gallery ) ); ?></span>
				</div>

				<div class="ybs-ys-gallery__dots" data-ybs-carousel-dots aria-hidden="true"></div>

				<div class="ybs-ys-gallery__actions">
					<button type="button" class="ybs-ys-pill-btn" data-ybs-lightbox-open="0">
						<span class="dashicons dashicons-camera-alt"></span>
						<?php echo esc_html( sprintf( __( 'View Photos (%d)', 'yacht-booking-system' ), count( $gallery ) ) ); ?>
					</button>
				</div>
			</section>
		<?php endif; ?>

		<header class="ybs-ys-header" data-ybs-reveal>
			<div class="ybs-ys-header__badges">
				<?php if ( $classes ) : ?>
					<span class="ybs-ys-badge ybs-ys-badge--gold"><?php echo esc_html( $classes[0]->name ); ?></span>
				<?php endif; ?>
				<?php if ( $location_name ) : ?>
					<span class="ybs-ys-badge ybs-ys-badge--outline"><span class="dashicons dashicons-location"></span><?php echo esc_html( $location_name ); ?></span>
				<?php endif; ?>
				<span class="ybs-ys-available"><span class="ybs-ys-pulse"></span><?php esc_html_e( 'Available now', 'yacht-booking-system' ); ?></span>
			</div>

			<h1 class="ybs-ys-title"><?php echo esc_html( get_the_title( $yacht_id ) ); ?></h1>

			<?php if ( get_the_excerpt( $yacht_id ) ) : ?>
				<p class="ybs-ys-lede"><?php echo esc_html( get_the_excerpt( $yacht_id ) ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( $capacity || $length || $cabins || $crew || $build_year || $location_name ) : ?>
			<section class="ybs-ys-specs" data-ybs-reveal aria-label="<?php esc_attr_e( 'Specifications', 'yacht-booking-system' ); ?>">
				<?php if ( $capacity ) : ?>
					<div class="ybs-ys-spec">
						<span class="ybs-ys-spec__icon dashicons dashicons-groups"></span>
						<span class="ybs-ys-spec__label"><?php esc_html_e( 'Guests', 'yacht-booking-system' ); ?></span>
						<span class="ybs-ys-spec__value" data-ybs-count="<?php echo esc_attr( (string) $capacity ); ?>"><?php echo esc_html( (string) $capacity ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $length ) : ?>
					<div class="ybs-ys-spec">
						<span class="ybs-ys-spec__icon dashicons dashicons-leftright"></span>
						<span class="ybs-ys-spec__label"><?php esc_html_e( 'Length', 'yacht-booking-system' ); ?></span>
						<span class="ybs-ys-spec__value"><?php echo esc_html( sprintf( __( '%s m', 'yacht-booking-system' ), $length ) ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $cabins ) : ?>
					<div class="ybs-ys-spec">
						<span class="ybs-ys-spec__icon dashicons dashicons-admin-home"></span>
						<span class="ybs-ys-spec__label"><?php esc_html_e( 'Cabins', 'yacht-booking-system' ); ?></span>
						<span class="ybs-ys-spec__value"><?php echo esc_html( (string) $cabins ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $crew ) : ?>
					<div class="ybs-ys-spec">
						<span class="ybs-ys-spec__icon dashicons dashicons-admin-users"></span>
						<span class="ybs-ys-spec__label"><?php esc_html_e( 'Crew', 'yacht-booking-system' ); ?></span>
						<span class="ybs-ys-spec__value"><?php echo esc_html( (string) $crew ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $build_year ) : ?>
					<div class="ybs-ys-spec">
						<span class="ybs-ys-spec__icon dashicons dashicons-calendar-alt"></span>
						<span class="ybs-ys-spec__label"><?php esc_html_e( 'Build year', 'yacht-booking-system' ); ?></span>
						<span class="ybs-ys-spec__value"><?php echo esc_html( $build_year ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $location_name ) : ?>
					<div class="ybs-ys-spec">
						<span class="ybs-ys-spec__icon dashicons dashicons-location"></span>
						<span class="ybs-ys-spec__label"><?php esc_html_e( 'Location', 'yacht-booking-system' ); ?></span>
						<span class="ybs-ys-spec__value"><?php echo esc_html( $location_name ); ?></span>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<div class="ybs-ys-layout">
			<main class="ybs-ys-main">

				<section class="ybs-ys-section" data-ybs-reveal>
					<span class="ybs-ys-eyebrow"><?php esc_html_e( 'Overview', 'yacht-booking-system' ); ?></span>
					<h2 class="ybs-ys-h2"><?php echo esc_html( sprintf( __( 'The %s experience', 'yacht-booking-system' ), get_the_title( $yacht_id ) ) ); ?></h2>
					<div class="ybs-ys-prose"><?php echo do_shortcode( wpautop( get_post_field( 'post_content', $yacht_id ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- post content rendered through standard filters. ?></div>
				</section>

				<?php if ( $rates ) : ?>
					<section class="ybs-ys-section" data-ybs-reveal>
						<span class="ybs-ys-eyebrow"><?php esc_html_e( 'Pricing', 'yacht-booking-system' ); ?></span>
						<h2 class="ybs-ys-h2"><?php esc_html_e( 'Charter Rates', 'yacht-booking-system' ); ?></h2>
						<div class="ybs-ys-rates-card">
							<?php if ( $shared_rates ) : ?>
								<div class="ybs-ys-rates__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Charter type', 'yacht-booking-system' ); ?>">
									<button
										type="button"
										class="ybs-ys-rates__tab is-active"
										role="tab"
										aria-selected="true"
										data-ybs-rates-tab="full"
									><?php esc_html_e( 'Full charter', 'yacht-booking-system' ); ?></button>
									<button
										type="button"
										class="ybs-ys-rates__tab"
										role="tab"
										aria-selected="false"
										data-ybs-rates-tab="shared"
									><?php esc_html_e( 'Shared · per seat', 'yacht-booking-system' ); ?></button>
								</div>

								<div class="ybs-ys-rates__panel is-active" data-ybs-rates-panel="full" role="tabpanel">
									<ul class="ybs-ys-rates__list">
										<?php foreach ( $rates as $rate ) : ?>
											<li>
												<span class="ybs-ys-rates__name">
													<?php echo esc_html( $rate['label'] ); ?>
													<?php if ( ! empty( $rate['window'] ) ) : ?>
														<small><?php echo esc_html( $rate['window'][0] . ' – ' . $rate['window'][1] ); ?></small>
													<?php endif; ?>
												</span>
												<strong><?php echo esc_html( Shortcode::format_price_public( $rate['amount'], $currency ) ); ?></strong>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>

								<div class="ybs-ys-rates__panel" data-ybs-rates-panel="shared" role="tabpanel" hidden>
									<ul class="ybs-ys-rates__list">
										<?php foreach ( $shared_rates as $rate ) : ?>
											<li>
												<span class="ybs-ys-rates__name">
													<?php echo esc_html( $rate['label'] ); ?>
													<?php if ( ! empty( $rate['window'] ) ) : ?>
														<small><?php echo esc_html( $rate['window'][0] . ' – ' . $rate['window'][1] ); ?></small>
													<?php endif; ?>
												</span>
												<strong><?php echo esc_html( Shortcode::format_price_public( $rate['amount'], $currency ) ); ?></strong>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php else : ?>
								<ul class="ybs-ys-rates__list">
									<?php foreach ( $rates as $rate ) : ?>
										<li>
											<span class="ybs-ys-rates__name">
												<?php echo esc_html( $rate['label'] ); ?>
												<?php if ( ! empty( $rate['window'] ) ) : ?>
													<small><?php echo esc_html( $rate['window'][0] . ' – ' . $rate['window'][1] ); ?></small>
												<?php endif; ?>
											</span>
											<strong><?php echo esc_html( Shortcode::format_price_public( $rate['amount'], $currency ) ); ?></strong>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( is_array( $included ) && $included ) : ?>
					<section class="ybs-ys-section" data-ybs-reveal>
						<span class="ybs-ys-eyebrow"><?php esc_html_e( 'On board', 'yacht-booking-system' ); ?></span>
						<h2 class="ybs-ys-h2"><?php esc_html_e( 'Amenities & features', 'yacht-booking-system' ); ?></h2>
						<ul class="ybs-ys-checklist">
							<?php foreach ( $included as $item ) : ?>
								<li class="ybs-ys-check">
									<span class="ybs-ys-check__mark" aria-hidden="true">&#10003;</span>
									<?php echo esc_html( is_array( $item ) ? ( $item['text'] ?? '' ) : $item ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( $occasions ) : ?>
					<section class="ybs-ys-section" data-ybs-reveal>
						<span class="ybs-ys-eyebrow"><?php esc_html_e( 'Perfect for', 'yacht-booking-system' ); ?></span>
						<h2 class="ybs-ys-h2"><?php esc_html_e( 'Occasions this yacht fits best', 'yacht-booking-system' ); ?></h2>
						<div class="ybs-ys-chips">
							<?php foreach ( $occasions as $term ) : ?>
								<a class="ybs-ys-chip" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( $lat && $lng ) : ?>
					<section class="ybs-ys-section" data-ybs-reveal>
						<span class="ybs-ys-eyebrow"><?php esc_html_e( 'Where you cruise', 'yacht-booking-system' ); ?></span>
						<h2 class="ybs-ys-h2"><?php esc_html_e( 'Location & boarding point', 'yacht-booking-system' ); ?></h2>
						<div class="ybs-ys-map-wrap">
							<div class="ybs-yacht-map" data-lat="<?php echo esc_attr( $lat ); ?>" data-lng="<?php echo esc_attr( $lng ); ?>" data-name="<?php echo esc_attr( $location_name ); ?>"></div>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( $similar ) : ?>
					<section class="ybs-ys-section" data-ybs-reveal>
						<span class="ybs-ys-eyebrow"><?php esc_html_e( 'Similar yachts', 'yacht-booking-system' ); ?></span>
						<h2 class="ybs-ys-h2"><?php esc_html_e( 'You might also like', 'yacht-booking-system' ); ?></h2>
						<div class="ybs-ys-similar">
							<?php foreach ( $similar as $other ) : ?>
								<?php
									$other_rates   = Shortcode::yacht_rates_public( $other->ID );
									$other_min     = $other_rates ? min( wp_list_pluck( $other_rates, 'amount' ) ) : 0;
									$other_meta    = array_filter(
										array(
											get_post_meta( $other->ID, 'length', true ) ? get_post_meta( $other->ID, 'length', true ) . ' m' : '',
											get_post_meta( $other->ID, 'capacity', true ) ? get_post_meta( $other->ID, 'capacity', true ) . __( ' guests', 'yacht-booking-system' ) : '',
										)
									);
									$other_classes = wp_get_post_terms( $other->ID, 'yacht_class' );
								?>
								<a class="ybs-ys-card" href="<?php echo esc_url( get_permalink( $other ) ); ?>">
									<div class="ybs-ys-card__media">
										<?php $other_thumb = get_the_post_thumbnail_url( $other, 'medium_large' ); ?>
										<?php if ( $other_thumb ) : ?>
											<img src="<?php echo esc_url( $other_thumb ); ?>" alt="<?php echo esc_attr( get_the_title( $other ) ); ?>" loading="lazy" />
										<?php endif; ?>
									</div>
									<div class="ybs-ys-card__body">
										<h3 class="ybs-ys-card__title"><?php echo esc_html( get_the_title( $other ) ); ?></h3>
										<?php if ( $other_meta ) : ?>
											<p class="ybs-ys-card__meta"><?php echo esc_html( implode( ' · ', $other_meta ) ); ?></p>
										<?php endif; ?>
										<?php if ( $other_min > 0 ) : ?>
											<p class="ybs-ys-card__price">
												<em><?php esc_html_e( 'from', 'yacht-booking-system' ); ?></em>
												<strong><?php echo esc_html( Shortcode::format_price_public( $other_min, $currency ) ); ?></strong>
												<span><?php esc_html_e( '/ hour · VAT excluded', 'yacht-booking-system' ); ?></span>
											</p>
										<?php endif; ?>
									</div>
								</a>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( is_array( $faq ) && $faq ) : ?>
					<section class="ybs-ys-section" data-ybs-reveal id="ybs-faq">
						<span class="ybs-ys-eyebrow ybs-ys-eyebrow--line"><?php esc_html_e( 'Frequently asked', 'yacht-booking-system' ); ?></span>
						<h2 class="ybs-ys-h2"><?php echo esc_html( sprintf( __( 'Questions about the %s', 'yacht-booking-system' ), get_the_title( $yacht_id ) ) ); ?></h2>
						<div class="ybs-ys-faq">
							<?php foreach ( $faq as $item ) : ?>
								<details class="ybs-ys-faq-item">
									<summary class="ybs-ys-faq-q">
										<span><?php echo esc_html( $item['question'] ?? '' ); ?></span>
										<span class="ybs-ys-faq-icon" aria-hidden="true"></span>
									</summary>
									<div class="ybs-ys-faq-a"><?php echo wp_kses_post( $item['answer'] ?? '' ); ?></div>
								</details>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>
			</main>

			<aside class="ybs-ys-sidebar">
				<div class="ybs-ys-bookcard" data-ybs-sticky>
					<div class="ybs-ys-form" id="ybs-book">
						<span class="ybs-ys-form-eyebrow"><?php esc_html_e( 'Get an instant quote', 'yacht-booking-system' ); ?></span>
						<?php echo Shortcode::render_booking_form( array( 'yacht_id' => $yacht_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted plugin markup. ?>
					</div>

					<footer class="ybs-ys-trust">
						<div><strong>5.0&#9733;</strong><span><?php esc_html_e( 'GOOGLE', 'yacht-booking-system' ); ?></span></div>
						<div><strong>100%</strong><span><?php esc_html_e( 'INSURED', 'yacht-booking-system' ); ?></span></div>
						<div><strong>24/7</strong><span><?php esc_html_e( 'SUPPORT', 'yacht-booking-system' ); ?></span></div>
					</footer>
				</div>
			</aside>
		</div>

		<section class="ybs-ys-cta" data-ybs-reveal>
			<span class="ybs-ys-eyebrow"><?php esc_html_e( 'Ready when you are', 'yacht-booking-system' ); ?></span>
			<h2 class="ybs-ys-h2"><?php echo esc_html( sprintf( __( 'Book the %s today', 'yacht-booking-system' ), get_the_title( $yacht_id ) ) ); ?></h2>
			<p><?php esc_html_e( 'Dates fill fast - lock in your preferred slot with an instant booking request.', 'yacht-booking-system' ); ?></p>
			<div class="ybs-ys-cta__actions">
				<a class="ybs-ys-btn ybs-ys-btn--gold" href="#ybs-book"><?php esc_html_e( 'Book Now', 'yacht-booking-system' ); ?></a>
				<a class="ybs-ys-btn ybs-ys-btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( Yacht::POST_TYPE ) ); ?>"><?php esc_html_e( 'Browse all yachts', 'yacht-booking-system' ); ?></a>
			</div>
		</section>
	</div>

	<div class="ybs-ys-dock" data-ybs-dock hidden>
		<div class="ybs-ys-dock__price">
			<?php if ( $min_rate > 0 ) : ?>
				<strong><?php echo esc_html( Shortcode::format_price_public( $min_rate, $currency ) ); ?></strong>
				<span><?php esc_html_e( 'per hour', 'yacht-booking-system' ); ?></span>
			<?php else : ?>
				<strong><?php echo esc_html( get_the_title( $yacht_id ) ); ?></strong>
			<?php endif; ?>
		</div>
		<a class="ybs-ys-dock__btn" href="#ybs-book"><?php esc_html_e( 'Book Now', 'yacht-booking-system' ); ?></a>
	</div>

	<div class="ybs-ys-lightbox" data-ybs-lightbox hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Photo viewer', 'yacht-booking-system' ); ?>">
		<button type="button" class="ybs-ys-lightbox__close" data-ybs-lightbox-close aria-label="<?php esc_attr_e( 'Close', 'yacht-booking-system' ); ?>">&times;</button>
		<button type="button" class="ybs-ys-lightbox__nav ybs-ys-lightbox__nav--prev" data-ybs-lightbox-prev aria-label="<?php esc_attr_e( 'Previous photo', 'yacht-booking-system' ); ?>">&#8249;</button>
		<figure class="ybs-ys-lightbox__stage">
			<img src="" alt="" />
			<figcaption></figcaption>
		</figure>
		<button type="button" class="ybs-ys-lightbox__nav ybs-ys-lightbox__nav--next" data-ybs-lightbox-next aria-label="<?php esc_attr_e( 'Next photo', 'yacht-booking-system' ); ?>">&#8250;</button>
		<span class="ybs-ys-lightbox__counter"></span>
	</div>
</div>

<?php
\Ybs\Frontend\Templates::document_end();
