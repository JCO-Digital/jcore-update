<?php

declare(strict_types=1);

namespace Jcore\Update\Licensing;

final class LicenseValidationResult {

	public function __construct(
		public readonly bool $valid,
		public readonly bool $success,
		public readonly bool $fromCache,
		public readonly ?string $errorCode = null,
		public readonly ?string $message = null,
	) {
	}

	public function isSuccess(): bool {
		return $this->success;
	}

	public static function success( bool $valid, bool $fromCache = false ): self {
		return new self( valid: $valid, success: true, fromCache: $fromCache );
	}

	public static function failure( string $errorCode, ?string $message = null, bool $fromCache = false ): self {
		return new self( valid: false, success: false, fromCache: $fromCache, errorCode: $errorCode, message: $message );
	}
}
