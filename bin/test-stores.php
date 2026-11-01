<?php
/**
 * Checks for the Easy Digital Downloads and SureCart integrations. Run on a
 * site where the store plugins to test are active; stores that are not active
 * are skipped. Fixtures are removed afterwards.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

global $shopnook_test_failed, $shopnook_test_count;
$shopnook_test_failed = 0;
$shopnook_test_count  = 0;

if ( ! function_exists( 'shopnook_test_assert' ) ) {
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
		echo esc_html( ( $condition ? 'PASS ' : 'FAIL ' ) . $label ) . "\n";
	}
}

global $wpdb;
$shopnook_cleanup = array();
$shopnook_saved   = get_option( Shopnook_Settings::OPTION );
$shopnook_active  = Shopnook_Stores::active();
$shopnook_hits    = function ( $event, $product ) use ( $wpdb ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the plugin's own table.
	return $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(hits),0) AS hits, COALESCE(SUM(revenue),0) AS revenue FROM %i WHERE event = %d AND product_id = %d', Shopnook_DB::table(), Shopnook_Tracking::EVENTS[ $event ], $product ), ARRAY_A );
};

echo esc_html( 'Active stores: ' . implode( ', ', array_keys( $shopnook_active ) ) ) . "\n";

try {
	Shopnook_Settings::update( array( 'tracking' => true ) );

	$shopnook_im = imagecreatetruecolor( 400, 300 );
	ob_start();
	imagejpeg( $shopnook_im );
	$shopnook_upload = wp_upload_bits( 'shopnook-store-fixture.jpg', null, ob_get_clean() );
	$shopnook_att    = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Shopnook store fixture',
			'post_status'    => 'inherit',
		),
		$shopnook_upload['file']
	);
	$shopnook_cleanup[] = $shopnook_att;
	$shopnook_render    = function ( array $products ) use ( $shopnook_att ) {
		$spots = array();
		foreach ( $products as $i => $product ) {
			$spots[] = array(
				'id'      => 'p' . $i,
				'x'       => 20 + 20 * $i,
				'y'       => 50,
				'product' => $product,
			);
		}
		$attrs = array(
			'id'       => $shopnook_att,
			'shopnook' => array(
				'enabled'  => true,
				'custom'   => true,
				'hotspots' => $spots,
			),
		);
		return do_blocks( '<!-- wp:image ' . wp_json_encode( $attrs ) . ' --><figure class="wp-block-image"><img src="' . esc_url( wp_get_attachment_url( $shopnook_att ) ) . '" alt="" class="wp-image-' . $shopnook_att . '"/></figure><!-- /wp:image -->' );
	};

	shopnook_test_assert( (bool) $shopnook_active, 'At least one store is active, so Shopnook boots' );
	shopnook_test_assert( has_action( 'render_block_core/image' ) || has_filter( 'render_block_core/image' ), 'Shopnook boots without depending on WooCommerce' );

	/* ---------------------------------------------------------------- */
	/* Easy Digital Downloads                                           */
	/* ---------------------------------------------------------------- */
	if ( isset( $shopnook_active['edd'] ) ) {
		$shopnook_dl = wp_insert_post(
			array(
				'post_type'    => 'download',
				'post_status'  => 'publish',
				'post_title'   => 'Shopnook fixture ebook',
				'post_excerpt' => 'A short guide.',
			)
		);
		update_post_meta( $shopnook_dl, 'edd_price', '19.00' );
		update_post_meta( $shopnook_dl, 'edd_sku', 'SN-EBOOK' );
		$shopnook_cleanup[] = $shopnook_dl;

		$shopnook_var = wp_insert_post(
			array(
				'post_type'   => 'download',
				'post_status' => 'publish',
				'post_title'  => 'Shopnook fixture course',
			)
		);
		update_post_meta( $shopnook_var, '_variable_pricing', 1 );
		update_post_meta(
			$shopnook_var,
			'edd_variable_prices',
			array(
				1 => array(
					'index'  => 1,
					'name'   => 'Basic',
					'amount' => '29.00',
				),
				2 => array(
					'index'  => 2,
					'name'   => 'Pro',
					'amount' => '99.00',
				),
			)
		);
		$shopnook_cleanup[] = $shopnook_var;

		$shopnook_card = Shopnook_Products::card( $shopnook_dl );
		shopnook_test_assert( $shopnook_card && 'edd' === $shopnook_card['store'] && 'Shopnook fixture ebook' === $shopnook_card['name'], 'EDD: a download gets a card' );
		shopnook_test_assert( $shopnook_card && false !== strpos( $shopnook_card['price'], '19.00' ), 'EDD: the card shows the price' );
		shopnook_test_assert( $shopnook_card && 'ajax' === $shopnook_card['cart']['mode'] && 'edd_add_to_cart' === $shopnook_card['cart']['fields']['action'] && $shopnook_card['cart']['fields']['token'], 'EDD: a single-price download adds to cart in place, with EDD\'s token' );
		$shopnook_vcard = Shopnook_Products::card( $shopnook_var );
		shopnook_test_assert( $shopnook_vcard && 'link' === $shopnook_vcard['cart']['mode'] && false !== strpos( $shopnook_vcard['price'], '29.00' ) && false !== strpos( $shopnook_vcard['price'], '99.00' ), 'EDD: variable prices show a range and link to the download page' );

		$shopnook_html = $shopnook_render( array( $shopnook_dl, $shopnook_var ) );
		shopnook_test_assert( 2 === substr_count( $shopnook_html, 'class="shopnook-pin ' ), 'EDD: block pins render for downloads' );
		shopnook_test_assert( false !== strpos( $shopnook_html, 'data-store="edd"' ) && false !== strpos( $shopnook_html, 'data-fields=' ), 'EDD: the cart button carries the store and its fields' );

		$shopnook_found = Shopnook_Products::search( 'SN-EBOOK' );
		shopnook_test_assert( in_array( $shopnook_dl, wp_list_pluck( $shopnook_found, 'id' ), true ), 'EDD: product search matches the SKU' );
		$shopnook_found = Shopnook_Products::search( 'fixture course' );
		shopnook_test_assert( in_array( $shopnook_var, wp_list_pluck( $shopnook_found, 'id' ), true ), 'EDD: product search matches the name' );
		$shopnook_found = Shopnook_Products::search( '', array( $shopnook_dl ) );
		shopnook_test_assert( 1 === count( $shopnook_found ) && $shopnook_dl === $shopnook_found[0]['id'], 'EDD: picker loads exact IDs' );

		$shopnook_saved_pins = Shopnook_Images::save( $shopnook_att, array( 'hotspots' => array( array( 'x' => 10, 'y' => 10, 'product' => $shopnook_dl ) ) ) );
		shopnook_test_assert( 1 === count( $shopnook_saved_pins['hotspots'] ), 'EDD: Media Library pins accept downloads' );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Safari/605.1.15';
		shopnook_test_assert( 1 === Shopnook_Tracking::ingest( array( array( 'e' => 'click', 's' => 1, 'a' => $shopnook_att, 'pr' => $shopnook_dl ) ) ), 'EDD: clicks on downloads are counted' );

		// A checkout: credited when built, counted when complete, removed when refunded.
		$_COOKIE[ Shopnook_Tracking::COOKIE ] = $shopnook_dl . '.1.' . $shopnook_att . '.0.' . time();
		$shopnook_order                       = edd_add_order(
			array(
				'status'   => 'pending',
				'currency' => 'USD',
				'email'    => 'fixture@example.com',
				'total'    => 38,
				'subtotal' => 38,
			)
		);
		edd_add_order_item(
			array(
				'order_id'     => $shopnook_order,
				'product_id'   => $shopnook_dl,
				'product_name' => 'Shopnook fixture ebook',
				'quantity'     => 2,
				'amount'       => 19,
				'subtotal'     => 38,
				'total'        => 38,
			)
		);
		do_action( 'edd_built_order', $shopnook_order, array() );
		$shopnook_lines = edd_get_order_meta( $shopnook_order, Shopnook_Tracking::ORDER_META, true );
		shopnook_test_assert( is_array( $shopnook_lines ) && 1 === count( $shopnook_lines ) && 2 === $shopnook_lines[0]['qty'], 'EDD: order items are credited from the cookie' );
		shopnook_test_assert( 0 === (int) $shopnook_hits( 'purchase', $shopnook_dl )['hits'], 'EDD: a pending order is not counted yet' );
		edd_update_order_status( $shopnook_order, 'complete' );
		$shopnook_row = $shopnook_hits( 'purchase', $shopnook_dl );
		shopnook_test_assert( 2 === (int) $shopnook_row['hits'] && 38.0 === (float) $shopnook_row['revenue'], 'EDD: a completed order is counted with its revenue' );
		edd_update_order_status( $shopnook_order, 'complete' );
		shopnook_test_assert( 2 === (int) $shopnook_hits( 'purchase', $shopnook_dl )['hits'], 'EDD: an order is only counted once' );
		edd_update_order_status( $shopnook_order, 'refunded' );
		shopnook_test_assert( 0 === (int) $shopnook_hits( 'purchase', $shopnook_dl )['hits'], 'EDD: a refunded order is taken back out' );
		edd_destroy_order( $shopnook_order );
		unset( $_COOKIE[ Shopnook_Tracking::COOKIE ] );

		shopnook_test_assert( '' !== Shopnook_Stores::all()['edd']->money( 12.5 ) && false !== strpos( Shopnook_Stores::all()['edd']->money( 12.5 ), '12.50' ), 'EDD: money is formatted in the store currency' );
	}

	/* ---------------------------------------------------------------- */
	/* SureCart                                                          */
	/* ---------------------------------------------------------------- */
	if ( isset( $shopnook_active['surecart'] ) ) {
		$shopnook_sc_id   = 'fixture-0000-4000-8000-000000000001';
		$shopnook_sc_json = array(
			'id'               => $shopnook_sc_id,
			'object'           => 'product',
			'name'             => 'Shopnook fixture tee',
			'slug'             => 'shopnook-fixture-tee',
			'description'      => 'Soft cotton tee.',
			'stock_enabled'    => false,
			'archived'         => false,
			'status'           => 'published',
			'prices'           => array(
				'object' => 'list',
				'data'   => array(
					array(
						'id'                 => 'price-fixture-1',
						'object'             => 'price',
						'amount'             => 2500,
						'currency'           => 'usd',
						'archived'           => false,
						'position'           => 0,
						'product'            => $shopnook_sc_id,
						'scratch_amount'     => 3000,
						'is_on_sale'         => true,
					),
				),
			),
			'variants'         => array(
				'object' => 'list',
				'data'   => array(),
			),
			'variant_options'  => array(
				'object' => 'list',
				'data'   => array(),
			),
			'metrics'          => array(
				'currency'         => 'usd',
				'min_price_amount' => 2500,
				'max_price_amount' => 2500,
				'prices_count'     => 1,
			),
		);
		$shopnook_sc = wp_insert_post(
			array(
				'post_type'   => 'sc_product',
				'post_status' => 'publish',
				'post_title'  => 'Shopnook fixture tee',
				'post_name'   => 'shopnook-fixture-tee',
			)
		);
		update_post_meta( $shopnook_sc, 'sc_id', $shopnook_sc_id );
		update_post_meta( $shopnook_sc, 'product', $shopnook_sc_json );
		$shopnook_cleanup[] = $shopnook_sc;

		$shopnook_card = Shopnook_Products::card( $shopnook_sc );
		shopnook_test_assert( $shopnook_card && 'surecart' === $shopnook_card['store'] && 'Shopnook fixture tee' === $shopnook_card['name'], 'SureCart: a product gets a card' );
		shopnook_test_assert( $shopnook_card && false !== strpos( $shopnook_card['price'], '25' ), 'SureCart: the card shows the price' );
		shopnook_test_assert( $shopnook_card && 'link' === $shopnook_card['cart']['mode'] && '' !== $shopnook_card['cart']['url'], 'SureCart: the card links to SureCart\'s checkout (' . ( $shopnook_card ? $shopnook_card['cart']['url'] : '' ) . ')' );

		$shopnook_html = $shopnook_render( array( $shopnook_sc ) );
		shopnook_test_assert( 1 === substr_count( $shopnook_html, 'class="shopnook-pin ' ) && false !== strpos( $shopnook_html, 'data-shopnook-click' ), 'SureCart: block pins render, and the buy link counts the click' );
		shopnook_test_assert( $shopnook_card && false !== strpos( $shopnook_html, '<span class="shopnook-card__price">' . $shopnook_card['price'] . '</span>' ), 'A "$" price survives rendering (not read as a regex backreference)' );
		$shopnook_found = Shopnook_Products::search( 'fixture tee' );
		shopnook_test_assert( in_array( $shopnook_sc, wp_list_pluck( $shopnook_found, 'id' ), true ), 'SureCart: product search matches the name' );

		// A confirmed checkout, then a revoked purchase.
		$_COOKIE[ Shopnook_Tracking::COOKIE ] = $shopnook_sc . '.2.' . $shopnook_att . '.0.' . time();
		$shopnook_checkout                    = json_decode(
			wp_json_encode(
				array(
					'id'         => 'checkout-fixture-' . wp_rand(),
					'status'     => 'paid',
					'currency'   => 'usd',
					'line_items' => array(
						'data' => array(
							array(
								'id'           => 'li-1',
								'quantity'     => 3,
								'total_amount' => 7500,
								'price'        => array(
									'id'      => 'price-fixture-1',
									'product' => $shopnook_sc_id,
								),
							),
						),
					),
					'purchases'  => array(
						'data' => array(
							array(
								'id'      => 'purchase-fixture-1',
								'product' => $shopnook_sc_id,
							),
						),
					),
				)
			)
		);
		do_action( 'surecart/checkout_confirmed', $shopnook_checkout, null );
		$shopnook_row = $shopnook_hits( 'purchase', $shopnook_sc );
		shopnook_test_assert( 3 === (int) $shopnook_row['hits'] && 75.0 === (float) $shopnook_row['revenue'], 'SureCart: a confirmed checkout is credited with its revenue' );
		do_action( 'surecart/checkout_confirmed', $shopnook_checkout, null );
		shopnook_test_assert( 3 === (int) $shopnook_hits( 'purchase', $shopnook_sc )['hits'], 'SureCart: confirming twice counts once' );
		do_action( 'surecart/purchase_revoked', (object) array( 'id' => 'purchase-fixture-1' ) );
		shopnook_test_assert( 0 === (int) $shopnook_hits( 'purchase', $shopnook_sc )['hits'], 'SureCart: a revoked purchase is taken back out' );
		unset( $_COOKIE[ Shopnook_Tracking::COOKIE ] );
		delete_transient( 'shopnook_sc_' . md5( $shopnook_checkout->id ) );
	}

	shopnook_test_assert( '' !== Shopnook_Stores::currency_symbol(), 'The dashboard has a currency symbol (' . Shopnook_Stores::currency_symbol() . ')' );
	if ( ! class_exists( 'Shopnook_Admin' ) ) {
		require_once SHOPNOOK_PATH . 'includes/admin/class-shopnook-admin.php';
	}
	shopnook_test_assert( in_array( Shopnook_Admin::stats_cap(), array( 'view_woocommerce_reports', 'view_shop_reports', 'manage_options' ), true ) && current_user_can( Shopnook_Admin::stats_cap() ), 'Admins can open the dashboard (' . Shopnook_Admin::stats_cap() . ')' );
} catch ( Throwable $shopnook_error ) {
	shopnook_test_assert( false, 'Exception: ' . $shopnook_error->getMessage() . ' at ' . $shopnook_error->getFile() . ':' . $shopnook_error->getLine() );
} finally {
	foreach ( $shopnook_cleanup as $shopnook_id ) {
		if ( 'attachment' === get_post_type( $shopnook_id ) ) {
			wp_delete_attachment( $shopnook_id, true );
		} else {
			wp_delete_post( $shopnook_id, true );
		}
	}
	if ( ! empty( $shopnook_att ) ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE attachment_id = %d OR (event = 6 AND day = %s)', Shopnook_DB::table(), $shopnook_att, current_time( 'Y-m-d' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	update_option( Shopnook_Settings::OPTION, $shopnook_saved );
	Shopnook_Settings::flush();
}

echo esc_html( sprintf( '%d checks, %d failed', $shopnook_test_count, $shopnook_test_failed ) ) . "\n";
