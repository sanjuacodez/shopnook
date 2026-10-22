<?php
/**
 * Settings: defaults, reading, sanitizing and per-image overrides.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Holds the single `shopnook_settings` option.
 *
 * Some settings can be overridden per image, first in the Media Library and
 * then in the block (see Shopnook_Settings::OVERRIDABLE). The block wins over
 * the Media Library, which wins over these site-wide values.
 */
class Shopnook_Settings {

	const OPTION = 'shopnook_settings';

	/**
	 * Settings an image or a block may override.
	 */
	const OVERRIDABLE = array( 'pin_style', 'pin_image', 'pin_size', 'pin_color', 'pin_bg', 'pin_visibility', 'display', 'mobile', 'card_fields', 'cart_text', 'view_text', 'accent' );

	/**
	 * Settings for this request, merged over the defaults.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Every setting and its default. Empty text means "use the translated default".
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Pins.
			'pin_style'       => 'dot',
			'pin_image'       => 0,
			'pin_size'        => 'md',
			'pin_color'       => '#ffffff',
			'pin_bg'          => '#111111',
			'pin_visibility'  => 'always',
			// Display and card.
			'display'         => 'tooltip',
			'mobile'          => 'sheet',
			'card_fields'     => array( 'image', 'name', 'price', 'sale', 'rating', 'cart', 'view' ),
			'cart_text'       => '',
			'view_text'       => '',
			'accent'          => '#111111',
			// Galleries.
			'gallery_layout'  => 'instagram',
			'gallery_columns' => 3,
			'gallery_gap'     => 8,
			'gallery_badge'   => true,
			// Tracking.
			'tracking'        => true,
			'credit_days'     => 7,
			'keep_days'       => 730,
			'exclude_staff'   => true,
			'delete_data'     => false,
		);
	}

	/**
	 * Allowed values for every choice setting.
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			'pin_style'      => array_keys( Shopnook_Pins::styles() ),
			'pin_size'       => array( 'sm', 'md', 'lg' ),
			'pin_visibility' => array( 'always', 'hover' ),
			'display'        => array_keys( self::displays() ),
			'mobile'         => array_keys( self::mobiles() ),
			'card_fields'    => array_keys( self::card_fields() ),
			'gallery_layout' => array( 'instagram', 'masonry', 'stories' ),
			'keep_days'      => array( 90, 180, 365, 730, 0 ),
		);
	}

	/**
	 * Product card parts, in display order.
	 *
	 * @return array Key => label.
	 */
	public static function card_fields() {
		return array(
			'image'   => __( 'Product image', 'shopnook' ),
			'name'    => __( 'Name', 'shopnook' ),
			'price'   => __( 'Price', 'shopnook' ),
			'sale'    => __( 'Sale badge', 'shopnook' ),
			'rating'  => __( 'Star rating', 'shopnook' ),
			'excerpt' => __( 'Short description', 'shopnook' ),
			'cart'    => __( 'Add to cart button', 'shopnook' ),
			'view'    => __( 'View product link', 'shopnook' ),
		);
	}

	/**
	 * Display modes.
	 *
	 * @return array Key => label.
	 */
	public static function displays() {
		return array(
			'tooltip' => __( 'Tooltip card', 'shopnook' ),
			'panel'   => __( 'Panel below image', 'shopnook' ),
			'drawer'  => __( 'Side drawer', 'shopnook' ),
			'list'    => __( 'Shop this look list', 'shopnook' ),
		);
	}

	/**
	 * Mobile behaviours.
	 *
	 * @return array Key => label.
	 */
	public static function mobiles() {
		return array(
			'sheet' => __( 'Bottom sheet', 'shopnook' ),
			'same'  => __( 'Same as desktop', 'shopnook' ),
			'panel' => __( 'Panel below image', 'shopnook' ),
		);
	}

	/**
	 * All settings.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Saves a partial update without going through the settings form.
	 *
	 * @param array $changes Settings to change.
	 */
	public static function update( array $changes ) {
		self::flush();
		update_option( self::OPTION, self::sanitize( array_merge( self::all(), $changes ) ) );
		self::flush();
	}

