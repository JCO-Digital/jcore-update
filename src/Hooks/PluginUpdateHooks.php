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
use Jcore\Update\Support\SemVer;
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
	 * Whether the major update row has already been rendered on this request.
	 *
	 * @var bool
	 */
	private bool $majorRowRendered = false;

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

		if ( \function_exists( 'add_action' ) ) {
			$basename = $this->pluginBasename();
			\add_action( 'after_plugin_row_' . $basename, array( $this, 'renderAfterPluginRow' ), 10, 2 );
			\add_action( 'after_plugin_row', array( $this, 'renderAfterPluginRow' ), 10, 2 );
			\add_action( 'in_plugin_update_message-' . $basename, array( $this, 'renderInPluginUpdateMessage' ), 10, 2 );
			\add_action( 'admin_post_jcore_allow_major_update', array( $this, 'handleAllowMajorUpdate' ) );
			\add_action( 'admin_notices', array( $this, 'renderAdminNotice' ) );
		}

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

		if ( \function_exists( 'remove_action' ) ) {
			$basename = $this->pluginBasename();
			\remove_action( 'after_plugin_row_' . $basename, array( $this, 'renderAfterPluginRow' ), 10 );
			\remove_action( 'after_plugin_row', array( $this, 'renderAfterPluginRow' ), 10 );
			\remove_action( 'in_plugin_update_message-' . $basename, array( $this, 'renderInPluginUpdateMessage' ), 10 );
			\remove_action( 'admin_post_jcore_allow_major_update', array( $this, 'handleAllowMajorUpdate' ) );
			\remove_action( 'admin_notices', array( $this, 'renderAdminNotice' ) );
		}

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

		$installedVersion = \is_string( $transient->checked[ $pluginBasename ] )
			? $transient->checked[ $pluginBasename ]
			: $this->config->version;

		$licenseKey     = $this->resolveLicenseKey();
		$allowedMajor   = $this->getAllowedMajorVersion();
		$installedMajor = SemVer::getMajor( $installedVersion );

		$channel = $this->config->filterMajorUpdates ? 'all' : null;
		$result  = $this->client->checkForUpdate( $installedVersion, $licenseKey, $channel );

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

		$resolvedUpdate = null;

		if ( ! $this->config->filterMajorUpdates ) {
			$resolvedUpdate = $result->payload ?? $result->majorPayload;
			$this->setAvailableMajorUpdate( null );
		} else {
			$candidateMajor = $result->majorPayload;

			// If single payload was returned, check its version.
			if ( $candidateMajor === null && $result->payload !== null ) {
				if ( SemVer::getMajor( $result->payload->newVersion ) > $installedMajor ) {
					$candidateMajor = $result->payload;
				}
			}

			if ( $candidateMajor !== null ) {
				$candidateMajorNum = SemVer::getMajor( $candidateMajor->newVersion );
				if ( $candidateMajorNum > $allowedMajor ) {
					// Major update is higher than allowed: save notice and don't allow automatic update.
					$this->setAvailableMajorUpdate( $candidateMajor );
				} else {
					// Major update is within allowed major: allow update!
					$resolvedUpdate = $candidateMajor;
					$this->setAvailableMajorUpdate( null );
				}
			} else {
				$this->setAvailableMajorUpdate( null );
			}

			if ( $resolvedUpdate === null && $result->payload !== null ) {
				$payloadMajor = SemVer::getMajor( $result->payload->newVersion );
				if ( $payloadMajor <= $allowedMajor ) {
					$resolvedUpdate = $result->payload;
				}
			}
		}

		if ( $resolvedUpdate === null ) {
			return $this->markNoUpdate( $transient, $pluginBasename, $installedVersion );
		}

		$transient->response[ $pluginBasename ] = $this->toUpdateResponseObject( $resolvedUpdate, $pluginBasename );

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
		$channel      = $this->config->filterMajorUpdates ? 'all' : null;
		$updateResult = $this->client->checkForUpdate( $this->config->version, $licenseKey, $channel );

		if ( ! $updateResult->success ) {
			return $result;
		}

		$payload      = null;
		$allowedMajor = $this->getAllowedMajorVersion();

		if ( $this->config->filterMajorUpdates ) {
			if ( $updateResult->payload !== null && SemVer::getMajor( $updateResult->payload->newVersion ) <= $allowedMajor ) {
				$payload = $updateResult->payload;
			} elseif ( $updateResult->majorPayload !== null && SemVer::getMajor( $updateResult->majorPayload->newVersion ) <= $allowedMajor ) {
				$payload = $updateResult->majorPayload;
			} else {
				$payload = $updateResult->majorPayload ?? $updateResult->payload;
			}
		} else {
			$payload = $updateResult->payload ?? $updateResult->majorPayload;
		}

		if ( $payload === null ) {
			return $result;
		}

		$info = PluginInfoPayload::fromUpdatePayload( $this->config->slug, $payload );

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

		$cacheKey = 'jcore_lic_' . \substr( \md5( $this->config->slug . ':' . $trimmed ), 0, 16 );

		if ( ! $forceRefresh && \function_exists( 'get_site_transient' ) && $this->config->licenseValidationCacheTtl > 0 ) {
			$cached = \get_site_transient( $cacheKey );
			if ( \is_array( $cached ) && isset( $cached['valid'] ) && \is_bool( $cached['valid'] ) ) {
				return LicenseValidationResult::success( $cached['valid'], true );
			}
		}

		$result = $this->client->validateLicense( $trimmed );

		if ( $result->isSuccess() && \function_exists( 'set_site_transient' ) && $this->config->licenseValidationCacheTtl > 0 ) {
			\set_site_transient( $cacheKey, array( 'valid' => $result->valid ), $this->config->licenseValidationCacheTtl );
		}

		return $result;
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
	 * Gets the allowed major version option name.
	 *
	 * @return string
	 */
	public function getAllowedMajorOptionName(): string {
		$sanitized = \function_exists( 'sanitize_key' )
			? \sanitize_key( $this->config->slug )
			: (string) \preg_replace( '/[^a-z0-9_\-]/i', '', $this->config->slug );

		return 'jcore_update_allowed_major_' . $sanitized;
	}

	/**
	 * Gets the highest allowed major version.
	 *
	 * @return int
	 */
	public function getAllowedMajorVersion(): int {
		$installedMajor = SemVer::getMajor( $this->config->version );
		$storedMajor    = 0;

		if ( \function_exists( 'get_option' ) ) {
			$storedMajor = (int) \get_option( $this->getAllowedMajorOptionName(), 0 );
		}

		return \max( $installedMajor, $storedMajor );
	}

	/**
	 * Sets the allowed major version.
	 *
	 * @param int $major The target major version number.
	 *
	 * @return bool
	 */
	public function setAllowedMajorVersion( int $major ): bool {
		if ( \function_exists( 'update_option' ) ) {
			return (bool) \update_option( $this->getAllowedMajorOptionName(), $major );
		}

		return false;
	}

	/**
	 * Resets the allowed major version option.
	 *
	 * @return bool
	 */
	public function resetAllowedMajorVersion(): bool {
		if ( \function_exists( 'delete_option' ) ) {
			return (bool) \delete_option( $this->getAllowedMajorOptionName() );
		}

		return false;
	}

	/**
	 * Gets the currently available major update payload, if any.
	 *
	 * @return UpdatePayload|null
	 */
	public function getAvailableMajorUpdate(): ?UpdatePayload {
		if ( \function_exists( 'get_site_transient' ) ) {
			$cached = \get_site_transient( $this->getMajorUpdateTransientKey() );
			if ( \is_array( $cached ) ) {
				return UpdatePayload::fromApiResponse( $cached );
			}
		}

		// Fallback: If transient is not set, check on-demand when in admin.
		if ( $this->config->filterMajorUpdates && \function_exists( 'is_admin' ) && \is_admin() ) {
			$licenseKey   = $this->resolveLicenseKey();
			$updateResult = $this->client->checkForUpdate( $this->config->version, $licenseKey, 'all' );

			if ( ! $updateResult->success ) {
				$this->logger->debug(
					'JCORE on-demand major update check failed; no major update button will be shown.',
					array(
						'slug'      => $this->config->slug,
						'errorCode' => $updateResult->errorCode,
						'message'   => $updateResult->message,
					)
				);

				return null;
			}

			$candidate = $updateResult->majorPayload;
			if ( $candidate === null && $updateResult->payload !== null ) {
				if ( SemVer::getMajor( $updateResult->payload->newVersion ) > SemVer::getMajor( $this->config->version ) ) {
					$candidate = $updateResult->payload;
				}
			}

			if ( $candidate !== null && SemVer::getMajor( $candidate->newVersion ) > $this->getAllowedMajorVersion() ) {
				$this->setAvailableMajorUpdate( $candidate );
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Sets or clears the available major update transient.
	 *
	 * @param UpdatePayload|null $payload The payload or null to clear.
	 *
	 * @return void
	 */
	public function setAvailableMajorUpdate( ?UpdatePayload $payload ): void {
		if ( ! \function_exists( 'set_site_transient' ) || ! \function_exists( 'delete_site_transient' ) ) {
			return;
		}

		$key = $this->getMajorUpdateTransientKey();

		if ( $payload === null ) {
			\delete_site_transient( $key );
			return;
		}

		\set_site_transient( $key, $payload->toArray(), $this->config->updateCacheTtl );
	}

	/**
	 * Generates the URL for allowing a major version update.
	 *
	 * @param int $targetMajor Target major version.
	 *
	 * @return string
	 */
	public function getAllowMajorUpdateUrl( int $targetMajor ): string {
		$args = array(
			'action'       => 'jcore_allow_major_update',
			'slug'         => $this->config->slug,
			'target_major' => $targetMajor,
		);

		$url = \function_exists( 'admin_url' )
			? \admin_url( 'admin-post.php' )
			: 'admin-post.php';

		if ( \function_exists( 'add_query_arg' ) ) {
			$url = (string) \add_query_arg( $args, $url );
		} else {
			$url .= '?' . \http_build_query( $args );
		}

		if ( \function_exists( 'wp_nonce_url' ) ) {
			return (string) \wp_nonce_url( $url, 'jcore_allow_major_update_' . $this->config->slug );
		}

		return $url;
	}

	/**
	 * Handles the admin-post action to allow a major version update.
	 *
	 * @return void
	 */
	public function handleAllowMajorUpdate(): void {
		if ( ! isset( $_GET['slug'] ) || $_GET['slug'] !== $this->config->slug ) {
			return;
		}

		if ( \function_exists( 'current_user_can' ) && ! \current_user_can( 'update_plugins' ) && ! \current_user_can( 'manage_options' ) ) {
			if ( \function_exists( 'wp_die' ) && \function_exists( 'esc_html__' ) ) {
				\wp_die( \esc_html__( 'Sorry, you are not allowed to perform this action.', 'jcore-update' ), 403 );
			}
			return;
		}

		if ( \function_exists( 'check_admin_referer' ) ) {
			\check_admin_referer( 'jcore_allow_major_update_' . $this->config->slug );
		}

		$targetMajor = isset( $_GET['target_major'] ) ? (int) $_GET['target_major'] : 0;
		if ( $targetMajor > 0 ) {
			$this->setAllowedMajorVersion( $targetMajor );
		}

		$this->setAvailableMajorUpdate( null );

		if ( \function_exists( 'delete_site_transient' ) ) {
			\delete_site_transient( 'update_plugins' );
		}

		$redirectUrl = \function_exists( 'wp_get_referer' ) && \wp_get_referer()
			? (string) \wp_get_referer()
			: ( \function_exists( 'admin_url' ) ? \admin_url( 'plugins.php' ) : '' );

		if ( $redirectUrl !== '' && \function_exists( 'add_query_arg' ) && \function_exists( 'wp_safe_redirect' ) ) {
			$redirectUrl = \add_query_arg(
				array(
					'jcore_major_allowed' => $targetMajor,
					'jcore_slug'          => $this->config->slug,
				),
				$redirectUrl
			);
			\wp_safe_redirect( $redirectUrl );

			if ( ! \defined( 'PHPUNIT_COMPOSER_INSTALL' ) && ! \defined( 'DOING_TESTS' ) ) {
				exit;
			}
		}
	}

	/**
	 * Renders an admin notice when a major version has been unlocked.
	 *
	 * @return void
	 */
	public function renderAdminNotice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['jcore_major_allowed'] ) || ! isset( $_GET['jcore_slug'] ) ) {
			return;
		}

		if ( $_GET['jcore_slug'] !== $this->config->slug ) {
			return;
		}

		$major = (int) $_GET['jcore_major_allowed'];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( $major <= 0 ) {
			return;
		}

		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				if ( \function_exists( 'esc_html__' ) && \function_exists( 'esc_html' ) ) {
					\printf(
						/* translators: 1: Plugin slug or name, 2: Major version number */
						\esc_html__( 'Major version %2$d.x updates have been enabled for %1$s. You can now update the plugin as normal.', 'jcore-update' ),
						'<strong>' . \esc_html( $this->config->slug ) . '</strong>',
						(int) $major
					);
				}
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the major update notice row in the plugins table when no standard update row is present.
	 *
	 * @param string               $file       The plugin basename.
	 * @param array<string, mixed> $pluginData Plugin header data.
	 *
	 * @return void
	 */
	public function renderAfterPluginRow( string $file, array $pluginData = array() ): void {
		if ( ! $this->config->filterMajorUpdates || $this->majorRowRendered ) {
			return;
		}

		$pluginBasename = $this->pluginBasename();
		$matches        = ( $file === $pluginBasename )
			|| ( $file === \basename( $this->config->pluginFile ) )
			|| \str_ends_with( $pluginBasename, '/' . $file )
			|| \str_ends_with( $file, '/' . \basename( $this->config->pluginFile ) );

		if ( ! $matches ) {
			return;
		}

		$majorUpdate = $this->getAvailableMajorUpdate();
		if ( $majorUpdate === null ) {
			return;
		}

		$updatePlugins = \function_exists( 'get_site_transient' ) ? \get_site_transient( 'update_plugins' ) : null;
		if ( $updatePlugins instanceof stdClass && isset( $updatePlugins->response[ $file ] ) ) {
			// WordPress core will render the update row and trigger in_plugin_update_message.
			return;
		}

		$this->majorRowRendered = true;

		$targetMajor = SemVer::getMajor( $majorUpdate->newVersion );
		$allowUrl    = $this->getAllowMajorUpdateUrl( $targetMajor );
		$pluginName  = ! empty( $pluginData['Name'] ) ? (string) $pluginData['Name'] : $this->config->slug;

		$columns = 3;
		if ( isset( $GLOBALS['wp_list_table'] ) && \is_object( $GLOBALS['wp_list_table'] ) && \method_exists( $GLOBALS['wp_list_table'], 'get_column_count' ) ) {
			$columns = $GLOBALS['wp_list_table']->get_column_count();
		} elseif ( \function_exists( 'wp_is_auto_update_enabled_for_type' ) && \wp_is_auto_update_enabled_for_type( 'plugin' ) ) {
			$columns = 4;
		}

		?>
		<tr class="plugin-update-tr active" id="<?php echo \esc_attr( $this->config->slug . '-major-update' ); ?>" data-slug="<?php echo \esc_attr( $this->config->slug ); ?>" data-plugin="<?php echo \esc_attr( $file ); ?>">
			<td colspan="<?php echo (int) $columns; ?>" class="plugin-update colspanchange">
				<div class="update-message notice inline notice-warning notice-alt">
					<p>
						<?php
						if ( \function_exists( 'esc_html__' ) && \function_exists( 'esc_html' ) ) {
							\printf(
								/* translators: 1: Plugin name, 2: New major version number */
								\esc_html__( 'There is a new major version of %1$s available (%2$s). Major updates may contain breaking changes.', 'jcore-update' ),
								'<strong>' . \esc_html( $pluginName ) . '</strong>',
								\esc_html( $majorUpdate->newVersion )
							);
						}
						?>
						<a href="<?php echo \esc_url( $allowUrl ); ?>" class="button button-secondary" style="margin-left: 10px; vertical-align: middle;">
							<?php
							if ( \function_exists( 'esc_html__' ) ) {
								\printf(
									/* translators: %d: Target major version number */
									\esc_html__( 'Allow upgrade to v%d.x', 'jcore-update' ),
									(int) $targetMajor
								);
							}
							?>
						</a>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders the major update notice inside WordPress's standard plugin update row.
	 *
	 * @param array<string, mixed> $pluginData Plugin header data.
	 * @param mixed                $response   WordPress update response object.
	 *
	 * @return void
	 */
	public function renderInPluginUpdateMessage( array $pluginData, mixed $response ): void {
		if ( ! $this->config->filterMajorUpdates ) {
			return;
		}

		$majorUpdate = $this->getAvailableMajorUpdate();
		if ( $majorUpdate === null ) {
			return;
		}

		$currentUpdateVersion = ( $response instanceof stdClass && isset( $response->new_version ) && \is_string( $response->new_version ) )
			? $response->new_version
			: '';

		if ( $currentUpdateVersion !== '' && SemVer::getMajor( $currentUpdateVersion ) >= SemVer::getMajor( $majorUpdate->newVersion ) ) {
			return;
		}

		$targetMajor = SemVer::getMajor( $majorUpdate->newVersion );
		$allowUrl    = $this->getAllowMajorUpdateUrl( $targetMajor );

		?>
		<span class="jcore-major-update-notice" style="display: block; margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.1);">
			<?php
			if ( \function_exists( 'esc_html__' ) && \function_exists( 'esc_html' ) ) {
				\printf(
					/* translators: %s: New major version number */
					\esc_html__( 'A new major version (%s) is also available (may contain breaking changes).', 'jcore-update' ),
					\esc_html( $majorUpdate->newVersion )
				);
			}
			?>
			<a href="<?php echo \esc_url( $allowUrl ); ?>" class="button button-secondary button-small" style="margin-left: 8px; vertical-align: middle;">
				<?php
				if ( \function_exists( 'esc_html__' ) ) {
					\printf(
						/* translators: %d: Target major version number */
						\esc_html__( 'Allow upgrade to v%d.x', 'jcore-update' ),
						(int) $targetMajor
					);
				}
				?>
			</a>
		</span>
		<?php
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
	 * Gets the transient key for caching available major update data.
	 *
	 * @return string
	 */
	private function getMajorUpdateTransientKey(): string {
		return 'jcore_maj_' . \substr( \md5( $this->config->slug ), 0, 16 );
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
