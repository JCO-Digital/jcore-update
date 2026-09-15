<?php
/**
 * Test bootstrap.
 *
 * @package Jcore\Update\Tests
 */

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

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

if ( ! function_exists( 'delete_site_transient' ) ) {
	/**
	 * Mock delete_site_transient.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_site_transient( $key ) {
		unset( $GLOBALS['wp_transients'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Mock get_option.
	 *
	 * @param string $key          Key.
	 * @param mixed  $defaultValue Default value.
	 * @return mixed
	 */
	function get_option( $key, $defaultValue = false ) {
		return $GLOBALS['wp_options'][ $key ] ?? $defaultValue;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Mock update_option.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	function update_option( $key, $value ) {
		$GLOBALS['wp_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Mock delete_option.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_option( $key ) {
		unset( $GLOBALS['wp_options'][ $key ] );
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

if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * Mock remove_filter.
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback  Callback.
	 * @param int      $priority  Priority.
	 * @return bool
	 */
	function remove_filter( $hook_name, $callback, $priority = 10 ) {
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Mock add_action.
	 *
	 * @param string   $hook_name     Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted args.
	 * @return bool
	 */
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'remove_action' ) ) {
	/**
	 * Mock remove_action.
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback  Callback.
	 * @param int      $priority  Priority.
	 * @return bool
	 */
	function remove_action( $hook_name, $callback, $priority = 10 ) {
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

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Mock sanitize_key.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Mock esc_html.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Mock esc_html__.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Mock esc_attr.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Mock esc_url.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return $url;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Mock admin_url.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	/**
	 * Mock wp_nonce_url.
	 *
	 * @param string $actionurl Action URL.
	 * @param string $action    Action.
	 * @param string $name      Name.
	 * @return string
	 */
	function wp_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) {
		$sep = str_contains( $actionurl, '?' ) ? '&' : '?';
		return $actionurl . $sep . $name . '=mock_nonce_' . $action;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Mock current_user_can.
	 *
	 * @param string $capability Capability.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		return $GLOBALS['wp_current_user_can'][ $capability ] ?? true;
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Mock check_admin_referer.
	 *
	 * @param string|int $action Action.
	 * @return bool
	 */
	function check_admin_referer( $action = -1 ) {
		unset( $action );
		return true;
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	/**
	 * Mock wp_safe_redirect.
	 *
	 * @param string $location Location.
	 * @param int    $status   Status.
	 * @return bool
	 */
	function wp_safe_redirect( $location, $status = 302 ) {
		$GLOBALS['wp_redirect_target'] = $location;
		return true;
	}
}

if ( ! function_exists( 'wp_get_referer' ) ) {
	/**
	 * Mock wp_get_referer.
	 *
	 * @return string|false
	 */
	function wp_get_referer() {
		return $GLOBALS['wp_referer'] ?? false;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Mock add_query_arg.
	 *
	 * @param mixed ...$args Arguments passed to add_query_arg.
	 * @return string
	 */
	function add_query_arg( ...$args ) {
		if ( count( $args ) === 2 && is_array( $args[0] ) ) {
			$params = $args[0];
			$url    = $args[1];
		} elseif ( count( $args ) === 3 ) {
			$params = array( $args[0] => $args[1] );
			$url    = $args[2];
		} else {
			return '';
		}

		$parts = explode( '?', $url, 2 );
		$base  = $parts[0];
		$query = array();
		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $query );
		}
		$query = array_merge( $query, $params );

		return $base . '?' . http_build_query( $query );
	}
}
