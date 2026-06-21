<?php
/**
 * Settings Helper File
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

/**
 * Settings Helper Class
 *
 * @package SpringDevs\Subscription\Admin
 */
class SettingsHelper {
	/**
	 * Singleton instance
	 *
	 * @var SettingsHelper|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return SettingsHelper
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the class.
	 */
	private function __construct() {
		add_filter( 'process_subscrpt_settings_fields', [ $this, 'process_settings_fields' ], 100, 1 );
	}

	/**
	 * Process settings fields.
	 *
	 * @param array $fields Settings fields.
	 * @return array Processed settings fields.
	 */
	public function process_settings_fields( $fields ) {
		// Group settings fields.
		$fields = $this->group_settings_fields( $fields );

		// Sort fields by priority (groups & fields).
		$fields = $this->sort_settings_fields( $fields );

		return $fields;
	}

	/**
	 * Group settings fields.
	 *
	 * @param array $fields Settings fields.
	 * @return array Processed settings fields.
	 */
	public function group_settings_fields( $fields ) {
		$tmp_fields = [];
		foreach ( $fields as $field ) {
			$field_group = $field['group'] ?? 'main';

			if ( $field['type'] === 'heading' ) {
				$group_priority                         = $field['priority'] ?? 0;
				$tmp_fields[ $field_group ]['priority'] = $group_priority;
				$field['priority']                      = -1;
			}

			$tmp_fields[ $field_group ]['fields'][] = $field;
		}
		return $tmp_fields;
	}

	/**
	 * Sort settings fields.
	 *
	 * @param array $fields Settings fields.
	 * @return array Processed settings fields.
	 */
	public function sort_settings_fields( $fields ) {
		// Sort groups by priority.
		uasort(
			$fields,
			function ( $a, $b ) {
				$priority_a = $a['priority'] ?? 0;
				$priority_b = $b['priority'] ?? 0;
				return $priority_a <=> $priority_b;
			}
		);

		// Sort fields within each group by priority.
		foreach ( $fields as $group_key => $group_data ) {
			uasort(
				$group_data['fields'],
				function ( $a, $b ) {
					$priority_a = $a['priority'] ?? 0;
					$priority_b = $b['priority'] ?? 0;
					return $priority_a <=> $priority_b;
				}
			);
			$fields[ $group_key ]['fields'] = $group_data['fields'];
		}
		return $fields;
	}

	/**
	 * Render specified settings field.
	 *
	 * @param string $field Field type.
	 * @param array  $args Field arguments.
	 * @param bool   $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_settings_field( $field, $args, $should_print = true ) {
		if ( empty( $field ) ) {
			$field = 'input'; // Default field type.
			subscrpt_write_debug_log( "[SettingsHelper] Field type not specified. Defaulting to 'input'." );
		}

		switch ( $field ) {
			case 'heading':
				return self::render_heading( $args, $should_print );
			case 'switch':
			case 'toggle':
				return self::render_switch_field( $args, $should_print );
			case 'select':
				return self::render_select_field( $args, $should_print );
			case 'multi_select':
				return self::render_multiselect_field( $args, $should_print );
			case 'join':
				return self::render_joined_field( $args, $should_print );
			case 'input':
			default:
				return self::render_input_field( $args, $should_print );
		}
	}

	/**
	 * Pro badge markup, shown beside settings that require WPSubscription Pro.
	 *
	 * @return string Pre-escaped badge HTML.
	 */
	public static function pro_badge_html() {
		return '<span class="subscrpt-pro-badge" title="' . esc_attr__( 'WPSubscription Pro required', 'subscription' ) . '">' . esc_html__( 'Pro', 'subscription' ) . '</span>';
	}


