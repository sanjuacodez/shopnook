<?php
/**
 * Shoppable core Gallery blocks: Instagram grid, masonry and stories layouts.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replaces the output of core Gallery blocks that have Shopnook switched on.
 *
 * Each image inside the gallery is a core Image block, so it keeps its own
 * pins (Media Library pins unless customized in the block). The gallery
 * decides the layout and the look of every pin in it.
 */
class Shopnook_Gallery {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'pre_render_block', array( __CLASS__, 'before_render' ), 10, 2 );
		add_filter( 'render_block_core/gallery', array( __CLASS__, 'render' ), 20, 2 );
	}

	/**
	 * Layouts.
	 *
	 * @return array Key => label.
	 */
	public static function layouts() {
		return array(
			'instagram' => __( 'Instagram grid', 'shopnook-shoppable-images' ),
			'masonry'   => __( 'Masonry', 'shopnook-shoppable-images' ),
			'stories'   => __( 'Stories carousel', 'shopnook-shoppable-images' ),
		);
	}

	/**
	 * The block's Shopnook settings when it is a shoppable gallery.
	 *
	 * @param array $block Parsed block.
	 * @return array|null
	 */
	private static function settings( $block ) {
		if ( empty( $block['blockName'] ) || 'core/gallery' !== $block['blockName'] ) {
			return null;
		}
		$settings = isset( $block['attrs']['shopnook'] ) && is_array( $block['attrs']['shopnook'] ) ? $block['attrs']['shopnook'] : array();
		return empty( $settings['enabled'] ) ? null : $settings;
	}

	/**
	 * Marks the start of a shoppable gallery so inner images skip their own pins.
	 *
	 * @param string|null $pre   Short-circuit output.
	 * @param array       $block Parsed block.
	 * @return string|null
	 */
	public static function before_render( $pre, $block ) {
		if ( null === $pre && null !== self::settings( $block ) ) {
			++Shopnook_Render::$in_gallery;
		}
		return $pre;
	}

	/**
	 * Builds the shoppable gallery.
	 *
	 * @param string $content Core gallery HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public static function render( $content, $block ) {
		$settings = self::settings( $block );
		if ( null === $settings ) {
			return $content;
		}
		Shopnook_Render::$in_gallery = max( 0, Shopnook_Render::$in_gallery - 1 );

		$look   = Shopnook_Settings::resolve( isset( $settings['overrides'] ) ? $settings['overrides'] : array() );
		$layout = isset( $settings['layout'] ) && isset( self::layouts()[ $settings['layout'] ] ) ? $settings['layout'] : Shopnook_Settings::get( 'gallery_layout' );
		$cols   = ! empty( $settings['columns'] ) ? min( 6, max( 1, (int) $settings['columns'] ) ) : (int) Shopnook_Settings::get( 'gallery_columns' );
		$items  = self::items( $block, $layout );
		if ( ! $items ) {
			return $content;
		}

		$post_id = Shopnook_Render::current_post_id();
		$source  = array(
			's' => 2,
			'a' => 0,
			'p' => $post_id,
		);

		$body = 'stories' === $layout ? self::stories( $items, $look ) : self::grid( $items, $layout );

		// Every product in the gallery, once, for cards.
		$all_spots = array();
		foreach ( $items as $item ) {
			$all_spots = array_merge( $all_spots, $item['spots'] );
		}
		$body .= Shopnook_Render::cards_template( $all_spots, $look );
		if ( 'stories' !== $layout ) {
			// The popup builds its pins from this template.
			$body .= '<template class="shopnook-pin-template">' . Shopnook_Pins::template( $look ) . '</template>';
			$data  = array();
			foreach ( $items as $item ) {
				$data[] = array(
					'a'       => $item['id'],
					'src'     => $item['large'][0],
					'srcset'  => $item['srcset'],
					'w'       => $item['large'][1],
					'h'       => $item['large'][2],
					'alt'     => $item['alt'],
					'caption' => $item['caption'],
					'spots'   => array_map( array( __CLASS__, 'spot_data' ), $item['spots'] ),
				);
			}
			$body .= '<script type="application/json" class="shopnook-gallery__data">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . '</script>';
		}

		$caption = '';
		if ( isset( $block['innerHTML'] ) && preg_match( '#<figcaption\b[^>]*>(.*?)</figcaption>#is', $block['innerHTML'], $m ) && '' !== trim( $m[1] ) ) {
			$caption = '<figcaption class="blocks-gallery-caption wp-element-caption">' . wp_kses_post( $m[1] ) . '</figcaption>';
		}

		// No wp-block-gallery class: core gallery CSS (flex, nested-image rules) would fight these layouts.
		$classes = array( 'shopnook-gallery', 'shopnook-gallery--' . $layout );
		if ( ! empty( $block['attrs']['align'] ) ) {
			$classes[] = 'align' . sanitize_html_class( $block['attrs']['align'] );
		}
		if ( ! empty( $block['attrs']['className'] ) ) {
			$classes = array_merge( $classes, array_map( 'sanitize_html_class', preg_split( '/\s+/', $block['attrs']['className'] ) ) );
		}
		$anchor = '';
		if ( preg_match( '#^<figure\b[^>]*\bid="([^"]+)"#i', ltrim( $content ), $m ) ) {
			$anchor = ' id="' . esc_attr( $m[1] ) . '"';
		}

		$html = sprintf(
			'<figure class="%1$s"%2$s style="--shopnook-cols:%3$d;--shopnook-gap:%4$dpx">%5$s%6$s</figure>',
			esc_attr( implode( ' ', array_filter( $classes ) ) ),
			$anchor,
			$cols,
			(int) Shopnook_Settings::get( 'gallery_gap' ),
			$body,
			$caption
		);

		$tags = new WP_HTML_Tag_Processor( $html );
		$tags->next_tag( 'figure' );
		Shopnook_Render::decorate( $tags, $look, $source );
		$tags->set_attribute( 'data-layout', $layout );

		Shopnook_Render::enqueue();
		return $tags->get_updated_html();
	}

	/**
	 * Pin data for the popup script.
	 *
	 * @param array $spot Visible pin.
	 * @return array
	 */
	public static function spot_data( $spot ) {
		return array(
			'x'     => $spot['x'],
			'y'     => $spot['y'],
			'p'     => $spot['product'],
			'label' => '' !== $spot['label'] ? $spot['label'] : $spot['card']['name'],
		);
	}

	/**
	 * Images in the gallery with their pins.
	 *
	 * @param array  $block  Parsed gallery block.
	 * @param string $layout Layout.
	 * @return array
	 */
	private static function items( $block, $layout ) {
		$items = array();
		$size  = 'stories' === $layout ? 'large' : 'medium_large';
		foreach ( isset( $block['innerBlocks'] ) ? $block['innerBlocks'] : array() as $inner ) {
			$id = isset( $inner['attrs']['id'] ) ? (int) $inner['attrs']['id'] : 0;
			if ( 'core/image' !== $inner['blockName'] || ! $id || ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			$settings = isset( $inner['attrs']['shopnook'] ) && is_array( $inner['attrs']['shopnook'] ) ? $inner['attrs']['shopnook'] : array();
			$data     = Shopnook_Images::for_block( $id, $settings );
			$large    = wp_get_attachment_image_src( $id, 'large' );
			if ( ! $large ) {
				continue;
			}

			$alt     = '';
			$caption = '';
			$html    = isset( $inner['innerHTML'] ) ? $inner['innerHTML'] : '';
			$tags    = new WP_HTML_Tag_Processor( $html );
			if ( $tags->next_tag( 'img' ) ) {
				$alt = (string) $tags->get_attribute( 'alt' );
			}
			if ( '' === $alt ) {
				$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			}
			if ( preg_match( '#<figcaption\b[^>]*>(.*?)</figcaption>#is', $html, $m ) ) {
				$caption = wp_kses_post( trim( $m[1] ) );
			}

			$items[] = array(
				'id'      => $id,
				'size'    => $size,
				'large'   => $large,
				'srcset'  => (string) wp_get_attachment_image_srcset( $id, 'large' ),
				'alt'     => trim( wp_strip_all_tags( $alt ) ),
				'caption' => $caption,
				'spots'   => Shopnook_Render::visible_spots( $data['hotspots'] ),
			);
		}
		return $items;
	}

	/**
	 * Instagram grid or masonry tiles that open the popup.
	 *
	 * @param array  $items  Images.
	 * @param string $layout Layout.
	 * @return string HTML.
	 */
	private static function grid( array $items, $layout ) {
		$icons = Shopnook_Pins::icons();
		$total = count( $items );
		$html  = '<ul class="shopnook-gallery__grid" role="list">';
		foreach ( $items as $i => $item ) {
			$count = count( $item['spots'] );
			$label = sprintf(
				/* translators: 1: photo number, 2: number of photos, 3: image description */
				__( 'Open photo %1$d of %2$d: %3$s', 'shopnook-shoppable-images' ),
				$i + 1,
				$total,
				'' !== $item['alt'] ? $item['alt'] : __( 'no description', 'shopnook-shoppable-images' )
			);
			if ( $count ) {
				/* translators: %d: number of tagged products */
				$label .= '. ' . sprintf( _n( '%d product', '%d products', $count, 'shopnook-shoppable-images' ), $count );
			}
			$badge = ( $count && Shopnook_Settings::get( 'gallery_badge' ) ) ? '<span class="shopnook-gallery__badge" aria-hidden="true">' . $icons['bag'] . '<span>' . (int) $count . '</span></span>' : '';
			$html .= sprintf(
				'<li class="shopnook-gallery__item"><button type="button" class="shopnook-gallery__tile" data-index="%1$d" data-a="%2$d" aria-label="%3$s" aria-haspopup="dialog">%4$s%5$s</button></li>',
				(int) $i,
				(int) $item['id'],
				esc_attr( $label ),
				wp_get_attachment_image(
					$item['id'],
					$item['size'],
					false,
					array(
						'alt'     => '',
						'loading' => $i < 3 ? 'eager' : 'lazy',
						'sizes'   => 'instagram' === $layout ? '(max-width: 600px) 50vw, 400px' : '(max-width: 600px) 50vw, 33vw',
					)
				),
				$badge
			);
		}
		return $html . '</ul>';
	}

	/**
	 * Stories carousel: tall slides with pins on each.
	 *
	 * @param array $items Images.
	 * @param array $look  Resolved look.
	 * @return string HTML.
	 */
	private static function stories( array $items, array $look ) {
		$total  = count( $items );
		$slides = '';
		foreach ( $items as $i => $item ) {
			$count = count( $item['spots'] );
			$meta  = '';
			if ( '' !== $item['caption'] || $count ) {
				$meta = '<div class="shopnook-stories__meta">';
				if ( '' !== $item['caption'] ) {
					$meta .= '<p class="shopnook-stories__caption">' . $item['caption'] . '</p>';
				}
				if ( $count ) {
					/* translators: %d: number of tagged products */
					$meta .= '<p class="shopnook-stories__count">' . esc_html( sprintf( _n( '%d product', '%d products', $count, 'shopnook-shoppable-images' ), $count ) ) . '</p>';
				}
				$meta .= '</div>';
			}
			$slides .= sprintf(
				'<li class="shopnook-stories__slide" aria-roledescription="%1$s" aria-label="%2$s"><div class="shopnook-stage shopnook-stage--cover" data-a="%3$d" data-w="%4$d" data-h="%5$d">%6$s%7$s</div>%8$s</li>',
				esc_attr__( 'slide', 'shopnook-shoppable-images' ),
				/* translators: 1: photo number, 2: number of photos */
				esc_attr( sprintf( __( '%1$d of %2$d', 'shopnook-shoppable-images' ), $i + 1, $total ) ),
				(int) $item['id'],
				(int) $item['large'][1],
				(int) $item['large'][2],
				wp_get_attachment_image(
					$item['id'],
					'large',
					false,
					array(
						'alt'     => $item['alt'],
						'loading' => $i < 3 ? 'eager' : 'lazy',
						'sizes'   => '(max-width: 600px) 75vw, 320px',
					)
				),
				Shopnook_Render::pins_html( $item['spots'], $look ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in pins_html().
				$meta // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Caption is kses-filtered, the count is escaped.
			);
		}

		$arrow = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="%s"/></svg>';
		return sprintf(
			'<div class="shopnook-stories" aria-roledescription="%1$s"><button type="button" class="shopnook-stories__nav shopnook-stories__nav--prev" aria-label="%2$s">%3$s</button><ul class="shopnook-stories__track" role="list">%4$s</ul><button type="button" class="shopnook-stories__nav shopnook-stories__nav--next" aria-label="%5$s">%6$s</button></div>',
			esc_attr__( 'carousel', 'shopnook-shoppable-images' ),
			esc_attr__( 'Previous slides', 'shopnook-shoppable-images' ),
			sprintf( $arrow, 'M15 18l-6-6 6-6' ),
			$slides,
			esc_attr__( 'Next slides', 'shopnook-shoppable-images' ),
			sprintf( $arrow, 'M9 18l6-6-6-6' )
		);
	}
}
