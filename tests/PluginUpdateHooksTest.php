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
		$this->config                       = new UpdateConfig(
			pluginFile: '/var/www/html/wp-content/plugins/my-plugin/my-plugin.php',
			slug: 'my-plugin',
			version: '1.2.0',
			apiBaseUrl: 'https://api.example.com'
		);
		$GLOBALS['wp_transients']           = array();
		$GLOBALS['wp_options']              = array();
		$GLOBALS['wp_current_user_can']     = array( 'update_plugins' => true );
		$GLOBALS['wp_remote_get_response']  = null;
		$GLOBALS['wp_remote_post_response'] = null;
		$_GET                               = array();
	}

	/**
	 * Test update check when already present in the transient (native caching).
	 */
	public function testCheckUpdateCachedNoUpdate(): void {
		$pluginBasename = 'my-plugin/my-plugin.php';

		$hooks = new PluginUpdateHooks( $this->config );

		$transient          = new stdClass();
		$transient->checked = array( $pluginBasename => '1.2.0' );

		// Simulate WordPress already having our "no_update" entry.
		$entry                                   = new stdClass();
		$entry->slug                             = 'my-plugin';
		$entry->new_version                      = '1.2.0';
		$transient->no_update[ $pluginBasename ] = $entry;

		$result = $hooks->checkUpdate( $transient );

		// Should return immediately without hitting the API.
		$this->assertObjectHasProperty( 'no_update', $result );
		$this->assertSame( '1.2.0', $result->no_update[ $pluginBasename ]->new_version );
	}

	/**
	 * Test update check when a minor update is available.
	 */
	public function testCheckUpdateWithMinorUpdate(): void {
		$pluginBasename = 'my-plugin/my-plugin.php';

		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'patch'    => null,
					'minor'    => array(
						'new_version' => '1.3.0',
						'package'     => 'https://example.com/1.3.0.zip',
					),
					'major'    => null,
					'versions' => array( '1.3.0' ),
				)
			),
		);

		$hooks = new PluginUpdateHooks( $this->config );

		$transient          = new stdClass();
		$transient->checked = array( $pluginBasename => '1.2.0' );

		$result = $hooks->checkUpdate( $transient );

		$this->assertObjectHasProperty( 'response', $result );
		$this->assertArrayHasKey( $pluginBasename, $result->response );
		$this->assertSame( '1.3.0', $result->response[ $pluginBasename ]->new_version );
		$this->assertNull( $hooks->getAvailableMajorUpdate() );
	}

	/**
	 * Test update check filters major update by default.
	 */
	public function testCheckUpdateFiltersMajorUpdateByDefault(): void {
		$pluginBasename = 'my-plugin/my-plugin.php';

		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'patch'    => null,
					'minor'    => null,
					'major'    => array(
						'new_version' => '2.0.3',
						'package'     => 'https://example.com/2.0.3.zip',
					),
					'versions' => array( '2.0.0', '2.0.3' ),
				)
			),
		);

		$hooks = new PluginUpdateHooks( $this->config );

		$transient          = new stdClass();
		$transient->checked = array( $pluginBasename => '1.2.0' );

		$result = $hooks->checkUpdate( $transient );

		// 2.0.3 must NOT be in response!
		$this->assertArrayNotHasKey( $pluginBasename, $result->response ?? array() );
		$this->assertObjectHasProperty( 'no_update', $result );
		$this->assertArrayHasKey( $pluginBasename, $result->no_update );

		// Major update info must be stored for notice display.
		$majorUpdate = $hooks->getAvailableMajorUpdate();
		$this->assertNotNull( $majorUpdate );
		$this->assertSame( '2.0.3', $majorUpdate->newVersion );
	}

	/**
	 * Test rendering the major update row with override button.
	 */
	public function testRenderAfterPluginRowOutputsMajorUpdateNotice(): void {
		$pluginBasename = 'my-plugin/my-plugin.php';

		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'patch'    => null,
					'minor'    => null,
					'major'    => array(
						'new_version' => '2.0.3',
						'package'     => 'https://example.com/2.0.3.zip',
					),
					'versions' => array( '2.0.3' ),
				)
			),
		);

		$hooks = new PluginUpdateHooks( $this->config );

		$transient          = new stdClass();
		$transient->checked = array( $pluginBasename => '1.2.0' );
		$hooks->checkUpdate( $transient );

		\ob_start();
		$hooks->renderAfterPluginRow( $pluginBasename, array( 'Name' => 'My Plugin' ) );
		$output = \ob_get_clean();

		$this->assertStringContainsString( 'plugin-update-tr', $output );
		$this->assertStringContainsString( '2.0.3', $output );
		$this->assertStringContainsString( 'Allow upgrade to v2.x', $output );
		$this->assertStringContainsString( 'action=jcore_allow_major_update', $output );
		$this->assertStringContainsString( 'target_major=2', $output );
	}

	/**
	 * Test rendering inside update row when minor and major updates both exist.
	 */
	public function testRenderInPluginUpdateMessageOutputsNotice(): void {
		$pluginBasename = 'my-plugin/my-plugin.php';

		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'minor'    => array(
						'new_version' => '1.3.0',
						'package'     => 'https://example.com/1.3.0.zip',
					),
					'major'    => array(
						'new_version' => '2.0.3',
						'package'     => 'https://example.com/2.0.3.zip',
					),
					'versions' => array( '1.3.0', '2.0.3' ),
				)
			),
		);

		$hooks = new PluginUpdateHooks( $this->config );

		$transient          = new stdClass();
		$transient->checked = array( $pluginBasename => '1.2.0' );
		$hooks->checkUpdate( $transient );

		$response              = new stdClass();
		$response->new_version = '1.3.0';

		\ob_start();
		$hooks->renderInPluginUpdateMessage( array( 'Name' => 'My Plugin' ), $response );
		$output = \ob_get_clean();

		$this->assertStringContainsString( 'jcore-major-update-notice', $output );
		$this->assertStringContainsString( '2.0.3', $output );
		$this->assertStringContainsString( 'Allow upgrade to v2.x', $output );
	}

	/**
	 * Test that allowing major update unlocks the target major version.
	 */
	public function testAllowMajorUpdateEnablesNextMajorTrack(): void {
		$pluginBasename = 'my-plugin/my-plugin.php';

		$hooks = new PluginUpdateHooks( $this->config );

		// Simulate user clicking allow button.
		$_GET = array(
			'action'       => 'jcore_allow_major_update',
			'slug'         => 'my-plugin',
			'target_major' => '2',
			'_wpnonce'     => 'mock_nonce_jcore_allow_major_update_my-plugin',
		);

		$hooks->handleAllowMajorUpdate();

		$this->assertSame( 2, $hooks->getAllowedMajorVersion() );

		// Now check updates again with 2.0.3 available.
		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'patch'    => null,
					'minor'    => null,
					'major'    => array(
						'new_version' => '2.0.3',
						'package'     => 'https://example.com/2.0.3.zip',
					),
					'versions' => array( '2.0.3' ),
				)
			),
		);

		$transient          = new stdClass();
		$transient->checked = array( $pluginBasename => '1.2.0' );

		$result = $hooks->checkUpdate( $transient );

		// 2.0.3 is now allowed and present in the response!
		$this->assertObjectHasProperty( 'response', $result );
		$this->assertArrayHasKey( $pluginBasename, $result->response );
		$this->assertSame( '2.0.3', $result->response[ $pluginBasename ]->new_version );
	}

	/**
	 * Test that one-time version bump to 2.x does NOT allow 3.x updates.
	 */
	public function testOneTimeVersionBumpDoesNotAllowHigherMajor(): void {
		$pluginBasename = 'my-plugin/my-plugin.php';

		$hooks = new PluginUpdateHooks( $this->config );

		// Explicitly allow major 2.
		$hooks->setAllowedMajorVersion( 2 );

		// API returns major 3.0.0.
		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'patch'    => null,
					'minor'    => null,
					'major'    => array(
						'new_version' => '3.0.0',
						'package'     => 'https://example.com/3.0.0.zip',
					),
					'versions' => array( '3.0.0' ),
				)
			),
		);

		$transient          = new stdClass();
		$transient->checked = array( $pluginBasename => '1.2.0' );

		$result = $hooks->checkUpdate( $transient );

		// 3.0.0 must NOT be in response!
		$this->assertArrayNotHasKey( $pluginBasename, $result->response ?? array() );
		$this->assertObjectHasProperty( 'no_update', $result );

		// 3.0.0 is saved as available major update.
		$majorUpdate = $hooks->getAvailableMajorUpdate();
		$this->assertNotNull( $majorUpdate );
		$this->assertSame( '3.0.0', $majorUpdate->newVersion );
	}

	/**
	 * Test disabling major update filtering allows all updates.
	 */
	public function testDisabledMajorUpdateFilteringAllowsDirectUpdate(): void {
		$pluginBasename = 'my-plugin/my-plugin.php';

		$config = new UpdateConfig(
			pluginFile: '/var/www/html/wp-content/plugins/my-plugin/my-plugin.php',
			slug: 'my-plugin',
			version: '1.2.0',
			apiBaseUrl: 'https://api.example.com',
			filterMajorUpdates: false
		);

		$hooks = new PluginUpdateHooks( $config );

		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'new_version' => '2.0.3',
					'package'     => 'https://example.com/2.0.3.zip',
				)
			),
		);

		$transient          = new stdClass();
		$transient->checked = array( $pluginBasename => '1.2.0' );

		$result = $hooks->checkUpdate( $transient );

		$this->assertObjectHasProperty( 'response', $result );
		$this->assertArrayHasKey( $pluginBasename, $result->response );
		$this->assertSame( '2.0.3', $result->response[ $pluginBasename ]->new_version );
		$this->assertNull( $hooks->getAvailableMajorUpdate() );
	}

	/**
	 * Test license validation caching and force refresh.
	 */
	public function testValidateLicenseCachingAndForceRefresh(): void {
		$GLOBALS['wp_remote_post_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode( array( 'valid' => true ) ),
		);

		$hooks = new PluginUpdateHooks( $this->config );

		// Initial check hits the API.
		$result1 = $hooks->validateLicense( 'test-key' );
		$this->assertTrue( $result1->valid );
		$this->assertFalse( $result1->fromCache );

		// Second check returns from cache.
		$GLOBALS['wp_remote_post_response'] = null; // Ensure remote call would fail if made.
		$result2                            = $hooks->validateLicense( 'test-key', false );
		$this->assertTrue( $result2->valid );
		$this->assertTrue( $result2->fromCache );

		// Force refresh bypasses cache.
		$GLOBALS['wp_remote_post_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode( array( 'valid' => false ) ),
		);
		$result3                            = $hooks->validateLicense( 'test-key', true );
		$this->assertFalse( $result3->valid );
		$this->assertFalse( $result3->fromCache );
	}

	/**
	 * Test rendering major update row on-demand when transient was not pre-populated.
	 */
	public function testRenderAfterPluginRowOnDemandResolution(): void {
		$pluginBasename = 'my-plugin/my-plugin.php';

		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'patch'    => null,
					'minor'    => null,
					'major'    => array(
						'new_version' => '2.0.3',
						'package'     => 'https://example.com/2.0.3.zip',
					),
					'versions' => array( '2.0.3' ),
				)
			),
		);

		$hooks = new PluginUpdateHooks( $this->config );

		// Notice: checkUpdate was NOT called beforehand; transient is empty.
		$this->assertNull( $GLOBALS['wp_transients'][ 'jcore_maj_' . substr( md5( 'my-plugin' ), 0, 16 ) ] ?? null );

		\ob_start();
		$hooks->renderAfterPluginRow( $pluginBasename, array( 'Name' => 'My Plugin' ) );
		$output = \ob_get_clean();

		$this->assertStringContainsString( 'plugin-update-tr', $output );
		$this->assertStringContainsString( '2.0.3', $output );
		$this->assertStringContainsString( 'Allow upgrade to v2.x', $output );
	}

	/**
	 * Test register and unregister.
	 */
	public function testRegisterAndUnregister(): void {
		$hooks = new PluginUpdateHooks( $this->config );
		$hooks->register();
		$hooks->register(); // Idempotent.
		$hooks->unregister();
		$hooks->unregister(); // Idempotent.
		$this->assertTrue( true );
	}
}
