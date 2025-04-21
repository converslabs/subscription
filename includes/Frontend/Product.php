<?php

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Product class
 * control single product page
 */
class Product {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_filter(
			'woocommerce_product_single_add_to_cart_text',
			array(
				$this,
				'change_single_add_to_cart_text',
			),
			10,
			2
		);
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'change_single_add_to_cart_text' ), 10, 2 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'change_price_html' ), 10, 2 );
		add_filter( 'woocommerce_quantity_input_args', array( $this, 'update_input_args' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart', array( $this, 'update_cart_quantity' ), 10, 3 );
		add_filter(
			'woocommerce_store_api_product_quantity_minimum',
			array(
				$this,
				'update_quantity_min_max',
			),
			10,
			2
		);
		add_filter(
			'woocommerce_store_api_product_quantity_maximum',
			array(
				$this,
				'update_quantity_min_max',
			),
			10,
			2
		);
		add_action(
			'woocommerce_after_cart_item_quantity_update',
			array(
				$this,
				'validate_quantity_on_manual_renewal',
			),
			10,
			2
		);
		add_filter( 'woocommerce_is_purchasable', array( $this, 'check_if_purchasable' ), 20, 2 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'text_if_active' ) );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'remove_button_active_products' ), 10, 2 );
	}

	/**
	 * Remove button if product already subscribed.
	 *
	 * @param mixed       $button Button.
	 * @param \WC_Product $product Product.
	 *
	 * @return mixed
	 */
	public function remove_button_active_products( $button, $product ) {
		$product = sdevs_get_subscription_product( $product );
		if ( ! $product->is_type( 'simple' ) ) {
			return $button;
		}

		if ( $product->is_enabled() ) {
			$limit = $product->get_limit();
			if ( 'one' === $limit ) {
				$unexpired = Helper::subscription_exists( $product->get_id(), array( 'active', 'pending' ) );
				if ( $unexpired ) {
					return;
				}
			} elseif ( 'only_one' === $limit && ! Helper::check_trial( $product->get_id() ) ) {
				return;
			}
		}

		return $button;
	}

	/**
	 * Display notice if already purchased.
	 */
	public function text_if_active() {
		global $product;
		$sdevs_product = sdevs_get_subscription_product( $product );
		if ( ! $sdevs_product->is_type( 'simple' ) ) {
			return;
		}

		if ( $sdevs_product->is_enabled() ) {
			$limit = $sdevs_product->get_limit();
			if ( 'unlimited' === $limit ) {
				return;
			}
			if ( 'one' === $limit ) {
				$unexpired = Helper::subscription_exists( $product->get_id(), array( 'active', 'pending' ) );
				if ( ! $unexpired ) {
					return false;
				} else {
					echo '<strong>' . esc_html_e( 'You Already Subscribed These Product!', 'sdevs_subscrpt' ) . '</strong>';
				}
			}
			if ( 'only_one' === $limit ) {
				if ( ! Helper::check_trial( $product->get_id() ) ) {
					echo '<strong>' . esc_html_e( 'You Already Subscribed These Product!', 'sdevs_subscrpt' ) . '</strong>';
				}
			}
		}
	}

	/**
	 * Check if product purchasable.
	 *
	 * @param boolean     $is_purchasable True\False.
	 * @param \WC_Product $product Product.
	 *
	 * @return boolean
	 */
	public function check_if_purchasable( $is_purchasable, $product ) {
		$product = sdevs_get_subscription_product( $product );
		if ( $product->is_enabled() ) {
			$limit = $product->get_limit();
			if ( 'unlimited' === $limit ) {
				return true;
			} elseif ( 'only_one' === $limit ) {
				return Helper::check_trial( $product->get_id() );
			} elseif ( 'one' === $limit ) {
				return ! Helper::subscription_exists( $product->get_id(), array( 'active', 'pending' ) );
			}
		}

		return $is_purchasable;
	}

	/**
	 * Validate quantity after add to cart cart on manual renewal process.
	 *
	 * @param string $cart_item_key Cart Item Key.
	 * @param int    $quantity Quantity.
	 *
	 * @return void
	 */
	public function validate_quantity_on_manual_renewal( $cart_item_key, $quantity ) {
		$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		$expired   = Helper::subscription_exists( $cart_item['data']->get_id(), 'expired' );
		if ( $expired ) {
			$order_item_id = get_post_meta( $expired, '_subscrpt_order_item_id', true );
			$item_quantity = (int) wc_get_order_item_meta( $order_item_id, '_qty', true );
			if ( $item_quantity !== $quantity ) {
				WC()->cart->set_quantity( $cart_item_key, $item_quantity );
				wc_add_notice( 'You can only add ' . $item_quantity . ' ' . ( $item_quantity > 1 ? 'items' : 'item' ) . ' on cart!', 'error' );
			}
		}
	}

	/**
	 * Update quantity min max for renewal process.
	 *
	 * @param int         $value Value.
	 * @param \WC_Product $product Product Object.
	 *
	 * @return int
	 */
	public function update_quantity_min_max( $value, $product ) {
		$expired = Helper::subscription_exists( $product->get_id(), 'expired' );
		if ( $expired ) {
			$order_item_id = get_post_meta( $expired, '_subscrpt_order_item_id', true );
			$item_quantity = (int) wc_get_order_item_meta( $order_item_id, '_qty', true );

			return $item_quantity;
		}

		return $value;
	}

	/**
	 * Update cart quantity on manual renewal process.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id Product Id.
	 * @param int    $quantity Quantity.
	 *
	 * @return void
	 */
	public function update_cart_quantity( $cart_item_key, $product_id, $quantity ) {
		$expired = Helper::subscription_exists( $product_id, 'expired' );
		if ( $expired ) {
			$order_item_id = get_post_meta( $expired, '_subscrpt_order_item_id', true );
			$item_quantity = (int) wc_get_order_item_meta( $order_item_id, '_qty', true );
			if ( $item_quantity !== $quantity ) {
				WC()->cart->set_quantity( $cart_item_key, $item_quantity );
				wc_add_notice( 'You can only add ' . $item_quantity . ' ' . ( $item_quantity > 1 ? 'items' : 'item' ) . ' on cart!', 'error' );
			}
		}
	}

	/**
	 * Update quantity input args.
	 *
	 * @param array       $args args.
	 * @param \WC_Product $product Product object.
	 *
	 * @return array
	 */
	public function update_input_args( $args, $product ) {
		$expired = Helper::subscription_exists( $product->get_id(), 'expired' );
		if ( $expired ) {
			$order_item_id       = get_post_meta( $expired, '_subscrpt_order_item_id', true );
			$item_quantity       = (int) wc_get_order_item_meta( $order_item_id, '_qty', true );
			$args['input_value'] = $item_quantity;
			$args['min_value']   = $item_quantity;
			$args['max_value']   = $item_quantity;
			$args['step']        = 1;
		}

		return $args;
	}

	/**
	 * Change single product add-to-cart button text.
	 *
	 * @param string      $text Add-to-cart button Text.
	 * @param \WC_Product $product Product Object.
	 */
	public function change_single_add_to_cart_text( $text, $product ) {
		$product = sdevs_get_subscription_product( $product );
		if ( $product->is_type( 'variable' ) || '' === $product->get_price() ) {
			return $text;
		}
		$cart_btn_label = $product->get_button_label();
		$expired        = Helper::subscription_exists( $product->get_id(), 'expired' );
		if ( $expired ) {
			$text = __( 'Renew', 'sdevs_subscrpt' );
		} elseif ( $product->is_enabled() && $cart_btn_label ) {
			$text = $cart_btn_label;
		}

		return $text;
	}

	/**
	 * Add trial, signup fee etc. with product price.
	 *
	 * @param mixed       $price Price.
	 * @param \WC_Product $product Product.
	 *
	 * @return mixed
	 */
	public function change_price_html( $price, $product ) {
		$product = sdevs_get_subscription_product( $product );
		if ( ! $product->is_type( 'simple' ) || '' === $price ) {
			return $price;
		}

		if ( $product->is_enabled() ) :
			$timing_option = $product->get_timing_option();
			$type          = Helper::get_typos( 1, $timing_option );
			$trial         = null;
			if ( $product->has_trial() ) {
				$meta_trial_time = $product->get_trial_timing_per();
				$trial           = '<br/><small> + Get ' . $meta_trial_time . ' ' . Helper::get_typos( $meta_trial_time, $product->get_trial_timing_option() ) . ' free trial!</small>';
			}

			return apply_filters( 'subscrpt_simple_price_html', ( $price . ' / ' . $type . $trial ), $product, $price, $trial );
		else :
			return $price;
		endif;
	}
}
