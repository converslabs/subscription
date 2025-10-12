<?php
/**
 * Compatibility Status Page
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Admin;

use SpringDevs\Subscription\Compatibility\Bootstrap;

/**
 * StatusPage class.
 *
 * Displays compatibility layer status in WordPress admin.
 *
 * @package SpringDevs\Subscription\Compatibility\Admin
 * @since   1.0.0
 */
class StatusPage {

	/**
	 * Single instance.
	 *
	 * @since 1.0.0
	 * @var   StatusPage|null
	 */
	private static $instance = null;

	/**
	 * Initialize the status page.
	 *
	 * @since  1.0.0
	 * @return StatusPage
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
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add menu page.
	 *
	 * @since 1.0.0
	 */
	public function add_menu_page() {
		add_submenu_page(
			'wp-subscription',
			__( 'Compatibility Status', 'wp_subscription' ),
			__( 'Compatibility', 'wp_subscription' ),
			'manage_woocommerce',
			'wp-subscription-compatibility',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'wp-subscription_page_wp-subscription-compatibility' !== $hook ) {
			return;
		}

		// Use WordPress core styles for consistent UI.
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
	}

	/**
	 * Render the status page.
	 *
	 * @since 1.0.0
	 */
	public function render_page() {
		$status     = Bootstrap::get_status();
		$is_healthy = $status['is_healthy'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WooCommerce Subscriptions Compatibility Status', 'wp_subscription' ); ?></h1>

			<div class="card" style="max-width: 800px;">
				<h2><?php esc_html_e( 'Overall Status', 'wp_subscription' ); ?></h2>
				<table class="widefat">
					<tbody>
						<tr>
							<td style="width: 200px;"><strong><?php esc_html_e( 'Status', 'wp_subscription' ); ?></strong></td>
							<td>
								<?php if ( $is_healthy ) : ?>
									<span style="color: #46b450;">✓ <?php esc_html_e( 'Active', 'wp_subscription' ); ?></span>
								<?php else : ?>
									<span style="color: #dc3232;">✗ <?php esc_html_e( 'Issues Detected', 'wp_subscription' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Version', 'wp_subscription' ); ?></strong></td>
							<td><?php echo esc_html( $status['version'] ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2><?php esc_html_e( 'Loaded Components', 'wp_subscription' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Component', 'wp_subscription' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp_subscription' ); ?></th>
							<th><?php esc_html_e( 'Count', 'wp_subscription' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$this->render_component_row(
							__( 'Core Functions', 'wp_subscription' ),
							Bootstrap::is_component_loaded( 'functions' ),
							Bootstrap::get_component_count( 'functions_count' )
						);
						$this->render_component_row(
							__( 'Wrapper Classes', 'wp_subscription' ),
							Bootstrap::is_component_loaded( 'classes' ),
							Bootstrap::get_component_count( 'classes_count' ) . '/6'
						);
						$this->render_component_row(
							__( 'Class Aliases', 'wp_subscription' ),
							Bootstrap::is_component_loaded( 'aliases' ),
							Bootstrap::get_component_count( 'aliases_count' ) . '/6'
						);
						$this->render_component_row(
							__( 'Action Hooks', 'wp_subscription' ),
							Bootstrap::is_component_loaded( 'hooks' ),
							Bootstrap::get_component_count( 'action_hooks_count' )
						);
						$this->render_component_row(
							__( 'Filter Hooks', 'wp_subscription' ),
							Bootstrap::is_component_loaded( 'hooks' ),
							Bootstrap::get_component_count( 'filter_hooks_count' )
						);
						$this->render_component_row(
							__( 'Gateway Integration', 'wp_subscription' ),
							Bootstrap::is_component_loaded( 'gateways' ),
							$this->get_supported_gateways_count()
						);
						?>
					</tbody>
				</table>
			</div>

			<?php $this->render_detected_gateways(); ?>

			<?php if ( ! empty( $status['errors'] ) ) : ?>
				<div class="card" style="max-width: 800px; margin-top: 20px; border-left: 4px solid #dc3232;">
					<h2><?php esc_html_e( 'Errors', 'wp_subscription' ); ?></h2>
					<ul style="color: #dc3232;">
						<?php foreach ( $status['errors'] as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php $this->render_conflict_status(); ?>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2><?php esc_html_e( 'Quick Tests', 'wp_subscription' ); ?></h2>
				<p><?php esc_html_e( 'Run quick tests to verify compatibility layer functionality.', 'wp_subscription' ); ?></p>
				<p>
					<a href="#" class="button button-secondary" onclick="alert('Test suite coming soon!'); return false;">
						<?php esc_html_e( 'Run All Tests', 'wp_subscription' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a component status row.
	 *
	 * @since 1.0.0
	 * @param string     $label Component label.
	 * @param bool       $is_loaded Whether component is loaded.
	 * @param string|int $count Item count.
	 */
	private function render_component_row( $label, $is_loaded, $count = '' ) {
		?>
		<tr>
			<td><strong><?php echo esc_html( $label ); ?></strong></td>
			<td>
				<?php if ( $is_loaded ) : ?>
					<span style="color: #46b450;">✓ <?php esc_html_e( 'Loaded', 'wp_subscription' ); ?></span>
				<?php else : ?>
					<span style="color: #dc3232;">✗ <?php esc_html_e( 'Not Loaded', 'wp_subscription' ); ?></span>
				<?php endif; ?>
			</td>
			<td><?php echo esc_html( $count ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Render detected gateways section.
	 *
	 * @since 1.0.0
	 */
	private function render_detected_gateways() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return;
		}

		$gateways = $this->get_detected_gateways();

		if ( empty( $gateways ) ) {
			return;
		}

		?>
		<div class="card" style="max-width: 800px; margin-top: 20px;">
			<h2><?php esc_html_e( 'Detected Payment Gateways', 'wp_subscription' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Gateway', 'wp_subscription' ); ?></th>
						<th><?php esc_html_e( 'ID', 'wp_subscription' ); ?></th>
						<th><?php esc_html_e( 'Enabled', 'wp_subscription' ); ?></th>
						<th><?php esc_html_e( 'Subscriptions Support', 'wp_subscription' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $gateways as $gateway ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $gateway['title'] ); ?></strong></td>
							<td><code><?php echo esc_html( $gateway['id'] ); ?></code></td>
							<td>
								<?php if ( $gateway['enabled'] ) : ?>
									<span style="color: #46b450;">✓</span>
								<?php else : ?>
									<span style="color: #999;">✗</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $gateway['supports_subscriptions'] ) : ?>
									<span style="color: #46b450;">✓ <?php esc_html_e( 'Supported', 'wp_subscription' ); ?></span>
								<?php else : ?>
									<span style="color: #999;">✗ <?php esc_html_e( 'Not Supported', 'wp_subscription' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Get detected payment gateways.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	private function get_detected_gateways() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return array();
		}

		$available_gateways = WC()->payment_gateways()->payment_gateways();
		$gateways           = array();

		foreach ( $available_gateways as $gateway ) {
			$gateways[] = array(
				'id'                     => $gateway->id,
				'title'                  => $gateway->get_title(),
				'enabled'                => 'yes' === $gateway->enabled,
				'supports_subscriptions' => $gateway->supports( 'subscriptions' ),
			);
		}

		return $gateways;
	}

	/**
	 * Get count of gateways with subscription support.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	private function get_supported_gateways_count() {
		$gateways  = $this->get_detected_gateways();
		$total     = count( $gateways );
		$supported = count(
			array_filter(
				$gateways,
				function ( $gateway ) {
					return $gateway['supports_subscriptions'];
				}
			)
		);

		return $supported . '/' . $total;
	}

	/**
	 * Render conflict status section.
	 *
	 * @since 1.0.0
	 */
	private function render_conflict_status() {
		$wcs_active = class_exists( 'WC_Subscriptions' ) && ! defined( 'WPSUBSCRIPTION_COMPAT_WC_SUBSCRIPTION' );

		if ( ! $wcs_active ) {
			return;
		}

		?>
		<div class="card" style="max-width: 800px; margin-top: 20px; border-left: 4px solid #ffb900;">
			<h2><?php esc_html_e( 'Plugin Conflicts', 'wp_subscription' ); ?></h2>
			<p style="color: #646970;">
				<span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
				<?php esc_html_e( 'WooCommerce Subscriptions plugin is currently active.', 'wp_subscription' ); ?>
			</p>
			<p>
				<?php
				echo wp_kses_post(
					__(
						'For optimal performance and to avoid conflicts, we recommend deactivating WooCommerce Subscriptions as WPSubscription provides the same functionality.',
						'wp_subscription'
					)
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Manage Plugins', 'wp_subscription' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}

