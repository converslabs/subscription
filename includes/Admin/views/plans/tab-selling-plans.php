<?php
/**
 * Plan detail - Selling Plans tab. Terms render as a wpsubs-accordion; each
 * term's actions (edit / delete / active toggle) live inside its panel so the
 * accordion header stays a single valid button.
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
		<div class="wpsubs-accordion" data-multi="1">
			<?php
			foreach ( $plan['terms'] as $selling_term ) :
				$panel_id = 'subscrpt-term-panel-' . (int) $selling_term['id'];
				?>
				<div class="wpsubs-accordion__item" data-term-id="<?php echo esc_attr( $selling_term['id'] ); ?>">
					<button type="button" class="wpsubs-accordion__header" aria-controls="<?php echo esc_attr( $panel_id ); ?>" aria-expanded="false">
						<span>
							<?php echo esc_html( $selling_term['name'] ); ?>
							<?php if ( 'draft' === $selling_term['status'] ) : ?>
								<span class="wpsubs-badge wpsubs-badge--draft"><?php esc_html_e( 'Draft', 'subscription' ); ?></span>
							<?php endif; ?>
						</span>
					</button>
					<div class="wpsubs-accordion__panel" id="<?php echo esc_attr( $panel_id ); ?>" hidden>
						<p style="margin-top:0;color:var(--wpsubs-text-muted,#6b7280);">
							<?php echo esc_html( $selling_term['breakdown'] ); ?>
						</p>
						<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
							<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-edit-term="<?php echo esc_attr( $selling_term['id'] ); ?>">
								<span class="dashicons dashicons-edit" style="margin-right:4px;"></span><?php esc_html_e( 'Edit', 'subscription' ); ?>
							</button>
							<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm wpsubs-btn--danger" data-subscrpt-delete-term="<?php echo esc_attr( $selling_term['id'] ); ?>">
								<span class="dashicons dashicons-trash" style="margin-right:4px;"></span><?php esc_html_e( 'Delete', 'subscription' ); ?>
							</button>
							<label class="wpsubs-settings-toggle-label" style="margin-left:auto;">
								<span class="wpsubs-settings-toggle-label__text"><?php esc_html_e( 'Active', 'subscription' ); ?></span>
								<input type="checkbox" class="wpsubs-toggle" data-subscrpt-toggle-term="<?php echo esc_attr( $selling_term['id'] ); ?>" <?php checked( 'active', $selling_term['status'] ); ?> />
								<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
							</label>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<button type="button" class="wpsubs-btn wpsubs-btn--outline" style="margin-top:16px;" data-wpsubs-modal-open="subscrpt-term-modal" data-subscrpt-add-term>
			<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:1;"></span>
			<?php esc_html_e( 'Add Selling Plan', 'subscription' ); ?>
		</button>
	<?php endif; ?>

</div>
