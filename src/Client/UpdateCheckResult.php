<?php
/**
 * Update check result value object.
 *
 * @package Jcore\Update\Client
 */

declare(strict_types=1);

namespace Jcore\Update\Client;

use Jcore\Update\ValueObject\UpdatePayload;

/**
 * Class UpdateCheckResult
 *
 * Represents the result of an update check.
 */
final class UpdateCheckResult {

	/**
	 * UpdateCheckResult constructor.
	 *
	 * @param bool                      $success      Whether the check was successful.
	 * @param bool                      $noUpdate     Whether there is no update available.
	 * @param UpdatePayload|null        $payload      The update payload if available.
	 * @param string|null               $errorCode    The error code if check failed.
	 * @param string|null               $message      The error message if check failed.
	 * @param UpdatePayload|null        $majorPayload Optional major update payload.
	 * @param array<string, mixed>|null $channels     Optional grouped channels.
	 * @param array<string>             $versions     Optional list of newer versions.
	 */
	public function __construct(
		public readonly bool $success,
		public readonly bool $noUpdate = false,
		public readonly ?UpdatePayload $payload = null,
		public readonly ?string $errorCode = null,
		public readonly ?string $message = null,
		public readonly ?UpdatePayload $majorPayload = null,
		public readonly ?array $channels = null,
		public readonly array $versions = array(),
	) {
	}

	/**
	 * Creates a successful update result.
	 *
	 * @param UpdatePayload             $payload      The update payload.
	 * @param UpdatePayload|null        $majorPayload Optional major update payload.
	 * @param array<string, mixed>|null $channels Optional grouped channels.
	 * @param array<string>             $versions     Optional list of newer versions.
	 *
	 * @return self
	 */
	public static function update(
		UpdatePayload $payload,
		?UpdatePayload $majorPayload = null,
		?array $channels = null,
		array $versions = array(),
	): self {
		return new self(
			success: true,
			noUpdate: false,
			payload: $payload,
			errorCode: null,
			message: null,
			majorPayload: $majorPayload,
			channels: $channels,
			versions: $versions,
		);
	}

	/**
	 * Creates a "no update" result.
	 *
	 * @param UpdatePayload|null        $majorPayload Optional major update payload.
	 * @param array<string, mixed>|null $channels     Optional grouped channels.
	 * @param array<string>             $versions     Optional list of newer versions.
	 *
	 * @return self
	 */
	public static function noUpdate(
		?UpdatePayload $majorPayload = null,
		?array $channels = null,
		array $versions = array(),
	): self {
		return new self(
			success: true,
			noUpdate: true,
			payload: null,
			errorCode: null,
			message: null,
			majorPayload: $majorPayload,
			channels: $channels,
			versions: $versions,
		);
	}

	/**
	 * Creates a failed update check result.
	 *
	 * @param string      $errorCode The error code.
	 * @param string|null $message   The error message.
	 *
	 * @return self
	 */
	public static function failure( string $errorCode, ?string $message = null ): self {
		return new self( false, false, null, $errorCode, $message );
	}
}
