<?php
/**
 * Plan presenter - maps PlanRepository rows to the admin template shape.
 *
 * Builds the array contract the Plans admin templates render (name / type /
 * terms / products / rows …) from the real DB rows, so the templates stay
 * dumb view files.
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

/**
 * Plan presenter.
 */
class PlanPresenter {

	/**
	 * Build every plan group in the template contract, keyed by group id.
	 *
	 * @return array
	 */
	public static function all() {
		$plans = array();

		foreach ( PlanRepository::get_groups() as $group ) {
			$plans[ $group['id'] ] = self::group( $group['id'] );
		}

		return array_filter( $plans );
	}

	/**
	 * Build a single plan group tree in the template contract.
	 *
	 * @param int $group_id Group id.
	 *
	 * @return array|null
	 */
	public static function group( $group_id ) {
		$tree = PlanRepository::get_group_tree( $group_id );

		if ( ! $tree ) {
			return null;
		}

		$type_key = $tree['type_key'];
		$terms    = array();

		foreach ( $tree['plans'] as $plan ) {
			$terms[] = array(
				'id'        => $plan['id'],
				'name'      => $plan['title'],
				'breakdown' => self::breakdown( $plan ),
				'status'    => $plan['status'],
			);
		}

		return array(
			'id'       => $tree['id'],
			'name'     => $tree['title'],
			'type'     => $type_key,
			'status'   => $tree['status'],
			'created'  => self::ago( $tree['created_at'] ),
			'edited'   => self::ago( $tree['updated_at'] ),
			'terms'    => $terms,
			'products' => self::products( $tree, $type_key ),
		);
	}

	/**
	 * Group relations by product into the products[] contract (read-only).
	 *
	 * @param array  $tree     Group tree (plans → relations).
	 * @param string $type_key Plan type key.
	 *
	 * @return array
	 */
	protected static function products( $tree, $type_key ) {
		$by_product = array();

		foreach ( $tree['plans'] as $plan ) {
			foreach ( $plan['relations'] as $relation ) {
				if ( PlanRepository::REL_PRODUCT !== (int) $relation['type'] ) {
					continue;
				}

				$oid = (int) $relation['oid'];

				if ( ! isset( $by_product[ $oid ] ) ) {
					$product            = function_exists( 'wc_get_product' ) ? wc_get_product( $oid ) : null;
					$by_product[ $oid ] = array(
						'id'         => $oid,
						'name'       => $product ? $product->get_name() : sprintf( '#%d', $oid ),
						'base_price' => $product ? self::money( (float) $product->get_price() ) : '-',
						'edit_url'   => get_edit_post_link( $oid, 'raw' ),
						'view_url'   => get_permalink( $oid ),
						'rows'       => array(),
						'_terms'     => array(),
					);
				}

				// One row per selling plan (term) - guard against duplicate rows.
				$plan_id = (int) $plan['id'];

				if ( isset( $by_product[ $oid ]['_terms'][ $plan_id ] ) ) {
					continue;
				}

				$by_product[ $oid ]['_terms'][ $plan_id ] = true;
				$by_product[ $oid ]['rows'][]             = self::row( $plan, $relation, $type_key );
			}
		}

		foreach ( $by_product as &$entry ) {
			unset( $entry['_terms'] );
		}
		unset( $entry );

		return array_values( $by_product );
	}

	/**
	 * Build one read-only price row for a (plan term × product) relation.
	 *
	 * @param array  $plan     Plan term row.
	 * @param array  $relation Relation row.
	 * @param string $type_key Plan type key.
	 *
	 * @return array
	 */
	protected static function row( $plan, $relation, $type_key ) {
		$data = $relation['data'];

		$regular        = isset( $data['regular_price'] ) ? (string) $data['regular_price'] : '';
		$selling        = isset( $data['sale_price'] ) ? (string) $data['sale_price'] : '';
		$discount_type  = $data['discount_type'] ?? 'percentage';
		$discount_value = isset( $data['discount_value'] ) ? (string) $data['discount_value'] : '0';

		return array(
			'relation_id' => (int) $relation['id'],
			'term'        => $plan['title'],
			'regular'     => '' !== $regular ? self::money( (float) $regular ) : '-',
			'offer'       => self::money( self::offer_price( $regular, $selling, $discount_type, $discount_value ) ),
			'exclude'     => ! empty( $relation['exclude'] ),
		);
	}

	/**
	 * Compute the offer price: (sale ?? regular) minus the discount.
	 *
	 * @param string $regular        Regular price.
	 * @param string $selling        Sale price (may be empty).
	 * @param string $discount_type  percentage|fixed.
	 * @param string $discount_value Discount amount.
	 *
	 * @return float
	 */
	public static function offer_price( $regular, $selling, $discount_type, $discount_value ) {
		$base     = '' !== $selling ? (float) $selling : (float) $regular;
		$discount = (float) $discount_value;

		if ( 'percentage' === $discount_type ) {
			$base -= $base * ( $discount / 100 );
		} else {
			$base -= $discount;
		}

		return max( 0, $base );
	}

	/**
	 * Build a term's pricing-breakdown display string.
	 *
	 * @param array $plan Plan term row.
	 *
	 * @return string
	 */
	protected static function breakdown( $plan ) {
		if ( ! empty( $plan['data']['pricing_breakdown'] ) ) {
			return $plan['data']['pricing_breakdown'];
		}

		$freq     = max( 1, (int) $plan['billing_frequency'] );
		$interval = self::interval_label( (int) $plan['billing_interval'] );
		$every    = 1 === $freq ? strtolower( $interval ) : $freq . ' ' . strtolower( $interval ) . 's';

		/* translators: %s: billing interval, e.g. "month" or "2 weeks". */
		return sprintf( __( 'Billed every %s', 'subscription' ), $every );
	}

	/**
	 * Map a billing-interval integer to its label.
	 *
	 * @param int $interval 1=day, 2=week, 3=month, 4=year.
	 *
	 * @return string
	 */
	public static function interval_label( $interval ) {
		$labels = array(
			1 => __( 'Day', 'subscription' ),
			2 => __( 'Week', 'subscription' ),
			3 => __( 'Month', 'subscription' ),
			4 => __( 'Year', 'subscription' ),
		);

		return $labels[ $interval ] ?? __( 'Month', 'subscription' );
	}

	/**
	 * Human "x ago" string from a MySQL datetime.
	 *
	 * @param string $datetime MySQL datetime (UTC).
	 *
	 * @return string
	 */
	protected static function ago( $datetime ) {
		$ts = strtotime( (string) $datetime );

		if ( ! $ts ) {
			return '';
		}

		/* translators: %s: human time difference, e.g. "2 hours". */
		return sprintf( __( '%s ago', 'subscription' ), human_time_diff( $ts, time() ) );
	}

	/**
	 * Format an amount as a bare number string (no currency symbol).
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	protected static function amount( $amount ) {
		return number_format( (float) $amount, 2, '.', '' );
	}

	/**
	 * Format an amount with the WooCommerce currency symbol (suffix style).
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	public static function money( $amount ) {
		$symbol = function_exists( 'get_woocommerce_currency_symbol' )
			? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
			: '$';

		return self::amount( $amount ) . $symbol;
	}
}
