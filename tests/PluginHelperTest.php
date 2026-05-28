<?php
/**
 * Tests for PluginHelper.
 *
 * @package Jcore\Update\Tests
 */

declare(strict_types=1);

namespace Jcore\Update\Tests;

use Jcore\Update\Support\PluginHelper;
use PHPUnit\Framework\TestCase;

/**
 * Class PluginHelperTest
 */
class PluginHelperTest extends TestCase {

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
	 * Tests getVersion with regex fallback.
	 */
	public function testGetVersionFallback(): void {
		$content = <<<'PHP'
<?php
/**
 * Plugin Name: Test Plugin
 * Version: 1.2.3
 */
PHP;
		file_put_contents( $this->tempFile, $content );

		// Ensure get_file_data is NOT defined for this test to trigger fallback.
		$this->assertEquals( '1.2.3', PluginHelper::getVersion( $this->tempFile ) );
	}

	/**
	 * Tests getVersion with missing file.
	 */
	public function testGetVersionMissingFile(): void {
		$this->assertEquals( '', PluginHelper::getVersion( '/non/existent/file.php' ) );
	}

	/**
	 * Tests getVersion with no version in header.
	 */
	public function testGetVersionNoVersion(): void {
		$content = <<<'PHP'
<?php
/**
 * Plugin Name: Test Plugin
 */
PHP;
		file_put_contents( $this->tempFile, $content );

		$this->assertEquals( '', PluginHelper::getVersion( $this->tempFile ) );
	}
}
