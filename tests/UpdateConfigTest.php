<?php
/**
 * Tests for UpdateConfig.
 *
 * @package Jcore\Update\Tests
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

namespace Jcore\Update\Tests;

use Jcore\Update\Config\UpdateConfig;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Class UpdateConfigTest
 */
class UpdateConfigTest extends TestCase {

	/**
	 * Temp file path.
	 *
	 * @var string
	 */
	private string $tempFile;

	/**
	 * Sets up the test.
	 */
	protected function setUp(): void {
		$this->tempFile = tempnam( sys_get_temp_dir(), 'wp-plugin' );
	}

	/**
	 * Tears down the test.
	 */
	protected function tearDown(): void {
		if ( file_exists( $this->tempFile ) ) {
			unlink( $this->tempFile );
		}
	}

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
		$this->assertTrue( $config->filterMajorUpdates );
	}

	/**
	 * Test custom filterMajorUpdates configuration.
	 */
	public function testCustomFilterMajorUpdates(): void {
		$config = new UpdateConfig(
			pluginFile: '/path/to/plugin.php',
			slug: 'my-plugin',
			version: '1.0.0',
			apiBaseUrl: 'https://api.example.com/',
			filterMajorUpdates: false
		);

		$this->assertFalse( $config->filterMajorUpdates );
	}

	/**
	 * Test version auto-detection from pluginFile when version is null/omitted.
	 */
	public function testAutoDetectVersionFromPluginFile(): void {
		$content = <<<'PHP'
<?php
/**
 * Plugin Name: My Plugin
 * Version: 1.4.2
 */
PHP;
		file_put_contents( $this->tempFile, $content );

		$config = new UpdateConfig(
			pluginFile: $this->tempFile,
			slug: 'my-plugin',
			apiBaseUrl: 'https://api.example.com/'
		);

		$this->assertSame( '1.4.2', $config->version );
	}

	/**
	 * Test version auto-detection from pluginFile when version is explicitly empty string.
	 */
	public function testAutoDetectVersionWhenEmptyStringPassed(): void {
		$content = <<<'PHP'
<?php
/**
 * Plugin Name: My Plugin
 * Version: 1.4.2
 */
PHP;
		file_put_contents( $this->tempFile, $content );

		$config = new UpdateConfig(
			pluginFile: $this->tempFile,
			slug: 'my-plugin',
			version: '',
			apiBaseUrl: 'https://api.example.com/'
		);

		$this->assertSame( '1.4.2', $config->version );
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
	 * Test that empty version throws exception when version cannot be auto-detected.
	 */
	public function testEmptyVersionThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'version must not be empty.' );

		new UpdateConfig(
			pluginFile: '/path/to/nonexistent-plugin.php',
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
