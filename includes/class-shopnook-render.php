<?php
/**
 * Front end: shoppable Image blocks, product cards and assets.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds pins to core Image blocks that have Shopnook switched on.
 *
 * The saved block markup is never changed. Everything happens when the block
 * renders, so turning the plugin off leaves an ordinary image.
 */
class Shopnook_Render {

	/**
	 * Set while a shoppable gallery renders, so its images skip their own pins.
	 *
	 * @var int
	 */
	public static $in_gallery = 0;

	/**
	 * Whether the assets were enqueued.
	 *
	 * @var bool
	 */
	private static $enqueued = false;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_filter( 'register_block_type_args', array( __CLASS__, 'block_attributes' ), 10, 2 );
		add_filter( 'render_block_core/image', array( __CLASS__, 'render_image' ), 20, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'footer_config' ), 1 );
	}

	/**
	 * Registers the `shopnook` attribute on core Image and Gallery blocks.
	 *
	 * @param array  $args Block type arguments.
	 * @param string $name Block name.
	 * @return array
	 */
	public static function block_attributes( $args, $name ) {
		if ( in_array( $name, array( 'core/image', 'core/gallery' ), true ) ) {
			$args['attributes']             = isset( $args['attributes'] ) ? $args['attributes'] : array();
			$args['attributes']['shopnook'] = array(
				'type'    => 'object',
				'default' => new stdClass(),
			);
		}
		return $args;
	}

	/**
	 * Registers the front-end script and stylesheet.
	 */
	public static function register_assets() {
		wp_register_style( 'shopnook', SHOPNOOK_URL . 'assets/css/shopnook.css', array(), self::asset_version( 'assets/css/shopnook.css' ) );
		wp_register_script(
			'shopnook',
			SHOPNOOK_URL . 'assets/js/shopnook.js',
			array(),
			self::asset_version( 'assets/js/shopnook.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Cache-busting version for an asset.
	 *
	 * @param string $path Path inside the plugin.
	 * @return string
	 */
	public static function asset_version( $path ) {
		$file = SHOPNOOK_PATH . $path;
		return SHOPNOOK_VERSION . ( file_exists( $file ) ? '.' . filemtime( $file ) : '' );
	}

	/**
	 * Enqueues the front-end assets once.
	 */
	public static function enqueue() {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;
		wp_enqueue_style( 'shopnook' );
		wp_enqueue_script( 'shopnook' );
	}

	/**
	 * Prints the script settings, once something shoppable was rendered.
	 */
	public static function footer_config() {
		if ( ! self::$enqueued ) {
			return;
		}
		$config = array(
			'events'     => esc_url_raw( rest_url( 'shopnook/v1/events' ) ),
			'addToCart'  => esc_url_raw( WC_AJAX::get_endpoint( 'add_to_cart' ) ),
			'cartUrl'    => esc_url_raw( wc_get_cart_url() ),
			'redirect'   => 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ),
			'track'      => Shopnook_Tracking::enabled_for_visitor(),
			'creditDays' => (int) Shopnook_Settings::get( 'credit_days' ),
			'cookie'     => Shopnook_Tracking::COOKIE,
			'i18n'       => array(
				'added'    => __( 'Added', 'shopnook-shoppable-images' ),
				'viewCart' => __( 'View cart', 'shopnook-shoppable-images' ),
				'adding'   => __( 'Adding…', 'shopnook-shoppable-images' ),
				'error'    => __( 'Could not add this product. Please try again.', 'shopnook-shoppable-images' ),
				'close'    => __( 'Close', 'shopnook-shoppable-images' ),
				'previous' => __( 'Previous photo', 'shopnook-shoppable-images' ),
				'next'     => __( 'Next photo', 'shopnook-shoppable-images' ),
				/* translators: 1: photo number, 2: number of photos */
				'counter'  => __( '%1$d of %2$d', 'shopnook-shoppable-images' ),
				'products' => __( 'Products in this photo', 'shopnook-shoppable-images' ),
				'none'     => __( 'No products tagged in this photo.', 'shopnook-shoppable-images' ),
			),
		);
		wp_add_inline_script( 'shopnook', 'window.shopnookConfig = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * The post an image is shown on, for analytics.
	 *
	 * @return int
	 */
	public static function current_post_id() {
		$id = get_the_ID();
		if ( ! $id && is_singular() ) {
			$id = get_queried_object_id();
		}
		return (int) $id;
	}

	/**
	 * Adds pins to a shoppable Image block.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public static function render_image( $content, $block ) {
		$settings = isset( $block['attrs']['shopnook'] ) && is_array( $block['attrs']['shopnook'] ) ? $block['attrs']['shopnook'] : array();
		if ( self::$in_gallery || empty( $settings['enabled'] ) || false === strpos( $content, '<img' ) ) {
			return $content;
		}

		$attachment_id = isset( $block['attrs']['id'] ) ? (int) $block['attrs']['id'] : 0;
		$data          = Shopnook_Images::for_block( $attachment_id, $settings );
		$look          = Shopnook_Settings::resolve( ...$data['overrides'] );
		$spots         = self::visible_spots( $data['hotspots'] );
		if ( ! $spots ) {
			return $content;
		}

		$source = array(
			's' => 1,
			'a' => $attachment_id,
			'p' => self::current_post_id(),
		);

		// Wrap the image (and its link, if any) so pins are placed on the picture itself.
		$wrapped = preg_replace_callback(
			'#(<a\b[^>]*>\s*)?<img\b[^>]*>(\s*</a>)?#i',
			function ( $m ) use ( $spots, $look ) {
				return '<div class="shopnook-stage">' . $m[0] . self::pins_html( $spots, $look ) . '</div>';
			},
			$content,
			1
		);
		if ( ! $wrapped || $wrapped === $content ) {
			return $content;
		}

		$extra = self::cards_template( $spots, $look );
		if ( 'list' === $look['display'] ) {
			$extra .= self::list_html( $spots, $look );
		}
		// Extras go inside the figure, before the caption if there is one.
		if ( false !== strpos( $wrapped, '<figcaption' ) ) {
			$wrapped = preg_replace( '#<figcaption#i', $extra . '<figcaption', $wrapped, 1 );
		} else {
			$wrapped = preg_replace( '#</figure>\s*$#i', $extra . '</figure>', $wrapped, 1 );
		}

		$tags = new WP_HTML_Tag_Processor( $wrapped );
		if ( ! $tags->next_tag( 'figure' ) ) {
			return $content;
		}
		self::decorate( $tags, $look, $source );

		self::enqueue();
		return $tags->get_updated_html();
	}

	/**
	 * Adds the Shopnook class, settings and colours to an element.
	 *
	 * @param WP_HTML_Tag_Processor $tags   Processor positioned on the element.
	 * @param array                 $look   Resolved look.
	 * @param array                 $source Analytics source { s, a, p }.
	 */
	public static function decorate( WP_HTML_Tag_Processor $tags, array $look, array $source ) {
		$tags->add_class( 'shopnook' );
		$tags->add_class( 'shopnook--' . $look['display'] );
		if ( 'hover' === $look['pin_visibility'] ) {
			$tags->add_class( 'shopnook--pins-hover' );
		}
		$style = (string) $tags->get_attribute( 'style' );
		$tags->set_attribute( 'style', trim( $style . ( $style && ';' !== substr( rtrim( $style ), -1 ) ? ';' : '' ) . Shopnook_Pins::style_vars( $look ) ) );
		$tags->set_attribute( 'data-shopnook', wp_json_encode( self::script_settings( $look, $source ) ) );
	}

	/**
	 * Settings the script needs for one image or gallery.
	 *
	 * @param array $look   Resolved look.
	 * @param array $source Analytics source.
	 * @return array
	 */
	public static function script_settings( array $look, array $source ) {
		return array(
			'display' => $look['display'],
			'mobile'  => $look['mobile'],
			'src'     => $source,
		);
	}

	/**
	 * Pins whose product can be shown, numbered in order.
	 *
	 * @param array $hotspots Pins.
	 * @return array
	 */
	public static function visible_spots( array $hotspots ) {
		$out = array();
		foreach ( $hotspots as $spot ) {
			$card = Shopnook_Products::card( $spot['product'] );
			if ( $card ) {
				$spot['card'] = $card;
				$out[]        = $spot;
			}
		}
		return $out;
	}

	/**
	 * Pin buttons.
	 *
	 * @param array $spots Visible pins.
	 * @param array $look  Resolved look.
	 * @return string HTML.
	 */
	public static function pins_html( array $spots, array $look ) {
		$html = '';
		foreach ( $spots as $i => $spot ) {
			$name = '' !== $spot['label'] ? $spot['label'] : $spot['card']['name'];
			/* translators: 1: pin number, 2: product name */
			$label = sprintf( __( 'Product %1$d: %2$s', 'shopnook-shoppable-images' ), $i + 1, $name );
			$html .= sprintf(
				'<button type="button" class="%1$s" style="left:%2$s%%;top:%3$s%%" data-product="%4$d" data-index="%5$d" data-label="%6$s" aria-label="%7$s" aria-expanded="false">%8$s</button>',
				esc_attr( Shopnook_Pins::classes( $look ) ),
				esc_attr( self::number( $spot['x'] ) ),
				esc_attr( self::number( $spot['y'] ) ),
				(int) $spot['product'],
				(int) $i,
				esc_attr( $name ),
				esc_attr( $label ),
				Shopnook_Pins::inner( $look, $i + 1 ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in Shopnook_Pins.
			);
		}
		return $html;
	}

	/**
	 * A number with a dot as decimal separator, whatever the locale.
	 *
	 * @param float $value Number.
	 * @return string
	 */
	private static function number( $value ) {
		return rtrim( rtrim( sprintf( '%.2F', $value ), '0' ), '.' );
	}

	/**
	 * Hidden card markup the script clones when a pin opens.
	 *
	 * @param array $spots Visible pins.
	 * @param array $look  Resolved look.
	 * @return string HTML.
	 */
	public static function cards_template( array $spots, array $look ) {
		$done = array();
		$html = '';
		foreach ( $spots as $spot ) {
			if ( isset( $done[ $spot['product'] ] ) ) {
				continue;
			}
			$done[ $spot['product'] ] = true;
			$html                    .= self::card_html( $spot['card'], $look );
		}
		return '<template class="shopnook-cards">' . $html . '</template>';
	}

	/**
	 * The "Shop this look" list under an image.
	 *
	 * @param array $spots Visible pins.
	 * @param array $look  Resolved look.
	 * @return string HTML.
	 */
	public static function list_html( array $spots, array $look ) {
		$items = '';
		foreach ( $spots as $i => $spot ) {
			$items .= sprintf(
				'<li class="shopnook-list__item" data-index="%1$d"><span class="shopnook-list__num" aria-hidden="true">%2$d</span>%3$s</li>',
				(int) $i,
				(int) $i + 1,
				self::card_html( $spot['card'], $look ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in card_html().
			);
		}
		return '<div class="shopnook-list"><p class="shopnook-list__title">' . esc_html__( 'Shop this look', 'shopnook-shoppable-images' ) . '</p><ol class="shopnook-list__items">' . $items . '</ol></div>';
	}

	/**
	 * One product card.
	 *
	 * @param array $card Card data from Shopnook_Products::card().
	 * @param array $look Resolved look.
	 * @return string HTML.
	 */
	public static function card_html( array $card, array $look ) {
		$fields = array_flip( $look['card_fields'] );
		$url    = esc_url( $card['url'] );
		$html   = '<div class="shopnook-card" data-product="' . (int) $card['id'] . '">';

		if ( isset( $fields['image'] ) ) {
			$html .= sprintf(
				'<a class="shopnook-card__media" href="%1$s" data-shopnook-click tabindex="-1" aria-hidden="true"><img src="%2$s"%3$s sizes="96px" alt="" loading="lazy" decoding="async"></a>',
				$url,
				esc_url( $card['image']['src'] ),
				$card['image']['srcset'] ? ' srcset="' . esc_attr( $card['image']['srcset'] ) . '"' : ''
			);
		}

		$html .= '<div class="shopnook-card__body">';
		if ( isset( $fields['sale'] ) && $card['onSale'] ) {
			$html .= '<span class="shopnook-card__badge">' . esc_html__( 'Sale', 'shopnook-shoppable-images' ) . '</span>';
		}
		if ( isset( $fields['name'] ) ) {
			$html .= '<a class="shopnook-card__name" href="' . $url . '" data-shopnook-click>' . esc_html( $card['name'] ) . '</a>';
		}
		if ( isset( $fields['rating'] ) && $card['rating'] > 0 ) {
			$html .= sprintf(
				'<span class="shopnook-card__rating"><span class="shopnook-card__stars" role="img" aria-label="%1$s" style="--shopnook-rating:%2$s%%"></span><span class="shopnook-card__reviews">(%3$s)</span></span>',
				/* translators: %s: average rating */
				esc_attr( sprintf( __( 'Rated %s out of 5', 'shopnook-shoppable-images' ), number_format_i18n( $card['rating'], 1 ) ) ),
				esc_attr( self::number( $card['rating'] * 20 ) ),
				esc_html( number_format_i18n( $card['reviews'] ) )
			);
		}
		if ( isset( $fields['price'] ) && '' !== $card['price'] ) {
			$html .= '<span class="shopnook-card__price">' . wp_kses_post( $card['price'] ) . '</span>';
		}
		if ( $card['stockText'] ) {
			$html .= '<span class="shopnook-card__stock shopnook-card__stock--' . esc_attr( $card['stock'] ) . '">' . esc_html( $card['stockText'] ) . '</span>';
		}
		if ( isset( $fields['excerpt'] ) && '' !== $card['excerpt'] ) {
			$html .= '<p class="shopnook-card__excerpt">' . esc_html( $card['excerpt'] ) . '</p>';
		}

		$actions = '';
		if ( isset( $fields['cart'] ) ) {
			if ( 'ajax' === $card['cart']['mode'] ) {
				$text     = '' !== $look['cart_text'] ? $look['cart_text'] : $card['cart']['text'];
				$actions .= '<button type="button" class="shopnook-card__cart" data-shopnook-cart="' . (int) $card['id'] . '">' . esc_html( $text ) . '</button>';
			} elseif ( 'link' === $card['cart']['mode'] ) {
				$actions .= sprintf(
					'<a class="shopnook-card__cart" href="%1$s" data-shopnook-click%2$s>%3$s</a>',
					esc_url( $card['cart']['url'] ),
					! empty( $card['cart']['external'] ) ? ' rel="nofollow sponsored"' : '',
					esc_html( $card['cart']['text'] )
				);
			}
		}
		if ( isset( $fields['view'] ) ) {
			$text     = '' !== $look['view_text'] ? $look['view_text'] : __( 'View product', 'shopnook-shoppable-images' );
			$actions .= '<a class="shopnook-card__view" href="' . $url . '" data-shopnook-click>' . esc_html( $text ) . '</a>';
		}
		if ( $actions ) {
			$html .= '<div class="shopnook-card__actions">' . $actions . '</div>';
		}

		return $html . '</div></div>';
	}
}
