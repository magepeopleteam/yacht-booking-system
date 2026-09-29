<?php
/**
 * Bare canvas for the WooCommerce checkout and thank-you pages when they are
 * loaded inside the booking drawer (`?mageyabo_embed=1`, see
 * WooCommerceDrawer). No theme header or footer - just the page content -
 * but wp_head()/wp_footer() still run, so WooCommerce, the payment gateways
 * and the theme's form styles all load as usual.
 *
 * @package MageYaBo
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main class="mageyabo-embed">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
