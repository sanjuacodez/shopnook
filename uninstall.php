<?php
/**
 * Removes Shopnook data on uninstall, only if "Delete all data" was switched on.
 *
 * @package Shopnook
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$shopnook_settings = get_option( 'shopnook_settings', array() );

wp_clear_scheduled_hook( 'shopnook_prune' );

if ( empty( $shopnook_settings['delete_data'] ) ) {
	return;
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Removing the plugin's own table and meta.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}shopnook_stats" );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_shopnook_image' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key

foreach ( array( '_shopnook_attribution', '_shopnook_counted' ) as $shopnook_key ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $shopnook_key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	$shopnook_orders_meta = $wpdb->prefix . 'wc_orders_meta';
	if ( $shopnook_orders_meta === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $shopnook_orders_meta ) ) ) ) {
		$wpdb->delete( $shopnook_orders_meta, array( 'meta_key' => $shopnook_key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}
	$shopnook_edd_meta = $wpdb->prefix . 'edd_ordermeta';
	if ( $shopnook_edd_meta === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $shopnook_edd_meta ) ) ) ) {
		$wpdb->delete( $shopnook_edd_meta, array( 'meta_key' => $shopnook_key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_shopnook\\_sc\\_%' OR option_name LIKE '\\_transient\\_timeout\\_shopnook\\_sc\\_%'" );
// phpcs:enable

delete_option( 'shopnook_settings' );
delete_option( 'shopnook_db_version' );
delete_option( 'shopnook_surecart_purchases' );
