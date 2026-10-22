<?php
/**
 * Settings screen.
 *
 * @package Shopnook
 */

defined( 'ABSPATH' ) || exit;

$shopnook_s    = Shopnook_Settings::all();
$shopnook_tabs = array(
	'pins'      => __( 'Pins', 'shopnook' ),
	'display'   => __( 'Display & card', 'shopnook' ),
	'galleries' => __( 'Galleries', 'shopnook' ),
	'tracking'  => __( 'Tracking', 'shopnook' ),
);
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab switch only.
$shopnook_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'pins';
if ( ! isset( $shopnook_tabs[ $shopnook_tab ] ) ) {
	$shopnook_tab = 'pins';
}

$shopnook_name = function ( $key ) {
	return Shopnook_Settings::OPTION . '[' . $key . ']';
};

/**
 * Prints an on/off switch.
 */
$shopnook_toggle = function ( $key, $label, $help = '' ) use ( $shopnook_s, $shopnook_name ) {
	?>
	<div class="shopnook-field">
		<label class="shopnook-switch">
			<input type="checkbox" name="<?php echo esc_attr( $shopnook_name( $key ) ); ?>" value="1" <?php checked( ! empty( $shopnook_s[ $key ] ) ); ?>>
			<span class="shopnook-switch__track" aria-hidden="true"></span>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php if ( $help ) : ?>
			<p class="shopnook-help"><?php echo esc_html( $help ); ?></p>
		<?php endif; ?>
	</div>
	<?php
};

/**
 * Prints a segmented radio group.
 */
$shopnook_segments = function ( $key, $label, $options, $help = '' ) use ( $shopnook_s, $shopnook_name ) {
	?>
	<fieldset class="shopnook-field">
		<legend class="shopnook-field__label"><?php echo esc_html( $label ); ?></legend>
		<div class="shopnook-segments">
			<?php foreach ( $options as $value => $text ) : ?>
				<label>
					<input type="radio" name="<?php echo esc_attr( $shopnook_name( $key ) ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( (string) $shopnook_s[ $key ], (string) $value ); ?>>
					<span><?php echo esc_html( $text ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php if ( $help ) : ?>
			<p class="shopnook-help"><?php echo esc_html( $help ); ?></p>
		<?php endif; ?>
	</fieldset>
	<?php
};

/**
 * Prints a colour input.
 */
$shopnook_color = function ( $key, $label ) use ( $shopnook_s, $shopnook_name ) {
	$id = 'shopnook-' . str_replace( '_', '-', $key );
	?>
	<div class="shopnook-field shopnook-field--color">
		<label class="shopnook-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<input type="color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $shopnook_name( $key ) ); ?>" value="<?php echo esc_attr( $shopnook_s[ $key ] ); ?>" data-preview="<?php echo esc_attr( $key ); ?>">
	</div>
	<?php
};

/**
 * Prints a text or number input.
 */
$shopnook_input = function ( $key, $label, $type = 'text', $attrs = array(), $help = '' ) use ( $shopnook_s, $shopnook_name ) {
	$id    = 'shopnook-' . str_replace( '_', '-', $key );
	$extra = '';
	foreach ( $attrs as $attr => $value ) {
		$extra .= ' ' . esc_attr( $attr ) . '="' . esc_attr( $value ) . '"';
	}
	?>
	<div class="shopnook-field">
		<label class="shopnook-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<input type="<?php echo esc_attr( $type ); ?>" class="<?php echo 'number' === $type ? 'small-text' : 'regular-text'; ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $shopnook_name( $key ) ); ?>" value="<?php echo esc_attr( $shopnook_s[ $key ] ); ?>"<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>>
		<?php if ( $help ) : ?>
			<p class="shopnook-help"><?php echo esc_html( $help ); ?></p>
		<?php endif; ?>
	</div>
	<?php
};

