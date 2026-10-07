<?php
/**
 * What the plan selector layouts print for a group, worked out once.
 *
 * Every layout partial shows the same group — its label, price, badge, terms and
 * the merchant's storefront words — in a different shape. This reads a group
 * into the values those partials print, so the partials hold markup only.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Frontend;

/**
 * Plain values for the plan selector partials.
 */
class PlanSelectorView {

	/**
	 * The posted plan id the selector starts with: the first group's first term,
	 * or empty when the first group has none (One-Time).
	 *
	 * @param array $groups Plan groups.
	 *
	 * @return string
	 */
	public static function default_plan_id( array $groups ): string {
		return ! empty( $groups[0]['terms'] ) ? (string) $groups[0]['terms'][0]['id'] : '';
	}

	/**
	 * What a term strikes through: its regular price when it differs from the
	 * offer, so a term without a saving shows no struck price.
	 *
	 * @param array $plan_term A group's term.
	 *
	 * @return string
	 */
	public static function term_regular( array $plan_term ): string {
		$regular = isset( $plan_term['regular_price'] ) ? (string) $plan_term['regular_price'] : '';
		$offer   = isset( $plan_term['price'] ) ? (string) $plan_term['price'] : '';

		return wp_strip_all_tags( $regular ) === wp_strip_all_tags( $offer ) ? '' : $regular;
	}

	/**
	 * How a group lists its intervals: its own storefront choice, else the
	 * store's `subscrpt_plan_intervals_display`, else chips.
	 *
	 * @param array $group Plan group.
	 *
	 * @return string 'chips' or 'dropdown'.
	 */
	public static function intervals( array $group ): string {
		$own = isset( $group['storefront']['intervals'] ) ? $group['storefront']['intervals'] : '';
		if ( in_array( $own, [ 'chips', 'dropdown' ], true ) ) {
			return $own;
		}

		return 'dropdown' === get_option( 'subscrpt_plan_intervals_display', 'chips' ) ? 'dropdown' : 'chips';
	}

	/**
	 * The values a partial prints for one group.
	 *
	 * Keys:
	 * - `gid`              string  HTML id base, `subscrpt-grp-<group id>`.
	 * - `is_first`         bool    The pre-selected option.
	 * - `has_terms`        bool    The group has any terms.
	 * - `term_count`       int     How many.
	 * - `badge_text`       string  The badge shown first ('' when none).
	 * - `has_badge`        bool    A badge slot is needed (the group or any term has one).
	 * - `price`            string  Price HTML.
	 * - `first_regular`    string  The first term's struck regular price, '' when it saves nothing.
	 * - `tag`              string  The merchant's tag.
	 * - `benefits_heading` string  Heading over the benefits.
	 * - `benefits`         array   Up to five benefit lines.
	 * - `learn_url`        string  Learn-more link.
	 * - `learn_panel`      string  Details panel text, used when there is no link.
	 * - `learn_label`      string  Learn-more label, defaulted.
	 * - `intervals`        string  'chips' or 'dropdown', from `intervals()`.
	 * - `best_saving`      string  "Save up to N%" from the term that saves most, '' when none saves.
	 *
	 * @param array $group Plan group.
	 * @param int   $index Its position; the first is pre-selected.
	 *
	 * @return array
	 */
	public static function group( array $group, int $index ): array {
		$has_terms = ! empty( $group['terms'] );

		// The badge follows the selection, so the slot has to exist whenever any
		// term carries one — not only when the term shown first does.
		$badge_text = isset( $group['badge'] ) ? (string) $group['badge'] : '';
		$has_badge  = '' !== $badge_text;
		if ( ! $has_badge && $has_terms ) {
			foreach ( $group['terms'] as $plan_term ) {
				if ( ! empty( $plan_term['badge'] ) ) {
					$has_badge = true;
					break;
				}
			}
		}

		// What the merchant wrote for the group. Every value is escaped where it prints.
		$storefront = isset( $group['storefront'] ) && is_array( $group['storefront'] ) ? $group['storefront'] : [];
		$lines      = [];
		foreach ( isset( $storefront['benefits'] ) && is_array( $storefront['benefits'] ) ? $storefront['benefits'] : [] as $line ) {
			if ( '' !== self::text( $line ) ) {
				$lines[] = self::text( $line );
			}
		}
		$learn = isset( $storefront['learn_more'] ) && is_array( $storefront['learn_more'] ) ? $storefront['learn_more'] : [];
		$label = self::text( isset( $learn['label'] ) ? $learn['label'] : '' );

		$best = 0;
		foreach ( $has_terms ? $group['terms'] : [] as $plan_term ) {
			$best = max( $best, isset( $plan_term['discount_percent'] ) ? (int) $plan_term['discount_percent'] : 0 );
		}

		return [
			'gid'              => 'subscrpt-grp-' . sanitize_html_class( $group['id'] ),
			'is_first'         => 0 === $index,
			'has_terms'        => $has_terms,
			'term_count'       => $has_terms ? count( $group['terms'] ) : 0,
			'badge_text'       => $badge_text,
			'has_badge'        => $has_badge,
			'price'            => isset( $group['price'] ) ? (string) $group['price'] : '',
			'first_regular'    => $has_terms ? self::term_regular( $group['terms'][0] ) : '',
			'tag'              => self::text( isset( $storefront['tag'] ) ? $storefront['tag'] : '' ),
			'benefits_heading' => self::text( isset( $storefront['benefits_heading'] ) ? $storefront['benefits_heading'] : '' ),
			'benefits'         => array_slice( $lines, 0, 5 ),
			'learn_url'        => self::text( isset( $learn['url'] ) ? $learn['url'] : '' ),
			'learn_panel'      => self::text( isset( $learn['panel'] ) ? $learn['panel'] : '' ),
			'learn_label'      => '' !== $label ? $label : __( 'Learn more', 'subscription' ),
			'intervals'        => self::intervals( $group ),
			/* translators: %d: the largest discount among the group's terms, in percent. */
			'best_saving'      => $best > 0 ? sprintf( __( 'Save up to %d%%', 'subscription' ), $best ) : '',
		];
	}

	/**
	 * A storefront value as trimmed text; anything not scalar is empty.
	 *
	 * @param mixed $value Stored value.
	 *
	 * @return string
	 */
	private static function text( $value ): string {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
