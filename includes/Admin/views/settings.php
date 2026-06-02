<?php
/**
 * Subscription settings admin view.
 *
 * @package SpringDevs\Subscription\Admin
 *
 * @var array $settings_fields Grouped and sorted settings fields.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'wp-subscription-admin-settings', SUBSCRPT_ASSETS . '/css/admin-settings.css', [], SUBSCRPT_VERSION );
wp_enqueue_script( 'wp-subscription-admin-settings', SUBSCRPT_ASSETS . '/js/admin-settings.js', [ 'jquery' ], SUBSCRPT_VERSION, true );
?>
<div class="wp-subscription-admin-content list-page subscrpt-subs-list">

	<!-- Page header -->
	<div style="margin-bottom:20px;">
		<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0 0 6px;line-height:1.2;"><?php esc_html_e( 'Settings', 'subscription' ); ?></h1>
		<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0 0 12px;line-height:1.5;"><?php esc_html_e( 'Configure subscription plugin behavior and features.', 'subscription' ); ?></p>
		<div style="border-top:1px dashed #d0d3d7;"></div>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'wp_subscription_settings' ); ?>
		<?php do_settings_sections( 'wp_subscription_settings' ); ?>

		<?php foreach ( $settings_fields as $group_id => $group ) : ?>
			<?php
			$fields      = array_values( $group['fields'] ?? array() );
			$field_count = count( $fields );
			?>
			<div class="wpsubs-table-card" style="margin-bottom:16px;padding:20px 24px;">
				<?php foreach ( $fields as $idx => $field ) : ?>
					<?php
					$field_type = $field['type'] ?? 'input';
					$field_data = $field['field_data'] ?? array();
					SpringDevs\Subscription\Admin\SettingsHelper::render_settings_field( $field_type, $field_data );
					?>
					<?php if ( 'heading' !== $field_type && $idx + 1 < $field_count ) : ?>
						<div style="border-top:1px solid #f1f5f9;margin:16px 0;"></div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>

		<div style="margin-top:8px;">
			<button type="submit" class="wpsubs-btn wpsubs-btn--primary">
				<?php esc_html_e( 'Save changes', 'subscription' ); ?>
			</button>
		</div>
	</form>

</div>
