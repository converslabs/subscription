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
	private function __construct() {}

	/**
	 * Text Element HTML.
	 *
	 * @param array $args Same as 'render_text_field'.
	 * @param bool  $join_item Whether to return element for 'join' container or not.
	 */
	public static function inp_text_element( $args = [], $join_item = false ) {
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
	public static function inp_select_element( $args = [], $join_item = false ) {
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
	public static function render_text_field( $args = [], $should_print = true ) {
		$title       = $args['title'] ?? '';
		$description = $args['description'] ?? '';

		// Return error if ID is not provided.
		if ( empty( $args['id'] ?? '' ) ) {
			$field_hint = empty( $title ) ? 'Error' : $title;
			$no_id_msg  = '<p><strong>' . $field_hint . ':</strong> ' . __( 'Field ID is required.', 'wp_subscription' ) . '</p>';
			return $should_print ? print wp_kses_post( $no_id_msg ) : $no_id_msg;
		}

		// Input HTML.
		$text_el_html = self::inp_text_element( $args );

		$description_html = '';
		if ( ! empty( $description ) ) {
			$description_html = sprintf(
				'<span class="label mt-2 ml-0.5">%s</span>',
				esc_html( $description )
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
				'<span class="label mt-2 ml-0.5">%s</span>',
				esc_html( $description )
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
	 *   - disabled (bool|array) - Disabled option value(s).
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
		$select_el_html = self::inp_select_element( $args );

		$description_html = '';
		if ( ! empty( $description ) ) {
			$description_html = sprintf(
				'<span class="label mt-2 ml-0.5">%s</span>',
				esc_html( $description )
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
}
