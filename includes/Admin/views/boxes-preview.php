<?php
/**
 * Subscription Boxes page — preview with sample data.
 *
 * Shown until something registers `subscrpt_render_boxes_page`. Every figure on
 * this screen is invented: nothing here reads the database, and the customer
 * names are the ones the Delivery preview uses, so the two previews read as one
 * store rather than two unrelated demos.
 *
 * @package SpringDevs\Subscription\Admin
 */

defined( 'ABSPATH' ) || exit;

$upgrade_url = 'https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro';

// 1. Boxes renewing inside the horizon, soonest first.
$dummy_boxes = array(
	array(
		'subscription_id' => 1042,
		'customer'        => 'Sarah Johnson',
		'email'           => 'sarah.j@example.com',
		'box'             => 'Build a Box — 5 items',
		'contents'        => array( 'Ethiopia Yirgacheffe', 'Colombia Huila', 'Dark Roast Blend', 'Oat Milk 1L', 'Filter Papers' ),
		'status'          => 'open',
		'locks_on'        => '12 Oct',
		'renewal'         => '2026-10-15',
	),
	array(
		'subscription_id' => 1087,
		'customer'        => 'Marcus Williams',
		'email'           => 'marcus.w@example.com',
		'box'             => 'Build a Box — 3 items',
		'contents'        => array( 'Colombia Huila', 'Dark Roast Blend', 'Filter Papers' ),
		'status'          => 'locked',
		'locks_on'        => '12 Oct',
		'renewal'         => '2026-10-13',
	),
	array(
		'subscription_id' => 1103,
		'customer'        => 'Emma Clarke',
		'email'           => 'emma.c@example.com',
		'box'             => 'Curated Box — The October Box',
		'contents'        => array( 'Ethiopia Yirgacheffe', 'Kenya Nyeri AA', 'Tasting Notes Card' ),
		'status'          => 'locked',
		'locks_on'        => '12 Oct',
		'renewal'         => '2026-10-14',
	),
	array(
		'subscription_id' => 1155,
		'customer'        => 'Linda Zhao',
		'email'           => 'linda.z@example.com',
		'box'             => 'Build a Box — 5 items',
		'contents'        => array( 'Kenya Nyeri AA', 'Oat Milk 1L', 'Dark Roast Blend', 'Ethiopia Yirgacheffe', 'Filter Papers' ),
		'status'          => 'open',
		'locks_on'        => '19 Oct',
		'renewal'         => '2026-10-22',
	),
	array(
		'subscription_id' => 1189,
		'customer'        => 'James O\'Brien',
		'email'           => 'j.obrien@example.com',
		'box'             => 'Build a Box — 3 items',
		'contents'        => array( 'Colombia Huila', 'Oat Milk 1L', 'Tasting Notes Card' ),
		'status'          => 'open',
		'locks_on'        => '19 Oct',
		'renewal'         => '2026-10-24',
	),
);

$box_status_map = array(
	'open'   => array(
		'mod'   => 'active',
		/* translators: %s: date the box contents stop being editable, e.g. "12 Oct". */
		'label' => __( 'Open until %s', 'subscription' ),
	),
	'locked' => array(
		'mod'   => 'pending',
		'label' => __( 'Locked — packing', 'subscription' ),
	),
);

// 2. What the warehouse pulls off the shelf for the boxes above.
$dummy_pick_list = array(
	array(
		'product' => 'Ethiopia Yirgacheffe 250g',
		'units'   => 34,
		'stock'   => 60,
		'short'   => 0,
	),
	array(
		'product' => 'Colombia Huila 250g',
		'units'   => 28,
		'stock'   => 41,
		'short'   => 0,
	),
	array(
		'product' => 'Dark Roast Blend 250g',
		'units'   => 22,
		'stock'   => 14,
		'short'   => 8,
	),
	array(
		'product' => 'Oat Milk 1L',
		'units'   => 19,
		'stock'   => 25,
		'short'   => 0,
	),
	array(
		'product' => 'Filter Papers (100)',
		'units'   => 12,
		'stock'   => 30,
		'short'   => 0,
	),
);

// 3. Three cycles of one curated box product, planned ahead.
$dummy_editions = array(
	array(
		'title'    => 'The October Box',
		'status'   => 'publish',
		'applies'  => '2026-10-15',
		'contents' => array( 'Ethiopia Yirgacheffe 250g', 'Kenya Nyeri AA 250g', 'Tasting Notes Card' ),
	),
	array(
		'title'    => 'The November Box',
		'status'   => 'draft',
		'applies'  => '2026-11-15',
		'contents' => array( 'Colombia Huila 250g', 'Guatemala Antigua 250g', 'Tasting Notes Card' ),
	),
	array(
		'title'    => 'The December Box',
		'status'   => 'draft',
		'applies'  => '2026-12-15',
		'contents' => array( 'Festive Blend 250g', 'Ethiopia Yirgacheffe 250g', 'Ceramic Dripper' ),
	),
);

