<?php

declare(strict_types=1);

namespace Jcore\Update\Client;

use Jcore\Update\ValueObject\UpdatePayload;

final class UpdateCheckResult {

	public function __construct(
		public readonly bool $success,
		public readonly bool $noUpdate,
		public readonly ?UpdatePayload $payload = null,
		public readonly ?string $errorCode = null,
		public readonly ?string $message = null,
	) {
	}

	public static function update( UpdatePayload $payload ): self {
		return new self( success: true, noUpdate: false, payload: $payload );
	}

	public static function noUpdate(): self {
		return new self( success: true, noUpdate: true );
	}

	public static function failure( string $errorCode, ?string $message = null ): self {
		return new self( success: false, noUpdate: false, errorCode: $errorCode, message: $message );
	}
}
