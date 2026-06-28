<?php

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Illuminate\Action;
use SpringDevs\Subscription\Illuminate\Helper;

// HPOS: This file is compatible with WooCommerce High-Performance Order Storage (HPOS).
// All WooCommerce order data is accessed via WooCommerce CRUD methods (wc_get_order, etc.).
// All direct post meta access is for subscription data only, not WooCommerce order data.
// If you add new order data access, use WooCommerce CRUD for HPOS compatibility.

/**
 * Subscriptions class
 *
 * @package SpringDevs\Subscription\Admin
 */
class Subscriptions {


	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ) );
		add_filter( 'manage_subscrpt_order_posts_columns', array( $this, 'add_custom_columns' ) );
		add_action( 'manage_subscrpt_order_posts_custom_column', array( $this, 'add_custom_columns_data' ), 10, 2 );
		add_action( 'load-post.php', array( $this, 'redirect_legacy_edit_screen' ) );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'remove_order_meta' ), 10, 1 );
		add_filter( 'bulk_actions-edit-subscrpt_order', array( $this, 'remove_bulk_actions' ) );
		add_action( 'restrict_manage_posts', array( $this, 'add_subscription_filter_select' ) );
		add_action( 'admin_menu', array( $this, 'add_overview_submenu' ), 40 );
	}

	/**
	 * Redirect the legacy CPT edit screen to the standalone details page.
	 *
	 * The subscription details UI now lives at
	 * admin.php?page=wp-subscription-details. Anyone landing on the old
	 * post.php edit screen for a subscrpt_order is sent there.
	 *
	 * @return void
	 */
	public function redirect_legacy_edit_screen() {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id && 'subscrpt_order' === get_post_type( $post_id ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wp-subscription-details&id=' . $post_id ) );
			exit;
		}
	}

	/**
	 * Remove 'Edit` and 'Trash' from bulk actions.
	 *
	 * @param array $actions Action list.
	 *
	 * @return array
	 */
	public function remove_bulk_actions( $actions ) {
		unset( $actions['edit'] );
		unset( $actions['trash'] );

		return $actions;
	}

	/**
	 * Hide order meta key from custom fields.
	 *
	 * @param array $formatted_meta Data with key-value.
	 *
	 * @return array
	 */
	public function remove_order_meta( $formatted_meta ): array {
		$temp_metas = array();
		foreach ( $formatted_meta as $key => $meta ) {
			if ( isset( $meta->key ) && '_renew_subscrpt' !== $meta->key ) {
				$temp_metas[ $key ] = $meta;
			}
		}

		return $temp_metas;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'subscrpt_admin_css' );
		wp_enqueue_style( 'subscrpt_status_css' );
	}

	/**
	 * Remove default post actions.
	 *
	 * @param array $actions Actions.
	 *
	 * @return array
	 */
	public function post_row_actions( $actions ) {
		global $current_screen;
		if ( 'subscrpt_order' !== $current_screen->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'] );
		unset( $actions['view'] );
		unset( $actions['trash'] );
		unset( $actions['edit'] );

		return $actions;
	}

	/**
	 * Register custom columns.
	 *
	 * @param array $columns Columns.
	 *
	 * @return array
	 */
	public function add_custom_columns( $columns ) {
		$columns['subscrpt_start_date'] = __( 'Start Date', 'subscription' );
		$columns['subscrpt_customer']   = __( 'Customer', 'subscription' );
		$columns['subscrpt_next_date']  = __( 'Next Date', 'subscription' );
		$columns['subscrpt_status']     = __( 'Status', 'subscription' );
		unset( $columns['date'] );
		unset( $columns['cb'] );

		return $columns;
	}

	/**
	 * Display column data.
	 *
	 * @param string $column Column.
	 * @param int    $post_id Post Id.
	 *
	 * @return void
	 */
	public function add_custom_columns_data( $column, $post_id ) {
		// HPOS: Safe. Only retrieves WooCommerce order via CRUD, and subscription meta via post meta.
		$order_id = get_post_meta( $post_id, '_subscrpt_order_id', true ); // HPOS: Only subscription meta, not order meta.
		$order    = wc_get_order( $order_id ); // HPOS: Safe, uses WooCommerce CRUD.
		if ( $order ) {
			if ( 'subscrpt_start_date' === $column ) {
				$start_date = get_post_meta( $post_id, '_subscrpt_start_date', true );
				echo ! empty( $start_date ) ? esc_html( wp_date( 'F d, Y', $start_date ) ) : '-';
			} elseif ( 'subscrpt_customer' === $column ) {
				?>
				<?php echo wp_kses_post( $order->get_formatted_billing_full_name() ); ?>
				<br />
				<a href="mailto:<?php echo wp_kses_post( $order->get_billing_email() ); ?>"><?php echo wp_kses_post( $order->get_billing_email() ); ?></a>
				<br />
				<?php if ( ! empty( $order->get_billing_phone() ) ) : ?>
					Phone : <a
						href="tel:<?php echo esc_js( $order->get_billing_phone() ); ?>"><?php echo esc_js( $order->get_billing_phone() ); ?></a>
				<?php endif; ?>
				<?php
			} elseif ( 'subscrpt_next_date' === $column ) {
				$next_date = get_post_meta( $post_id, '_subscrpt_next_date', true );
				echo ! empty( $next_date ) ? esc_html( wp_date( 'F d, Y', $next_date ) ) : '-';
			} elseif ( 'subscrpt_status' === $column ) {
				$status_obj = get_post_status_object( get_post_status( $post_id ) );
				?>
				<span
					class="subscrpt-legacy-status subscrpt-legacy-status--<?php echo esc_html( $status_obj->name ); ?>"><?php echo esc_html( $status_obj->label ); ?></span>
				<?php
			}
		} else {
			esc_html_e( 'Order not found !!', 'subscription' );
		}
	}

	/**
	 * Build the subscription info rows.
	 *
	 * Assembles the label/value rows describing a subscription (product, cost,
	 * dates, status, payment method, addresses, trial, etc.) and applies the
	 * `subscrpt_admin_info_rows` filter so extensions (e.g. the Pro plugin) can
	 * add or reorder rows. Returns null when the related order is missing.
	 *
	 * @param int $subscription_id Subscription (subscrpt_order) post ID.
	 *
	 * @return array<string,array{label:string,value:string}>|null
	 */
	public static function get_info_rows( $subscription_id ) {
		$order_id         = get_post_meta( $subscription_id, '_subscrpt_order_id', true );
		$order_item_id    = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
		$trial            = get_post_meta( $subscription_id, '_subscrpt_trial', true );
		$start_date       = get_post_meta( $subscription_id, '_subscrpt_start_date', true );
		$next_date        = get_post_meta( $subscription_id, '_subscrpt_next_date', true );
		$trial_start_date = get_post_meta( $subscription_id, '_subscrpt_trial_started', true );
		$trial_end_date   = get_post_meta( $subscription_id, '_subscrpt_trial_ended', true );
		$trial_mode       = get_post_meta( $subscription_id, '_subscrpt_trial_mode', true );
		$order            = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}
		$order_item = $order->get_item( $order_item_id );

		$product_name = $order_item->get_name();
		$product_link = get_the_permalink( $order_item->get_product_id() );

		// Get payment information
		$product_id    = $order_item->get_product_id();
		$max_payments  = subscrpt_get_max_payments( $subscription_id ) ? subscrpt_get_max_payments( $subscription_id ) : 0;
		$payments_made = subscrpt_count_payments_made( $subscription_id );

		$rows = array(
			'product'  => array(
				'label' => __( 'Product', 'subscription' ),
				'value' => '<a href="' . esc_html( $product_link ) . '" target="_blank">' . esc_html( $product_name ) . '</a>',
			),
			'cost'     => array(
				'label' => __( 'Cost', 'subscription' ),
				'value' => Helper::format_price_with_order_item( get_post_meta( $subscription_id, '_subscrpt_price', true ), $order_item->get_id() ),
			),
			'quantity' => array(
				'label' => __( 'Qty', 'subscription' ),
				'value' => "x{$order_item->get_quantity()}",
			),
		);

		// Add payment information if max_payments is set and not unlimited
		if ( ! empty( $max_payments ) && $max_payments > 0 ) {
			$rows['total_payments'] = array(
				'label' => __( 'Total Payments', 'subscription' ),
				'value' => esc_html( $payments_made ) . ' / ' . esc_html( $max_payments ),
			);
		}

		$rows += array(
			'start_date'       => array(
				'label' => __( 'Started date', 'subscription' ),
				'value' => ! empty( $start_date ) ? wp_date( 'F d, Y', $trial && $trial_start_date ? $trial_start_date : $start_date ) : '-',
			),
			'next_date'        => array(
				'label' => __( 'Payment due date', 'subscription' ),
				'value' => ! empty( $next_date ) ? wp_date( 'F d, Y', $trial && $trial_end_date && 'on' === $trial_mode ? $trial_end_date : ( $next_date ?? '-' ) ) : '-',
			),
			'status'           => array(
				'label' => __( 'Status', 'subscription' ),
				'value' => '<span class="subscrpt-legacy-status subscrpt-legacy-status--' . get_post_status( $subscription_id ) . '">' . get_post_status_object( get_post_status( $subscription_id ) )->label . '</span>',
			),
			'payment_method'   => array(
				'label' => __( 'Payment Method', 'subscription' ),
				'value' => empty( $order->get_payment_method_title() ) ? '-' : $order->get_payment_method_title(),
			),
			'billing_address'  => array(
				'label' => __( 'Billing', 'subscription' ),
				'value' => $order->get_formatted_billing_address() ? $order->get_formatted_billing_address() : __( 'No billing address set.', 'subscription' ),
			),
			'shipping_address' => array(
				'label' => __( 'Shipping', 'subscription' ),
				'value' => $order->get_formatted_shipping_address() ? $order->get_formatted_shipping_address() : __( 'No shipping address set.', 'subscription' ),
			),
		);
		if ( $trial ) {
			$rows = array_slice( $rows, 0, 3, true ) + array(
				'trial'        => array(
					'label' => __( 'Trial', 'subscription' ),
					'value' => $trial,
				),
				'trial_period' => array(
					'label' => __( 'Trial Period', 'subscription' ),
					'value' => ( $trial_start_date && $trial_end_date ? ' [ ' . wp_date( 'F d, Y', $trial_start_date ) . ' - ' . wp_date( 'F d, Y', $trial_end_date ) . ' ] ' : __( 'Trial isn\'t activated yet! ', 'subscription' ) ),
				),
			) + array_slice( $rows, 3, count( $rows ) - 1, true );
		}

		if ( class_exists( 'WC_Stripe' ) && 'stripe' === $order->get_payment_method() ) {
			$is_auto_renew = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
			$new_rows      = array();
			foreach ( $rows as $key => $value ) {
				$new_rows[ $key ] = $value;
				if ( 'payment_method' === $key ) {
					$new_rows['stripe_auto_renewal'] = array(
						'label' => __( 'Stripe Auto Renewal', 'subscription' ),
						'value' => '0' !== $is_auto_renew ? 'On' : 'Off',
					);
				}
			}

			$rows = $new_rows;
		}

		$rows = apply_filters( 'subscrpt_admin_info_rows', $rows, $subscription_id, $order );

		return $rows;
	}

	/**
	 * Apply a status change to a subscription.
	 *
	 * Updates the subscription post status, fires the admin status-change email
	 * notification, runs the subscription status action, and completes the
	 * related order when the subscription becomes active. Called from the
	 * standalone subscription details page.
	 *
	 * @param int    $post_id Subscription (subscrpt_order) post ID.
	 * @param string $action  Target status slug.
	 *
	 * @return void
	 */
	public static function process_status_change( $post_id, $action ) {
		$old_status = get_post_status( $post_id );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $action,
			)
		);

		if ( $old_status !== $action ) {
			$old_status_object = get_post_status_object( $old_status );
			$new_status_object = get_post_status_object( $action );
			WC()->mailer();
			do_action( 'subscrpt_status_changed_admin_email_notification', $post_id, $old_status_object->label, $new_status_object->label );
		}

		$order_id = get_post_meta( $post_id, '_subscrpt_order_id', true );
		if ( 'active' === $action ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->update_status( 'completed' );
			}
			Action::status( $action, $post_id );
		} else {
			Action::status( $action, $post_id );
		}
	}

	public function add_subscription_filter_select() {
		// Implementation of add_subscription_filter_select method
	}

	public function add_overview_submenu() {
		// Remove and re-add submenu to ensure Overview is first
		remove_submenu_page( 'edit.php?post_type=subscrpt_order', 'edit.php?post_type=subscrpt_order' );
		add_submenu_page(
			'edit.php?post_type=subscrpt_order',
			__( 'Overview', 'subscription' ),
			__( 'Overview', 'subscription' ),
			'manage_options',
			'subscription_overview',
			array( $this, 'render_overview_page' ),
			0
		);
		add_submenu_page(
			'edit.php?post_type=subscrpt_order',
			__( 'All Subscriptions', 'subscription' ),
			__( 'All Subscriptions', 'subscription' ),
			'manage_options',
			'edit.php?post_type=subscrpt_order',
			'',
			1
		);
		if ( ! class_exists( 'Sdevs_Wc_Subscription_Pro' ) ) {
			add_submenu_page(
				'edit.php?post_type=subscrpt_order',
				__( 'Go Pro', 'subscription' ),
				__( 'Go Pro', 'subscription' ),
				'manage_options',
				'wp_subscription_go_pro',
				array( $this, 'render_go_pro_page' ),
				99
			);
		}
	}

	public function render_overview_page() {
		?>
		<div class="wrap wpsubscription-overview" style="max-width:1100px;margin:40px auto 0 auto;">
			<div class="wpsubscription-overview-card" style="background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);padding:40px 32px 32px 32px;">
				<div class="wpsubscription-overview-top" style="display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start;margin-bottom:40px;">
					<div class="wpsubscription-overview-info" style="display:flex;flex-direction:column;gap:18px;">
						<h1 style="margin-bottom:0.2em;"><?php esc_html_e( 'WPSubscription Overview', 'subscription' ); ?></h1>
						<p class="product-desc" style="font-size:1.15em;line-height:1.6;max-width:500px;">
							<?php esc_html_e( 'WPSubscription is the most seamless and reliable WooCommerce subscription solution for store owners looking to grow recurring revenue. Easily manage recurring payments, automate renewals, and delight your customers with flexible plans.', 'subscription' ); ?>
						</p>
						<div class="wpsubscription-links" style="display:flex;gap:12px;flex-wrap:wrap;">
							<a href="https://docs.converslabs.com/en" target="_blank" class="button button-secondary"><?php esc_html_e( 'Documentation', 'subscription' ); ?></a>
							<a href="https://wpsubscription.co/" target="_blank" class="button button-secondary"><?php esc_html_e( 'Website', 'subscription' ); ?></a>
						</div>
					</div>
					<div class="promo-video" style="text-align:center;">
						<iframe width="420" height="236" src="https://www.youtube.com/embed/2e6o5p0M7L4" title="WPSubscription Promo" frameborder="0" allowfullscreen style="max-width:100%;border-radius:8px;"></iframe>
					</div>
				</div>

				<div class="wpsubscription-what-section" style="margin-bottom:40px;">
					<h2><?php esc_html_e( 'What does Subscriptions for WooCommerce do?', 'subscription' ); ?></h2>
					<p style="font-size:1.08em;max-width:900px;line-height:1.7;">
						<?php esc_html_e( 'Subscriptions for WooCommerce enables you to create and manage recurring payment products and services with ease. Automate renewals, offer flexible billing schedules, and provide your customers with a seamless subscription experience. Whether you sell digital content, physical goods, or memberships, WPSubscription gives you the tools to grow your recurring revenue.', 'subscription' ); ?>
					</p>
				</div>

				<h2 style="margin-top:2em;"><?php esc_html_e( 'Highlights', 'subscription' ); ?></h2>
				<div class="wpsubscription-features-grid">
					<div class="feature-box"><span class="dashicons dashicons-admin-generic"></span><h3>Easy Setup</h3><p>Get started in minutes with our intuitive onboarding wizard.</p></div>
					<div class="feature-box"><span class="dashicons dashicons-money"></span><h3>Multiple Gateways</h3><p>Support for Stripe, PayPal, and Paddle out of the box.</p></div>
					<div class="feature-box"><span class="dashicons dashicons-schedule"></span><h3>Flexible Plans</h3><p>Create and manage various subscription types and delivery schedules.</p></div>
					<div class="feature-box"><span class="dashicons dashicons-chart-line"></span><h3>Comprehensive Dashboard</h3><p>Monitor and manage all subscriptions in one place.</p></div>
				</div>
			</div>
		</div>
		<style>
		.wpsubscription-overview .promo-video { text-align:center; }
		.wpsubscription-features-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
			gap: 24px;
			margin-top: 32px;
		}
		.feature-box {
			background: #fff;
			border-radius: 10px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.06);
			padding: 24px 18px 18px 18px;
			text-align: center;
			transition: transform 0.2s, box-shadow 0.2s;
			will-change: transform;
		}
		.feature-box:hover {
			transform: translateY(-6px) scale(1.03);
			box-shadow: 0 6px 24px rgba(0,0,0,0.10);
		}
		.feature-box .dashicons {
			font-size: 2.2em;
			color: #7f54b3;
			margin-bottom: 10px;
			display: block;
		}
		.feature-box h3 {
			margin: 12px 0 8px 0;
			font-size: 1.15em;
		}
		.feature-box p {
			color: #555;
			font-size: 1em;
			margin: 0;
		}
		</style>
		<?php
	}

	public function render_go_pro_page() {
		if ( class_exists( 'Sdevs_Wc_Subscription_Pro' ) ) {
			echo '<div class="notice notice-info" style="margin:40px auto;max-width:700px;text-align:center;font-size:1.2em;">Pro is already active.</div>';
			return;
		}
		?>
		<div class="wrap wpsubscription-go-pro" style="max-width:900px;margin:40px auto 0 auto;">
			<div class="wpsubscription-go-pro-card" style="background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.06);padding:40px 32px 32px 32px;">
				<h1 style="margin-bottom:0.5em;"><?php esc_html_e( 'Upgrade to WPSubscription Pro', 'subscription' ); ?></h1>
				<p style="font-size:1.12em;max-width:600px;line-height:1.6;">
					<?php esc_html_e( 'Unlock the full power of subscriptions for WooCommerce. Get advanced features, priority support, and more ways to grow your recurring revenue.', 'subscription' ); ?>
				</p>
				<table class="wpsubscription-compare-table" style="width:100%;margin:32px 0 40px 0;border-collapse:separate;border-spacing:0;box-shadow:0 1px 4px rgba(0,0,0,0.04);background:#fafbfc;border-radius:8px;overflow:hidden;">
					<thead>
						<tr style="background:#f8f9fa;">
							<th style="padding:18px 12px 18px 24px;font-size:1.08em;text-align:left;border:none;"></th>
							<th style="padding:18px 12px;font-size:1.08em;text-align:center;border:none;">Free</th>
							<th style="padding:18px 12px;font-size:1.08em;text-align:center;border:none;color:#7f54b3;">Pro</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td style="padding:16px 12px 16px 24px;">Simple subscription products</td>
							<td style="text-align:center;">✔️</td>
							<td style="text-align:center;">✔️</td>
						</tr>
						<tr style="background:#f6f7f7;">
							<td style="padding:16px 12px 16px 24px;">Automated recurring billing</td>
							<td style="text-align:center;">✔️</td>
							<td style="text-align:center;">✔️</td>
						</tr>
						<tr>
							<td style="padding:16px 12px 16px 24px;">Multiple payment gateways</td>
							<td style="text-align:center;">✔️</td>
							<td style="text-align:center;">✔️</td>
						</tr>
						<tr style="background:#f6f7f7;">
							<td style="padding:16px 12px 16px 24px;">Customer self-service portal</td>
							<td style="text-align:center;">✔️</td>
							<td style="text-align:center;">✔️</td>
						</tr>
						<tr>
							<td style="padding:16px 12px 16px 24px;">Priority support</td>
							<td style="text-align:center;">—</td>
							<td style="text-align:center;">✔️</td>
						</tr>
						<tr style="background:#f6f7f7;">
							<td style="padding:16px 12px 16px 24px;">Advanced reporting & analytics</td>
							<td style="text-align:center;">—</td>
							<td style="text-align:center;">✔️</td>
						</tr>
						<tr>
							<td style="padding:16px 12px 16px 24px;">Variable product support</td>
							<td style="text-align:center;">—</td>
							<td style="text-align:center;font-weight:600;color:#43a047;">✔️</td>
						</tr>
					</tbody>
				</table>
				<div style="text-align:center;margin-top:24px;">
					<a href="https://wpsubscription.co/" target="_blank" class="button button-primary button-hero" style="font-size:1.2em;padding:16px 40px 16px 40px;background:#7f54b3;border:none;box-shadow:0 2px 8px rgba(127,84,179,0.10);">
						<?php esc_html_e( 'Upgrade to Pro', 'subscription' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}
}
