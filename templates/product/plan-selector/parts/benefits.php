<?php
/**
 * Plan selector part — a group's benefits, under their heading.
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector/parts/benefits.php
 *
 * @var array $view `PlanSelectorView::group()` for the group.
 *
 * @package SpringDevs\Subscription
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $view['benefits'] ) ) {
	return;
}
?>
<?php if ( '' !== $view['benefits_heading'] ) : ?>
	<p class="subscrpt-buybox__benefits-heading" id="<?php echo esc_attr( $view['gid'] . '-benefits' ); ?>"><?php echo esc_html( $view['benefits_heading'] ); ?></p>
<?php endif; ?>
<ul class="subscrpt-buybox__benefits"<?php echo '' !== $view['benefits_heading'] ? ' aria-labelledby="' . esc_attr( $view['gid'] . '-benefits' ) . '"' : ''; ?>>
	<?php foreach ( $view['benefits'] as $subscrpt_line ) : ?>
		<li><?php echo esc_html( $subscrpt_line ); ?></li>
	<?php endforeach; ?>
</ul>
