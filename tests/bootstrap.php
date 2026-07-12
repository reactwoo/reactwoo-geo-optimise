<?php
/**
 * PHPUnit bootstrap for Geo Optimise generation transport tests.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'RWGA_PATH', dirname( __DIR__ ) . '/merged-geo-ai/' );
define( 'RWGA_VERSION', '0.4.68' );
define( 'RWGO_VERSION', '0.4.79' );

$GLOBALS['rwga_test_options'] = array();
$GLOBALS['rwga_test_filters'] = array();

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub.
	 */
	class WP_Error {
		/** @var string */
		private $code;
		/** @var string */
		private $message;
		/** @var mixed */
		private $data;

		/**
		 * @param string $code    Code.
		 * @param string $message Message.
		 * @param mixed  $data    Data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			$this->data    = $data;
		}

		/** @return string */
		public function get_error_code() {
			return $this->code;
		}

		/** @return string */
		public function get_error_message() {
			return $this->message;
		}

		/** @return mixed */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $str Value.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return is_scalar( $str ) ? (string) $str : '';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $value Value.
	 * @param int   $flags Flags.
	 * @return string|false
	 */
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return (string) $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook  Hook.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Args.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['rwga_test_filters'][ $hook ] ) ) {
			return $value;
		}
		foreach ( $GLOBALS['rwga_test_filters'][ $hook ] as $callback ) {
			$value = $callback( $value, ...$args );
		}
		return $value;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $option  Option.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		return $GLOBALS['rwga_test_options'][ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $option Option.
	 * @param mixed  $value  Value.
	 * @return bool
	 */
	function update_option( $option, $value ) {
		$GLOBALS['rwga_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! class_exists( 'RWGA_Settings', false ) ) {
	/**
	 * Minimal settings stub for engine mode tests.
	 */
	class RWGA_Settings {
		const OPTION_KEY = 'rwga_settings';

		/**
		 * @return array<string, mixed>
		 */
		public static function get_settings() {
			$stored = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			return array_merge(
				array( 'workflow_engine' => 'automatic' ),
				$stored
			);
		}
	}
}

require_once RWGA_PATH . 'includes/services/class-rwga-engine.php';
require_once RWGA_PATH . 'includes/services/generation/interface-rwga-generation-transport.php';
require_once RWGA_PATH . 'includes/services/generation/class-rwga-workflow-prompt-spec-registry.php';
require_once RWGA_PATH . 'includes/services/generation/class-rwga-prompt-context-formatter.php';
require_once RWGA_PATH . 'includes/services/generation/class-rwga-local-ai-transport.php';
require_once RWGA_PATH . 'includes/services/generation/class-rwga-managed-ai-transport.php';
require_once RWGA_PATH . 'includes/services/generation/class-rwga-wordpress-ai-transport.php';
require_once RWGA_PATH . 'includes/services/generation/class-rwga-generation-router.php';
