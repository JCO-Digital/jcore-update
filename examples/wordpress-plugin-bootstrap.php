<?php
/**
 * Example plugin bootstrap for jcore-update.
 *
 * Copy patterns from this file into your own plugin bootstrap/settings code.
 *
 * @package Jcore\Update\Examples
 */

declare(strict_types=1);

use Jcore\Update\Config\UpdateConfig;
use Jcore\Update\Hooks\PluginUpdateHooks;
use Jcore\Update\Support\PluginHelper;
use Jcore\Update\Support\WordPressLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Adjust these for your plugin.
const MY_PLUGIN_SLUG           = 'my-plugin-slug';
const MY_PLUGIN_LICENSE_OPTION = 'my_plugin_license_key';

// If your plugin bundles composer dependencies.
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoloadPath ) ) {
	require_once $autoloadPath;
}

/**
 * Gets the updater instance.
 *
 * @return PluginUpdateHooks|null
 */
function my_plugin_get_updater(): ?PluginUpdateHooks {
	return $GLOBALS['my_plugin_updater'] ?? null;
}

/**
 * Bootstraps the updates.
 *
 * @return void
 */
function my_plugin_bootstrap_updates(): void {
	$storedLicense = get_option( MY_PLUGIN_LICENSE_OPTION );
	$licenseKey    = is_string( $storedLicense ) && $storedLicense !== '' ? $storedLicense : null;

	$config = new UpdateConfig(
		pluginFile: __FILE__,
		slug: MY_PLUGIN_SLUG,
		version: PluginHelper::getVersion( __FILE__ ),
		apiBaseUrl: 'https://api.example.com/v1',
		licenseKey: $licenseKey,
		logger: new WordPressLogger( '[my-plugin-updater]' ),
	);

	$updater = new PluginUpdateHooks( $config );
	$updater->register();

	$GLOBALS['my_plugin_updater'] = $updater;
}
add_action( 'plugins_loaded', 'my_plugin_bootstrap_updates' );

/**
 * Admin UI belongs to the host plugin (not jcore-update).
 */
function my_plugin_register_license_settings_page(): void {
	add_options_page(
		'My Plugin License',
		'My Plugin License',
		'manage_options',
		'my-plugin-license',
		'my_plugin_render_license_settings_page'
	);
}
add_action( 'admin_menu', 'my_plugin_register_license_settings_page' );

/**
 * Registers the license setting.
 *
 * @return void
 */
function my_plugin_register_license_setting(): void {
	register_setting(
		'my_plugin_license',
		MY_PLUGIN_LICENSE_OPTION,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'my_plugin_sanitize_license_key',
			'default'           => '',
		)
	);
}
add_action( 'admin_init', 'my_plugin_register_license_setting' );

/**
 * Sanitizes the license key and validates it.
 *
 * @param mixed $raw The raw input.
 *
 * @return string
 */
function my_plugin_sanitize_license_key( mixed $raw ): string {
	$licenseKey = trim( (string) $raw );

	if ( $licenseKey === '' ) {
		add_settings_error( 'my_plugin_license', 'empty_license', 'License key removed.', 'updated' );
		return '';
	}

	$updater = my_plugin_get_updater();
	if ( ! $updater instanceof PluginUpdateHooks ) {
		add_settings_error( 'my_plugin_license', 'updater_missing', 'Updater is not available yet. Please try again.', 'error' );
		return $licenseKey;
	}

	// Force a live check when user saves, instead of relying on cache.
	$result = $updater->validateLicense( $licenseKey, true );

	if ( ! $result->isSuccess() ) {
		add_settings_error(
			'my_plugin_license',
			'license_check_failed',
			'Could not validate license right now (' . ( $result->errorCode ?? 'unknown_error' ) . '). Saved anyway.',
			'warning'
		);

		return $licenseKey;
	}

	if ( ! $result->valid ) {
		add_settings_error( 'my_plugin_license', 'license_invalid', 'License key is invalid.', 'error' );

		// Option A: keep saved invalid value for user editing UX (chosen here).
		// Option B: return '' to avoid persisting invalid keys.
		return $licenseKey;
	}

	add_settings_error( 'my_plugin_license', 'license_valid', 'License key is valid.', 'updated' );
	return $licenseKey;
}

/**
 * Renders the license settings page.
 *
 * @return void
 */
function my_plugin_render_license_settings_page(): void {
	?>
	<div class="wrap">
		<h1>My Plugin License</h1>
		<?php settings_errors( 'my_plugin_license' ); ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'my_plugin_license' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( MY_PLUGIN_LICENSE_OPTION ); ?>">License Key</label></th>
					<td>
						<input
							name="<?php echo esc_attr( MY_PLUGIN_LICENSE_OPTION ); ?>"
							id="<?php echo esc_attr( MY_PLUGIN_LICENSE_OPTION ); ?>"
							type="text"
							class="regular-text"
							value="<?php echo esc_attr( (string) get_option( MY_PLUGIN_LICENSE_OPTION, '' ) ); ?>"
						/>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Save License Key' ); ?>
		</form>
	</div>
	<?php
}
