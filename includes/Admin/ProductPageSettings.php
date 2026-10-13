<?php
/**
 * Settings > Product page: how the purchase options look.
 *
 * Adds the group's heading, the layout, how intervals list, the colours and the
 * corner radius, and a live preview that renders the real layout templates with
 * sample plans. The values are read on the storefront by `Plans::layout_for()`
 * (layout) and `Frontend\PlanStyle` (colours, radius); this only collects them.
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Frontend\PlanStyle;
use SpringDevs\Subscription\Frontend\Plans;

defined( 'ABSPATH' ) || exit;

/**
 * The Product page group of the settings screen.
 */
class ProductPageSettings {

	/**
	 * Option name of the store's layout.
	 */
	const LAYOUT = 'subscrpt_plan_selector_layout';

	/**
	 * Option name of how a group lists its intervals when it does not say.
	 */
	const INTERVALS = 'subscrpt_plan_intervals_display';

	/**
	 * Colour option => [ title, CSS custom property, example the field shows when empty ].
	 *
	 * @return array
	 */
	private static function colour_fields(): array {
		return [
			'subscrpt_plan_color_accent'     => [ __( 'Accent colour', 'subscription' ), '--subscrpt-accent', '#2271b1', __( 'The selected option, its outline and the buttons.', 'subscription' ) ],
			'subscrpt_plan_color_accent_ink' => [ __( 'Text on the accent', 'subscription' ), '--subscrpt-accent-ink', '#ffffff', __( 'Text and marks drawn on the accent colour.', 'subscription' ) ],
			'subscrpt_plan_color_ink'        => [ __( 'Text colour', 'subscription' ), '--subscrpt-ink', '#1d2327', __( 'Leave empty to use your theme\'s text colour.', 'subscription' ) ],
			'subscrpt_plan_color_border'     => [ __( 'Border colour', 'subscription' ), '--subscrpt-border', '#e2e4e7', __( 'The outline of options that are not selected.', 'subscription' ) ],
			'subscrpt_plan_color_badge'      => [ __( 'Discount badge', 'subscription' ), '--subscrpt-badge', '#2271b1', __( 'Leave empty to use the accent colour.', 'subscription' ) ],
			'subscrpt_plan_color_ribbon'     => [ __( 'Tag ribbon', 'subscription' ), '--subscrpt-ribbon', '#1d2327', __( 'The merchant\'s tag on an option.', 'subscription' ) ],
		];
	}

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'subscrpt_settings_fields', [ $this, 'add_settings_fields' ] );
		add_action( 'subscrpt_register_settings', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Whether the request is the settings screen.
	 *
	 * @return bool
	 */
	private static function on_settings_screen(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads which page this is, nothing else.
		return isset( $_GET['page'] ) && 'wp-subscription-settings' === $_GET['page'];
	}

	/**
	 * The layout in force: the stored one when it exists, else stacked.
	 *
	 * @return string
	 */
	private static function current_layout(): string {
		$layout = get_option( self::LAYOUT, '' );

		return is_string( $layout ) && array_key_exists( $layout, Plans::layouts() ) ? $layout : 'stacked';
	}

	/**
	 * How intervals list by default.
	 *
	 * @return string 'chips' or 'dropdown'.
	 */
	private static function current_intervals(): string {
		return PlanStyle::sanitize_intervals( get_option( self::INTERVALS, 'chips' ) );
	}

	/**
	 * Layout key => label, for a select.
	 *
	 * @return array
	 */
	public static function layout_labels(): array {
		$known  = [
			'stacked'      => __( 'Stacked cards', 'subscription' ),
			'classic'      => __( 'Classic radio list', 'subscription' ),
			'dropdown'     => __( 'Dropdown', 'subscription' ),
			'accordion'    => __( 'Accordion', 'subscription' ),
			'grid'         => __( 'Grid of tiles', 'subscription' ),
			'grid_savings' => __( 'Grid, savings first', 'subscription' ),
			'buttons'      => __( 'Button row', 'subscription' ),
		];
		$labels = [];
		foreach ( array_keys( Plans::layouts() ) as $key ) {
			$labels[ $key ] = $known[ $key ] ?? ucwords( str_replace( [ '_', '-' ], ' ', (string) $key ) );
		}

		return $labels;
	}

	/**
	 * Add the group's heading and fields to the settings screen.
	 *
	 * @param array $settings_fields Settings fields.
	 *
	 * @return array
	 */
	public function add_settings_fields( $settings_fields ) {
		$settings_fields[] = [
			'type'       => 'heading',
			'group'      => 'product_page',
			'priority'   => 0,
			'field_data' => [
				'title'       => __( 'Product page', 'subscription' ),
				'description' => __( 'How a product page shows its options.', 'subscription' ),
			],
		];

		$settings_fields[] = [
			'type'       => 'html',
			'group'      => 'product_page',
			'priority'   => 1,
			'field_data' => [
				'title'       => __( 'Preview', 'subscription' ),
				'description' => __( 'Sample plans in your choices. Nothing here is saved until you save the settings.', 'subscription' ),
				// Rendering seven layouts is not free; only the settings screen needs it.
				'content'     => self::on_settings_screen() ? $this->preview_html() : '',
			],
		];

		$settings_fields[] = [
			'type'       => 'select',
			'group'      => 'product_page',
			'priority'   => 2,
			'field_data' => [
				'id'          => self::LAYOUT,
				'title'       => __( 'Layout', 'subscription' ),
				'description' => __( 'How the purchase options look. A product can choose its own in its Subscription tab.', 'subscription' ),
				'options'     => self::layout_labels(),
				'selected'    => self::current_layout(),
			],
		];

		$settings_fields[] = [
			'type'       => 'select',
			'group'      => 'product_page',
			'priority'   => 3,
			'field_data' => [
				'id'          => self::INTERVALS,
				'title'       => __( 'Intervals', 'subscription' ),
				'description' => __( 'How a plan lists its billing periods: as chips, or in a dropdown. A plan group can choose its own.', 'subscription' ),
				'options'     => [
					'chips'    => __( 'Chips', 'subscription' ),
					'dropdown' => __( 'Dropdown', 'subscription' ),
				],
				'selected'    => self::current_intervals(),
			],
		];

		$priority = 4;
		foreach ( self::colour_fields() as $option => $field ) {
			$settings_fields[] = [
				'type'       => 'input',
				'group'      => 'product_page',
				'priority'   => $priority++,
				'field_data' => [
					'id'          => $option,
					'title'       => $field[0],
					'description' => $field[3],
					'value'       => PlanStyle::sanitize_colour( get_option( $option, '' ) ),
					'placeholder' => $field[2],
					'attributes'  => [
						'data-subscrpt-style-var' => $field[1],
						'maxlength'               => '7',
						'pattern'                 => '#([0-9A-Fa-f]{3}){1,2}',
						'autocomplete'            => 'off',
					],
				],
			];
		}

		$settings_fields[] = [
			'type'       => 'input',
			'group'      => 'product_page',
			'priority'   => $priority,
			'field_data' => [
				'id'          => PlanStyle::RADIUS,
				'title'       => __( 'Corner radius', 'subscription' ),
				'description' => __( 'In pixels, 0 to 40. Empty keeps the default.', 'subscription' ),
				'type'        => 'number',
				'value'       => PlanStyle::sanitize_radius( get_option( PlanStyle::RADIUS, '' ) ),
				'placeholder' => '10',
				'attributes'  => [
					'data-subscrpt-style-var'  => '--subscrpt-radius',
					'data-subscrpt-style-unit' => 'px',
					'min'                      => '0',
					'max'                      => (string) PlanStyle::MAX_RADIUS,
					'step'                     => '1',
				],
			],
		];

		return $settings_fields;
	}

	/**
	 * Register the settings, each with a callback that keeps bad values out.
	 *
	 * @return void
	 */
	public function register_settings() {
		$group = 'wp_subscription_settings';

		register_setting( $group, self::LAYOUT, [ 'type' => 'string', 'sanitize_callback' => [ PlanStyle::class, 'sanitize_layout' ] ] );
		register_setting( $group, self::INTERVALS, [ 'type' => 'string', 'sanitize_callback' => [ PlanStyle::class, 'sanitize_intervals' ] ] );
		register_setting( $group, PlanStyle::RADIUS, [ 'type' => 'string', 'sanitize_callback' => [ PlanStyle::class, 'sanitize_radius' ] ] );

		foreach ( array_keys( self::colour_fields() ) as $option ) {
			register_setting( $group, $option, [ 'type' => 'string', 'sanitize_callback' => [ PlanStyle::class, 'sanitize_colour' ] ] );
		}
	}

	/**
	 * Load the preview's script and the selector stylesheet on the settings screen.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! self::on_settings_screen() ) {
			return;
		}

		wp_enqueue_style( 'subscrpt_plans_selector_css', SUBSCRPT_ASSETS . '/css/frontend/plans.css', [], SUBSCRPT_VERSION );
		wp_enqueue_script( 'subscrpt_plan_style_preview', SUBSCRPT_ASSETS . '/js/admin/plan-style-preview.js', [], SUBSCRPT_VERSION, true );
	}

	/**
	 * Sample plans, in the shape `PlanGroups` gives the layouts.
	 *
	 * @param string $key       Makes the group ids unique across the preview's copies.
	 * @param string $intervals 'chips' or 'dropdown', for the group with terms.
	 *
	 * @return array
	 */
	private function sample_groups( string $key, string $intervals ): array {
		return [
			[
				'id'            => 'preview-' . $key,
				'type'          => 'subscribe_save',
				'label'         => __( 'Subscribe & Save', 'subscription' ),
				'price'         => wc_price( 20 ),
				'old_price'     => '',
				'badge'         => '',
				'terms_heading' => __( 'Deliver every', 'subscription' ),
				'storefront'    => [
					'tag'              => __( 'Most popular', 'subscription' ),
					'benefits_heading' => __( 'Included', 'subscription' ),
					'benefits'         => [ __( 'Free shipping', 'subscription' ), __( 'Skip or cancel any time', 'subscription' ) ],
					'intervals'        => $intervals,
				],
				'terms'         => [
					[
						'id'               => 1,
						'label'            => __( 'Every month', 'subscription' ),
						'interval_label'   => __( 'Month', 'subscription' ),
						'price'            => wc_price( 20 ),
						'regular_price'    => wc_price( 20 ),
						'note'             => __( '$20.00 / month', 'subscription' ),
						'badge'            => '',
						'saving_label'     => '',
						'discount_percent' => 0,
					],
					[
						'id'               => 2,
						'label'            => __( 'Every 2 months', 'subscription' ),
						'interval_label'   => __( '2 months', 'subscription' ),
						'price'            => wc_price( 18 ),
						'regular_price'    => wc_price( 20 ),
						'note'             => __( '$18.00 / 2 months', 'subscription' ),
						'badge'            => __( 'Save 10%', 'subscription' ),
						'saving_label'     => __( 'Save 10%', 'subscription' ),
						'discount_percent' => 10,
					],
					[
						'id'               => 3,
						'label'            => __( 'Every 3 months', 'subscription' ),
						'interval_label'   => __( '3 months', 'subscription' ),
						'price'            => wc_price( 16 ),
						'regular_price'    => wc_price( 20 ),
						'note'             => __( '$16.00 / 3 months', 'subscription' ),
						'badge'            => __( 'Save 20%', 'subscription' ),
						'saving_label'     => __( 'Save 20%', 'subscription' ),
						'discount_percent' => 20,
					],
				],
			],
			[
				'id'        => 'preview-one-time-' . $key,
				'type'      => 'one_time',
				'label'     => __( 'One-time purchase', 'subscription' ),
				'price'     => wc_price( 20 ),
				'old_price' => '',
				'badge'     => '',
				'note'      => '',
				'terms'     => [],
			],
		];
	}

	/**
	 * The live preview: every layout, in both interval styles, rendered by the
	 * real templates.
	 *
	 * Only the chosen pair shows; the script switches between them as the
	 * settings change, and sets the colour variables on the buyboxes inside. It
	 * is `inert` and its controls have no names, so nothing in it can post into
	 * the settings form.
	 *
	 * @return string Markup, escaped by the templates.
	 */
	public function preview_html(): string {
		$layouts   = Plans::layouts();
		$layout    = self::current_layout();
		$intervals = self::current_intervals();
		$product   = new \WC_Product_Simple();
		$html      = '<div class="subscrpt-preview" data-subscrpt-preview inert>';

		foreach ( $layouts as $key => $partial ) {
			if ( ! file_exists( wc_locate_template( $partial, 'subscription', SUBSCRPT_TEMPLATES ) ) ) {
				$partial = $layouts['stacked'];
			}

			foreach ( [ 'chips', 'dropdown' ] as $style ) {
				$markup = wc_get_template_html(
					$partial,
					[
						'groups'  => $this->sample_groups( $key . '-' . $style, $style ),
						'product' => $product,
						'context' => 'page',
						'layout'  => $key,
					],
					'subscription',
					SUBSCRPT_TEMPLATES
				);

				// A preview must not post, and plans.js must not bind to it.
				$markup = preg_replace( '/\sname="[^"]*"/', '', $markup );
				$markup = str_replace( ' data-subscrpt-buybox', '', $markup );

				$html .= sprintf(
					'<div data-subscrpt-preview-layout="%1$s" data-subscrpt-preview-intervals="%2$s"%3$s>%4$s</div>',
					esc_attr( $key ),
					esc_attr( $style ),
					$key === $layout && $style === $intervals ? '' : ' hidden',
					$markup
				);
			}
		}

		return $html . '</div>';
	}
}
