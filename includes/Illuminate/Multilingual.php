<?php
/**
 * WPML / Polylang compatibility.
 *
 * @package SpringDevs\Subscription\Illuminate
 */

namespace SpringDevs\Subscription\Illuminate;

use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;
use WC_Email;

/**
 * Class Multilingual
 *
 * Everything here goes through WPML's hook API — `wpml_object_id`,
 * `wpml_switch_language`, `wpml_translate_single_string` and friends — never
 * `icl_*()` or `pll_*()` functions. Polylang implements the same hooks, so one
 * code path serves both, and with neither installed every filter returns the
 * default it was given: nothing to guard, nothing to fatal.
 *
 * Three things need it:
 *
 * - Plans are attached to a product id, and a translation is another post, so
 *   plan lookups read across the product's whole translation group.
 * - Plan and plan-group titles live in the plan tables, not in posts, so they are
 *   registered as translatable strings and translated on the way out.
 * - Customer emails are mostly sent by cron, with no visitor to take a language
 *   from, so the shopper's language is recorded on the subscription at checkout
 *   and each customer email switches to it while it renders.
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Multilingual {

	/**
	 * String-translation context (WPML) / group (Polylang) for plan titles.
	 */
	const STRING_CONTEXT = 'WPSubscription';

	/**
	 * Subscription meta: the locale the shopper checked out in.
	 */
	const LOCALE_META = '_subscrpt_locale';

	/**
	 * Subscription meta: the WPML / Polylang language code the shopper checked out in.
	 */
	const LANGUAGE_META = '_subscrpt_language';

	/**
	 * Option: hash of the plan titles last registered, so they are registered once per change.
	 */
	const STRINGS_HASH_OPTION = 'subscrpt_plan_strings_hash';

	/**
	 * WooCommerce session key: the locale and language of the storefront page the shopper last saw.
	 */
	const SESSION_KEY = 'subscrpt_shopper_language';

	/**
	 * Translation groups already looked up this request, by post id.
	 *
	 * @var array<int,int[]>
	 */
	private static $groups = array();

	/**
	 * What each open switch changed, innermost last: [ locale switched, language switched ].
	 *
	 * @var array<int,bool[]>
	 */
	private static $switches = array();

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'remember_shopper_language' ) );
		add_action( 'save_post_subscrpt_order', array( $this, 'remember_language' ), 10, 3 );
		add_filter( 'subscrpt_before_saving_renewal_order', array( $this, 'set_renewal_order_language' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'register_plan_strings' ) );
	}

	/**
	 * Whether WPML or Polylang is running with at least one language set up.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return ! empty( apply_filters( 'wpml_default_language', null ) );
	}

	/**
	 * Every post in a post's translation group, the post itself first.
	 *
	 * A single-item list when no multilingual plugin is active or the post has no
	 * translations.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return int[]
	 */
	public static function translation_ids( $post_id ): array {
		$post_id = absint( $post_id );

		if ( ! $post_id || ! self::is_active() ) {
			return array( $post_id );
		}

		if ( isset( self::$groups[ $post_id ] ) ) {
			return self::$groups[ $post_id ];
		}

		$ids       = array( $post_id );
		$post_type = get_post_type( $post_id );

		// One lookup per language rather than `wpml_get_element_translations`:
		// Polylang's version of that queries without a post type, so it only
		// ever finds posts, never products.
		if ( $post_type ) {
			$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );

			foreach ( array_keys( (array) $languages ) as $language ) {
				$ids[] = (int) apply_filters( 'wpml_object_id', $post_id, $post_type, false, $language );
			}
		}

		self::$groups[ $post_id ] = array_values( array_unique( array_filter( $ids ) ) );

		return self::$groups[ $post_id ];
	}

	/**
	 * One id that stands for a post in every language — the lowest in its group.
	 *
	 * Used to key caches, so every translation of a product shares one entry and
	 * one flush clears it for all of them.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return int
	 */
	public static function canonical_id( $post_id ): int {
		return (int) min( self::translation_ids( $post_id ) );
	}

	/**
	 * Translate a string registered with {@see register_plan_strings()}.
	 *
	 * @param string $value Original value.
	 * @param string $name  String name, e.g. `plan_12_title`.
	 *
	 * @return string
	 */
	public static function translate_string( $value, $name ): string {
		$value = (string) $value;

		if ( '' === $value || ! self::is_active() ) {
			return $value;
		}

		return (string) apply_filters( 'wpml_translate_single_string', $value, self::STRING_CONTEXT, $name );
	}

	/**
	 * Translate the plan and group titles of resolved plan rows.
	 *
	 * @param array $rows Rows from {@see PlanRepository::resolve_for_product()}.
	 *
	 * @return array
	 */
	public static function translate_plan_rows( array $rows ): array {
		if ( ! self::is_active() ) {
			return $rows;
		}

		foreach ( $rows as &$row ) {
			if ( isset( $row['group_title'], $row['plan_group_id'] ) ) {
				$row['group_title'] = self::translate_string( $row['group_title'], self::group_string_name( $row['plan_group_id'] ) );
			}
			if ( isset( $row['plan_title'], $row['plan_id'] ) ) {
				$row['plan_title'] = self::translate_string( $row['plan_title'], self::plan_string_name( $row['plan_id'] ) );
			}
		}
		unset( $row );

		return $rows;
	}

	/**
	 * String name for a plan group's title.
	 *
	 * @param int $group_id Plan group id.
	 *
	 * @return string
	 */
	public static function group_string_name( $group_id ): string {
		return 'plan_group_' . absint( $group_id ) . '_title';
	}

	/**
	 * String name for a plan's title.
	 *
	 * @param int $plan_id Plan id.
	 *
	 * @return string
	 */
	public static function plan_string_name( $plan_id ): string {
		return 'plan_' . absint( $plan_id ) . '_title';
	}

	/**
	 * Register every plan and plan-group title for translation.
	 *
	 * Runs on admin_init but only does work when a title has been added or changed
	 * since the last run, so the strings appear in WPML String Translation /
	 * Polylang's Translations screen without hooking every write path.
	 *
	 * @return void
	 */
	public function register_plan_strings() {
		if ( ! self::is_active() ) {
			return;
		}

		$titles = PlanRepository::get_titles();
		$hash   = md5( (string) wp_json_encode( $titles ) );

		if ( get_option( self::STRINGS_HASH_OPTION ) === $hash ) {
			return;
		}

		foreach ( $titles['groups'] as $group_id => $title ) {
			do_action( 'wpml_register_single_string', self::STRING_CONTEXT, self::group_string_name( $group_id ), $title );
		}
		foreach ( $titles['plans'] as $plan_id => $title ) {
			do_action( 'wpml_register_single_string', self::STRING_CONTEXT, self::plan_string_name( $plan_id ), $title );
		}

		update_option( self::STRINGS_HASH_OPTION, $hash, false );
	}

	/**
	 * Keep the language of the shop pages the shopper browses in their session.
	 *
	 * The block checkout places its order through the Store API, a REST request
	 * with no language in its URL, which Polylang and WPML answer in the default
	 * language. The page the shopper was looking at knew their language, so it is
	 * kept here for {@see remember_language()}. Only written to a session that
	 * already exists, so a visitor with no cart costs nothing.
	 *
	 * Shop, product, cart and checkout pages only: any other front-end request —
	 * the browser's own `/favicon.ico`, a 404, a feed — has no language prefix
	 * either, and would overwrite it with the default.
	 *
	 * @return void
	 */
	public function remember_shopper_language() {
		if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->session->has_session() ) {
			return;
		}

		if ( ! is_woocommerce() && ! is_cart() && ! ( is_checkout() && ! is_wc_endpoint_url() ) ) {
			return;
		}

		WC()->session->set(
			self::SESSION_KEY,
			array(
				'locale'   => determine_locale(),
				'language' => (string) apply_filters( 'wpml_current_language', null ),
			)
		);
	}

	/**
	 * Record the shopper's language on a subscription when checkout creates it.
	 *
	 * The language of the last storefront page they saw, else this request's.
	 * Only when it differs from the site's — a subscription with nothing recorded
	 * gets emails in the site language, and keeps following it if that changes.
	 * Admin and cron requests are skipped: their language is not the customer's.
	 *
	 * @param int      $post_id Subscription id.
	 * @param \WP_Post $post    Subscription post.
	 * @param bool     $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function remember_language( $post_id, $post, $update ) {
		if ( $update || is_admin() || wp_doing_cron() || metadata_exists( 'post', $post_id, self::LOCALE_META ) ) {
			return;
		}

		$seen = function_exists( 'WC' ) && WC()->session ? WC()->session->get( self::SESSION_KEY ) : null;

		$locale      = ! empty( $seen['locale'] ) ? $seen['locale'] : determine_locale();
		$site_locale = get_option( 'WPLANG' ) ? get_option( 'WPLANG' ) : 'en_US';
		if ( $locale && $locale !== $site_locale ) {
			update_post_meta( $post_id, self::LOCALE_META, $locale );
		}

		$language = ! empty( $seen['language'] ) ? $seen['language'] : apply_filters( 'wpml_current_language', null );
		if ( $language && 'all' !== $language && apply_filters( 'wpml_default_language', null ) !== $language ) {
			update_post_meta( $post_id, self::LANGUAGE_META, $language );
		}
	}

	/**
	 * Give a renewal order its subscription's language.
	 *
	 * Renewal orders are built in cron, so nothing sets a language on them, and
	 * WooCommerce Multilingual sends an order's own emails — "order received",
	 * "completed" — in the language stored in its `wpml_language` meta.
	 * Polylang for WooCommerce keeps order languages its own way, which the WPML
	 * hook API cannot set.
	 *
	 * @param \WC_Order $new_order       Renewal order, not yet saved.
	 * @param \WC_Order $old_order       Order it renews.
	 * @param int       $subscription_id Subscription id.
	 *
	 * @return \WC_Order
	 */
	public function set_renewal_order_language( $new_order, $old_order, $subscription_id ) {
		if ( ! $new_order instanceof \WC_Order || ! self::is_active() || $new_order->get_meta( 'wpml_language' ) ) {
			return $new_order;
		}

		$language = self::subscription_language( $subscription_id )['language'];
		if ( '' === $language && $old_order instanceof \WC_Order ) {
			$language = (string) $old_order->get_meta( 'wpml_language' );
		}

		if ( '' !== $language ) {
			$new_order->update_meta_data( 'wpml_language', $language );
		}

		return $new_order;
	}

	/**
	 * The locale and language a subscription's customer emails should be written in.
	 *
	 * What checkout recorded; for subscriptions from before that, the language
	 * WPML stored on the parent order, then the customer's own profile language.
	 * Empty values mean the site language.
	 *
	 * @param int $subscription_id Subscription id.
	 *
	 * @return array{locale:string,language:string}
	 */
	public static function subscription_language( $subscription_id ): array {
		$locale   = (string) get_post_meta( $subscription_id, self::LOCALE_META, true );
		$language = (string) get_post_meta( $subscription_id, self::LANGUAGE_META, true );

		if ( '' === $locale && '' === $language ) {
			$order_item_id = (int) get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
			$order_id      = $order_item_id ? wc_get_order_id_by_order_item_id( $order_item_id ) : 0;
			$order         = $order_id ? wc_get_order( $order_id ) : false;

			if ( $order && self::is_active() ) {
				$language = (string) $order->get_meta( 'wpml_language' );
				$locale   = self::locale_for_language( $language );
			}

			$user_id = (int) get_post_field( 'post_author', $subscription_id );
			if ( '' === $locale && $user_id && get_user_meta( $user_id, 'locale', true ) ) {
				$locale = get_user_locale( $user_id );
			}
		}

		/**
		 * Filter the language a subscription's customer emails are written in.
		 *
		 * @param array $language {
		 *     @type string $locale   WordPress locale, e.g. `de_DE`. Empty for the site locale.
		 *     @type string $language WPML / Polylang language code, e.g. `de`. Empty for the current language.
		 * }
		 * @param int   $subscription_id Subscription id.
		 */
		return apply_filters(
			'subscrpt_subscription_language',
			array(
				'locale'   => $locale,
				'language' => $language,
			),
			$subscription_id
		);
	}

	/**
	 * The locale WPML has for a language code.
	 *
	 * @param string $language Language code.
	 *
	 * @return string Empty when unknown.
	 */
	private static function locale_for_language( $language ): string {
		if ( '' === (string) $language ) {
			return '';
		}

		$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );

		return isset( $languages[ $language ]['default_locale'] ) ? (string) $languages[ $language ]['default_locale'] : '';
	}

	/**
	 * Switch translations to a subscription's language.
	 *
	 * Switches the locale for gettext, and the WPML / Polylang language for their
	 * translated options and strings. Pair every `true` with
	 * {@see restore_language()}.
	 *
	 * @param int $subscription_id Subscription id.
	 *
	 * @return bool Whether anything was switched.
	 */
	public static function switch_to_subscription_language( $subscription_id ): bool {
		$target = self::subscription_language( $subscription_id );

		$locale_switched = ! empty( $target['locale'] ) && switch_to_locale( $target['locale'] );

		$language_switched = false;
		if ( ! empty( $target['language'] ) && self::is_active() && apply_filters( 'wpml_current_language', null ) !== $target['language'] ) {
			do_action( 'wpml_switch_language', $target['language'] );
			$language_switched = true;
		}

		if ( ! $locale_switched && ! $language_switched ) {
			return false;
		}

		self::$switches[] = array( $locale_switched, $language_switched );

		return true;
	}

	/**
	 * Undo the last {@see switch_to_subscription_language()}.
	 *
	 * @return void
	 */
	public static function restore_language() {
		$switch = array_pop( self::$switches );

		if ( ! $switch ) {
			return;
		}

		list( $locale_switched, $language_switched ) = $switch;

		if ( $language_switched ) {
			do_action( 'wpml_switch_language', null );
		}
		if ( $locale_switched ) {
			restore_previous_locale();
		}
	}

	/**
	 * Switch an email to its subscription's language before it builds anything.
	 *
	 * WooCommerce reads an email's saved settings when the email object is built,
	 * long before a trigger runs, so they are re-read after the switch to pick up a
	 * translated subject and heading.
	 *
	 * @param WC_Email $email           Email about to be sent.
	 * @param int      $subscription_id Subscription id.
	 *
	 * @return bool Whether it switched; pass `true` on to {@see restore_email_language()}.
	 */
	public static function switch_email_language( WC_Email $email, $subscription_id ): bool {
		if ( ! self::switch_to_subscription_language( $subscription_id ) ) {
			return false;
		}

		$email->init_settings();

		return true;
	}

	/**
	 * Put an email back in the site language after sending.
	 *
	 * @param WC_Email $email Email that was sent.
	 *
	 * @return void
	 */
	public static function restore_email_language( WC_Email $email ) {
		self::restore_language();
		$email->init_settings();
	}
}
