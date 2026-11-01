<?php
/**
 * Product data for cards and for the editor's product picker.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns store products into the small arrays the scripts use.
 */
class Shopnook_Products {

	/**
	 * Card data built during this request, by product ID. False = hidden.
	 *
	 * @var array
	 */
	private static $cards = array();

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

		$store  = Shopnook_Stores::for_product( $product_id );
		$result = $store ? $store->card( $product_id ) : null;
		if ( ! $result ) {
			self::$cards[ $product_id ] = false;
			return null;
		}

		list( $card, $product ) = $result;
		$card = array_merge(
			array(
				'id'    => $product_id,
				'store' => $store->id(),
			),
			$card
		);
		if ( empty( $card['image'] ) ) {
			$card['image'] = array(
				'src'    => '',
				'srcset' => '',
				'alt'    => '',
			);
		}

		/**
		 * Filters the product data a Shopnook card shows.
		 *
		 * @param array  $card    Card data. `store` is "woocommerce", "edd" or "surecart".
		 * @param object $product The store's product: WC_Product, EDD_Download or a SureCart product.
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
	 * Products for the editor's picker, from every active store.
	 *
	 * @param string $term    Search text; matches names and SKUs.
	 * @param int[]  $include Exact IDs to load instead of searching.
	 * @return array
	 */
	public static function search( $term, array $include = array() ) {
		$include = array_slice( array_filter( array_map( 'absint', $include ) ), 0, 100 );
		$stores  = Shopnook_Stores::active();
		$label   = count( $stores ) > 1;
		$out     = array();
		foreach ( $stores as $store ) {
			$ids = array();
			if ( $include ) {
				foreach ( $include as $id ) {
					if ( get_post_type( $id ) === $store->post_type() ) {
						$ids[] = $id;
					}
				}
				if ( ! $ids ) {
					continue;
				}
			}
			foreach ( $store->search( $term, $ids ) as $item ) {
				if ( $label ) {
					$item['store'] = $store->label();
				}
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * A published product to preview cards with.
	 *
	 * @return int
	 */
	public static function sample_id() {
		foreach ( Shopnook_Stores::active() as $store ) {
			$id = $store->sample_id();
			if ( $id ) {
				return $id;
			}
		}
		return 0;
	}
}
