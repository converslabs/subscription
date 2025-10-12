<?php
/**
 * WooCommerce Subscriptions Conflict Detector
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility;

/**
 * ConflictDetector class.
 *
 * Detects if WooCommerce Subscriptions plugin is active and shows admin notice.
 *
 * @package SpringDevs\Subscription\Compatibility
 * @since   1.0.0
 */
class ConflictDetector {

	/**
	 * Single instance.
	 *
	 * @since 1.0.0
	 * @var   ConflictDetector|null
	 */
	private static $instance = null;

	/**
	 * Initialize the detector.
	 *
	 * @since  1.0.0
	 * @return ConflictDetector
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'admin_notices', array( $this, 'check_wcs_conflict' ) );
		add_action( 'admin_init', array( $this, 'handle_deactivate_wcs' ) );
	}

	/**
	 * Check for WooCommerce Subscriptions conflict.
	 *
	 * @since 1.0.0
	 */
	public function check_wcs_conflict() {
		// Check if real WooCommerce Subscriptions is active.
		// We check for the main WC_Subscriptions class and ensure it's not our compatibility version.
		if ( $this->is_wcs_active() ) {
			$this->show_conflict_notice();
		}
	}

	/**
	 * Check if WooCommerce Subscriptions is active.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	private function is_wcs_active() {
		// If our compatibility layer defined the constant, WCS is not active.
		if ( defined( 'WPSUBSCRIPTION_COMPAT_WC_SUBSCRIPTION' ) ) {
			return false;
		}

		// Check for WCS main class.
		if ( class_exists( 'WC_Subscriptions' ) ) {
			return true;
		}

		// Check if plugin file exists and is active.
		$active_plugins = (array) get_option( 'active_plugins', array() );
		return in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', $active_plugins, true );
	}

	/**
	 * Show conflict notice.
	 *
	 * @since 1.0.0
	 */
	private function show_conflict_notice() {
		$screen = get_current_screen();

		// Don't show on plugins page where WP already shows notices.
		if ( $screen && 'plugins' === $screen->id ) {
			return;
		}

		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'WPSubscription Compatibility Warning', 'wp_subscription' ); ?></strong>
			</p>
			<p>
				<?php
				echo wp_kses_post(
					__(
						'WooCommerce Subscriptions plugin is active alongside WPSubscription. For best compatibility and to avoid conflicts, please deactivate WooCommerce Subscriptions as WPSubscription provides the same functionality with additional features.',
						'wp_subscription'
					)
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Manage Plugins', 'wp_subscription' ); ?>
				</a>
				<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
					<a href="<?php echo esc_url( $this->get_deactivate_wcs_url() ); ?>" class="button button-primary">
						<?php esc_html_e( 'Deactivate WooCommerce Subscriptions', 'wp_subscription' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Get URL to deactivate WooCommerce Subscriptions.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	private function get_deactivate_wcs_url() {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'wpsubscription_deactivate_wcs',
				),
				admin_url( 'admin.php' )
			),
			'wpsubscription_deactivate_wcs'
		);
	}

	/**
	 * Handle WooCommerce Subscriptions deactivation request.
	 *
	 * @since 1.0.0
	 */
	public function handle_deactivate_wcs() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['action'] ) || 'wpsubscription_deactivate_wcs' !== $_GET['action'] ) {
			return;
		}
		// phpcs:enable

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpsubscription_deactivate_wcs' ) ) {
			wp_die( esc_html__( 'Security check failed', 'wp_subscription' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to deactivate plugins', 'wp_subscription' ) );
		}

		// Deactivate WooCommerce Subscriptions.
		deactivate_plugins( 'woocommerce-subscriptions/woocommerce-subscriptions.php' );

		// Redirect back with success message.
		wp_safe_redirect(
			add_query_arg(
				array(
					'wpsubscription_wcs_deactivated' => '1',
				),
				admin_url( 'plugins.php' )
			)
		);
		exit;
	}
}

