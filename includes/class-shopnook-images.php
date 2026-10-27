<?php
/**
 * Pins stored on Media Library images.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads, cleans and saves the `_shopnook_image` attachment meta.
 *
 * The meta holds `hotspots` (the pins) and `overrides` (look settings for
 * this image). Blocks use them unless they were customized for their post.
 */
class Shopnook_Images {

	const META = '_shopnook_image';

	/**
	 * Most pins one image can hold; more than enough, but keeps payloads bounded.
	 */
	const MAX_PINS = 100;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'media_field' ), 10, 2 );
		add_filter( 'manage_media_columns', array( __CLASS__, 'media_column' ) );
		add_action( 'manage_media_custom_column', array( __CLASS__, 'media_column_value' ), 10, 2 );
	}

	/**
	 * Pins and overrides saved on an image.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array { hotspots: array, overrides: array }
	 */
	public static function get( $attachment_id ) {
		$data = $attachment_id ? get_post_meta( (int) $attachment_id, self::META, true ) : array();
		$data = is_array( $data ) ? $data : array();
		return array(
			'hotspots'  => isset( $data['hotspots'] ) && is_array( $data['hotspots'] ) ? $data['hotspots'] : array(),
			'overrides' => isset( $data['overrides'] ) && is_array( $data['overrides'] ) ? $data['overrides'] : array(),
		);
	}

	/**
	 * Saves pins and overrides on an image. Empty data removes the meta.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $data          Raw { hotspots, overrides }.
	 * @return array The cleaned data.
	 */
	public static function save( $attachment_id, array $data ) {
		$clean = array(
			'hotspots'  => self::sanitize_hotspots( isset( $data['hotspots'] ) ? $data['hotspots'] : array() ),
			'overrides' => Shopnook_Settings::sanitize_overrides( isset( $data['overrides'] ) ? $data['overrides'] : array() ),
		);
		if ( ! $clean['hotspots'] && ! $clean['overrides'] ) {
			delete_post_meta( $attachment_id, self::META );
		} else {
			update_post_meta( $attachment_id, self::META, $clean );
		}
		return $clean;
	}

	/**
	 * Cleans a list of pins. Pins without a real product are dropped.
	 *
	 * @param mixed $hotspots Raw pins.
	 * @return array
	 */
	public static function sanitize_hotspots( $hotspots ) {
		$out = array();
		if ( ! is_array( $hotspots ) ) {
			return $out;
		}
		foreach ( $hotspots as $spot ) {
			if ( ! is_array( $spot ) || count( $out ) >= self::MAX_PINS ) {
				continue;
			}
			$product = isset( $spot['product'] ) ? absint( $spot['product'] ) : 0;
			if ( ! $product || 'product' !== get_post_type( $product ) ) {
				continue;
			}
			$id    = isset( $spot['id'] ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $spot['id'] ) ) : '';
			$out[] = array(
				'id'      => $id ? substr( $id, 0, 12 ) : substr( md5( wp_rand() ), 0, 8 ),
				'x'       => self::percent( isset( $spot['x'] ) ? $spot['x'] : 50 ),
				'y'       => self::percent( isset( $spot['y'] ) ? $spot['y'] : 50 ),
				'product' => $product,
				'label'   => isset( $spot['label'] ) ? mb_substr( sanitize_text_field( (string) $spot['label'] ), 0, 80 ) : '',
			);
		}
		return $out;
	}

	/**
	 * A position as a percentage between 0 and 100.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	private static function percent( $value ) {
		return round( min( 100, max( 0, (float) $value ) ), 2 );
	}

	/**
	 * The pins and overrides a block should use.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $settings      The block's `shopnook` attribute.
	 * @return array { hotspots, overrides: [ media, block ], custom: bool }
	 */
	public static function for_block( $attachment_id, array $settings ) {
		$media  = self::get( $attachment_id );
		$custom = ! empty( $settings['custom'] );
		return array(
			'hotspots'  => $custom ? self::sanitize_hotspots( isset( $settings['hotspots'] ) ? $settings['hotspots'] : array() ) : self::sanitize_hotspots( $media['hotspots'] ),
			'overrides' => array( $media['overrides'], isset( $settings['overrides'] ) && is_array( $settings['overrides'] ) ? $settings['overrides'] : array() ),
			'custom'    => $custom,
		);
	}

	/**
	 * Adds the "Products" row with a "Tag products" button to the attachment details.
	 *
	 * @param array   $fields Fields.
	 * @param WP_Post $post   Attachment.
	 * @return array
	 */
	public static function media_field( $fields, $post ) {
		if ( ! wp_attachment_is_image( $post ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $fields;
		}
		$count = count( self::get( $post->ID )['hotspots'] );

		$fields['shopnook'] = array(
			'label' => __( 'Products', 'shopnook-shoppable-images' ),
			'input' => 'html',
			'html'  => sprintf(
				'<span class="shopnook-media-field"><button type="button" class="button shopnook-media-button" data-id="%1$d">%2$s</button> <span class="shopnook-media-count" data-id="%1$d">%3$s</span></span>',
				$post->ID,
				esc_html__( 'Tag products', 'shopnook-shoppable-images' ),
				esc_html( self::count_label( $count ) )
			),
			'helps' => __( 'Shopnook pins show wherever this image is used as a shoppable image.', 'shopnook-shoppable-images' ),
		);
		return $fields;
	}

	/**
	 * "3 products" text.
	 *
	 * @param int $count Pins.
	 * @return string
	 */
	public static function count_label( $count ) {
		/* translators: %d: number of tagged products */
		return $count ? sprintf( _n( '%d product', '%d products', $count, 'shopnook-shoppable-images' ), $count ) : __( 'None yet', 'shopnook-shoppable-images' );
	}

	/**
	 * Adds a "Products" column to the Media Library list view.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function media_column( $columns ) {
		$columns['shopnook'] = __( 'Products', 'shopnook-shoppable-images' );
		return $columns;
	}

	/**
	 * Prints the number of tagged products.
	 *
	 * @param string $column Column.
	 * @param int    $id     Attachment ID.
	 */
	public static function media_column_value( $column, $id ) {
		if ( 'shopnook' !== $column ) {
			return;
		}
		$count = count( self::get( $id )['hotspots'] );
		echo $count ? esc_html( number_format_i18n( $count ) ) : '<span aria-hidden="true">—</span>';
	}
}
