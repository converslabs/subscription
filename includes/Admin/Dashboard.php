<?php
/**
 * Dashboard screen.
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;
use SpringDevs\Subscription\Illuminate\Stats;

/**
 * Builds the dashboard payload and renders its container.
 *
 * The screen is a small React app (src/dashboard/). Everything it shows is
 * computed here and handed over preloaded — there is no REST round trip,
 * because none of these figures change while the page is open.
 */
class Dashboard {

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'subscrpt-dashboard';

	/**
	 * Hook the screen's assets.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
	}

	/**
	 * Enqueue the bundle, but only on the dashboard screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function maybe_enqueue( $hook ) {
		if ( 'toplevel_page_wp-subscription' !== $hook ) {
			return;
		}

		$this->enqueue();
	}

	/**
	 * Render the dashboard page.
	 *
	 * No shared admin footer: the screen ends in its own footer row, which
	 * already links the docs and support, so the shared one only repeated them.
	 *
	 * @return void
	 */
	public function render() {
		( new Menu() )->render_admin_header( __( 'Overview', 'subscription' ) );
		include __DIR__ . '/views/dashboard.php';
	}

	/**
	 * Enqueue the dashboard bundle.
	 *
	 * @return void
	 */
	public function enqueue() {
		$asset_file = SUBSCRPT_PATH . '/build/dashboard.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			self::HANDLE,
			SUBSCRPT_URL . '/build/dashboard.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		/*
		 * WordPress ships the stylesheet for @wordpress/components separately
		 * from the script. Without this the components render as unstyled
		 * markup — which is why plugins that skip it end up reinventing every
		 * button in their own CSS.
		 */
		wp_enqueue_style( 'wp-components' );

		wp_enqueue_style(
			self::HANDLE,
			SUBSCRPT_URL . '/build/dashboard.css',
			array( 'wp-components' ),
			$asset['version']
		);

		// The build emits dashboard-rtl.css alongside dashboard.css; this is
		// what makes WordPress pick it up for right-to-left locales.
		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );

		wp_set_script_translations( self::HANDLE, 'subscription' );

