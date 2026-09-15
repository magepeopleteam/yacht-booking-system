<?php
/**
 * Single yacht details page - the template shipped with Yacht Booking
 * System. Copy this file to `yourtheme/magepeople-yacht-booking-system/single-yacht.php`
 * to customize it; the plugin copy is used only when no theme override exists.
 *
 * Available in scope: the global WP post (a `yacht` post type).
 *
 * @package magepeople-yacht-booking-system
 */

use MageYaBo\Frontend\Shortcode;
use MageYaBo\PostTypes\Yacht;
use MageYaBo\Settings;

defined( 'ABSPATH' ) || exit;

$mageyabo_yacht_id      = get_the_ID();
$mageyabo_currency      = Settings::get( 'currency_symbol', '$' );
$mageyabo_capacity      = (int) get_post_meta( $mageyabo_yacht_id, 'mageyabo_capacity', true );
$mageyabo_cabins        = (int) get_post_meta( $mageyabo_yacht_id, 'mageyabo_cabins', true );
$mageyabo_crew          = (int) get_post_meta( $mageyabo_yacht_id, 'mageyabo_crew_size', true );
$mageyabo_length        = get_post_meta( $mageyabo_yacht_id, 'mageyabo_length', true );
$mageyabo_build_year    = get_post_meta( $mageyabo_yacht_id, 'mageyabo_build_year', true );
$mageyabo_location_name = get_post_meta( $mageyabo_yacht_id, 'mageyabo_location_name', true );
$mageyabo_lat           = get_post_meta( $mageyabo_yacht_id, 'mageyabo_location_lat', true );
$mageyabo_lng           = get_post_meta( $mageyabo_yacht_id, 'mageyabo_location_lng', true );
$mageyabo_included      = get_post_meta( $mageyabo_yacht_id, 'mageyabo_included_items', true );
$mageyabo_faq           = get_post_meta( $mageyabo_yacht_id, 'mageyabo_faq', true );
$mageyabo_gallery_ids   = get_post_meta( $mageyabo_yacht_id, 'mageyabo_gallery', true );
$mageyabo_classes       = wp_get_post_terms( $mageyabo_yacht_id, 'mageyabo_yacht_class' );
$mageyabo_occasions     = wp_get_post_terms( $mageyabo_yacht_id, 'mageyabo_yacht_occasion' );

$mageyabo_cta_disabled      = '1' === get_post_meta( $mageyabo_yacht_id, 'mageyabo_cta_disabled', true );
$mageyabo_cta_eyebrow       = Settings::get( 'cta_eyebrow', __( 'Ready when you are', 'magepeople-yacht-booking-system' ) );
$mageyabo_cta_heading_tpl   = get_post_meta( $mageyabo_yacht_id, 'mageyabo_cta_heading', true ) ?: Settings::get( 'cta_heading', __( 'Book the {yacht_name} today', 'magepeople-yacht-booking-system' ) );
$mageyabo_cta_text          = get_post_meta( $mageyabo_yacht_id, 'mageyabo_cta_text', true ) ?: Settings::get( 'cta_text', __( 'Dates fill fast - lock in your preferred slot with an instant booking request.', 'magepeople-yacht-booking-system' ) );
$mageyabo_cta_button_label  = Settings::get( 'cta_button_label', __( 'Book Now', 'magepeople-yacht-booking-system' ) );
$mageyabo_cta_button2_label = Settings::get( 'cta_button2_label', __( 'Browse all yachts', 'magepeople-yacht-booking-system' ) );
$mageyabo_cta_heading       = strtr( $mageyabo_cta_heading_tpl, array( '{yacht_name}' => get_the_title( $mageyabo_yacht_id ) ) );

$mageyabo_rates         = Shortcode::yacht_rates_public( $mageyabo_yacht_id );
$mageyabo_min_rate      = $mageyabo_rates ? min( wp_list_pluck( $mageyabo_rates, 'amount' ) ) : 0;
$mageyabo_booking_mode  = get_post_meta( $mageyabo_yacht_id, 'mageyabo_booking_mode', true ) ?: 'full';
$mageyabo_shared_rates  = 'both' === $mageyabo_booking_mode ? Shortcode::yacht_shared_rates_public( $mageyabo_yacht_id ) : array();

