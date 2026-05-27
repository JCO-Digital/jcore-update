<?php
/**
 * Tests for UpdatePayload.
 *
 * @package Jcore\Update\Tests
 */

declare(strict_types=1);

namespace Jcore\Update\Tests;

use Jcore\Update\ValueObject\UpdatePayload;
use PHPUnit\Framework\TestCase;

/**
 * Class UpdatePayloadTest
 */
class UpdatePayloadTest extends TestCase {

	/**
	 * Test parsing from API response.
	 */
	public function testFromApiResponse(): void {
		$data = array(
			'new_version'  => '2.0.0',
			'package'      => 'https://example.com/download.zip',
			'url'          => 'https://example.com/info',
			'tested'       => '6.5',
			'requires'     => '6.0',
			'requires_php' => '8.0',
			'sections'     => array(
				'description' => 'A description',
			),
			'icons'        => array(
				'1x' => 'https://example.com/icon.png',
			),
			'banners'      => array(
				'low' => 'https://example.com/banner.png',
			),
			'name'         => 'My Plugin',
		);

		$payload = UpdatePayload::fromApiResponse( $data );

		$this->assertNotNull( $payload );
		$this->assertSame( '2.0.0', $payload->newVersion );
		$this->assertSame( 'https://example.com/download.zip', $payload->package );
		$this->assertSame( 'https://example.com/info', $payload->url );
		$this->assertSame( '6.5', $payload->tested );
		$this->assertSame( '6.0', $payload->requires );
		$this->assertSame( '8.0', $payload->requiresPhp );
		$this->assertSame( array( 'description' => 'A description' ), $payload->sections );
		$this->assertSame( array( '1x' => 'https://example.com/icon.png' ), $payload->icons );
		$this->assertSame( array( 'low' => 'https://example.com/banner.png' ), $payload->banners );
		$this->assertSame( 'My Plugin', $payload->name );
	}

	/**
	 * Test parsing from minimal API response.
	 */
	public function testFromApiResponseMinimal(): void {
		$data = array(
			'new_version' => '2.0.0',
		);

		$payload = UpdatePayload::fromApiResponse( $data );

		$this->assertNotNull( $payload );
		$this->assertSame( '2.0.0', $payload->newVersion );
		$this->assertNull( $payload->package );
	}

	/**
	 * Test parsing from invalid API response.
	 */
	public function testFromApiResponseInvalid(): void {
		$this->assertNull( UpdatePayload::fromApiResponse( array() ) );
		$this->assertNull( UpdatePayload::fromApiResponse( array( 'new_version' => '' ) ) );
	}

	/**
	 * Test conversion to array.
	 */
	public function testToArray(): void {
		$payload = new UpdatePayload(
			newVersion: '2.0.0',
			package: 'https://example.com/pkg',
			sections: array( 'changelog' => 'Fixes bugs' )
		);

		$array = $payload->toArray();

		$this->assertSame( '2.0.0', $array['new_version'] );
		$this->assertSame( 'https://example.com/pkg', $array['package'] );
		$this->assertSame( array( 'changelog' => 'Fixes bugs' ), $array['sections'] );
	}
}
