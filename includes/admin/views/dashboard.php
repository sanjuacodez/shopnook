<?php
/**
 * Dashboard screen.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

$shopnook_ranges = Shopnook_Admin::ranges();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only date range.
$shopnook_range = isset( $_GET['range'] ) ? absint( $_GET['range'] ) : 30;
if ( ! isset( $shopnook_ranges[ $shopnook_range ] ) ) {
	$shopnook_range = 30;
}

$shopnook_today     = strtotime( current_time( 'Y-m-d' ) );
$shopnook_to        = gmdate( 'Y-m-d', $shopnook_today );
$shopnook_from      = gmdate( 'Y-m-d', $shopnook_today - ( $shopnook_range - 1 ) * DAY_IN_SECONDS );
$shopnook_prev_to   = gmdate( 'Y-m-d', $shopnook_today - $shopnook_range * DAY_IN_SECONDS );
$shopnook_prev_from = gmdate( 'Y-m-d', $shopnook_today - ( 2 * $shopnook_range - 1 ) * DAY_IN_SECONDS );

$shopnook_totals   = Shopnook_Stats::totals( $shopnook_from, $shopnook_to );
$shopnook_previous = Shopnook_Stats::totals( $shopnook_prev_from, $shopnook_prev_to );
$shopnook_series   = Shopnook_Stats::series( $shopnook_from, $shopnook_to );
$shopnook_has_data = Shopnook_Stats::has_data();

/**
 * Percentage change, or null when there is nothing to compare with.
 */
$shopnook_delta = function ( $now, $before ) {
	if ( ! $before ) {
		return null;
	}
	return ( $now - $before ) / $before * 100;
};

/**
 * A share as a percentage string.
 */
$shopnook_rate = function ( $part, $whole ) {
	return $whole ? number_format_i18n( $part / $whole * 100, 1 ) . '%' : '–';
};

// Daily columns up to 90 days; months for the 12-month view.
$shopnook_buckets = array();
foreach ( $shopnook_series as $shopnook_day => $shopnook_values ) {
	$shopnook_ts  = strtotime( $shopnook_day );
	$shopnook_key = $shopnook_range > 90 ? gmdate( 'Y-m', $shopnook_ts ) : $shopnook_day;
	if ( ! isset( $shopnook_buckets[ $shopnook_key ] ) ) {
		$shopnook_buckets[ $shopnook_key ] = array(
			'label'  => $shopnook_range > 90 ? date_i18n( 'F Y', $shopnook_ts ) : date_i18n( get_option( 'date_format' ), $shopnook_ts ),
			'short'  => $shopnook_range > 90 ? date_i18n( 'M', $shopnook_ts ) : date_i18n( 'M j', $shopnook_ts ),
			'values' => array_fill_keys( array_keys( $shopnook_values ), 0 ),
		);
	}
	foreach ( $shopnook_values as $shopnook_k => $shopnook_v ) {
		$shopnook_buckets[ $shopnook_key ]['values'][ $shopnook_k ] += $shopnook_v;
	}
}

$shopnook_metrics = array(
	'views'   => __( 'Views', 'shopnook' ),
	'opens'   => __( 'Pin opens', 'shopnook' ),
	'clicks'  => __( 'Product clicks', 'shopnook' ),
	'carts'   => __( 'Add to carts', 'shopnook' ),
	'sold'    => __( 'Items sold', 'shopnook' ),
	/* translators: %s: currency symbol */
	'revenue' => sprintf( __( 'Revenue (%s)', 'shopnook' ), html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, get_bloginfo( 'charset' ) ) ),
);

$shopnook_tiles = array(
	array( 'views', __( 'Views', 'shopnook' ), '' ),
	/* translators: %s: percentage */
	array( 'opens', __( 'Pin opens', 'shopnook' ), sprintf( __( '%s of views', 'shopnook' ), $shopnook_rate( $shopnook_totals['opens'], $shopnook_totals['views'] ) ) ),
	/* translators: %s: percentage */
	array( 'clicks', __( 'Product clicks', 'shopnook' ), sprintf( __( '%s of pin opens', 'shopnook' ), $shopnook_rate( $shopnook_totals['clicks'], $shopnook_totals['opens'] ) ) ),
	array( 'carts', __( 'Add to carts', 'shopnook' ), '' ),
	/* translators: %s: number of items */
	array( 'orders', __( 'Orders', 'shopnook' ), sprintf( _n( '%s item sold', '%s items sold', $shopnook_totals['sold'], 'shopnook' ), number_format_i18n( $shopnook_totals['sold'] ) ) ),
);

$shopnook_url = function ( $range ) {
	return add_query_arg(
		array(
			'page'  => 'shopnook',
			'range' => $range,
		),
		admin_url( 'admin.php' )
	);
};

