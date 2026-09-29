<?php
/**
 * Tests for Readme.
 *
 * @package Jcore\Update\Tests
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

namespace Jcore\Update\Tests;

use Jcore\Update\Support\Readme;
use PHPUnit\Framework\TestCase;

/**
 * Class ReadmeTest
 */
class ReadmeTest extends TestCase {

	/**
	 * Temp readme path.
	 *
	 * @var string
	 */
	private string $tempFile;

	/**
	 * Sets up the test.
	 */
	protected function setUp(): void {
		$this->tempFile = tempnam( sys_get_temp_dir(), 'readme' );
		file_put_contents( $this->tempFile, self::sampleReadme() );
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
	 * A readme in the WordPress format.
	 *
	 * @return string
	 */
	public static function sampleReadme(): string {
		return <<<'TXT'
=== My Plugin ===
Contributors: jcodigital
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.2.0

Short description <with> markup.

== Description ==

First paragraph spanning
two lines. See https://example.com for more.

= Features =

* **Bold** feature with `code`
* Link to [docs](https://example.com/docs)

== Installation ==

1. Upload the folder.
2. Activate the plugin.

== Frequently Asked Questions ==

= Is it good? =

Yes.

== Unknown Section ==

Ignored.

== Changelog ==

= 1.2.0 (2026-09-24) =

* Feature: something new

= v1.1.0 =

* Fix: something old
TXT;
	}

	/**
	 * Tests header lookup.
	 */
	public function testHeader(): void {
		$readme = new Readme( $this->tempFile );

		$this->assertTrue( $readme->exists() );
		$this->assertSame( '7.1', $readme->header( 'Tested up to' ) );
		$this->assertSame( '6.7', $readme->header( 'requires at least' ) );
		$this->assertNull( $readme->header( 'Donate link' ) );
	}

	/**
	 * Tests a missing readme.
	 */
	public function testMissingFile(): void {
		$readme = new Readme( '/non/existent/readme.txt' );

		$this->assertFalse( $readme->exists() );
		$this->assertNull( $readme->header( 'Tested up to' ) );
		$this->assertSame( array(), $readme->sections() );
	}

	/**
	 * Tests section parsing and key mapping.
	 */
	public function testSections(): void {
		$sections = ( new Readme( $this->tempFile ) )->sections();

		$this->assertSame( array( 'description', 'installation', 'faq', 'changelog' ), array_keys( $sections ) );

		$this->assertSame(
			'<p>First paragraph spanning two lines. See <a href="https://example.com">https://example.com</a> for more.</p>'
			. '<h4>Features</h4>'
			. '<ul><li><strong>Bold</strong> feature with <code>code</code></li>'
			. '<li>Link to <a href="https://example.com/docs">docs</a></li></ul>',
			$sections['description']
		);

		$this->assertSame(
			'<ol><li>Upload the folder.</li><li>Activate the plugin.</li></ol>',
			$sections['installation']
		);

		$this->assertSame( '<h4>Is it good?</h4><p>Yes.</p>', $sections['faq'] );

		$this->assertSame(
			'<h4>1.2.0 (2026-09-24)</h4><ul><li>Feature: something new</li></ul>'
			. '<h4>v1.1.0</h4><ul><li>Fix: something old</li></ul>',
			$sections['changelog']
		);
	}

	/**
	 * Tests that HTML in the readme is escaped.
	 */
	public function testToHtmlEscapes(): void {
		$this->assertSame( '<p>a &lt;b&gt; &amp; c</p>', Readme::toHtml( 'a <b> & c' ) );
	}

	/**
	 * Tests that a remote entry for a new version is prepended.
	 */
	public function testMergeChangelogPrependsUnknownVersion(): void {
		$local  = '<h4>1.2.0</h4><ul><li>old</li></ul>';
		$remote = "= 1.3.0 (2026-10-01) =\n\n* new";

		$this->assertSame(
			'<h4>1.3.0 (2026-10-01)</h4><ul><li>new</li></ul>' . $local,
			Readme::mergeChangelog( $remote, $local )
		);
	}

	/**
	 * Tests that a remote entry already in the local changelog is dropped.
	 */
	public function testMergeChangelogSkipsKnownVersion(): void {
		$local = '<h4>1.2.0 (2026-09-24)</h4><ul><li>old</li></ul>';

		$this->assertSame( $local, Readme::mergeChangelog( "= v1.2.0 =\n* old", $local ) );
		// 1.2.0 must not match 1.2.01 or similar.
		$this->assertStringStartsWith( '<h4>1.2.01</h4>', Readme::mergeChangelog( '= 1.2.01 =', $local ) );
	}

	/**
	 * Tests edge cases of the merge.
	 */
	public function testMergeChangelogEdges(): void {
		$this->assertSame( '', Readme::mergeChangelog( '', '' ) );
		$this->assertSame( '<p>x</p>', Readme::mergeChangelog( '', '<p>x</p>' ) );
		$this->assertSame( '<h4>2.0.0</h4>', Readme::mergeChangelog( '= 2.0.0 =', '' ) );
		$this->assertSame( '<h4>2.0.0</h4><p>html</p>', Readme::mergeChangelog( '<h4>2.0.0</h4><p>html</p>', '' ) );
	}
}
