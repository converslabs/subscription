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
		// Return error if ID is not provided.
		if ( empty( $args['id'] ?? '' ) ) {
			$no_id_msg = '<p>' . __( '<strong>Error:</strong> Field ID is required.', 'wp_subscription' ) . '</p>';
			return $should_print ? print wp_kses_post( $no_id_msg ) : $no_id_msg;
		}

		// Render start.
		$id          = $args['id'];
		$title       = $args['title'] ?? '';
		$description = $args['description'] ?? '';

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

		$description_html = '';
		if ( ! empty( $description ) ) {
			$description_html = <<<HTML
            <span class="label mt-2 ml-1">{$description}</span>
HTML;
		}

		$html_content = <<<HTML
            <div class="grid grid-cols-6 gap-4">
                <label for="{$id}" class="font-semibold align-middle text-sm">{$title}</label>

                <div class="col-span-5">
                    <select
                        id="{$id}"
                        class="select! min-w-80! max-w-full!"
                    >
                        {$options_html}
                    </select>

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
