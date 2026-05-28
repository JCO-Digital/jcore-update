<?php
/**
 * Helper for WordPress plugin operations.
 *
 * @package Jcore\Update\Support
 */

declare(strict_types=1);

namespace Jcore\Update\Support;

/**
 * Class PluginHelper
 */
final class PluginHelper {

	/**
	 * Gets the version from the plugin file header.
	 *
	 * @param string $pluginFile The main plugin file path.
	 *
	 * @return string The version or an empty string if not found.
	 */
	public static function getVersion( string $pluginFile ): string {
		if ( ! \is_file( $pluginFile ) ) {
			return '';
		}

		if ( ! \function_exists( 'get_file_data' ) ) {
			$content = \file_get_contents( $pluginFile );
			if ( $content === false ) {
				return '';
			}

			if ( \preg_match( '/^[ \t\/*#@]*Version:(.*)$/mi', $content, $matches ) ) {
				return \trim( $matches[1] );
			}

			return '';
		}

		$data = \get_file_data( $pluginFile, array( 'Version' => 'Version' ), 'plugin' );

		return $data['Version'] ?? '';
	}
}
