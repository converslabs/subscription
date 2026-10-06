<?php
/**
 * Storefront plan selector groups.
 *
 * The single builder of the purchase options the plan selector renders: one
 * card per plan group with its terms, then the One-Time card. Pro adds its own
 * extras (trial and signup-fee text, the box builder, …) through the
 * `subscrpt_plan_term` and `subscrpt_plan_selector_groups` filters rather than
 * building a second copy.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Admin\PlanPresenter;
use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

/**
 * Builds the plan selector groups for a product or variation.
 */
class PlanGroups {

	/**
	 * Groups for a product or variation that offers a plan.
	 *
	 * @param \WC_Product $product   Product, or the variation when `$parent_id` is set.
	 * @param int         $parent_id Parent product id for a variation, 0 otherwise.
	 * @param string      $context   Where the selector renders, passed to the filters.
	 *
	 * @return array Groups in plan-selector.php shape; empty when no plan is offered.
	 */
	public static function for_product( \WC_Product $product, int $parent_id = 0, string $context = 'page' ): array {
		$product_id   = $parent_id ? $parent_id : $product->get_id();
		$variation_id = $parent_id ? $product->get_id() : 0;

		if ( ! subscrpt_plan_offered( $product_id, $variation_id ) ) {
			return [];
		}

		$rows = PlanRepository::resolve_for_product( $product_id, $variation_id );

		return self::from_rows( $rows, $product, $context );
	}

	/**
	 * Groups from resolved plan rows.
	 *
	 * One entry per plan group, each with its terms (id, label, price, note,
	 * discount_percent, badge), followed by the One-Time card when the product
	 * offers one.
	 *
	 * @param array       $rows    Rows from `PlanRepository::resolve_for_product()`.
	 * @param \WC_Product $product Product or variation being rendered.
	 * @param string      $context Where the selector renders, passed to the filters.
	 *
	 * @return array
	 */
	public static function from_rows( array $rows, \WC_Product $product, string $context = 'page' ): array {
		if ( empty( $rows ) ) {
			return [];
		}

		$groups = [];
		foreach ( $rows as $row ) {
			$gid      = (int) $row['plan_group_id'];
			$type_key = PlanRepository::type_to_string( (int) $row['group_type'] );

			if ( ! isset( $groups[ $gid ] ) ) {
				$groups[ $gid ] = [
					'id'               => 'grp_' . $gid,
					'type'             => $type_key,
					'label'            => $row['group_title'],
					'price'            => '',
					'old_price'        => '',
					'terms'            => [],
					'badge'            => '',
					'discount_percent' => 0,
				];
			}

			$price_num = self::term_price( $row, $type_key );

			// Installments price on a different basis, so they never carry a percentage.
			$regular  = isset( $row['relation_data']['regular_price'] ) ? (float) $row['relation_data']['regular_price'] : 0.0;
			$term_pct = 0;
			if ( 'installments' !== $type_key && $regular > 0 && $price_num < $regular ) {
				$term_pct = (int) round( ( $regular - $price_num ) / $regular * 100 );
			}

			$term = [
				'id'               => (int) $row['plan_id'],
				'label'            => $row['plan_title'],
				'price'            => wc_price( $price_num ),
				'note'             => self::term_note( $row, $type_key, $price_num ),
				'discount_percent' => $term_pct,
				'badge'            => '',
			];

			/**
			 * Filters one term of the storefront plan selector after it is built.
			 *
			 * Pro appends its trial and signup-fee text to the note here.
			 *
			 * @param array       $term     Term (id, label, price, note, discount_percent, badge).
			 * @param array       $row      The resolved plan row the term was built from.
			 * @param string      $type_key Plan type: recurring, subscribe_save or installments.
			 * @param \WC_Product $product  Product or variation being rendered.
			 */
			$filtered = apply_filters( 'subscrpt_plan_term', $term, $row, $type_key, $product );

			// A listener that returns nothing must not take the storefront down.
			$groups[ $gid ]['terms'][] = is_array( $filtered ) ? $filtered : $term;
		}

		// The card shows one term at a time, the selected one, so the badge is
		// per term; the card starts on the first term's, which is pre-selected.
		foreach ( $groups as &$group ) {
			$group['price']            = $group['terms'][0]['price'];
			$group['discount_percent'] = max( array_merge( [ 0 ], array_column( $group['terms'], 'discount_percent' ) ) );

			foreach ( $group['terms'] as &$term ) {
				$term['badge'] = $term['discount_percent'] > 0
					? subscrpt_card_badge_text( $group, $product, $term['discount_percent'] )
					: '';
			}
			unset( $term );

			$group['badge'] = $group['terms'][0]['badge'];
		}
		unset( $group );

		$groups = array_values( $groups );

		// Last, so a subscription stays the pre-selected first card. Pass the
		// variation itself: its parent's flag only means "any variation enabled".
		$one_time = subscrpt_one_time_group( $product );
		if ( $one_time ) {
			$groups[] = $one_time;
		}

		/**
		 * Filters the storefront plan selector groups, One-Time card included.
		 *
		 * @param array       $groups  Groups (id, type, label, price, old_price, terms, badge, discount_percent).
		 * @param \WC_Product $product Product or variation being rendered.
		 * @param string      $context Where the selector renders; 'page' for the product page.
		 */
		return (array) apply_filters( 'subscrpt_plan_selector_groups', $groups, $product, $context );
	}

