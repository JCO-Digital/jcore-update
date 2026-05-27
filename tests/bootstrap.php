<?php
/**
 * Test bootstrap.
 *
 * @package Jcore\Update\Tests
 */

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Mock WordPress functions if not defined.
 */

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * Mock wp_remote_get.
	 *
	 * @param string $url  URL.
	 * @param array  $args Args.
	 * @return array|WP_Error
	 */
	function wp_remote_get( $url, $args = array() ) {
		return $GLOBALS['wp_remote_get_response'] ?? new WP_Error( 'not_implemented', 'Mock not configured' );
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * Mock wp_remote_post.
	 *
	 * @param string $url  URL.
	 * @param array  $args Args.
	 * @return array|WP_Error
	 */
	function wp_remote_post( $url, $args = array() ) {
		return $GLOBALS['wp_remote_post_response'] ?? new WP_Error( 'not_implemented', 'Mock not configured' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Mock wp_remote_retrieve_response_code.
	 *
	 * @param array $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return $response['response']['code'] ?? 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Mock wp_remote_retrieve_body.
	 *
	 * @param array $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		return $response['body'] ?? '';
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Mock is_wp_error.
	 *
	 * @param mixed $thing Thing to check.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Mock WP_Error.
	 */
	class WP_Error {
		/**
		 * WP_Error constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( public $code, public $message ) {}

		/**
		 * Get error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Mock wp_json_encode.
	 *
	 * @param mixed $data Data.
	 * @return string
	 */
	function wp_json_encode( $data ) {
		return (string) json_encode( $data );
	}
}

if ( ! function_exists( 'get_site_transient' ) ) {
	/**
	 * Mock get_site_transient.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	function get_site_transient( $key ) {
		return $GLOBALS['wp_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_site_transient' ) ) {
	/**
	 * Mock set_site_transient.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL.
	 * @return bool
	 */
	function set_site_transient( $key, $value, $ttl ) {
		$GLOBALS['wp_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Mock apply_filters.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  $value     Value.
	 * @param mixed  ...$args   Args.
	 * @return mixed
	 */
	function apply_filters( $hook_name, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Mock add_filter.
	 *
	 * @param string   $hook_name     Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted args.
	 * @return bool
	 */
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	/**
	 * Mock plugin_basename.
	 *
	 * @param string $file File.
	 * @return string
	 */
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}
