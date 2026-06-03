<?php
/**
 * Integrations Admin Page
 *
 * Displays payment gateways and third-party integrations as cards.
 *
 * @package SpringDevs\Subscription\Admin
 *
 * @var array $integrations Filtered integrations list.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'wp-subscription-admin-components', SUBSCRPT_ASSETS . '/css/admin-components.css', [], SUBSCRPT_VERSION );

// Split by type.
$payment_gateways = array_filter(
	$integrations,
	function ( $i ) {
		return ( $i['type'] ?? 'payment_gateway' ) === 'payment_gateway';
	}
);
$third_party      = array_filter(
	$integrations,
	function ( $i ) {
		return ( $i['type'] ?? '' ) === 'third_party';
	}
);

// Category config.
$category_config = [
	'lms'        => [
		'label' => __( 'LMS', 'subscription' ),
		'bg'    => '#ede9fe',
		'color' => '#6d28d9',
	],
	'crm'        => [
		'label' => __( 'CRM', 'subscription' ),
		'bg'    => '#fce7f3',
		'color' => '#9d174d',
	],
	'automation' => [
		'label' => __( 'Automation', 'subscription' ),
		'bg'    => '#e0f2fe',
		'color' => '#0369a1',
	],
	'email'      => [
		'label' => __( 'Email', 'subscription' ),
		'bg'    => '#dcfce7',
		'color' => '#166534',
	],
	'license'    => [
		'label' => __( 'License', 'subscription' ),
		'bg'    => '#fef3c7',
		'color' => '#92400e',
	],
];
?>

<div class="wp-subscription-admin-content list-page subscrpt-subs-list">

	<!-- Page header -->
	<div style="margin-bottom:20px;">
		<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0 0 6px;line-height:1.2;"><?php esc_html_e( 'Integrations', 'subscription' ); ?></h1>
		<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0 0 12px;line-height:1.5;"><?php esc_html_e( 'Connect your subscriptions with payment gateways and third-party plugins.', 'subscription' ); ?></p>
		<div style="border-top:1px dashed #d0d3d7;"></div>
	</div>

	<?php if ( ! empty( $_GET['subscrpt_installed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible" style="margin:0 0 16px;"><p><?php esc_html_e( 'Plugin installed and activated successfully.', 'subscription' ); ?></p></div>
	<?php endif; ?>

	<!-- Payment Gateways -->
	<div style="margin-bottom:32px;">
		<div style="margin-bottom:12px;">
			<h2 style="font-size:12px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.06em;margin:0 0 3px;line-height:1.4;margin-left:1px;"><?php esc_html_e( 'Payment Gateways', 'subscription' ); ?></h2>
			<p style="font-size:12px;color:var(--wpsubs-text-muted);margin:0;line-height:1.5;margin-left:1px;"><?php esc_html_e( 'Enable and configure payment methods that support recurring billing for subscription products.', 'subscription' ); ?></p>
		</div>
		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
			<?php foreach ( $payment_gateways as $integration ) : ?>
				<?php
				$is_installed = ! empty( $integration['is_installed'] );
				$is_active    = ! empty( $integration['is_active'] );
				$is_beta      = ! empty( $integration['is_beta'] );
				$is_pro       = ! empty( $integration['is_pro'] );
				$icon_url     = $integration['icon_url'] ?? '';
				$icon_initial = $integration['icon_initial'] ?? strtoupper( substr( $integration['title'], 0, 2 ) );
				$icon_color   = $integration['icon_color'] ?? '#64748b';

				if ( $is_active ) {
					$status_dot  = '#16a34a';
					$status_text = __( 'Active', 'subscription' );
				} elseif ( $is_installed ) {
					$status_dot  = '#d97706';
					$status_text = __( 'Inactive', 'subscription' );
				} else {
					$status_dot  = '#9ca3af';
					$status_text = __( 'Not Installed', 'subscription' );
				}
				?>
				<div class="wpsubs-table-card" style="padding:16px;display:flex;flex-direction:column;gap:12px;">

					<!-- Header: icon + name + status -->
					<div style="display:flex;gap:10px;align-items:flex-start;">
						<div style="width:40px;height:40px;flex-shrink:0;border-radius:8px;overflow:hidden;border:1px solid var(--wpsubs-border);background:var(--wpsubs-surface-muted);display:flex;align-items:center;justify-content:center;">
							<?php if ( $icon_url ) : ?>
								<img src="<?php echo esc_url( $icon_url ); ?>" style="width:100%;height:100%;object-fit:contain;" alt="" />
							<?php else : ?>
								<span style="font-size:11px;font-weight:700;color:#fff;background:<?php echo esc_attr( $icon_color ); ?>;width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><?php echo esc_html( $icon_initial ); ?></span>
							<?php endif; ?>
						</div>
						<div style="flex:1;min-width:0;">
							<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:6px;margin-bottom:5px;">
								<span style="font-size:13px;font-weight:600;color:var(--wpsubs-text);line-height:1.3;"><?php echo esc_html( $integration['title'] ); ?></span>
								<span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:500;color:<?php echo esc_attr( $status_dot ); ?>;white-space:nowrap;flex-shrink:0;">
									<span style="width:7px;height:7px;border-radius:50%;background:<?php echo esc_attr( $status_dot ); ?>;flex-shrink:0;"></span>
									<?php echo esc_html( $status_text ); ?>
								</span>
							</div>
							<div style="display:flex;flex-wrap:wrap;gap:4px;">
								<?php if ( $is_pro ) : ?>
									<span style="display:inline-flex;align-items:center;font-size:10px;font-weight:600;padding:2px 7px;border-radius:10px;background:#f5f3ff;color:#7c3aed;line-height:1.6;"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
								<?php endif; ?>
								<?php if ( $is_beta ) : ?>
									<span style="display:inline-flex;align-items:center;font-size:10px;font-weight:500;padding:2px 7px;border-radius:10px;background:#fff7ed;color:#c2410c;line-height:1.6;"><?php esc_html_e( 'Beta', 'subscription' ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $integration['supports_recurring'] ) ) : ?>
									<span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:500;padding:2px 7px;border-radius:10px;background:#dcfce7;color:#166534;line-height:1.6;">
										<svg width="10" height="10" viewBox="0 0 24 24" fill="none"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="#166534"/></svg>
										<?php esc_html_e( 'Automatic recurring', 'subscription' ); ?>
									</span>
								<?php else : ?>
									<span style="display:inline-flex;align-items:center;font-size:10px;font-weight:500;padding:2px 7px;border-radius:10px;background:#fff3e0;color:#c2410c;line-height:1.6;"><?php esc_html_e( 'Manual renewals only', 'subscription' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- Description -->
					<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.5;"><?php echo esc_html( $integration['description'] ?? '' ); ?></p>

					<!-- Separator -->
					<div style="border-top:1px solid var(--wpsubs-border);margin:auto -16px 0;"></div>

					<!-- Actions -->
					<div style="display:flex;gap:6px;flex-wrap:wrap;">
						<?php if ( $is_pro && ! defined( 'SUBSCRIPT_PRO_VERSION' ) ) : ?>
							<div style="width:100%;display:flex;align-items:center;gap:6px;background:#f5f3ff;border-radius:6px;padding:7px 10px;font-size:12px;font-weight:500;color:#7c3aed;line-height:1.4;">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
								<?php esc_html_e( 'WPSubscription Pro required', 'subscription' ); ?>
							</div>
						<?php else : ?>
							<?php
							foreach ( $integration['actions'] as $action ) :
								$is_primary = ( 'function' === $action['type'] );
								$btn_class  = $is_primary ? 'wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm' : 'wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm';
								?>
								<?php if ( 'link' === $action['type'] ) : ?>
									<a href="<?php echo esc_url( $action['url'] ); ?>" class="<?php echo esc_attr( $btn_class ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
								<?php elseif ( 'external_link' === $action['type'] ) : ?>
									<a href="<?php echo esc_url( $action['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr( $btn_class ); ?>">
										<?php echo esc_html( $action['label'] ); ?>
										<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
									</a>
								<?php elseif ( 'function' === $action['type'] ) : ?>
									<button class="<?php echo esc_attr( $btn_class ); ?>" onclick="<?php echo esc_attr( $action['function'] ); ?>"><?php echo esc_html( $action['label'] ); ?></button>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- 3rd Party Integrations -->
	<?php if ( ! empty( $third_party ) ) : ?>
	<div style="margin-bottom:32px;">
		<div style="margin-bottom:12px;">
			<h2 style="font-size:12px;font-weight:600;color:var(--wpsubs-text-muted);text-transform:uppercase;letter-spacing:0.06em;margin:0 0 3px;line-height:1.4;margin-left:1px;"><?php esc_html_e( '3rd Party Integrations', 'subscription' ); ?></h2>
			<p style="font-size:12px;color:var(--wpsubs-text-muted);margin:0;line-height:1.5;margin-left:1px;"><?php esc_html_e( 'Connect subscription events with your LMS, CRM, automation, email, and license management tools.', 'subscription' ); ?></p>
		</div>
		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
			<?php foreach ( $third_party as $integration ) : ?>
				<?php
				$is_active    = ! empty( $integration['is_active'] );
				$is_pro       = ! empty( $integration['is_pro'] );
				$icon_url     = $integration['icon_url'] ?? '';
				$icon_initial = $integration['icon_initial'] ?? strtoupper( substr( $integration['title'], 0, 2 ) );
				$icon_color   = $integration['icon_color'] ?? '#64748b';
				$category     = $integration['category'] ?? '';
				$cat          = $category_config[ $category ] ?? [
					'label' => $category,
					'bg'    => '#f1f5f9',
					'color' => '#475569',
				];

				$status_dot  = $is_active ? '#16a34a' : '#9ca3af';
				$status_text = $is_active ? __( 'Active', 'subscription' ) : __( 'Not Installed', 'subscription' );
				?>
				<div class="wpsubs-table-card" style="padding:16px;display:flex;flex-direction:column;gap:12px;">

					<!-- Header: icon + name + status -->
					<div style="display:flex;gap:10px;align-items:flex-start;">
						<div style="width:40px;height:40px;flex-shrink:0;border-radius:8px;overflow:hidden;border:1px solid var(--wpsubs-border);background:var(--wpsubs-surface-muted);display:flex;align-items:center;justify-content:center;">
							<?php if ( $icon_url ) : ?>
								<img src="<?php echo esc_url( $icon_url ); ?>" style="width:100%;height:100%;object-fit:contain;" alt="" />
							<?php else : ?>
								<span style="font-size:11px;font-weight:700;color:#fff;background:<?php echo esc_attr( $icon_color ); ?>;width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><?php echo esc_html( $icon_initial ); ?></span>
							<?php endif; ?>
						</div>
						<div style="flex:1;min-width:0;">
							<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:6px;margin-bottom:5px;">
								<span style="font-size:13px;font-weight:600;color:var(--wpsubs-text);line-height:1.3;"><?php echo esc_html( $integration['title'] ); ?></span>
								<span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:500;color:<?php echo esc_attr( $status_dot ); ?>;white-space:nowrap;flex-shrink:0;">
									<span style="width:7px;height:7px;border-radius:50%;background:<?php echo esc_attr( $status_dot ); ?>;flex-shrink:0;"></span>
									<?php echo esc_html( $status_text ); ?>
								</span>
							</div>
							<?php if ( $cat['label'] || $is_pro ) : ?>
								<div style="display:flex;flex-wrap:wrap;gap:4px;">
									<?php if ( $is_pro ) : ?>
										<span style="display:inline-flex;align-items:center;font-size:10px;font-weight:600;padding:2px 7px;border-radius:10px;background:#f5f3ff;color:#7c3aed;line-height:1.6;"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
									<?php endif; ?>
									<?php if ( $cat['label'] ) : ?>
										<span style="display:inline-flex;align-items:center;font-size:10px;font-weight:500;padding:2px 7px;border-radius:10px;background:<?php echo esc_attr( $cat['bg'] ); ?>;color:<?php echo esc_attr( $cat['color'] ); ?>;line-height:1.6;"><?php echo esc_html( $cat['label'] ); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Description -->
					<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0;line-height:1.5;"><?php echo esc_html( $integration['description'] ?? '' ); ?></p>

					<!-- Separator -->
					<div style="border-top:1px solid var(--wpsubs-border);margin:auto -16px 0;"></div>

					<!-- Actions -->
					<div style="display:flex;gap:6px;flex-wrap:wrap;">
						<?php if ( $is_pro && ! defined( 'SUBSCRIPT_PRO_VERSION' ) ) : ?>
							<div style="width:100%;display:flex;align-items:center;gap:6px;background:#f5f3ff;border-radius:6px;padding:7px 10px;font-size:12px;font-weight:500;color:#7c3aed;line-height:1.4;">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
								<?php esc_html_e( 'WPSubscription Pro required', 'subscription' ); ?>
							</div>
						<?php else : ?>
							<?php
							foreach ( $integration['actions'] as $action ) :
								$is_primary = ( 'function' === $action['type'] );
								$btn_class  = $is_primary ? 'wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm' : 'wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm';
								?>
								<?php if ( 'link' === $action['type'] ) : ?>
									<a href="<?php echo esc_url( $action['url'] ); ?>" class="<?php echo esc_attr( $btn_class ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
								<?php elseif ( 'external_link' === $action['type'] ) : ?>
									<a href="<?php echo esc_url( $action['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr( $btn_class ); ?>">
										<?php echo esc_html( $action['label'] ); ?>
										<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
									</a>
								<?php elseif ( 'function' === $action['type'] ) : ?>
									<button class="<?php echo esc_attr( $btn_class ); ?>" onclick="<?php echo esc_attr( $action['function'] ); ?>"><?php echo esc_html( $action['label'] ); ?></button>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

</div>
