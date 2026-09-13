<?php
/**
 * Subscription settings admin view.
 *
 * One form, every panel rendered, one panel visible. The form posts to
 * `options.php`, which saves whatever it is given — so a panel that is not
 * rendered is a panel whose settings do not save. Hiding is therefore CSS, not
 * a conditional, and every one of the fields below submits on every save
 * whichever section happens to be open.
 *
 * One level of navigation: the rail picks a section and the section's panel
 * stacks whatever groups belong to it. A section holding a single group drops
 * that group's heading, because the rail item beside it already says the same
 * word.
 *
 * @package SpringDevs\Subscription\Admin
 *
 * @var array  $settings_fields Grouped and sorted settings fields.
 * @var string $active_cat      Section currently open.
 * @var array  $category_groups Section key => ordered group keys (empty ones dropped).
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SpringDevs\Subscription\Admin\SettingsHelper;

wp_enqueue_style( 'wp-subscription-admin-settings', SUBSCRPT_ASSETS . '/css/admin-settings.css', [], SUBSCRPT_VERSION );
wp_enqueue_script( 'wp-subscription-admin-settings', SUBSCRPT_ASSETS . '/js/admin-settings.js', [ 'jquery' ], SUBSCRPT_VERSION, true );

$subscrpt_settings_base = admin_url( 'admin.php?page=wp-subscription-settings' );
?>
<div class="wp-subscription-admin-content list-page">

	<form method="post" action="options.php" class="subscrpt-settings" data-subscrpt-settings>
		<?php settings_fields( 'wp_subscription_settings' ); ?>
		<?php do_settings_sections( 'wp_subscription_settings' ); ?>

		<?php // Page header: matches the other admin pages (Subscriptions, Integrations) — title + one-line description + dashed rule — with Save pinned to the right of the title row. ?>
		<div style="margin-bottom:20px;">
			<div style="display:flex;align-items:flex-start;gap:12px;">
				<div style="flex:1;min-width:0;">
					<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0 0 6px;line-height:1.2;"><?php esc_html_e( 'Settings', 'subscription' ); ?></h1>
					<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0 0 12px;line-height:1.5;"><?php esc_html_e( 'Configure how subscriptions renew, charge and behave for your customers.', 'subscription' ); ?></p>
				</div>
				<button type="submit" class="wpsubs-btn wpsubs-btn--primary subscrpt-settings__save">
					<?php esc_html_e( 'Save changes', 'subscription' ); ?>
				</button>
			</div>
			<div style="border-top:1px dashed #d0d3d7;"></div>
		</div>

		<div class="subscrpt-settings__layout">

			<aside class="subscrpt-settings__sidebar">
				<nav class="wpsubs-vnav" aria-label="<?php esc_attr_e( 'Settings sections', 'subscription' ); ?>">
					<?php $subscrpt_all_active = 'all' === $active_cat; ?>
					<a
						class="wpsubs-vnav__item<?php echo $subscrpt_all_active ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'cat', 'all', $subscrpt_settings_base ) ); ?>"
						data-subscrpt-cat="all"
						aria-current="<?php echo $subscrpt_all_active ? 'page' : 'false'; ?>"
					>
						<span class="wpsubs-vnav__label"><?php esc_html_e( 'All Settings', 'subscription' ); ?></span>
					</a>
					<?php
					$subscrpt_cat_labels = SettingsHelper::categories();
					foreach ( $category_groups as $subscrpt_cat_id => $subscrpt_cat_group_ids ) :
						$subscrpt_cat_active = $subscrpt_cat_id === $active_cat;
						?>
					<a
						class="wpsubs-vnav__item<?php echo $subscrpt_cat_active ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'cat', $subscrpt_cat_id, $subscrpt_settings_base ) ); ?>"
						data-subscrpt-cat="<?php echo esc_attr( $subscrpt_cat_id ); ?>"
						aria-current="<?php echo $subscrpt_cat_active ? 'page' : 'false'; ?>"
					>
						<span class="wpsubs-vnav__label"><?php echo esc_html( $subscrpt_cat_labels[ $subscrpt_cat_id ] ?? $subscrpt_cat_id ); ?></span>
						<?php if ( SettingsHelper::category_is_pro_locked( $subscrpt_cat_group_ids, $settings_fields ) ) : ?>
							<?php echo wp_kses_post( SettingsHelper::pro_badge_html() ); ?>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
				</nav>
			</aside>

			<div class="subscrpt-settings__content">

				<div class="subscrpt-settings__panels">
					<?php
					// One card per settings group, so groups stay visually separate
					// instead of running together in a single section card. The rail
					// shows a group's whole section; "All Settings" shows every card.
					foreach ( $category_groups as $subscrpt_cat_id => $subscrpt_cat_group_ids ) :
						$subscrpt_cat_open = 'all' === $active_cat || $subscrpt_cat_id === $active_cat;
						foreach ( $subscrpt_cat_group_ids as $subscrpt_group_id ) :
							$subscrpt_group  = $settings_fields[ $subscrpt_group_id ] ?? array();
							$subscrpt_fields = array_values( $subscrpt_group['fields'] ?? array() );
							$subscrpt_count  = count( $subscrpt_fields );
							if ( 0 === $subscrpt_count ) {
								continue;
							}
							$subscrpt_label = SettingsHelper::group_label( $subscrpt_group_id, $subscrpt_group );
							?>
							<section
								class="subscrpt-settings__panel wpsubs-table-card"
								id="subscrpt-panel-<?php echo esc_attr( $subscrpt_group_id ); ?>"
								role="region"
								aria-label="<?php echo esc_attr( $subscrpt_label ); ?>"
								data-subscrpt-panel="<?php echo esc_attr( $subscrpt_group_id ); ?>"
								data-subscrpt-cat="<?php echo esc_attr( $subscrpt_cat_id ); ?>"
								<?php echo $subscrpt_cat_open ? '' : 'hidden'; ?>
							>
								<?php foreach ( $subscrpt_fields as $subscrpt_idx => $subscrpt_field ) : ?>
									<?php
									$subscrpt_type = $subscrpt_field['type'] ?? 'input';
									SettingsHelper::render_settings_field( $subscrpt_type, $subscrpt_field['field_data'] ?? array() );

									// No rule before a heading — the heading is itself the break.
									$subscrpt_next = $subscrpt_fields[ $subscrpt_idx + 1 ] ?? null;
									$subscrpt_rule = 'heading' !== $subscrpt_type
										&& $subscrpt_idx + 1 < $subscrpt_count
										&& 'heading' !== ( $subscrpt_next['type'] ?? '' );
									?>
									<?php if ( $subscrpt_rule ) : ?>
										<div class="subscrpt-settings__rule"></div>
									<?php endif; ?>
								<?php endforeach; ?>
							</section>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>

			</div>

		</div>
	</form>

</div>
