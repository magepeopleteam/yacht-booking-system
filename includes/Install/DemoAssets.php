<?php
/**
 * Original demo and help images hosted outside the lightweight install ZIP.
 *
 * @package MageYaBo
 */

namespace MageYaBo\Install;

// phpcs:disable WordPress.Files.FileName -- This plugin uses PSR-4 class filenames.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolve original assets locally or at the pinned public image repository. */
class DemoAssets {

	/**
	 * Read the bundled image inventory once per request.
	 *
	 * @return array
	 */
	public static function manifest(): array {
		static $manifest = null;
		if ( null === $manifest ) {
			$file = MAGEYABO_PLUGIN_DIR . 'assets/demo/manifest.json';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read a bundled local JSON file, never a remote URL.
			$manifest = is_readable( $file ) ? json_decode( file_get_contents( $file ), true ) : array();
			$manifest = is_array( $manifest ) ? $manifest : array();
		}
		return $manifest;
	}

	/**
	 * Prefer an existing bundled image; otherwise use its original hosted file.
	 *
	 * @param string $key       Image path in the inventory.
	 * @param string $local     Optional bundled file path.
	 * @param string $local_url URL corresponding to the bundled file.
	 * @return string
	 */
	public static function url( string $key, string $local, string $local_url ): string {
		if ( is_readable( $local ) ) {
			return $local_url;
		}
		$manifest = self::manifest();
		if ( empty( $manifest['base_url'] ) || ! isset( $manifest['assets'][ $key ] ) ) {
			return '';
		}
		return esc_url_raw( trailingslashit( $manifest['base_url'] ) . implode( '/', array_map( 'rawurlencode', explode( '/', $key ) ) ) );
	}

	/**
	 * Original help screenshot URLs, keyed by their existing filenames.
	 *
	 * @return array<string,string>
	 */
	public static function guide_urls(): array {
		$urls     = array();
		$manifest = self::manifest();
		foreach ( array_keys( $manifest['assets'] ?? array() ) as $key ) {
			if ( 0 === strpos( $key, 'admin/guide/' ) ) {
				$file          = basename( $key );
				$urls[ $file ] = self::url( $key, MAGEYABO_PLUGIN_DIR . 'assets/admin/images/guide/' . $file, MAGEYABO_PLUGIN_URL . 'assets/admin/images/guide/' . $file );
			}
		}
		return $urls;
	}

	/**
	 * Obtain an unchanged photo in a temporary file for media_handle_sideload().
	 *
	 * @param string $filename Original photo filename.
	 * @return string|\WP_Error Temporary file; the caller owns its cleanup.
	 */
	public static function temporary_photo( string $filename ) {
		static $loader = null;
		if ( null === $loader ) {
			$loader = require __DIR__ . '/demo-image-downloader.php';
		}
		return $loader( $filename, MAGEYABO_PLUGIN_DIR . 'assets/demo/', MAGEYABO_PLUGIN_DIR . 'assets/demo/manifest.json' );
	}
}
