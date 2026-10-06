<?php
/**
 * PHPUnit bootstrap for free-plugin unit tests.
 *
 * Stubs the WordPress and WooCommerce surface that includes/functions.php and the
 * templates touch, then loads functions.php. It deliberately skips composer's
 * autoload "files", which would pull in admin code that needs a real WordPress.
 *
 * Every stub is guarded, so a later test may define a richer one first.
 *
 * @package SpringDevs\Subscription
 */

namespace {
	// Plugin files open with `defined( 'ABSPATH' ) || exit;`; without this the
	// first one loaded would end the run silently with a success code.
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}

	if ( ! defined( 'SUBSCRPT_TEMPLATES' ) ) {
		define( 'SUBSCRPT_TEMPLATES', dirname( __DIR__ ) . '/templates/' );
	}

	// Fixtures: tests write these, the stubs read them.
	$GLOBALS['wp_post_meta']      = []; // [post_id][meta_key] => value.
	$GLOBALS['wp_options']        = []; // [option_name] => value.
	$GLOBALS['applied_actions']   = []; // [hook] => [ [args], ... ], one entry per call.
	$GLOBALS['applied_filters']   = []; // [hook] => [ [value, args...], ... ].
	$GLOBALS['wp_filter_returns'] = []; // [hook] => forced filter return.
	$GLOBALS['wp_hooks_registry'] = []; // [hook][priority][] => [callback, accepted_args].

	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( $post_id, $key = '', $single = false ) {
			return $GLOBALS['wp_post_meta'][ $post_id ][ $key ] ?? ( $single ? '' : [] );
		}
	}

	if ( ! function_exists( 'metadata_exists' ) ) {
		function metadata_exists( $type, $post_id, $key ) {
			return array_key_exists( $key, $GLOBALS['wp_post_meta'][ $post_id ] ?? [] );
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $key, $default = false ) {
			return $GLOBALS['wp_options'][ $key ] ?? $default;
		}
	}

	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
			$GLOBALS['wp_hooks_registry'][ $tag ][ $priority ][] = [ $callback, $accepted_args ];
			return true;
		}
	}

	if ( ! function_exists( 'add_action' ) ) {
		function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
			return add_filter( $tag, $callback, $priority, $accepted_args );
		}
	}

	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $tag, $value, ...$args ) {
			$GLOBALS['applied_filters'][ $tag ][] = array_merge( [ $value ], $args );

			if ( empty( $GLOBALS['wp_hooks_registry'][ $tag ] ) ) {
				return $GLOBALS['wp_filter_returns'][ $tag ] ?? $value;
			}

			$by_priority = $GLOBALS['wp_hooks_registry'][ $tag ];
			ksort( $by_priority );
			foreach ( $by_priority as $callbacks ) {
				foreach ( $callbacks as list( $callback, $accepted ) ) {
					$value = $callback( ...array_slice( array_merge( [ $value ], $args ), 0, $accepted ) );
				}
			}
			return $value;
		}
	}

	if ( ! function_exists( 'do_action' ) ) {
		function do_action( $tag, ...$args ) {
			$GLOBALS['applied_actions'][ $tag ][] = $args;

			$by_priority = $GLOBALS['wp_hooks_registry'][ $tag ] ?? [];
			ksort( $by_priority );
			foreach ( $by_priority as $callbacks ) {
				foreach ( $callbacks as list( $callback, $accepted ) ) {
					$callback( ...array_slice( $args, 0, $accepted ) );
				}
			}
		}
	}

	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = 'default' ) {
			return $text;
		}
	}

	if ( ! function_exists( '_n' ) ) {
		function _n( $single, $plural, $number, $domain = 'default' ) {
			return 1 === (int) $number ? $single : $plural;
		}
	}

	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'wc_price' ) ) {
		function wc_price( $price ) {
			return '$' . number_format( (float) $price, 2 );
		}
	}

	if ( ! function_exists( 'wp_strip_all_tags' ) ) {
		function wp_strip_all_tags( $text ) {
			return trim( strip_tags( (string) $text ) );
		}
	}

	if ( ! function_exists( 'absint' ) ) {
		function absint( $value ) {
			return abs( (int) $value );
		}
	}

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $text ) {
			return trim( strip_tags( (string) $text ) );
		}
	}

	if ( ! function_exists( 'esc_url_raw' ) ) {
		function esc_url_raw( $url ) {
			return preg_match( '#^(https?://|/)#i', (string) $url ) ? trim( (string) $url ) : '';
		}
	}

	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( $data, $flags = 0, $depth = 512 ) {
			return json_encode( $data, $flags, $depth );
		}
	}

	if ( ! function_exists( 'esc_html__' ) ) {
		function esc_html__( $text, $domain = 'default' ) {
			return esc_html( $text );
		}
	}

	if ( ! function_exists( 'wp_kses_post' ) ) {
		function wp_kses_post( $text ) {
			return (string) $text;
		}
	}

	if ( ! function_exists( 'sanitize_html_class' ) ) {
		function sanitize_html_class( $classname ) {
			return preg_replace( '|%[a-fA-F0-9][a-fA-F0-9]|', '', preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $classname ) );
		}
	}

	if ( ! function_exists( 'checked' ) ) {
		function checked( $checked, $current = true, $display = true ) {
			$result = (string) $checked === (string) $current ? " checked='checked'" : '';
			if ( $display ) {
				echo $result; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			return $result;
		}
	}

	if ( ! function_exists( 'is_product' ) ) {
		function is_product() {
			return ! empty( $GLOBALS['wp_is_product'] );
		}
	}

	// Object cache: tests prime `subscrpt_plans` / `product_<id>` with plan rows
	// so PlanRepository::resolve_for_product() never reaches the database.
	$GLOBALS['wp_object_cache'] = []; // [group][key] => value.

	if ( ! function_exists( 'wp_cache_get' ) ) {
		function wp_cache_get( $key, $group = '' ) {
			return $GLOBALS['wp_object_cache'][ $group ][ $key ] ?? false;
		}
	}

	if ( ! function_exists( 'wp_cache_set' ) ) {
		function wp_cache_set( $key, $data, $group = '' ) {
			$GLOBALS['wp_object_cache'][ $group ][ $key ] = $data;
			return true;
		}
	}

	if ( ! function_exists( 'wc_get_template' ) ) {
		/**
		 * Include a plugin template with its args extracted as variables.
		 *
		 * The WooCommerce theme-override lookup is skipped: tests render the
		 * template that ships, never a theme's copy.
		 */
		function wc_get_template( $template_name, $args = [], $template_path = '', $default_path = '' ) {
			extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract
			include SUBSCRPT_TEMPLATES . $template_name;
		}
	}

	if ( ! function_exists( 'wc_get_template_html' ) ) {
		function wc_get_template_html( $template_name, $args = [], $template_path = '', $default_path = '' ) {
			ob_start();
			wc_get_template( $template_name, $args, $template_path, $default_path );
			return (string) ob_get_clean();
		}
	}

	/**
	 * A product, as much of one as free's helpers touch.
	 *
	 * It has to be called `WC_Product`: the plugin guards product reads with
	 * `instanceof \WC_Product`.
	 */
	if ( ! class_exists( 'WC_Product' ) ) {
		class WC_Product {

			public $id;
			public $name;
			public $type          = 'simple';
			public $regular_price = '';
			public $sale_price    = '';
			public $price         = '';
			public $meta          = [];

			public function __construct( int $id = 0, string $name = '', array $props = [] ) {
				$this->id   = $id;
				$this->name = $name ? $name : 'Product ' . $id;
				foreach ( $props as $key => $value ) {
					$this->$key = $value;
				}
			}

			public function get_id() {
				return $this->id;
			}

			public function get_name() {
				return $this->name;
			}

			public function get_regular_price() {
				return $this->regular_price;
			}

			public function get_sale_price() {
				return $this->sale_price;
			}

			public function get_price() {
				return '' !== $this->price ? $this->price : ( '' !== $this->sale_price ? $this->sale_price : $this->regular_price );
			}

			public function is_type( $type ) {
				return is_array( $type ) ? in_array( $this->type, $type, true ) : $this->type === $type;
			}

			public function get_meta( $key, $single = true ) {
				return $this->meta[ $key ] ?? '';
			}
		}
	}

	if ( ! class_exists( 'WC_Product_Stub' ) ) {
		class WC_Product_Stub extends WC_Product {}
	}

	// Lean autoloader: composer's would also load the admin files.
	spl_autoload_register(
		static function ( $class ) {
			$prefix = 'SpringDevs\\Subscription\\';
			if ( 0 !== strpos( $class, $prefix ) || 0 === strpos( $class, $prefix . 'Tests\\' ) ) {
				return;
			}
			$file = dirname( __DIR__ ) . '/includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_file( $file ) ) {
				require_once $file;
			}
		}
	);

	// Last: it calls the stubs above at run time, not load time.
	require_once dirname( __DIR__ ) . '/includes/functions.php';
}
