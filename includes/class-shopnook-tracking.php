<?php
/**
 * Analytics events and sales attribution.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Counts views, pin opens, clicks and add-to-carts, and credits orders.
 *
 * When a shopper opens a product from a shoppable image, the script stores the
 * product and where it came from in a first-party cookie. Nothing in it
 * identifies the shopper. At checkout, order lines for those products are
 * credited to the image if the click is inside the credit window (last click
 * wins), and counted once the order is paid.
 */
class Shopnook_Tracking {

	const COOKIE      = 'shopnook_ref';
	const ORDER_META  = '_shopnook_attribution';
	const PAID_META   = '_shopnook_counted';
	const MAX_BATCH   = 50;
	const RATE_LIMIT  = 600;

	/**
	 * Event names and their stored codes.
	 */
	const EVENTS = array(
		'view'     => 1,
		'open'     => 2,
		'click'    => 3,
		'cart'     => 4,
		'purchase' => 5,
		'order'    => 6,
	);

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'attribute_classic' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'attribute' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'status_changed' ), 10, 4 );
		add_filter( 'wp_consent_api_registered_' . plugin_basename( SHOPNOOK_FILE ), '__return_true' );
		add_action( 'init', array( __CLASS__, 'cookie_info' ) );
	}

	/**
	 * Whether this visitor's activity should be counted.
	 *
	 * @return bool
	 */
	public static function enabled_for_visitor() {
		if ( ! Shopnook_Settings::get( 'tracking' ) ) {
			return false;
		}
		return ! ( Shopnook_Settings::get( 'exclude_staff' ) && current_user_can( 'edit_posts' ) );
	}

	/**
	 * Describes the cookie to consent plugins that use the WP Consent API.
	 */
	public static function cookie_info() {
		if ( function_exists( 'wp_add_cookie_info' ) && Shopnook_Settings::get( 'tracking' ) ) {
			$days = (int) Shopnook_Settings::get( 'credit_days' );
			wp_add_cookie_info(
				self::COOKIE,
				'Shopnook',
				'statistics',
				/* translators: %d: number of days */
				sprintf( _n( '%d day', '%d days', $days, 'shopnook-shoppable-images' ), $days ),
				__( 'Remembers which shoppable image led to a product, to credit the sale to that image.', 'shopnook-shoppable-images' ),
				false
			);
		}
	}

	/**
	 * Adds to a daily counter.
	 *
	 * @param string      $event   Event name (see EVENTS).
	 * @param array       $source  { s: 1 image | 2 gallery, a: attachment, p: post }.
	 * @param int         $product Product ID.
	 * @param int         $hits    How many.
	 * @param float       $revenue Revenue to add.
	 * @param string|null $day     Day as Y-m-d in the site's timezone; today by default.
	 */
	public static function record( $event, array $source, $product, $hits = 1, $revenue = 0.0, $day = null ) {
		global $wpdb;
		if ( ! isset( self::EVENTS[ $event ] ) || $hits < 1 ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Counter upsert on the plugin's own table.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (day, event, source, post_id, attachment_id, product_id, hits, revenue) VALUES (%s, %d, %d, %d, %d, %d, %d, %f) ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits), revenue = revenue + VALUES(revenue)',
				Shopnook_DB::table(),
				$day ? $day : current_time( 'Y-m-d' ),
				self::EVENTS[ $event ],
				isset( $source['s'] ) ? (int) $source['s'] : 1,
				isset( $source['p'] ) ? (int) $source['p'] : 0,
				isset( $source['a'] ) ? (int) $source['a'] : 0,
				(int) $product,
				(int) $hits,
				(float) $revenue
			)
		);
	}

	/**
	 * Takes away from a daily counter, e.g. when a counted order is cancelled.
	 *
	 * @param string $event   Event name.
	 * @param array  $source  Source.
	 * @param int    $product Product ID.
	 * @param int    $hits    How many.
	 * @param float  $revenue Revenue to remove.
	 * @param string $day     Day the original was counted on.
	 */
	public static function unrecord( $event, array $source, $product, $hits, $revenue, $day ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Counter update on the plugin's own table.
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET hits = IF(hits > %d, hits - %d, 0), revenue = GREATEST(revenue - %f, 0) WHERE day = %s AND event = %d AND source = %d AND post_id = %d AND attachment_id = %d AND product_id = %d',
				Shopnook_DB::table(),
				(int) $hits,
				(int) $hits,
				(float) $revenue,
				$day,
				self::EVENTS[ $event ],
				isset( $source['s'] ) ? (int) $source['s'] : 1,
				isset( $source['p'] ) ? (int) $source['p'] : 0,
				isset( $source['a'] ) ? (int) $source['a'] : 0,
				(int) $product
			)
		);
	}

	/**
	 * Stores a batch of events sent by the script.
	 *
	 * @param array $events List of { e, s, a, p, pr }.
	 * @return int Events stored.
	 */
	public static function ingest( array $events ) {
		if ( ! Shopnook_Settings::get( 'tracking' ) || self::is_bot() || ! self::within_rate_limit( count( $events ) ) ) {
			return 0;
		}

		$counts = array();
		foreach ( array_slice( $events, 0, self::MAX_BATCH ) as $event ) {
			$clean = self::clean_event( $event );
			if ( $clean ) {
				$key            = implode( ':', $clean );
				$counts[ $key ] = isset( $counts[ $key ] ) ? $counts[ $key ] + 1 : 1;
			}
		}
		foreach ( $counts as $key => $hits ) {
			list( $name, $s, $a, $p, $product ) = explode( ':', $key );
			self::record(
				$name,
				array(
					's' => (int) $s,
					'a' => (int) $a,
					'p' => (int) $p,
				),
				(int) $product,
				$hits
			);
		}
		return array_sum( $counts );
	}

	/**
	 * Validates one event from the browser.
	 *
	 * @param mixed $event Raw event.
	 * @return array|null [ name, source, attachment, post, product ].
	 */
	private static function clean_event( $event ) {
		if ( ! is_array( $event ) || empty( $event['e'] ) ) {
			return null;
		}
		$name = sanitize_key( $event['e'] );
		if ( ! in_array( $name, array( 'view', 'open', 'click', 'cart' ), true ) ) {
			return null;
		}
		$s       = isset( $event['s'] ) && 2 === (int) $event['s'] ? 2 : 1;
		$a       = isset( $event['a'] ) ? absint( $event['a'] ) : 0;
		$p       = isset( $event['p'] ) ? absint( $event['p'] ) : 0;
		$product = isset( $event['pr'] ) ? absint( $event['pr'] ) : 0;

		if ( $a && 'attachment' !== get_post_type( $a ) ) {
			return null;
		}
		if ( $p && 'publish' !== get_post_status( $p ) ) {
			$p = 0;
		}
		if ( 'view' === $name ) {
			$product = 0;
		} elseif ( ! $product || 'product' !== get_post_type( $product ) ) {
			return null;
		}
		return array( $name, $s, $a, $p, $product );
	}

	/**
	 * Whether the request comes from a crawler or a headless tool.
	 *
	 * @return bool
	 */
	public static function is_bot() {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return '' === $agent || (bool) preg_match( '/bot|crawl|spider|slurp|headless|lighthouse|pingdom|preview|facebookexternalhit|curl|wget|python|monitor/i', $agent );
	}

	/**
	 * A simple per-IP limit so nobody can flood the counters.
	 *
	 * The IP is only hashed into a transient name that expires in ten minutes.
	 *
	 * @param int $count Events in this request.
	 * @return bool
	 */
	private static function within_rate_limit( $count ) {
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$bucket = (int) floor( time() / 600 );
		$key    = 'shopnook_rl_' . substr( md5( $ip . '|' . $bucket . '|' . wp_salt( 'nonce' ) ), 0, 20 );
		$used   = (int) get_transient( $key );
		if ( $used + $count > self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $used + $count, 600 );
		return true;
	}

	/**
	 * Products in the shopper's cookie that are still inside the credit window.
	 *
	 * @return array Product ID => source { s, a, p }.
	 */
	public static function cookie_credits() {
		$raw = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		return self::parse_cookie( $raw, time() - DAY_IN_SECONDS * (int) Shopnook_Settings::get( 'credit_days' ) );
	}

	/**
	 * Reads the cookie: `product.source.attachment.post.timestamp` entries joined by `_`.
	 *
	 * @param string $raw   Cookie value.
	 * @param int    $since Ignore clicks before this time.
	 * @return array Product ID => source.
	 */
	public static function parse_cookie( $raw, $since ) {
		$out = array();
		foreach ( array_slice( explode( '_', (string) $raw ), 0, 30 ) as $entry ) {
			if ( ! preg_match( '/^(\d+)\.([12])\.(\d+)\.(\d+)\.(\d+)$/', $entry, $m ) ) {
				continue;
			}
			$product = (int) $m[1];
			// The script writes the newest entry first; keep the first one seen (last click wins).
			if ( isset( $out[ $product ] ) || (int) $m[5] < $since ) {
				continue;
			}
			$out[ $product ] = array(
				's' => (int) $m[2],
				'a' => (int) $m[3],
				'p' => (int) $m[4],
			);
		}
		return $out;
	}

	/**
	 * Classic checkout: credits the new order.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Checkout data.
	 * @param WC_Order $order       Order.
	 */
	public static function attribute_classic( $order_id, $posted_data = array(), $order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( $order ) {
			self::attribute( $order );
		}
	}

	/**
	 * Credits order lines to the shoppable images that led to them.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function attribute( $order ) {
		if ( ! $order instanceof WC_Order || ! Shopnook_Settings::get( 'tracking' ) || $order->get_meta( self::ORDER_META ) ) {
			return;
		}
		$credits = self::cookie_credits();
		if ( ! $credits ) {
			return;
		}

		$lines = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = (int) $item->get_product_id();
			if ( isset( $credits[ $product ] ) ) {
				$lines[] = array(
					'item'    => (int) $item_id,
					'product' => $product,
					'qty'     => max( 1, (int) $item->get_quantity() ),
					'total'   => (float) $item->get_total(),
					'source'  => $credits[ $product ],
				);
			}
		}
		if ( $lines ) {
			$order->update_meta_data( self::ORDER_META, $lines );
			$order->save();
			if ( $order->is_paid() ) {
				self::count_order( $order );
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
	public static function status_changed( $order_id, $from, $to, $order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( self::ORDER_META ) ) {
			return;
		}
		if ( in_array( $to, wc_get_is_paid_statuses(), true ) ) {
			self::count_order( $order );
		} elseif ( in_array( $to, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			self::uncount_order( $order );
		}
	}

	/**
	 * Adds a paid order to the stats, once.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function count_order( WC_Order $order ) {
		$lines = $order->get_meta( self::ORDER_META );
		if ( ! is_array( $lines ) || $order->get_meta( self::PAID_META ) ) {
			return;
		}
		$day = current_time( 'Y-m-d' );
		foreach ( $lines as $line ) {
			self::record( 'purchase', $line['source'], $line['product'], $line['qty'], $line['total'], $day );
		}
		self::record( 'order', array( 's' => 0 ), 0, 1, 0.0, $day );
		$order->update_meta_data( self::PAID_META, $day );
		$order->save();
	}

	/**
	 * Removes a counted order from the stats.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function uncount_order( WC_Order $order ) {
		$lines = $order->get_meta( self::ORDER_META );
		$day   = $order->get_meta( self::PAID_META );
		if ( ! is_array( $lines ) || ! $day ) {
			return;
		}
		foreach ( $lines as $line ) {
			self::unrecord( 'purchase', $line['source'], $line['product'], $line['qty'], $line['total'], $day );
		}
		self::unrecord( 'order', array( 's' => 0 ), 0, 1, 0.0, $day );
		$order->delete_meta_data( self::PAID_META );
		$order->save();
	}
}
