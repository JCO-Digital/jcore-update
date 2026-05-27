<?php

declare(strict_types=1);

namespace Jcore\Update\ValueObject;

final class UpdatePayload {

	/**
	 * @param array<string, string>|null $sections
	 * @param array<string, string>|null $icons
	 * @param array<string, string>|null $banners
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
	 * @param array<string, mixed> $data
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
	 * @param mixed $value
	 */
	private static function stringOrNull( mixed $value ): ?string {
		return is_string( $value ) && $value !== '' ? $value : null;
	}

	/**
	 * @param mixed $value
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
