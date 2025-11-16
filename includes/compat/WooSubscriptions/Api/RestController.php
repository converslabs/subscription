<?php
/**
 * Plugin Name - WooCommerce Subscriptions REST API Controller
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compat\WooSubscriptions\Api;

use SpringDevs\Subscription\Compat\WooSubscriptions\Services\SubscriptionLocator;
use SpringDevs\Subscription\Compat\WooSubscriptions\Data\StatusMapper;
use SpringDevs\Subscription\Compat\WooSubscriptions\Data\SyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API controller for WooCommerce Subscriptions compatibility.
 *
 * Provides REST endpoints matching WooCommerce Subscriptions API structure.
 *
 * @since 1.0.0
 */
class RestController {

	/**
	 * Namespace for REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const NAMESPACE = 'wc/v3';

	/**
	 * Route base.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const REST_BASE = 'subscriptions';

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var RestController
	 */
	private static $instance;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return RestController
	 */
	public static function instance() {
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		// List subscriptions.
		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Get single subscription.
		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the subscription.', 'wp_subscription' ),
							'type'        => 'integer',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'default'     => false,
							'description' => __( 'Whether to bypass trash and force deletion.', 'wp_subscription' ),
							'type'        => 'boolean',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Get subscription statuses.
		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/statuses',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_statuses' ),
					'permission_callback' => '__return_true',
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Get subscription orders.
		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>[\d]+)/orders',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_subscription_orders' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Check if a given request has access to read subscriptions.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'read_private_shop_orders' ) ) {
			// Allow customers to view their own subscriptions.
			if ( ! is_user_logged_in() ) {
				return new \WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'wp_subscription' ), array( 'status' => rest_authorization_required_code() ) );
			}
		}

		return true;
	}

	/**
	 * Check if a given request has access to read a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		$subscription_id = (int) $request['id'];
		$subscription    = $this->get_subscription_object( $subscription_id );

		if ( ! $subscription ) {
			return new \WP_Error( 'woocommerce_rest_subscription_invalid_id', __( 'Invalid subscription ID.', 'wp_subscription' ), array( 'status' => 404 ) );
		}

		// Check permissions: admin can view all, customers can view their own.
		$customer_id = (int) get_post_meta( $subscription_id, '_customer_user', true );
		$is_admin    = current_user_can( 'manage_woocommerce' ) || current_user_can( 'read_private_shop_orders' );
		$is_owner    = is_user_logged_in() && get_current_user_id() === $customer_id;

		if ( ! $is_admin && ! $is_owner ) {
			return new \WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot view this resource.', 'wp_subscription' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to create subscriptions.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'woocommerce_rest_cannot_create', __( 'Sorry, you are not allowed to create resources.', 'wp_subscription' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to update a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		$subscription_id = (int) $request['id'];
		$subscription    = $this->get_subscription_object( $subscription_id );

		if ( ! $subscription ) {
			return new \WP_Error( 'woocommerce_rest_subscription_invalid_id', __( 'Invalid subscription ID.', 'wp_subscription' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'woocommerce_rest_cannot_edit', __( 'Sorry, you are not allowed to edit this resource.', 'wp_subscription' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to delete a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		$subscription_id = (int) $request['id'];
		$subscription    = $this->get_subscription_object( $subscription_id );

		if ( ! $subscription ) {
			return new \WP_Error( 'woocommerce_rest_subscription_invalid_id', __( 'Invalid subscription ID.', 'wp_subscription' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'woocommerce_rest_cannot_delete', __( 'Sorry, you are not allowed to delete this resource.', 'wp_subscription' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Get a collection of subscriptions.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'status'     => isset( $request['status'] ) ? $request['status'] : 'any',
			'limit'      => isset( $request['per_page'] ) ? (int) $request['per_page'] : 10,
			'product_id' => isset( $request['product'] ) ? (int) $request['product'] : null,
		);

		// If customer parameter is set, use it; otherwise, if user is not admin, show only their subscriptions.
		$customer_id = isset( $request['customer'] ) ? (int) $request['customer'] : 0;

		if ( ! $customer_id && ! current_user_can( 'manage_woocommerce' ) && is_user_logged_in() ) {
			$customer_id = get_current_user_id();
		}

		$locator = new SubscriptionLocator();
		$subscriptions = $locator->get_subscriptions_by_user( $customer_id, $args );

		$data = array();

		foreach ( $subscriptions as $subscription ) {
			$data[] = $this->prepare_item_for_response( $subscription, $request );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Get a single subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function get_item( $request ) {
		$subscription_id = (int) $request['id'];
		$subscription    = $this->get_subscription_object( $subscription_id );

		if ( ! $subscription ) {
			return new \WP_Error( 'woocommerce_rest_subscription_invalid_id', __( 'Invalid subscription ID.', 'wp_subscription' ), array( 'status' => 404 ) );
		}

		$response = $this->prepare_item_for_response( $subscription, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Create a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function create_item( $request ) {
		// Note: Creating subscriptions via REST API requires WPSubscription's internal logic.
		// This is a compatibility endpoint that may have limitations.
		return new \WP_Error( 'woocommerce_rest_not_implemented', __( 'Subscription creation via REST API is not yet fully implemented. Please use WPSubscription\'s native subscription creation methods.', 'wp_subscription' ), array( 'status' => 501 ) );
	}

	/**
	 * Update a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function update_item( $request ) {
		$subscription_id = (int) $request['id'];
		$subscription    = $this->get_subscription_object( $subscription_id );

		if ( ! $subscription ) {
			return new \WP_Error( 'woocommerce_rest_subscription_invalid_id', __( 'Invalid subscription ID.', 'wp_subscription' ), array( 'status' => 404 ) );
		}

		// Handle status updates.
		if ( isset( $request['status'] ) ) {
			$new_status = sanitize_text_field( $request['status'] );
			$old_status = $subscription->get_status();

			if ( $new_status !== $old_status ) {
				/**
				 * Trigger subscription status change.
				 *
				 * @since 1.0.0
				 *
				 * @param \WC_Subscription $subscription Subscription instance.
				 * @param string           $new_status   New status.
				 * @param string           $old_status   Old status.
				 */
				do_action( 'woocommerce_subscription_status_changed', $subscription, $new_status, $old_status );
			}
		}

		$response = $this->prepare_item_for_response( $subscription, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Delete a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function delete_item( $request ) {
		$subscription_id = (int) $request['id'];
		$force           = isset( $request['force'] ) ? (bool) $request['force'] : false;

		$subscription = $this->get_subscription_object( $subscription_id );

		if ( ! $subscription ) {
			return new \WP_Error( 'woocommerce_rest_subscription_invalid_id', __( 'Invalid subscription ID.', 'wp_subscription' ), array( 'status' => 404 ) );
		}

		// Get WPSubscription ID.
		$wps_id = $this->get_wps_subscription_id( $subscription_id );

		if ( $wps_id ) {
			if ( $force ) {
				wp_delete_post( $wps_id, true );
			} else {
				wp_trash_post( $wps_id );
			}
		}

		$response = new \WP_REST_Response();
		$response->set_data(
			array(
				'deleted'  => true,
				'previous' => $this->prepare_item_for_response( $subscription, $request ),
			)
		);

		return $response;
	}

	/**
	 * Get subscription statuses.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_statuses( $request ) {
		$statuses = array(
			'active'         => array(
				'name'   => __( 'Active', 'wp_subscription' ),
				'public' => true,
			),
			'on-hold'        => array(
				'name'   => __( 'On Hold', 'wp_subscription' ),
				'public' => true,
			),
			'cancelled'      => array(
				'name'   => __( 'Cancelled', 'wp_subscription' ),
				'public' => true,
			),
			'expired'        => array(
				'name'   => __( 'Expired', 'wp_subscription' ),
				'public' => true,
			),
			'pending'        => array(
				'name'   => __( 'Pending', 'wp_subscription' ),
				'public' => true,
			),
			'pending-cancel' => array(
				'name'   => __( 'Pending Cancellation', 'wp_subscription' ),
				'public' => true,
			),
		);

		return rest_ensure_response( $statuses );
	}

	/**
	 * Get orders for a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function get_subscription_orders( $request ) {
		$subscription_id = (int) $request['id'];
		$subscription    = $this->get_subscription_object( $subscription_id );

		if ( ! $subscription ) {
			return new \WP_Error( 'woocommerce_rest_subscription_invalid_id', __( 'Invalid subscription ID.', 'wp_subscription' ), array( 'status' => 404 ) );
		}

		// Get WPSubscription ID.
		$wps_id = $this->get_wps_subscription_id( $subscription_id );

		if ( ! $wps_id ) {
			return new \WP_Error( 'woocommerce_rest_subscription_invalid_id', __( 'Invalid subscription ID.', 'wp_subscription' ), array( 'status' => 404 ) );
		}

		// Get related orders.
		global $wpdb;

		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT order_id FROM {$table_name} WHERE subscription_id = %d ORDER BY id DESC",
				$wps_id
			)
		);

		$orders = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order ) {
				$orders[] = array(
					'id'     => $order->get_id(),
					'number' => $order->get_order_number(),
					'status' => $order->get_status(),
					'total'  => $order->get_total(),
					'date'   => $order->get_date_created()->date( 'c' ),
				);
			}
		}

		return rest_ensure_response( $orders );
	}

	/**
	 * Prepare subscription data for response.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription  $subscription Subscription object.
	 * @param \WP_REST_Request $request      Request object.
	 *
	 * @return array
	 */
	protected function prepare_item_for_response( $subscription, $request ) {
		$data = array(
			'id'                   => $subscription->get_id(),
			'status'               => $subscription->get_status(),
			'currency'             => get_woocommerce_currency(),
			'date_created'         => $subscription->get_date( 'start' ) ? gmdate( 'c', $subscription->get_date( 'start' ) ) : '',
			'date_modified'        => current_time( 'c' ),
			'customer_id'          => $subscription->get_customer_id(),
			'billing_period'       => $subscription->get_billing_period(),
			'billing_interval'    => $subscription->get_billing_interval(),
			'next_payment_date'    => $subscription->get_date( 'next_payment' ) ? gmdate( 'c', $subscription->get_date( 'next_payment' ) ) : '',
			'trial_end_date'       => $subscription->get_date( 'trial_end' ) ? gmdate( 'c', $subscription->get_date( 'trial_end' ) ) : '',
			'end_date'             => $subscription->get_date( 'end' ) ? gmdate( 'c', $subscription->get_date( 'end' ) ) : '',
			'payment_method'       => $subscription->get_payment_method(),
			'payment_method_title' => $subscription->get_payment_method_title(),
		);

		return $data;
	}

	/**
	 * Get subscription object.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription ID (may be WCS or WPSubscription).
	 *
	 * @return \WC_Subscription|null
	 */
	protected function get_subscription_object( $subscription_id ) {
		// Resolve to WPSubscription ID if needed.
		$wps_id = $this->get_wps_subscription_id( $subscription_id );

		if ( ! $wps_id ) {
			return null;
		}

		// Get the subscription post.
		$post = get_post( $wps_id );

		if ( ! $post || 'subscrpt_order' !== $post->post_type ) {
			return null;
		}

		// Get customer ID.
		$customer_id = (int) $post->post_author;

		// Get subscription via SubscriptionLocator.
		$locator = new SubscriptionLocator();
		$subscriptions = $locator->get_subscriptions_by_user( $customer_id, array( 'status' => 'any' ) );

		// Find the subscription by ID.
		if ( isset( $subscriptions[ $wps_id ] ) ) {
			return $subscriptions[ $wps_id ];
		}

		return null;
	}

	/**
	 * Get WPSubscription ID from subscription ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return int|null
	 */
	protected function get_wps_subscription_id( $subscription_id ) {
		// If it's already a WPSubscription ID, return it.
		if ( 'subscrpt_order' === get_post_type( $subscription_id ) ) {
			return $subscription_id;
		}

		// If it's a shop_subscription ID, find the WPSubscription ID.
		if ( 'shop_subscription' === get_post_type( $subscription_id ) ) {
			$wps_id = (int) get_post_meta( $subscription_id, SyncService::WCS_MAP_META_KEY, true );

			if ( $wps_id && 'subscrpt_order' === get_post_type( $wps_id ) ) {
				return $wps_id;
			}
		}

		return null;
	}

	/**
	 * Get collection parameters.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'context'  => $this->get_context_param( array( 'default' => 'view' ) ),
			'page'     => array(
				'description' => __( 'Current page of the collection.', 'wp_subscription' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page' => array(
				'description' => __( 'Maximum number of items to be returned in result set.', 'wp_subscription' ),
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'status'   => array(
				'description' => __( 'Limit result set to subscriptions assigned a specific status.', 'wp_subscription' ),
				'type'        => 'string',
				'enum'        => array( 'any', 'active', 'on-hold', 'cancelled', 'expired', 'pending', 'pending-cancel' ),
				'default'     => 'any',
			),
			'customer' => array(
				'description' => __( 'Limit result set to subscriptions assigned a specific customer ID.', 'wp_subscription' ),
				'type'        => 'integer',
			),
			'product'  => array(
				'description' => __( 'Limit result set to subscriptions assigned a specific product ID.', 'wp_subscription' ),
				'type'        => 'integer',
			),
		);
	}

	/**
	 * Get context parameter.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Arguments.
	 *
	 * @return array
	 */
	protected function get_context_param( $args = array() ) {
		$param_details = array(
			'description'       => __( 'Scope under which the request is made; determines fields present in response.', 'wp_subscription' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$defaults = array(
			'type'        => 'string',
			'default'     => 'view',
			'enum'        => array( 'view', 'edit' ),
			'description' => __( 'Scope under which the request is made; determines fields present in response.', 'wp_subscription' ),
		);

		return array_merge( $defaults, $param_details, $args );
	}

	/**
	 * Get endpoint args for item schema.
	 *
	 * @since 1.0.0
	 *
	 * @param string $method HTTP method.
	 *
	 * @return array
	 */
	public function get_endpoint_args_for_item_schema( $method = \WP_REST_Server::CREATABLE ) {
		$args = array();

		if ( \WP_REST_Server::CREATABLE === $method || \WP_REST_Server::EDITABLE === $method ) {
			$args['status'] = array(
				'description' => __( 'Subscription status.', 'wp_subscription' ),
				'type'        => 'string',
				'enum'        => array( 'active', 'on-hold', 'cancelled', 'expired', 'pending', 'pending-cancel' ),
			);
		}

		return $args;
	}

	/**
	 * Get public item schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_public_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'subscription',
			'type'       => 'object',
			'properties' => array(
				'id'                   => array(
					'description' => __( 'Unique identifier for the subscription.', 'wp_subscription' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'status'               => array(
					'description' => __( 'Subscription status.', 'wp_subscription' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'customer_id'          => array(
					'description' => __( 'Customer ID.', 'wp_subscription' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'billing_period'       => array(
					'description' => __( 'Billing period.', 'wp_subscription' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'billing_interval'    => array(
					'description' => __( 'Billing interval.', 'wp_subscription' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'next_payment_date'    => array(
					'description' => __( 'Next payment date.', 'wp_subscription' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);
	}
}

