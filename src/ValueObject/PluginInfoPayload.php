<?php
/**
 * Value object for plugin information.
 *
 * @package Jcore\Update\ValueObject
 */

declare(strict_types=1);

namespace Jcore\Update\ValueObject;

/**
 * Class PluginInfoPayload
 *
 * Represents plugin information used for the information popup.
 */
final class PluginInfoPayload {

	/**
	 * PluginInfoPayload constructor.
	 *
	 * @param string                $name         The plugin name.
	 * @param string                $slug         The plugin slug.
	 * @param string                $version      The version.
	 * @param string|null           $tested       Tested version.
	 * @param string|null           $requires     Required version.
	 * @param string|null           $requiresPhp  Required PHP.
	 * @param string|null           $downloadLink Download link.
	 * @param array<string, string> $sections     Info sections.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $slug,
		public readonly string $version,
		public readonly ?string $tested,
		public readonly ?string $requires,
		public readonly ?string $requiresPhp,
		public readonly ?string $downloadLink,
		public readonly array $sections = array(),
	) {
	}

	/**
	 * Creates an instance from an UpdatePayload.
	 *
	 * @param string        $slug    The slug.
	 * @param UpdatePayload $payload The payload.
	 *
	 * @return self
	 */
	public static function fromUpdatePayload( string $slug, UpdatePayload $payload ): self {
		return new self(
			name: $payload->name ?? $slug,
			slug: $slug,
			version: $payload->newVersion,
			tested: $payload->tested,
			requires: $payload->requires,
			requiresPhp: $payload->requiresPhp,
			downloadLink: $payload->package,
			sections: $payload->sections ?? array(),
		);
	}
}
