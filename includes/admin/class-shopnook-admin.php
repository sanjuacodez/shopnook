<?php
/**
 * Admin screens: dashboard, settings and the order note.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Everything under the "Shopnook" admin menu.
 */
class Shopnook_Admin {

	const STATS_CAP = 'view_woocommerce_reports';

	/**
	 * Admin page hook suffixes.
	 *
	 * @var string[]
	 */
	private static $pages = array();

	/**
	 * Hooks into WordPress.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SHOPNOOK_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'update_option_' . Shopnook_Settings::OPTION, array( 'Shopnook_Settings', 'flush' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'order_note' ) );
	}

	/**
	 * Adds the menu and its pages.
	 */
	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l1 13H5L6 7Z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Menu icon data URI.

		self::$pages[] = add_menu_page( __( 'Shopnook', 'shopnook-shoppable-images' ), __( 'Shopnook', 'shopnook-shoppable-images' ), self::STATS_CAP, 'shopnook', array( __CLASS__, 'page_dashboard' ), $icon, 58 );
		self::$pages[] = add_submenu_page( 'shopnook', __( 'Shopnook Dashboard', 'shopnook-shoppable-images' ), __( 'Dashboard', 'shopnook-shoppable-images' ), self::STATS_CAP, 'shopnook', array( __CLASS__, 'page_dashboard' ) );
		self::$pages[] = add_submenu_page( 'shopnook', __( 'Shopnook Settings', 'shopnook-shoppable-images' ), __( 'Settings', 'shopnook-shoppable-images' ), 'manage_options', 'shopnook-settings', array( __CLASS__, 'page_settings' ) );
	}

	/**
	 * Registers the settings option.
	 */
	public static function register_setting() {
		register_setting(
			'shopnook_settings',
			Shopnook_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Shopnook_Settings', 'sanitize' ),
				'default'           => Shopnook_Settings::defaults(),
			)
		);
	}

	/**
	 * Settings and dashboard links on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=shopnook-settings' ) ) . '">' . esc_html__( 'Settings', 'shopnook-shoppable-images' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=shopnook' ) ) . '">' . esc_html__( 'Dashboard', 'shopnook-shoppable-images' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Loads admin assets on plugin screens.
	 *
	 * @param string $hook Current screen hook.
	 */
	public static function assets( $hook ) {
		if ( ! in_array( $hook, self::$pages, true ) ) {
			return;
		}
		wp_enqueue_style( 'shopnook' );
		wp_enqueue_style( 'shopnook-admin', SHOPNOOK_URL . 'assets/css/admin.css', array(), Shopnook_Render::asset_version( 'assets/css/admin.css' ) );
		if ( false !== strpos( $hook, 'shopnook-settings' ) ) {
			wp_enqueue_media();
		}
		wp_enqueue_script( 'shopnook-admin', SHOPNOOK_URL . 'assets/js/admin.js', array(), Shopnook_Render::asset_version( 'assets/js/admin.js' ), true );
		wp_localize_script(
			'shopnook-admin',
			'shopnookAdmin',
			array(
				'icons' => Shopnook_Pins::icons(),
				'i18n'  => array(
					'choose' => __( 'Choose a pin image', 'shopnook-shoppable-images' ),
					'use'    => __( 'Use as pin', 'shopnook-shoppable-images' ),
				),
			)
		);
	}

	/**
	 * The dashboard screen.
	 */
	public static function page_dashboard() {
		if ( ! current_user_can( self::STATS_CAP ) ) {
			return;
		}
		include SHOPNOOK_PATH . 'includes/admin/views/dashboard.php';
	}

	/**
	 * The settings screen.
	 */
	public static function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SHOPNOOK_PATH . 'includes/admin/views/settings.php';
	}

	/**
	 * Date ranges offered on the dashboard.
	 *
	 * @return array Days => label.
	 */
	public static function ranges() {
		return array(
			7   => __( 'Last 7 days', 'shopnook-shoppable-images' ),
			30  => __( 'Last 30 days', 'shopnook-shoppable-images' ),
			90  => __( 'Last 90 days', 'shopnook-shoppable-images' ),
			365 => __( 'Last 12 months', 'shopnook-shoppable-images' ),
		);
	}

	/**
	 * Short number: 1,284 / 12.9K / 4.2M.
	 *
	 * @param float $n Number.
	 * @return string
	 */
	public static function compact( $n ) {
		$n = (float) $n;
		if ( abs( $n ) >= 1000000 ) {
			return number_format_i18n( $n / 1000000, 1 ) . 'M';
		}
		if ( abs( $n ) >= 10000 ) {
			return number_format_i18n( $n / 1000, 1 ) . 'K';
		}
		return number_format_i18n( $n );
	}

	/**
	 * A price in the store currency, as plain text.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function money( $amount ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * Notes on the order screen which items Shopnook credited.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function order_note( $order ) {
		$lines = $order instanceof WC_Order ? $order->get_meta( Shopnook_Tracking::ORDER_META ) : array();
		if ( ! is_array( $lines ) || ! $lines ) {
			return;
		}
		$items = array();
		foreach ( $lines as $line ) {
			$product = wc_get_product( $line['product'] );
			$post_id = isset( $line['source']['p'] ) ? (int) $line['source']['p'] : 0;
			$where   = $post_id ? get_the_title( $post_id ) : '';
			$name    = $product ? $product->get_name() : '#' . (int) $line['product'];
			/* translators: 1: product name, 2: post title */
			$items[] = $where ? sprintf( __( '%1$s (from “%2$s”)', 'shopnook-shoppable-images' ), $name, $where ) : $name;
		}
		echo '<p class="form-field form-field-wide shopnook-order-note"><strong>' . esc_html__( 'Shopnook:', 'shopnook-shoppable-images' ) . '</strong> ' . esc_html(
			/* translators: %s: list of products */
			sprintf( __( 'credited to shoppable images: %s', 'shopnook-shoppable-images' ), implode( ', ', $items ) )
		) . '</p>';
	}
}
