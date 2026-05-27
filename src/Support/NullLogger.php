<?php

declare(strict_types=1);

namespace Jcore\Update\Support;

final class NullLogger implements LoggerInterface {

	public function debug( string $message, array $context = array() ): void {
	}

	public function info( string $message, array $context = array() ): void {
	}

	public function warning( string $message, array $context = array() ): void {
	}

	public function error( string $message, array $context = array() ): void {
	}
}
