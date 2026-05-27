<?php
/**
 * Value object for plugin identity.
 *
 * @package Jcore\Update\ValueObject
 */

declare(strict_types=1);

namespace Jcore\Update\ValueObject;

/**
 * Class PluginIdentity
 *
 * Holds identity information about a plugin.
 */
final class PluginIdentity {

	/**
	 * PluginIdentity constructor.
	 *
	 * @param string $slug           The plugin slug.
	 * @param string $pluginBasename The plugin basename (e.g. slug/slug.php).
	 * @param string $version        The current version.
	 */
	public function __construct(
		public readonly string $slug,
		public readonly string $pluginBasename,
		public readonly string $version,
	) {
	}
}
