<?php
/**
 * REST routes under `shopnook/v1`.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product search and image pins for the editors, and the public events route.
 */
class Shopnook_Rest {

	const NS = 'shopnook/v1';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Registers the routes.
	 */
	public static function routes() {
		register_rest_route(
			self::NS,
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'products' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'search'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'include' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'integer' ),
						'default' => array(),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/images/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_image' ),
					'permission_callback' => array( __CLASS__, 'can_read_image' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'save_image' ),
					'permission_callback' => array( __CLASS__, 'can_edit_image' ),
					'args'                => array(
						'hotspots'  => array(
							'type'    => 'array',
							'default' => array(),
						),
						'overrides' => array(
							'type'    => 'object',
							'default' => array(),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/events',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'events' ),
				// Public on purpose: shoppers send anonymous counters. Input is validated and rate-limited.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Products for the picker.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function products( WP_REST_Request $request ) {
		return rest_ensure_response( Shopnook_Products::search( (string) $request['search'], array_filter( array_map( 'absint', (array) $request['include'] ) ) ) );
	}

	/**
	 * Whether the user may read an image's pins (any editor may use the image).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_read_image( WP_REST_Request $request ) {
		return current_user_can( 'edit_posts' ) && wp_attachment_is_image( (int) $request['id'] );
	}

	/**
	 * Whether the user may change an image's pins.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_edit_image( WP_REST_Request $request ) {
		return wp_attachment_is_image( (int) $request['id'] ) && current_user_can( 'edit_post', (int) $request['id'] );
	}

	/**
	 * An image with its pins.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_image( WP_REST_Request $request ) {
		return rest_ensure_response( self::image_response( (int) $request['id'] ) );
	}

	/**
	 * Saves an image's pins and look.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function save_image( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		Shopnook_Images::save(
			$id,
			array(
				'hotspots'  => (array) $request['hotspots'],
				'overrides' => (array) $request['overrides'],
			)
		);
		return rest_ensure_response( self::image_response( $id ) );
	}

	/**
	 * Image data for the editors.
	 *
	 * @param int $id Attachment ID.
	 * @return array
	 */
	private static function image_response( $id ) {
		$data  = Shopnook_Images::get( $id );
		$large = wp_get_attachment_image_src( $id, 'large' );
		return array(
			'id'        => $id,
			'url'       => $large ? $large[0] : wp_get_attachment_url( $id ),
			'alt'       => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'hotspots'  => Shopnook_Images::sanitize_hotspots( $data['hotspots'] ),
			'overrides' => (object) $data['overrides'],
			'canEdit'   => current_user_can( 'edit_post', $id ),
			'count'     => Shopnook_Images::count_label( count( $data['hotspots'] ) ),
		);
	}

	/**
	 * Stores a batch of anonymous events.
	 *
	 * The script sends them with sendBeacon, which can't set headers, so the
	 * body is read as raw JSON whatever the content type says.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function events( WP_REST_Request $request ) {
		$body   = json_decode( (string) $request->get_body(), true );
		$events = is_array( $body ) && isset( $body['events'] ) && is_array( $body['events'] ) ? $body['events'] : array();
		$stored = $events ? Shopnook_Tracking::ingest( $events ) : 0;
		return new WP_REST_Response( array( 'stored' => $stored ), 200 );
	}
}
