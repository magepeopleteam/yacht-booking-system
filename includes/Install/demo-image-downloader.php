<?php
/**
 * Shared, self-contained original-photo loader for Free and Yachtiva imports.
 * Keep the theme copy byte-identical to this file.
 *
 * @package MagePeople
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return a verified temporary JPEG from the local bundle or the public CDN.
 *
 * @param string $filename        Original photo filename.
 * @param string $local_directory Optional local originals directory.
 * @param string $manifest_path   Bundled, trusted image inventory.
 * @return string|WP_Error Temporary file path, or an error without leftover files.
 */
return static function ( $filename, $local_directory, $manifest_path ) {
	if ( ! is_string( $filename ) || basename( $filename ) !== $filename ) {
		return new WP_Error( 'mageyabo_demo_image_unknown', __( 'The requested demo photo is not in the image inventory.', 'magepeople-yacht-booking-system' ) );
	}
	static $manifests = array();
	if ( ! isset( $manifests[ $manifest_path ] ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- This is the bundled local inventory, not an HTTP request.
		$manifests[ $manifest_path ] = is_readable( $manifest_path ) ? json_decode( file_get_contents( $manifest_path ), true ) : array();
	}
	$manifest = $manifests[ $manifest_path ];
	$asset    = $manifest['assets'][ 'demo/' . $filename ] ?? null;
	if ( ! is_array( $asset ) || empty( $manifest['base_url'] ) ) {
		return new WP_Error( 'mageyabo_demo_image_unknown', __( 'The requested demo photo is not in the image inventory.', 'magepeople-yacht-booking-system' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$temporary = wp_tempnam( $filename );
	if ( ! $temporary ) {
		return new WP_Error( 'mageyabo_demo_image_temp', __( 'WordPress could not create a temporary photo file.', 'magepeople-yacht-booking-system' ) );
	}

	$local = trailingslashit( $local_directory ) . $filename;
	if ( is_readable( $local ) ) {
		if ( ! copy( $local, $temporary ) ) {
			wp_delete_file( $temporary );
			return new WP_Error( 'mageyabo_demo_image_copy', __( 'WordPress could not copy the demo photo.', 'magepeople-yacht-booking-system' ) );
		}
	} else {
		$url      = trailingslashit( $manifest['base_url'] ) . 'demo/' . rawurlencode( $filename );
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 30,
				'redirection'         => 3,
				'stream'              => true,
				'filename'            => $temporary,
				'limit_response_size' => (int) $asset['bytes'] + 1,
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			wp_delete_file( $temporary );
			return new WP_Error( 'mageyabo_demo_image_download', __( 'The original demo photo could not be downloaded. Check the internet connection and try again.', 'magepeople-yacht-booking-system' ) );
		}
	}

	$size = wp_getimagesize( $temporary );
	// A matching SHA-256 preserves every original byte, including dimensions.
	if ( ! $size || 'image/jpeg' !== $size['mime'] || ! hash_equals( $asset['sha256'], hash_file( 'sha256', $temporary ) ) ) {
		wp_delete_file( $temporary );
		return new WP_Error( 'mageyabo_demo_image_integrity', __( 'The downloaded demo photo did not match the original. Please try again.', 'magepeople-yacht-booking-system' ) );
	}
	return $temporary;
};
