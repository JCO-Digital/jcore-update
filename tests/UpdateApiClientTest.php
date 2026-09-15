<?php
/**
 * Tests for UpdateApiClient.
 *
 * @package Jcore\Update\Tests
 */

declare(strict_types=1);

namespace Jcore\Update\Tests;

use Jcore\Update\Client\UpdateApiClient;
use Jcore\Update\Config\UpdateConfig;
use PHPUnit\Framework\TestCase;

/**
 * Class UpdateApiClientTest
 */
class UpdateApiClientTest extends TestCase {

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
			version: '1.0.0',
			apiBaseUrl: 'https://api.example.com'
		);
		$GLOBALS['wp_remote_get_response']  = null;
		$GLOBALS['wp_remote_post_response'] = null;
	}

	/**
	 * Test successful update check for minor update.
	 */
	public function testCheckForUpdateSuccess(): void {
		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'new_version' => '1.1.0',
					'package'     => 'https://example.com/1.1.0.zip',
				)
			),
		);

		$client = new UpdateApiClient( $this->config );
		$result = $client->checkForUpdate( '1.0.0' );

		$this->assertTrue( $result->success );
		$this->assertFalse( $result->noUpdate );
		$this->assertNotNull( $result->payload );
		$this->assertSame( '1.1.0', $result->payload->newVersion );
		$this->assertNull( $result->majorPayload );
	}

	/**
	 * Test single update response with major version bump.
	 */
	public function testCheckForUpdateSingleMajorBump(): void {
		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'new_version' => '2.0.3',
					'package'     => 'https://example.com/2.0.3.zip',
				)
			),
		);

		$client = new UpdateApiClient( $this->config );
		$result = $client->checkForUpdate( '1.2.0' );

		$this->assertTrue( $result->success );
		$this->assertFalse( $result->noUpdate );
		$this->assertNotNull( $result->payload );
		$this->assertSame( '2.0.3', $result->payload->newVersion );
		$this->assertNotNull( $result->majorPayload );
		$this->assertSame( '2.0.3', $result->majorPayload->newVersion );
	}

	/**
	 * Test grouped channel payload with both minor and major updates.
	 */
	public function testCheckForUpdateGroupedPayload(): void {
		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'patch'    => null,
					'minor'    => array(
						'new_version' => '1.3.0',
						'package'     => 'https://example.com/1.3.0.zip',
					),
					'major'    => array(
						'new_version' => '2.0.3',
						'package'     => 'https://example.com/2.0.3.zip',
					),
					'versions' => array( '1.2.1', '1.3.0', '2.0.0', '2.0.3' ),
				)
			),
		);

		$client = new UpdateApiClient( $this->config );
		$result = $client->checkForUpdate( '1.2.0', null, 'all' );

		$this->assertTrue( $result->success );
		$this->assertFalse( $result->noUpdate );
		$this->assertNotNull( $result->payload );
		$this->assertSame( '1.3.0', $result->payload->newVersion );
		$this->assertNotNull( $result->majorPayload );
		$this->assertSame( '2.0.3', $result->majorPayload->newVersion );
		$this->assertCount( 4, $result->versions );
	}

	/**
	 * Test grouped channel payload with only major update available.
	 */
	public function testCheckForUpdateGroupedMajorOnly(): void {
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

		$client = new UpdateApiClient( $this->config );
		$result = $client->checkForUpdate( '1.2.0', null, 'all' );

		$this->assertTrue( $result->success );
		$this->assertTrue( $result->noUpdate );
		$this->assertNull( $result->payload );
		$this->assertNotNull( $result->majorPayload );
		$this->assertSame( '2.0.3', $result->majorPayload->newVersion );
	}

	/**
	 * Test update check when no update is available.
	 */
	public function testCheckForUpdateNoUpdate(): void {
		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 204 ),
			'body'     => '',
		);

		$client = new UpdateApiClient( $this->config );
		$result = $client->checkForUpdate( '1.0.0' );

		$this->assertTrue( $result->success );
		$this->assertTrue( $result->noUpdate );
		$this->assertNull( $result->payload );
		$this->assertNull( $result->majorPayload );
	}

	/**
	 * Test successful license validation.
	 */
	public function testValidateLicenseSuccess(): void {
		$GLOBALS['wp_remote_post_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode( array( 'valid' => true ) ),
		);

		$client = new UpdateApiClient( $this->config );
		$result = $client->validateLicense( 'valid-key' );

		$this->assertTrue( $result->success );
		$this->assertTrue( $result->valid );
	}

	/**
	 * Test failed license validation.
	 */
	public function testValidateLicenseInvalid(): void {
		$GLOBALS['wp_remote_post_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode( array( 'valid' => false ) ),
		);

		$client = new UpdateApiClient( $this->config );
		$result = $client->validateLicense( 'invalid-key' );

		$this->assertTrue( $result->success );
		$this->assertFalse( $result->valid );
	}
}
