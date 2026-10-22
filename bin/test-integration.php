<?php
/**
 * Integration checks. Run with `wp eval-file bin/test-integration.php` on a
 * site with WooCommerce and Shopnook active. Fixtures are removed afterwards.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

global $shopnook_test_failed, $shopnook_test_count;
$shopnook_test_failed = 0;
$shopnook_test_count  = 0;

/**
 * Prints one result.
 *
 * @param bool   $condition Passed.
 * @param string $label     What was checked.
 */
function shopnook_test_assert( $condition, $label ) {
	global $shopnook_test_failed, $shopnook_test_count;
	++$shopnook_test_count;
	if ( ! $condition ) {
		++$shopnook_test_failed;
	}
	$line = ( $condition ? 'PASS ' : 'FAIL ' ) . $label;
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( $line );
	} else {
		echo esc_html( $line ) . "\n";
	}
}

global $wpdb;
$shopnook_table    = Shopnook_DB::table();
$shopnook_saved    = get_option( Shopnook_Settings::OPTION );
$shopnook_cleanup  = array();
$shopnook_orders   = array();
$shopnook_day      = '2001-01-01';
$shopnook_stats_of = function ( $event, $product = null ) use ( $wpdb ) {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Reads the plugin's own table.
	if ( null === $product ) {
		return $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(hits),0) AS hits, COALESCE(SUM(revenue),0) AS revenue FROM %i WHERE event = %d', Shopnook_DB::table(), Shopnook_Tracking::EVENTS[ $event ] ), ARRAY_A );
	}
	return $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(hits),0) AS hits, COALESCE(SUM(revenue),0) AS revenue FROM %i WHERE event = %d AND product_id = %d', Shopnook_DB::table(), Shopnook_Tracking::EVENTS[ $event ], $product ), ARRAY_A );
	// phpcs:enable
};

