<?php

declare(strict_types=1);

namespace Jcore\Update\Tests;

use Jcore\Update\Client\UpdateApiClient;
use Jcore\Update\Config\UpdateConfig;
use PHPUnit\Framework\TestCase;

class UpdateApiClientTest extends TestCase {

	private UpdateConfig $config;

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

	public function testCheckForUpdateSuccess(): void {
		$GLOBALS['wp_remote_get_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode(
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
		$this->assertSame( '1.1.0', $result->payload->newVersion );
	}

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
	}

	public function testValidateLicenseSuccess(): void {
		$GLOBALS['wp_remote_post_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( array( 'valid' => true ) ),
		);

		$client = new UpdateApiClient( $this->config );
		$result = $client->validateLicense( 'valid-key' );

		$this->assertTrue( $result->success );
		$this->assertTrue( $result->valid );
	}

	public function testValidateLicenseInvalid(): void {
		$GLOBALS['wp_remote_post_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( array( 'valid' => false ) ),
		);

		$client = new UpdateApiClient( $this->config );
		$result = $client->validateLicense( 'invalid-key' );

		$this->assertTrue( $result->success );
		$this->assertFalse( $result->valid );
	}
}
