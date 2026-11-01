<?php
/**
 * WooCommerce products and orders.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce: add to cart in place, orders credited at checkout.
 */
class Shopnook_Store_WooCommerce extends Shopnook_Store {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'woocommerce';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'WooCommerce';
	}

	/**
	 * {@inheritDoc}
	 */
	public function post_type() {
		return 'product';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'attribute_classic' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'attribute' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'status_changed' ), 10, 4 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'order_note' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function card( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() || post_password_required( $product->get_id() ) ) {
			return null;
		}

		$image = $this->image( $product->get_image_id(), 'woocommerce_thumbnail' );
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
				'mode'   => 'ajax',
				'text'   => $product->add_to_cart_text(),
				'fields' => array(
					'product_id' => $product->get_id(),
					'quantity'   => 1,
				),
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

		$rating = wc_review_ratings_enabled() ? (float) $product->get_average_rating() : 0;

		return array(
			array(
				'name'      => wp_strip_all_tags( $product->get_name() ),
				'url'       => $product->get_permalink(),
				'price'     => wp_kses_post( $product->get_price_html() ),
				'onSale'    => $product->is_on_sale(),
				'rating'    => round( $rating, 2 ),
				'reviews'   => $rating ? (int) $product->get_review_count() : 0,
				'excerpt'   => $this->excerpt( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ),
				'image'     => $image,
				'stock'     => $stock,
				'stockText' => 'outofstock' === $stock ? __( 'Out of stock', 'shopnook-shoppable-images' ) : ( 'onbackorder' === $stock ? __( 'On backorder', 'shopnook-shoppable-images' ) : '' ),
				'cart'      => $cart,
			),
			$product,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function search( $term, array $include = array() ) {
		if ( $include ) {
			$ids = $include;
		} elseif ( '' !== $term ) {
			$data_store = WC_Data_Store::load( 'product' );
			$ids        = array_slice( (array) $data_store->search_products( wc_clean( $term ), '', false, false, 20 ), 0, 20 );
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
			$out[] = array(
				'id'     => $product->get_id(),
				'name'   => wp_strip_all_tags( $product->get_name() ),
				'price'  => $this->plain( $product->get_price_html() ),
				'sku'    => (string) $product->get_sku(),
				'image'  => $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ),
				'status' => $product->get_status(),
				'stock'  => $product->get_stock_status(),
			);
		}
		return $out;
	}

	/**
	 * {@inheritDoc}
	 */
	public function money( $amount ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function currency_symbol() {
		return html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function cart_config() {
		return array(
			'url'      => esc_url_raw( WC_AJAX::get_endpoint( 'add_to_cart' ) ),
			'cartUrl'  => esc_url_raw( wc_get_cart_url() ),
			'redirect' => 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function sample_id() {
		$ids = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 1,
				'return' => 'ids',
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Classic checkout: credits the new order.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Checkout data.
	 * @param WC_Order $order       Order.
	 */
	public function attribute_classic( $order_id, $posted_data = array(), $order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( $order ) {
			$this->attribute( $order );
		}
	}

	/**
	 * Credits order lines to the shoppable images that led to them.
	 *
	 * @param WC_Order $order Order.
	 */
	public function attribute( $order ) {
		if ( ! $order instanceof WC_Order || $order->get_meta( Shopnook_Tracking::ORDER_META ) ) {
			return;
		}
		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$items[] = array(
					'item'    => (int) $item_id,
					'product' => (int) $item->get_product_id(),
					'qty'     => (int) $item->get_quantity(),
					'total'   => (float) $item->get_total(),
				);
			}
		}
		$lines = Shopnook_Tracking::credit_lines( $items );
		if ( $lines ) {
			$order->update_meta_data( Shopnook_Tracking::ORDER_META, $lines );
			$order->save();
			if ( $order->is_paid() ) {
				$this->count_order( $order );
			}
		}
	}

	/**
	 * Counts a credited order once it is paid, and takes it back if it is
	 * cancelled, refunded or failed afterwards.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Old status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 */
	public function status_changed( $order_id, $from, $to, $order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( Shopnook_Tracking::ORDER_META ) ) {
			return;
		}
		if ( in_array( $to, wc_get_is_paid_statuses(), true ) ) {
			$this->count_order( $order );
		} elseif ( in_array( $to, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			$this->uncount_order( $order );
		}
	}

	/**
	 * Adds a paid order to the stats, once.
	 *
	 * @param WC_Order $order Order.
	 */
	public function count_order( WC_Order $order ) {
		if ( $order->get_meta( Shopnook_Tracking::PAID_META ) ) {
			return;
		}
		$day = Shopnook_Tracking::count_lines( $order->get_meta( Shopnook_Tracking::ORDER_META ) );
		if ( $day ) {
			$order->update_meta_data( Shopnook_Tracking::PAID_META, $day );
			$order->save();
		}
	}

	/**
	 * Removes a counted order from the stats.
	 *
	 * @param WC_Order $order Order.
	 */
	public function uncount_order( WC_Order $order ) {
		if ( Shopnook_Tracking::uncount_lines( $order->get_meta( Shopnook_Tracking::ORDER_META ), $order->get_meta( Shopnook_Tracking::PAID_META ) ) ) {
			$order->delete_meta_data( Shopnook_Tracking::PAID_META );
			$order->save();
		}
	}

	/**
	 * Notes on the order screen which items Shopnook credited.
	 *
	 * @param WC_Order $order Order.
	 */
	public function order_note( $order ) {
		if ( $order instanceof WC_Order ) {
			Shopnook_Tracking::print_order_note( $order->get_meta( Shopnook_Tracking::ORDER_META ), 'p', 'form-field form-field-wide shopnook-order-note' );
		}
	}
}
