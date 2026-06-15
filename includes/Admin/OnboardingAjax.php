<?php

namespace SpringDevs\Subscription\Admin;

/**
 * AJAX handlers for the onboarding wizard.
 *
 * Handles page-2 save (product create/update), variation fetch, and wizard reset.
 *
 * @package SpringDevs\Subscription\Admin
 */
class OnboardingAjax {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'wp_ajax_subscrpt_save_wizard_page2', array( $this, 'save_wizard_page2' ) );
		add_action( 'wp_ajax_subscrpt_reset_wizard', array( $this, 'reset_wizard' ) );
		add_action( 'wp_ajax_subscrpt_get_product_variations', array( $this, 'get_product_variations' ) );
	}

	/**
	 * Save wizard Page 2 data to session and create/update product.
	 *
	 * @return void Sends JSON.
	 */
	public function save_wizard_page2() {
		check_ajax_referer( 'subscrpt_onboarding_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'subscription' ) ), 403 );
		}

		if ( ! session_id() ) {
			session_start();
		}

		$product_mode     = isset( $_POST['product_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['product_mode'] ) ) : 'new';
		$product_name     = isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';
		$product_price    = isset( $_POST['product_price'] ) ? sanitize_text_field( wp_unslash( $_POST['product_price'] ) ) : '';
		$existing_product = isset( $_POST['existing_product_id'] ) ? absint( $_POST['existing_product_id'] ) : 0;
		$variation_id     = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$timing_option    = isset( $_POST['timing_option'] ) ? sanitize_text_field( wp_unslash( $_POST['timing_option'] ) ) : 'never';
		$billing_per      = isset( $_POST['billing_per'] ) ? absint( $_POST['billing_per'] ) : 1;
		$billing_period   = isset( $_POST['billing_period'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_period'] ) ) : 'months';
		$trial_timing_per = isset( $_POST['trial_timing_per'] ) ? absint( $_POST['trial_timing_per'] ) : 0;
		$signup_fee       = isset( $_POST['signup_fee'] ) ? sanitize_text_field( wp_unslash( $_POST['signup_fee'] ) ) : '';
		$trial_enabled    = isset( $_POST['trial_enabled'] ) ? (int) $_POST['trial_enabled'] : 0;
		$trial_timing_opt = isset( $_POST['trial_timing_option'] ) ? sanitize_text_field( wp_unslash( $_POST['trial_timing_option'] ) ) : 'days';
		$length_enabled   = isset( $_POST['length_enabled'] ) ? (int) $_POST['length_enabled'] : 0;
		$length_per       = isset( $_POST['length_per'] ) ? absint( $_POST['length_per'] ) : 0;
		$length_option    = isset( $_POST['length_option'] ) ? sanitize_text_field( wp_unslash( $_POST['length_option'] ) ) : 'months';

		$result_product_id = 0;

		if ( 'new' === $product_mode ) {
			$wc_product = new \WC_Product_Simple();
			$wc_product->set_name( $product_name );
			$wc_product->set_regular_price( wc_format_decimal( $product_price ) );
			$wc_product->set_status( 'publish' );
			$this->apply_subscription_meta( $wc_product, $billing_period, $billing_per, $trial_timing_per, $trial_timing_opt, $signup_fee );
			$result_product_id = $wc_product->save();

			if ( ! $result_product_id ) {
				wp_send_json_error( array( 'message' => __( 'Failed to create product. Please try again.', 'subscription' ) ) );
			}
		} elseif ( 'existing' === $product_mode && $existing_product > 0 ) {
			$parent = wc_get_product( $existing_product );

			if ( ! $parent ) {
				wp_send_json_error( array( 'message' => __( 'Product not found.', 'subscription' ) ) );
			}

			if ( $variation_id > 0 ) {
				// Variable product: update parent name only; apply meta + price on the variation.
				$parent->set_name( $product_name );
				$parent->save();

				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$variation->set_regular_price( wc_format_decimal( $product_price ) );
					$this->apply_subscription_meta( $variation, $billing_period, $billing_per, $trial_timing_per, $trial_timing_opt, $signup_fee );
					$variation->save();
				}
			} else {
				// Simple product: update name, price, and subscription meta in one save.
				$parent->set_name( $product_name );
				$parent->set_regular_price( wc_format_decimal( $product_price ) );
				$this->apply_subscription_meta( $parent, $billing_period, $billing_per, $trial_timing_per, $trial_timing_opt, $signup_fee );
				$parent->save();
			}

			$result_product_id = $existing_product;
		}

		$_SESSION['subscrpt_onboarding_wizard'] = array(
			'page'                => 3,
			'product_id'          => $result_product_id,
			'product_mode'        => $product_mode,
			'product_name'        => $product_name,
			'product_price'       => $product_price,
			'existing_product'    => $existing_product,
			'variation_id'        => $variation_id,
			'timing_option'       => $timing_option,
			'billing_per'         => $billing_per,
			'billing_period'      => $billing_period,
			'trial_timing_per'    => $trial_timing_per,
			'signup_fee'          => $signup_fee,
			'trial_enabled'       => $trial_enabled,
			'trial_timing_option' => $trial_timing_opt,
			'length_enabled'      => $length_enabled,
			'length_per'          => $length_per,
			'length_option'       => $length_option,
		);

		wp_send_json_success( array( 'product_id' => $result_product_id ) );
	}

	/**
	 * Stage subscription meta on a WC product object via the WC meta layer.
	 * Does NOT call save() — the caller must save the object after calling this method.
	 *
	 * @param \WC_Product $product        Product or variation object.
	 * @param string      $billing_period Billing period unit (days/weeks/months/years).
	 * @param int         $billing_per    Billing interval count.
	 * @param int         $trial_per      Free trial length.
	 * @param string      $trial_option   Free trial period unit.
	 * @param string      $signup_fee     One-time sign-up fee (pro only).
	 *
	 * @return void
	 */
	private function apply_subscription_meta( $product, $billing_period, $billing_per, $trial_per, $trial_option, $signup_fee ) {
		$product->update_meta_data( '_subscrpt_enabled', true );
		$product->update_meta_data( '_subscrpt_timing_option', $billing_period );
		$product->update_meta_data( '_subscrpt_timing_per', $billing_per );
		$product->update_meta_data( '_subscrpt_trial_timing_per', $trial_per );
		$product->update_meta_data( '_subscrpt_trial_timing_option', $trial_option );
		$product->update_meta_data( '_subscrpt_payment_type', 'recurring' );

		if ( function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated() && '' !== $signup_fee ) {
			$product->update_meta_data( '_subscrpt_signup_fee', wc_format_decimal( $signup_fee ) );
		}
	}

	/**
	 * Return available variations for a variable product (wizard use).
	 *
	 * @return void Sends JSON.
	 */
	public function get_product_variations() {
		check_ajax_referer( 'subscrpt_onboarding_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'subscription' ) ), 403 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'subscription' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || 'variable' !== $product->get_type() ) {
			wp_send_json_error( array( 'message' => __( 'Not a variable product.', 'subscription' ) ) );
		}

		$available = $product->get_available_variations();
		$result    = array();

		foreach ( $available as $variation ) {
			$v_id = $variation['variation_id'];

			// Build human-readable attribute label.
			$attr_labels = array();
			foreach ( $variation['attributes'] as $attr_key => $attr_value ) {
				if ( '' === $attr_value ) {
					$attr_labels[] = __( 'Any', 'subscription' );
					continue;
				}
				$taxonomy = str_replace( 'attribute_', '', $attr_key );
				if ( taxonomy_exists( $taxonomy ) ) {
					$term          = get_term_by( 'slug', $attr_value, $taxonomy );
					$attr_labels[] = $term ? $term->name : ucfirst( str_replace( '-', ' ', $attr_value ) );
				} else {
					$attr_labels[] = ucfirst( str_replace( '-', ' ', $attr_value ) );
				}
			}
			$label = implode( ' / ', $attr_labels );
			if ( ! $label ) {
				/* translators: %d: variation ID */
				$label = sprintf( __( 'Variation #%d', 'subscription' ), $v_id );
			}

			$result[] = array(
				'id'             => $v_id,
				'label'          => $label,
				'price'          => $variation['display_price'],
				'sku'            => $variation['sku'],
				'billing_period' => get_post_meta( $v_id, '_subscrpt_timing_option', true ),
				'billing_per'    => get_post_meta( $v_id, '_subscrpt_timing_per', true ) ?: 1,
				'trial_per'      => get_post_meta( $v_id, '_subscrpt_trial_timing_per', true ),
				'signup_fee'     => get_post_meta( $v_id, '_subscrpt_signup_fee', true ),
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Reset wizard session (for "Add another" action).
	 *
	 * @return void Sends JSON.
	 */
	public function reset_wizard() {
		check_ajax_referer( 'subscrpt_onboarding_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'subscription' ) ), 403 );
		}

		if ( ! session_id() ) {
			session_start();
		}

		unset( $_SESSION['subscrpt_onboarding_wizard'] );

		wp_send_json_success();
	}
}
