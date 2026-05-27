<?php

declare(strict_types=1);

namespace Jcore\Update\ValueObject;

final class PluginInfoPayload {

	/**
	 * @param array<string, string> $sections
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
