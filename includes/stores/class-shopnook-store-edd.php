<?php
/**
 * Easy Digital Downloads products and orders.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Easy Digital Downloads: add to cart in place, orders credited at checkout.
 */
class Shopnook_Store_EDD extends Shopnook_Store {

	/**
	 * Order statuses that mean the order is paid.
	 */
	const PAID = array( 'complete', 'completed', 'publish', 'edd_subscription' );

	/**
	 * Order statuses that take a counted order back out.
	 */
	const REVERSED = array( 'refunded', 'revoked', 'failed', 'cancelled' );

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'edd';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'Easy Digital Downloads';
	}

	/**
	 * {@inheritDoc}
	 */
	public function post_type() {
		return 'download';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return function_exists( 'edd_get_download' ) && function_exists( 'edd_get_order' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		add_action( 'edd_built_order', array( $this, 'attribute' ), 10, 1 );
		add_action( 'edd_transition_order_status', array( $this, 'status_changed' ), 10, 3 );
		add_action( 'edd_view_order_details_sidebar_after', array( $this, 'order_note' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function card( $product_id ) {
		if ( ! $this->published( $product_id ) ) {
			return null;
		}
		$download = edd_get_download( $product_id );
		if ( ! $download ) {
			return null;
		}

		$variable = $download->has_variable_prices();
		if ( $variable ) {
			$price = edd_price_range( $download->ID );
		} else {
			$amount = (float) edd_get_download_price( $download->ID );
			$price  = $amount > 0 ? esc_html( edd_currency_filter( edd_format_amount( $amount ) ) ) : esc_html__( 'Free', 'shopnook-shoppable-images' );
		}

		$text = (string) edd_get_option( 'add_to_cart_text', '' );
		$text = '' !== $text ? $text : __( 'Add to cart', 'shopnook-shoppable-images' );
		if ( $variable ) {
			// Price options are chosen on the download's own page.
			$cart = array(
				'mode' => 'link',
				'url'  => get_permalink( $download->ID ),
				'text' => __( 'Select options', 'shopnook-shoppable-images' ),
			);
		} else {
			$timestamp = time();
			$cart      = array(
				'mode'   => 'ajax',
				'store'  => 'edd',
				'text'   => $text,
				'fields' => array(
					'action'      => 'edd_add_to_cart',
					'download_id' => $download->ID,
					'price_ids[]' => $download->ID,
					'nonce'       => wp_create_nonce( 'edd-add-to-cart-' . $download->ID ),
					'timestamp'   => $timestamp,
					'token'       => class_exists( '\EDD\Utils\Tokenizer' ) ? \EDD\Utils\Tokenizer::tokenize( $timestamp ) : '',
				),
			);
		}

		$post = get_post( $download->ID );

		return array(
			array(
				'name'      => wp_strip_all_tags( get_the_title( $download->ID ) ),
				'url'       => get_permalink( $download->ID ),
				'price'     => wp_kses_post( $price ),
				'onSale'    => false,
				'rating'    => 0,
				'reviews'   => 0,
				'excerpt'   => $this->excerpt( $post->post_excerpt ? $post->post_excerpt : $post->post_content ),
				'image'     => $this->image( get_post_thumbnail_id( $download->ID ) ),
				'stock'     => 'instock',
				'stockText' => '',
				'cart'      => $cart,
			),
			$download,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function search( $term, array $include = array() ) {
		$ids = $include ? $include : $this->find_ids( $term, 'edd_sku' );
		$out = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || $this->post_type() !== $post->post_type || 'trash' === $post->post_status ) {
				continue;
			}
			$download = edd_get_download( $id );
			$price    = $download && $download->has_variable_prices() ? $this->plain( edd_price_range( $id ) ) : $this->plain( edd_currency_filter( edd_format_amount( edd_get_download_price( $id ) ) ) );
			$thumb    = get_post_thumbnail_id( $id );
			$out[]    = array(
				'id'     => (int) $id,
				'name'   => wp_strip_all_tags( get_the_title( $id ) ),
				'price'  => $price,
				'sku'    => (string) get_post_meta( $id, 'edd_sku', true ),
				'image'  => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'thumbnail' ) : '',
				'status' => $post->post_status,
				'stock'  => 'instock',
			);
		}
		return $out;
	}

	/**
	 * {@inheritDoc}
	 */
	public function money( $amount ) {
		return html_entity_decode( wp_strip_all_tags( edd_currency_filter( edd_format_amount( $amount ) ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function currency_symbol() {
		return html_entity_decode( (string) edd_currency_symbol( edd_get_currency() ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function cart_config() {
		return array(
			'url'      => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'cartUrl'  => esc_url_raw( edd_get_checkout_uri() ),
			'redirect' => (bool) edd_straight_to_checkout(),
		);
	}

	/**
	 * Credits a new order's items while the shopper's cookie is available.
	 *
	 * @param int $order_id Order ID.
	 */
	public function attribute( $order_id ) {
		$order = edd_get_order( $order_id );
		if ( ! $order || edd_get_order_meta( $order_id, Shopnook_Tracking::ORDER_META, true ) ) {
			return;
		}
		$items = array();
		foreach ( (array) $order->get_items() as $item ) {
			$items[] = array(
				'item'    => (int) $item->id,
				'product' => (int) $item->product_id,
				'qty'     => (int) $item->quantity,
				'total'   => (float) $item->total,
			);
		}
		$lines = Shopnook_Tracking::credit_lines( $items );
		if ( $lines ) {
			edd_update_order_meta( $order_id, Shopnook_Tracking::ORDER_META, $lines );
			if ( in_array( $order->status, self::PAID, true ) ) {
				$this->count_order( $order_id );
			}
		}
	}

	/**
	 * Counts a credited order once it is paid, and takes it back if it is
	 * refunded, revoked, failed or cancelled afterwards.
	 *
	 * @param string $from     Old status.
	 * @param string $to       New status.
	 * @param int    $order_id Order ID.
	 */
	public function status_changed( $from, $to, $order_id ) {
		if ( ! edd_get_order_meta( $order_id, Shopnook_Tracking::ORDER_META, true ) ) {
			return;
		}
		if ( in_array( $to, self::PAID, true ) ) {
			$this->count_order( $order_id );
		} elseif ( in_array( $to, self::REVERSED, true ) ) {
			$this->uncount_order( $order_id );
		}
	}

	/**
	 * Adds a paid order to the stats, once.
	 *
	 * @param int $order_id Order ID.
	 */
	public function count_order( $order_id ) {
		if ( edd_get_order_meta( $order_id, Shopnook_Tracking::PAID_META, true ) ) {
			return;
		}
		$day = Shopnook_Tracking::count_lines( edd_get_order_meta( $order_id, Shopnook_Tracking::ORDER_META, true ) );
		if ( $day ) {
			edd_update_order_meta( $order_id, Shopnook_Tracking::PAID_META, $day );
		}
	}

	/**
	 * Removes a counted order from the stats.
	 *
	 * @param int $order_id Order ID.
	 */
	public function uncount_order( $order_id ) {
		$lines = edd_get_order_meta( $order_id, Shopnook_Tracking::ORDER_META, true );
		if ( Shopnook_Tracking::uncount_lines( $lines, edd_get_order_meta( $order_id, Shopnook_Tracking::PAID_META, true ) ) ) {
			edd_delete_order_meta( $order_id, Shopnook_Tracking::PAID_META );
		}
	}

	/**
	 * Notes on the order screen which items Shopnook credited.
	 *
	 * @param int $order_id Order ID.
	 */
	public function order_note( $order_id ) {
		$lines = edd_get_order_meta( $order_id, Shopnook_Tracking::ORDER_META, true );
		if ( $lines ) {
			echo '<div class="postbox shopnook-order-note"><div class="inside">';
			Shopnook_Tracking::print_order_note( $lines, 'p', '' );
			echo '</div></div>';
		}
	}
}
