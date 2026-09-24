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
	 * Gets the version from the plugin file header or parent directory plugin files.
	 *
	 * @param string $pluginFile The main plugin file path or a subfile path within the plugin.
	 *
	 * @return string The version or an empty string if not found.
	 */
	public static function getVersion( string $pluginFile ): string {
		if ( $pluginFile === '' ) {
			return '';
		}

		// 1. Direct file check if it points to a valid file.
		if ( \is_file( $pluginFile ) ) {
			$version = self::extractVersionFromFile( $pluginFile );
			if ( $version !== '' ) {
				return $version;
			}
		}

		// 2. Determine starting directory for directory traversal.
		$dir = \is_dir( $pluginFile ) ? $pluginFile : \dirname( $pluginFile );

		// 3. Traverse upwards looking for plugin root file (up to 5 levels).
		$maxLevels = 5;
		$level     = 0;

		while ( $level < $maxLevels && $dir !== '' && $dir !== '.' && $dir !== '/' ) {
			$dirBase = \basename( $dir );

			// Stop traversal if we reach system/WordPress container directories.
			if ( \in_array( $dirBase, array( 'plugins', 'mu-plugins', 'wp-content', 'vendor', 'node_modules' ), true ) ) {
				break;
			}

			$version = self::scanDirectoryForVersion( $dir );
			if ( $version !== '' ) {
				return $version;
			}

			$parent = \dirname( $dir );
			if ( $parent === $dir ) {
				break;
			}

			$dir = $parent;
			++$level;
		}

		return '';
	}

	/**
	 * Reads the standard plugin headers from the main plugin file.
	 *
	 * Uses `get_file_data()` when WordPress is loaded and falls back to a
	 * header regex otherwise. Missing headers are returned as empty strings.
	 *
	 * @param string $pluginFile The main plugin file path.
	 *
	 * @return array{Name: string, PluginURI: string, Version: string, Description: string, Author: string, AuthorURI: string, RequiresWP: string, RequiresPHP: string}
	 */
	public static function getPluginData( string $pluginFile ): array {
		$headers = array(
			'Name'        => 'Plugin Name',
			'PluginURI'   => 'Plugin URI',
			'Version'     => 'Version',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'RequiresWP'  => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
		);

		$data = \array_fill_keys( \array_keys( $headers ), '' );

		if ( $pluginFile === '' || ! \is_file( $pluginFile ) || ! \is_readable( $pluginFile ) ) {
			return $data;
		}

		if ( \function_exists( 'get_file_data' ) ) {
			$fileData = \get_file_data( $pluginFile, $headers, 'plugin' );
			foreach ( $data as $key => $unused ) {
				$data[ $key ] = isset( $fileData[ $key ] ) && \is_string( $fileData[ $key ] ) ? \trim( $fileData[ $key ] ) : '';
			}

			return $data;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = \file_get_contents( $pluginFile, false, null, 0, 8192 );
		if ( $content === false ) {
			return $data;
		}

		foreach ( $headers as $key => $header ) {
			if ( \preg_match( '/^[ \t\/*#@]*' . \preg_quote( $header, '/' ) . ':(.*)$/mi', $content, $matches ) ) {
				$data[ $key ] = \trim( (string) \preg_replace( '/\s*(?:\*\/|\?>).*/', '', $matches[1] ) );
			}
		}

		return $data;
	}

	/**
	 * Extracts the Version header from a single PHP file.
	 *
	 * @param string $filePath The file path.
	 *
	 * @return string The version or an empty string if not found.
	 */
	private static function extractVersionFromFile( string $filePath ): string {
		if ( ! \is_file( $filePath ) || ! \is_readable( $filePath ) ) {
			return '';
		}

		if ( ! \function_exists( 'get_file_data' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content = \file_get_contents( $filePath, false, null, 0, 8192 );
			if ( $content === false ) {
				return '';
			}

			if ( \preg_match( '/^[ \t\/*#@]*Version:(.*)$/mi', $content, $matches ) ) {
				return \trim( $matches[1] );
			}

			return '';
		}

		$data = \get_file_data( $filePath, array( 'Version' => 'Version' ), 'plugin' );

		if ( ! empty( $data['Version'] ) ) {
			return $data['Version'];
		}

		// Fallback regex if get_file_data returned empty.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = \file_get_contents( $filePath, false, null, 0, 8192 );
		if ( $content !== false && \preg_match( '/^[ \t\/*#@]*Version:(.*)$/mi', $content, $matches ) ) {
			return \trim( $matches[1] );
		}

		return '';
	}

	/**
	 * Scans a directory for a main plugin file and extracts its version.
	 *
	 * @param string $dir Directory path.
	 *
	 * @return string The version or empty string if not found.
	 */
	private static function scanDirectoryForVersion( string $dir ): string {
		if ( ! \is_dir( $dir ) || ! \is_readable( $dir ) ) {
			return '';
		}

		$dirName = \basename( $dir );

		// Check conventional main plugin file first (e.g. plugin-name/plugin-name.php).
		$preferredFile = $dir . '/' . $dirName . '.php';
		if ( \is_file( $preferredFile ) ) {
			$version = self::extractVersionFromFile( $preferredFile );
			if ( $version !== '' ) {
				return $version;
			}
		}

		// Scan all .php files in the directory (non-recursive).
		$files = \glob( $dir . '/*.php' );
		if ( ! \is_array( $files ) ) {
			return '';
		}

		foreach ( $files as $file ) {
			if ( $file === $preferredFile ) {
				continue;
			}

			$version = self::extractVersionFromFile( $file );
			if ( $version !== '' ) {
				return $version;
			}
		}

		return '';
	}
}
