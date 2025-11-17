<?php

namespace SpringDevs\Subscription\Admin;

/**
 * Menu class
 *
 * @package SpringDevs\Subscription\Admin
 */
class Menu {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_wp_subscription_bulk_action', array( $this, 'handle_bulk_action_ajax' ) );
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets() {
		wp_enqueue_style(
			'wp-subscription-admin',
			WP_SUBSCRIPTION_ASSETS . '/css/admin.css',
			array(),
			WP_SUBSCRIPTION_VERSION
		);

		// Enqueue admin JavaScript for subscription list functionality
		wp_enqueue_script(
			'sdevs_subscription_admin',
			WP_SUBSCRIPTION_ASSETS . '/js/admin.js',
			array( 'jquery' ),
			WP_SUBSCRIPTION_VERSION,
			true
		);

		// Localize script for AJAX
		wp_localize_script(
			'sdevs_subscription_admin',
			'wp_subscription_ajax',
			array(
				'nonce'   => wp_create_nonce( 'wp_subscription_bulk_action_nonce' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Create Subscriptions Menu.
	 */
	public function create_admin_menu() {
		$parent_slug = 'wp-subscription';
		// Determine if the menu is active
		$is_active = isset( $_GET['page'] ) && strpos( $_GET['page'], 'wp-subscription' ) === 0;
		$icon_url  = $is_active
			? WP_SUBSCRIPTION_ASSETS . '/images/icons/subscription-20.png'
			: WP_SUBSCRIPTION_ASSETS . '/images/icons/subscription-20-gray.png';
		// Main menu
		add_menu_page(
			__( 'WP Subscription', 'wp_subscription' ),
			__( 'WP Subscription', 'wp_subscription' ),
			'manage_woocommerce',
			$parent_slug,
			array( $this, 'render_subscriptions_page' ),
			$icon_url,
			40
		);

		// Subscriptions List
		add_submenu_page(
			$parent_slug,
			__( 'Subscriptions', 'wp_subscription' ),
			__( 'Subscriptions', 'wp_subscription' ),
			'manage_woocommerce',
			$parent_slug,
			array( $this, 'render_subscriptions_page' )
		);

		// Stats Overview
		add_submenu_page(
			$parent_slug,
			__( 'Reports', 'wp_subscription' ),
			__( 'Reports', 'wp_subscription' ),
			'manage_woocommerce',
			'wp-subscription-stats',
			array( $this, 'render_stats_page' )
		);

		/*
		! For god's sake, check existing code before implementing!
		// Settings
		add_submenu_page(
			$parent_slug,
			__( 'Settings', 'wp_subscription' ),
			__( 'Settings', 'wp_subscription' ),
			'manage_woocommerce',
			'wp-subscription-settings',
			array( $this, 'render_settings_page' )
		);
		*/

		// Support
		add_submenu_page(
			$parent_slug,
			__( 'Support', 'wp_subscription' ),
			__( 'Support', 'wp_subscription' ),
			'manage_woocommerce',
			'wp-subscription-support',
			array( $this, 'render_support_page' )
		);

		// Add WP Subscription link under WooCommerce menu
		add_submenu_page(
			'woocommerce',
			__( 'WP Subscription', 'wp_subscription' ),
			__( 'WP Subscription', 'wp_subscription' ),
			'manage_woocommerce',
			'wp-subscription',
			array( $this, 'render_subscriptions_page' )
		);
	}
	/**
	 * Render the admin header
	 */
	public function render_admin_footer() {
		?>
		<div style="text-align:center;margin:38px 0 0 0;font-size:14px;color:#888;">
			Made with <span style="color:#e25555;font-size:1.1em;">♥</span> by the WP Subscription Team
			<div style="margin-top:6px;">
				<a href="https://wpsubscription.co/contact" target="_blank" style="color:#2563eb;text-decoration:none;">Support</a>
				&nbsp;/&nbsp;
				<a href="https://docs.converslabs.com/en" target="_blank" style="color:#2563eb;text-decoration:none;">Docs</a>
			</div>
		</div>
		<?php
	}
	/**
	 * Render the admin header
	 */
	public function render_admin_header() {
		// Get current page slug
		$current    = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : 'wp-subscription';
		$menu_items = [
			[
				'slug'  => 'wp-subscription',
				'label' => __( 'Subscriptions', 'wp_subscription' ),
				'url'   => admin_url( 'admin.php?page=wp-subscription' ),
			],
			[
				'slug'  => 'wp-subscription-stats',
				'label' => __( 'Reports', 'wp_subscription' ),
				'url'   => admin_url( 'admin.php?page=wp-subscription-stats' ),
			],
			[
				'slug'  => 'wp-subscription-settings',
				'label' => __( 'Settings', 'wp_subscription' ),
				'url'   => admin_url( 'admin.php?page=wp-subscription-settings' ),
			],
		];
		// Allow pro plugin to inject menu items
		$menu_items = apply_filters( 'wp_subscription_admin_header_menu_items', $menu_items, $current );
		$menu_items = array_merge(
			$menu_items,
			[
				[
					'slug'  => 'wp-subscription-support',
					'label' => __( 'Support', 'wp_subscription' ),
					'url'   => admin_url( 'admin.php?page=wp-subscription-support' ),
				],
			]
		);
		?>
		<div class="wp-subscription-admin-header">
			<div style="width:1240px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;">
				<div class="wp-subscription-admin-header-left" style="display:flex;align-items:center;gap:14px;">
					<img style="height:30px;" src="<?php echo esc_url( WP_SUBSCRIPTION_ASSETS . '/images/logo-title.svg' ); ?>" alt="WP Subscription" class="wp-subscription-logo">
					<nav class="wp-subscription-admin-header-menu">
						<?php foreach ( $menu_items as $item ) : ?>
							<a href="<?php echo esc_url( $item['url'] ); ?>" class="<?php echo ( $current === $item['slug'] ) ? 'current' : ''; ?>">
								<?php echo esc_html( $item['label'] ); ?>
							</a>
						<?php endforeach; ?>
					</nav>
				</div>
				<div class="wp-subscription-admin-header-right">
					<?php if ( ! class_exists( 'Sdevs_Wc_Subscription_Pro' ) ) : ?>
					<a target="_blank" href="https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro" class="wp-subscription-upgrade-btn"><?php esc_html_e( 'Upgrade to Pro', 'wp_subscription' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Subscriptions page
	 */
	public function render_subscriptions_page() {
		$this->render_admin_header();

		// Handle filters
		$status      = isset( $_GET['subscrpt_status'] ) ? sanitize_text_field( $_GET['subscrpt_status'] ) : '';
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$date_filter = isset( $_GET['date_filter'] ) ? sanitize_text_field( $_GET['date_filter'] ) : '';
		$per_page    = isset( $_GET['per_page'] ) ? max( 1, intval( $_GET['per_page'] ) ) : 20;
		$paged       = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

		// Handle form submissions (both filters and bulk actions)
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			// Handle bulk actions
			if ( isset( $_POST['bulk_action'] ) || isset( $_POST['bulk_action2'] ) ) {
				$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : sanitize_text_field( $_POST['bulk_action2'] );
				$action      = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : sanitize_text_field( $_POST['action2'] );

				if ( $bulk_action && $action && $action !== '-1' && isset( $_POST['subscription_ids'] ) && is_array( $_POST['subscription_ids'] ) ) {
					$subscription_ids = array_map( 'intval', $_POST['subscription_ids'] );

					if ( $action === 'trash' ) {
						foreach ( $subscription_ids as $sub_id ) {
							wp_trash_post( $sub_id );
						}
					} elseif ( $action === 'restore' ) {
						foreach ( $subscription_ids as $sub_id ) {
							wp_untrash_post( $sub_id );
						}
					} elseif ( $action === 'delete' ) {
						foreach ( $subscription_ids as $sub_id ) {
							wp_delete_post( $sub_id, true );
						}
					}

					wp_safe_redirect( admin_url( 'admin.php?page=wp-subscription' ) );
					exit;
				}
			}

			// Handle filter form submission
			if ( isset( $_POST['filter_action'] ) ) {
				$filter_params = array();

				if ( ! empty( $_POST['subscrpt_status'] ) ) {
					$filter_params['subscrpt_status'] = sanitize_text_field( $_POST['subscrpt_status'] );
				}
				if ( ! empty( $_POST['date_filter'] ) ) {
					$filter_params['date_filter'] = sanitize_text_field( $_POST['date_filter'] );
				}
				if ( ! empty( $_POST['s'] ) ) {
					$filter_params['s'] = sanitize_text_field( $_POST['s'] );
				}
				if ( ! empty( $_POST['per_page'] ) ) {
					$filter_params['per_page'] = intval( $_POST['per_page'] );
				}

				$redirect_url = add_query_arg( $filter_params, admin_url( 'admin.php?page=wp-subscription' ) );
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}

		// Handle individual actions
		if ( isset( $_GET['action'] ) && ! empty( $_GET['sub_id'] ) ) {
			$sub_id = intval( $_GET['sub_id'] );
			$action = sanitize_text_field( $_GET['action'] );

			if ( $action === 'duplicate' ) {
				$post = get_post( $sub_id );
				if ( $post && $post->post_type === 'subscrpt_order' ) {
					$new_post = [
						'post_title'   => $post->post_title . ' (Copy)',
						'post_content' => $post->post_content,
						'post_status'  => 'draft',
						'post_type'    => 'subscrpt_order',
					];
					$new_id   = wp_insert_post( $new_post );
					if ( $new_id ) {
						$meta = get_post_meta( $sub_id );
						foreach ( $meta as $key => $values ) {
							foreach ( $values as $value ) {
								add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
							}
						}
					}
				}
				wp_safe_redirect( admin_url( 'admin.php?page=wp-subscription' ) );
				exit;
			} elseif ( $action === 'trash' ) {
				// Move to trash
				wp_trash_post( $sub_id );
				wp_safe_redirect( admin_url( 'admin.php?page=wp-subscription' ) );
				exit;
			} elseif ( $action === 'restore' ) {
				// Restore from trash
				wp_untrash_post( $sub_id );
				wp_safe_redirect( admin_url( 'admin.php?page=wp-subscription' ) );
				exit;
			} elseif ( $action === 'delete' ) {
				// Permanent delete
				wp_delete_post( $sub_id, true );
				wp_safe_redirect( admin_url( 'admin.php?page=wp-subscription' ) );
				exit;
			} elseif ( $action === 'clean_trash' ) {
				// Clean all trash items
				$trash_posts = get_posts(
					[
						'post_type'   => 'subscrpt_order',
						'post_status' => 'trash',
						'numberposts' => -1,
						'fields'      => 'ids',
					]
				);

				foreach ( $trash_posts as $trash_id ) {
					wp_delete_post( $trash_id, true );
				}

				wp_safe_redirect( admin_url( 'admin.php?page=wp-subscription&subscrpt_status=trash' ) );
				exit;
			}
		}

		$args = [
			'post_type'      => 'subscrpt_order',
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $status ) {
			$args['post_status'] = $status;
		}
		// Search only by subscription ID
		if ( $search !== '' ) {
			if ( is_numeric( $search ) ) {
				$args['p'] = intval( $search );
			} else {
				// If not numeric, return no results
				$args['post__in'] = array( 0 );
			}
		}
		// Dynamic date filter (YYYY-MM)
		if ( $date_filter && preg_match( '/^\d{4}-\d{2}$/', $date_filter ) ) {
			$year                 = substr( $date_filter, 0, 4 );
			$month                = substr( $date_filter, 5, 2 );
			$args['date_query'][] = [
				'year'  => intval( $year ),
				'month' => intval( $month ),
			];
		}

		$query         = new \WP_Query( $args );
		$subscriptions = $query->posts;
		$total         = $query->found_posts;
		$max_num_pages = $query->max_num_pages;

		// Get all possible statuses for filter dropdown
		$all_statuses = get_post_stati( [ 'show_in_admin_all_list' => true ], 'objects' );

		include __DIR__ . '/views/subscription-list.php';
		?>
		<div style="text-align:center;margin:38px 0 0 0;font-size:14px;color:#888;">
			Made with <span style="color:#e25555;font-size:1.1em;">♥</span> by the WP Subscription Team
			<div style="margin-top:6px;">
				<a href="https://wpsubscription.co/contact" target="_blank" style="color:#2563eb;text-decoration:none;">Support</a>
				&nbsp;/&nbsp;
				<a href="https://docs.converslabs.com/en" target="_blank" style="color:#2563eb;text-decoration:none;">Docs</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Stats page
	 */
	public function render_stats_page() {
		$this->render_admin_header();

		if ( ! class_exists( 'Sdevs_Wc_Subscription_Pro' ) ) {
			?>
			<div class="wp-subscription-admin-content" style="max-width:1240px;margin:32px auto 0 auto">
				<div class="wp-subscription-hero-upgrade" style="margin-bottom:18px;">
					<div class="wp-subscription-hero-content">
						<span class="wp-subscription-hero-icon">✨</span>
						<span class="wp-subscription-hero-title">
							Unlock advanced features, priority support,<br>
							and more subscription control and reporting.
						</span>
					</div>
					<a href="https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro"
					target="_blank"
					class="wp-subscription-hero-btn">
						UPGRADE TO PRO
					</a>
				</div>
			</div>
			<?php
		} else {
			// Allow pro plugin to override the entire stats page content
			do_action( 'wp_subscription_render_stats_page' );
		}

		$this->render_admin_footer();

		return;
	}

	/**
	 * Render Support page
	 */
	public function render_support_page() {
		$this->render_admin_header();
		?>
		<div class="wp-subscription-admin-content" style="max-width:1240px;margin:32px auto 0 auto">
			<?php if ( ! class_exists( 'Sdevs_Wc_Subscription_Pro' ) ) : ?>
			<!-- HERO VARIANT 1: Emoji -->
			<div class="wp-subscription-hero-upgrade" style="margin-bottom:18px;">
				<div class="wp-subscription-hero-content">
					<span class="wp-subscription-hero-icon">✨</span>
					<span class="wp-subscription-hero-title">
						Unlock advanced features, priority support,<br>
						and more subscription control and reporting.
					</span>
				</div>
				<a href="https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro"
					target="_blank"
					class="wp-subscription-hero-btn">
					UPGRADE TO PRO
				</a>
			</div>
			<?php endif; ?>
			
			<!-- Product Overview & Video -->
			<div class="wp-subscription-admin-box" style="margin-bottom:24px;display:flex;gap:32px;align-items:flex-start;flex-wrap:wrap;">
				<div style="flex:1;">
					<h3>Product Overview</h3>
					<p style="font-size:14px;line-height:1.7;margin:0 0 10px 0;">
						WPSubscription helps you to sell products and services on a recurring basis using your existing WooCommerce store. Whether you're offering digital licenses, physical product boxes, or ongoing service plans, this plugin provides the tools to build and manage subscription models.
					</p>
					<ul style="font-size:14px;line-height:1.6;margin:0 0 0 18px;padding:0;list-style:disc;">
						<li>Create simple or variable subscription products</li>
						<li>Set billing intervals (daily, weekly, monthly, yearly)</li>
						<li>Offer free trials and sign-up fees</li>
						<li>Allow customers to cancel or renew subscriptions manually</li>
						<li>View and manage subscriptions from the admin dashboard</li>
						<li>Customize subscription behavior and role assignment</li>
						<li>Integrate with payment gateways that support recurring billing (Stripe, PayPal)</li>
					</ul>
				</div>
				<div style="flex:1;">
					<div style="background:#f4f7fa;border-radius:8px;padding:10px 10px 6px 10px;box-shadow:0 2px 8px #e0e7ef;">
						<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:6px;text-align: center;">
							<iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/o8usgcZp1nY?si=iPb5z7bvcy0rIyOQ" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
ß                        </div>
						<!-- <div style="text-align:center;font-size:13px;color:#888;margin-top:4px;">Watch: Quick Product Tour</div> -->
					</div>
				</div>
			</div>

			<!-- PRO Features List -->
			<div class="wp-subscription-admin-box wp-subscription-pro-features" style="margin-bottom:32px;">
				<div style="font-size:1.3em;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:10px;">
					<svg width="28" height="28" fill="none" viewBox="0 0 28 28"><circle cx="14" cy="14" r="14" fill="#2196f3"/><path d="M9 14.5l3 3 7-7" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					<span>WP Subscription PRO Features</span>
				</div>
				<ul class="wp-subscription-pro-feature-list" style="list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;">
					<li style="background:#f4f7fa;border-radius:8px;padding:18px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px #e0e7ef;">
						<span style="font-size:1.5em;color:#2196f3;">🔀</span>
						<span><b>Variable Product</b><br><span style="color:#555;font-size:0.98em;">Offer flexible subscription options for variable products.</span></span>
					</li>
					<li style="background:#f4f7fa;border-radius:8px;padding:18px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px #e0e7ef;">
						<span style="font-size:1.5em;color:#2196f3;">🚚</span>
						<span><b>Delivery Schedule</b><br><span style="color:#555;font-size:0.98em;">Set custom delivery intervals for each subscription.</span></span>
					</li>
					<li style="background:#f4f7fa;border-radius:8px;padding:18px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px #e0e7ef;">
						<span style="font-size:1.5em;color:#2196f3;">📜</span>
						<span><b>Subscription History</b><br><span style="color:#555;font-size:0.98em;">Track all changes and events for every subscription.</span></span>
					</li>
					<li style="background:#f4f7fa;border-radius:8px;padding:18px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px #e0e7ef;">
						<span style="font-size:1.5em;color:#2196f3;">⏳</span>
						<span><b>More Subscription Durations</b><br><span style="color:#555;font-size:0.98em;">Offer more flexible and custom subscription periods.</span></span>
					</li>
					<li style="background:#f4f7fa;border-radius:8px;padding:18px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px #e0e7ef;">
						<span style="font-size:1.5em;color:#2196f3;">💶</span>
						<span><b>Sign Up Fee</b><br><span style="color:#555;font-size:0.98em;">Charge a one-time sign up fee for new subscribers.</span></span>
					</li>
					<li style="background:#f4f7fa;border-radius:8px;padding:18px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px #e0e7ef;">
						<span style="font-size:1.5em;color:#2196f3;">⏩</span>
						<span><b>Early Renewal</b><br><span style="color:#555;font-size:0.98em;">Allow customers to renew their subscription before expiry.</span></span>
					</li>
					<li style="background:#f4f7fa;border-radius:8px;padding:18px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px #e0e7ef;">
						<span style="font-size:1.5em;color:#2196f3;">💳</span>
						<span><b>Renewal Price</b><br><span style="color:#555;font-size:0.98em;">Set a different price for subscription renewals.</span></span>
					</li>
				</ul>
			</div>

			<!-- Support Resources: 2 rows, 2 columns, each in its own box -->
			<div class="wp-subscription-support-resources" style="display:grid;grid-template-columns:repeat(2,1fr);gap:24px;margin-bottom:24px;">
				<div class="wp-subscription-admin-box">
					<h3>Documentation</h3>
					<p style="font-size:14px;margin:0 0 8px 0;">Read our <a href="https://docs.converslabs.com/en\" target=\"_blank\" style=\"color:#2271b1;\">comprehensive docs</a> for setup, migration, and advanced usage.</p>
					<a href="https://docs.converslabs.com/en" target="_blank" class="button button-small" style="font-size:13px;padding:5px 14px;">View Docs</a>
				</div>
				<div class="wp-subscription-admin-box">
					<h3>Facing An Issue?</h3>
					<p style="font-size:14px;margin:0 0 8px 0;">If you have a problem, <a href="https://wpsubscription.co/contact\" target=\"_blank\" style=\"color:#d93025;\">open a support ticket</a> or check our FAQ.</p>
					<a href="https://wpsubscription.co/contact" target="_blank" class="button button-small" style="font-size:13px;padding:5px 14px;">Get Support</a>
				</div>
				<div class="wp-subscription-admin-box">
					<h3>Request a Feature</h3>
					<p style="font-size:14px;margin:0 0 8px 0;">Have an idea? <a href="https://wpsubscription.co/contact\" target=\"_blank\" style=\"color:#2271b1;\">Request a feature</a> or vote on others.</p>
					<a href="https://wpsubscription.co/contact" target="_blank" class="button button-small" style="font-size:13px;padding:5px 14px;">Request Feature</a>
				</div>
				<div class="wp-subscription-admin-box">
					<h3>Show Your Love</h3>
					<p style="font-size:14px;margin:0 0 8px 0;">Enjoying WP Subscription? <a href="https://wordpress.org/support/plugin/subscription/reviews/\" target=\"_blank\" style=\"color:#f59e42;\">Leave us a review</a> or share your experience!</p>
					<a href="https://wordpress.org/support/plugin/subscription/reviews/" target="_blank" class="button button-small" style="font-size:13px;padding:5px 14px;">Leave a Review</a>
				</div>
			</div>
		</div>
		<div style="text-align:center;margin:38px 0 0 0;font-size:14px;color:#888;">
			Made with <span style="color:#e25555;font-size:1.1em;">♥</span> by the WP Subscription Team
			<div style="margin-top:6px;">
				<a href="https://wpsubscription.co/contact" target="_blank" style="color:#2563eb;text-decoration:none;">Support</a>
				&nbsp;/&nbsp;
				<a href="https://docs.converslabs.com/en" target="_blank" style="color:#2563eb;text-decoration:none;">Docs</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the legacy subscriptions list page (WP_List_Table based)
	 */
	public function render_legacy_subscriptions_page() {
		// No longer needed, as the menu now links directly to the post type list.
	}

	/**
	 * Handle bulk action AJAX
	 */
	public function handle_bulk_action_ajax() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_subscription_bulk_action_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp_subscription' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wp_subscription' ) ) );
		}

		// Get action and subscription IDs
		$bulk_action      = sanitize_text_field( $_POST['bulk_action'] );
		$subscription_ids = isset( $_POST['subscription_ids'] ) ? array_map( 'intval', $_POST['subscription_ids'] ) : array();

		if ( empty( $subscription_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No subscriptions selected.', 'wp_subscription' ) ) );
		}

		$processed_count = 0;
		$errors          = array();

		foreach ( $subscription_ids as $subscription_id ) {
			$post = get_post( $subscription_id );

			if ( ! $post || $post->post_type !== 'subscrpt_order' ) {
				// translators: Subscription ID.
				$errors[] = sprintf( __( 'Subscription #%d not found.', 'wp_subscription' ), $subscription_id );
				continue;
			}

			try {
				switch ( $bulk_action ) {
					case 'trash':
						if ( wp_trash_post( $subscription_id ) ) {
							++$processed_count;
						} else {
							$errors[] = sprintf(
								// translators: Subscription ID.
								__( 'Failed to move subscription #%d to trash.', 'wp_subscription' ),
								$subscription_id
							);
						}
						break;

					case 'restore':
						if ( wp_untrash_post( $subscription_id ) ) {
							++$processed_count;
						} else {
							$errors[] = sprintf(
								// translators: Subscription ID.
								__( 'Failed to restore subscription #%d.', 'wp_subscription' ),
								$subscription_id
							);
						}
						break;

					case 'delete':
						if ( wp_delete_post( $subscription_id, true ) ) {
							++$processed_count;
						} else {
							$errors[] = sprintf(
								// translators: Subscription ID.
								__( 'Failed to delete subscription #%d.', 'wp_subscription' ),
								$subscription_id
							);
						}
						break;

					default:
						$errors[] = sprintf(
							// translators: Bulk action.
							__( 'Unknown action: %s', 'wp_subscription' ),
							$bulk_action
						);
						break;
				}
			} catch ( Exception $e ) {
				$errors[] = sprintf(
					// translators: Subscription ID, Error message.
					__( 'Error processing subscription #%1$d: %2$s', 'wp_subscription' ),
					$subscription_id,
					$e->getMessage()
				);
			}
		}

		// Prepare response message
		$message = '';
		if ( $processed_count > 0 ) {
			switch ( $bulk_action ) {
				case 'trash':
					$message = sprintf(
						// translators: Number of subscriptions.
						_n( '%d subscription moved to trash.', '%d subscriptions moved to trash.', $processed_count, 'wp_subscription' ),
						$processed_count
					);
					break;
				case 'restore':
					$message = sprintf(
						// translators: Number of subscriptions.
						_n( '%d subscription restored.', '%d subscriptions restored.', $processed_count, 'wp_subscription' ),
						$processed_count
					);
					break;
				case 'delete':
					$message = sprintf(
						// translators: Number of subscriptions.
						_n( '%d subscription permanently deleted.', '%d subscriptions permanently deleted.', $processed_count, 'wp_subscription' ),
						$processed_count
					);
					break;
			}
		}

		if ( ! empty( $errors ) ) {
			$message .= ' ' . __( 'Some errors occurred:', 'wp_subscription' ) . ' ' . implode( ', ', $errors );
		}

		if ( $processed_count > 0 ) {
			wp_send_json_success( array( 'message' => $message ) );
		} else {
			wp_send_json_error( array( 'message' => $message ?: __( 'No subscriptions were processed.', 'wp_subscription' ) ) );
		}
	}
}
