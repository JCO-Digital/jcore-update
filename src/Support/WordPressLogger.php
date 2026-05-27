<?php

declare(strict_types=1);

namespace Jcore\Update\Support;

final class WordPressLogger implements LoggerInterface {

	public function __construct( private readonly string $prefix = '[jcore-update]' ) {
	}

	public function debug( string $message, array $context = array() ): void {
		$this->log( 'DEBUG', $message, $context );
	}

	public function info( string $message, array $context = array() ): void {
		$this->log( 'INFO', $message, $context );
	}

	public function warning( string $message, array $context = array() ): void {
		$this->log( 'WARNING', $message, $context );
	}

	public function error( string $message, array $context = array() ): void {
		$this->log( 'ERROR', $message, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function log( string $level, string $message, array $context ): void {
		if ( ! \function_exists( 'error_log' ) ) {
			return;
		}

		$encoded = \json_encode( $context );
		$suffix  = \is_string( $encoded ) ? ' ' . $encoded : '';

		\error_log( $this->prefix . ' ' . $level . ': ' . $message . $suffix );
	}
}
