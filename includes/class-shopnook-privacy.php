<?php
/**
 * Privacy policy text.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Suggests text for the site's privacy policy.
 */
class Shopnook_Privacy {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'policy' ) );
	}

	/**
	 * Adds the suggested privacy policy text.
	 */
	public static function policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'Shoppable images on this site count how often they are viewed and how often their products are opened, clicked and added to the cart. These are anonymous daily totals; no personal data or IP address is stored.', 'shopnook' ) . '</p>'
			. '<p>' . esc_html__( 'When you open a product from a shoppable image, a cookie named shopnook_ref remembers the product and the image for a few days, so a later purchase of that product can be credited to the image. It holds no personal data. If this site asks for cookie consent, the cookie is only set when you allow statistics cookies.', 'shopnook' ) . '</p>';
		wp_add_privacy_policy_content( 'Shopnook', wp_kses_post( $content ) );
	}
}
