<?php
/**
 * Tests for SemVer helper.
 *
 * @package Jcore\Update\Tests
 */

declare(strict_types=1);

namespace Jcore\Update\Tests;

use Jcore\Update\Support\SemVer;
use PHPUnit\Framework\TestCase;

/**
 * Class SemVerTest
 */
class SemVerTest extends TestCase {

	/**
	 * Tests clean method.
	 */
	public function testClean(): void {
		$this->assertSame( '1.2.3', SemVer::clean( '1.2.3' ) );
		$this->assertSame( '1.2.3', SemVer::clean( 'v1.2.3' ) );
		$this->assertSame( '1.2.3', SemVer::clean( 'V1.2.3' ) );
		$this->assertSame( '2.0.0-beta.1', SemVer::clean( ' v2.0.0-beta.1 ' ) );
	}

	/**
	 * Tests getMajor method.
	 */
	public function testGetMajor(): void {
		$this->assertSame( 1, SemVer::getMajor( '1.2.3' ) );
		$this->assertSame( 2, SemVer::getMajor( 'v2.0.0' ) );
		$this->assertSame( 10, SemVer::getMajor( '10.5.1' ) );
		$this->assertSame( 0, SemVer::getMajor( '0.9.4' ) );
		$this->assertSame( 0, SemVer::getMajor( 'invalid' ) );
	}

	/**
	 * Tests getMinor method.
	 */
	public function testGetMinor(): void {
		$this->assertSame( 2, SemVer::getMinor( '1.2.3' ) );
		$this->assertSame( 0, SemVer::getMinor( 'v2.0.0' ) );
		$this->assertSame( 5, SemVer::getMinor( '10.5.1' ) );
		$this->assertSame( 0, SemVer::getMinor( '1' ) );
	}

	/**
	 * Tests getPatch method.
	 */
	public function testGetPatch(): void {
		$this->assertSame( 3, SemVer::getPatch( '1.2.3' ) );
		$this->assertSame( 0, SemVer::getPatch( 'v2.0.0' ) );
		$this->assertSame( 1, SemVer::getPatch( '10.5.1-beta.2' ) );
		$this->assertSame( 4, SemVer::getPatch( '1.2.4+build.100' ) );
		$this->assertSame( 0, SemVer::getPatch( '1.2' ) );
	}

	/**
	 * Tests isMajorBump method.
	 */
	public function testIsMajorBump(): void {
		$this->assertTrue( SemVer::isMajorBump( '1.2.0', '2.0.0' ) );
		$this->assertTrue( SemVer::isMajorBump( '1.2.0', '2.0.3' ) );
		$this->assertTrue( SemVer::isMajorBump( '0.9.0', '1.0.0' ) );
		$this->assertFalse( SemVer::isMajorBump( '1.2.0', '1.3.0' ) );
		$this->assertFalse( SemVer::isMajorBump( '1.2.0', '1.2.1' ) );
		$this->assertFalse( SemVer::isMajorBump( '2.0.0', '1.2.0' ) );
		$this->assertFalse( SemVer::isMajorBump( '1.2.0', '1.2.0' ) );
	}

	/**
	 * Tests isSameMajor method.
	 */
	public function testIsSameMajor(): void {
		$this->assertTrue( SemVer::isSameMajor( '1.2.0', '1.3.0' ) );
		$this->assertTrue( SemVer::isSameMajor( '1.0.0', '1.9.9' ) );
		$this->assertFalse( SemVer::isSameMajor( '1.2.0', '2.0.0' ) );
		$this->assertFalse( SemVer::isSameMajor( '0.9.0', '1.0.0' ) );
	}

	/**
	 * Tests compare method.
	 */
	public function testCompare(): void {
		$this->assertSame( -1, SemVer::compare( '1.2.0', '1.3.0' ) );
		$this->assertSame( 0, SemVer::compare( 'v1.2.0', '1.2.0' ) );
		$this->assertSame( 1, SemVer::compare( '2.0.0', '1.9.9' ) );
	}
}