$edition_status_map = array(
	'publish' => array(
		'mod'   => 'active',
		'label' => __( 'Published', 'subscription' ),
	),
	'draft'   => array(
		'mod'   => 'draft',
		'label' => __( 'Draft', 'subscription' ),
	),
);

// 4. The same box, from the customer's side of the account page.
$dummy_customer_box = array(
	'customer' => 'Sarah Johnson',
	'box'      => 'Build a Box — 5 items',
	'renewal'  => '2026-10-15',
	'contents' => array( 'Ethiopia Yirgacheffe', 'Colombia Huila', 'Dark Roast Blend', 'Oat Milk 1L', 'Filter Papers' ),
	'locks_on' => __( 'Monday 12 October', 'subscription' ),
);
?>

<div class="wp-subscription-admin-content list-page">

	<!-- Disclaimer banner -->
	<div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;">
		<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#d97706" style="flex-shrink:0;margin-top:1px;" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
		<p style="margin:0;font-size:13px;color:#92400e;line-height:1.5;">
			<strong><?php esc_html_e( 'Preview with sample data.', 'subscription' ); ?></strong>
			<?php esc_html_e( 'Subscription Boxes is on its way. Every box, product and date below is an example, so you can see how the screen will work before it ships.', 'subscription' ); ?>
		</p>
	</div>

	<?php
	wpsubs_render_page_header(
		array(
			'title'       => __( 'Subscription Boxes', 'subscription' ),
			'description' => __( 'Plan box editions, lock contents before renewal, and pack what is due.', 'subscription' ),
		)
	);
	?>

	<!-- 1. Upcoming boxes -->
	<h2 style="font-size:14px;font-weight:600;color:var(--wpsubs-text);margin:20px 0 10px;"><?php esc_html_e( 'Upcoming boxes', 'subscription' ); ?></h2>
	<p style="margin:0 0 12px;font-size:13px;color:var(--wpsubs-text-muted);"><?php esc_html_e( 'Box subscriptions renewing within the next 14 days, soonest first.', 'subscription' ); ?></p>

	<div class="wpsubs-table-card">
		<table class="wpsubs-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Customer', 'subscription' ); ?></th>
					<th><?php esc_html_e( 'Box', 'subscription' ); ?></th>
					<th><?php esc_html_e( 'Contents', 'subscription' ); ?></th>
					<th><?php esc_html_e( 'Status', 'subscription' ); ?></th>
					<th class="wpsubs-col--nowrap"><?php esc_html_e( 'Renewal date', 'subscription' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $dummy_boxes as $subscrpt_box ) : ?>
					<?php
					$subscrpt_name_parts = array_values( array_filter( explode( ' ', $subscrpt_box['customer'] ) ) );
					$subscrpt_initials   = '';
					foreach ( array_slice( $subscrpt_name_parts, 0, 2 ) as $subscrpt_part ) {
						$subscrpt_initials .= strtoupper( $subscrpt_part[0] );
					}
					$subscrpt_color_slot = ord( strtolower( $subscrpt_initials[0] ?? 'a' ) ) % 8;

					$subscrpt_badge = $box_status_map[ $subscrpt_box['status'] ] ?? $box_status_map['open'];
					$subscrpt_label = 'open' === $subscrpt_box['status']
						? sprintf( $subscrpt_badge['label'], $subscrpt_box['locks_on'] )
						: $subscrpt_badge['label'];

					$subscrpt_shown     = array_slice( $subscrpt_box['contents'], 0, 3 );
					$subscrpt_remaining = count( $subscrpt_box['contents'] ) - count( $subscrpt_shown );
					?>
					<tr>
						<td>
							<div class="wpsubs-customer">
								<div class="wpsubs-avatar" data-color="<?php echo (int) $subscrpt_color_slot; ?>"><?php echo esc_html( $subscrpt_initials ); ?></div>
								<div class="wpsubs-customer__info">
									<span class="wpsubs-customer__name"><?php echo esc_html( $subscrpt_box['customer'] ); ?></span>
									<span class="wpsubs-customer__sub"><?php echo esc_html( $subscrpt_box['email'] ); ?></span>
								</div>
							</div>
						</td>
						<td>
							<span class="wpsubs-cell-title" style="font-weight:600;"><?php echo esc_html( $subscrpt_box['box'] ); ?></span>
							<span class="wpsubs-cell-id">#<?php echo esc_html( $subscrpt_box['subscription_id'] ); ?></span>
						</td>
						<td>
							<span class="wpsubs-cell-title" style="font-weight:400;"><?php echo esc_html( implode( ', ', $subscrpt_shown ) ); ?></span>
							<?php if ( $subscrpt_remaining > 0 ) : ?>
								<span class="wpsubs-cell-id">
									<?php
									printf(
										/* translators: %d: number of further items in the box. */
										esc_html( _n( '+%d more item', '+%d more items', $subscrpt_remaining, 'subscription' ) ),
										(int) $subscrpt_remaining
									);
									?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<span class="wpsubs-badge wpsubs-badge--<?php echo esc_attr( $subscrpt_badge['mod'] ); ?>"><?php echo esc_html( $subscrpt_label ); ?></span>
						</td>
						<td class="wpsubs-col--nowrap">
							<span class="wpsubs-cell-title" style="font-weight:400;"><?php echo esc_html( $subscrpt_box['renewal'] ); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- 2. Pick list -->
	<h2 style="font-size:14px;font-weight:600;color:var(--wpsubs-text);margin:28px 0 10px;"><?php esc_html_e( 'Pick list', 'subscription' ); ?></h2>
	<p style="margin:0 0 12px;font-size:13px;color:var(--wpsubs-text-muted);"><?php esc_html_e( 'Everything the boxes above add up to, against what is on the shelf.', 'subscription' ); ?></p>

	<div class="wpsubs-table-card">
		<table class="wpsubs-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'subscription' ); ?></th>
					<th class="wpsubs-col--nowrap"><?php esc_html_e( 'Units to pack', 'subscription' ); ?></th>
					<th class="wpsubs-col--nowrap"><?php esc_html_e( 'In stock', 'subscription' ); ?></th>
					<th class="wpsubs-col--nowrap"><?php esc_html_e( 'Short by', 'subscription' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $dummy_pick_list as $subscrpt_pick ) : ?>
					<?php $subscrpt_is_short = $subscrpt_pick['short'] > 0; ?>
					<tr<?php echo $subscrpt_is_short ? ' style="background:#fef2f2;"' : ''; ?>>
						<td>
							<span class="wpsubs-cell-title" style="font-weight:600;"><?php echo esc_html( $subscrpt_pick['product'] ); ?></span>
							<?php if ( $subscrpt_is_short ) : ?>
								<span class="wpsubs-cell-id" style="color:#b91c1c;"><?php esc_html_e( 'Reorder before packing day', 'subscription' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="wpsubs-col--nowrap">
							<span class="wpsubs-cell-title" style="font-weight:400;font-variant-numeric:tabular-nums;"><?php echo esc_html( number_format_i18n( $subscrpt_pick['units'] ) ); ?></span>
						</td>
						<td class="wpsubs-col--nowrap">
							<span class="wpsubs-cell-title" style="font-weight:400;font-variant-numeric:tabular-nums;<?php echo $subscrpt_is_short ? 'color:#dc2626;' : ''; ?>"><?php echo esc_html( number_format_i18n( $subscrpt_pick['stock'] ) ); ?></span>
						</td>
						<td class="wpsubs-col--nowrap">
							<?php if ( $subscrpt_is_short ) : ?>
								<span class="wpsubs-badge wpsubs-badge--expired"><?php echo esc_html( number_format_i18n( $subscrpt_pick['short'] ) ); ?></span>
							<?php else : ?>
								<span style="color:var(--wpsubs-text-subtle);">&#8212;</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- 3. Edition planner -->
	<h2 style="font-size:14px;font-weight:600;color:var(--wpsubs-text);margin:28px 0 10px;"><?php esc_html_e( 'Edition planner', 'subscription' ); ?></h2>
	<p style="margin:0 0 12px;font-size:13px;color:var(--wpsubs-text-muted);"><?php esc_html_e( 'Three cycles of the Curated Box, planned ahead. A draft edition applies from the renewal date it names, so next month can be built while this month ships.', 'subscription' ); ?></p>

	<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:8px;">
		<?php foreach ( $dummy_editions as $subscrpt_edition ) : ?>
			<?php
			$subscrpt_edition_badge = $edition_status_map[ $subscrpt_edition['status'] ] ?? $edition_status_map['draft'];
			?>
			<div style="flex:1;min-width:240px;border:1px solid var(--wpsubs-border);border-radius:12px;padding:16px;background:var(--wpsubs-surface);">
				<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;">
					<span style="font-size:14px;font-weight:600;color:var(--wpsubs-text);"><?php echo esc_html( $subscrpt_edition['title'] ); ?></span>
					<span class="wpsubs-badge wpsubs-badge--<?php echo esc_attr( $subscrpt_edition_badge['mod'] ); ?>"><?php echo esc_html( $subscrpt_edition_badge['label'] ); ?></span>
				</div>
				<ul style="margin:0 0 12px;padding-left:18px;font-size:13px;color:var(--wpsubs-text-muted);line-height:1.7;">
					<?php foreach ( $subscrpt_edition['contents'] as $subscrpt_item ) : ?>
						<li><?php echo esc_html( $subscrpt_item ); ?></li>
					<?php endforeach; ?>
				</ul>
				<div style="font-size:12px;color:var(--wpsubs-text-subtle);border-top:1px dashed var(--wpsubs-border);padding-top:10px;">
					<?php
					printf(
						/* translators: %s: renewal date the edition first applies to, e.g. "2026-10-15". */
						esc_html__( 'Applies from renewals on %s', 'subscription' ),
						esc_html( $subscrpt_edition['applies'] )
					);
					?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- 4. The customer's view -->
	<h2 style="font-size:14px;font-weight:600;color:var(--wpsubs-text);margin:28px 0 10px;"><?php esc_html_e( 'Your next box, as the customer sees it', 'subscription' ); ?></h2>
	<p style="margin:0 0 12px;font-size:13px;color:var(--wpsubs-text-muted);"><?php esc_html_e( 'The same box on the customer\'s account page, while the contents can still be changed.', 'subscription' ); ?></p>

	<div style="max-width:420px;border:1px solid var(--wpsubs-border);border-radius:12px;padding:18px;background:var(--wpsubs-surface);box-shadow:var(--wpsubs-shadow);">
		<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:4px;">
			<span style="font-size:14px;font-weight:600;color:var(--wpsubs-text);"><?php esc_html_e( 'Your next box', 'subscription' ); ?></span>
			<span class="wpsubs-badge wpsubs-badge--active"><?php echo esc_html( $dummy_customer_box['renewal'] ); ?></span>
		</div>
		<div style="font-size:12px;color:var(--wpsubs-text-subtle);margin-bottom:12px;"><?php echo esc_html( $dummy_customer_box['box'] ); ?></div>
		<ul style="margin:0 0 14px;padding-left:18px;font-size:13px;color:var(--wpsubs-text);line-height:1.8;">
			<?php foreach ( $dummy_customer_box['contents'] as $subscrpt_item ) : ?>
				<li><?php echo esc_html( $subscrpt_item ); ?></li>
			<?php endforeach; ?>
		</ul>
		<div style="display:flex;align-items:center;gap:8px;border-top:1px dashed var(--wpsubs-border);padding-top:12px;">
			<svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="flex-shrink:0;color:var(--wpsubs-text-subtle);" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="M8 11V7a4 4 0 018 0v4"/></svg>
			<span style="font-size:12px;color:var(--wpsubs-text-muted);">
				<?php
				printf(
					/* translators: %s: day the box contents stop being editable, e.g. "Monday 12 October". */
					esc_html__( 'Changes lock on %s', 'subscription' ),
					esc_html( $dummy_customer_box['locks_on'] )
				);
				?>
			</span>
		</div>
	</div>

	<!-- Upgrade CTA -->
	<div style="margin-top:28px;border:1px solid var(--wpsubs-border);border-radius:12px;padding:20px;background:var(--wpsubs-surface-muted);display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
		<div style="flex:1;min-width:260px;">
			<h2 style="margin:0 0 6px;font-size:15px;font-weight:600;color:var(--wpsubs-text);"><?php esc_html_e( 'Subscription Boxes is what WPSubscription Pro will ship.', 'subscription' ); ?></h2>
			<p style="margin:0;font-size:13px;line-height:1.6;color:var(--wpsubs-text-muted);">
				<?php esc_html_e( 'Build-a-box and curated box products, a lock window before each renewal, the pick list your warehouse packs from, and editions planned months ahead — all of it arrives with Pro.', 'subscription' ); ?>
			</p>
		</div>
		<a class="wpsubs-btn wpsubs-btn--primary" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noreferrer noopener" style="flex-shrink:0;">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="margin-right:6px;"><path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z"/></svg>
			<?php esc_html_e( 'Upgrade to Pro', 'subscription' ); ?>
		</a>
	</div>

</div>
