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
 * `$context` says where the selector renders, so a listener can tell them apart:
 *
 * - `'page'`      the product page's own selector (a simple product);
 * - `'variation'` one variation's selector, rendered on the server for
 *                 `woocommerce_available_variation` and swapped in when the
 *                 shopper picks that variation.
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
	 * @param string      $context   Where the selector renders: 'page' or 'variation'.
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
	 * One entry per plan group, each with its terms (id, label, interval_label, price,
	 * regular_price, note, discount_percent, saving_label, badge) and the
	 * `terms_heading` that introduces them, and the group's `storefront` fields (benefits_heading,
	 * benefits, learn_more, tag) when it sets any, followed by the One-Time card when the product
	 * offers one.
	 *
	 * @param array       $rows    Rows from `PlanRepository::resolve_for_product()`.
	 * @param \WC_Product $product Product or variation being rendered.
	 * @param string      $context Where the selector renders: 'page' or 'variation'.
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
					'terms_heading'    => self::terms_heading( $type_key ),
					'badge'            => '',
					'discount_percent' => 0,
				];

				// Only a group that sets any carries the key, so a plain group is unchanged.
				$storefront = isset( $row['group_data']['storefront'] ) ? $row['group_data']['storefront'] : [];
				if ( is_array( $storefront ) && ! empty( $storefront ) ) {
					$groups[ $gid ]['storefront'] = $storefront;
				}
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
				'interval_label'   => self::interval_text( $row, $type_key ),
				'price'            => wc_price( $price_num ),
				'regular_price'    => self::term_regular_price( $row, $type_key ),
				'note'             => self::term_note( $row, $type_key, $price_num ),
				'discount_percent' => $term_pct,
				'saving_label'     => $term_pct > 0
					/* translators: %d: discount percentage. */
					? sprintf( __( 'Save %d%%', 'subscription' ), $term_pct )
					: '',
				'badge'            => '',
			];

			/**
			 * Filters one term of the storefront plan selector after it is built.
			 *
			 * Pro appends its trial and signup-fee text to the note here.
			 *
			 * @param array       $term     Term (id, label, interval_label, price, regular_price, note,
			 *                              discount_percent, saving_label, badge).
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
		 * @param array       $groups  Groups (id, type, label, price, old_price, terms, terms_heading, badge,
		 *                             discount_percent).
		 * @param \WC_Product $product Product or variation being rendered.
		 * @param string      $context Where the selector renders: 'page' on the product page,
		 *                             'variation' for one variation's selector.
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

		return sprintf(
			/* translators: 1: price, 2: billing interval. */
			__( 'Billed %1$s / %2$s', 'subscription' ),
			self::price_text( $price_num ),
			$every
		);
	}

	/**
	 * The heading that introduces a group's terms.
	 *
	 * @param string $type_key Plan type key.
	 *
	 * @return string
	 */
	private static function terms_heading( string $type_key ): string {
		switch ( $type_key ) {
			case 'subscribe_save':
				return __( 'Deliver every', 'subscription' );
			case 'installments':
				return __( 'Pay every', 'subscription' );
			default:
				return __( 'Billed every', 'subscription' );
		}
	}

	/**
	 * The regular price a term is struck against, per payment for installments.
	 *
	 * @param array  $row      Resolved plan row.
	 * @param string $type_key Plan type key.
	 *
	 * @return string Formatted price, empty when the plan has no regular price.
	 */
	private static function term_regular_price( array $row, string $type_key ): string {
		$data    = is_array( $row['relation_data'] ) ? $row['relation_data'] : [];
		$regular = isset( $data['regular_price'] ) && '' !== $data['regular_price'] ? (float) $data['regular_price'] : 0.0;

		if ( $regular <= 0 ) {
			return '';
		}

		if ( 'installments' === $type_key ) {
			$regular = (float) subscrpt_split_amounts( $regular, self::installment_count( $row ) )['per_installment'];
		}

		return wc_price( $regular );
	}

	/**
	 * What a term's chip reads: the interval ("1 month", "2 weeks"), unless the
	 * merchant's plan title is a name of its own ("Barista's pick"), which is kept.
	 *
	 * @param array  $row      Resolved plan row.
	 * @param string $type_key Plan type key; installments add their payment count.
	 *
	 * @return string
	 */
	private static function interval_text( array $row, string $type_key = 'recurring' ): string {
		$title = trim( (string) $row['plan_title'] );
		$freq  = max( 1, (int) $row['billing_frequency'] );
		$unit  = strtolower( PlanPresenter::interval_label( (int) $row['billing_interval'] ) );
		$every = $freq . ' ' . $unit . ( 1 === $freq ? '' : 's' );

		$phrase = '/^(every\s+)?(\d+\s*)?(day|week|month|year)s?$|^(daily|weekly|monthly|yearly|annual|annually|quarterly)$/i';

		$text = ( '' === $title || preg_match( $phrase, $title ) ) ? $every : $title;

		if ( 'installments' === $type_key ) {
			$count = self::installment_count( $row );
			$text  = sprintf(
				/* translators: 1: billing interval, e.g. "1 month", 2: number of payments. */
				_n( '%1$s · %2$d payment', '%1$s · %2$d payments', $count, 'subscription' ),
				$text,
				$count
			);
		}

		return $text;
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