$mageyabo_gallery_ids = is_array( $mageyabo_gallery_ids ) ? array_values( array_filter( array_map( 'intval', $mageyabo_gallery_ids ) ) ) : array();
$mageyabo_thumb_id    = (int) get_post_thumbnail_id( $mageyabo_yacht_id );

if ( $mageyabo_thumb_id && ! in_array( $mageyabo_thumb_id, $mageyabo_gallery_ids, true ) ) {
	array_unshift( $mageyabo_gallery_ids, $mageyabo_thumb_id );
}

$mageyabo_gallery = array();

foreach ( $mageyabo_gallery_ids as $mageyabo_gid ) {
	$mageyabo_large = wp_get_attachment_image_url( $mageyabo_gid, 'large' );
	$mageyabo_full  = wp_get_attachment_image_url( $mageyabo_gid, 'full' );

	if ( $mageyabo_large ) {
		$mageyabo_gallery[] = array(
			'id'    => $mageyabo_gid,
			'large' => $mageyabo_large,
			'full'  => $mageyabo_full ? $mageyabo_full : $mageyabo_large,
			'alt'   => trim( get_post_meta( $mageyabo_gid, '_wp_attachment_image_alt', true ) ),
		);
	}
}

$mageyabo_similar = array();

if ( $mageyabo_classes ) {
	// One implementation, shared with the shortcode renderer.
	$mageyabo_similar = Shortcode::related_yachts_public( $mageyabo_yacht_id, wp_list_pluck( $mageyabo_classes, 'term_id' ) );
}

\MageYaBo\Frontend\Templates::document_start();
?>

