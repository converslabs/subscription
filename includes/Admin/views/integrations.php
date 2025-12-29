<?php
/**
 * Payment Gateways Admin Page
 *
 * Displays available payment gateway options as cards.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>


<div class="wrap">

	<div class="wp-subscription-admin-content" style="max-width:1240px;margin:32px auto 0 auto">
		<h2><?php esc_html_e( 'Payment Gateways', 'subscription' ); ?></h2>
		
		<p style="font-size:14px;line-height:1.7;margin:0 0 24px 0;">
			<?php esc_html_e( 'Configure your store\'s payment gateways for subscription products. Enable, disable, and manage available payment methods that support recurring billing.', 'subscription' ); ?>
		</p>

		<!-- Payment Gateway Cards -->
		<div class="wp-subscription-payment-gateways" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px;margin-bottom:32px;">
			<?php
			foreach ( $integrations as $integration ) :
				?>
				<div style="background-color:white;border-radius:8px;padding:18px;box-shadow:0 2px 8px #e0e7ef;">
					<div style="display: flex; gap: 14px;">
						<div>
							<img 
								style="width: 40px; height: 40px; border-radius: 8px;"
								src=<?php echo esc_url( $integration['icon_url'] ?? '' ); ?> 
								alt=""
							/>
						</div>
						<div style="width: 100%;">
							<div style="display: flex; align-items: center; gap: 4px;">
								<span style="font-size:1.2em;font-weight:600;">
									<?php echo esc_html( $integration['title'] ?? '' ); ?>
								</span>

								<div style="margin-left: auto; display: flex; gap: 6px; align-items: center;">
									<!-- Integration Status Badge -->
									<?php if ( ! empty( $integration['is_installed'] ) && ! empty( $integration['is_active'] ) ) : ?>
										<span style="font-size:0.85em;padding:4px 8px;border-radius:12px;white-space:nowrap;text-transform: uppercase;background:#e8f5e9;color:#1b5e20;">
											<?php esc_html_e( 'Active', 'subscription' ); ?>
										</span>
									<?php elseif ( ! empty( $integration['is_installed'] ) && empty( $integration['is_active'] ) ) : ?>
										<span style="font-size:0.85em;padding:4px 8px;border-radius:12px;white-space:nowrap;text-transform: uppercase;background:#e3f2fd;color:#0d47a1;">
											<?php esc_html_e( 'Inactive', 'subscription' ); ?>
										</span>
									<?php else : ?>
										<span style="font-size:0.85em;padding:4px 8px;border-radius:12px;white-space:nowrap;text-transform: uppercase;background:#ffebee;color:#b71c1c;">
											<?php esc_html_e( 'Not Available', 'subscription' ); ?>
										</span>
									<?php endif; ?>

									<!-- Beta Badge -->
									<?php if ( ! empty( $integration['is_beta'] ) && $integration['is_beta'] ) : ?>
										<span style="font-size:0.85em;padding:4px 8px;border-radius:12px;white-space:nowrap;text-transform: uppercase;background:#ffebee;color:#b71c1c;font-weight: 500;">
											<?php esc_html_e( 'Beta', 'subscription' ); ?>
										</span>
									<?php endif; ?>
								</div>
							</div>
							<p style="font-size:12px;line-height:1.5;margin:8px 0 0 0;color:#555;">
								<?php echo esc_html( $integration['description'] ?? '' ); ?>
							</p>
						</div>
					</div>

					<!-- Recurring Support -->
					<?php if ( ! empty( $integration['supports_recurring'] ) ) : ?>
						<div style="background:#e8f5e9;color:#1b5e20;padding:6px 10px;margin:20px 0;border-radius:4px;font-size:13px;margin-bottom:16px;">
							<span style="display:flex;align-items:center;gap:6px;">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="#1b5e20"/>
								</svg>
								<?php esc_html_e( 'Supports automatic recurring payments.', 'subscription' ); ?>
							</span>
						</div>
					<?php else : ?>
						<div style="background:#fff3e0;color:#e65100;padding:6px 10px;margin:20px 0;border-radius:4px;font-size:13px;margin-bottom:16px;">
							<span style="display:flex;align-items:center;gap:6px;">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="#e65100"/>
								</svg>
								<?php esc_html_e( 'Manual renewals only.', 'subscription' ); ?>
							</span>
						</div>
					<?php endif; ?>

					<!-- Actions -->
					<div style="width: 100%; display: flex; gap: 8px; flex-wrap: wrap; align-items: end;">
						<?php foreach ( $integration['actions'] as $integration_action ) : ?>
							<?php if ( 'link' === $integration_action['type'] ) : ?>
								<a href="<?php echo esc_url( $integration_action['url'] ); ?>" class="button button-primary" style="flex:1;text-align:center;">
									<?php echo esc_html( $integration_action['label'] ); ?>
								</a>
							<?php elseif ( 'external_link' === $integration_action['type'] ) : ?>
								<a href="<?php echo esc_url( $integration_action['url'] ); ?>" target="_blank" class="button button-primary" style="flex:1;text-align:center;">
									<?php echo esc_html( $integration_action['label'] ); ?>
								</a>
							<?php elseif ( 'function' === $integration_action['type'] ) : ?>
								<button 
									class="<?php echo esc_attr( $integration_action['class'] ?? 'button button-primary' ); ?>"
									style="flex:1;text-align:center;" 
									onclick="<?php echo esc_attr( $integration_action['function'] ); ?>"
								>
									<?php echo esc_html( $integration_action['label'] ); ?>
								</button>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
			endforeach;
			?>
		</div>

		<!-- Information box -->
		<div class="wp-subscription-admin-box" style="margin-bottom:24px;">
			<h3><?php esc_html_e( 'About Payment Gateways', 'subscription' ); ?></h3>
			<p style="font-size:14px;line-height:1.7;margin:0 0 10px 0;">
				<?php esc_html_e( 'For subscription products to work properly, you need to use payment gateways that support recurring payments. Some payment methods only support manual renewals, which requires customers to manually pay for each renewal period.', 'subscription' ); ?>
			</p>
			<ul style="font-size:14px;line-height:1.6;margin:0 0 0 18px;padding:0;list-style:disc;">
				<li><?php esc_html_e( 'Automatic recurring billing requires a compatible payment gateway', 'subscription' ); ?></li>
				<li><?php esc_html_e( 'Manual renewal methods work with any payment gateway', 'subscription' ); ?></li>
				<li><?php esc_html_e( 'Some gateways may require additional configuration for subscriptions', 'subscription' ); ?></li>
			</ul>
		</div>

		<!-- Documentation box -->
		<div class="wp-subscription-support-resources" style="display:grid;grid-template-columns:repeat(2,1fr);gap:24px;margin-bottom:24px;">
			<div class="wp-subscription-admin-box">
				<h3><?php esc_html_e( 'Payment Gateway Documentation', 'subscription' ); ?></h3>
				<p style="font-size:14px;margin:0 0 8px 0;">
					<?php esc_html_e( 'Learn how to set up and configure payment gateways for subscription products.', 'subscription' ); ?>
				</p>
				<a href="https://docs.converslabs.com/en" target="_blank" class="button button-small" style="font-size:13px;padding:5px 14px;">
					<?php esc_html_e( 'View Documentation', 'subscription' ); ?>
				</a>
			</div>
			<div class="wp-subscription-admin-box">
				<h3><?php esc_html_e( 'Need Help?', 'subscription' ); ?></h3>
				<p style="font-size:14px;margin:0 0 8px 0;">
					<?php esc_html_e( 'If you\'re having trouble with a payment gateway, our support team can help.', 'subscription' ); ?>
				</p>
				<a href="https://wpsubscription.co/contact" target="_blank" class="button button-small" style="font-size:13px;padding:5px 14px;">
					<?php esc_html_e( 'Get Support', 'subscription' ); ?>
				</a>
			</div>
		</div>
	</div>

</div>
