<?php
/**
 * Client for the JCORE Update API.
 *
 * @package Jcore\Update\Client
 */

declare(strict_types=1);

namespace Jcore\Update\Client;

use Jcore\Update\Config\UpdateConfig;
use Jcore\Update\Licensing\LicenseValidationResult;
use Jcore\Update\Support\LoggerInterface;
use Jcore\Update\Support\NullLogger;
use Jcore\Update\ValueObject\UpdatePayload;

/**
 * Class UpdateApiClient
 *
 * Handles HTTP requests to the JCORE Update API.
 */
final class UpdateApiClient {

	/**
	 * The logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * UpdateApiClient constructor.
	 *
	 * @param UpdateConfig         $config The configuration.
	 * @param LoggerInterface|null $logger Optional logger.
	 */
	public function __construct(
		private readonly UpdateConfig $config,
		?LoggerInterface $logger = null,
	) {
		$this->logger = $logger ?? $this->config->logger ?? new NullLogger();
	}

	/**
	 * Checks for updates for the plugin.
	 *
	 * @param string      $installedVersion The currently installed version.
	 * @param string|null $licenseKey       Optional license key.
	 *
	 * @return UpdateCheckResult
	 */
	public function checkForUpdate( string $installedVersion, ?string $licenseKey = null ): UpdateCheckResult {
		if ( ! $this->hasWordPressHttpApi() ) {
			return UpdateCheckResult::failure( 'transport_error', 'WordPress HTTP API is unavailable.' );
		}

		$url = $this->buildUpdateCheckUrl( $installedVersion, $licenseKey );

		$args = array(
			'timeout' => $this->config->requestTimeout,
			'headers' => array(
				'Accept' => 'application/json',
			),
		);

		$args = $this->filterHttpArgs( $args, 'update_check' );

		$response = \wp_remote_get( $url, $args );

		if ( \is_wp_error( $response ) ) {
			$this->logger->warning(
				'JCORE update check transport error.',
				array(
					'slug'  => $this->config->slug,
					'error' => $response->get_error_message(),
				)
			);

			return UpdateCheckResult::failure( 'transport_error', $response->get_error_message() );
		}

		$statusCode = (int) \wp_remote_retrieve_response_code( $response );

		if ( $statusCode === 204 ) {
			return UpdateCheckResult::noUpdate();
		}

		if ( $statusCode === 429 ) {
			return UpdateCheckResult::failure( 'rate_limited', 'Update API rate limit reached.' );
		}

		if ( $statusCode !== 200 ) {
			return UpdateCheckResult::failure( 'http_error', 'Unexpected status code: ' . $statusCode );
		}

		$body = (string) \wp_remote_retrieve_body( $response );
		$data = \json_decode( $body, true );

		if ( ! \is_array( $data ) ) {
			return UpdateCheckResult::failure( 'invalid_json', 'Unable to decode update-check response JSON.' );
		}

		$payload = UpdatePayload::fromApiResponse( $data );

		if ( $payload === null ) {
			return UpdateCheckResult::failure( 'invalid_payload', 'Update response missing required fields.' );
		}

		return UpdateCheckResult::update( $payload );
	}

	/**
	 * Validates a license key with the API.
	 *
	 * @param string $licenseKey The license key to validate.
	 *
	 * @return LicenseValidationResult
	 */
	public function validateLicense( string $licenseKey ): LicenseValidationResult {
		if ( ! $this->hasWordPressHttpApi() ) {
			return LicenseValidationResult::failure( 'transport_error', 'WordPress HTTP API is unavailable.' );
		}

		if ( $licenseKey === '' ) {
			return LicenseValidationResult::failure( 'invalid_payload', 'License key must not be empty.' );
		}

		$url = $this->config->normalizedApiBaseUrl() . '/licenses/validate';

		$encoded = \wp_json_encode(
			array(
				'slug'        => $this->config->slug,
				'license_key' => $licenseKey,
			)
		);

		if ( ! \is_string( $encoded ) ) {
			return LicenseValidationResult::failure( 'invalid_payload', 'Could not encode license validation request.' );
		}

		$args = array(
			'timeout' => $this->config->requestTimeout,
			'headers' => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			),
			'body'    => $encoded,
		);

		$args = $this->filterHttpArgs( $args, 'validate_license' );

		$response = \wp_remote_post( $url, $args );

		if ( \is_wp_error( $response ) ) {
			$this->logger->warning(
				'JCORE license validation transport error.',
				array(
					'slug'  => $this->config->slug,
					'error' => $response->get_error_message(),
				)
			);

			return LicenseValidationResult::failure( 'transport_error', $response->get_error_message() );
		}

		$statusCode = (int) \wp_remote_retrieve_response_code( $response );

		if ( $statusCode === 429 ) {
			return LicenseValidationResult::failure( 'rate_limited', 'License validation rate limit reached.' );
		}

		if ( $statusCode !== 200 ) {
			return LicenseValidationResult::failure( 'http_error', 'Unexpected status code: ' . $statusCode );
		}

		$body = (string) \wp_remote_retrieve_body( $response );
		$data = \json_decode( $body, true );

		if ( ! \is_array( $data ) ) {
			return LicenseValidationResult::failure( 'invalid_json', 'Unable to decode license response JSON.' );
		}

		if ( ! \array_key_exists( 'valid', $data ) || ! \is_bool( $data['valid'] ) ) {
			return LicenseValidationResult::failure( 'invalid_payload', 'License response missing boolean `valid` field.' );
		}

		return LicenseValidationResult::success( $data['valid'] );
	}

	/**
	 * Builds the update check URL.
	 *
	 * @param string      $installedVersion The installed version.
	 * @param string|null $licenseKey       The license key.
	 *
	 * @return string
	 */
	private function buildUpdateCheckUrl( string $installedVersion, ?string $licenseKey ): string {
		$baseUrl = $this->config->normalizedApiBaseUrl() . '/update-check';

		$query = array(
			'slug'    => $this->config->slug,
			'version' => $installedVersion,
		);

		if ( $licenseKey !== null && $licenseKey !== '' ) {
			$query['license_key'] = $licenseKey;
		}

		if ( \function_exists( 'add_query_arg' ) ) {
			return (string) \add_query_arg( $query, $baseUrl );
		}

		return $baseUrl . '?' . \http_build_query( $query );
	}

	/**
	 * Filters the HTTP arguments.
	 *
	 * @param array<string, mixed> $args    The arguments.
	 * @param string               $context The context.
	 *
	 * @return array<string, mixed>
	 */
	private function filterHttpArgs( array $args, string $context ): array {
		if ( $this->config->httpArgsFilter !== null ) {
			$custom = ( $this->config->httpArgsFilter )( $args, $context, $this->config );
			if ( \is_array( $custom ) ) {
				$args = $custom;
			}
		}

		if ( \function_exists( 'apply_filters' ) ) {
			$filtered = \apply_filters( 'jcore_update_http_args', $args, $context, $this->config );
			if ( \is_array( $filtered ) ) {
				$args = $filtered;
			}
		}

		return $args;
	}

	/**
	 * Checks if the WordPress HTTP API is available.
	 *
	 * @return bool
	 */
	private function hasWordPressHttpApi(): bool {
		return \function_exists( 'wp_remote_get' )
			&& \function_exists( 'wp_remote_post' )
			&& \function_exists( 'wp_remote_retrieve_body' )
			&& \function_exists( 'wp_remote_retrieve_response_code' )
			&& \function_exists( 'is_wp_error' );
	}
}
