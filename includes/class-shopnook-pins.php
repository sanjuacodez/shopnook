<?php
/**
 * Pin styles and their markup.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * The built-in pin templates plus the custom image pin.
 */
class Shopnook_Pins {

	/**
	 * Pin styles.
	 *
	 * @return array Key => label.
	 */
	public static function styles() {
		return array(
			'dot'    => __( 'Dot', 'shopnook' ),
			'pulse'  => __( 'Pulsing dot', 'shopnook' ),
			'plus'   => __( 'Plus', 'shopnook' ),
			'number' => __( 'Number', 'shopnook' ),
			'bag'    => __( 'Shopping bag', 'shopnook' ),
			'tag'    => __( 'Price tag', 'shopnook' ),
			'custom' => __( 'Custom image', 'shopnook' ),
		);
	}

	/**
	 * Icons drawn inside the pin.
	 *
	 * @return array Style => SVG.
	 */
	public static function icons() {
		$svg = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>';
		return array(
			'plus' => sprintf( $svg, '<path d="M12 5v14M5 12h14"/>' ),
			'bag'  => sprintf( $svg, '<path d="M6 7h12l1 13H5L6 7Z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>' ),
			'tag'  => sprintf( $svg, '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9-9-9Z"/><circle cx="7.5" cy="7.5" r="1.5" fill="currentColor"/>' ),
		);
	}

	/**
	 * What goes inside a pin button.
	 *
	 * @param array $look   Resolved look (see Shopnook_Settings::resolve()).
	 * @param int   $number Pin number, from 1.
	 * @return string HTML.
	 */
	public static function inner( array $look, $number ) {
		$icons = self::icons();
		switch ( $look['pin_style'] ) {
			case 'number':
				return '<span class="shopnook-pin__num">' . (int) $number . '</span>';
			case 'custom':
				$src = $look['pin_image'] ? wp_get_attachment_image_url( $look['pin_image'], 'thumbnail' ) : '';
				return $src ? '<img class="shopnook-pin__img" src="' . esc_url( $src ) . '" alt="" draggable="false">' : '<span class="shopnook-pin__dot"></span>';
			case 'pulse':
				return '<span class="shopnook-pin__ring" aria-hidden="true"></span><span class="shopnook-pin__dot"></span>';
			case 'plus':
			case 'bag':
			case 'tag':
				return $icons[ $look['pin_style'] ];
			default:
				return '<span class="shopnook-pin__dot"></span>';
		}
	}

	/**
	 * Classes for a pin button.
	 *
	 * @param array $look Resolved look.
	 * @return string
	 */
	public static function classes( array $look ) {
		return 'shopnook-pin shopnook-pin--' . sanitize_html_class( $look['pin_style'] ) . ' shopnook-pin--' . sanitize_html_class( $look['pin_size'] );
	}

	/**
	 * A template pin, cloned by the script for gallery popups. `{n}` is the number.
	 *
	 * @param array $look Resolved look.
	 * @return string HTML.
	 */
	public static function template( array $look ) {
		return '<button type="button" class="' . esc_attr( self::classes( $look ) ) . '">' . str_replace( '>1<', '>{n}<', self::inner( $look, 1 ) ) . '</button>';
	}

	/**
	 * CSS custom properties for a look.
	 *
	 * @param array $look Resolved look.
	 * @return string Inline style.
	 */
	public static function style_vars( array $look ) {
		return sprintf( '--shopnook-pin-fg:%s;--shopnook-pin-bg:%s;--shopnook-accent:%s', $look['pin_color'], $look['pin_bg'], $look['accent'] );
	}
}
