<?php
/**
 * Demo content for WordPress Playground (Live Preview and local testing).
 *
 * Creates a small home-goods shop: products with generated photos, a
 * lookbook post with shoppable images, three shoppable galleries and two
 * months of stats. Not shipped with the plugin; bin/build-blueprint puts the
 * wp-load.php require in front of this file when it embeds it.
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * Saves a GD image as a Media Library attachment.
 *
 * @param GdImage|resource $im    Image.
 * @param string           $name  File name without extension.
 * @param string           $title Title / alt text.
 * @return int Attachment ID.
 */
function shopnook_demo_attach( $im, $name, $title ) {
	ob_start();
	imagejpeg( $im, null, 86 );
	$bytes = ob_get_clean();
	imagedestroy( $im );
	$upload = wp_upload_bits( $name . '.jpg', null, $bytes );
	$id     = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $title,
			'post_status'    => 'inherit',
		),
		$upload['file']
	);
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $title );
	return $id;
}

/**
 * A colour from hex.
 *
 * @param GdImage|resource $im  Image.
 * @param string           $hex Colour.
 * @return int
 */
function shopnook_demo_color( $im, $hex ) {
	$hex = ltrim( $hex, '#' );
	return imagecolorallocate( $im, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

/**
 * Vertical gradient fill.
 *
 * @param GdImage|resource $im   Image.
 * @param string           $from Top colour.
 * @param string           $to   Bottom colour.
 */
function shopnook_demo_gradient( $im, $from, $to ) {
	$w = imagesx( $im );
	$h = imagesy( $im );
	$a = sscanf( ltrim( $from, '#' ), '%02x%02x%02x' );
	$b = sscanf( ltrim( $to, '#' ), '%02x%02x%02x' );
	for ( $y = 0; $y < $h; $y++ ) {
		$t = $y / max( 1, $h - 1 );
		$c = imagecolorallocate( $im, (int) ( $a[0] + ( $b[0] - $a[0] ) * $t ), (int) ( $a[1] + ( $b[1] - $a[1] ) * $t ), (int) ( $a[2] + ( $b[2] - $a[2] ) * $t ) );
		imageline( $im, 0, $y, $w, $y, $c );
	}
}

/**
 * Draws one object; shapes are in a 0–100 coordinate space.
 *
 * @param GdImage|resource $im    Image.
 * @param string           $shape Shape.
 * @param float            $x     Centre x (%).
 * @param float            $y     Centre y (%).
 * @param float            $s     Size (% of width).
 * @param string           $color Colour.
 */
function shopnook_demo_object( $im, $shape, $x, $y, $s, $color ) {
	$w  = imagesx( $im );
	$h  = imagesy( $im );
	$cx = (int) ( $x / 100 * $w );
	$cy = (int) ( $y / 100 * $h );
	$r  = (int) ( $s / 100 * $w / 2 );
	$c  = shopnook_demo_color( $im, $color );
	$sh = imagecolorallocatealpha( $im, 0, 0, 0, 100 );
	imagefilledellipse( $im, $cx, $cy + (int) ( $r * 1.05 ), (int) ( $r * 2.2 ), (int) ( $r * 0.35 ), $sh );
	switch ( $shape ) {
		case 'vase':
			imagefilledellipse( $im, $cx, $cy + (int) ( $r * 0.3 ), (int) ( $r * 1.3 ), (int) ( $r * 1.4 ), $c );
			imagefilledrectangle( $im, $cx - (int) ( $r * 0.25 ), $cy - (int) ( $r * 0.9 ), $cx + (int) ( $r * 0.25 ), $cy, $c );
			break;
		case 'lamp':
			imagefilledpolygon( $im, array( $cx - (int) ( $r * 0.8 ), $cy - (int) ( $r * 0.2 ), $cx + (int) ( $r * 0.8 ), $cy - (int) ( $r * 0.2 ), $cx + (int) ( $r * 0.5 ), $cy - $r, $cx - (int) ( $r * 0.5 ), $cy - $r ), 4, $c );
			imagefilledrectangle( $im, $cx - 4, $cy - (int) ( $r * 0.2 ), $cx + 4, $cy + (int) ( $r * 0.8 ), shopnook_demo_color( $im, '#3b3026' ) );
			imagefilledellipse( $im, $cx, $cy + (int) ( $r * 0.85 ), (int) ( $r * 0.9 ), (int) ( $r * 0.2 ), shopnook_demo_color( $im, '#3b3026' ) );
			break;
		case 'table':
			imagefilledrectangle( $im, $cx - $r, $cy - (int) ( $r * 0.15 ), $cx + $r, $cy + (int) ( $r * 0.05 ), $c );
			imagefilledrectangle( $im, $cx - (int) ( $r * 0.85 ), $cy, $cx - (int) ( $r * 0.72 ), $cy + $r, $c );
			imagefilledrectangle( $im, $cx + (int) ( $r * 0.72 ), $cy, $cx + (int) ( $r * 0.85 ), $cy + $r, $c );
			break;
		case 'cushion':
			imagefilledellipse( $im, $cx, $cy, (int) ( $r * 2 ), (int) ( $r * 1.5 ), $c );
			break;
		case 'rug':
			imagefilledellipse( $im, $cx, $cy, (int) ( $r * 2 ), (int) ( $r * 0.55 ), $c );
			break;
		case 'throw':
			imagefilledrectangle( $im, $cx - $r, $cy - (int) ( $r * 0.5 ), $cx + $r, $cy + (int) ( $r * 0.5 ), $c );
			for ( $i = -3; $i <= 3; $i++ ) {
				imageline( $im, $cx + (int) ( $i * $r / 3.5 ), $cy - (int) ( $r * 0.5 ), $cx + (int) ( $i * $r / 3.5 ), $cy + (int) ( $r * 0.5 ), shopnook_demo_color( $im, '#ffffff' ) );
			}
			break;
		default:
			imagefilledrectangle( $im, $cx - $r, $cy - $r, $cx + $r, $cy + $r, $c );
	}
}

/**
 * Builds the demo shop.
 */
function shopnook_blueprint_demo() {
	global $wp_rewrite, $wpdb;
	$wp_rewrite->set_permalink_structure( '/%postname%/' );
	flush_rewrite_rules( false );
	update_option( 'woocommerce_currency', 'USD' );
	update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );

	// name, price, sale price, shape, colour, rating.
	$catalogue = array(
		array( 'Stoneware Vase', '48', '', 'vase', '#b5643c', 4.8 ),
		array( 'Linen Table Lamp', '129', '99', 'lamp', '#e9dcc4', 4.6 ),
		array( 'Oak Side Table', '240', '', 'table', '#9a6b3f', 4.9 ),
		array( 'Bouclé Cushion', '39', '', 'cushion', '#efe7da', 4.4 ),
		array( 'Jute Round Rug', '180', '149', 'rug', '#c9a66b', 4.2 ),
		array( 'Waffle Cotton Throw', '72', '', 'throw', '#5f7a6a', 4.7 ),
	);

	$products = array();
	foreach ( $catalogue as $i => $item ) {
		$im = imagecreatetruecolor( 800, 800 );
		shopnook_demo_gradient( $im, '#f4efe8', '#e2d8ca' );
		shopnook_demo_object( $im, $item[3], 50, 52, 48, $item[4] );
		$image = shopnook_demo_attach( $im, 'product-' . ( $i + 1 ), $item[0] );

		$product = new WC_Product_Simple();
		$product->set_name( $item[0] );
		$product->set_status( 'publish' );
		$product->set_regular_price( $item[1] );
		if ( $item[2] ) {
			$product->set_sale_price( $item[2] );
		}
		$product->set_short_description( 'Made in small batches with natural materials. Free returns within 30 days.' );
		$product->set_image_id( $image );
		$product->set_average_rating( $item[5] );
		$product->set_rating_counts( array( 5 => 12 + $i * 3 ) );
		$product->set_review_count( 12 + $i * 3 );
		$products[ $item[3] ] = $product->save();
	}

	// One variable product, to show "Select options".
	$variable = new WC_Product_Variable();
	$variable->set_name( 'Ceramic Mug Set' );
	$variable->set_status( 'publish' );
	$attribute = new WC_Product_Attribute();
	$attribute->set_name( 'Colour' );
	$attribute->set_options( array( 'Sand', 'Sage' ) );
	$attribute->set_variation( true );
	$attribute->set_visible( true );
	$variable->set_attributes( array( $attribute ) );
	$im = imagecreatetruecolor( 800, 800 );
	shopnook_demo_gradient( $im, '#f4efe8', '#e2d8ca' );
	shopnook_demo_object( $im, 'vase', 50, 55, 30, '#8aa38f' );
	$variable->set_image_id( shopnook_demo_attach( $im, 'product-mugs', 'Ceramic Mug Set' ) );
	$variable_id = $variable->save();
	foreach ( array( 'Sand', 'Sage' ) as $colour ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $variable_id );
		$variation->set_attributes( array( 'colour' => $colour ) );
		$variation->set_regular_price( '34' );
		$variation->set_status( 'publish' );
		$variation->save();
	}
	$products['mugs'] = $variable_id;

	// Room photos: where each object sits, so pins land on them.
	$rooms = array(
		array(
			'title'   => 'Living room with oak side table',
			'from'    => '#d9cfc2',
			'to'      => '#a8998a',
			'objects' => array( array( 'rug', 50, 86, 60 ), array( 'table', 26, 62, 18 ), array( 'lamp', 26, 40, 12 ), array( 'cushion', 66, 58, 14 ), array( 'throw', 78, 66, 16 ) ),
		),
		array(
			'title'   => 'Reading corner',
			'from'    => '#cfd6cc',
			'to'      => '#8e9b8b',
			'objects' => array( array( 'lamp', 70, 36, 14 ), array( 'cushion', 38, 60, 18 ), array( 'vase', 82, 70, 8 ) ),
		),
		array(
			'title'   => 'Shelf styling',
			'from'    => '#e6ddd0',
			'to'      => '#c2b29d',
			'objects' => array( array( 'vase', 30, 50, 10 ), array( 'vase', 52, 52, 7 ), array( 'mugs', 74, 56, 10 ) ),
		),
		array(
			'title'   => 'Bedroom morning light',
			'from'    => '#efe6da',
			'to'      => '#c4b3a0',
			'objects' => array( array( 'throw', 50, 62, 40 ), array( 'cushion', 36, 46, 14 ), array( 'lamp', 86, 44, 12 ) ),
		),
		array(
			'title'   => 'Entryway',
			'from'    => '#d6d2cc',
			'to'      => '#9c958b',
			'objects' => array( array( 'table', 50, 58, 30 ), array( 'vase', 42, 38, 8 ), array( 'rug', 50, 90, 50 ) ),
		),
		array(
			'title'   => 'Sunday table',
			'from'    => '#ece2d3',
			'to'      => '#b9a284',
			'objects' => array( array( 'table', 50, 66, 56 ), array( 'mugs', 40, 50, 10 ), array( 'vase', 62, 46, 9 ) ),
		),
	);

	$room_ids = array();
	foreach ( $rooms as $n => $room ) {
		$tall = 3 === $n || 4 === $n;
		$im   = imagecreatetruecolor( $tall ? 1080 : 1600, $tall ? 1440 : 1067 );
		shopnook_demo_gradient( $im, $room['from'], $room['to'] );
		imagefilledrectangle( $im, 0, (int) ( imagesy( $im ) * 0.72 ), imagesx( $im ), imagesy( $im ), imagecolorallocatealpha( $im, 60, 45, 30, 90 ) );
		$spots = array();
		foreach ( $room['objects'] as $obj ) {
			$shape = 'mugs' === $obj[0] ? 'vase' : $obj[0];
			$color = 'mugs' === $obj[0] ? '#8aa38f' : $catalogue[ array_search( $obj[0], array_column( $catalogue, 3 ), true ) ][4];
			shopnook_demo_object( $im, $shape, $obj[1], $obj[2], $obj[3], $color );
			$spots[] = array(
				'id'      => substr( md5( $room['title'] . $obj[0] . $obj[1] ), 0, 8 ),
				'x'       => $obj[1],
				'y'       => $obj[2],
				'product' => $products[ $obj[0] ],
				'label'   => '',
			);
		}
		$id = shopnook_demo_attach( $im, 'room-' . ( $n + 1 ), $room['title'] );
		Shopnook_Images::save( $id, array( 'hotspots' => $spots ) );
		$room_ids[] = $id;
	}

	$image_block = function ( $id, $settings, $caption = '' ) {
		$url  = wp_get_attachment_image_url( $id, 'large' );
		$alt  = esc_attr( get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		$json = wp_json_encode(
			array(
				'id'       => $id,
				'sizeSlug' => 'large',
				'shopnook' => $settings,
			)
		);
		$cap  = $caption ? '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>' : '';
		return '<!-- wp:image ' . $json . ' --><figure class="wp-block-image size-large"><img src="' . esc_url( $url ) . '" alt="' . $alt . '" class="wp-image-' . $id . '"/>' . $cap . '</figure><!-- /wp:image -->';
	};
	$gallery_block = function ( $ids, $settings ) use ( $image_block ) {
		$inner = '';
		foreach ( $ids as $id ) {
			$inner .= $image_block( $id, new stdClass() );
		}
		$json = wp_json_encode(
			array(
				'linkTo'   => 'none',
				'shopnook' => $settings,
			)
		);
		return '<!-- wp:gallery ' . $json . ' --><figure class="wp-block-gallery has-nested-images columns-default is-cropped">' . $inner . '</figure><!-- /wp:gallery -->';
	};
	$p = function ( $text ) {
		return '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';
	};
	$h = function ( $text ) {
		return '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $text ) . '</h2><!-- /wp:heading -->';
	};

	$lookbook = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Autumn Lookbook: Warm Neutrals at Home',
			'post_content' => $p( 'Soft textures, warm wood and a few handmade pieces. Tap the dots on any photo to shop the look.' )
				. $image_block( $room_ids[0], array( 'enabled' => true ), 'Tooltip cards (the default).' )
				. $p( 'Layer a jute rug under oak and linen for a room that feels finished but relaxed.' )
				. $image_block( $room_ids[1], array( 'enabled' => true, 'overrides' => array( 'display' => 'list', 'pin_style' => 'number' ) ), 'Shop this look list with numbered pins.' )
				. $image_block( $room_ids[2], array( 'enabled' => true, 'overrides' => array( 'display' => 'panel', 'pin_style' => 'bag', 'pin_bg' => '#ffffff', 'pin_color' => '#111111' ) ), 'Panel below the image, bag pins.' )
				. $image_block( $room_ids[3], array( 'enabled' => true, 'overrides' => array( 'display' => 'drawer', 'pin_style' => 'pulse' ) ), 'Side drawer, pulsing pins.' )
				. $image_block( $room_ids[4], array( 'enabled' => false ), 'Shoppable switched off: a plain image.' ),
		)
	);

	$galleries = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Shop the Feed',
			'post_content' => $h( 'Instagram grid' ) . $gallery_block( $room_ids, array( 'enabled' => true, 'layout' => 'instagram' ) )
				. $h( 'Stories' ) . $gallery_block( $room_ids, array( 'enabled' => true, 'layout' => 'stories' ) )
				. $h( 'Masonry' ) . $gallery_block( $room_ids, array( 'enabled' => true, 'layout' => 'masonry' ) ),
		)
	);

	// About two months of activity, busier towards today.
	$table = Shopnook_DB::table();
	foreach ( range( 0, 59 ) as $age ) {
		$day   = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) ) - $age * DAY_IN_SECONDS );
		$scale = 1 + ( 60 - $age ) / 40;
		foreach ( array_slice( $room_ids, 0, 4 ) as $n => $att ) {
			$views = (int) ( wp_rand( 30, 60 ) * $scale / ( $n + 1 ) );
			$wpdb->replace( $table, array( 'day' => $day, 'event' => 1, 'source' => 1, 'post_id' => $lookbook, 'attachment_id' => $att, 'product_id' => 0, 'hits' => $views, 'revenue' => 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( $rooms[ $n ]['objects'] as $obj ) {
				$product = $products[ $obj[0] ];
				$opens   = (int) round( $views * wp_rand( 6, 14 ) / 100 );
				$clicks  = (int) round( $opens * wp_rand( 25, 45 ) / 100 );
				$carts   = (int) round( $clicks * wp_rand( 20, 40 ) / 100 );
				$sold    = (int) round( $carts * wp_rand( 30, 60 ) / 100 );
				$price   = (float) wc_get_product( $product )->get_price();
				foreach ( array( 2 => $opens, 3 => $clicks, 4 => $carts, 5 => $sold ) as $event => $hits ) {
					if ( $hits ) {
						$wpdb->replace( $table, array( 'day' => $day, 'event' => $event, 'source' => 1, 'post_id' => $lookbook, 'attachment_id' => $att, 'product_id' => $product, 'hits' => $hits, 'revenue' => 5 === $event ? $hits * $price : 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					}
				}
				if ( $sold ) {
					$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (day, event, source, post_id, attachment_id, product_id, hits, revenue) VALUES (%s, 6, 0, 0, 0, 0, %d, 0) ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits)', $table, $day, max( 1, (int) ( $sold * 0.8 ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				}
			}
		}
		$wpdb->replace( $table, array( 'day' => $day, 'event' => 1, 'source' => 2, 'post_id' => $galleries, 'attachment_id' => $room_ids[5], 'product_id' => 0, 'hits' => (int) ( wp_rand( 20, 40 ) * $scale ), 'revenue' => 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	update_option( 'shopnook_demo', array( 'lookbook' => $lookbook, 'galleries' => $galleries, 'rooms' => $room_ids, 'products' => $products ) );
}

shopnook_blueprint_demo();
