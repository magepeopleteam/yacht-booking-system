<?php
/**
 * Theme information for the booking workspace. Installation is supplied by Pro.
 *
 * @package MageYaBo
 */

namespace MageYaBo\Admin;

use MageYaBo\Install\DemoAssets;

// phpcs:disable WordPress.Files.FileName -- The plugin uses PSR-4 class filenames.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Provides theme information to the React workspace. */
class Themes {

	/**
	 * Describe Yachtiva without depending on the Pro add-on being installed.
	 *
	 * @return array Theme metadata and capability-checked WordPress actions.
	 */
	public static function config(): array {
		$theme = wp_get_theme( 'yachtiva' );
		$data  = array(
			'name'        => 'Yachtiva',
			'author'      => 'MagePeople',
			'screenshot'  => DemoAssets::url( 'admin/yachtiva-demo.png', MAGEYABO_PLUGIN_DIR . 'assets/admin/images/yachtiva-demo.png', MAGEYABO_PLUGIN_URL . 'assets/admin/images/yachtiva-demo.png' ),
			'demoUrl'     => 'https://wpyacht.com/',
			'purchaseUrl' => esc_url_raw( apply_filters( 'mageyabo_theme_purchase_url', 'https://mage-people.com/checkout?edd_action=add_to_cart&download_id=144408&edd_options%5Bprice_id%5D=1' ) ),
			'available'   => false,
			'installUrl'  => '',
			'installed'   => $theme->exists(),
			'active'      => 'yachtiva' === get_template(),
			'version'     => $theme->exists() ? $theme->get( 'Version' ) : '1.0.4',
			'requiresWp'  => '6.4',
			'requiresPhp' => '7.4',
			'activateUrl' => '',
			'themesUrl'   => '',
			'networkGate' => is_multisite() && $theme->exists() && ! $theme->is_allowed(),
			'error'       => $theme->exists() && $theme->errors() ? $theme->errors()->get_error_message() : '',
		);

		/**
		 * Pro supplies bundled-theme availability, requirements and its installer.
		 *
		 * @param array $data Theme information for the admin workspace.
		 */
		$data = apply_filters( 'mageyabo_admin_theme', $data );

		if ( current_user_can( 'switch_themes' ) || current_user_can( 'edit_theme_options' ) ) {
			$data['themesUrl'] = admin_url( 'themes.php?theme=yachtiva' );
		}

		if ( $data['available'] && $data['installed'] && ! $data['active'] && ! $data['error'] && ! $data['networkGate'] && current_user_can( 'switch_themes' ) ) {
			// React escapes href attributes; wp_nonce_url() would double-escape them.
			$data['activateUrl'] = add_query_arg( '_wpnonce', wp_create_nonce( 'switch-theme_yachtiva' ), admin_url( 'themes.php?action=activate&stylesheet=yachtiva' ) );
		}

		return $data;
	}
}
