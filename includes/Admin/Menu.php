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
		add_action( 'admin_menu', array( $this, 'reorder_submenu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_subscrpt_bulk_action', array( $this, 'handle_bulk_action_ajax' ) );
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets() {
		wp_enqueue_style(
			'wp-subscription-admin',
			SUBSCRPT_ASSETS . '/css/admin.css',
			array(),
			SUBSCRPT_VERSION
		);

		// Enqueue admin JavaScript for subscription list functionality
		wp_enqueue_script(
			'sdevs_subscription_admin',
			SUBSCRPT_ASSETS . '/js/admin.js',
			array( 'jquery' ),
			SUBSCRPT_VERSION,
			true
		);

		// Localize script for AJAX
		wp_localize_script(
			'sdevs_subscription_admin',
			'wp_subscription_ajax',
			array(
				'nonce'   => wp_create_nonce( 'subscrpt_bulk_action_nonce' ),
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
		$is_active = isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'wp-subscription' ) === 0;
		$icon_url  = $is_active
			? SUBSCRPT_ASSETS . '/images/icons/subscription-20.png'
			: SUBSCRPT_ASSETS . '/images/icons/subscription-20-gray.png';

		$pro_text  = __( 'WPSubscription Pro required', 'subscription' );
		$pro_badge = subscrpt_pro_activated() ? '' : ' <span title="' . $pro_text . '"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#ff6a34" style="vertical-align:middle;margin-bottom:2.2px;flex-shrink:0;" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M19 19h-14c-.5 0 -.9 -.3 -1 -.8l-2 -10c0 -.4 .1 -.8 .5 -1.1c.4 -.2 .8 -.2 1.1 0l4.1 3.3l3.4 -5.1c.4 -.6 1.3 -.6 1.7 0l3.4 5.1l4.1 -3.3c.3 -.3 .8 -.3 1.1 0c.4 .2 .5 .6 .5 1.1l-2 10c0 .5 -.5 .8 -1 .8z"/></svg></span>';

		// Main menu
		add_menu_page(
			__( 'WPSubscription', 'subscription' ),
			__( 'WPSubscription', 'subscription' ),
			'manage_options',
			$parent_slug,
			array( $this, 'render_subscriptions_page' ),
			$icon_url,
			40
		);

		// Subscriptions List
		add_submenu_page(
			$parent_slug,
			__( 'Subscriptions', 'subscription' ),
			__( 'Subscriptions', 'subscription' ),
			'manage_options',
			$parent_slug,
			array( $this, 'render_subscriptions_page' )
		);

		// Stats Overview
		add_submenu_page(
			$parent_slug,
			__( 'Reports', 'subscription' ),
			__( 'Reports', 'subscription' ) . $pro_badge,
			'manage_options',
			'wp-subscription-stats',
			array( $this, 'render_stats_page' )
		);

		// Subscription Health
		add_submenu_page(
			$parent_slug,
			__( 'Health', 'subscription' ),
			__( 'Health', 'subscription' ) . $pro_badge,
			'manage_options',
			'wp-subscription-health',
			array( $this, 'render_health_page' )
		);

		// Delivery Schedules
		add_submenu_page(
			$parent_slug,
			__( 'Delivery Schedules', 'subscription' ),
			__( 'Delivery Schedules', 'subscription' ) . $pro_badge,
			'manage_options',
			'wp-subscription-delivery',
			array( $this, 'render_delivery_page' )
		);

		// Help & Resources
		add_submenu_page(
			$parent_slug,
			__( 'Help & Resources', 'subscription' ),
			__( 'Help & Resources', 'subscription' ),
			'manage_options',
			'wp-subscription-support',
			array( $this, 'render_support_page' )
		);

		// Add WPSubscription link under WooCommerce menu
		add_submenu_page(
			'woocommerce',
			__( 'WPSubscription', 'subscription' ),
			__( 'WPSubscription', 'subscription' ),
			'manage_options',
			'wp-subscription',
			array( $this, 'render_subscriptions_page' )
		);
	}

	/**
	 * Reorder the WPSubscription submenu after all items are registered.
	 *
	 * Runs at admin_menu priority 999 so every plugin has already inserted
	 * its items. A filter lets the Pro plugin (or any extension) adjust the
	 * slug order before sorting is applied.
	 *
	 * @do_action subscrpt_submenu_order {string[]} $order Ordered list of submenu page slugs.
	 */
	public function reorder_submenu() {
		$parent = 'wp-subscription';

		global $submenu;

		if ( empty( $submenu[ $parent ] ) ) {
			return;
		}

		// slug => position. Use gaps of 10 so extensions can insert between items.
		// When pro is not active, pro-only pages (Reports, Delivery, Health) move
		// to the bottom so free pages stay prominent.
		if ( subscrpt_pro_activated() ) {
			$default_order = [
				'wp-subscription'              => 10, // Subscriptions
				'wp-subscription-stats'        => 20, // Reports
				'wp-subscription-delivery'     => 30, // Delivery (pro)
				'wp-subscription-settings'     => 40, // Settings
				'wp-subscription-health'       => 50, // Health
				'wp-subscription-integrations' => 60, // Integrations
				'wp-subscription-support'      => 70, // Help & Resources
				'wp-subscription-license'      => 80, // License (pro)
			];
		} else {
			$default_order = [
				'wp-subscription'              => 10, // Subscriptions
				'wp-subscription-settings'     => 20, // Settings
				'wp-subscription-integrations' => 30, // Integrations
				'wp-subscription-support'      => 40, // Help & Resources
				'wp-subscription-stats'        => 50, // Reports (pro preview)
				'wp-subscription-delivery'     => 60, // Delivery (pro preview)
				'wp-subscription-health'       => 70, // Health (pro preview)
				'wp-subscription-license'      => 80, // License (pro)
			];
		}

		/**
		 * Filter the WPSubscription submenu slug order.
		 *
		 * Each entry is a slug => integer position pair. Lower positions appear
		 * first. Use gaps of 10 between built-in positions so extensions can
		 * insert their own slugs between existing items without renumbering.
		 *
		 * Example (pro plugin adding Delivery at position 35):
		 *   add_filter( 'subscrpt_submenu_order', function( $order ) {
		 *       $order['wp-subscription-delivery'] = 35;
		 *       return $order;
		 *   } );
		 *
		 * @param array<string,int> $order Map of slug => position.
		 */
		$order = apply_filters( 'subscrpt_submenu_order', $default_order );

		// Sort by position value, preserving slug keys.
		asort( $order );

		// Index current items by slug for fast lookup.
		$indexed = [];
		foreach ( $submenu[ $parent ] as $item ) {
			$indexed[ $item[2] ] = $item;
		}

		// Build sorted list from the ordered slugs.
		$sorted = [];
		foreach ( array_keys( $order ) as $slug ) {
			if ( isset( $indexed[ $slug ] ) ) {
				$sorted[] = $indexed[ $slug ];
				unset( $indexed[ $slug ] );
			}
		}

		// Append any remaining items not covered by the order list.
		foreach ( $indexed as $item ) {
			$sorted[] = $item;
		}

		$submenu[ $parent ] = $sorted; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional reorder of submenu items.
	}

	/**
	 * Render the admin header
	 */
	public function render_admin_footer() {
		?>
		<div style="text-align:center;margin:38px 0 0 0;font-size:14px;color:#888;">
			Made with <span style="color:#e25555;font-size:1.1em;">♥</span> by the WPSubscription Team
			<div style="margin-top:6px;">
				<a href="https://wpsubscription.co/contact" target="_blank" style="color:#2563eb;text-decoration:none;">Support</a>
				&nbsp;/&nbsp;
				<a href="https://docs.converslabs.com/en" target="_blank" style="color:#2563eb;text-decoration:none;">Docs</a>
			</div>
		</div>
		<?php
	}
	/**
	 * Render the admin header.
	 *
	 * @param string $title    Page title shown on the left.
	 * @param string $subtitle Optional subtitle shown below the title.
	 */
	public function render_admin_header( string $title = '', string $subtitle = '' ) {
		$current = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'wp-subscription';

		// Kept for backward compatibility — extensions may hook here for side-effects.
		$menu_items = apply_filters( 'subscrpt_admin_header_menu_items', [], $current );
		unset( $menu_items ); // Nav no longer rendered in header; navigation is in WP sidebar.
		?>
		<div class="wp-subscription-admin-header">
			<div class="wp-subscription-admin-header-inner">
			<div class="wp-subscription-admin-header-left">
				<nav class="wp-subscription-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'subscription' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription' ) ); ?>" class="wp-subscription-breadcrumb-home" aria-label="<?php esc_attr_e( 'WPSubscription Home', 'subscription' ); ?>">
						<span class="dashicons dashicons-admin-home"></span>
					</a>
					<?php if ( $title ) : ?>
						<span class="wp-subscription-breadcrumb-sep" aria-hidden="true">/</span>
						<span class="wp-subscription-breadcrumb-current"><?php echo esc_html( $title ); ?></span>
					<?php endif; ?>
				</nav>
			</div>
			<div class="wp-subscription-admin-header-right">
				<?php if ( ! class_exists( 'Sdevs_Wc_Subscription_Pro' ) ) : ?>
					<a target="_blank" href="https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro" class="wp-subscription-upgrade-btn"><?php esc_html_e( 'Upgrade to Pro', 'subscription' ); ?></a>
				<?php endif; ?>
<img src="<?php echo esc_url( SUBSCRPT_ASSETS . '/images/logo-title.svg' ); ?>" alt="WPSubscription" class="wp-subscription-logo">
			</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Subscriptions page
	 */
	public function render_subscriptions_page() {
		$this->render_admin_header( __( 'Subscriptions', 'subscription' ), __( 'Manage your subscriptions', 'subscription' ) );

		// Handle filters
		$status      = isset( $_GET['subscrpt_status'] ) ? sanitize_text_field( wp_unslash( $_GET['subscrpt_status'] ) ) : '';
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$date_filter = isset( $_GET['date_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['date_filter'] ) ) : '';
		$per_page    = isset( $_GET['per_page'] ) ? max( 1, intval( $_GET['per_page'] ) ) : 20;
		$paged       = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

		// Handle form submissions (both filters and bulk actions)
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $request_method ) {
			// Verify nonce before processing any POST data.
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'subscrpt_list_action' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'subscription' ) );
			}
			// Handle bulk actions
			if ( isset( $_POST['bulk_action'] ) || isset( $_POST['bulk_action2'] ) ) {
				$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : sanitize_text_field( wp_unslash( $_POST['bulk_action2'] ?? '' ) );
				$action      = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : sanitize_text_field( wp_unslash( $_POST['action2'] ?? '' ) );

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
					$filter_params['subscrpt_status'] = sanitize_text_field( wp_unslash( $_POST['subscrpt_status'] ) );
				}
				if ( ! empty( $_POST['date_filter'] ) ) {
					$filter_params['date_filter'] = sanitize_text_field( wp_unslash( $_POST['date_filter'] ) );
				}
				if ( ! empty( $_POST['s'] ) ) {
					$filter_params['s'] = sanitize_text_field( wp_unslash( $_POST['s'] ) );
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
			$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
			$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

			// Clean trash action.
			if ( $action === 'clean_trash' ) {
				// Verify nonce for security.
				$nonce_action = 'wpsubs_action_clean_trash';
				if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed. Please try again.', 'subscription' ) . '</p></div>';
					wp_die();
				}

				// Clean all trash items.
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
			} else {
				// For other actions, verify nonce with subscription ID.
				$nonce_action = 'wpsubs_action_' . $sub_id;
				if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed. Please try again.', 'subscription' ) . '</p></div>';
					wp_die();
				}

				$redirect_url = admin_url( 'admin.php?page=wp-subscription' );

				switch ( $action ) {
					case 'duplicate':
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
						break;
					case 'trash':
						wp_trash_post( $sub_id );
						break;
					case 'restore':
						wp_untrash_post( $sub_id );
						break;
					case 'delete':
						wp_delete_post( $sub_id, true );
						$redirect_url = admin_url( 'admin.php?page=wp-subscription&subscrpt_status=trash' );
						break;
				}

				wp_safe_redirect( $redirect_url );
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
			Made with <span style="color:#e25555;font-size:1.1em;">♥</span> by the WPSubscription Team
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
		$this->render_admin_header( __( 'Reports', 'subscription' ), __( 'View your subscription analytics', 'subscription' ) );

		if ( ! subscrpt_pro_activated() ) {
			include 'views/reports-preview.php';
		} else {
			// Allow pro plugin to override the entire stats page content.
			do_action( 'subscrpt_render_stats_page' );
		}

		$this->render_admin_footer();
	}

	/**
	 * Render Health page
	 */
	public function render_health_page() {
		$this->render_admin_header( __( 'Health', 'subscription' ), __( 'Monitor your subscription health', 'subscription' ) );

		if ( ! subscrpt_pro_activated() ) {
			include 'views/health-preview.php';
		} else {
			// Allow pro plugin to render the full health page content.
			do_action( 'subscrpt_render_health_page' );
		}

		$this->render_admin_footer();
	}

	/**
	 * Render Delivery Schedules page.
	 * When pro is active, fires subscrpt_render_delivery_page for pro to handle.
	 */
	public function render_delivery_page() {
		$this->render_admin_header( __( 'Delivery Schedules', 'subscription' ), __( 'Track and manage subscription delivery schedules.', 'subscription' ) );

		if ( ! subscrpt_pro_activated() ) {
			include 'views/delivery-preview.php';
		} else {
			// Allow pro plugin to render the full delivery page content.
			do_action( 'subscrpt_render_delivery_page' );
		}

		$this->render_admin_footer();
	}

	/**
	 * Render Support page
	 */
	public function render_support_page() {
		$this->render_admin_header( __( 'Help & Resources', 'subscription' ), __( 'Documentation, community links, and ways to get help with WPSubscription.', 'subscription' ) );
		include 'views/support.php';
		$this->render_admin_footer();
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
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'subscrpt_bulk_action_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'subscription' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'subscription' ) ) );
		}

		// Get action and subscription IDs
		$bulk_action      = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$subscription_ids = isset( $_POST['subscription_ids'] ) ? array_map( 'intval', $_POST['subscription_ids'] ) : array();

		if ( empty( $subscription_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No subscriptions selected.', 'subscription' ) ) );
		}

		$processed_count = 0;
		$errors          = array();

		foreach ( $subscription_ids as $subscription_id ) {
			$post = get_post( $subscription_id );

			if ( ! $post || $post->post_type !== 'subscrpt_order' ) {
				// translators: Subscription ID.
				$errors[] = sprintf( __( 'Subscription #%d not found.', 'subscription' ), $subscription_id );
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
								__( 'Failed to move subscription #%d to trash.', 'subscription' ),
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
								__( 'Failed to restore subscription #%d.', 'subscription' ),
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
								__( 'Failed to delete subscription #%d.', 'subscription' ),
								$subscription_id
							);
						}
						break;

					default:
						$errors[] = sprintf(
							// translators: Bulk action.
							__( 'Unknown action: %s', 'subscription' ),
							$bulk_action
						);
						break;
				}
			} catch ( Exception $e ) {
				$errors[] = sprintf(
					// translators: Subscription ID, Error message.
					__( 'Error processing subscription #%1$d: %2$s', 'subscription' ),
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
						_n( '%d subscription moved to trash.', '%d subscriptions moved to trash.', $processed_count, 'subscription' ),
						$processed_count
					);
					break;
				case 'restore':
					$message = sprintf(
						// translators: Number of subscriptions.
						_n( '%d subscription restored.', '%d subscriptions restored.', $processed_count, 'subscription' ),
						$processed_count
					);
					break;
				case 'delete':
					$message = sprintf(
						// translators: Number of subscriptions.
						_n( '%d subscription permanently deleted.', '%d subscriptions permanently deleted.', $processed_count, 'subscription' ),
						$processed_count
					);
					break;
			}
		}

		if ( ! empty( $errors ) ) {
			$message .= ' ' . __( 'Some errors occurred:', 'subscription' ) . ' ' . implode( ', ', $errors );
		}

		if ( $processed_count > 0 ) {
			wp_send_json_success( array( 'message' => $message ) );
		} else {
			wp_send_json_error( array( 'message' => $message ? $message : __( 'No subscriptions were processed.', 'subscription' ) ) );
		}
	}
}
