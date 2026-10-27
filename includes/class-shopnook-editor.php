<?php
/**
 * Block editor and Media Library scripts.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the pin editor in the block editor and wherever the media modal opens.
 *
 * Plain scripts on the WordPress globals (wp.element, wp.components…), so
 * there is no build step.
 */
class Shopnook_Editor {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'block_editor' ) );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'canvas' ) );
		add_action( 'wp_enqueue_media', array( __CLASS__, 'media' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'media_screens' ) );
	}

	/**
	 * Registers the shared pin editor and its stylesheet.
	 */
	public static function register() {
		$version = array( 'Shopnook_Render', 'asset_version' );
		wp_register_style( 'shopnook-editor', SHOPNOOK_URL . 'assets/css/editor.css', array( 'wp-components', 'shopnook' ), call_user_func( $version, 'assets/css/editor.css' ) );
		wp_register_script(
			'shopnook-pin-editor',
			SHOPNOOK_URL . 'assets/js/pin-editor.js',
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-compose' ),
			call_user_func( $version, 'assets/js/pin-editor.js' ),
			true
		);
		wp_register_script(
			'shopnook-block-editor',
			SHOPNOOK_URL . 'assets/js/block-editor.js',
			array( 'shopnook-pin-editor', 'wp-hooks', 'wp-blocks', 'wp-block-editor', 'wp-data', 'wp-core-data' ),
			call_user_func( $version, 'assets/js/block-editor.js' ),
			true
		);
		wp_register_script(
			'shopnook-media',
			SHOPNOOK_URL . 'assets/js/media.js',
			array( 'shopnook-pin-editor' ),
			call_user_func( $version, 'assets/js/media.js' ),
			true
		);
		foreach ( array( 'shopnook-pin-editor', 'shopnook-block-editor', 'shopnook-media' ) as $handle ) {
			wp_set_script_translations( $handle, 'shopnook-shoppable-images', SHOPNOOK_PATH . 'languages' );
		}
	}

	/**
	 * Adds the editor settings to the page once.
	 */
	private static function add_config() {
		static $added = false;
		if ( ! $added ) {
			$added = true;
			wp_add_inline_script( 'shopnook-pin-editor', 'window.shopnookEditor = ' . wp_json_encode( self::config() ) . ';', 'before' );
		}
	}

	/**
	 * Labels, defaults and icons the editor scripts need.
	 *
	 * @return array
	 */
	public static function config() {
		$settings = Shopnook_Settings::all();
		$look     = Shopnook_Settings::resolve();
		$pin_src  = $look['pin_image'] ? wp_get_attachment_image_url( $look['pin_image'], 'thumbnail' ) : '';
		return array(
			'defaults'     => $look,
			'pinImageUrl'  => $pin_src ? $pin_src : '',
			'gallery'      => array(
				'layout'  => $settings['gallery_layout'],
				'columns' => (int) $settings['gallery_columns'],
			),
			'pinStyles'    => Shopnook_Pins::styles(),
			'pinIcons'     => Shopnook_Pins::icons(),
			'sizes'        => array(
				'sm' => __( 'Small', 'shopnook-shoppable-images' ),
				'md' => __( 'Medium', 'shopnook-shoppable-images' ),
				'lg' => __( 'Large', 'shopnook-shoppable-images' ),
			),
			'visibility'   => array(
				'always' => __( 'Always', 'shopnook-shoppable-images' ),
				'hover'  => __( 'On hover', 'shopnook-shoppable-images' ),
			),
			'displays'     => Shopnook_Settings::displays(),
			'mobiles'      => Shopnook_Settings::mobiles(),
			'cardFields'   => Shopnook_Settings::card_fields(),
			'layouts'      => Shopnook_Gallery::layouts(),
			'settingsUrl'  => admin_url( 'admin.php?page=shopnook-settings' ),
			'canManage'    => current_user_can( 'manage_options' ),
			// WordPress 7.0 added a Content tab to the block sidebar; Shopnook's panels belong there.
			'contentTab'   => version_compare( get_bloginfo( 'version' ), '7.0-alpha', '>=' ),
		);
	}

	/**
	 * Block editor: the Shopnook panel on Image and Gallery blocks.
	 */
	public static function block_editor() {
		self::add_config();
		wp_enqueue_style( 'shopnook-editor' );
		wp_enqueue_script( 'shopnook-block-editor' );
	}

	/**
	 * The editor canvas is an iframe; the bag badge on shoppable blocks needs its style there too.
	 */
	public static function canvas() {
		if ( is_admin() ) {
			wp_enqueue_style( 'shopnook-canvas', SHOPNOOK_URL . 'assets/css/canvas.css', array(), Shopnook_Render::asset_version( 'assets/css/canvas.css' ) );
		}
	}

	/**
	 * Media modal anywhere in the admin: the "Tag products" button.
	 */
	public static function media() {
		if ( ! is_admin() || ! current_user_can( 'upload_files' ) ) {
			return;
		}
		self::add_config();
		wp_enqueue_style( 'shopnook-editor' );
		wp_enqueue_script( 'shopnook-media' );
	}

	/**
	 * The Media Library list view and the Edit Media screen print the field
	 * without opening the modal.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function media_screens( $hook ) {
		$screen = get_current_screen();
		if ( 'upload.php' === $hook || ( 'post.php' === $hook && $screen && 'attachment' === $screen->post_type ) ) {
			self::media();
		}
	}
}

