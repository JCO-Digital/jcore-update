<?php
/**
 * Tests for UpdateConfig.
 *
 * @package Jcore\Update\Tests
 */

declare(strict_types=1);

namespace Jcore\Update\Tests;

use Jcore\Update\Config\UpdateConfig;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Class UpdateConfigTest
 */
class UpdateConfigTest extends TestCase {

	/**
	 * Test valid configuration.
	 */
	public function testValidConfig(): void {
		$config = new UpdateConfig(
			pluginFile: '/path/to/plugin.php',
			slug: 'my-plugin',
			version: '1.0.0',
			apiBaseUrl: 'https://api.example.com/'
		);

		$this->assertSame( '/path/to/plugin.php', $config->pluginFile );
		$this->assertSame( 'my-plugin', $config->slug );
		$this->assertSame( '1.0.0', $config->version );
		$this->assertSame( 'https://api.example.com', $config->normalizedApiBaseUrl() );
	}

	/**
	 * Test that empty plugin file throws exception.
	 */
	public function testEmptyPluginFileThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'pluginFile must not be empty.' );

		new UpdateConfig(
			pluginFile: '',
			slug: 'my-plugin',
			version: '1.0.0',
			apiBaseUrl: 'https://api.example.com/'
		);
	}

	/**
	 * Test that empty slug throws exception.
	 */
	public function testEmptySlugThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'slug must not be empty.' );

		new UpdateConfig(
			pluginFile: '/path/to/plugin.php',
			slug: '',
			version: '1.0.0',
			apiBaseUrl: 'https://api.example.com/'
		);
	}

	/**
	 * Test that empty version throws exception.
	 */
	public function testEmptyVersionThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'version must not be empty.' );

		new UpdateConfig(
			pluginFile: '/path/to/plugin.php',
			slug: 'my-plugin',
			version: '',
			apiBaseUrl: 'https://api.example.com/'
		);
	}

	/**
	 * Test that empty API base URL throws exception.
	 */
	public function testEmptyApiBaseUrlThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'apiBaseUrl must not be empty.' );

		new UpdateConfig(
			pluginFile: '/path/to/plugin.php',
			slug: 'my-plugin',
			version: '1.0.0',
			apiBaseUrl: ''
		);
	}

	/**
	 * Test that invalid request timeout throws exception.
	 */
	public function testInvalidRequestTimeoutThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'requestTimeout must be >= 1 second.' );

		new UpdateConfig(
			pluginFile: '/path/to/plugin.php',
			slug: 'my-plugin',
			version: '1.0.0',
			apiBaseUrl: 'https://api.example.com/',
			requestTimeout: 0
		);
	}

	/**
	 * Test that invalid update cache TTL throws exception.
	 */
	public function testInvalidUpdateCacheTtlThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'updateCacheTtl must be >= 0.' );

		new UpdateConfig(
			pluginFile: '/path/to/plugin.php',
			slug: 'my-plugin',
			version: '1.0.0',
			apiBaseUrl: 'https://api.example.com/',
			updateCacheTtl: -1
		);
	}
}
