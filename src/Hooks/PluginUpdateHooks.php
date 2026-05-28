<?php
/**
 * Hooks for WordPress plugin updates.
 *
 * @package Jcore\Update\Hooks
 */

declare(strict_types=1);

namespace Jcore\Update\Hooks;

use Jcore\Update\Client\UpdateApiClient;
use Jcore\Update\Config\UpdateConfig;
use Jcore\Update\Licensing\LicenseValidationResult;
use Jcore\Update\Support\LoggerInterface;
use Jcore\Update\Support\NullLogger;
use Jcore\Update\ValueObject\PluginInfoPayload;
use Jcore\Update\ValueObject\UpdatePayload;
use stdClass;

/**
 * Class PluginUpdateHooks
 *
 * Connects the UpdateApiClient to WordPress hooks.
 */
final class PluginUpdateHooks {

	/**
	 * The logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Whether the hooks have been registered.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * The API client.
	 *
	 * @var UpdateApiClient
	 */
	private UpdateApiClient $client;

	/**
	 * PluginUpdateHooks constructor.
	 *
	 * @param UpdateConfig         $config The configuration.
	 * @param UpdateApiClient|null $client Optional API client.
	 * @param LoggerInterface|null $logger Optional logger.
	 */
	public function __construct(
		private readonly UpdateConfig $config,
		?UpdateApiClient $client = null,
		?LoggerInterface $logger = null,
	) {
		$this->logger = $logger ?? $this->config->logger ?? new NullLogger();
		$this->client = $client ?? new UpdateApiClient( $this->config, $this->logger );
	}

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->registered || ! \function_exists( 'add_filter' ) ) {
			return;
		}

		\add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'checkUpdate' ) );
		\add_filter( 'plugins_api', array( $this, 'pluginPopup' ), 20, 3 );

		$this->registered = true;
	}

	/**
	 * Unregisters the hooks.
	 *
	 * @return void
	 */
	public function unregister(): void {
		if ( ! $this->registered || ! \function_exists( 'remove_filter' ) ) {
			return;
		}

		\remove_filter( 'pre_set_site_transient_update_plugins', array( $this, 'checkUpdate' ) );
		\remove_filter( 'plugins_api', array( $this, 'pluginPopup' ), 20 );

		$this->registered = false;
	}

	/**
	 * Filter for `pre_set_site_transient_update_plugins`.
	 *
	 * @param stdClass|mixed $transient The transient value.
	 *
	 * @return stdClass|mixed
	 */
	public function checkUpdate( mixed $transient ): mixed {
		if ( ! ( $transient instanceof stdClass ) ) {
			return $transient;
		}

		if ( ! isset( $transient->checked ) || ! \is_array( $transient->checked ) ) {
			return $transient;
		}

		$pluginBasename = $this->pluginBasename();
		if ( ! \array_key_exists( $pluginBasename, $transient->checked ) ) {
			return $transient;
		}

		// If we already have a response (update) or a no_update entry, stay native and don't re-check.
		if ( isset( $transient->response[ $pluginBasename ] ) || isset( $transient->no_update[ $pluginBasename ] ) ) {
			return $transient;
		}

		$installedVersion = \is_string( $transient->checked[ $pluginBasename ] )
			? $transient->checked[ $pluginBasename ]
			: $this->config->version;

		$licenseKey = $this->resolveLicenseKey();
		$result     = $this->client->checkForUpdate( $installedVersion, $licenseKey );

		if ( ! $result->success ) {
			$this->logger->debug(
				'JCORE update check failed; leaving transient untouched.',
				array(
					'slug'      => $this->config->slug,
					'errorCode' => $result->errorCode,
				)
			);
			return $transient;
		}

		if ( $result->noUpdate || $result->payload === null ) {
			return $this->markNoUpdate( $transient, $pluginBasename, $installedVersion );
		}

		$transient->response[ $pluginBasename ] = $this->toUpdateResponseObject( $result->payload, $pluginBasename );

		return $transient;
	}

	/**
	 * Filter for `plugins_api`.
	 *
	 * @param object|mixed $result The result object.
	 * @param string|mixed $action The action being performed.
	 * @param object|mixed $args   Arguments for the action.
	 *
	 * @return object|mixed
	 */
	public function pluginPopup( mixed $result, mixed $action, mixed $args ): mixed {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		if ( ! \is_object( $args ) || ! isset( $args->slug ) || ! \is_string( $args->slug ) || $args->slug !== $this->config->slug ) {
			return $result;
		}

		$licenseKey   = $this->resolveLicenseKey();
		$updateResult = $this->client->checkForUpdate( $this->config->version, $licenseKey );

		if ( ! $updateResult->success || $updateResult->noUpdate || $updateResult->payload === null ) {
			return $result;
		}

		$info = PluginInfoPayload::fromUpdatePayload( $this->config->slug, $updateResult->payload );
		return $this->toPluginInfoObject( $info );
	}

	/**
	 * Validates a license key.
	 *
	 * @param string $licenseKey   The license key.
	 * @param bool   $forceRefresh Whether to force a refresh.
	 *
	 * @return LicenseValidationResult
	 */
	public function validateLicense( string $licenseKey, bool $forceRefresh = false ): LicenseValidationResult {
		$trimmed = \trim( $licenseKey );

		if ( $trimmed === '' ) {
			return LicenseValidationResult::failure( 'invalid_payload', 'License key must not be empty.' );
		}

		return $this->client->validateLicense( $trimmed );
	}

	/**
	 * Checks if a license key is valid.
	 *
	 * @param string $licenseKey   The license key.
	 * @param bool   $forceRefresh Whether to force a refresh.
	 *
	 * @return bool
	 */
	public function isLicenseValid( string $licenseKey, bool $forceRefresh = false ): bool {
		return $this->validateLicense( $licenseKey, $forceRefresh )->valid;
	}

	/**
	 * Gets the plugin basename.
	 *
	 * @return string
	 */
	private function pluginBasename(): string {
		if ( \function_exists( 'plugin_basename' ) ) {
			return \plugin_basename( $this->config->pluginFile );
		}

		return \basename( \dirname( $this->config->pluginFile ) ) . '/' . \basename( $this->config->pluginFile );
	}

	/**
	 * Resolves the license key to use.
	 *
	 * @return string|null
	 */
	private function resolveLicenseKey(): ?string {
		if ( $this->config->licenseProvider !== null ) {
			$provided = $this->config->licenseProvider->getLicenseKey();
			if ( \is_string( $provided ) && $provided !== '' ) {
				return $provided;
			}
		}

		return $this->config->licenseKey;
	}

	/**
	 * Marks the plugin as having no update.
	 *
	 * @param stdClass $transient        The transient object.
	 * @param string   $pluginBasename   The plugin basename.
	 * @param string   $installedVersion The installed version.
	 *
	 * @return stdClass
	 */
	private function markNoUpdate( stdClass $transient, string $pluginBasename, string $installedVersion ): stdClass {
		if ( ! isset( $transient->no_update ) || ! \is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		$entry              = new stdClass();
		$entry->id          = $this->config->slug;
		$entry->slug        = $this->config->slug;
		$entry->plugin      = $pluginBasename;
		$entry->new_version = $installedVersion;
		$entry->url         = '';
		$entry->package     = '';

		$transient->no_update[ $pluginBasename ] = $entry;

		return $transient;
	}

	/**
	 * Converts an UpdatePayload to a WordPress update response object.
	 *
	 * @param UpdatePayload $payload        The payload.
	 * @param string        $pluginBasename The plugin basename.
	 *
	 * @return stdClass
	 */
	private function toUpdateResponseObject( UpdatePayload $payload, string $pluginBasename ): stdClass {
		$response               = new stdClass();
		$response->id           = $this->config->slug;
		$response->slug         = $this->config->slug;
		$response->plugin       = $pluginBasename;
		$response->new_version  = $payload->newVersion;
		$response->tested       = $payload->tested;
		$response->package      = $payload->package;
		$response->url          = $payload->url;
		$response->requires     = $payload->requires;
		$response->requires_php = $payload->requiresPhp;

		if ( $payload->icons !== null ) {
			$response->icons = $payload->icons;
		}

		if ( $payload->banners !== null ) {
			$response->banners = $payload->banners;
		}

		return $response;
	}

	/**
	 * Converts a PluginInfoPayload to a WordPress plugin information object.
	 *
	 * @param PluginInfoPayload $info The payload.
	 *
	 * @return stdClass
	 */
	private function toPluginInfoObject( PluginInfoPayload $info ): stdClass {
		$object                = new stdClass();
		$object->name          = $info->name;
		$object->slug          = $info->slug;
		$object->version       = $info->version;
		$object->tested        = $info->tested;
		$object->requires      = $info->requires;
		$object->requires_php  = $info->requiresPhp;
		$object->download_link = $info->downloadLink;
		$object->sections      = $info->sections;

		if ( $info->downloadLink !== null ) {
			$object->package = $info->downloadLink;
		}

		return $object;
	}
}
