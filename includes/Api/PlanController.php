<?php
/**
 * Subscription Plans REST controller (free base).
 *
 * CRUD for plan groups, plan terms, and product relations, plus a product
 * picker for the admin Plans manager. Namespace `wpsubscription/v1`, gated by
 * `manage_woocommerce`. This is the free plugin's first REST surface; Pro adds
 * the extra routes (`/plans/detach`, `/plans/migrate`, plan-side bulk attach).
 *
 * Admin-only: the storefront and checkout never call these routes - they read
 * plan data directly via PlanRepository. A REST fault cannot break checkout.
 *
 * @package SpringDevs\Subscription\Api
 */

namespace SpringDevs\Subscription\Api;

use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

/**
 * Plan REST controller.
 */
class PlanController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NS = 'wpsubscription/v1';

	/**
	 * Register all plan routes.
	 *
	 * Called from within `rest_api_init` (see API::register_api), so it does not
	 * hook the action itself.
	 *
	 * @return void
	 */
	public function register_routes() {
		$perm = array( $this, 'check_permission' );

		register_rest_route(
			self::NS,
			'/plans/groups',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_groups' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_group' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/groups/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_group' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_group' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_group' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/terms',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_term' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/terms/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_term' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_term' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_term' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/relations',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_relation' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/relations/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_relation' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_relation' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/plans/products',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_products' ),
					'permission_callback' => $perm,
				),
			)
		);
	}

	/**
	 * Permission check: WooCommerce manager.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You are not allowed to manage subscription plans.', 'subscription' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Read request params, preferring a JSON body over query / form params.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return array
	 */
	protected function read_params( WP_REST_Request $request ) {
		$json = $request->get_json_params();

		return ! empty( $json ) ? $json : $request->get_params();
	}

	/* ---- Groups ---- */

	/**
	 * GET /plans/groups - list every plan group with its term count.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_groups() {
		$groups = PlanRepository::get_groups();

		foreach ( $groups as &$group ) {
			$plans               = PlanRepository::get_plans( $group['id'] );
			$group['term_count'] = count( $plans );
			$group['type_key']   = PlanRepository::type_to_string( $group['type'] );
		}
		unset( $group );

		return rest_ensure_response( $groups );
	}

	/**
	 * POST /plans/groups - create a plan group.
	 *
	 * Free is Recurring-only: a non-Recurring type is rejected unless Pro is
	 * active (Pro unlocks Subscribe & Save / Installments).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_group( WP_REST_Request $request ) {
		$params = $this->read_params( $request );

		$guard = $this->guard_recurring_only( $params );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$id = PlanRepository::insert_group( $params );

		if ( ! $id ) {
			return new WP_Error( 'subscrpt_plan_create_failed', __( 'Could not create the plan group.', 'subscription' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( PlanRepository::get_group_tree( $id ) );
	}

	/**
	 * GET /plans/groups/{id} - full group tree.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_group( WP_REST_Request $request ) {
		$group = PlanRepository::get_group_tree( (int) $request['id'] );

		if ( ! $group ) {
			return $this->not_found();
		}

		return rest_ensure_response( $group );
	}

	/**
	 * PUT /plans/groups/{id} - update a plan group.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_group( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PlanRepository::get_group( $id ) ) {
			return $this->not_found();
		}

		$params = $this->read_params( $request );

		$guard = $this->guard_recurring_only( $params );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		PlanRepository::update_group( $id, $params );

		return rest_ensure_response( PlanRepository::get_group_tree( $id ) );
	}

	/**
	 * DELETE /plans/groups/{id} - delete group + cascade.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function delete_group( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PlanRepository::get_group( $id ) ) {
			return $this->not_found();
		}

		PlanRepository::delete_group( $id );

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/* ---- Terms ---- */

	/**
	 * POST /plans/terms - create a plan term under a group.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_term( WP_REST_Request $request ) {
		$params = $this->read_params( $request );

		if ( empty( $params['plan_group_id'] ) || ! PlanRepository::get_group( $params['plan_group_id'] ) ) {
			return new WP_Error( 'subscrpt_plan_group_missing', __( 'A valid plan_group_id is required.', 'subscription' ), array( 'status' => 400 ) );
		}

		$id = PlanRepository::insert_plan( $params );

		if ( ! $id ) {
			return new WP_Error( 'subscrpt_term_create_failed', __( 'Could not create the plan term.', 'subscription' ), array( 'status' => 500 ) );
		}

		// Link products already in the group to the new selling plan so it shows
		// on the Products tab for them (inheriting their existing price).
		PlanRepository::backfill_term_relations( (int) $params['plan_group_id'], $id );

		return rest_ensure_response( PlanRepository::get_plan( $id ) );
	}

	/**
	 * GET /plans/terms/{id} - single plan term (for edit prefill).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_term( WP_REST_Request $request ) {
		$term = PlanRepository::get_plan( (int) $request['id'] );

		if ( ! $term ) {
			return $this->not_found();
		}

		return rest_ensure_response( $term );
	}

	/**
	 * PUT /plans/terms/{id} - update a plan term.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_term( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PlanRepository::get_plan( $id ) ) {
			return $this->not_found();
		}

		PlanRepository::update_plan( $id, $this->read_params( $request ) );

		return rest_ensure_response( PlanRepository::get_plan( $id ) );
	}

	/**
	 * DELETE /plans/terms/{id} - delete a plan term + its relations.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function delete_term( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PlanRepository::get_plan( $id ) ) {
			return $this->not_found();
		}

		PlanRepository::delete_plan( $id );

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/* ---- Relations ---- */

	/**
	 * POST /plans/relations - attach a product to a plan term.
	 *
	 * Free is simple-product only: a variation relation (`vid` != 0) is rejected
	 * unless Pro is active (Pro unlocks per-variation attach).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_relation( WP_REST_Request $request ) {
		$params = $this->read_params( $request );

		if ( empty( $params['plan_id'] ) || ! PlanRepository::get_plan( $params['plan_id'] ) ) {
			return new WP_Error( 'subscrpt_plan_missing', __( 'A valid plan_id is required.', 'subscription' ), array( 'status' => 400 ) );
		}

		if ( empty( $params['oid'] ) ) {
			return new WP_Error( 'subscrpt_oid_missing', __( 'A product or term id (oid) is required.', 'subscription' ), array( 'status' => 400 ) );
		}

		if ( ! isset( $params['type'] ) ) {
			$params['type'] = PlanRepository::REL_PRODUCT;
		}

		$guard = $this->guard_simple_only( $params );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$id = PlanRepository::insert_relation( $params );

		if ( ! $id ) {
			return new WP_Error( 'subscrpt_relation_create_failed', __( 'Could not attach the product.', 'subscription' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( PlanRepository::get_relation( $id ) );
	}

	/**
	 * PUT /plans/relations/{id} - update a relation (price / exclude).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_relation( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PlanRepository::get_relation( $id ) ) {
			return $this->not_found();
		}

		PlanRepository::update_relation( $id, $this->read_params( $request ) );

		return rest_ensure_response( PlanRepository::get_relation( $id ) );
	}

	/**
	 * DELETE /plans/relations/{id} - detach a product from a plan term.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function delete_relation( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PlanRepository::get_relation( $id ) ) {
			return $this->not_found();
		}

		PlanRepository::delete_relation( $id );

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/* ---- Product picker ---- */

	/**
	 * GET /plans/products - search WC products for the connect picker.
	 *
	 * Free is simple-product only: the picker returns simple products.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function search_products( WP_REST_Request $request ) {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );

		$args = array(
			'status'  => 'publish',
			'type'    => 'simple',
			'limit'   => 20,
			'return'  => 'objects',
			's'       => $search,
			'orderby' => 'title',
			'order'   => 'ASC',
		);

		$products = function_exists( 'wc_get_products' ) ? wc_get_products( $args ) : array();
		$results  = array();

		foreach ( $products as $product ) {
			$results[] = array(
				'id'         => $product->get_id(),
				'name'       => $product->get_name(),
				'type'       => $product->get_type(),
				'price'      => wc_get_price_to_display( $product ),
				'price_html' => wp_strip_all_tags( $product->get_price_html() ),
				'is_virtual' => $product->is_virtual(),
			);
		}

		return rest_ensure_response( $results );
	}

	/* ---- Free constraint guards ---- */

	/**
	 * Reject a non-Recurring group type on a free-only install.
	 *
	 * @param array $params Group params.
	 *
	 * @return true|WP_Error
	 */
	protected function guard_recurring_only( array $params ) {
		if ( ! isset( $params['type'] ) || subscrpt_pro_activated() ) {
			return true;
		}

		$type_int = is_numeric( $params['type'] ) ? (int) $params['type'] : PlanRepository::type_to_int( $params['type'] );

		if ( PlanRepository::TYPE_MAP['recurring'] !== $type_int ) {
			return new WP_Error(
				'subscrpt_plan_type_pro',
				__( 'Subscribe & Save and Installment plans require Subscription Pro.', 'subscription' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Reject a per-variation relation (vid != 0) on a free-only install.
	 *
	 * @param array $params Relation params.
	 *
	 * @return true|WP_Error
	 */
	protected function guard_simple_only( array $params ) {
		if ( subscrpt_pro_activated() ) {
			return true;
		}

		if ( ! empty( $params['vid'] ) ) {
			return new WP_Error(
				'subscrpt_variation_pro',
				__( 'Attaching plans to product variations requires Subscription Pro.', 'subscription' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Standard 404 response.
	 *
	 * @return WP_Error
	 */
	protected function not_found() {
		return new WP_Error( 'subscrpt_plan_not_found', __( 'Not found.', 'subscription' ), array( 'status' => 404 ) );
	}
}
