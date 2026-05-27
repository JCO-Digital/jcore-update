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
	 * @param bool               $success   Whether the check was successful.
	 * @param bool               $noUpdate  Whether there is no update available.
	 * @param UpdatePayload|null $payload   The update payload if available.
	 * @param string|null        $errorCode The error code if check failed.
	 * @param string|null        $message   The error message if check failed.
	 */
	public function __construct(
		public readonly bool $success,
		public readonly bool $noUpdate = false,
		public readonly ?UpdatePayload $payload = null,
		public readonly ?string $errorCode = null,
		public readonly ?string $message = null,
	) {
	}

	/**
	 * Creates a successful update result.
	 *
	 * @param UpdatePayload $payload The update payload.
	 *
	 * @return self
	 */
	public static function update( UpdatePayload $payload ): self {
		return new self( true, false, $payload );
	}

	/**
	 * Creates a "no update" result.
	 *
	 * @return self
	 */
	public static function noUpdate(): self {
		return new self( true, true );
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