	/**
	 * The price a term shows: the offer price, or for installments the amount
	 * of each payment.
	 *
	 * @param array  $row      Resolved plan row.
	 * @param string $type_key Plan type key.
	 *
	 * @return float
	 */
	private static function term_price( array $row, string $type_key ): float {
		$price = self::offer_price( $row );

		if ( 'installments' === $type_key ) {
			return (float) subscrpt_split_amounts( $price, self::installment_count( $row ) )['per_installment'];
		}

		return $price;
	}

	/**
	 * The offer price entered on the plan relation, after its discount.
	 *
	 * @param array $row Resolved plan row.
	 *
	 * @return float
	 */
	private static function offer_price( array $row ): float {
		$data = is_array( $row['relation_data'] ) ? $row['relation_data'] : [];

		return (float) PlanPresenter::offer_price(
			isset( $data['regular_price'] ) ? (string) $data['regular_price'] : '',
			isset( $data['sale_price'] ) ? (string) $data['sale_price'] : '',
			isset( $data['discount_type'] ) ? (string) $data['discount_type'] : 'percentage',
			isset( $data['discount_value'] ) ? (string) $data['discount_value'] : '0'
		);
	}

	/**
	 * Number of payments in an installments plan. `billing_frequency` is the
	 * "every N" cadence, not the count.
	 *
	 * @param array $row Resolved plan row.
	 *
	 * @return int At least 1.
	 */
	private static function installment_count( array $row ): int {
		$plan_data = isset( $row['plan_data'] ) && is_array( $row['plan_data'] ) ? $row['plan_data'] : [];

		return max( 1, (int) ( $plan_data['installment_count'] ?? 0 ) );
	}

	/**
	 * The billing note under a term ("Billed $10.00 / month").
	 *
	 * @param array  $row       Resolved plan row.
	 * @param string $type_key  Plan type key.
	 * @param float  $price_num The term's price.
	 *
	 * @return string
	 */
	private static function term_note( array $row, string $type_key, float $price_num ): string {
		$interval = strtolower( PlanPresenter::interval_label( (int) $row['billing_interval'] ) );
		$freq     = max( 1, (int) $row['billing_frequency'] );
		$every    = 1 === $freq ? $interval : $freq . ' ' . $interval . 's';

		if ( 'installments' === $type_key ) {
			$count = self::installment_count( $row );
			$total = subscrpt_split_amounts( self::offer_price( $row ), $count )['total'];

			return sprintf(
				/* translators: 1: number of payments, 2: per-payment price, 3: billing interval, 4: total price. */
				__( 'Pay %4$s in %1$d installments of %2$s, billed every %3$s.', 'subscription' ),
				$count,
				self::price_text( $price_num ),
				$every,
				self::price_text( $total )
			);
		}

		$data    = is_array( $row['relation_data'] ) ? $row['relation_data'] : [];
		$regular = isset( $data['regular_price'] ) && '' !== $data['regular_price'] ? (float) $data['regular_price'] : null;

		$price_disp = ( null !== $regular && $price_num < $regular )
			? '<del>' . self::price_text( $regular ) . '</del> ' . self::price_text( $price_num )
			: self::price_text( $price_num );

		return sprintf(
			/* translators: 1: price (may include a struck-through regular price), 2: billing interval. */
			__( 'Billed %1$s / %2$s', 'subscription' ),
			$price_disp,
			$every
		);
	}

	/**
	 * A price as plain text, the currency symbol a real character rather than
	 * an entity, so it reads cleanly in the note and in data attributes.
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	private static function price_text( float $amount ): string {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}
}
