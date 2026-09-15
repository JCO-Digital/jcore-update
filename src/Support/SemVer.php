<?php
/**
 * Semantic versioning helper.
 *
 * @package Jcore\Update\Support
 */

declare(strict_types=1);

namespace Jcore\Update\Support;

/**
 * Class SemVer
 *
 * Provides utilities for parsing and comparing semantic version numbers.
 */
final class SemVer {

	/**
	 * Cleans and normalizes a version string by trimming whitespace and removing leading 'v' or 'V'.
	 *
	 * @param string $version The raw version string.
	 *
	 * @return string The normalized version string.
	 */
	public static function clean( string $version ): string {
		return \ltrim( \trim( $version ), 'vV' );
	}

	/**
	 * Extracts the major version number.
	 *
	 * @param string $version The version string.
	 *
	 * @return int The major version number.
	 */
	public static function getMajor( string $version ): int {
		$cleaned = self::clean( $version );
		$parts   = \explode( '.', $cleaned );

		return isset( $parts[0] ) && \is_numeric( $parts[0] ) ? (int) $parts[0] : 0;
	}

	/**
	 * Extracts the minor version number.
	 *
	 * @param string $version The version string.
	 *
	 * @return int The minor version number.
	 */
	public static function getMinor( string $version ): int {
		$cleaned = self::clean( $version );
		$parts   = \explode( '.', $cleaned );

		return isset( $parts[1] ) && \is_numeric( $parts[1] ) ? (int) $parts[1] : 0;
	}

	/**
	 * Extracts the patch version number.
	 *
	 * @param string $version The version string.
	 *
	 * @return int The patch version number.
	 */
	public static function getPatch( string $version ): int {
		$cleaned = self::clean( $version );
		$parts   = \explode( '.', $cleaned );

		if ( ! isset( $parts[2] ) ) {
			return 0;
		}

		$patchPart = \explode( '-', $parts[2] )[0];
		$patchPart = \explode( '+', $patchPart )[0];

		return \is_numeric( $patchPart ) ? (int) $patchPart : 0;
	}

	/**
	 * Checks if targetVersion is a major version bump over currentVersion.
	 *
	 * @param string $currentVersion The current version.
	 * @param string $targetVersion  The target version.
	 *
	 * @return bool True if targetVersion has a higher major version.
	 */
	public static function isMajorBump( string $currentVersion, string $targetVersion ): bool {
		return self::getMajor( $targetVersion ) > self::getMajor( $currentVersion );
	}

	/**
	 * Checks if two versions share the same major version.
	 *
	 * @param string $versionA First version.
	 * @param string $versionB Second version.
	 *
	 * @return bool True if both versions have the same major version.
	 */
	public static function isSameMajor( string $versionA, string $versionB ): bool {
		return self::getMajor( $versionA ) === self::getMajor( $versionB );
	}

	/**
	 * Compares two versions using standard PHP version comparison.
	 *
	 * @param string $versionA First version.
	 * @param string $versionB Second version.
	 *
	 * @return int -1 if versionA < versionB, 0 if equal, 1 if versionA > versionB.
	 */
	public static function compare( string $versionA, string $versionB ): int {
		return \version_compare( self::clean( $versionA ), self::clean( $versionB ) );
	}
}