/**
 * Prints the change badge for a tile.
 */
$shopnook_change = function ( $value ) {
	if ( null === $value ) {
		echo '<span class="shopnook-tile__delta is-flat">' . esc_html__( 'No earlier data', 'shopnook' ) . '</span>';
		return;
	}
	$class = abs( $value ) < 0.5 ? 'is-flat' : ( $value > 0 ? 'is-up' : 'is-down' );
	$arrow = 'is-up' === $class ? '↑' : ( 'is-down' === $class ? '↓' : '→' );
	printf(
		'<span class="shopnook-tile__delta %1$s"><span aria-hidden="true">%2$s</span> %3$s%% <span class="screen-reader-text">%4$s</span></span>',
		esc_attr( $class ),
		esc_html( $arrow ),
		esc_html( number_format_i18n( abs( $value ), 1 ) ),
		esc_html( 'is-down' === $class ? __( 'lower than the previous period', 'shopnook' ) : __( 'higher than the previous period', 'shopnook' ) )
	);
};
?>
<div class="wrap shopnook-admin">
	<div class="shopnook-admin__head">
		<h1><?php esc_html_e( 'Shopnook', 'shopnook' ); ?></h1>
		<nav class="shopnook-range" aria-label="<?php esc_attr_e( 'Date range', 'shopnook' ); ?>">
			<?php foreach ( $shopnook_ranges as $shopnook_days => $shopnook_label ) : ?>
				<a href="<?php echo esc_url( $shopnook_url( $shopnook_days ) ); ?>" <?php echo $shopnook_days === $shopnook_range ? 'aria-current="page" class="is-current"' : ''; ?>><?php echo esc_html( $shopnook_label ); ?></a>
			<?php endforeach; ?>
		</nav>
	</div>

	<?php do_action( 'sanjayshankar_connect_card', 'shopnook' ); ?>

	<?php if ( ! Shopnook_Settings::get( 'tracking' ) ) : ?>
		<div class="notice notice-warning inline"><p>
			<?php esc_html_e( 'Tracking is turned off, so no new activity is being counted.', 'shopnook' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=shopnook-settings&tab=tracking' ) ); ?>"><?php esc_html_e( 'Tracking settings', 'shopnook' ); ?></a>
		</p></div>
	<?php endif; ?>

	<?php if ( ! $shopnook_has_data ) : ?>
		<section class="shopnook-card-box shopnook-start">
			<h2><?php esc_html_e( 'Make your first image shoppable', 'shopnook' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Edit a post or page and select an Image block (or a Gallery block).', 'shopnook' ); ?></li>
				<li><?php esc_html_e( 'In the block settings, switch on “Shoppable image” in the Shopnook panel.', 'shopnook' ); ?></li>
				<li><?php esc_html_e( 'Click “Tag products”, click the image to drop pins, and pick a product for each.', 'shopnook' ); ?></li>
			</ol>
			<p><?php esc_html_e( 'You can also tag images in the Media Library: open an image and click “Tag products”. Those pins show wherever the image is shoppable.', 'shopnook' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'Write a post', 'shopnook' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php esc_html_e( 'Open the Media Library', 'shopnook' ); ?></a>
				<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=shopnook-settings' ) ); ?>"><?php esc_html_e( 'Choose pin and card styles', 'shopnook' ); ?></a>
			</p>
		</section>
	<?php endif; ?>

	<section class="shopnook-kpis" aria-label="<?php esc_attr_e( 'Totals', 'shopnook' ); ?>">
		<div class="shopnook-tile shopnook-tile--hero">
			<span class="shopnook-tile__label"><?php esc_html_e( 'Revenue from shoppable images', 'shopnook' ); ?></span>
			<span class="shopnook-tile__value"><?php echo esc_html( Shopnook_Admin::money( $shopnook_totals['revenue'] ) ); ?></span>
			<?php $shopnook_change( $shopnook_delta( $shopnook_totals['revenue'], $shopnook_previous['revenue'] ) ); ?>
			<span class="shopnook-tile__note">
				<?php
				/* translators: %d: credit window in days */
				echo esc_html( sprintf( _n( 'Orders credited within %d day of a click.', 'Orders credited within %d days of a click.', (int) Shopnook_Settings::get( 'credit_days' ), 'shopnook' ), (int) Shopnook_Settings::get( 'credit_days' ) ) );
				?>
			</span>
		</div>
		<?php foreach ( $shopnook_tiles as $shopnook_tile ) : ?>
			<div class="shopnook-tile">
				<span class="shopnook-tile__label"><?php echo esc_html( $shopnook_tile[1] ); ?></span>
				<span class="shopnook-tile__value"><?php echo esc_html( Shopnook_Admin::compact( $shopnook_totals[ $shopnook_tile[0] ] ) ); ?></span>
				<?php $shopnook_change( $shopnook_delta( $shopnook_totals[ $shopnook_tile[0] ], $shopnook_previous[ $shopnook_tile[0] ] ) ); ?>
				<?php if ( $shopnook_tile[2] ) : ?>
					<span class="shopnook-tile__note"><?php echo esc_html( $shopnook_tile[2] ); ?></span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</section>

	<div class="shopnook-grid">
		<section class="shopnook-card-box shopnook-trend">
			<div class="shopnook-card-box__head">
				<h2><?php esc_html_e( 'Trend', 'shopnook' ); ?></h2>
				<div class="shopnook-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Measure', 'shopnook' ); ?>">
					<?php $shopnook_first = true; ?>
					<?php foreach ( $shopnook_metrics as $shopnook_key => $shopnook_label ) : ?>
						<button type="button" role="tab" id="shopnook-tab-<?php echo esc_attr( $shopnook_key ); ?>" aria-controls="shopnook-panel-<?php echo esc_attr( $shopnook_key ); ?>" aria-selected="<?php echo $shopnook_first ? 'true' : 'false'; ?>" tabindex="<?php echo $shopnook_first ? '0' : '-1'; ?>"><?php echo esc_html( $shopnook_label ); ?></button>
						<?php $shopnook_first = false; ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php $shopnook_first = true; ?>
			<?php foreach ( $shopnook_metrics as $shopnook_key => $shopnook_label ) : ?>
				<div role="tabpanel" id="shopnook-panel-<?php echo esc_attr( $shopnook_key ); ?>" aria-labelledby="shopnook-tab-<?php echo esc_attr( $shopnook_key ); ?>" <?php echo $shopnook_first ? '' : 'hidden'; ?>>
					<?php
					$shopnook_points = array();
					foreach ( $shopnook_buckets as $shopnook_bucket ) {
						$shopnook_value    = $shopnook_bucket['values'][ $shopnook_key ];
						$shopnook_points[] = array(
							$shopnook_bucket['label'],
							$shopnook_value,
							'revenue' === $shopnook_key ? Shopnook_Admin::money( $shopnook_value ) : number_format_i18n( $shopnook_value ),
							$shopnook_bucket['short'],
						);
					}
					$shopnook_chart_label = $shopnook_label;
					include SHOPNOOK_PATH . 'includes/admin/views/chart.php';
					?>
					<details class="shopnook-table-view">
						<summary><?php esc_html_e( 'Show as table', 'shopnook' ); ?></summary>
						<table class="widefat striped">
							<thead><tr><th scope="col"><?php esc_html_e( 'Date', 'shopnook' ); ?></th><th scope="col" class="num"><?php echo esc_html( $shopnook_label ); ?></th></tr></thead>
							<tbody>
								<?php foreach ( array_reverse( $shopnook_points ) as $shopnook_point ) : ?>
									<tr><td><?php echo esc_html( $shopnook_point[0] ); ?></td><td class="num"><?php echo esc_html( $shopnook_point[2] ); ?></td></tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</details>
				</div>
				<?php $shopnook_first = false; ?>
			<?php endforeach; ?>
		</section>

		<section class="shopnook-card-box shopnook-funnel">
			<h2><?php esc_html_e( 'From view to sale', 'shopnook' ); ?></h2>
			<?php
			$shopnook_steps = array(
				array( __( 'Views', 'shopnook' ), $shopnook_totals['views'] ),
				array( __( 'Pin opens', 'shopnook' ), $shopnook_totals['opens'] ),
				array( __( 'Product clicks', 'shopnook' ), $shopnook_totals['clicks'] ),
				array( __( 'Add to carts', 'shopnook' ), $shopnook_totals['carts'] ),
				array( __( 'Items sold', 'shopnook' ), $shopnook_totals['sold'] ),
			);
			$shopnook_top_step = max( 1, $shopnook_totals['views'], $shopnook_totals['opens'], $shopnook_totals['clicks'], $shopnook_totals['carts'], $shopnook_totals['sold'] );
			?>
			<ol class="shopnook-funnel__steps">
				<?php foreach ( $shopnook_steps as $shopnook_i => $shopnook_step_row ) : ?>
					<li>
						<span class="shopnook-funnel__label"><?php echo esc_html( $shopnook_step_row[0] ); ?></span>
						<span class="shopnook-funnel__track"><span class="shopnook-funnel__bar" style="width:<?php echo esc_attr( round( $shopnook_step_row[1] / $shopnook_top_step * 100, 2 ) ); ?>%"></span></span>
						<span class="shopnook-funnel__value"><?php echo esc_html( number_format_i18n( $shopnook_step_row[1] ) ); ?></span>
						<span class="shopnook-funnel__rate">
							<?php
							if ( $shopnook_i ) {
								/* translators: %s: percentage of the previous step */
								echo esc_html( sprintf( __( '%s of previous', 'shopnook' ), $shopnook_rate( $shopnook_step_row[1], $shopnook_steps[ $shopnook_i - 1 ][1] ) ) );
							}
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
			<p class="shopnook-muted"><?php esc_html_e( 'Items sold counts paid orders only. Cancelled and refunded orders are removed.', 'shopnook' ); ?></p>
		</section>
	</div>

	<?php
	$shopnook_tables = array(
		'attachment' => array(
			'title'   => __( 'Top images', 'shopnook' ),
			'columns' => array( 'views', 'opens', 'clicks', 'carts', 'sold', 'revenue' ),
		),
		'product'    => array(
			'title'   => __( 'Top products', 'shopnook' ),
			'columns' => array( 'opens', 'clicks', 'carts', 'sold', 'revenue' ),
		),
		'post'       => array(
			'title'   => __( 'Top posts and galleries', 'shopnook' ),
			'columns' => array( 'views', 'opens', 'clicks', 'sold', 'revenue' ),
		),
	);
	$shopnook_column_labels = array(
		'views'   => __( 'Views', 'shopnook' ),
		'opens'   => __( 'Opens', 'shopnook' ),
		'clicks'  => __( 'Clicks', 'shopnook' ),
		'carts'   => __( 'Carts', 'shopnook' ),
		'sold'    => __( 'Sold', 'shopnook' ),
		'revenue' => __( 'Revenue', 'shopnook' ),
	);
	?>
	<div class="shopnook-tables">
		<?php foreach ( $shopnook_tables as $shopnook_group => $shopnook_table ) : ?>
			<?php $shopnook_rows = Shopnook_Stats::top( $shopnook_group, $shopnook_from, $shopnook_to ); ?>
			<section class="shopnook-card-box shopnook-top shopnook-top--<?php echo esc_attr( $shopnook_group ); ?>">
				<h2><?php echo esc_html( $shopnook_table['title'] ); ?></h2>
				<?php if ( ! $shopnook_rows ) : ?>
					<p class="shopnook-muted"><?php esc_html_e( 'Nothing in this period yet.', 'shopnook' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html( 'product' === $shopnook_group ? __( 'Product', 'shopnook' ) : ( 'post' === $shopnook_group ? __( 'Post', 'shopnook' ) : __( 'Image', 'shopnook' ) ) ); ?></th>
								<?php foreach ( $shopnook_table['columns'] as $shopnook_col ) : ?>
									<th scope="col" class="num"><?php echo esc_html( $shopnook_column_labels[ $shopnook_col ] ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $shopnook_rows as $shopnook_row ) : ?>
								<tr>
									<td class="shopnook-top__name"><div class="shopnook-top__cell">
										<?php
										if ( 'attachment' === $shopnook_group ) {
											$shopnook_title = get_the_title( $shopnook_row['id'] );
											echo wp_get_attachment_image( $shopnook_row['id'], array( 40, 40 ), false, array( 'alt' => '' ) );
											$shopnook_link = get_edit_post_link( $shopnook_row['id'] );
										} elseif ( 'product' === $shopnook_group ) {
											$shopnook_title = get_the_title( $shopnook_row['id'] );
											$shopnook_link  = get_edit_post_link( $shopnook_row['id'] );
										} else {
											$shopnook_title = get_the_title( $shopnook_row['id'] );
											$shopnook_link  = get_permalink( $shopnook_row['id'] );
										}
										$shopnook_title = '' !== $shopnook_title ? $shopnook_title : '#' . $shopnook_row['id'];
										if ( $shopnook_link ) {
											echo '<a href="' . esc_url( $shopnook_link ) . '">' . esc_html( $shopnook_title ) . '</a>';
										} else {
											echo esc_html( $shopnook_title );
										}
										if ( 'post' === $shopnook_group ) {
											echo ' <span class="shopnook-chip">' . esc_html( 2 === $shopnook_row['source'] ? __( 'Gallery', 'shopnook' ) : __( 'Image', 'shopnook' ) ) . '</span>';
										}
										?>
									</div></td>
									<?php foreach ( $shopnook_table['columns'] as $shopnook_col ) : ?>
										<td class="num"><?php echo esc_html( 'revenue' === $shopnook_col ? Shopnook_Admin::money( $shopnook_row['revenue'] ) : number_format_i18n( $shopnook_row[ $shopnook_col ] ) ); ?></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
</div>
