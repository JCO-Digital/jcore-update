<?php
/**
 * License validation result value object.
 *
 * @package Jcore\Update\Licensing
 */

declare(strict_types=1);

namespace Jcore\Update\Licensing;

/**
 * Class LicenseValidationResult
 *
 * Represents the result of a license validation attempt.
 */
final class LicenseValidationResult {

	/**
	 * LicenseValidationResult constructor.
	 *
	 * @param bool        $valid     Whether the license is valid.
	 * @param bool        $success   Whether the validation request was successful.
	 * @param bool        $fromCache Whether the result came from cache.
	 * @param string|null $errorCode Optional error code.
	 * @param string|null $message   Optional error message.
	 */
	public function __construct(
		public readonly bool $valid,
		public readonly bool $success,
		public readonly bool $fromCache,
		public readonly ?string $errorCode = null,
		public readonly ?string $message = null,
	) {
	}

	/**
	 * Checks if the validation request itself was successful.
	 *
	 * @return bool
	 */
	public function isSuccess(): bool {
		return $this->success;
	}

	/**
	 * Creates a successful validation result.
	 *
	 * @param bool $valid     Whether the license is valid.
	 * @param bool $fromCache Whether it was from cache.
	 *
	 * @return self
	 */
	public static function success( bool $valid, bool $fromCache = false ): self {
		return new self( $valid, true, $fromCache );
	}

	/**
	 * Creates a failed validation result.
	 *
	 * @param string      $errorCode The error code.
	 * @param string|null $message   The error message.
	 * @param bool        $fromCache Whether it was from cache.
	 *
	 * @return self
	 */
	public static function failure( string $errorCode, ?string $message = null, bool $fromCache = false ): self {
		return new self( false, false, $fromCache, $errorCode, $message );
	}
}