$shopnook_look = Shopnook_Settings::resolve();
?>
<div class="wrap shopnook-admin">
	<div class="shopnook-admin__head">
		<h1><?php esc_html_e( 'Shopnook Settings', 'shopnook' ); ?></h1>
	</div>
	<p class="shopnook-intro"><?php esc_html_e( 'These are the defaults for every shoppable image. An image can change them in the Media Library, and a block can change them again for one post.', 'shopnook' ); ?></p>

	<nav class="nav-tab-wrapper shopnook-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'shopnook' ); ?>">
		<a class="nav-tab" href="<?php echo esc_url( admin_url( 'admin.php?page=shopnook' ) ); ?>"><?php esc_html_e( 'Dashboard', 'shopnook' ); ?></a>
		<?php foreach ( $shopnook_tabs as $shopnook_key => $shopnook_label ) : ?>
			<a class="nav-tab<?php echo $shopnook_key === $shopnook_tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=shopnook-settings&tab=' . $shopnook_key ) ); ?>" <?php echo $shopnook_key === $shopnook_tab ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $shopnook_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="options.php" class="shopnook-settings shopnook-settings--<?php echo esc_attr( $shopnook_tab ); ?>">
		<?php settings_fields( 'shopnook_settings' ); ?>
		<input type="hidden" name="<?php echo esc_attr( $shopnook_name( '_tab' ) ); ?>" value="<?php echo esc_attr( $shopnook_tab ); ?>">

		<?php if ( 'pins' === $shopnook_tab ) : ?>
			<div class="shopnook-settings__layout">
				<div class="shopnook-card-box">
					<fieldset class="shopnook-field">
						<legend class="shopnook-field__label"><?php esc_html_e( 'Pin style', 'shopnook' ); ?></legend>
						<div class="shopnook-pin-choices">
							<?php foreach ( Shopnook_Pins::styles() as $shopnook_style => $shopnook_label ) : ?>
								<?php
								$shopnook_demo              = $shopnook_look;
								$shopnook_demo['pin_style'] = $shopnook_style;
								$shopnook_demo['pin_size']  = 'md';
								?>
								<label class="shopnook-pin-choice">
									<input type="radio" name="<?php echo esc_attr( $shopnook_name( 'pin_style' ) ); ?>" value="<?php echo esc_attr( $shopnook_style ); ?>" <?php checked( $shopnook_s['pin_style'], $shopnook_style ); ?>>
									<span class="shopnook-pin-choice__box" style="<?php echo esc_attr( Shopnook_Pins::style_vars( $shopnook_look ) ); ?>">
										<span class="<?php echo esc_attr( Shopnook_Pins::classes( $shopnook_demo ) ); ?>" aria-hidden="true">
											<?php
											if ( 'custom' === $shopnook_style && ! $shopnook_s['pin_image'] ) {
												echo '<span class="dashicons dashicons-format-image"></span>';
											} else {
												echo Shopnook_Pins::inner( $shopnook_demo, 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
											}
											?>
										</span>
									</span>
									<span class="shopnook-pin-choice__label"><?php echo esc_html( $shopnook_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<div class="shopnook-field shopnook-pin-image" <?php echo 'custom' === $shopnook_s['pin_style'] ? '' : 'hidden'; ?>>
						<span class="shopnook-field__label"><?php esc_html_e( 'Custom pin image', 'shopnook' ); ?></span>
						<input type="hidden" name="<?php echo esc_attr( $shopnook_name( 'pin_image' ) ); ?>" value="<?php echo esc_attr( $shopnook_s['pin_image'] ); ?>">
						<span class="shopnook-pin-image__preview"><?php echo $shopnook_s['pin_image'] ? wp_get_attachment_image( $shopnook_s['pin_image'], array( 48, 48 ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup. ?></span>
						<button type="button" class="button shopnook-pin-image__choose"><?php esc_html_e( 'Choose image', 'shopnook' ); ?></button>
						<button type="button" class="button-link shopnook-pin-image__remove" <?php echo $shopnook_s['pin_image'] ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'shopnook' ); ?></button>
						<p class="shopnook-help"><?php esc_html_e( 'A square PNG, WebP or SVG with a transparent background works best, for example your logo or a small icon.', 'shopnook' ); ?></p>
					</div>

					<?php
					$shopnook_segments(
						'pin_size',
						__( 'Size', 'shopnook' ),
						array(
							'sm' => __( 'Small', 'shopnook' ),
							'md' => __( 'Medium', 'shopnook' ),
							'lg' => __( 'Large', 'shopnook' ),
						)
					);
					?>
					<div class="shopnook-field-row">
						<?php $shopnook_color( 'pin_bg', __( 'Pin background', 'shopnook' ) ); ?>
						<?php $shopnook_color( 'pin_color', __( 'Pin icon color', 'shopnook' ) ); ?>
					</div>
					<?php
					$shopnook_segments(
						'pin_visibility',
						__( 'Show pins', 'shopnook' ),
						array(
							'always' => __( 'Always', 'shopnook' ),
							'hover'  => __( 'When hovering the image', 'shopnook' ),
						),
						__( 'On phones and tablets, pins are always shown.', 'shopnook' )
					);
					?>
				</div>

				<aside class="shopnook-card-box shopnook-preview" aria-label="<?php esc_attr_e( 'Preview', 'shopnook' ); ?>">
					<p class="shopnook-field__label"><?php esc_html_e( 'Preview', 'shopnook' ); ?></p>
					<div class="shopnook-preview__image shopnook" style="<?php echo esc_attr( Shopnook_Pins::style_vars( $shopnook_look ) ); ?>">
						<div class="shopnook-stage">
							<?php foreach ( array( array( 30, 38 ), array( 64, 58 ), array( 45, 78 ) ) as $shopnook_i => $shopnook_pos ) : ?>
								<span class="<?php echo esc_attr( Shopnook_Pins::classes( $shopnook_look ) ); ?>" style="left:<?php echo (int) $shopnook_pos[0]; ?>%;top:<?php echo (int) $shopnook_pos[1]; ?>%" data-n="<?php echo (int) $shopnook_i + 1; ?>" aria-hidden="true"><?php echo Shopnook_Pins::inner( $shopnook_look, $shopnook_i + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?></span>
							<?php endforeach; ?>
						</div>
					</div>
					<p class="shopnook-help"><?php esc_html_e( 'Updates as you change the options. Save to apply.', 'shopnook' ); ?></p>
				</aside>
			</div>

		<?php elseif ( 'display' === $shopnook_tab ) : ?>
			<div class="shopnook-settings__layout">
				<div class="shopnook-card-box">
					<fieldset class="shopnook-field">
						<legend class="shopnook-field__label"><?php esc_html_e( 'When a pin is clicked, show', 'shopnook' ); ?></legend>
						<div class="shopnook-mode-choices">
							<?php
							$shopnook_mode_help = array(
								'tooltip' => __( 'A small card next to the pin.', 'shopnook' ),
								'panel'   => __( 'The product opens in a box under the image.', 'shopnook' ),
								'drawer'  => __( 'A panel slides in from the side of the screen.', 'shopnook' ),
								'list'    => __( 'All products are listed under the image; pins highlight their product.', 'shopnook' ),
							);
							foreach ( Shopnook_Settings::displays() as $shopnook_mode => $shopnook_label ) :
								?>
								<label class="shopnook-mode-choice">
									<input type="radio" name="<?php echo esc_attr( $shopnook_name( 'display' ) ); ?>" value="<?php echo esc_attr( $shopnook_mode ); ?>" <?php checked( $shopnook_s['display'], $shopnook_mode ); ?>>
									<span class="shopnook-mode-choice__art shopnook-mode-choice__art--<?php echo esc_attr( $shopnook_mode ); ?>" aria-hidden="true"><i></i><b></b></span>
									<span class="shopnook-mode-choice__text"><strong><?php echo esc_html( $shopnook_label ); ?></strong><span><?php echo esc_html( $shopnook_mode_help[ $shopnook_mode ] ); ?></span></span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
					<?php $shopnook_segments( 'mobile', __( 'On phones', 'shopnook' ), Shopnook_Settings::mobiles(), __( 'A bottom sheet is easiest to use with one thumb.', 'shopnook' ) ); ?>

					<fieldset class="shopnook-field">
						<legend class="shopnook-field__label"><?php esc_html_e( 'The product card shows', 'shopnook' ); ?></legend>
						<div class="shopnook-checks">
							<?php foreach ( Shopnook_Settings::card_fields() as $shopnook_key => $shopnook_label ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $shopnook_name( 'card_fields' ) ); ?>[]" value="<?php echo esc_attr( $shopnook_key ); ?>" <?php checked( in_array( $shopnook_key, $shopnook_s['card_fields'], true ) ); ?>> <?php echo esc_html( $shopnook_label ); ?></label>
							<?php endforeach; ?>
						</div>
						<p class="shopnook-help"><?php esc_html_e( 'Variable and grouped products show “Select options” instead of Add to cart, and link to the product page.', 'shopnook' ); ?></p>
					</fieldset>
					<div class="shopnook-field-row">
						<?php $shopnook_input( 'cart_text', __( 'Add to cart button text', 'shopnook' ), 'text', array( 'placeholder' => __( 'Add to cart', 'shopnook' ) ) ); ?>
						<?php $shopnook_input( 'view_text', __( 'View product link text', 'shopnook' ), 'text', array( 'placeholder' => __( 'View product', 'shopnook' ) ) ); ?>
					</div>
					<?php $shopnook_color( 'accent', __( 'Button color', 'shopnook' ) ); ?>
				</div>

				<aside class="shopnook-card-box shopnook-preview" aria-label="<?php esc_attr_e( 'Card preview', 'shopnook' ); ?>">
					<p class="shopnook-field__label"><?php esc_html_e( 'Card preview', 'shopnook' ); ?></p>
					<?php
					$shopnook_sample = wc_get_products(
						array(
							'status' => 'publish',
							'limit'  => 1,
							'return' => 'ids',
						)
					);
					$shopnook_card   = $shopnook_sample ? Shopnook_Products::card( $shopnook_sample[0] ) : null;
					if ( $shopnook_card ) :
						?>
						<div class="shopnook-pop shopnook-pop--preview" style="<?php echo esc_attr( Shopnook_Pins::style_vars( $shopnook_look ) ); ?>">
							<?php echo Shopnook_Render::card_html( $shopnook_card, $shopnook_look ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in card_html(). ?>
						</div>
						<p class="shopnook-help"><?php esc_html_e( 'Shows your saved settings with one of your products.', 'shopnook' ); ?></p>
					<?php else : ?>
						<p class="shopnook-help"><?php esc_html_e( 'Add a published product to see a preview.', 'shopnook' ); ?></p>
					<?php endif; ?>
				</aside>
			</div>

		<?php elseif ( 'galleries' === $shopnook_tab ) : ?>
			<div class="shopnook-card-box">
				<p class="shopnook-help shopnook-help--lead"><?php esc_html_e( 'Turn on “Shoppable gallery” in a Gallery block’s settings. Each photo uses its own pins, and these are the defaults for the layout.', 'shopnook' ); ?></p>
				<fieldset class="shopnook-field">
					<legend class="shopnook-field__label"><?php esc_html_e( 'Default layout', 'shopnook' ); ?></legend>
					<div class="shopnook-mode-choices">
						<?php
						$shopnook_layout_help = array(
							'instagram' => __( 'Square tiles. A photo opens in a popup with its products beside it.', 'shopnook' ),
							'masonry'   => __( 'Pinterest-style columns that keep each photo’s shape. Same popup.', 'shopnook' ),
							'stories'   => __( 'Tall slides people swipe through, with pins on each slide.', 'shopnook' ),
						);
						foreach ( Shopnook_Gallery::layouts() as $shopnook_layout => $shopnook_label ) :
							?>
							<label class="shopnook-mode-choice">
								<input type="radio" name="<?php echo esc_attr( $shopnook_name( 'gallery_layout' ) ); ?>" value="<?php echo esc_attr( $shopnook_layout ); ?>" <?php checked( $shopnook_s['gallery_layout'], $shopnook_layout ); ?>>
								<span class="shopnook-mode-choice__art shopnook-mode-choice__art--<?php echo esc_attr( $shopnook_layout ); ?>" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></span>
								<span class="shopnook-mode-choice__text"><strong><?php echo esc_html( $shopnook_label ); ?></strong><span><?php echo esc_html( $shopnook_layout_help[ $shopnook_layout ] ); ?></span></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
				<div class="shopnook-field-row">
					<?php
					$shopnook_input(
						'gallery_columns',
						__( 'Columns', 'shopnook' ),
						'number',
						array(
							'min' => 1,
							'max' => 6,
						),
						__( 'For the grid and masonry layouts.', 'shopnook' )
					);
					$shopnook_input(
						'gallery_gap',
						__( 'Space between photos (px)', 'shopnook' ),
						'number',
						array(
							'min' => 0,
							'max' => 40,
						)
					);
					?>
				</div>
				<?php $shopnook_toggle( 'gallery_badge', __( 'Show the number of products on each tile', 'shopnook' ) ); ?>
			</div>

		<?php else : ?>
			<div class="shopnook-card-box">
				<?php $shopnook_toggle( 'tracking', __( 'Count views, clicks and sales', 'shopnook' ), __( 'Anonymous daily totals for the dashboard. No personal data or IP addresses are stored.', 'shopnook' ) ); ?>
				<?php
				$shopnook_input(
					'credit_days',
					__( 'Credit a sale to an image for (days)', 'shopnook' ),
					'number',
					array(
						'min' => 1,
						'max' => 90,
					),
					__( 'When someone clicks a product on a shoppable image and buys it within this many days, the sale counts for that image. The most recent click wins.', 'shopnook' )
				);
				?>
				<div class="shopnook-field">
					<label class="shopnook-field__label" for="shopnook-keep-days"><?php esc_html_e( 'Keep stats for', 'shopnook' ); ?></label>
					<select id="shopnook-keep-days" name="<?php echo esc_attr( $shopnook_name( 'keep_days' ) ); ?>">
						<?php
						$shopnook_keep = array(
							90  => __( '3 months', 'shopnook' ),
							180 => __( '6 months', 'shopnook' ),
							365 => __( '1 year', 'shopnook' ),
							730 => __( '2 years', 'shopnook' ),
							0   => __( 'Forever', 'shopnook' ),
						);
						foreach ( $shopnook_keep as $shopnook_days => $shopnook_label ) :
							?>
							<option value="<?php echo esc_attr( $shopnook_days ); ?>" <?php selected( (int) $shopnook_s['keep_days'], $shopnook_days ); ?>><?php echo esc_html( $shopnook_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php $shopnook_toggle( 'exclude_staff', __( 'Don’t count visits by logged-in staff', 'shopnook' ), __( 'Anyone who can edit posts, so your own testing doesn’t inflate the numbers.', 'shopnook' ) ); ?>
				<p class="shopnook-help">
					<?php esc_html_e( 'If a cookie consent plugin that supports the WP Consent API is active, Shopnook only counts and sets its cookie after visitors allow statistics cookies.', 'shopnook' ); ?>
				</p>
				<hr>
				<?php $shopnook_toggle( 'delete_data', __( 'Delete all Shopnook data when the plugin is deleted', 'shopnook' ), __( 'Removes settings, stats and the pins saved on images. Pins saved in blocks stay in your posts and are simply ignored.', 'shopnook' ) ); ?>
			</div>
		<?php endif; ?>

		<?php submit_button(); ?>
	</form>
</div>
