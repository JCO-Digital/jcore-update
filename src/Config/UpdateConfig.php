<?php
/**
 * Configuration for the JCORE Update integration.
 *
 * @package Jcore\Update\Config
 */

declare(strict_types=1);

namespace Jcore\Update\Config;

use Closure;
use Jcore\Update\Licensing\LicenseProviderInterface;
use Jcore\Update\Support\LoggerInterface;
use Jcore\Update\Support\PluginHelper;

/**
 * Class UpdateConfig
 *
 * Holds configuration values for the update client and hooks.
 */
final class UpdateConfig {

	/**
	 * The current version.
	 *
	 * @var string
	 */
	public readonly string $version;

	/**
	 * UpdateConfig constructor.
	 *
	 * @param string                        $pluginFile                The main plugin file path.
	 * @param string                        $slug                      The plugin slug.
	 * @param string|null                   $version                   Optional current version (auto-detected from pluginFile if omitted or empty).
	 * @param string                        $apiBaseUrl                The API base URL.
	 * @param string|null                   $licenseKey                Optional hardcoded license key.
	 * @param LicenseProviderInterface|null $licenseProvider           Optional license provider.
	 * @param int                           $requestTimeout            API request timeout in seconds.
	 * @param int                           $updateCacheTtl            Update check cache TTL in seconds.
	 * @param int                           $licenseValidationCacheTtl License validation cache TTL in seconds.
	 * @param bool                          $filterMajorUpdates        Whether to filter major version updates by default.
	 * @param Closure|null                  $httpArgsFilter            Optional closure to filter HTTP args.
	 * @param LoggerInterface|null          $logger                    Optional logger.
	 *
	 * @throws \InvalidArgumentException If required parameters are empty.
	 */
	public function __construct(
		public readonly string $pluginFile,
		public readonly string $slug,
		?string $version = null,
		public readonly string $apiBaseUrl = '',
		public readonly ?string $licenseKey = null,
		public readonly ?LicenseProviderInterface $licenseProvider = null,
		public readonly int $requestTimeout = 10,
		public readonly int $updateCacheTtl = 43200,
		public readonly int $licenseValidationCacheTtl = 600,
		public readonly bool $filterMajorUpdates = true,
		public readonly ?Closure $httpArgsFilter = null,
		public readonly ?LoggerInterface $logger = null,
	) {
		if ( $this->pluginFile === '' ) {
			throw new \InvalidArgumentException( 'pluginFile must not be empty.' );
		}

		if ( $this->slug === '' ) {
			throw new \InvalidArgumentException( 'slug must not be empty.' );
		}

		$resolvedVersion = $version ?? '';
		if ( $resolvedVersion === '' ) {
			$resolvedVersion = PluginHelper::getVersion( $this->pluginFile );
		}
		$this->version = $resolvedVersion;

		if ( $this->version === '' ) {
			throw new \InvalidArgumentException( 'version must not be empty.' );
		}

		if ( $this->apiBaseUrl === '' ) {
			throw new \InvalidArgumentException( 'apiBaseUrl must not be empty.' );
		}

		if ( $this->requestTimeout < 1 ) {
			throw new \InvalidArgumentException( 'requestTimeout must be >= 1 second.' );
		}

		if ( $this->updateCacheTtl < 0 ) {
			throw new \InvalidArgumentException( 'updateCacheTtl must be >= 0.' );
		}

		if ( $this->licenseValidationCacheTtl < 0 ) {
			throw new \InvalidArgumentException( 'licenseValidationCacheTtl must be >= 0.' );
		}
	}

	/**
	 * Normalizes the API base URL by stripping trailing slashes.
	 *
	 * @return string
	 */
	public function normalizedApiBaseUrl(): string {
		return rtrim( $this->apiBaseUrl, '/' );
	}
}
