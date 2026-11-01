<?php
/**
 * What every store integration provides.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * One e-commerce plugin Shopnook can tag products from.
 *
 * Products are WordPress posts in every supported store, so a pin keeps the
 * post ID and the store is found from the post type.
 */
abstract class Shopnook_Store {

	/**
	 * Short key, e.g. "woocommerce".
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Name shown to admins, e.g. "WooCommerce".
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * The post type that holds this store's products.
	 *
	 * @return string
	 */
	abstract public function post_type();

	/**
	 * Whether the store plugin is installed and active.
	 *
	 * @return bool
	 */
	abstract public function is_active();

	/**
	 * Card data for one product, or null when it can't be shown.
	 *
	 * Keys: id, name, url, price (HTML), onSale, rating, reviews, excerpt,
	 * image { src, srcset, alt }, stock, stockText, cart { mode, ... }.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null [ card, product object passed to the card filter ].
	 */
	abstract public function card( $product_id );

	/**
	 * Products for the editor's picker.
	 *
	 * @param string $term    Search text.
	 * @param int[]  $include Exact IDs to load instead of searching.
	 * @return array List of { id, name, price, sku, image, status, stock }.
	 */
	abstract public function search( $term, array $include = array() );

	/**
	 * An amount in the store currency, as plain text.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	abstract public function money( $amount );

	/**
	 * The store currency symbol.
	 *
	 * @return string
	 */
	abstract public function currency_symbol();

	/**
	 * Settings the front-end script needs to add to this store's cart.
	 *
	 * @return array
	 */
	public function cart_config() {
		return array();
	}

	/**
	 * Hooks for crediting this store's orders.
	 */
	public function init() {}

	/**
	 * The newest published product, for previews.
	 *
	 * @return int
	 */
	public function sample_id() {
		$ids = get_posts(
			array(
				'post_type'      => $this->post_type(),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'has_password'   => false,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Whether a product post can be shown to shoppers.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	protected function published( $product_id ) {
		return get_post_type( $product_id ) === $this->post_type()
			&& 'publish' === get_post_status( $product_id )
			&& ! post_password_required( $product_id );
	}

	/**
	 * The featured image of a product post, sized for cards.
	 *
	 * @param int    $image_id Attachment ID.
	 * @param string $size     Image size.
	 * @return array|null { src, srcset, alt }.
	 */
	protected function image( $image_id, $size = 'medium' ) {
		$src = $image_id ? wp_get_attachment_image_src( $image_id, $size ) : false;
		if ( ! $src ) {
			return null;
		}
		return array(
			'src'    => $src[0],
			'srcset' => (string) wp_get_attachment_image_srcset( $image_id, $size ),
			'alt'    => trim( wp_strip_all_tags( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) ),
		);
	}

	/**
	 * Product posts matching a search, newest first.
	 *
	 * @param string $term     Search text; empty lists the newest.
	 * @param string $sku_meta Meta key holding the SKU, if the store has one.
	 * @return int[]
	 */
	protected function find_ids( $term, $sku_meta = '' ) {
		$args = array(
			'post_type'      => $this->post_type(),
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( '' === $term ) {
			$args['post_status'] = 'publish';
			return array_map( 'intval', get_posts( $args ) );
		}
		$ids = get_posts( $args + array( 's' => $term ) );
		if ( $sku_meta ) {
			$ids = array_merge(
				$ids,
				get_posts(
					$args + array(
						'meta_key'   => $sku_meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value' => $term, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					)
				)
			);
		}
		return array_slice( array_values( array_unique( array_map( 'intval', $ids ) ) ), 0, 20 );
	}

	/**
	 * Plain text from price HTML: "$180.00 $149.00".
	 *
	 * @param string $html Price HTML.
	 * @return string
	 */
	protected function plain( $html ) {
		$html = preg_replace( '#<span class="screen-reader-text">.*?</span>#s', '', (string) $html );
		$html = str_replace( '</del>', '</del> ', $html );
		return trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
	}

	/**
	 * Short plain-text excerpt for a card.
	 *
	 * @param string $text Description.
	 * @return string
	 */
	protected function excerpt( $text ) {
		return wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $text ) ), 18 );
	}
}
