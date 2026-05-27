<?php
/**
 * Update payload value object.
 *
 * @package Jcore\Update\ValueObject
 */

declare(strict_types=1);

namespace Jcore\Update\ValueObject;

/**
 * Class UpdatePayload
 *
 * Represents the data returned by the update API.
 */
final class UpdatePayload {

	/**
	 * UpdatePayload constructor.
	 *
	 * @param string                     $newVersion  The new version.
	 * @param string|null                $package     The download package URL.
	 * @param string|null                $url         The info URL.
	 * @param string|null                $tested      The tested WordPress version.
	 * @param string|null                $requires    The required WordPress version.
	 * @param string|null                $requiresPhp The required PHP version.
	 * @param array<string, string>|null $sections    The info sections.
	 * @param array<string, string>|null $icons       The plugin icons.
	 * @param array<string, string>|null $banners     The plugin banners.
	 * @param string|null                $name        The plugin name.
	 */
	public function __construct(
		public readonly string $newVersion,
		public readonly ?string $package = null,
		public readonly ?string $url = null,
		public readonly ?string $tested = null,
		public readonly ?string $requires = null,
		public readonly ?string $requiresPhp = null,
		public readonly ?array $sections = null,
		public readonly ?array $icons = null,
		public readonly ?array $banners = null,
		public readonly ?string $name = null,
	) {
	}

	/**
	 * Creates an instance from API response data.
	 *
	 * @param array<string, mixed> $data The data.
	 *
	 * @return self|null
	 */
	public static function fromApiResponse( array $data ): ?self {
		$newVersion = isset( $data['new_version'] ) && is_string( $data['new_version'] )
			? $data['new_version']
			: null;

		if ( $newVersion === null || $newVersion === '' ) {
			return null;
		}

		return new self(
			newVersion: $newVersion,
			package: self::stringOrNull( $data['package'] ?? null ),
			url: self::stringOrNull( $data['url'] ?? null ),
			tested: self::stringOrNull( $data['tested'] ?? null ),
			requires: self::stringOrNull( $data['requires'] ?? null ),
			requiresPhp: self::stringOrNull( $data['requires_php'] ?? null ),
			sections: self::stringMapOrNull( $data['sections'] ?? null ),
			icons: self::stringMapOrNull( $data['icons'] ?? null ),
			banners: self::stringMapOrNull( $data['banners'] ?? null ),
			name: self::stringOrNull( $data['name'] ?? null ),
		);
	}

	/**
	 * Converts the payload to an array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'new_version'  => $this->newVersion,
			'package'      => $this->package,
			'url'          => $this->url,
			'tested'       => $this->tested,
			'requires'     => $this->requires,
			'requires_php' => $this->requiresPhp,
			'sections'     => $this->sections,
			'icons'        => $this->icons,
			'banners'      => $this->banners,
			'name'         => $this->name,
		);
	}

	/**
	 * Normalizes a value to string or null.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string|null
	 */
	private static function stringOrNull( mixed $value ): ?string {
		return is_string( $value ) && $value !== '' ? $value : null;
	}

	/**
	 * Normalizes a value to a string map or null.
	 *
	 * @param mixed $value The value.
	 *
	 * @return array<string, string>|null
	 */
	private static function stringMapOrNull( mixed $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$result = array();

		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && is_string( $item ) ) {
				$result[ $key ] = $item;
			}
		}

		return $result === array() ? null : $result;
	}
}
