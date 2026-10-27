<?php
/**
 * Admin notices on WPSubscription screens.
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

/**
 * Keeps WPSubscription screens free of other plugins' notices.
 *
 * WordPress prints every admin notice on every admin screen, so a merchant
 * opening the dashboard first scrolls past promotions, setup nags and update
 * prompts that have nothing to do with subscriptions. On this plugin's own
 * screens the notice hooks are emptied, and only notices registered through
 * the `subscrpt_admin_notices` filter are shown — under the page header, in
 * the admin component style (`wpsubs-notice`).
 *
 * Every other admin screen is left exactly as WordPress renders it.
 */
class Notices {

	/**
	 * Notice hooks emptied on WPSubscription screens.
	 */
	const HOOKS = array( 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' );

	/**
	 * Notice types and the dashicon each one shows.
	 */
	const TYPES = array(
		'info'    => 'info-outline',
		'success' => 'yes-alt',
		'warning' => 'warning',
		'error'   => 'dismiss',
	);

	/**
	 * Whether the notices have been printed on this request.
	 *
	 * @var bool
	 */
	private static $rendered = false;

	/**
	 * Hook the suppression.
	 */
	public function __construct() {
		// The last thing to run before admin-header.php fires the notice hooks,
		// so callbacks registered as late as `in_admin_header` are caught too.
		add_action( 'in_admin_header', array( $this, 'suppress_notices' ), PHP_INT_MAX );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Whether the current admin screen is one of this plugin's pages.
	 *
	 * Covers the dashboard and every submenu page, including the ones pro and
	 * add-ons register under the WPSubscription menu.
	 *
	 * @return bool
	 */
	public static function is_own_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return false;
		}

		return 'toplevel_page_wp-subscription' === $screen->id
			|| str_starts_with( $screen->id, 'wpsubscription_page' )
			|| str_starts_with( $screen->id, 'wp-subscription_page' );
	}

	/**
	 * Remove every notice callback on WPSubscription screens.
	 *
	 * @return void
	 */
	public function suppress_notices() {
		if ( ! self::is_own_screen() ) {
			return;
		}

		foreach ( self::HOOKS as $hook ) {
			remove_all_actions( $hook );
		}
	}

	/**
	 * Mark WPSubscription screens, so the stylesheet can hide notices other
	 * plugins inject with JavaScript after the page has loaded.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		if ( self::is_own_screen() ) {
			$classes .= ' subscrpt-own-screen';
		}

		return $classes;
	}

	/**
	 * Print the WPSubscription notices.
	 *
	 * Called from Menu::render_admin_header(), so they sit directly under the
	 * page header on every screen that has one. Printed once per request.
	 *
	 * @return void
	 */
	public static function render() {
		if ( self::$rendered ) {
			return;
		}
		self::$rendered = true;

		/**
		 * Filters the notices shown on WPSubscription admin screens.
		 *
		 * Other plugins' admin notices are not shown on these screens. A notice
		 * that belongs here — one about subscriptions — is added through this
		 * filter instead and rendered in the WPSubscription style.
		 *
		 * @since 2.5.0
		 *
		 * @param array[] $notices {
		 *     List of notices.
		 *
		 *     @type string $id          Unique key.
		 *     @type string $type        info, success, warning or error. Default info.
		 *     @type string $title       Optional heading.
		 *     @type string $message     Body. Basic HTML allowed.
		 *     @type array  $action      Optional `[ 'label' => string, 'url' => string ]`.
		 *     @type bool   $dismissible Whether a close button is shown. Default true.
		 * }
		 */
		$notices = apply_filters( 'subscrpt_admin_notices', array() );

		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}
		?>
		<div class="wpsubs-notices">
			<?php
			foreach ( $notices as $notice ) {
				if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
					self::render_notice( $notice );
				}
			}
			?>
		</div>
		<?php
	}

	/**
	 * Print one notice.
	 *
	 * @param array $notice Notice, as documented on `subscrpt_admin_notices`.
	 * @return void
	 */
	private static function render_notice( array $notice ) {
		$type        = isset( $notice['type'], self::TYPES[ $notice['type'] ] ) ? $notice['type'] : 'info';
		$dismissible = $notice['dismissible'] ?? true;
		$action      = $notice['action'] ?? array();
		?>
		<div
			class="wpsubs-notice wpsubs-notice--<?php echo esc_attr( $type ); ?>"
			role="<?php echo 'error' === $type ? 'alert' : 'status'; ?>"
			<?php if ( ! empty( $notice['id'] ) ) : ?>
				data-notice="<?php echo esc_attr( $notice['id'] ); ?>"
			<?php endif; ?>
		>
			<span class="wpsubs-notice__icon dashicons dashicons-<?php echo esc_attr( self::TYPES[ $type ] ); ?>" aria-hidden="true"></span>
			<div class="wpsubs-notice__body">
				<?php if ( ! empty( $notice['title'] ) ) : ?>
					<p class="wpsubs-notice__title"><?php echo esc_html( $notice['title'] ); ?></p>
				<?php endif; ?>
				<div class="wpsubs-notice__text"><?php echo wp_kses_post( wpautop( $notice['message'] ) ); ?></div>
				<?php if ( ! empty( $action['label'] ) && ! empty( $action['url'] ) ) : ?>
					<a class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm wpsubs-notice__action" href="<?php echo esc_url( $action['url'] ); ?>">
						<?php echo esc_html( $action['label'] ); ?>
					</a>
				<?php endif; ?>
			</div>
			<?php if ( $dismissible ) : ?>
				<button type="button" class="wpsubs-notice__close" data-wpsubs-notice-close aria-label="<?php esc_attr_e( 'Dismiss this notice', 'subscription' ); ?>">
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
				</button>
			<?php endif; ?>
		</div>
		<?php
	}
}