	/**
	 * Text Element HTML.
	 *
	 * @param array $args Same as 'render_text_field'.
	 * @param bool  $join_item Whether to return element for 'join' container or not.
	 */
	public static function inp_element( $args = [], $join_item = false ) {
		$id          = $args['id'];
		$value       = $args['value'] ?? '';
		$placeholder = $args['placeholder'] ?? '';
		$type        = $args['type'] ?? 'text';

		$disabled_attr = isset( $args['disabled'] ) && $args['disabled'] ? 'disabled' : '';

		$style_attr = '';
		if ( isset( $args['style'] ) ) {
			$style_attr = $args['style'];
		}

		$other_attrs_html = '';
		foreach ( ( $args['attributes'] ?? [] ) as $attr_key => $attr_value ) {
			$other_attrs_html .= sprintf( ' %s="%s" ', esc_attr( $attr_key ), esc_attr( $attr_value ) );
		}

		ob_start();
		?>
			<input
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $id ); ?>"
				class="wpsubs-input"
				<?php
				if ( $style_attr ) :
					?>
					style="<?php echo esc_attr( $style_attr ); ?>"<?php endif; ?>
				type="<?php echo esc_attr( $type ); ?>"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				<?php echo esc_attr( $disabled_attr ); ?>
				<?php echo wp_kses_post( $other_attrs_html ); ?>
			/>
		<?php
		return ob_get_clean();
	}

	/**
	 * Select Element HTML.
	 *
	 * @param array $args Same as 'render_select_field'.
	 * @param bool  $join_item Whether to return element for 'join' container or not.
	 */
	public static function select_element( $args = [], $join_item = false ) {
		$id = $args['id'];

		// Enhanced / multiselect → wpsubs-tag-select (pill input with filter).
		if ( isset( $args['enhanced'] ) && $args['enhanced'] ) {
			$multiple    = isset( $args['attributes']['multiple'] ) && $args['attributes']['multiple'];
			$adv_options = array();
			foreach ( ( $args['options'] ?? [] ) as $opt_value => $opt_label ) {
				$adv_options[] = array(
					'value' => (string) $opt_value,
					'label' => $opt_label,
				);
			}

			ob_start();
			wpsubs_render_tag_select(
				array(
					'name'     => $id,
					'value'    => $args['selected'] ?? ( $multiple ? array() : '' ),
					'options'  => $adv_options,
					'multiple' => $multiple,
				)
			);
			return ob_get_clean();
		}

		// Regular select → wpsubs-adv-select (button-based custom dropdown).
		$selected    = (string) ( $args['selected'] ?? '' );
		$adv_options = array();
		foreach ( ( $args['options'] ?? [] ) as $opt_value => $opt_label ) {
			$adv_options[] = array(
				'value'    => (string) $opt_value,
				'label'    => $opt_label,
				'disabled' => isset( $args['disabled'] ) && ( is_array( $args['disabled'] )
					? in_array( $opt_value, $args['disabled'], true )
					: $args['disabled'] === $opt_value ),
			);
		}

		ob_start();
		wpsubs_render_adv_select(
			array(
				'name'    => $id,
				'value'   => $selected,
				'options' => $adv_options,
				'align'   => 'left',
			)
		);
		return ob_get_clean();
	}

	/**
	 * Render Field Heading.
	 *
	 * - Args:
	 *   - title (string) - Field title.
	 *   - description (string) - Field description (optional).
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_heading( $args = [], $should_print = true ) {
		$title       = $args['title'] ?? '';
		$description = $args['description'] ?? '';

		ob_start();
		?>
		<div class="wpsubs-settings-heading">
			<h3 class="wpsubs-settings-heading__title"><?php echo esc_html( $title ); ?></h3>
			<?php if ( ! empty( $description ) ) : ?>
				<p class="wpsubs-settings-heading__desc"><?php echo wp_kses_post( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		$html_content = ob_get_clean();

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
	}

	/**
	 * Render Text field.
	 *
	 * - Args:
	 *   - id (string) - Field ID.
	 *   - title (string) - Field title.
	 *   - description (string) - Field description (optional).
	 *   - value (string) - Default value.
	 *   - placeholder (string) - Default placeholder.
	 *   - disabled (bool) - Disabled status.
	 *   - type (string) - Input type [text, email, number, date, time, etc.].
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_input_field( $args = [], $should_print = true ) {
		$title       = $args['title'] ?? '';
		$description = $args['description'] ?? '';

		// Return error if ID is not provided.
		if ( empty( $args['id'] ?? '' ) ) {
			$field_hint = empty( $title ) ? 'Error' : $title;
			$no_id_msg  = '<p><strong>' . $field_hint . ':</strong> ' . __( 'Field ID is required.', 'subscription' ) . '</p>';
			return $should_print ? print wp_kses_post( $no_id_msg ) : $no_id_msg;
		}

		// Input HTML.
		$text_el_html = self::inp_element( $args );

		ob_start();
		?>
		<div class="wpsubs-settings-field<?php echo ! empty( $args['pro_locked'] ) ? ' wpsubs-settings-field--locked' : ''; ?>">
			<div class="wpsubs-settings-field__label">
				<?php
				if ( ! empty( $args['pro_locked'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped badge markup.
					echo self::pro_badge_html();
				}
				?>
				<?php echo esc_html( $title ); ?>
			</div>
			<div class="wpsubs-settings-field__control">
				<?php
					// Output intentionally not escaped as element is already escaped during generation & re-escaping breaks the HTML structure.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $text_el_html;
				?>
				<?php if ( ! empty( $description ) ) : ?>
					<p class="wpsubs-settings-field__hint"><?php echo wp_kses_post( $description ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		$html_content = ob_get_clean();

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
		// All form elements inside $html_content are pre-escaped during generation (esc_attr, esc_html).
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
	}

	/**
	 * Render Switch field.
	 *
	 * - Args:
	 *   - id (string) - Field ID.
	 *   - title (string) - Field title.
	 *   - label (string) - Checkbox label.
	 *   - description (string) - Field description (optional).
	 *   - value (string) - Checked value.
	 *   - checked (bool) - Checked status.
	 *   - disabled (bool) - Disabled status.
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_switch_field( $args = [], $should_print = true ) {
		$id          = $args['id'];
		$title       = $args['title'] ?? '';
		$label       = $args['label'] ?? '';
		$description = $args['description'] ?? '';
		$value       = $args['value'] ?? '0';

		// Return error if ID is not provided.
		if ( empty( $args['id'] ?? '' ) ) {
			$field_hint = empty( $title ) ? 'Error' : $title;
			$no_id_msg  = '<p><strong>' . $field_hint . ':</strong> ' . __( 'Field ID is required.', 'subscription' ) . '</p>';
			return $should_print ? print wp_kses_post( $no_id_msg ) : $no_id_msg;
		}

		$description_html = '';
		if ( ! empty( $description ) ) {
			$description_html = sprintf(
				'<p class="wpsubs-settings-field__hint">%s</p>',
				wp_kses_post( $description )
			);
		}

		$style_attr = '';
		if ( isset( $args['style'] ) ) {
			$style_attr .= ' ' . $args['style'];
		}

		$other_attrs_html = '';
		foreach ( ( $args['attributes'] ?? [] ) as $attr_key => $attr_value ) {
			$other_attrs_html .= sprintf( ' %s="%s" ', esc_attr( $attr_key ), esc_attr( $attr_value ) );
		}

		$checked_attr  = isset( $args['checked'] ) && (bool) $args['checked'] ? 'checked' : '';
		$disabled_attr = isset( $args['disabled'] ) && (bool) $args['disabled'] ? 'disabled' : '';

		ob_start();
		?>
		<div class="wpsubs-settings-field<?php echo ! empty( $args['pro_locked'] ) ? ' wpsubs-settings-field--locked' : ''; ?>">
			<div class="wpsubs-settings-field__label">
				<?php
				if ( ! empty( $args['pro_locked'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped badge markup.
					echo self::pro_badge_html();
				}
				?>
				<?php echo esc_html( $title ); ?>
			</div>
			<div class="wpsubs-settings-field__control">
				<label class="wpsubs-settings-toggle-label" for="<?php echo esc_attr( $id ); ?>">
					<input
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $id ); ?>"
						class="wpsubs-toggle"
						type="checkbox"
						value="<?php echo esc_attr( $value ); ?>"
						<?php echo esc_attr( $checked_attr ); ?>
						<?php echo esc_attr( $disabled_attr ); ?>
						<?php
							// Output intentionally not escaped as element is already escaped during generation & re-escaping breaks the HTML structure.
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo $other_attrs_html;
						?>
					/>
					<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
					<?php if ( ! empty( $label ) ) : ?>
						<span class="wpsubs-settings-toggle-label__text"><?php echo esc_html( $label ); ?></span>
					<?php endif; ?>
				</label>
				<?php echo wp_kses_post( $description_html ); ?>
			</div>
		</div>
		<?php
		$html_content = ob_get_clean();

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
		// All form elements inside $html_content are pre-escaped during generation (esc_attr, esc_html).
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
	}

	/**
	 * Render Select field.
	 *
	 * - Args:
	 *   - id (string) - Field ID.
	 *   - title (string) - Field title.
	 *   - description (string) - Field description (optional).
	 *   - options (array) - Field options [value => label].
	 *   - selected (string) - Selected option value.
	 *   - disabled (string|array) - Disabled option value(s).
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_select_field( $args = [], $should_print = true ) {
		$title       = $args['title'] ?? '';
		$description = $args['description'] ?? '';

		// Return error if ID is not provided.
		if ( empty( $args['id'] ?? '' ) ) {
			$field_hint = empty( $title ) ? 'Error' : $title;
			$no_id_msg  = '<p><strong>' . $field_hint . ':</strong> ' . __( 'Field ID is required.', 'subscription' ) . '</p>';
			return $should_print ? print wp_kses_post( $no_id_msg ) : $no_id_msg;
		}

		// Select HTML.
		$select_el_html = self::select_element( $args );

		ob_start();
		?>
		<div class="wpsubs-settings-field<?php echo ! empty( $args['pro_locked'] ) ? ' wpsubs-settings-field--locked' : ''; ?>">
			<div class="wpsubs-settings-field__label">
				<?php
				if ( ! empty( $args['pro_locked'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped badge markup.
					echo self::pro_badge_html();
				}
				?>
				<?php echo esc_html( $title ); ?>
			</div>
			<div class="wpsubs-settings-field__control">
				<?php
					// Output intentionally not escaped as element is already escaped during generation & re-escaping breaks the HTML structure.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $select_el_html;
				?>
				<?php if ( ! empty( $description ) ) : ?>
					<p class="wpsubs-settings-field__hint"><?php echo wp_kses_post( $description ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		$html_content = ob_get_clean();

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
		// All form elements inside $html_content are pre-escaped during generation (esc_attr, esc_html).
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
	}

	/**
	 * Render Multiselect field.
	 * Just a wrapper over 'render_select_field' with multiple attribute.
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_multiselect_field( $args = [], $should_print = true ) {
		$default_multiselect_args = [
			'attributes' => [
				'multiple' => 'multiple',
			],
			'enhanced'   => true,
		];

		$args = wp_parse_args( $args, $default_multiselect_args );

		return self::render_select_field( $args, $should_print );
	}

	/**
	 * Render Joined field with multiple elements.
	 *
	 * - Args:
	 *   - title (string) - Field title.
	 *   - description (string) - Field description (optional).
	 *   - vertical (bool) - Whether to show items vertically or not.
	 *   - elements ([...string]) - Array of HTML elements to join.
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_joined_field( $args = [], $should_print = true ) {
		$title       = $args['title'] ?? '';
		$description = $args['description'] ?? '';

		$vertical_style = ( $args['vertical'] ?? false ) ? 'flex-direction:column;' : '';

		ob_start();
		?>
		<div class="wpsubs-settings-field<?php echo ! empty( $args['pro_locked'] ) ? ' wpsubs-settings-field--locked' : ''; ?>">
			<div class="wpsubs-settings-field__label">
				<?php
				if ( ! empty( $args['pro_locked'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped badge markup.
					echo self::pro_badge_html();
				}
				?>
				<?php echo esc_html( $title ); ?>
			</div>
			<div class="wpsubs-settings-field__control">
				<div class="wpsubs-input-group"
				<?php
				if ( $vertical_style ) :
					?>
					style="<?php echo esc_attr( $vertical_style ); ?>"<?php endif; ?>>
					<?php
					foreach ( ( $args['elements'] ?? [] ) as $element_html ) {
						// Output intentionally not escaped as element is already escaped during generation & re-escaping breaks the HTML structure.
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $element_html;
					}
					?>
				</div>
				<?php if ( ! empty( $description ) ) : ?>
					<p class="wpsubs-settings-field__hint"><?php echo wp_kses_post( $description ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		$html_content = ob_get_clean();

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
		// All form elements inside $html_content are pre-escaped during generation (esc_attr, esc_html).
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
	}
}
