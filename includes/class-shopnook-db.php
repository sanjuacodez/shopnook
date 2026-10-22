<?php
/**
 * The stats table.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the `{prefix}shopnook_stats` table.
 *
 * Events are never stored one by one: each row is a daily counter for one
 * event on one product, image and post. That keeps the table small and the
 * dashboard fast, and nothing in it identifies a visitor.
 */
class Shopnook_DB {

	const VERSION_OPTION = 'shopnook_db_version';

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'shopnook_stats';
	}

	/**
	 * Creates or updates the table.
	 */
	public static function install() {
		global $wpdb;

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
day date NOT NULL,
event tinyint(3) unsigned NOT NULL,
source tinyint(3) unsigned NOT NULL DEFAULT 1,
post_id bigint(20) unsigned NOT NULL DEFAULT 0,
attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
product_id bigint(20) unsigned NOT NULL DEFAULT 0,
hits int(10) unsigned NOT NULL DEFAULT 0,
revenue decimal(19,4) NOT NULL DEFAULT 0,
PRIMARY KEY  (day,event,source,post_id,attachment_id,product_id),
KEY event_day (event,day)
) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::VERSION_OPTION, SHOPNOOK_DB_VERSION, false );
	}

	/**
	 * Runs the installer when the table is missing or out of date, for
	 * updates that skip the activation hook.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) !== SHOPNOOK_DB_VERSION ) {
			self::install();
		}
	}
}
