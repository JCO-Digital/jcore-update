<?php
/**
 * Logger that uses WordPress error_log.
 *
 * @package Jcore\Update\Support
 */

declare(strict_types=1);

namespace Jcore\Update\Support;

/**
 * Class WordPressLogger
 *
 * Implements LoggerInterface using WordPress's error_log function.
 */
final class WordPressLogger implements LoggerInterface {

	/**
	 * WordPressLogger constructor.
	 *
	 * @param string $prefix The log message prefix.
	 */
	public function __construct( private readonly string $prefix = '[jcore-update]' ) {
	}

	/**
	 * Log a debug message.
	 *
	 * @param string               $message The message.
	 * @param array<string, mixed> $context The context.
	 *
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( 'DEBUG', $message, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @param string               $message The message.
	 * @param array<string, mixed> $context The context.
	 *
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'INFO', $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string               $message The message.
	 * @param array<string, mixed> $context The context.
	 *
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'WARNING', $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string               $message The message.
	 * @param array<string, mixed> $context The context.
	 *
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'ERROR', $message, $context );
	}

	/**
	 * Internal log method.
	 *
	 * @param string               $level   The log level.
	 * @param string               $message The message.
	 * @param array<string, mixed> $context The context.
	 *
	 * @return void
	 */
	private function log( string $level, string $message, array $context ): void {
		if ( ! \function_exists( 'error_log' ) ) {
			return;
		}

		$encoded = \wp_json_encode( $context );
		$suffix  = \is_string( $encoded ) ? ' ' . $encoded : '';

		\error_log( $this->prefix . ' ' . $level . ': ' . $message . $suffix ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
