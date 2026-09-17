<?php
/**
 * Tests for PluginHelper.
 *
 * @package Jcore\Update\Tests
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_rmdir

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
	 * Temp directory path.
	 *
	 * @var string
	 */
	private string $tempDir;

	/**
	 * Sets up the test.
	 */
	protected function setUp(): void {
		$this->tempFile = tempnam( sys_get_temp_dir(), 'wp-plugin' );
		$this->tempDir  = sys_get_temp_dir() . '/wp-plugin-test-' . uniqid();
		mkdir( $this->tempDir, 0777, true );
	}

	/**
	 * Tears down the test.
	 */
	protected function tearDown(): void {
		if ( file_exists( $this->tempFile ) ) {
			unlink( $this->tempFile );
		}

		$this->removeDirectory( $this->tempDir );
	}

	/**
	 * Recursively removes a directory.
	 *
	 * @param string $dir Directory path.
	 */
	private function removeDirectory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$scanned = scandir( $dir );
		$files   = array_diff( is_array( $scanned ) ? $scanned : array(), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				$this->removeDirectory( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
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

	/**
	 * Tests getVersion finds version from main plugin file when called from a nested subfile.
	 */
	public function testGetVersionFromNestedSubfile(): void {
		$pluginDir = $this->tempDir . '/my-awesome-plugin';
		$srcDir    = $pluginDir . '/src/Subfolder';
		mkdir( $srcDir, 0777, true );

		$mainPluginFile = $pluginDir . '/my-awesome-plugin.php';
		$mainContent    = <<<'PHP'
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Version: 2.5.1
 */
PHP;
		file_put_contents( $mainPluginFile, $mainContent );

		$subFile    = $srcDir . '/Bootstrap.php';
		$subContent = <<<'PHP'
<?php
namespace MyAwesomePlugin;
class Bootstrap {}
PHP;
		file_put_contents( $subFile, $subContent );

		$this->assertSame( '2.5.1', PluginHelper::getVersion( $subFile ) );
	}

	/**
	 * Tests getVersion when passed a directory path.
	 */
	public function testGetVersionFromDirectory(): void {
		$pluginDir = $this->tempDir . '/my-plugin';
		mkdir( $pluginDir, 0777, true );

		$mainPluginFile = $pluginDir . '/my-plugin.php';
		$mainContent    = <<<'PHP'
<?php
/**
 * Plugin Name: My Plugin
 * Version: 3.1.0
 */
PHP;
		file_put_contents( $mainPluginFile, $mainContent );

		$this->assertSame( '3.1.0', PluginHelper::getVersion( $pluginDir ) );
	}
}