try {
	// Fixtures.
	$shopnook_product = new WC_Product_Simple();
	$shopnook_product->set_name( 'Shopnook fixture lamp' );
	$shopnook_product->set_status( 'publish' );
	$shopnook_product->set_regular_price( '50' );
	$shopnook_product_id = $shopnook_product->save();
	$shopnook_cleanup[]  = $shopnook_product_id;

	$shopnook_draft = new WC_Product_Simple();
	$shopnook_draft->set_name( 'Shopnook fixture draft' );
	$shopnook_draft->set_status( 'draft' );
	$shopnook_draft->set_regular_price( '10' );
	$shopnook_draft_id  = $shopnook_draft->save();
	$shopnook_cleanup[] = $shopnook_draft_id;

	$shopnook_im = imagecreatetruecolor( 400, 300 );
	ob_start();
	imagejpeg( $shopnook_im );
	$shopnook_upload = wp_upload_bits( 'shopnook-fixture.jpg', null, ob_get_clean() );
	$shopnook_att    = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Shopnook fixture',
			'post_status'    => 'inherit',
		),
		$shopnook_upload['file']
	);
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $shopnook_att, wp_generate_attachment_metadata( $shopnook_att, $shopnook_upload['file'] ) );
	$shopnook_cleanup[] = $shopnook_att;

	// Settings.
	$shopnook_clean = Shopnook_Settings::sanitize(
		array(
			'_tab'       => 'pins',
			'pin_style'  => 'nonsense',
			'pin_bg'     => 'red; background:url(x)',
			'pin_size'   => 'lg',
			'gallery_columns' => 99,
		)
	);
	shopnook_test_assert( 'dot' === $shopnook_clean['pin_style'] && '#111111' === $shopnook_clean['pin_bg'] && 'lg' === $shopnook_clean['pin_size'], 'Settings reject unknown choices and bad colours' );
	shopnook_test_assert( 6 === $shopnook_clean['gallery_columns'], 'Gallery columns are clamped' );
	$shopnook_clean = Shopnook_Settings::sanitize( array( '_tab' => 'tracking' ) );
	shopnook_test_assert( false === $shopnook_clean['tracking'] && false === $shopnook_clean['exclude_staff'], 'Unchecked boxes on the posted tab switch off' );
	shopnook_test_assert( Shopnook_Settings::get( 'card_fields' ) === $shopnook_clean['card_fields'], 'Settings on other tabs are kept' );

	$shopnook_over = Shopnook_Settings::sanitize_overrides(
		array(
			'display'     => 'drawer',
			'pin_style'   => '',
			'card_fields' => array( 'view', 'name', 'bogus' ),
			'not_allowed' => 'x',
			'pin_color'   => '#00ff00',
		)
	);
	$shopnook_keys = array_keys( $shopnook_over );
	sort( $shopnook_keys );
	shopnook_test_assert( array( 'card_fields', 'display', 'pin_color' ) === $shopnook_keys && array( 'name', 'view' ) === $shopnook_over['card_fields'], 'Overrides keep only allowed, non-empty keys; card fields in canonical order' );
	$shopnook_look = Shopnook_Settings::resolve( array( 'display' => 'panel', 'pin_size' => 'sm' ), array( 'display' => 'list' ) );
	shopnook_test_assert( 'list' === $shopnook_look['display'] && 'sm' === $shopnook_look['pin_size'], 'Block overrides beat Media Library overrides, which beat settings' );
	shopnook_test_assert( 'dot' === Shopnook_Settings::resolve( array( 'pin_style' => 'custom' ) )['pin_style'] || Shopnook_Settings::get( 'pin_image' ), 'A custom pin without an image falls back to a dot' );

	// Media Library pins.
	$shopnook_saved_pins = Shopnook_Images::save(
		$shopnook_att,
		array(
			'hotspots' => array(
				array( 'x' => 150, 'y' => -5, 'product' => $shopnook_product_id, 'label' => '<b>Lamp</b>' ),
				array( 'x' => 10, 'y' => 10, 'product' => 999999999 ),
				array( 'x' => 20, 'y' => 20, 'product' => $shopnook_att ),
				array( 'x' => 30, 'y' => 30, 'product' => $shopnook_draft_id ),
			),
		)
	);
	shopnook_test_assert( 2 === count( $shopnook_saved_pins['hotspots'] ), 'Pins without a real product are dropped on save' );
	shopnook_test_assert( 100.0 === $shopnook_saved_pins['hotspots'][0]['x'] && 0.0 === $shopnook_saved_pins['hotspots'][0]['y'] && 'Lamp' === $shopnook_saved_pins['hotspots'][0]['label'], 'Positions are clamped and labels stripped' );

	// Rendering an Image block.
	$shopnook_block = function ( $settings ) use ( $shopnook_att ) {
		$attrs = array(
			'id'       => $shopnook_att,
			'shopnook' => $settings,
		);
		return do_blocks( '<!-- wp:image ' . wp_json_encode( $attrs ) . ' --><figure class="wp-block-image"><img src="' . esc_url( wp_get_attachment_url( $shopnook_att ) ) . '" alt="" class="wp-image-' . $shopnook_att . '"/><figcaption class="wp-element-caption">Cap</figcaption></figure><!-- /wp:image -->' );
	};
	$shopnook_html = $shopnook_block( array( 'enabled' => true ) );
	shopnook_test_assert( 1 === substr_count( $shopnook_html, 'class="shopnook-pin ' ), 'Shoppable image renders one pin (the draft product is hidden)' );
	shopnook_test_assert( false !== strpos( $shopnook_html, 'shopnook-stage' ) && strpos( $shopnook_html, 'shopnook-cards' ) < strpos( $shopnook_html, '<figcaption' ), 'Pins wrap the image; cards sit before the caption' );
	shopnook_test_assert( false === strpos( $shopnook_block( array( 'enabled' => false ) ), 'shopnook-pin' ), 'Switched off: a plain image' );
	$shopnook_html = $shopnook_block(
		array(
			'enabled'   => true,
			'custom'    => true,
			'hotspots'  => array(
				array( 'x' => 10, 'y' => 10, 'product' => $shopnook_product_id ),
				array( 'x' => 60, 'y' => 60, 'product' => $shopnook_product_id ),
			),
			'overrides' => array(
				'display'   => 'list',
				'pin_style' => 'number',
			),
		)
	);
	shopnook_test_assert( 2 === substr_count( $shopnook_html, 'class="shopnook-pin ' ) && false !== strpos( $shopnook_html, 'shopnook-list__items' ) && false !== strpos( $shopnook_html, 'shopnook-pin__num">2<' ), 'Block pins and look override the Media Library' );
	shopnook_test_assert( false === strpos( $shopnook_html, '<b>' ), 'No unescaped label markup in output' );

	// Gallery.
	$shopnook_gallery = do_blocks( '<!-- wp:gallery {"shopnook":{"enabled":true,"layout":"stories"}} --><figure class="wp-block-gallery has-nested-images"><!-- wp:image {"id":' . $shopnook_att . '} --><figure class="wp-block-image"><img src="x" alt="Fixture alt" class="wp-image-' . $shopnook_att . '"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->' );
	shopnook_test_assert( false !== strpos( $shopnook_gallery, 'shopnook-gallery--stories' ) && 1 === substr_count( $shopnook_gallery, 'class="shopnook-pin ' ), 'Stories gallery renders slides with pins' );
	shopnook_test_assert( 0 === Shopnook_Render::$in_gallery, 'Gallery render resets the inner-image flag' );
	$shopnook_gallery = do_blocks( '<!-- wp:gallery {"shopnook":{"enabled":true,"layout":"instagram"}} --><figure class="wp-block-gallery has-nested-images"><!-- wp:image {"id":' . $shopnook_att . '} --><figure class="wp-block-image"><img src="x" alt="Fixture alt" class="wp-image-' . $shopnook_att . '"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->' );
	preg_match( '#<script type="application/json" class="shopnook-gallery__data">(.*?)</script>#', $shopnook_gallery, $shopnook_m );
	$shopnook_data = $shopnook_m ? json_decode( $shopnook_m[1], true ) : null;
	shopnook_test_assert( is_array( $shopnook_data ) && 'Fixture alt' === $shopnook_data[0]['alt'] && 1 === count( $shopnook_data[0]['spots'] ), 'Instagram gallery ships photo data with alt text and pins' );

	// Events.
	Shopnook_Settings::update( array( 'tracking' => true ) );
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Chrome/130';
	$_SERVER['REMOTE_ADDR']     = '203.0.113.' . wp_rand( 1, 250 );
	$shopnook_before            = $shopnook_stats_of( 'open', $shopnook_product_id );
	$shopnook_stored            = Shopnook_Tracking::ingest(
		array(
			array( 'e' => 'open', 's' => 1, 'a' => $shopnook_att, 'p' => 0, 'pr' => $shopnook_product_id ),
			array( 'e' => 'open', 's' => 1, 'a' => $shopnook_att, 'p' => 0, 'pr' => $shopnook_product_id ),
			array( 'e' => 'purchase', 's' => 1, 'a' => $shopnook_att, 'pr' => $shopnook_product_id ),
			array( 'e' => 'click', 's' => 1, 'a' => $shopnook_att, 'pr' => $shopnook_att ),
			array( 'e' => 'view', 's' => 2, 'a' => $shopnook_att, 'p' => 0 ),
		)
	);
	shopnook_test_assert( 3 === $shopnook_stored && 2 === (int) $shopnook_stats_of( 'open', $shopnook_product_id )['hits'] - (int) $shopnook_before['hits'], 'Valid events are counted; purchases and non-products from the browser are refused' );
	$_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';
	shopnook_test_assert( 0 === Shopnook_Tracking::ingest( array( array( 'e' => 'view', 'a' => $shopnook_att ) ) ), 'Bots are not counted' );
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Chrome/130';
	$shopnook_events = array_fill( 0, 50, array( 'e' => 'view', 'a' => $shopnook_att ) );
	$shopnook_n      = 0;
	for ( $shopnook_i = 0; $shopnook_i < 14; $shopnook_i++ ) {
		$shopnook_n += Shopnook_Tracking::ingest( $shopnook_events );
	}
	shopnook_test_assert( $shopnook_n <= Shopnook_Tracking::RATE_LIMIT, 'Per-IP rate limit caps a flood' );

	// Cookie parsing.
	$shopnook_now    = time();
	$shopnook_parsed = Shopnook_Tracking::parse_cookie( "7.1.10.20.{$shopnook_now}_7.2.11.21.{$shopnook_now}_8.1.12.22.100_bad.entry", $shopnook_now - DAY_IN_SECONDS );
	shopnook_test_assert( array( 7 ) === array_keys( $shopnook_parsed ) && 10 === $shopnook_parsed[7]['a'], 'Cookie: newest click wins, expired clicks and junk ignored' );

	// Attribution and purchases.
	$_COOKIE[ Shopnook_Tracking::COOKIE ] = $shopnook_product_id . '.1.' . $shopnook_att . '.0.' . $shopnook_now;
	$shopnook_order                       = wc_create_order();
	$shopnook_order->add_product( wc_get_product( $shopnook_product_id ), 2 );
	$shopnook_order->calculate_totals();
	$shopnook_orders[] = $shopnook_order->get_id();
	$shopnook_sold     = $shopnook_stats_of( 'purchase', $shopnook_product_id );
	Shopnook_Tracking::attribute( $shopnook_order );
	$shopnook_order = wc_get_order( $shopnook_order->get_id() );
	shopnook_test_assert( is_array( $shopnook_order->get_meta( Shopnook_Tracking::ORDER_META ) ), 'Order lines are credited from the cookie' );
	shopnook_test_assert( (int) $shopnook_sold['hits'] === (int) $shopnook_stats_of( 'purchase', $shopnook_product_id )['hits'], 'Unpaid orders are not counted yet' );
	$shopnook_order->update_status( 'processing' );
	$shopnook_after = $shopnook_stats_of( 'purchase', $shopnook_product_id );
	shopnook_test_assert( 2 === (int) $shopnook_after['hits'] - (int) $shopnook_sold['hits'] && abs( (float) $shopnook_after['revenue'] - (float) $shopnook_sold['revenue'] - 100 ) < 0.01, 'Paid order counts 2 items and 100 revenue' );
	$shopnook_order = wc_get_order( $shopnook_order->get_id() );
	$shopnook_order->update_status( 'completed' );
	shopnook_test_assert( (int) $shopnook_after['hits'] === (int) $shopnook_stats_of( 'purchase', $shopnook_product_id )['hits'], 'Completing a paid order does not count it twice' );
	$shopnook_order = wc_get_order( $shopnook_order->get_id() );
	$shopnook_order->update_status( 'cancelled' );
	shopnook_test_assert( (int) $shopnook_sold['hits'] === (int) $shopnook_stats_of( 'purchase', $shopnook_product_id )['hits'], 'Cancelling removes the sale' );

	$_COOKIE[ Shopnook_Tracking::COOKIE ] = $shopnook_product_id . '.1.' . $shopnook_att . '.0.' . ( $shopnook_now - 30 * DAY_IN_SECONDS );
	$shopnook_order2                      = wc_create_order();
	$shopnook_order2->add_product( wc_get_product( $shopnook_product_id ), 1 );
	$shopnook_order2->calculate_totals();
	$shopnook_orders[] = $shopnook_order2->get_id();
	Shopnook_Tracking::attribute( $shopnook_order2 );
	shopnook_test_assert( ! wc_get_order( $shopnook_order2->get_id() )->get_meta( Shopnook_Tracking::ORDER_META ), 'Clicks outside the credit window are not credited' );
	unset( $_COOKIE[ Shopnook_Tracking::COOKIE ] );

	// REST.
	wp_set_current_user( 0 );
	shopnook_test_assert( in_array( rest_do_request( new WP_REST_Request( 'GET', '/shopnook/v1/products' ) )->get_status(), array( 401, 403 ), true ), 'Product search needs an editor' );
	shopnook_test_assert( in_array( rest_do_request( new WP_REST_Request( 'POST', '/shopnook/v1/images/' . $shopnook_att ) )->get_status(), array( 401, 403 ), true ), 'Saving pins needs edit rights' );
	$shopnook_req = new WP_REST_Request( 'POST', '/shopnook/v1/events' );
	$shopnook_req->set_body( '{"events":[{"e":"view","a":' . $shopnook_att . '}]}' );
	shopnook_test_assert( 200 === rest_do_request( $shopnook_req )->get_status(), 'Events route is public' );
	$shopnook_admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	wp_set_current_user( (int) $shopnook_admins[0] );
	$shopnook_req = new WP_REST_Request( 'GET', '/shopnook/v1/products' );
	$shopnook_req->set_param( 'search', 'fixture lamp' );
	$shopnook_res = rest_do_request( $shopnook_req );
	shopnook_test_assert( 200 === $shopnook_res->get_status() && in_array( $shopnook_product_id, wp_list_pluck( $shopnook_res->get_data(), 'id' ), true ), 'Admins can search products by name' );

	// Retention.
	$wpdb->insert( $shopnook_table, array( 'day' => $shopnook_day, 'event' => 1, 'source' => 1, 'post_id' => 0, 'attachment_id' => 0, 'product_id' => 0, 'hits' => 1, 'revenue' => 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	Shopnook_Settings::update( array( 'keep_days' => 365 ) );
	Shopnook_Stats::prune();
	shopnook_test_assert( ! $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE day = %s', Shopnook_DB::table(), $shopnook_day ) ), 'Old stats are pruned' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
} catch ( Throwable $shopnook_error ) {
	shopnook_test_assert( false, 'Exception: ' . $shopnook_error->getMessage() . ' at ' . $shopnook_error->getFile() . ':' . $shopnook_error->getLine() );
} finally {
	foreach ( $shopnook_orders as $shopnook_id ) {
		$shopnook_o = wc_get_order( $shopnook_id );
		if ( $shopnook_o ) {
			$shopnook_o->delete( true );
		}
	}
	foreach ( $shopnook_cleanup as $shopnook_id ) {
		if ( 'attachment' === get_post_type( $shopnook_id ) ) {
			wp_delete_attachment( $shopnook_id, true );
		} else {
			wp_delete_post( $shopnook_id, true );
		}
	}
	// Fixture counters: rows for the fixture image, product and the test day.
	if ( ! empty( $shopnook_att ) ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE attachment_id = %d OR product_id = %d OR day = %s', Shopnook_DB::table(), $shopnook_att, isset( $shopnook_product_id ) ? $shopnook_product_id : 0, $shopnook_day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	update_option( Shopnook_Settings::OPTION, $shopnook_saved );
	Shopnook_Settings::flush();
}

$shopnook_summary = sprintf( '%d checks, %d failed', $shopnook_test_count, $shopnook_test_failed );
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$shopnook_test_failed ? WP_CLI::error( $shopnook_summary ) : WP_CLI::success( $shopnook_summary );
} else {
	echo esc_html( $shopnook_summary ) . "\n";
}
