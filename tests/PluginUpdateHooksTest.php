<?php
/**
 * Tests for PluginUpdateHooks.
 *
 * @package Jcore\Update\Tests
 */

declare(strict_types=1);

namespace Jcore\Update\Tests;

use Jcore\Update\Config\UpdateConfig;
use Jcore\Update\Hooks\PluginUpdateHooks;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Class PluginUpdateHooksTest
 */
class PluginUpdateHooksTest extends TestCase {

	/**
	 * The configuration.
	 *
	 * @var UpdateConfig
	 */
	private UpdateConfig $config;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		$this->config                      = new UpdateConfig(
			pluginFile: '/var/www/html/wp-content/plugins/my-plugin/my-plugin.php',
			slug: 'my-plugin',
			version: '1.0.0',
			apiBaseUrl: 'https://api.example.com'
		);
		$GLOBALS['wp_transients']          = array();
		$GLOBALS['wp_remote_get_response'] = null;
	}

	/**
	 * Test update check when cached as "no update".
	 */
	public function testCheckUpdateCachedNoUpdate(): void {
		$pluginBasename                        = 'my-plugin/my-plugin.php';
		$cacheKey                              = 'jcore_update_' . md5( 'my-plugin|1.0.0|none' );
		$GLOBALS['wp_transients'][ $cacheKey ] = array( 'state' => 'no_update' );

		$hooks = new PluginUpdateHooks( $this->config );

		$transient          = new stdClass();
		$transient->checked = array( $pluginBasename => '1.0.0' );

		$result = $hooks->checkUpdate( $transient );

		$this->assertObjectHasProperty( 'no_update', $result );
		$this->assertArrayHasKey( $pluginBasename, $result->no_update );
		$this->assertSame( '1.0.0', $result->no_update[ $pluginBasename ]->new_version );
	}

	/**
	 * Test update check when an update is available.
	 */
	public function testCheckUpdateWithUpdate(): void {
		$pluginBasename = 'my-plugin/my-plugin.php';

		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'new_version' => '1.1.0',
					'package'     => 'https://example.com/1.1.0.zip',
				)
			),
		);

		$hooks = new PluginUpdateHooks( $this->config );

		$transient          = new stdClass();
		$transient->checked = array( $pluginBasename => '1.0.0' );

		$result = $hooks->checkUpdate( $transient );

		$this->assertObjectHasProperty( 'response', $result );
		$this->assertArrayHasKey( $pluginBasename, $result->response );
		$this->assertSame( '1.1.0', $result->response[ $pluginBasename ]->new_version );
	}
}
