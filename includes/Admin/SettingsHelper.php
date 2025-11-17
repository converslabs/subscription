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
	public static function render_settings_field( $field = 'input', $args, $should_print = true ) {
		switch ( $field ) {
			case 'heading':
				return self::render_heading( $args, $should_print );
			case 'switch':
			case 'toggle':
				return self::render_switch_field( $args, $should_print );
			case 'select':
				return self::render_select_field( $args, $should_print );
			case 'join':
				return self::render_joined_field( $args, $should_print );
			case 'input':
			default:
				return self::render_input_field( $args, $should_print );
		}
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

		$join_class = $join_item ? 'join-item mx-0!' : '';

		$html_content = <<<HTML
			<input 
				id="{$id}"
				name="{$id}"
				class="input! min-w-80! max-w-full! {$join_class}"
				style="outline-offset: 0.5px !important; outline-color: #e5e7eb !important;"
				type="{$type}"
				placeholder="{$placeholder}"
				value="{$value}"
				{$disabled_attr}
			/>
		HTML;

		return $html_content;
	}

	/**
	 * Select Element HTML.
	 *
	 * @param array $args Same as 'render_select_field'.
	 * @param bool  $join_item Whether to return element for 'join' container or not.
	 */
	public static function select_element( $args = [], $join_item = false ) {
		$id    = $args['id'];
		$value = $args['value'] ?? '';

		$join_class = $join_item ? 'join-item mx-0!' : '';

		$options_html = '';
		foreach ( ( $args['options'] ?? [] ) as $value => $label ) {
			$selected = isset( $args['selected'] ) && $args['selected'] === $value;
			$disabled = false;
			if ( isset( $args['disabled'] ) ) {
				if ( is_array( $args['disabled'] ) ) {
					$disabled = in_array( $value, $args['disabled'], true );
				} else {
					$disabled = $args['disabled'] === $value;
				}
			}

			$options_tmp_html = sprintf(
				'<option value="%s" %s %s>%s</option>',
				esc_attr( $value ),
				$selected ? 'selected' : '',
				$disabled ? 'disabled' : '',
				esc_html( $label ),
			);
			$options_html    .= $options_tmp_html;
		}

		$html_content = <<<HTML
			<select
				id="{$id}"
				name="{$id}"
				class="select! min-w-80! max-w-full! {$join_class}"
				style="outline-offset: 0.5px !important; outline-color: #e5e7eb !important;"
			>
				{$options_html}
			</select>
		HTML;

		return $html_content;
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

		$description_html = '';
		if ( ! empty( $description ) ) {
			$description_html = sprintf(
				'<p class="mb-0! mt-2! ml-0.5! text-[13px]! text-gray-500!">%s</p>',
				wp_kses_post( $description )
			);
		}

		$html_content = <<<HTML
            <div class="my-4 first-of-type:mt-0">
                <h2 class="m-0!">{$title}</h2>
				{$description_html}
            </div>
HTML;

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
			$no_id_msg  = '<p><strong>' . $field_hint . ':</strong> ' . __( 'Field ID is required.', 'wp_subscription' ) . '</p>';
			return $should_print ? print wp_kses_post( $no_id_msg ) : $no_id_msg;
		}

		// Input HTML.
		$text_el_html = self::inp_element( $args );

		$description_html = '';
		if ( ! empty( $description ) ) {
			$description_html = sprintf(
				'<p class="mb-0! mt-2! ml-0.5! text-[13px]! text-gray-500!">%s</p>',
				wp_kses_post( $description )
			);
		}

		$html_content = <<<HTML
            <div class="grid grid-cols-6 gap-4">
                <span class="font-semibold text-sm mt-0.5">{$title}</span>

                <div class="col-span-5">
                    {$text_el_html}
                    <br/>
                    {$description_html}
                </div>
            </div>
HTML;

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
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
			$no_id_msg  = '<p><strong>' . $field_hint . ':</strong> ' . __( 'Field ID is required.', 'wp_subscription' ) . '</p>';
			return $should_print ? print wp_kses_post( $no_id_msg ) : $no_id_msg;
		}

		$description_html = '';
		if ( ! empty( $description ) ) {
			$description_html = sprintf(
				'<p class="mb-0! mt-2! ml-0.5! text-[13px]! text-gray-500!">%s</p>',
				wp_kses_post( $description )
			);
		}

		$checked_attr  = isset( $args['checked'] ) && (bool) $args['checked'] ? 'checked' : '';
		$disabled_attr = isset( $args['disabled'] ) && (bool) $args['disabled'] ? 'disabled' : '';

		$html_content = <<<HTML
            <div class="grid grid-cols-6 gap-4">
                <span class="font-semibold text-sm mt-0.5">{$title}</span>

                <div class="col-span-5">
                    <label for="{$id}">
                        <input 
                            id="{$id}"
                            name="{$id}"
                            class="wp-subscription-toggle"
                            type="checkbox" 
                            value="{$value}"
                            {$checked_attr}
                            {$disabled_attr}
                        />
                        <span class="wp-subscription-toggle-ui" aria-hidden="true"></span>

                        <span class="ml-2 text-sm align-middle">{$label}</span>
                    </label>

                    <br/>
                    {$description_html}
                </div>
            </div>
HTML;

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
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
			$no_id_msg  = '<p><strong>' . $field_hint . ':</strong> ' . __( 'Field ID is required.', 'wp_subscription' ) . '</p>';
			return $should_print ? print wp_kses_post( $no_id_msg ) : $no_id_msg;
		}

		// Select HTML.
		$select_el_html = self::select_element( $args );

		$description_html = '';
		if ( ! empty( $description ) ) {
			$description_html = sprintf(
				'<p class="mb-0! mt-2! ml-0.5! text-[13px]! text-gray-500!">%s</p>',
				wp_kses_post( $description )
			);
		}

		$html_content = <<<HTML
            <div class="grid grid-cols-6 gap-4">
                <span class="font-semibold text-sm mt-0.5">{$title}</span>

                <div class="col-span-5">
                    {$select_el_html}
                    <br/>
                    {$description_html}
                </div>
            </div>
HTML;

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
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

		$description_html = '';
		if ( ! empty( $description ) ) {
			$description_html = sprintf(
				'<p class="mb-0! mt-2! ml-0.5! text-[13px]! text-gray-500!">%s</p>',
				wp_kses_post( $description )
			);
		}

		$vertical_class = ( $args['vertical'] ?? false ) ? 'join-vertical' : '';

		$join_items_html = '';
		foreach ( ( $args['elements'] ?? [] ) as $element_html ) {
			$join_items_html .= $element_html;
		}

		$html_content = <<<HTML
            <div class="grid grid-cols-6 gap-4">
                <span class="font-semibold text-sm mt-0.5">{$title}</span>

                <div class="col-span-5">
					<div class="join {$vertical_class}">
						{$join_items_html}
					</div>
                    <br/>
                    {$description_html}
                </div>
            </div>
HTML;

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
	}
}
