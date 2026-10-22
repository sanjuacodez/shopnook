<?php
/**
 * Plugin Name:       Shopnook – Shoppable Images for WooCommerce
 * Plugin URI:        https://github.com/sanjuacodez/shopnook
 * Description:       Tag WooCommerce products on any image or gallery in the block editor. Pins open product cards with add to cart, galleries get Instagram, masonry and stories layouts, and an analytics dashboard shows views, clicks and the sales they bring.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Sanjay Shankar
 * Author URI:        https://sanjayshankar.me
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       shopnook
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   10.3
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

define( 'SHOPNOOK_VERSION', '1.0.0' );
define( 'SHOPNOOK_DB_VERSION', '1' );
define( 'SHOPNOOK_FILE', __FILE__ );
define( 'SHOPNOOK_PATH', plugin_dir_path( __FILE__ ) );
define( 'SHOPNOOK_URL', plugin_dir_url( __FILE__ ) );

require_once SHOPNOOK_PATH . 'includes/class-shopnook-settings.php';
require_once SHOPNOOK_PATH . 'includes/class-shopnook-db.php';
require_once SHOPNOOK_PATH . 'includes/class-shopnook-pins.php';
require_once SHOPNOOK_PATH . 'includes/class-shopnook-images.php';
require_once SHOPNOOK_PATH . 'includes/class-shopnook-products.php';
require_once SHOPNOOK_PATH . 'includes/class-shopnook-render.php';
require_once SHOPNOOK_PATH . 'includes/class-shopnook-gallery.php';
require_once SHOPNOOK_PATH . 'includes/class-shopnook-tracking.php';
require_once SHOPNOOK_PATH . 'includes/class-shopnook-stats.php';
require_once SHOPNOOK_PATH . 'includes/class-shopnook-rest.php';
require_once SHOPNOOK_PATH . 'includes/class-shopnook-editor.php';
require_once SHOPNOOK_PATH . 'includes/class-shopnook-privacy.php';

if ( is_admin() ) {
	require_once SHOPNOOK_PATH . 'includes/admin/class-shopnook-admin.php';
}

register_activation_hook( __FILE__, array( 'Shopnook_DB', 'install' ) );
register_deactivation_hook( __FILE__, array( 'Shopnook_Stats', 'unschedule' ) );

add_action( 'before_woocommerce_init', 'shopnook_declare_wc_compatibility' );
add_action( 'plugins_loaded', 'shopnook_boot' );

/**
 * Declares support for WooCommerce order tables (HPOS) and the cart and checkout blocks.
 */
function shopnook_declare_wc_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SHOPNOOK_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SHOPNOOK_FILE, true );
	}
}

/**
 * Wires every part of the plugin once WordPress and other plugins are loaded.
 */
function shopnook_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'shopnook_missing_woocommerce_notice' );
		return;
	}

	Shopnook_DB::maybe_upgrade();
	Shopnook_Images::init();
	Shopnook_Render::init();
	Shopnook_Gallery::init();
	Shopnook_Tracking::init();
	Shopnook_Stats::init();
	Shopnook_Rest::init();
	Shopnook_Editor::init();
	Shopnook_Privacy::init();

	if ( is_admin() ) {
		Shopnook_Admin::init();
	}
}

/**
 * Explains that WooCommerce is needed, for sites that skip plugin dependencies.
 */
function shopnook_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'Shopnook needs WooCommerce to be installed and active.', 'shopnook' ) . '</p></div>';
}
