<?php

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\ExtendRestApi;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;

/**
 * Cart class
 */
class Cart {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_to_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'define_custom_schema' ) );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'change_price_cart_html' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'change_price_cart_html' ), 10, 2 );
		add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'add_rows_order_total' ) );
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'add_rows_order_total' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'set_renew_status' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'check_cart_items' ) );
		add_filter( 'woocommerce_get_item_data', array( $this, 'set_line_item_meta' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'add_calculation_price_filter' ) );
		add_action( 'woocommerce_calculate_totals', array( $this, 'remove_calculation_price_filter' ) );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'remove_calculation_price_filter' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'add_to_cart_validation' ), 10, 2 );
	}

	/**
	 * Add to cart validation.
	 *
	 * @param bool $passed Passed ?.
	 * @param int  $product_id Product Id.
	 *
	 * @return bool
	 */
	public function add_to_cart_validation( bool $passed, int $product_id ): bool {
		$cart_items   = WC()->cart->cart_contents;
		$error_notice = null;
		$failed       = false;
		$product      = sdevs_get_subscription_product( $product_id );
		$enabled      = $product->is_enabled();
		foreach ( $cart_items as $key => $cart_item ) {
			if ( isset( $cart_item['subscription'] ) ) {
				if ( $enabled ) {
					$error_notice = __( 'Currently You have an another Subscriptional product on cart !!', 'wp_subscription' );
				} else {
					$error_notice = __( 'Currently You have Subscriptional product in a cart !!', 'wp_subscription' );
				}
				$failed = true;
			} elseif ( $enabled ) {
				$failed       = true;
				$error_notice = __( 'Your cart isn\'t empty !!', 'wp_subscription' );
			}
		}

		if ( $failed ) {
			wc_add_notice( $error_notice, 'error' );

			return false;
		}

		return $passed;
	}

	/**
	 * Add filter before cart calculation.
	 *
	 * @return void
	 */
	public function add_calculation_price_filter() {
		add_filter( 'woocommerce_product_get_price', array( $this, 'set_prices_for_calculation' ), 100, 2 );
	}

	/**
	 * Return 0 if product has trial.
	 *
	 * @param float       $price Price.
	 * @param \WC_Product $product Product object.
	 *
	 * @return float
	 */
	public function set_prices_for_calculation( $price, $product ) {
		$product = sdevs_get_subscription_product( $product );
		if ( $product->is_enabled() && $product->is_type( 'simple' ) ) {
			$trial_time_per = $product->get_meta( '_subscrpt_trial_timing_per' );
			if ( ! empty( $trial_time_per ) && $trial_time_per > 0 && Helper::check_trial( $product->get_id() ) ) {
				return 0;
			}
		}

		return $price;
	}

	/**
	 * Remove filter after calculate calculation.
	 *
	 * @return void
	 */
	public function remove_calculation_price_filter() {
		remove_filter( 'woocommerce_product_get_price', array( $this, 'set_prices_for_calculation' ), 100 );
	}

	/**
	 * Set line item for display meta details.
	 *
	 * @param array $cart_item_data Cart Item Data.
	 * @param array $cart_item Cart Item.
	 *
	 * @return array
	 */
	public function set_line_item_meta( $cart_item_data, $cart_item ) {
		if ( isset( $cart_item['subscription'] ) ) {
			if ( $cart_item['subscription']['trial'] ) {
				$cart_item_data[] = array(
					'key'    => __( 'Free Trial', 'wp_subscription' ),
					'value'  => $cart_item['subscription']['trial'],
					'hidden' => true,
					'__experimental_woocommerce_blocks_hidden' => false,
				);
			}
		}

		return $cart_item_data;
	}

	/**
	 * Check cart items if it's valid or not?
	 *
	 * @return void
	 */
	public function check_cart_items() {
		if ( subscrpt_pro_activated() ) {
			return;
		}
		$cart_items = WC()->cart->cart_contents;
		if ( is_array( $cart_items ) ) {
			foreach ( $cart_items as $key => $value ) {
				/**
				 * Product Object.
				 *
				 * @var \WC_Product $product
				 */
				$product = $value['data'];
				$product = sdevs_get_subscription_product( $product );
				if ( isset( $value['subscription'] ) ) {
					if ( $product->is_type( 'simple' ) ) {
						if ( Helper::get_typos( 1, $product->get_meta( '_subscrpt_timing_option' ) ) !== $value['subscription']['type'] || $product->get_trial() !== $value['subscription']['trial'] ) {
							// remove the item.
							wc_add_notice( __( 'An item which is no longer available was removed from your cart.', 'wp_subscription' ), 'error' );
							WC()->cart->remove_cart_item( $key );
						}
					} else {
						// remove the item.
						wc_add_notice( __( 'An item which is no longer available was removed from your cart.', 'wp_subscription' ), 'error' );
						WC()->cart->remove_cart_item( $key );
					}
				} elseif ( $product->get_meta( '_subscrpt_enabled' ) ) {
					// remove the item.
					wc_add_notice( __( 'An item which is no longer available was removed from your cart.', 'wp_subscription' ), 'error' );
					WC()->cart->remove_cart_item( $key );
				}
			}
		}
	}

	/**
	 * Define custom schema.
	 *
	 * @return void
	 */
	public function define_custom_schema() {
		$this->register_endpoint_data(
			array(
				'endpoint'        => CartItemSchema::IDENTIFIER,
				'namespace'       => 'sdevs_subscription',
				'data_callback'   => array( $this, 'extend_cart_item_data' ),
				'schema_callback' => array( $this, 'extend_cart_item_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
		$this->register_endpoint_data(
			array(
				'endpoint'        => CartSchema::IDENTIFIER,
				'namespace'       => 'sdevs_subscription',
				'data_callback'   => array( $this, 'extend_cart_data' ),
				'schema_callback' => array( $this, 'extend_cart_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Register subscription product schema into cart/items endpoint.
	 *
	 * @return array Registered schema.
	 */
	public function extend_cart_schema() {
		return array(
			'recurring_totals' => array(
				'description'      => __( 'List of recurring totals in cart.', 'wp_subscription' ),
				'type'             => 'array',
				'readonly'         => true,
				'recurring_totals' => array(
					'price'           => array(
						'description' => __( 'price of the subscription.', 'wp_subscription' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'time'            => array(
						'description' => __( 'time of the subscription.', 'wp_subscription' ),
						'type'        => array( 'number' ),
						'readonly'    => true,
					),
					'type'            => array(
						'description' => __( 'type of the subscription.', 'wp_subscription' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'description'     => array(
						'description' => __( 'price of the subscription description.', 'wp_subscription' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'can_user_cancel' => array(
						'description' => __( 'Allow User Cancellation?', 'wp_subscription' ),
						'type'        => array( 'string' ),
						'readonly'    => true,
					),
					'max_no_payment'  => array(
						'description' => __( 'Maximum Total Payments', 'wp_subscription' ),
						'type'        => array( 'number' ),
						'readonly'    => true,
					),
				),
			),
		);
	}

	/**
	 * Register subscription product data into cart/items endpoint.
	 *
	 * @return array $item_data Registered data or empty array if condition is not satisfied.
	 */
	public function extend_cart_data() {
		$cart_items = WC()->cart->cart_contents;
		$recurrings = array();
		if ( $cart_items ) {
			foreach ( $cart_items as $cart_item ) {
				if ( isset( $cart_item['subscription'] ) && $cart_item['subscription']['type'] ) {
					$cart_subscription = $cart_item['subscription'];
					$start_date        = Helper::start_date( $cart_subscription['trial'] );
					$next_date         = Helper::next_date(
						( $cart_subscription['time'] ?? 1 ) . ' ' . $cart_subscription['type'],
						$cart_subscription['trial']
					);

					// Calculate price including tax
					$product        = $cart_item['data'];
					$price_excl_tax = (float) $cart_item['subscription']['per_cost'];
					$qty            = $cart_item['quantity'];

					// Get tax rate for the product
					$tax_rates      = \WC_Tax::get_rates( $product->get_tax_class() );
					$price_incl_tax = $price_excl_tax;

					if ( wc_prices_include_tax() ) {
						// If prices are inclusive of tax, we already have the right price
						$price_incl_tax = $price_excl_tax;
					} else {
						// If prices are exclusive of tax, calculate tax and add it
						$tax_amount     = \WC_Tax::calc_tax( $price_excl_tax, $tax_rates, false );
						$price_incl_tax = $price_excl_tax + array_sum( $tax_amount );
					}

					// Multiply by quantity and convert to cents for Stripe
					$total_amount = ( $price_incl_tax * $qty ) * 100;

					$recurrings[] = apply_filters(
						'subscrpt_cart_recurring_data',
						array(
							'price'           => $total_amount,
							'time'            => $cart_subscription['time'],
							'type'            => $cart_subscription['type'],
							'description'     => empty( $cart_subscription['trial'] ) ? 'Next billing on: ' . $next_date : 'First billing on: ' . $start_date,
							'can_user_cancel' => $cart_item['data']->get_meta( '_subscrpt_user_cancel' ),
							'max_no_payment'  => $cart_item['data']->get_meta( '_subscrpt_max_no_payment' ),
						),
						$cart_item
					);
				}
			}
		}

		return $recurrings;
	}

	/**
	 * Register subscription product schema into cart/items endpoint.
	 *
	 * @return array Registered schema.
	 */
	public function extend_cart_item_schema() {
		return array(
			'time'           => array(
				'description' => __( 'time of the subscription type.', 'wp_subscription' ),
				'type'        => array( 'number', 'null' ),
				'readonly'    => true,
			),
			'type'           => array(
				'description' => __( 'the subscription type.', 'wp_subscription' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
			'trial'          => array(
				'description' => __( 'the subscription trial.', 'wp_subscription' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
			'signup_fee'     => array(
				'description' => __( 'Signup Fee amount.', 'wp_subscription' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
			'cost'           => array(
				'description' => __( 'Recurring amount.', 'wp_subscription' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
			'max_no_payment' => array(
				'description' => __( 'Maximum Total Payments', 'wp_subscription' ),
				'type'        => array( 'number' ),
				'readonly'    => true,
			),
		);
	}

	/**
	 * Register subscription product data into cart/items endpoint.
	 *
	 * @param array $cart_item Current cart item data.
	 *
	 * @return array $item_data Registered data or empty array if condition is not satisfied.
	 */
	public function extend_cart_item_data( $cart_item ) {
		$item_data = array(
			'time'           => null,
			'type'           => null,
			'trial'          => null,
			'signup_fee'     => null,
			'cost'           => null,
			'max_no_payment' => null,
		);

		if ( isset( $cart_item['subscription'] ) ) {
			$item_data = $cart_item['subscription'];
			unset( $item_data['per_cost'] );
			$item_data['cost'] = (float) $cart_item['subscription']['per_cost'] * $cart_item['quantity'];
		}
		if ( ! subscrpt_pro_activated() ) {
			$item_data['time']       = null;
			$item_data['signup_fee'] = null;
		}

		return $item_data;
	}

	/**
	 * Add product meta on cart item.
	 *
	 * @param array $cart_item_data cart_item_data.
	 * @param int   $product_id Product ID.
	 *
	 * @return array
	 */
	public function add_to_cart_item_data( array $cart_item_data, int $product_id ): array {
		$product = sdevs_get_subscription_product( $product_id );
		if ( ! $product->is_type( 'simple' ) ) {
			return $cart_item_data;
		}
		if ( $product->is_enabled() ) :
			$subscription_data          = array();
			$subscription_data['time']  = null;
			$subscription_data['type']  = $product->get_timing_option();
			$subscription_data['trial'] = null;
			if ( $product->has_trial() ) {
				$subscription_data['trial'] = $product->get_trial();
			}
			$subscription_data['signup_fee']                  = null;
			$subscription_data['per_cost']                    = $product->get_price();
			$cart_item_data['subscription']                   = apply_filters( 'subscrpt_block_simple_cart_item_data', $subscription_data, $product, $cart_item_data );
			$cart_item_data['subscription']['max_no_payment'] = $product->get_meta( '_subscrpt_max_no_payment' );
		endif;

		return $cart_item_data;
	}

	/**
	 * Register endpoint data with the API.
	 *
	 * @param array $args Endpoint data to register.
	 */
	protected function register_endpoint_data( $args ) {
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			woocommerce_store_api_register_endpoint_data( $args );
		} else {
			Package::container()->get( ExtendRestApi::class )->register_endpoint_data( $args );
		}
	}

	/**
	 * Display formatted price on cart.
	 *
	 * @param string $price price.
	 * @param array  $cart_item cart item.
	 *
	 * @return string
	 */
	public function change_price_cart_html( $price, $cart_item ) {
		$product = sdevs_get_subscription_product( $cart_item['product_id'] );
		if ( ! $product->is_type( 'simple' ) ) {
			return $price;
		}

		if ( $product->is_enabled() ) {
			return $product->get_price_html();
		}

		return $price;
	}

	/**
	 * Display "Recurring totals" on cart
	 *
	 * @return void
	 */
	public function add_rows_order_total() {
		$cart_items = WC()->cart->get_cart_contents();
		$recurrs    = Helper::get_recurrs_from_cart( $cart_items );
		if ( 0 === count( $recurrs ) ) {
			return;
		}
		?>
		<tr class="recurring-total">
			<th><?php esc_html_e( 'Recurring totals', 'wp_subscription' ); ?></th>
			<td data-title="<?php esc_attr_e( 'Recurring totals', 'wp_subscription' ); ?>">
				<?php foreach ( $recurrs as $recurr ) : ?>
					<p>
						<span><?php echo wp_kses_post( $recurr['price_html'] ); ?></span>
						<?php if ( $recurr['max_no_payment'] > 0 ) : ?>
							<span>x <?php echo esc_html( $recurr['max_no_payment'] ); ?></span>
						<?php endif; ?>
						<br />
						<small>
						<?php
						$billing_text = $recurr['trial_status'] ? 'First billing on' : 'Next billing on';
						esc_html_e( $billing_text, 'wp_subscription' );
						?>
							: <?php echo esc_html( $recurr['trial_status'] ? $recurr['start_date'] : $recurr['next_date'] ); ?></small>
						<?php if ( 'yes' === $recurr['can_user_cancel'] ) : ?>
							<br>
							<small><?php esc_html_e( 'You can cancel subscription at any time!', 'wp_subscription' ); ?></small>
						<?php endif; ?>

						<!-- add how many times will be build if _subscrpt_renewal_limit is not 0 -->
						<?php if ( $recurr['max_no_payment'] > 0 ) : ?>
							<br>
							<small><?php esc_html_e( 'This subscription will be billed for', 'wp_subscription' ); ?> <?php echo esc_html( $recurr['max_no_payment'] ); ?> <?php esc_html_e( 'times.', 'wp_subscription' ); ?></small>
						<?php endif; ?>
					</p>
				<?php endforeach; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Add renew status.
	 *
	 * @param array $cart_item_data cart_item_data.
	 * @param int   $product_id Product ID.
	 *
	 * @return array
	 */
	public function set_renew_status( $cart_item_data, $product_id ) {
		$expired = Helper::subscription_exists( $product_id, 'expired' );
		if ( $expired ) {
			// Check if maximum payment limit has been reached
			if ( subscrpt_is_max_payments_reached( $expired ) ) {
				wc_add_notice( __( 'This subscription has reached its maximum payment limit and cannot be renewed further.', 'wp_subscription' ), 'error' );
				return $cart_item_data; // Don't add renew status
			}

			$cart_item_data['renew_subscrpt'] = true;
		}

		return $cart_item_data;
	}
}
