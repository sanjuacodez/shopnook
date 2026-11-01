<?php
/**
 * SureCart products and checkouts.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * SureCart: cards link to SureCart's checkout, and checkouts are credited
 * when SureCart confirms them in the shopper's browser.
 */
class Shopnook_Store_SureCart extends Shopnook_Store {

	/**
	 * Option holding credited purchases, so a refund can take them back out.
	 */
	const PURCHASES = 'shopnook_surecart_purchases';

	/**
	 * Most purchases remembered for refunds.
	 */
	const KEEP = 500;

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'surecart';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'SureCart';
	}

	/**
	 * {@inheritDoc}
	 */
	public function post_type() {
		return 'sc_product';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return function_exists( 'sc_get_product' ) && class_exists( '\SureCart\Models\Product' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		add_action( 'surecart/checkout_confirmed', array( $this, 'attribute' ), 10, 1 );
		add_action( 'surecart/purchase_revoked', array( $this, 'revoked' ), 10, 1 );
	}

	/**
	 * The SureCart product model for a product post.
	 *
	 * @param int $product_id Product post ID.
	 * @return object|null
	 */
	private function model( $product_id ) {
		try {
			$product = sc_get_product( (int) $product_id );
		} catch ( \Throwable $e ) {
			return null;
		}
		return is_object( $product ) && ! is_wp_error( $product ) ? $product : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function card( $product_id ) {
		if ( ! $this->published( $product_id ) ) {
			return null;
		}
		$product = $this->model( $product_id );
		if ( ! $product ) {
			return null;
		}

		$on_sale = ! empty( $product->is_on_sale );
		$amount  = $this->price_text( $product );
		$price   = esc_html( $amount );
		if ( $on_sale && '' !== $amount && $product->scratch_display_amount ) {
			$price = '<del>' . esc_html( $product->scratch_display_amount ) . '</del> <ins>' . esc_html( $amount ) . '</ins>';
		}

		$in_stock = (bool) $product->in_stock;
		if ( ! $in_stock ) {
			$cart = array( 'mode' => 'none' );
		} elseif ( $product->has_options ) {
			// Variants and price choices are picked on the product page.
			$cart = array(
				'mode' => 'link',
				'url'  => get_permalink( $product_id ),
				'text' => __( 'Select options', 'shopnook-shoppable-images' ),
			);
		} else {
			// Straight to SureCart's checkout when the product's buy link is on, otherwise its product page.
			$cart = array(
				'mode' => 'link',
				'url'  => $product->buyLink()->isEnabled() ? (string) $product->checkout_permalink : get_permalink( $product_id ),
				'text' => __( 'Buy now', 'shopnook-shoppable-images' ),
			);
		}

		$image = $this->image( get_post_thumbnail_id( $product_id ) );
		if ( ! $image ) {
			$preview = (object) $product->preview_image;
			if ( ! empty( $preview->src ) ) {
				$image = array(
					'src'    => (string) $preview->src,
					'srcset' => isset( $preview->srcset ) ? (string) $preview->srcset : '',
					'alt'    => isset( $preview->alt ) ? (string) $preview->alt : '',
				);
			}
		}

		$post = get_post( $product_id );

		return array(
			array(
				'name'      => wp_strip_all_tags( $product->name ? $product->name : get_the_title( $product_id ) ),
				'url'       => get_permalink( $product_id ),
				'price'     => $price,
				'onSale'    => $on_sale,
				'rating'    => 0,
				'reviews'   => 0,
				'excerpt'   => $this->excerpt( $post->post_excerpt ? $post->post_excerpt : (string) $product->description ),
				'image'     => $image,
				'stock'     => $in_stock ? 'instock' : 'outofstock',
				'stockText' => $in_stock ? '' : __( 'Out of stock', 'shopnook-shoppable-images' ),
				'cart'      => $cart,
			),
			$product,
		);
	}

	/**
	 * The product's price as text: a range when it has several prices.
	 *
	 * Falls back to formatting the first price without currency conversion,
	 * which SureCart can't do until the store is connected to its account.
	 *
	 * @param object $product SureCart product.
	 * @return string
	 */
	private function price_text( $product ) {
		$text = '';
		try {
			$text = (string) ( count( (array) $product->active_prices ) > 1 && $product->range_display_amount ? $product->range_display_amount : $product->display_amount );
		} catch ( \Throwable $e ) {
			$text = '';
		}
		if ( '' !== trim( $text ) ) {
			return trim( $text );
		}
		try {
			$price = $product->initial_price;
		} catch ( \Throwable $e ) {
			$price = null;
		}
		if ( ! is_object( $price ) || ! isset( $price->amount ) ) {
			return '';
		}
		$currency = isset( $price->currency ) ? (string) $price->currency : 'usd';
		try {
			$text = (string) \SureCart\Support\Currency::format( (int) $price->amount, $currency, array( 'convert' => false ) );
		} catch ( \Throwable $e ) {
			$text = '';
		}
		if ( '' === trim( $text ) ) {
			$symbol = '';
			try {
				$symbol = html_entity_decode( (string) \SureCart\Support\Currency::getCurrencySymbol( $currency ), ENT_QUOTES, get_bloginfo( 'charset' ) );
			} catch ( \Throwable $e ) {
				$symbol = strtoupper( $currency ) . ' ';
			}
			$text = $symbol . number_format_i18n( $this->major( (int) $price->amount, $currency ), 2 );
		}
		return trim( $text );
	}

	/**
	 * {@inheritDoc}
	 */
	public function search( $term, array $include = array() ) {
		$ids = $include ? $include : $this->find_ids( $term );
		$out = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || $this->post_type() !== $post->post_type || 'trash' === $post->post_status ) {
				continue;
			}
			$product = $this->model( $id );
			$thumb   = get_post_thumbnail_id( $id );
			$preview = $product ? (object) $product->preview_image : null;
			$out[]   = array(
				'id'     => (int) $id,
				'name'   => wp_strip_all_tags( get_the_title( $id ) ),
				'price'  => $product ? $this->price_text( $product ) : '',
				'sku'    => '',
				'image'  => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'thumbnail' ) : ( ! empty( $preview->src ) ? (string) $preview->src : '' ),
				'status' => $post->post_status,
				'stock'  => $product && ! $product->in_stock ? 'outofstock' : 'instock',
			);
		}
		return $out;
	}

	/**
	 * The store currency code (SureCart caches the account).
	 *
	 * @return string
	 */
	private function currency() {
		static $currency = null;
		if ( null === $currency ) {
			try {
				$currency = strtolower( (string) \SureCart::account()->currency );
			} catch ( \Throwable $e ) {
				$currency = '';
			}
		}
		return '' !== $currency ? $currency : 'usd';
	}

	/**
	 * {@inheritDoc}
	 */
	public function money( $amount ) {
		if ( class_exists( '\SureCart\Support\Currency' ) ) {
			$currency = $this->currency();
			$minor    = \SureCart\Support\Currency::isZeroDecimal( $currency ) ? (float) $amount : (float) $amount * 100;
			return html_entity_decode( wp_strip_all_tags( \SureCart\Support\Currency::format( (int) round( $minor ), $currency ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		}
		return number_format_i18n( (float) $amount, 2 );
	}

	/**
	 * {@inheritDoc}
	 */
	public function currency_symbol() {
		if ( class_exists( '\SureCart\Support\Currency' ) ) {
			return html_entity_decode( (string) \SureCart\Support\Currency::getCurrencySymbol( $this->currency() ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		}
		return '';
	}

	/**
	 * The product post for a SureCart product ID.
	 *
	 * @param mixed $product Product ID string or expanded product object.
	 * @return int
	 */
	private function post_for( $product ) {
		$sc_id = is_object( $product ) ? ( isset( $product->id ) ? $product->id : '' ) : $product;
		if ( ! is_string( $sc_id ) || '' === $sc_id ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'      => $this->post_type(),
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'sc_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $sc_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * A line item's amount in the store currency's main unit.
	 *
	 * @param int    $minor    Amount in minor units (cents).
	 * @param string $currency Currency code.
	 * @return float
	 */
	private function major( $minor, $currency ) {
		if ( class_exists( '\SureCart\Support\Currency' ) && \SureCart\Support\Currency::isZeroDecimal( (string) $currency ) ) {
			return (float) $minor;
		}
		return (float) $minor / 100;
	}

	/**
	 * Credits a confirmed checkout to the shoppable images that led to it.
	 *
	 * SureCart confirms a checkout from the shopper's browser, so the cookie
	 * is available here.
	 *
	 * @param object $checkout SureCart checkout.
	 */
	public function attribute( $checkout ) {
		if ( ! is_object( $checkout ) || empty( $checkout->id ) || ! Shopnook_Tracking::cookie_credits() ) {
			return;
		}
		if ( isset( $checkout->status ) && ! in_array( $checkout->status, array( 'paid', 'processing' ), true ) ) {
			return;
		}
		$done = get_transient( 'shopnook_sc_' . md5( (string) $checkout->id ) );
		if ( $done ) {
			return;
		}

		$line_items = isset( $checkout->line_items->data ) ? $checkout->line_items->data : array();
		if ( ! $line_items || ! is_object( $line_items[0]->price ?? null ) ) {
			try {
				$full       = \SureCart\Models\Checkout::with( array( 'line_items', 'line_item.price' ) )->find( $checkout->id );
				$line_items = ! is_wp_error( $full ) && isset( $full->line_items->data ) ? $full->line_items->data : array();
			} catch ( \Throwable $e ) {
				$line_items = array();
			}
		}

		$currency = isset( $checkout->currency ) ? (string) $checkout->currency : $this->currency();
		$items    = array();
		foreach ( $line_items as $line ) {
			$price   = isset( $line->price ) ? $line->price : null;
			$product = is_object( $price ) && isset( $price->product ) ? $this->post_for( $price->product ) : 0;
			if ( $product ) {
				$items[] = array(
					'item'    => isset( $line->id ) ? (string) $line->id : '',
					'product' => $product,
					'qty'     => isset( $line->quantity ) ? (int) $line->quantity : 1,
					'total'   => $this->major( isset( $line->total_amount ) ? (int) $line->total_amount : 0, $currency ),
					'sc'      => is_object( $price->product ) ? (string) $price->product->id : (string) $price->product,
				);
			}
		}

		$lines = Shopnook_Tracking::credit_lines( $items );
		set_transient( 'shopnook_sc_' . md5( (string) $checkout->id ), 1, MONTH_IN_SECONDS );
		if ( ! $lines ) {
			return;
		}
		$day = Shopnook_Tracking::count_lines( $lines );
		$this->remember( $checkout, $lines, $day );
	}

	/**
	 * Keeps the credited lines by purchase, so a revoked purchase can be taken back out.
	 *
	 * @param object $checkout Checkout.
	 * @param array  $lines    Credited lines.
	 * @param string $day      Day they were counted on.
	 */
	private function remember( $checkout, array $lines, $day ) {
		$purchases = isset( $checkout->purchases->data ) ? $checkout->purchases->data : array();
		$saved     = get_option( self::PURCHASES, array() );
		$saved     = is_array( $saved ) ? $saved : array();
		foreach ( $lines as $line ) {
			foreach ( $purchases as $purchase ) {
				$product = isset( $purchase->product ) ? $purchase->product : '';
				$product = is_object( $product ) ? ( isset( $product->id ) ? $product->id : '' ) : $product;
				if ( ! empty( $purchase->id ) && $product === $line['sc'] ) {
					$saved[ (string) $purchase->id ] = array(
						'line' => $line,
						'day'  => $day,
					);
				}
			}
		}
		update_option( self::PURCHASES, array_slice( $saved, -self::KEEP, null, true ), false );
	}

	/**
	 * Takes a revoked (refunded) purchase back out of the stats.
	 *
	 * @param object $purchase SureCart purchase.
	 */
	public function revoked( $purchase ) {
		$saved = get_option( self::PURCHASES, array() );
		$id    = is_object( $purchase ) && isset( $purchase->id ) ? (string) $purchase->id : '';
		if ( '' === $id || ! is_array( $saved ) || ! isset( $saved[ $id ] ) ) {
			return;
		}
		Shopnook_Tracking::uncount_lines( array( $saved[ $id ]['line'] ), $saved[ $id ]['day'], false );
		unset( $saved[ $id ] );
		update_option( self::PURCHASES, $saved, false );
	}
}
