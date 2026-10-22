<?php
/**
 * Product data for cards and for the editor's product picker.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns WooCommerce products into the small arrays the scripts use.
 */
class Shopnook_Products {

	/**
	 * Card data built during this request, by product ID. False = hidden.
	 *
	 * @var array
	 */
	private static $cards = array();

	/**
	 * Whether a product can be shown to shoppers.
	 *
	 * @param WC_Product|false|null $product Product.
	 * @return bool
	 */
	public static function visible( $product ) {
		return $product instanceof WC_Product
			&& 'publish' === $product->get_status()
			&& ! post_password_required( $product->get_id() );
	}

	/**
	 * Card data for one product, or null when it can't be shown.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null
	 */
	public static function card( $product_id ) {
		$product_id = (int) $product_id;
		if ( isset( self::$cards[ $product_id ] ) ) {
			return self::$cards[ $product_id ] ? self::$cards[ $product_id ] : null;
		}

		$product = wc_get_product( $product_id );
		if ( ! self::visible( $product ) ) {
			self::$cards[ $product_id ] = false;
			return null;
		}

		$image_id = $product->get_image_id();
		$image    = null;
		if ( $image_id ) {
			$src   = wp_get_attachment_image_src( $image_id, 'woocommerce_thumbnail' );
			$image = $src ? array(
				'src'    => $src[0],
				'srcset' => (string) wp_get_attachment_image_srcset( $image_id, 'woocommerce_thumbnail' ),
				'alt'    => trim( wp_strip_all_tags( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) ),
			) : null;
		}
		if ( ! $image ) {
			$image = array(
				'src'    => wc_placeholder_img_src( 'woocommerce_thumbnail' ),
				'srcset' => '',
				'alt'    => '',
			);
		}

		$stock = $product->get_stock_status();
		if ( ! $product->is_in_stock() ) {
			$cart = array( 'mode' => 'none' );
		} elseif ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->supports( 'ajax_add_to_cart' ) ) {
			$cart = array(
				'mode' => 'ajax',
				'text' => $product->add_to_cart_text(),
			);
		} else {
			// Variable, grouped and external products are bought on their own page or site.
			$cart = array(
				'mode'     => 'link',
				'url'      => $product->add_to_cart_url(),
				'text'     => $product->add_to_cart_text(),
				'external' => $product->is_type( 'external' ),
			);
		}

		$excerpt = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
		$rating  = wc_review_ratings_enabled() ? (float) $product->get_average_rating() : 0;

		$card = array(
			'id'        => $product_id,
			'name'      => wp_strip_all_tags( $product->get_name() ),
			'url'       => $product->get_permalink(),
			'price'     => wp_kses_post( $product->get_price_html() ),
			'onSale'    => $product->is_on_sale(),
			'rating'    => round( $rating, 2 ),
			'reviews'   => $rating ? (int) $product->get_review_count() : 0,
			'excerpt'   => wp_trim_words( wp_strip_all_tags( strip_shortcodes( $excerpt ) ), 18 ),
			'image'     => $image,
			'stock'     => $stock,
			'stockText' => 'outofstock' === $stock ? __( 'Out of stock', 'shopnook' ) : ( 'onbackorder' === $stock ? __( 'On backorder', 'shopnook' ) : '' ),
			'cart'      => $cart,
		);

		/**
		 * Filters the product data a Shopnook card shows.
		 *
		 * @param array      $card    Card data.
		 * @param WC_Product $product Product.
		 */
		$card = apply_filters( 'shopnook_product_card', $card, $product );

		self::$cards[ $product_id ] = $card;
		return $card;
	}

	/**
	 * Card data for every product shown on this page so far.
	 *
	 * @return array Product ID => card.
	 */
	public static function used() {
		return array_filter( self::$cards );
	}

	/**
	 * The price as short plain text, without the screen-reader sentences
	 * WooCommerce adds to sale prices: "$180.00 $149.00".
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function plain_price( $product ) {
		$html = preg_replace( '#<span class="screen-reader-text">.*?</span>#s', '', (string) $product->get_price_html() );
		$html = str_replace( '</del>', '</del> ', $html );
		return trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
	}

	/**
	 * Products for the editor's picker.
	 *
	 * @param string $term    Search text; matches names and SKUs.
	 * @param int[]  $include Exact IDs to load instead of searching.
	 * @return array
	 */
	public static function search( $term, array $include = array() ) {
		if ( $include ) {
			$ids = array_slice( array_map( 'absint', $include ), 0, 100 );
		} elseif ( '' !== $term ) {
			$store = WC_Data_Store::load( 'product' );
			$ids   = array_slice( (array) $store->search_products( wc_clean( $term ), '', false, false, 20 ), 0, 20 );
		} else {
			$ids = wc_get_products(
				array(
					'status'  => 'publish',
					'limit'   => 20,
					'orderby' => 'date',
					'order'   => 'DESC',
					'return'  => 'ids',
				)
			);
		}

		$out = array();
		foreach ( array_filter( $ids ) as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || $product->is_type( 'variation' ) || 'trash' === $product->get_status() ) {
				continue;
			}
			$thumb = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );
			$out[] = array(
				'id'     => $product->get_id(),
				'name'   => wp_strip_all_tags( $product->get_name() ),
				'price'  => self::plain_price( $product ),
				'sku'    => (string) $product->get_sku(),
				'image'  => $thumb,
				'status' => $product->get_status(),
				'stock'  => $product->get_stock_status(),
			);
		}
		return $out;
	}
}
