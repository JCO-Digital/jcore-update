<?php
/**
 * A logger that does nothing.
 *
 * @package Jcore\Update\Support
 */

declare(strict_types=1);

namespace Jcore\Update\Support;

/**
 * Class NullLogger
 *
 * Implements LoggerInterface by doing nothing.
 */
final class NullLogger implements LoggerInterface {

	/**
	 * Log a debug message.
	 *
	 * @param string               $message The message.
	 * @param array<string, mixed> $context The context.
	 *
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
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
	}
}