<div class="ybs-ys" id="ybs-single">

	<div class="ybs-ys-container">
		<nav class="ybs-ys-breadcrumb" data-ybs-reveal aria-label="<?php esc_attr_e( 'Breadcrumb', 'magepeople-yacht-booking-system' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'magepeople-yacht-booking-system' ); ?></a>
			<span class="ybs-ys-breadcrumb__sep">/</span>
			<a href="<?php echo esc_url( get_post_type_archive_link( Yacht::POST_TYPE ) ); ?>"><?php esc_html_e( 'Yachts', 'magepeople-yacht-booking-system' ); ?></a>
			<span class="ybs-ys-breadcrumb__sep">/</span>
			<span class="ybs-ys-breadcrumb__current"><?php echo esc_html( get_the_title( $mageyabo_yacht_id ) ); ?></span>
		</nav>

		<?php if ( $mageyabo_gallery ) : ?>
			<section class="ybs-ys-gallery" data-ybs-reveal aria-label="<?php esc_attr_e( 'Photo gallery', 'magepeople-yacht-booking-system' ); ?>">
				<div class="ybs-ys-gallery__grid" data-ybs-carousel>
					<?php foreach ( array_slice( $mageyabo_gallery, 0, 5 ) as $mageyabo_i => $mageyabo_img ) : ?>
						<button
							type="button"
							class="ybs-ys-gallery__tile<?php echo 0 === $mageyabo_i ? ' ybs-ys-gallery__tile--hero' : ''; ?>"
							data-ybs-lightbox-open="<?php echo esc_attr( (string) $mageyabo_i ); ?>"
							data-full-src="<?php echo esc_url( $mageyabo_img['full'] ); ?>"
						>
							<img
								src="<?php echo esc_url( $mageyabo_img['large'] ); ?>"
								alt="<?php echo esc_attr( $mageyabo_img['alt'] ? $mageyabo_img['alt'] : get_the_title( $mageyabo_yacht_id ) ); ?>"
								loading="<?php echo 0 === $mageyabo_i ? 'eager' : 'lazy'; ?>"
							/>
							<?php if ( 4 === $mageyabo_i && count( $mageyabo_gallery ) > 5 ) : ?>
								<span class="ybs-ys-gallery__more">+<?php echo esc_html( count( $mageyabo_gallery ) - 5 ); ?></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>

					<span class="ybs-ys-gallery__counter" data-ybs-carousel-counter>1 / <?php echo esc_html( count( $mageyabo_gallery ) ); ?></span>
				</div>

				<div class="ybs-ys-gallery__dots" data-ybs-carousel-dots aria-hidden="true"></div>

				<div class="ybs-ys-gallery__actions">
					<button type="button" class="ybs-ys-pill-btn" data-ybs-lightbox-open="0">
						<span class="dashicons dashicons-camera-alt"></span>
						<?php echo esc_html( /* translators: %d: number of photos in the gallery. */ sprintf( __( 'View Photos (%d)', 'magepeople-yacht-booking-system' ), count( $mageyabo_gallery ) ) ); ?>
					</button>
				</div>
			</section>
		<?php endif; ?>

		<header class="ybs-ys-header" data-ybs-reveal>
			<div class="ybs-ys-header__badges">
				<?php if ( $mageyabo_classes ) : ?>
					<span class="ybs-ys-badge ybs-ys-badge--gold"><?php echo esc_html( $mageyabo_classes[0]->name ); ?></span>
				<?php endif; ?>
				<?php if ( $mageyabo_location_name ) : ?>
					<span class="ybs-ys-badge ybs-ys-badge--outline"><span class="dashicons dashicons-location"></span><?php echo esc_html( $mageyabo_location_name ); ?></span>
				<?php endif; ?>
				<span class="ybs-ys-available"><span class="ybs-ys-pulse"></span><?php esc_html_e( 'Available now', 'magepeople-yacht-booking-system' ); ?></span>
			</div>

			<h1 class="ybs-ys-title"><?php echo esc_html( get_the_title( $mageyabo_yacht_id ) ); ?></h1>

			<?php if ( get_the_excerpt( $mageyabo_yacht_id ) ) : ?>
				<p class="ybs-ys-lede"><?php echo esc_html( get_the_excerpt( $mageyabo_yacht_id ) ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( $mageyabo_capacity || $mageyabo_length || $mageyabo_cabins || $mageyabo_crew || $mageyabo_build_year || $mageyabo_location_name ) : ?>
			<section class="ybs-ys-specs" data-ybs-reveal aria-label="<?php esc_attr_e( 'Specifications', 'magepeople-yacht-booking-system' ); ?>">
				<?php if ( $mageyabo_capacity ) : ?>
					<div class="ybs-ys-spec">
						<span class="ybs-ys-spec__icon dashicons dashicons-groups"></span>
						<span class="ybs-ys-spec__label"><?php esc_html_e( 'Guests', 'magepeople-yacht-booking-system' ); ?></span>
						<span class="ybs-ys-spec__value" data-ybs-count="<?php echo esc_attr( (string) $mageyabo_capacity ); ?>"><?php echo esc_html( (string) $mageyabo_capacity ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $mageyabo_length ) : ?>
					<div class="ybs-ys-spec">
						<span class="ybs-ys-spec__icon dashicons dashicons-leftright"></span>
						<span class="ybs-ys-spec__label"><?php esc_html_e( 'Length', 'magepeople-yacht-booking-system' ); ?></span>
						<span class="ybs-ys-spec__value"><?php echo esc_html( /* translators: %s: yacht length in metres. */ sprintf( __( '%s m', 'magepeople-yacht-booking-system' ), $mageyabo_length ) ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $mageyabo_cabins ) : ?>
					<div class="ybs-ys-spec">
						<span class="ybs-ys-spec__icon dashicons dashicons-admin-home"></span>
						<span class="ybs-ys-spec__label"><?php esc_html_e( 'Cabins', 'magepeople-yacht-booking-system' ); ?></span>
						<span class="ybs-ys-spec__value"><?php echo esc_html( (string) $mageyabo_cabins ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $mageyabo_crew ) : ?>
					<div class="ybs-ys-spec">
						<span class="ybs-ys-spec__icon dashicons dashicons-admin-users"></span>
						<span class="ybs-ys-spec__label"><?php esc_html_e( 'Crew', 'magepeople-yacht-booking-system' ); ?></span>
						<span class="ybs-ys-spec__value"><?php echo esc_html( (string) $mageyabo_crew ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $mageyabo_build_year ) : ?>
					<div class="ybs-ys-spec">
						<span class="ybs-ys-spec__icon dashicons dashicons-calendar-alt"></span>
						<span class="ybs-ys-spec__label"><?php esc_html_e( 'Build year', 'magepeople-yacht-booking-system' ); ?></span>
						<span class="ybs-ys-spec__value"><?php echo esc_html( $mageyabo_build_year ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $mageyabo_location_name ) : ?>
					<div class="ybs-ys-spec">
						<span class="ybs-ys-spec__icon dashicons dashicons-location"></span>
						<span class="ybs-ys-spec__label"><?php esc_html_e( 'Location', 'magepeople-yacht-booking-system' ); ?></span>
						<span class="ybs-ys-spec__value"><?php echo esc_html( $mageyabo_location_name ); ?></span>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<div class="ybs-ys-layout">
			<main class="ybs-ys-main">

				<section class="ybs-ys-section" data-ybs-reveal>
					<span class="ybs-ys-eyebrow"><?php esc_html_e( 'Overview', 'magepeople-yacht-booking-system' ); ?></span>
					<h2 class="ybs-ys-h2"><?php echo esc_html( /* translators: %s: yacht name. */ sprintf( __( 'The %s experience', 'magepeople-yacht-booking-system' ), get_the_title( $mageyabo_yacht_id ) ) ); ?></h2>
					<?php
					// Run the real `the_content` filter, as core does, rather
					// than hand-rolling wpautop()+do_shortcode(). Safe from
					// recursion because Templates::load_single_yacht_template()
					// already unhooked Shortcode::append_to_single_yacht().
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `the_content` is a core filter, applied here as core does.
					$mageyabo_content = apply_filters( 'the_content', get_post_field( 'post_content', $mageyabo_yacht_id ) );
					?>
					<div class="ybs-ys-prose"><?php echo $mageyabo_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- post content passed through the_content, as core does. ?></div>
				</section>

				<?php if ( $mageyabo_rates ) : ?>
					<section class="ybs-ys-section" data-ybs-reveal>
						<span class="ybs-ys-eyebrow"><?php esc_html_e( 'Pricing', 'magepeople-yacht-booking-system' ); ?></span>
						<h2 class="ybs-ys-h2"><?php esc_html_e( 'Charter Rates', 'magepeople-yacht-booking-system' ); ?></h2>
						<div class="ybs-ys-rates-card">
							<?php if ( $mageyabo_shared_rates ) : ?>
								<div class="ybs-ys-rates__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Charter type', 'magepeople-yacht-booking-system' ); ?>">
									<button
										type="button"
										class="ybs-ys-rates__tab is-active"
										role="tab"
										aria-selected="true"
										data-ybs-rates-tab="full"
									><?php esc_html_e( 'Full charter', 'magepeople-yacht-booking-system' ); ?></button>
									<button
										type="button"
										class="ybs-ys-rates__tab"
										role="tab"
										aria-selected="false"
										data-ybs-rates-tab="shared"
									><?php esc_html_e( 'Shared · per seat', 'magepeople-yacht-booking-system' ); ?></button>
								</div>

								<div class="ybs-ys-rates__panel is-active" data-ybs-rates-panel="full" role="tabpanel">
									<ul class="ybs-ys-rates__list">
										<?php foreach ( $mageyabo_rates as $mageyabo_rate ) : ?>
											<li>
												<span class="ybs-ys-rates__name">
													<?php echo esc_html( $mageyabo_rate['label'] ); ?>
													<?php if ( ! empty( $mageyabo_rate['window'] ) ) : ?>
														<small><?php echo esc_html( $mageyabo_rate['window'][0] . ' – ' . $mageyabo_rate['window'][1] ); ?></small>
													<?php endif; ?>
												</span>
												<strong><?php echo esc_html( Shortcode::format_price_public( $mageyabo_rate['amount'], $mageyabo_currency ) ); ?></strong>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>

								<div class="ybs-ys-rates__panel" data-ybs-rates-panel="shared" role="tabpanel" hidden>
									<ul class="ybs-ys-rates__list">
										<?php foreach ( $mageyabo_shared_rates as $mageyabo_rate ) : ?>
											<li>
												<span class="ybs-ys-rates__name">
													<?php echo esc_html( $mageyabo_rate['label'] ); ?>
													<?php if ( ! empty( $mageyabo_rate['window'] ) ) : ?>
														<small><?php echo esc_html( $mageyabo_rate['window'][0] . ' – ' . $mageyabo_rate['window'][1] ); ?></small>
													<?php endif; ?>
												</span>
												<strong><?php echo esc_html( Shortcode::format_price_public( $mageyabo_rate['amount'], $mageyabo_currency ) ); ?></strong>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php else : ?>
								<ul class="ybs-ys-rates__list">
									<?php foreach ( $mageyabo_rates as $mageyabo_rate ) : ?>
										<li>
											<span class="ybs-ys-rates__name">
												<?php echo esc_html( $mageyabo_rate['label'] ); ?>
												<?php if ( ! empty( $mageyabo_rate['window'] ) ) : ?>
													<small><?php echo esc_html( $mageyabo_rate['window'][0] . ' – ' . $mageyabo_rate['window'][1] ); ?></small>
												<?php endif; ?>
											</span>
											<strong><?php echo esc_html( Shortcode::format_price_public( $mageyabo_rate['amount'], $mageyabo_currency ) ); ?></strong>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( is_array( $mageyabo_included ) && $mageyabo_included ) : ?>
					<section class="ybs-ys-section" data-ybs-reveal>
						<span class="ybs-ys-eyebrow"><?php esc_html_e( 'On board', 'magepeople-yacht-booking-system' ); ?></span>
						<h2 class="ybs-ys-h2"><?php esc_html_e( 'Amenities & features', 'magepeople-yacht-booking-system' ); ?></h2>
						<ul class="ybs-ys-checklist">
							<?php foreach ( $mageyabo_included as $mageyabo_item ) : ?>
								<li class="ybs-ys-check">
									<span class="ybs-ys-check__mark" aria-hidden="true">&#10003;</span>
									<?php echo esc_html( is_array( $mageyabo_item ) ? ( $mageyabo_item['text'] ?? '' ) : $mageyabo_item ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( $mageyabo_occasions ) : ?>
					<section class="ybs-ys-section" data-ybs-reveal>
						<span class="ybs-ys-eyebrow"><?php esc_html_e( 'Perfect for', 'magepeople-yacht-booking-system' ); ?></span>
						<h2 class="ybs-ys-h2"><?php esc_html_e( 'Occasions this yacht fits best', 'magepeople-yacht-booking-system' ); ?></h2>
						<div class="ybs-ys-chips">
							<?php
							foreach ( $mageyabo_occasions as $term ) :
								// get_term_link() returns WP_Error for a term whose
								// taxonomy is gone; passing that to esc_url() is a
								// TypeError on PHP 8.
								$mageyabo_term_link = get_term_link( $term );
								?>
								<a class="ybs-ys-chip" href="<?php echo esc_url( is_wp_error( $mageyabo_term_link ) ? '' : $mageyabo_term_link ); ?>"><?php echo esc_html( $term->name ); ?></a>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( $mageyabo_lat && $mageyabo_lng ) : ?>
					<section class="ybs-ys-section" data-ybs-reveal>
						<span class="ybs-ys-eyebrow"><?php esc_html_e( 'Where you cruise', 'magepeople-yacht-booking-system' ); ?></span>
						<h2 class="ybs-ys-h2"><?php esc_html_e( 'Location & boarding point', 'magepeople-yacht-booking-system' ); ?></h2>
						<div class="ybs-ys-map-wrap">
							<div class="ybs-yacht-map" data-lat="<?php echo esc_attr( $mageyabo_lat ); ?>" data-lng="<?php echo esc_attr( $mageyabo_lng ); ?>" data-name="<?php echo esc_attr( $mageyabo_location_name ); ?>"></div>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( $mageyabo_similar ) : ?>
					<section class="ybs-ys-section" data-ybs-reveal>
						<span class="ybs-ys-eyebrow"><?php esc_html_e( 'Similar yachts', 'magepeople-yacht-booking-system' ); ?></span>
						<h2 class="ybs-ys-h2"><?php esc_html_e( 'You might also like', 'magepeople-yacht-booking-system' ); ?></h2>
						<div class="ybs-ys-similar">
							<?php foreach ( $mageyabo_similar as $mageyabo_other ) : ?>
								<?php
									$mageyabo_other_rates   = Shortcode::yacht_rates_public( $mageyabo_other->ID );
									$mageyabo_other_min     = $mageyabo_other_rates ? min( wp_list_pluck( $mageyabo_other_rates, 'amount' ) ) : 0;
									$mageyabo_other_meta    = array_filter(
										array(
											get_post_meta( $mageyabo_other->ID, 'mageyabo_length', true ) ? get_post_meta( $mageyabo_other->ID, 'mageyabo_length', true ) . ' m' : '',
											get_post_meta( $mageyabo_other->ID, 'mageyabo_capacity', true ) ? get_post_meta( $mageyabo_other->ID, 'mageyabo_capacity', true ) . __( ' guests', 'magepeople-yacht-booking-system' ) : '',
										)
									);
									$mageyabo_other_classes = wp_get_post_terms( $mageyabo_other->ID, 'mageyabo_yacht_class' );
								?>
								<a class="ybs-ys-card" href="<?php echo esc_url( get_permalink( $mageyabo_other ) ); ?>">
									<div class="ybs-ys-card__media">
										<?php $mageyabo_other_thumb = get_the_post_thumbnail_url( $mageyabo_other, 'medium_large' ); ?>
										<?php if ( $mageyabo_other_thumb ) : ?>
											<img src="<?php echo esc_url( $mageyabo_other_thumb ); ?>" alt="<?php echo esc_attr( get_the_title( $mageyabo_other ) ); ?>" loading="lazy" />
										<?php endif; ?>
									</div>
									<div class="ybs-ys-card__body">
										<h3 class="ybs-ys-card__title"><?php echo esc_html( get_the_title( $mageyabo_other ) ); ?></h3>
										<?php if ( $mageyabo_other_meta ) : ?>
											<p class="ybs-ys-card__meta"><?php echo esc_html( implode( ' · ', $mageyabo_other_meta ) ); ?></p>
										<?php endif; ?>
										<?php if ( $mageyabo_other_min > 0 ) : ?>
											<p class="ybs-ys-card__price">
												<em><?php esc_html_e( 'from', 'magepeople-yacht-booking-system' ); ?></em>
												<strong><?php echo esc_html( Shortcode::format_price_public( $mageyabo_other_min, $mageyabo_currency ) ); ?></strong>
												<span><?php esc_html_e( '/ hour · VAT excluded', 'magepeople-yacht-booking-system' ); ?></span>
											</p>
										<?php endif; ?>
									</div>
								</a>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( is_array( $mageyabo_faq ) && $mageyabo_faq ) : ?>
					<section class="ybs-ys-section" data-ybs-reveal id="ybs-faq">
						<span class="ybs-ys-eyebrow ybs-ys-eyebrow--line"><?php esc_html_e( 'Frequently asked', 'magepeople-yacht-booking-system' ); ?></span>
						<h2 class="ybs-ys-h2"><?php echo esc_html( /* translators: %s: yacht name. */ sprintf( __( 'Questions about the %s', 'magepeople-yacht-booking-system' ), get_the_title( $mageyabo_yacht_id ) ) ); ?></h2>
						<div class="ybs-ys-faq">
							<?php foreach ( $mageyabo_faq as $mageyabo_item ) : ?>
								<details class="ybs-ys-faq-item">
									<summary class="ybs-ys-faq-q">
										<span><?php echo esc_html( $mageyabo_item['question'] ?? '' ); ?></span>
										<span class="ybs-ys-faq-icon" aria-hidden="true"></span>
									</summary>
									<div class="ybs-ys-faq-a"><?php echo wp_kses_post( $mageyabo_item['answer'] ?? '' ); ?></div>
								</details>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>
			</main>

			<aside class="ybs-ys-sidebar">
				<div class="ybs-ys-bookcard" data-ybs-sticky>
					<div class="ybs-ys-form" id="ybs-book">
						<span class="ybs-ys-form-eyebrow"><?php esc_html_e( 'Get an instant quote', 'magepeople-yacht-booking-system' ); ?></span>
						<?php echo Shortcode::render_booking_form( array( 'yacht_id' => $mageyabo_yacht_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted plugin markup. ?>
					</div>

					<footer class="ybs-ys-trust">
						<div><strong>5.0&#9733;</strong><span><?php esc_html_e( 'GOOGLE', 'magepeople-yacht-booking-system' ); ?></span></div>
						<div><strong>100%</strong><span><?php esc_html_e( 'INSURED', 'magepeople-yacht-booking-system' ); ?></span></div>
						<div><strong>24/7</strong><span><?php esc_html_e( 'SUPPORT', 'magepeople-yacht-booking-system' ); ?></span></div>
					</footer>
				</div>
			</aside>
		</div>

		<?php if ( ! $mageyabo_cta_disabled ) : ?>
			<section class="ybs-ys-cta" data-ybs-reveal>
				<span class="ybs-ys-eyebrow"><?php echo esc_html( $mageyabo_cta_eyebrow ); ?></span>
				<h2 class="ybs-ys-h2"><?php echo esc_html( $mageyabo_cta_heading ); ?></h2>
				<p><?php echo esc_html( $mageyabo_cta_text ); ?></p>
				<div class="ybs-ys-cta__actions">
					<a class="ybs-ys-btn ybs-ys-btn--gold" href="#ybs-book"><?php echo esc_html( $mageyabo_cta_button_label ); ?></a>
					<a class="ybs-ys-btn ybs-ys-btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( Yacht::POST_TYPE ) ); ?>"><?php echo esc_html( $mageyabo_cta_button2_label ); ?></a>
				</div>
			</section>
		<?php endif; ?>
	</div>

	<div class="ybs-ys-dock" data-ybs-dock hidden>
		<div class="ybs-ys-dock__price">
			<?php if ( $mageyabo_min_rate > 0 ) : ?>
				<strong><?php echo esc_html( Shortcode::format_price_public( $mageyabo_min_rate, $mageyabo_currency ) ); ?></strong>
				<span><?php esc_html_e( 'per hour', 'magepeople-yacht-booking-system' ); ?></span>
			<?php else : ?>
				<strong><?php echo esc_html( get_the_title( $mageyabo_yacht_id ) ); ?></strong>
			<?php endif; ?>
		</div>
		<a class="ybs-ys-dock__btn" href="#ybs-book"><?php esc_html_e( 'Book Now', 'magepeople-yacht-booking-system' ); ?></a>
	</div>

	<div class="ybs-ys-lightbox" data-ybs-lightbox hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Photo viewer', 'magepeople-yacht-booking-system' ); ?>">
		<button type="button" class="ybs-ys-lightbox__close" data-ybs-lightbox-close aria-label="<?php esc_attr_e( 'Close', 'magepeople-yacht-booking-system' ); ?>">&times;</button>
		<button type="button" class="ybs-ys-lightbox__nav ybs-ys-lightbox__nav--prev" data-ybs-lightbox-prev aria-label="<?php esc_attr_e( 'Previous photo', 'magepeople-yacht-booking-system' ); ?>">&#8249;</button>
		<figure class="ybs-ys-lightbox__stage">
			<img src="" alt="" />
			<figcaption></figcaption>
		</figure>
		<button type="button" class="ybs-ys-lightbox__nav ybs-ys-lightbox__nav--next" data-ybs-lightbox-next aria-label="<?php esc_attr_e( 'Next photo', 'magepeople-yacht-booking-system' ); ?>">&#8250;</button>
		<span class="ybs-ys-lightbox__counter"></span>
	</div>
</div>

<?php
\MageYaBo\Frontend\Templates::document_end();
