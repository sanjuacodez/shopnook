<?php
/**
 * One-series column chart, as inline SVG.
 *
 * Expects $shopnook_points: list of [ label, value, display value ], and
 * $shopnook_chart_label: what is plotted. Single series, so no legend box;
 * the tab above names it. Hover and arrow keys show each value (admin.js),
 * and the table view below the chart lists every value.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

$shopnook_w      = 1100;
$shopnook_h      = 260;
$shopnook_left   = 48;
$shopnook_right  = 8;
$shopnook_top    = 12;
$shopnook_bottom = 28;
$shopnook_plot_w = $shopnook_w - $shopnook_left - $shopnook_right;
$shopnook_plot_h = $shopnook_h - $shopnook_top - $shopnook_bottom;
$shopnook_n      = max( 1, count( $shopnook_points ) );
$shopnook_max    = 0;
foreach ( $shopnook_points as $shopnook_point ) {
	$shopnook_max = max( $shopnook_max, (float) $shopnook_point[1] );
}

// Round the axis up to a clean step: 1, 2, 2.5 or 5 times a power of ten.
$shopnook_step = 1;
if ( $shopnook_max > 4 ) {
	$shopnook_raw  = $shopnook_max / 4;
	$shopnook_mag  = pow( 10, floor( log10( $shopnook_raw ) ) );
	$shopnook_step = $shopnook_mag * 10;
	foreach ( array( 1, 2, 2.5, 5, 10 ) as $shopnook_mult ) {
		if ( $shopnook_mult * $shopnook_mag >= $shopnook_raw ) {
			$shopnook_step = $shopnook_mult * $shopnook_mag;
			break;
		}
	}
}
$shopnook_y_max = max( 4, ceil( $shopnook_max / $shopnook_step ) * $shopnook_step );
$shopnook_band  = $shopnook_plot_w / $shopnook_n;
$shopnook_bar_w = min( 24, max( 2, $shopnook_band * 0.7 ) );
$shopnook_base  = $shopnook_top + $shopnook_plot_h;
$shopnook_every = (int) ceil( $shopnook_n / 10 );
?>
<div class="shopnook-chart" data-label="<?php echo esc_attr( $shopnook_chart_label ); ?>">
	<svg viewBox="0 0 <?php echo (int) $shopnook_w; ?> <?php echo (int) $shopnook_h; ?>" role="img" aria-label="<?php echo esc_attr( $shopnook_chart_label ); ?>" tabindex="0" focusable="true">
		<?php for ( $shopnook_t = 0; $shopnook_t <= $shopnook_y_max; $shopnook_t += $shopnook_step ) : ?>
			<?php $shopnook_y = $shopnook_base - ( $shopnook_t / $shopnook_y_max ) * $shopnook_plot_h; ?>
			<line class="shopnook-chart__grid" x1="<?php echo esc_attr( $shopnook_left ); ?>" x2="<?php echo esc_attr( $shopnook_w - $shopnook_right ); ?>" y1="<?php echo esc_attr( round( $shopnook_y, 2 ) ); ?>" y2="<?php echo esc_attr( round( $shopnook_y, 2 ) ); ?>"/>
			<text class="shopnook-chart__tick" x="<?php echo esc_attr( $shopnook_left - 8 ); ?>" y="<?php echo esc_attr( round( $shopnook_y + 4, 2 ) ); ?>" text-anchor="end"><?php echo esc_html( Shopnook_Admin::compact( $shopnook_t ) ); ?></text>
		<?php endfor; ?>
		<?php foreach ( $shopnook_points as $shopnook_i => $shopnook_point ) : ?>
			<?php
			$shopnook_x  = $shopnook_left + $shopnook_i * $shopnook_band;
			$shopnook_bh = $shopnook_point[1] > 0 ? max( 2, ( $shopnook_point[1] / $shopnook_y_max ) * $shopnook_plot_h ) : 0;
			$shopnook_bx = $shopnook_x + ( $shopnook_band - $shopnook_bar_w ) / 2;
			$shopnook_r  = min( 4, $shopnook_bh, $shopnook_bar_w / 2 );
			?>
			<g class="shopnook-chart__col" data-label="<?php echo esc_attr( $shopnook_point[0] ); ?>" data-value="<?php echo esc_attr( $shopnook_point[2] ); ?>">
				<rect class="shopnook-chart__hit" x="<?php echo esc_attr( round( $shopnook_x, 2 ) ); ?>" y="<?php echo esc_attr( $shopnook_top ); ?>" width="<?php echo esc_attr( round( $shopnook_band, 2 ) ); ?>" height="<?php echo esc_attr( $shopnook_plot_h ); ?>"/>
				<?php if ( $shopnook_bh > 0 ) : ?>
					<path class="shopnook-chart__bar" d="<?php echo esc_attr( sprintf( 'M%1$.2F %2$.2FV%3$.2FA%4$.2F %4$.2F 0 0 1 %5$.2F %6$.2FH%7$.2FA%4$.2F %4$.2F 0 0 1 %8$.2F %3$.2FV%2$.2FZ', $shopnook_bx, $shopnook_base, $shopnook_base - $shopnook_bh + $shopnook_r, $shopnook_r, $shopnook_bx + $shopnook_r, $shopnook_base - $shopnook_bh, $shopnook_bx + $shopnook_bar_w - $shopnook_r, $shopnook_bx + $shopnook_bar_w ) ); ?>"/>
				<?php endif; ?>
			</g>
			<?php if ( 0 === $shopnook_i % $shopnook_every ) : ?>
				<text class="shopnook-chart__tick" x="<?php echo esc_attr( round( $shopnook_x + $shopnook_band / 2, 2 ) ); ?>" y="<?php echo esc_attr( $shopnook_h - 8 ); ?>" text-anchor="middle"><?php echo esc_html( $shopnook_point[3] ); ?></text>
			<?php endif; ?>
		<?php endforeach; ?>
		<line class="shopnook-chart__axis" x1="<?php echo esc_attr( $shopnook_left ); ?>" x2="<?php echo esc_attr( $shopnook_w - $shopnook_right ); ?>" y1="<?php echo esc_attr( $shopnook_base ); ?>" y2="<?php echo esc_attr( $shopnook_base ); ?>"/>
	</svg>
	<div class="shopnook-chart__tip" hidden><strong></strong><span></span></div>
</div>
