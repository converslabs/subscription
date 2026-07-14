<?php
/**
 * Plan detail - Selling Plans tab. Terms render as a flat table: name +
 * breakdown on the left, actions (active toggle / edit / delete) on the right.
 *
 * @var array $plan Plan (PlanPresenter shape).
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div style="padding-top:8px;">

	<?php if ( empty( $plan['terms'] ) ) : ?>
		<div class="wpsubs-empty">
			<div class="wpsubs-empty__icon">🗓️</div>
			<h3 class="wpsubs-empty__title"><?php esc_html_e( 'No selling plans yet', 'subscription' ); ?></h3>
			<p class="wpsubs-empty__desc"><?php esc_html_e( 'A selling plan sets how often the customer is charged, like every month or every year. Add at least one so this plan group can be sold.', 'subscription' ); ?></p>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" style="margin-top:20px;" data-wpsubs-modal-open="subscrpt-term-modal" data-subscrpt-add-term>
				<?php esc_html_e( 'Add your first selling plan', 'subscription' ); ?>
			</button>
		</div>
	<?php else : ?>
		<div class="wpsubs-table-card">
			<table class="wpsubs-table">
				<tbody>
					<?php foreach ( $plan['terms'] as $selling_term ) : ?>
						<tr data-term-id="<?php echo esc_attr( $selling_term['id'] ); ?>">
							<td>
								<span style="font-weight:600;"><?php echo esc_html( $selling_term['name'] ); ?></span>
								<?php if ( 'draft' === $selling_term['status'] ) : ?>
									<span class="wpsubs-badge wpsubs-badge--draft" style="margin-left:6px;"><?php esc_html_e( 'Draft', 'subscription' ); ?></span>
								<?php endif; ?>
								<span style="display:block;margin-top:2px;font-size:13px;color:var(--wpsubs-text-muted);"><?php echo esc_html( $selling_term['breakdown'] ); ?></span>
								<?php if ( ! empty( $selling_term['chips'] ) ) : ?>
									<span style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
										<?php foreach ( $selling_term['chips'] as $chip ) : ?>
											<span class="wpsubs-badge wpsubs-badge--neutral" style="font-weight:400;"><?php echo esc_html( $chip ); ?></span>
										<?php endforeach; ?>
									</span>
								<?php endif; ?>
							</td>
							<td style="width:1%;white-space:nowrap;">
								<div style="display:flex;align-items:center;gap:8px;justify-content:flex-end;">
									<label class="wpsubs-settings-toggle-label">
										<span class="wpsubs-settings-toggle-label__text"><?php esc_html_e( 'Active', 'subscription' ); ?></span>
										<input type="checkbox" class="wpsubs-toggle" data-subscrpt-toggle-term="<?php echo esc_attr( $selling_term['id'] ); ?>" <?php checked( 'active', $selling_term['status'] ); ?> />
										<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
									</label>
									<button type="button" class="wpsubs-btn wpsubs-btn--icon wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-edit-term="<?php echo esc_attr( $selling_term['id'] ); ?>" aria-label="<?php esc_attr_e( 'Edit', 'subscription' ); ?>" title="<?php esc_attr_e( 'Edit', 'subscription' ); ?>">
										<span class="dashicons dashicons-edit"></span>
									</button>
									<button type="button" class="wpsubs-btn wpsubs-btn--icon wpsubs-btn--outline wpsubs-btn--sm wpsubs-btn--danger" data-subscrpt-delete-term="<?php echo esc_attr( $selling_term['id'] ); ?>" aria-label="<?php esc_attr_e( 'Delete', 'subscription' ); ?>" title="<?php esc_attr_e( 'Delete', 'subscription' ); ?>">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<button type="button" class="wpsubs-btn wpsubs-btn--outline" style="margin-top:16px;" data-wpsubs-modal-open="subscrpt-term-modal" data-subscrpt-add-term>
			<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:1;"></span>
			<?php esc_html_e( 'Add Selling Plan', 'subscription' ); ?>
		</button>
	<?php endif; ?>

</div>
