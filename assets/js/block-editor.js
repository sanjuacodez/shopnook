/**
 * Shopnook controls on the core Image and Gallery blocks.
 *
 * Adds a `shopnook` attribute and a "Shopnook" sidebar panel. Nothing changes
 * in the saved markup, so the blocks stay valid with the plugin off.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var c = wp.components;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var _n = wp.i18n._n;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useSelect = wp.data.useSelect;
	var apiFetch = wp.apiFetch;
	var shopnook = window.shopnook;
	var config = shopnook.config;

	var BLOCKS = [ 'core/image', 'core/gallery' ];
	var inspectorProps = config.contentTab ? { group: 'content' } : null;

	wp.hooks.addFilter( 'blocks.registerBlockType', 'shopnook/attribute', function ( settings, name ) {
		if ( BLOCKS.indexOf( name ) === -1 ) {
			return settings;
		}
		return Object.assign( {}, settings, {
			attributes: Object.assign( {}, settings.attributes, {
				shopnook: { type: 'object', default: {} },
			} ),
		} );
	} );

	/* ---------------------------------------------------------------- */
	/* Media Library pins, shared between blocks showing the same image  */
	/* ---------------------------------------------------------------- */

	var images = {};
	var imageListeners = [];

	function loadImage( id, force ) {
		if ( ! id ) {
			return;
		}
		if ( images[ id ] && ! force ) {
			return;
		}
		images[ id ] = images[ id ] || { loading: true };
		apiFetch( { path: '/shopnook/v1/images/' + id } ).then(
			function ( data ) {
				setImage( id, data );
			},
			function () {
				setImage( id, { error: true, hotspots: [], overrides: {} } );
			}
		);
	}

	function setImage( id, data ) {
		images[ id ] = data;
		imageListeners.slice().forEach( function ( fn ) {
			fn();
		} );
	}

	function useImage( id ) {
		var tick = useState( 0 );
		useEffect( function () {
			var fn = function () {
				tick[ 1 ]( function ( n ) {
					return n + 1;
				} );
			};
			imageListeners.push( fn );
			return function () {
				imageListeners = imageListeners.filter( function ( l ) {
					return l !== fn;
				} );
			};
		}, [] );
		useEffect(
			function () {
				loadImage( id );
			},
			[ id ]
		);
		return id ? images[ id ] : null;
	}

	function countText( n ) {
		return sprintf( _n( '%d product', '%d products', n, 'shopnook-shoppable-images' ), n );
	}

	/* ---------------------------------------------------------------- */
	/* Image block panel                                                 */
	/* ---------------------------------------------------------------- */

	function ImagePanel( props ) {
		var attributes = props.attributes;
		var settings = attributes.shopnook || {};
		var id = attributes.id;
		var modalState = useState( null );

		var gallery = useSelect(
			function ( select ) {
				var store = select( 'core/block-editor' );
				var parents = store.getBlockParents( props.clientId, true );
				for ( var i = 0; i < parents.length; i++ ) {
					var parent = store.getBlock( parents[ i ] );
					if ( parent && 'core/gallery' === parent.name ) {
						return parent.attributes.shopnook && parent.attributes.shopnook.enabled ? parent : null;
					}
				}
				return null;
			},
			[ props.clientId ]
		);

		var active = !! settings.enabled || !! gallery;
		var media = useImage( active ? id : 0 );
		var custom = !! settings.custom;
		var mediaHotspots = media && media.hotspots ? media.hotspots : [];
		var hotspots = custom ? settings.hotspots || [] : mediaHotspots;
		var mediaOverrides = media && media.overrides && ! Array.isArray( media.overrides ) ? media.overrides : {};
		var inherited = Object.assign( {}, config.defaults, mediaOverrides );

		function set( patch ) {
			var next = Object.assign( {}, settings, patch );
			Object.keys( next ).forEach( function ( key ) {
				if ( undefined === next[ key ] ) {
					delete next[ key ];
				}
			} );
			props.setAttributes( { shopnook: next } );
		}

		var children = [];

		if ( gallery ) {
			children.push( el( 'p', { key: 'g', className: 'shopnook-panel-note' }, __( 'This image is part of a shoppable gallery. Its pins show in the gallery; the gallery sets the layout and look.', 'shopnook-shoppable-images' ) ) );
		} else {
			children.push(
				el( c.ToggleControl, {
					key: 'toggle',
					__nextHasNoMarginBottom: true,
					label: __( 'Shoppable image', 'shopnook-shoppable-images' ),
					help: settings.enabled ? __( 'Pins open product cards on your site.', 'shopnook-shoppable-images' ) : __( 'Off: shown as a plain image. Pins are kept.', 'shopnook-shoppable-images' ),
					checked: !! settings.enabled,
					onChange: function ( on ) {
						set( { enabled: on } );
					},
				} )
			);
		}

		if ( active ) {
			if ( ! id && ! custom ) {
				children.push(
					el( 'p', { key: 'noid', className: 'shopnook-panel-note' }, __( 'This image is not from the Media Library, so it has no shared pins. You can still add pins for this post.', 'shopnook-shoppable-images' ) )
				);
			} else if ( id && ( ! media || media.loading ) ) {
				children.push( el( c.Spinner, { key: 'spin' } ) );
			}

			var source;
			if ( custom ) {
				/* translators: %s: number of products, e.g. "3 products" */
				source = sprintf( __( 'Customized for this post: %s.', 'shopnook-shoppable-images' ), countText( hotspots.length ) );
			} else {
				/* translators: %s: number of products, e.g. "3 products" */
				source = sprintf( __( 'Using Media Library pins: %s.', 'shopnook-shoppable-images' ), countText( hotspots.length ) );
			}
			if ( custom || ( id && media && ! media.loading ) ) {
				children.push( el( 'p', { key: 'src', className: 'shopnook-panel-source' }, source ) );
			}

			var buttons = [];
			if ( custom ) {
				buttons.push(
					el(
						c.Button,
						{
							key: 'edit',
							variant: 'primary',
							onClick: function () {
								modalState[ 1 ]( 'block' );
							},
						},
						__( 'Edit pins for this post', 'shopnook-shoppable-images' )
					)
				);
				if ( id ) {
					buttons.push(
						el(
							c.Button,
							{
								key: 'reset',
								variant: 'secondary',
								onClick: function () {
									// eslint-disable-next-line no-alert
									if ( window.confirm( __( 'Remove the pins made for this post and use the Media Library pins again?', 'shopnook-shoppable-images' ) ) ) {
										set( { custom: undefined, hotspots: undefined } );
									}
								},
							},
							__( 'Use Media Library pins', 'shopnook-shoppable-images' )
						)
					);
				}
			} else {
				if ( id && media && ! media.loading ) {
					buttons.push(
						el(
							c.Button,
							{
								key: 'media',
								variant: 'primary',
								disabled: ! media.canEdit,
								onClick: function () {
									modalState[ 1 ]( 'media' );
								},
							},
							mediaHotspots.length ? __( 'Edit Media Library pins', 'shopnook-shoppable-images' ) : __( 'Tag products', 'shopnook-shoppable-images' )
						)
					);
				}
				buttons.push(
					el(
						c.Button,
						{
							key: 'customize',
							variant: id ? 'secondary' : 'primary',
							onClick: function () {
								// Starts from the Media Library pins; the block only becomes custom on Save.
								modalState[ 1 ]( 'block' );
							},
						},
						id ? __( 'Customize for this post', 'shopnook-shoppable-images' ) : __( 'Add pins', 'shopnook-shoppable-images' )
					)
				);
			}
			children.push( el( 'div', { key: 'buttons', className: 'shopnook-panel-buttons' }, buttons ) );

			if ( id && ! custom ) {
				children.push(
					el( 'p', { key: 'help', className: 'shopnook-panel-help' }, __( 'Media Library pins show everywhere this image is shoppable. Customize to give this post its own pins.', 'shopnook-shoppable-images' ) )
				);
			}
		}

		var panels = [
			el( c.PanelBody, { key: 'main', title: __( 'Shopnook', 'shopnook-shoppable-images' ), initialOpen: active, className: 'shopnook-panel' }, children ),
		];

		if ( active && ! gallery ) {
			var overrideCount = Object.keys( settings.overrides || {} ).length;
			panels.push(
				el(
					c.PanelBody,
					{
						key: 'look',
						title: overrideCount
							? /* translators: %d: number of changed settings */ sprintf( __( 'Shopnook look (%d changed)', 'shopnook-shoppable-images' ), overrideCount ) : __( 'Shopnook look', 'shopnook-shoppable-images' ),
						initialOpen: false,
						className: 'shopnook-panel',
					},
					el( 'p', { className: 'shopnook-panel-help' }, __( 'For this image in this post only. Leave on Default to follow the Media Library and site settings.', 'shopnook-shoppable-images' ) ),
					el( shopnook.LookControls, {
						value: settings.overrides || {},
						inherited: inherited,
						onChange: function ( next ) {
							set( { overrides: Object.keys( next ).length ? next : undefined } );
						},
					} )
				)
			);
		}

		var modal = null;
		if ( 'media' === modalState[ 0 ] && media ) {
			modal = el( shopnook.EditorModal, {
				title: __( 'Media Library pins', 'shopnook-shoppable-images' ),
				intro: __( 'These pins are saved on the image and show in every post where it is shoppable, except posts with their own pins.', 'shopnook-shoppable-images' ),
				imageUrl: media.url || attributes.url,
				hotspots: mediaHotspots,
				inherited: inherited,
				saveLabel: __( 'Save to Media Library', 'shopnook-shoppable-images' ),
				onClose: function () {
					modalState[ 1 ]( null );
				},
				onSave: function ( data ) {
					return apiFetch( {
						path: '/shopnook/v1/images/' + id,
						method: 'POST',
						data: { hotspots: data.hotspots, overrides: mediaOverrides },
					} ).then( function ( saved ) {
						setImage( id, saved );
					} );
				},
			} );
		} else if ( 'block' === modalState[ 0 ] ) {
			modal = el( shopnook.EditorModal, {
				title: __( 'Pins for this post', 'shopnook-shoppable-images' ),
				intro: id ? __( 'These pins replace the Media Library pins for this image in this post only.', 'shopnook-shoppable-images' ) : null,
				imageUrl: attributes.url,
				hotspots: hotspots,
				inherited: Object.assign( {}, inherited, settings.overrides || {} ),
				onClose: function () {
					modalState[ 1 ]( null );
				},
				onSave: function ( data ) {
					set( { custom: true, hotspots: data.hotspots } );
				},
			} );
		}

		return el( Fragment, null, el( InspectorControls, inspectorProps, panels ), modal );
	}

	/* ---------------------------------------------------------------- */
	/* Gallery block panel                                               */
	/* ---------------------------------------------------------------- */

	function GalleryPanel( props ) {
		var settings = props.attributes.shopnook || {};

		function set( patch ) {
			var next = Object.assign( {}, settings, patch );
			Object.keys( next ).forEach( function ( key ) {
				if ( undefined === next[ key ] ) {
					delete next[ key ];
				}
			} );
			props.setAttributes( { shopnook: next } );
		}

		var layout = settings.layout || config.gallery.layout;
		/* translators: %s: the inherited value */
		var layoutOptions = [ { value: '', label: sprintf( __( 'Default (%s)', 'shopnook-shoppable-images' ), config.layouts[ config.gallery.layout ] ) } ].concat(
			Object.keys( config.layouts ).map( function ( key ) {
				return { value: key, label: config.layouts[ key ] };
			} )
		);
		/* translators: %d: default number of columns */
		var columnOptions = [ { value: '', label: sprintf( __( 'Default (%d)', 'shopnook-shoppable-images' ), config.gallery.columns ) } ];
		for ( var i = 1; i <= 6; i++ ) {
			columnOptions.push( { value: String( i ), label: String( i ) } );
		}

		var children = [
			el( c.ToggleControl, {
				key: 'toggle',
				__nextHasNoMarginBottom: true,
				label: __( 'Shoppable gallery', 'shopnook-shoppable-images' ),
				help: settings.enabled
					? __( 'Select an image in the gallery to tag its products. Preview the post to see the layout.', 'shopnook-shoppable-images' )
					: __( 'Off: a normal gallery.', 'shopnook-shoppable-images' ),
				checked: !! settings.enabled,
				onChange: function ( on ) {
					set( { enabled: on } );
				},
			} ),
		];

		if ( settings.enabled ) {
			children.push(
				el( c.SelectControl, {
					key: 'layout',
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
					label: __( 'Layout', 'shopnook-shoppable-images' ),
					value: settings.layout || '',
					options: layoutOptions,
					onChange: function ( v ) {
						set( { layout: v || undefined } );
					},
				} )
			);
			if ( 'stories' !== layout ) {
				children.push(
					el( c.SelectControl, {
						key: 'cols',
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
						label: __( 'Columns', 'shopnook-shoppable-images' ),
						value: settings.columns ? String( settings.columns ) : '',
						options: columnOptions,
						onChange: function ( v ) {
							set( { columns: v ? parseInt( v, 10 ) : undefined } );
						},
					} )
				);
			}
		}

		var panels = [ el( c.PanelBody, { key: 'main', title: __( 'Shopnook', 'shopnook-shoppable-images' ), initialOpen: !! settings.enabled, className: 'shopnook-panel' }, children ) ];

		if ( settings.enabled ) {
			var overrideCount = Object.keys( settings.overrides || {} ).length;
			panels.push(
				el(
					c.PanelBody,
					{
						key: 'look',
						title: overrideCount
							? /* translators: %d: number of changed settings */ sprintf( __( 'Shopnook look (%d changed)', 'shopnook-shoppable-images' ), overrideCount ) : __( 'Shopnook look', 'shopnook-shoppable-images' ),
						initialOpen: false,
						className: 'shopnook-panel',
					},
					el(
						'p',
						{ className: 'shopnook-panel-help' },
						'stories' === layout
							? __( 'Applies to every photo in this gallery.', 'shopnook-shoppable-images' )
							: __( 'Applies to every photo in this gallery. The popup lists the products beside the photo.', 'shopnook-shoppable-images' )
					),
					el( shopnook.LookControls, {
						value: settings.overrides || {},
						inherited: config.defaults,
						sections: 'stories' === layout ? [ 'pins', 'display', 'card' ] : [ 'pins', 'card' ],
						onChange: function ( next ) {
							set( { overrides: Object.keys( next ).length ? next : undefined } );
						},
					} )
				)
			);
		}

		return el( InspectorControls, inspectorProps, panels );
	}

	/* ---------------------------------------------------------------- */
	/* Wiring                                                            */
	/* ---------------------------------------------------------------- */

	var withPanel = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( BLOCKS.indexOf( props.name ) === -1 ) {
				return el( BlockEdit, props );
			}
			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				props.isSelected ? el( 'core/gallery' === props.name ? GalleryPanel : ImagePanel, props ) : null
			);
		};
	}, 'withShopnookPanel' );

	wp.hooks.addFilter( 'editor.BlockEdit', 'shopnook/panel', withPanel );

	// A small bag badge on shoppable images and galleries in the canvas.
	var withBadge = wp.compose.createHigherOrderComponent( function ( BlockListBlock ) {
		return function ( props ) {
			var s = props.attributes && props.attributes.shopnook;
			if ( BLOCKS.indexOf( props.name ) === -1 || ! s || ! s.enabled ) {
				return el( BlockListBlock, props );
			}
			return el( BlockListBlock, Object.assign( {}, props, { className: ( props.className ? props.className + ' ' : '' ) + 'shopnook-is-shoppable' } ) );
		};
	}, 'withShopnookBadge' );

	wp.hooks.addFilter( 'editor.BlockListBlock', 'shopnook/badge', withBadge );
} )( window.wp );
