<?php
/**
 * Interface for license key providers.
 *
 * @package Jcore\Update\Licensing
 */

declare(strict_types=1);

namespace Jcore\Update\Licensing;

/**
 * Interface LicenseProviderInterface
 *
 * Defines how to retrieve a license key dynamically.
 */
interface LicenseProviderInterface {

	/**
	 * Gets the license key.
	 *
	 * @return string|null
	 */
	public function getLicenseKey(): ?string;
}