		wp_add_inline_script(
			self::HANDLE,
			'window.subscrptDashboard = ' . wp_json_encode( $this->get_data() ) . ';',
			'before'
		);
	}

	/**
	 * Everything the dashboard renders.
	 *
	 * @return array<string,mixed>
	 */
	public function get_data(): array {
		$counts = Stats::get_status_counts();
		$setup  = $this->get_setup();

		return array(
			'pulse'  => $this->get_pulse( $counts ),
			'chart'  => $this->get_chart(),
			'setup'  => $setup,
			'health' => $this->get_health( $counts, $setup ),
			'build'  => $this->get_build_cards(),
			'footer' => $this->get_footer_links(),
			'isPro'  => subscrpt_pro_activated(),
		);
	}

	/**
	 * The five figures.
	 *
	 * @param array<string,int> $counts Status counts.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_pulse( array $counts ): array {
		$list = admin_url( 'admin.php?page=wp-subscription-list' );

		// Each of these runs a query, so call them once.
		$on_hold = (int) ( $counts['on_hold'] ?? 0 );
		$failed  = Stats::count_failed_renewals_since( 24 );

		// This month rather than a rolling window: the list filters by calendar
		// month, and a figure that opens the list must match the rows it shows.
		$this_month = new \DateTimeImmutable( 'now', wp_timezone() );

		return array(
			array(
				'key'   => 'active',
				'icon'  => 'people',
				'label' => __( 'Active subscriptions', 'subscription' ),
				'value' => (int) ( $counts['active'] ?? 0 ),
				'url'   => self::list_url( 'active' ),
			),
			array(
				'key'   => 'on_hold',
				'icon'  => 'pause',
				'label' => __( 'On-hold subscriptions', 'subscription' ),
				'value' => $on_hold,
				'url'   => self::list_url( 'on_hold' ),
				'tone'  => $on_hold > 0 ? 'warning' : '',
			),
			array(
				'key'   => 'due',
				'icon'  => 'money',
				'label' => __( 'Renewals due (next 7 days)', 'subscription' ),
				'value' => Stats::count_renewals_due_within( 7 ),
				'url'   => add_query_arg( 'renewal_due', 7, $list ),
			),
			array(
				'key'   => 'failed',
				'icon'  => 'alert',
				'label' => __( 'Failed renewals (last 24h)', 'subscription' ),
				'value' => $failed,
				'url'   => admin_url( 'edit.php?post_type=shop_order&post_status=wc-failed' ),
				'tone'  => $failed > 0 ? 'error' : '',
			),
			array(
				'key'   => 'new',
				'icon'  => 'trend',
				'label' => __( 'New subscriptions (this month)', 'subscription' ),
				'value' => Stats::count_new_in_month( $this_month ),
				'url'   => add_query_arg( 'date_filter', $this_month->format( 'Y-m' ), $list ),
			),
		);
	}

	/**
	 * Monthly subscription revenue for the chart.
	 *
	 * Values are pre-formatted here rather than in the browser: the store's
	 * currency, decimal separator and symbol position all live in WooCommerce
	 * settings, and reimplementing wc_price() in JavaScript gets them wrong for
	 * every locale that is not the developer's.
	 *
	 * @return array<string,mixed>
	 */
	private function get_chart(): array {
		$months = Stats::get_monthly_revenue( 6 );
		$total  = 0.0;

		foreach ( $months as &$month ) {
			$total           += $month['total'];
			$month['display'] = function_exists( 'wc_price' )
				? wp_strip_all_tags( html_entity_decode( wc_price( $month['total'] ), ENT_QUOTES, 'UTF-8' ) )
				: number_format_i18n( $month['total'], 2 );
		}
		unset( $month );

		return array(
			'months'  => $months,
			'total'   => $total,
			'display' => function_exists( 'wc_price' )
				? wp_strip_all_tags( html_entity_decode( wc_price( $total ), ENT_QUOTES, 'UTF-8' ) )
				: number_format_i18n( $total, 2 ),
			'empty'   => $total <= 0,
			'url'     => admin_url( 'admin.php?page=wp-subscription-stats' ),
			// Without pro, Reports is a preview of the Pro screen; the link says so.
			'pro'     => ! subscrpt_pro_activated(),
		);
	}

	/**
	 * The onboarding checklist.
	 *
	 * The three steps the onboarding wizard walks through, each carrying
	 * whether it is done and where to go to do it — a checklist that hides what
	 * you have finished gives no sense of progress.
	 *
	 * Done is read from the plan tables, not from a flag the wizard sets, so a
	 * store that built its plans on the Plans screen is counted too, and one
	 * that deleted them all is not.
	 *
	 * Only the first step goes to the wizard. The wizard always creates a new
	 * plan, so a store that already has one finishes the later steps on that
	 * plan's own screen instead of making a second.
	 *
	 * The gateway is not a step: the wizard does not touch it, and a store can
	 * finish onboarding without one. It rides along as a warning that outlives
	 * the steps, because without it no customer can pay.
	 *
	 * @return array<string,mixed>
	 */
	private function get_setup(): array {
		$wizard       = admin_url( 'admin.php?page=wp-subscription-onboarding' );
		$latest_plan  = $this->latest_plan_group_id();
		$selling_plan = $this->latest_plan_group_id_with_active_term();
		$product_plan = $selling_plan ? $selling_plan : $latest_plan;

		$items = array(
			array(
				'id'     => 'plan',
				'label'  => __( 'Create a plan', 'subscription' ),
				'done'   => $latest_plan > 0,
				'action' => array(
					'label' => __( 'Create', 'subscription' ),
					'url'   => $wizard,
				),
			),
			array(
				'id'     => 'durations',
				'label'  => __( 'Add billing durations', 'subscription' ),
				'done'   => $selling_plan > 0,
				'action' => array(
					'label' => __( 'Add', 'subscription' ),
					'url'   => $latest_plan ? self::plan_url( $latest_plan, 'plans' ) : $wizard,
				),
			),
			array(
				'id'     => 'product',
				'label'  => __( 'Connect a product', 'subscription' ),
				'done'   => $this->has_active_product_relation(),
				'action' => array(
					'label' => __( 'Connect', 'subscription' ),
					'url'   => $product_plan ? self::plan_url( $product_plan, 'products' ) : $wizard,
				),
			),
		);

		$done = count( array_filter( array_column( $items, 'done' ) ) );

		return array(
			'items'    => $items,
			'done'     => $done,
			'total'    => count( $items ),
			'complete' => $done === count( $items ),
			'gateway'  => $this->has_enabled_gateway() ? null : array(
				'title'  => __( 'No payment gateway enabled', 'subscription' ),
				'text'   => __( 'Customers cannot pay for subscriptions yet.', 'subscription' ),
				'action' => array(
					'label' => __( 'Set up', 'subscription' ),
					'url'   => admin_url( 'admin.php?page=wp-subscription-integrations' ),
				),
			),
			'wizard'   => array(
				'label' => __( 'Run setup wizard', 'subscription' ),
				'url'   => $wizard,
			),
		);
	}

	/**
	 * The banner across the top of the page.
	 *
	 * One sentence answering "is anything wrong", most urgent first:
	 * unfinished onboarding, then no way to pay, then subscription states. A
	 * store with nothing to sell, or no way to be paid for it, has nothing to
	 * be healthy about.
	 *
	 * The wizard only opens by itself once, so this is where a store owner who
	 * skipped it finds the way back — for as long as a step is left.
	 *
	 * @param array<string,int>   $counts Status counts.
	 * @param array<string,mixed> $setup  Onboarding checklist.
	 * @return array<string,mixed>
	 */
	private function get_health( array $counts, array $setup ): array {
		if ( empty( $setup['complete'] ) ) {
			$done  = (int) $setup['done'];
			$total = (int) $setup['total'];

			return array(
				'state'  => 'attention',
				'title'  => __( 'Finish setting up', 'subscription' ),
				/* translators: 1: onboarding steps done, 2: onboarding steps in total. */
				'text'   => sprintf( _n( '%1$d of %2$d onboarding step done.', '%1$d of %2$d onboarding steps done.', $total, 'subscription' ), $done, $total ),
				'action' => array(
					'label' => __( 'Start onboarding', 'subscription' ),
					'url'   => admin_url( 'admin.php?page=wp-subscription-onboarding' ),
				),
			);
		}

		if ( ! empty( $setup['gateway'] ) ) {
			return array(
				'state'  => 'attention',
				'title'  => __( 'Payments are not set up', 'subscription' ),
				'text'   => __( 'No payment gateway is enabled, so customers cannot pay for subscriptions.', 'subscription' ),
				'action' => array(
					'label' => __( 'Set up payments', 'subscription' ),
					'url'   => admin_url( 'admin.php?page=wp-subscription-integrations' ),
				),
			);
		}

		$on_hold = (int) ( $counts['on_hold'] ?? 0 );

		if ( $on_hold > 0 ) {
			return array(
				'state'  => 'attention',
				'title'  => __( 'Some subscriptions need a look', 'subscription' ),
				/* translators: %d: number of on-hold subscriptions. */
				'text'   => sprintf( _n( '%d subscription is on hold.', '%d subscriptions are on hold.', $on_hold, 'subscription' ), $on_hold ),
				'action' => array(
					'label' => __( 'Review them', 'subscription' ),
					'url'   => self::list_url( 'on_hold' ),
				),
			);
		}

		$active = (int) ( $counts['active'] ?? 0 );

		return array(
			'state' => 'clear',
			'title' => __( 'All subscriptions look healthy', 'subscription' ),
			/* translators: %d: number of active subscriptions. */
			'text'  => sprintf( _n( '%d active subscription, nothing needs attention.', '%d active subscriptions, nothing needs attention.', $active, 'subscription' ), $active ),
		);
	}

	/**
	 * The three cards along the bottom.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_build_cards(): array {
		$is_pro = subscrpt_pro_activated();

		return array(
			array(
				'tone'    => 'insight',
				'icon'    => 'chart',
				'eyebrow' => __( 'Insight', 'subscription' ),
				'title'   => __( 'Open reports', 'subscription' ),
				'text'    => __( 'Revenue, active subscriptions and growth over time.', 'subscription' ),
				'link'    => array(
					'label' => __( 'View reports', 'subscription' ),
					'url'   => admin_url( 'admin.php?page=wp-subscription-stats' ),
				),
				'pro'     => ! $is_pro,
			),
			array(
				'tone'    => 'setup',
				'icon'    => 'card',
				'eyebrow' => __( 'Setup', 'subscription' ),
				'title'   => __( 'Configure payments', 'subscription' ),
				'text'    => __( 'Connect PayPal, Stripe, Paddle and more from one screen.', 'subscription' ),
				'link'    => array(
					'label' => __( 'Open integrations', 'subscription' ),
					'url'   => admin_url( 'admin.php?page=wp-subscription-integrations' ),
				),
			),
			$is_pro
				? array(
					'tone'    => 'extend',
					'icon'    => 'shield',
					'eyebrow' => __( 'Extend', 'subscription' ),
					'title'   => __( 'Subscription health', 'subscription' ),
					'text'    => __( 'Find and recover subscriptions that need rescuing.', 'subscription' ),
					'link'    => array(
						'label' => __( 'Open health', 'subscription' ),
						'url'   => admin_url( 'admin.php?page=wp-subscription-health' ),
					),
				)
				: array(
					'tone'    => 'extend',
					'icon'    => 'shield',
					'eyebrow' => __( 'Extend', 'subscription' ),
					'title'   => __( 'WPSubscription Pro', 'subscription' ),
					'text'    => __( 'Payment retries, a health queue and revenue reporting.', 'subscription' ),
					'link'    => array(
						'label'    => __( 'See what Pro adds', 'subscription' ),
						'url'      => 'https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=dashboard',
						'external' => true,
					),
				),
		);
	}

	/**
	 * The centred link row at the very bottom.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_footer_links(): array {
		return array(
			array(
				'label' => __( 'Documentation', 'subscription' ),
				'url'   => 'https://docs.wpsubscription.co/en?utm_source=plugin&utm_medium=admin&utm_campaign=dashboard',
			),
			array(
				'label' => __( 'Get support', 'subscription' ),
				'url'   => 'https://wpsubscription.co/contact?utm_source=plugin&utm_medium=admin&utm_campaign=dashboard',
			),
			array(
				'label' => __( 'My account', 'subscription' ),
				'url'   => 'https://my.wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=dashboard',
			),
		);
	}

	/**
	 * The subscriptions list, filtered to one status.
	 *
	 * The list reads `subscrpt_status` — its dropdown is named that and its
	 * reset link clears that. Any other name, `post_status` included, is ignored
	 * without complaint and the list opens unfiltered.
	 *
	 * @param string $status A registered subscription status, e.g. `on_hold`.
	 * @return string
	 */
	private static function list_url( string $status ): string {
		return add_query_arg( 'subscrpt_status', $status, admin_url( 'admin.php?page=wp-subscription-list' ) );
	}

	/**
	 * A plan's screen, opened on one of its tabs.
	 *
	 * @param int    $plan_id Plan group id.
	 * @param string $tab     `plans` (its durations) or `products`.
	 * @return string
	 */
	private static function plan_url( int $plan_id, string $tab ): string {
		return add_query_arg(
			array(
				'view' => 'detail',
				'plan' => $plan_id,
				'tab'  => $tab,
			),
			admin_url( 'admin.php?page=' . Plans::SLUG )
		);
	}

	/**
	 * The newest plan, in any status, or 0 when there is none.
	 *
	 * A draft plan has still been created. Any plan at all is the same test the
	 * first-visit redirect to the wizard uses.
	 *
	 * @return int
	 */
	private function latest_plan_group_id(): int {
		global $wpdb;

		$table = PlanRepository::group_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$id = $wpdb->get_var( "SELECT id FROM {$table} ORDER BY id DESC LIMIT 1" );
		// phpcs:enable

		return (int) $id;
	}

	/**
	 * The plan that most recently gained an active billing duration, or 0.
	 *
	 * Drafts do not count: the Plans screen seeds a draft "Monthly" duration
	 * under every new plan, so counting it would tick this step the moment a
	 * plan exists, before anyone has chosen how customers are billed.
	 *
	 * @return int
	 */
	private function latest_plan_group_id_with_active_term(): int {
		global $wpdb;

		$table = PlanRepository::plan_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT plan_group_id FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT 1", 'active' ) );
		// phpcs:enable

		return (int) $id;
	}

	/**
	 * Whether any gateway this plugin supports is switched on.
	 *
	 * @return bool
	 */
	private function has_enabled_gateway(): bool {
		foreach ( array( 'wp_subscription_paypal', 'stripe', 'smartpay_paddle' ) as $gateway ) {
			if ( Integrations::is_gateway_enabled( $gateway ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether anything is connected to an active billing duration.
	 *
	 * A product attached only to draft durations cannot be bought, and an
	 * exclusion row takes a product away rather than connecting it, so
	 * neither counts.
	 *
	 * @return bool
	 */
	private function has_active_product_relation(): bool {
		global $wpdb;

		$relations = PlanRepository::relation_table();
		$plans     = PlanRepository::plan_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$relations} r
				INNER JOIN {$plans} p ON p.id = r.plan_id
				WHERE r.status = %s AND r.exclude = 0 AND p.status = %s
				LIMIT 1",
				'active',
				'active'
			)
		);
		// phpcs:enable

		return (bool) $found;
	}
}
