<?php

declare(strict_types=1);

namespace Jcore\Update\ValueObject;

final class PluginIdentity {

	public function __construct(
		public readonly string $slug,
		public readonly string $pluginBasename,
		public readonly string $version,
	) {
	}
}
