<?php
/**
 * Dashboard queries and data retention.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads the daily counters for the dashboard and prunes old ones.
 */
class Shopnook_Stats {

	const CRON = 'shopnook_prune';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'prune' ) );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Removes the scheduled clean-up, on deactivation.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Deletes counters older than the retention setting.
	 */
	public static function prune() {
		global $wpdb;
		$days = (int) Shopnook_Settings::get( 'keep_days' );
		if ( ! $days ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The plugin's own table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s', Shopnook_DB::table(), gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) ) - $days * DAY_IN_SECONDS ) ) );
	}

	/**
	 * Totals per event for a date range.
	 *
	 * @param string $from First day, Y-m-d.
	 * @param string $to   Last day, Y-m-d.
	 * @return array { views, opens, clicks, carts, sold, revenue, orders }
	 */
	public static function totals( $from, $to ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The plugin's own table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT event, SUM(hits) AS hits, SUM(revenue) AS revenue FROM %i WHERE day BETWEEN %s AND %s GROUP BY event', Shopnook_DB::table(), $from, $to ), ARRAY_A );

		$out = array_fill_keys( array( 'views', 'opens', 'clicks', 'carts', 'sold', 'revenue', 'orders' ), 0 );
		$map = array_flip( Shopnook_Tracking::EVENTS );
		foreach ( (array) $rows as $row ) {
			$name = isset( $map[ (int) $row['event'] ] ) ? $map[ (int) $row['event'] ] : '';
			switch ( $name ) {
				case 'view':
					$out['views'] = (int) $row['hits'];
					break;
				case 'open':
					$out['opens'] = (int) $row['hits'];
					break;
				case 'click':
					$out['clicks'] = (int) $row['hits'];
					break;
				case 'cart':
					$out['carts'] = (int) $row['hits'];
					break;
				case 'purchase':
					$out['sold']    = (int) $row['hits'];
					$out['revenue'] = (float) $row['revenue'];
					break;
				case 'order':
					$out['orders'] = (int) $row['hits'];
					break;
			}
		}
		return $out;
	}

	/**
	 * Daily values for the trend chart. Missing days are filled with zeros.
	 *
	 * @param string $from First day.
	 * @param string $to   Last day.
	 * @return array Day => { views, opens, clicks, carts, sold, revenue }
	 */
	public static function series( $from, $to ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The plugin's own table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT day, event, SUM(hits) AS hits, SUM(revenue) AS revenue FROM %i WHERE day BETWEEN %s AND %s AND event < 6 GROUP BY day, event', Shopnook_DB::table(), $from, $to ), ARRAY_A );

		$blank = array_fill_keys( array( 'views', 'opens', 'clicks', 'carts', 'sold', 'revenue' ), 0 );
		$out   = array();
		for ( $t = strtotime( $from ); $t <= strtotime( $to ); $t += DAY_IN_SECONDS ) {
			$out[ gmdate( 'Y-m-d', $t ) ] = $blank;
		}
		$keys = array(
			1 => 'views',
			2 => 'opens',
			3 => 'clicks',
			4 => 'carts',
			5 => 'sold',
		);
		foreach ( (array) $rows as $row ) {
			if ( ! isset( $out[ $row['day'] ], $keys[ (int) $row['event'] ] ) ) {
				continue;
			}
			$out[ $row['day'] ][ $keys[ (int) $row['event'] ] ] = (int) $row['hits'];
			if ( 5 === (int) $row['event'] ) {
				$out[ $row['day'] ]['revenue'] = (float) $row['revenue'];
			}
		}
		return $out;
	}

	/**
	 * Best images, products or posts for a date range.
	 *
	 * @param string $group 'attachment', 'product' or 'post'.
	 * @param string $from  First day.
	 * @param string $to    Last day.
	 * @param int    $limit Rows.
	 * @return array
	 */
	public static function top( $group, $from, $to, $limit = 10 ) {
		global $wpdb;
		$columns = array(
			'attachment' => 'attachment_id',
			'product'    => 'product_id',
			'post'       => 'post_id',
		);
		if ( ! isset( $columns[ $group ] ) ) {
			return array();
		}
		$column = $columns[ $group ];
		// Posts are split into images and galleries, so they group by source too.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- The plugin's own table.
		if ( 'post' === $group ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT %i AS id, source, SUM(IF(event = 1, hits, 0)) AS views, SUM(IF(event = 2, hits, 0)) AS opens, SUM(IF(event = 3, hits, 0)) AS clicks, SUM(IF(event = 4, hits, 0)) AS carts, SUM(IF(event = 5, hits, 0)) AS sold, SUM(IF(event = 5, revenue, 0)) AS revenue FROM %i WHERE day BETWEEN %s AND %s AND %i > 0 AND event < 6 GROUP BY %i, source ORDER BY revenue DESC, sold DESC, clicks DESC, opens DESC, views DESC LIMIT %d',
					$column,
					Shopnook_DB::table(),
					$from,
					$to,
					$column,
					$column,
					$limit
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT %i AS id, SUM(IF(event = 1, hits, 0)) AS views, SUM(IF(event = 2, hits, 0)) AS opens, SUM(IF(event = 3, hits, 0)) AS clicks, SUM(IF(event = 4, hits, 0)) AS carts, SUM(IF(event = 5, hits, 0)) AS sold, SUM(IF(event = 5, revenue, 0)) AS revenue FROM %i WHERE day BETWEEN %s AND %s AND %i > 0 AND event < 6 GROUP BY %i ORDER BY revenue DESC, sold DESC, clicks DESC, opens DESC, views DESC LIMIT %d',
					$column,
					Shopnook_DB::table(),
					$from,
					$to,
					$column,
					$column,
					$limit
				),
				ARRAY_A
			);
		}
		// phpcs:enable

		$out = array();
		foreach ( (array) $rows as $row ) {
			$item = array(
				'id'      => (int) $row['id'],
				'views'   => (int) $row['views'],
				'opens'   => (int) $row['opens'],
				'clicks'  => (int) $row['clicks'],
				'carts'   => (int) $row['carts'],
				'sold'    => (int) $row['sold'],
				'revenue' => (float) $row['revenue'],
			);
			if ( 'post' === $group ) {
				$item['source'] = (int) $row['source'];
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Whether anything was ever recorded.
	 *
	 * @return bool
	 */
	public static function has_data() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The plugin's own table.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i LIMIT 1', Shopnook_DB::table() ) );
	}
}