	/**
	 * Forgets the cached settings, e.g. after they were saved.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Cleans a full settings array from the form.
	 *
	 * The form posts one tab at a time, so settings that belong to other tabs
	 * keep their saved values. The hidden `_tab` field names the tab.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$current  = wp_parse_args( (array) get_option( self::OPTION, array() ), $defaults );
		$tab      = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';
		$tabs     = self::tabs();

		// Checkboxes on the posted tab that are missing were switched off.
		if ( $tab && isset( $tabs[ $tab ] ) ) {
			foreach ( $tabs[ $tab ] as $key ) {
				if ( ! array_key_exists( $key, $input ) && ( is_bool( $defaults[ $key ] ) || 'card_fields' === $key ) ) {
					$input[ $key ] = is_bool( $defaults[ $key ] ) ? false : array();
				}
			}
		}

		$out = array();
		foreach ( $defaults as $key => $default ) {
			$value       = array_key_exists( $key, $input ) ? $input[ $key ] : $current[ $key ];
			$out[ $key ] = self::clean( $key, $value, $default );
		}
		return $out;
	}

	/**
	 * Settings shown on each settings tab.
	 *
	 * @return array Tab => keys.
	 */
	public static function tabs() {
		return array(
			'pins'     => array( 'pin_style', 'pin_image', 'pin_size', 'pin_color', 'pin_bg', 'pin_visibility' ),
			'display'  => array( 'display', 'mobile', 'card_fields', 'cart_text', 'view_text', 'accent' ),
			'galleries' => array( 'gallery_layout', 'gallery_columns', 'gallery_gap', 'gallery_badge' ),
			'tracking' => array( 'tracking', 'credit_days', 'keep_days', 'exclude_staff', 'delete_data' ),
		);
	}

	/**
	 * Cleans one value.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $value   Raw value.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function clean( $key, $value, $default ) {
		$choices = self::choices();

		if ( 'card_fields' === $key ) {
			$value = is_array( $value ) ? array_map( 'sanitize_key', $value ) : array();
			// Keep the canonical order whatever order the form sent.
			return array_values( array_intersect( $choices['card_fields'], $value ) );
		}
		if ( is_bool( $default ) ) {
			return ! empty( $value ) && 'false' !== $value;
		}
		if ( 'keep_days' === $key ) {
			$value = absint( $value );
			return in_array( $value, $choices['keep_days'], true ) ? $value : $default;
		}
		if ( isset( $choices[ $key ] ) ) {
			$value = sanitize_key( (string) $value );
			return in_array( $value, $choices[ $key ], true ) ? $value : $default;
		}
		if ( in_array( $key, array( 'pin_color', 'pin_bg', 'accent' ), true ) ) {
			$color = sanitize_hex_color( (string) $value );
			return $color ? $color : $default;
		}
		switch ( $key ) {
			case 'pin_image':
				$value = absint( $value );
				return ( $value && wp_attachment_is_image( $value ) ) ? $value : 0;
			case 'gallery_columns':
				return min( 6, max( 1, absint( $value ) ) );
			case 'gallery_gap':
				return min( 40, absint( $value ) );
			case 'credit_days':
				return min( 90, max( 1, absint( $value ) ) );
			case 'cart_text':
			case 'view_text':
				return mb_substr( sanitize_text_field( (string) $value ), 0, 40 );
		}
		return $default;
	}

	/**
	 * Cleans an overrides array from the Media Library or a block.
	 *
	 * Missing or empty values mean "inherit" and are dropped.
	 *
	 * @param mixed $input Raw overrides.
	 * @return array
	 */
	public static function sanitize_overrides( $input ) {
		$out = array();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		foreach ( self::OVERRIDABLE as $key ) {
			if ( ! array_key_exists( $key, $input ) || '' === $input[ $key ] || null === $input[ $key ] ) {
				continue;
			}
			if ( 'card_fields' === $key && ! is_array( $input[ $key ] ) ) {
				continue;
			}
			$value = self::clean( $key, $input[ $key ], null );
			if ( null !== $value && ( 'pin_image' !== $key || $value ) ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * The look of one image: site settings, then each overrides layer.
	 *
	 * @param array ...$layers Overrides, least specific first.
	 * @return array
	 */
	public static function resolve( ...$layers ) {
		$all  = self::all();
		$look = array();
		foreach ( self::OVERRIDABLE as $key ) {
			$look[ $key ] = $all[ $key ];
		}
		foreach ( $layers as $layer ) {
			foreach ( self::sanitize_overrides( $layer ) as $key => $value ) {
				$look[ $key ] = $value;
			}
		}
		if ( 'custom' === $look['pin_style'] && ! $look['pin_image'] ) {
			$look['pin_style'] = 'dot';
		}
		return $look;
	}
}
