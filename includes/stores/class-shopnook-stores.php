<?php
/**
 * The store plugins Shopnook can tag products from.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finds the active stores and the store a product belongs to.
 */
class Shopnook_Stores {

	/**
	 * Every supported store, active or not.
	 *
	 * @var Shopnook_Store[]|null
	 */
	private static $all = null;

	/**
	 * Every supported store, in order of preference.
	 *
	 * @return Shopnook_Store[] Keyed by store ID.
	 */
	public static function all() {
		if ( null === self::$all ) {
			$stores = array( new Shopnook_Store_WooCommerce(), new Shopnook_Store_EDD(), new Shopnook_Store_SureCart() );

			/**
			 * Filters the stores Shopnook can tag products from.
			 *
			 * @param Shopnook_Store[] $stores Store integrations.
			 */
			$stores    = apply_filters( 'shopnook_stores', $stores );
			self::$all = array();
			foreach ( $stores as $store ) {
				if ( $store instanceof Shopnook_Store ) {
					self::$all[ $store->id() ] = $store;
				}
			}
		}
		return self::$all;
	}

	/**
	 * The stores whose plugin is active.
	 *
	 * @return Shopnook_Store[] Keyed by store ID.
	 */
	public static function active() {
		return array_filter(
			self::all(),
			function ( $store ) {
				return $store->is_active();
			}
		);
	}

	/**
	 * The main active store, used for the dashboard's currency.
	 *
	 * @return Shopnook_Store|null
	 */
	public static function primary() {
		$active = self::active();
		return $active ? reset( $active ) : null;
	}

	/**
	 * The active store a product belongs to.
	 *
	 * @param int $product_id Product ID.
	 * @return Shopnook_Store|null
	 */
	public static function for_product( $product_id ) {
		$type = get_post_type( (int) $product_id );
		if ( ! $type ) {
			return null;
		}
		foreach ( self::active() as $store ) {
			if ( $store->post_type() === $type ) {
				return $store;
			}
		}
		return null;
	}

	/**
	 * Whether an ID is a product of an active store.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_product( $product_id ) {
		return (bool) self::for_product( $product_id );
	}

	/**
	 * Whether an ID is a product of any supported store, active or not.
	 *
	 * Used when saving pins, so they survive a store plugin being switched off for a while.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_product_type( $product_id ) {
		$type = get_post_type( (int) $product_id );
		foreach ( self::all() as $store ) {
			if ( $store->post_type() === $type ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * An amount in the main store's currency, as plain text.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function money( $amount ) {
		$store = self::primary();
		return $store ? $store->money( $amount ) : number_format_i18n( (float) $amount, 2 );
	}

	/**
	 * The main store's currency symbol.
	 *
	 * @return string
	 */
	public static function currency_symbol() {
		$store = self::primary();
		return $store ? $store->currency_symbol() : '';
	}
}
