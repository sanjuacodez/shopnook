/**
 * Shopnook pin editor, shared by the block editor and the Media Library.
 *
 * Exposes window.shopnook.EditorModal (pins and look in one modal),
 * window.shopnook.LookControls and window.shopnook.products (a small cache).
 * Plain wp.element code, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var c = wp.components;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var _n = wp.i18n._n;
	var apiFetch = wp.apiFetch;
	var config = window.shopnookEditor || {};

	/* ---------------------------------------------------------------- */
	/* Product cache                                                     */
	/* ---------------------------------------------------------------- */

	var cache = {};
	var listeners = [];

	var products = {
		get: function ( id ) {
			return cache[ id ];
		},
		remember: function ( list ) {
			( list || [] ).forEach( function ( p ) {
				cache[ p.id ] = p;
			} );
			listeners.slice().forEach( function ( fn ) {
				fn();
			} );
		},
		load: function ( ids ) {
			var missing = ( ids || [] ).filter( function ( id ) {
				return id && ! cache[ id ];
			} );
			if ( ! missing.length ) {
				return Promise.resolve();
			}
			return apiFetch( {
				path:
					'/shopnook/v1/products?' +
					missing
						.map( function ( id ) {
							return 'include[]=' + encodeURIComponent( id );
						} )
						.join( '&' ),
			} ).then( products.remember, function () {} );
		},
		search: function ( term ) {
			return apiFetch( {
				path: '/shopnook/v1/products?search=' + encodeURIComponent( term || '' ),
			} ).then( function ( list ) {
				products.remember( list );
				return list;
			} );
		},
		subscribe: function ( fn ) {
			listeners.push( fn );
			return function () {
				listeners = listeners.filter( function ( l ) {
					return l !== fn;
				} );
			};
		},
	};

	/** Re-renders a component when products load. */
	function useProducts() {
		var state = useState( 0 );
		useEffect( function () {
			return products.subscribe( function () {
				state[ 1 ]( function ( n ) {
					return n + 1;
				} );
			} );
		}, [] );
	}

	function uid() {
		return Math.random().toString( 36 ).slice( 2, 10 );
	}

	function clamp( n ) {
		return Math.round( Math.min( 100, Math.max( 0, n ) ) * 100 ) / 100;
	}

	/* ---------------------------------------------------------------- */
	/* Product picker                                                    */
	/* ---------------------------------------------------------------- */

	function ProductSummary( props ) {
		var p = props.product;
		return el(
			'span',
			{ className: 'shopnook-pe-product' },
			p.image ? el( 'img', { src: p.image, alt: '', width: 40, height: 40 } ) : null,
			el(
				'span',
				{ className: 'shopnook-pe-product__text' },
				el( 'strong', null, p.name ),
				el(
					'span',
					null,
					[ p.price, p.sku ? 'SKU ' + p.sku : '', 'outofstock' === p.stock ? __( 'Out of stock', 'shopnook-shoppable-images' ) : '', 'publish' !== p.status ? __( 'Not published', 'shopnook-shoppable-images' ) : '' ]
						.filter( Boolean )
						.join( ' · ' )
				)
			)
		);
	}

	function ProductPicker( props ) {
		useProducts();
		var selected = props.value ? products.get( props.value ) : null;
		var editingState = useState( ! props.value );
		var editing = editingState[ 0 ];
		var termState = useState( '' );
		var resultsState = useState( null );
		var loadingState = useState( false );
		var timer = useRef();

		useEffect(
			function () {
				if ( ! editing ) {
					return;
				}
				clearTimeout( timer.current );
				loadingState[ 1 ]( true );
				timer.current = setTimeout( function () {
					products.search( termState[ 0 ] ).then(
						function ( list ) {
							resultsState[ 1 ]( list );
							loadingState[ 1 ]( false );
						},
						function () {
							resultsState[ 1 ]( [] );
							loadingState[ 1 ]( false );
						}
					);
				}, 250 );
				return function () {
					clearTimeout( timer.current );
				};
			},
			[ termState[ 0 ], editing ]
		);

		if ( props.value && ! editing ) {
			return el(
				'div',
				{ className: 'shopnook-pe-picker shopnook-pe-picker--selected' },
				/* translators: %d: product ID */
				selected ? el( ProductSummary, { product: selected } ) : el( 'span', { className: 'shopnook-pe-muted' }, sprintf( __( 'Product #%d', 'shopnook-shoppable-images' ), props.value ) ),
				el(
					c.Button,
					{
						variant: 'link',
						onClick: function () {
							editingState[ 1 ]( true );
						},
					},
					__( 'Change', 'shopnook-shoppable-images' )
				)
			);
		}

		var results = resultsState[ 0 ];
		return el(
			'div',
			{ className: 'shopnook-pe-picker' },
			el( c.SearchControl, {
				__nextHasNoMarginBottom: true,
				label: __( 'Search products', 'shopnook-shoppable-images' ),
				placeholder: __( 'Search by name or SKU', 'shopnook-shoppable-images' ),
				value: termState[ 0 ],
				onChange: termState[ 1 ],
				autoFocus: !! props.autoFocus,
			} ),
			el(
				'div',
				{ className: 'shopnook-pe-results', 'aria-live': 'polite', 'aria-busy': loadingState[ 0 ] ? 'true' : 'false' },
				results && ! results.length && ! loadingState[ 0 ] ? el( 'p', { className: 'shopnook-pe-muted' }, __( 'No products found.', 'shopnook-shoppable-images' ) ) : null,
				results
					? el(
							'ul',
							null,
							results.map( function ( p ) {
								return el(
									'li',
									{ key: p.id },
									el(
										'button',
										{
											type: 'button',
											className: 'shopnook-pe-result' + ( p.id === props.value ? ' is-selected' : '' ),
											onClick: function () {
												props.onChange( p.id );
												editingState[ 1 ]( false );
												termState[ 1 ]( '' );
											},
										},
										el( ProductSummary, { product: p } )
									)
								);
							} )
					  )
					: el( c.Spinner )
			),
			props.value
				? el(
						c.Button,
						{
							variant: 'link',
							onClick: function () {
								editingState[ 1 ]( false );
							},
						},
						__( 'Cancel', 'shopnook-shoppable-images' )
				  )
				: null
		);
	}

	/* ---------------------------------------------------------------- */
	/* Pin canvas                                                        */
	/* ---------------------------------------------------------------- */

	function pinInner( look, n ) {
		var style = look.pin_style;
		if ( 'number' === style ) {
			return el( 'span', { className: 'shopnook-pin__num' }, n );
		}
		if ( 'custom' === style && config.pinImageUrl && look.pin_image === config.defaults.pin_image ) {
			return el( 'img', { className: 'shopnook-pin__img', src: config.pinImageUrl, alt: '', draggable: false } );
		}
		if ( config.pinIcons && config.pinIcons[ style ] ) {
			return el( 'span', { className: 'shopnook-pin__icon', dangerouslySetInnerHTML: { __html: config.pinIcons[ style ] } } );
		}
		return el( 'span', { className: 'shopnook-pin__dot' } );
	}

	function PinEditor( props ) {
		useProducts();
		var hotspots = props.hotspots;
		var look = props.look || config.defaults;
		var selectedState = useState( hotspots.length ? hotspots[ 0 ].id : null );
		var selected = selectedState[ 0 ];
		var stage = useRef();
		var drag = useRef( null );
		var justDragged = useRef( false );

		useEffect( function () {
			products.load(
				hotspots.map( function ( h ) {
					return h.product;
				} )
			);
		}, [] );

		var list = useRef();
		useEffect(
			function () {
				var row = list.current && list.current.querySelector( '.shopnook-pe__row.is-selected' );
				if ( row && row.scrollIntoView ) {
					row.scrollIntoView( { block: 'nearest' } );
				}
			},
			[ selected, hotspots.length ]
		);

		function update( id, patch ) {
			props.onChange(
				hotspots.map( function ( h ) {
					return h.id === id ? Object.assign( {}, h, patch ) : h;
				} )
			);
		}

		function remove( id ) {
			props.onChange(
				hotspots.filter( function ( h ) {
					return h.id !== id;
				} )
			);
		}

		function position( event ) {
			var rect = stage.current.getBoundingClientRect();
			return {
				x: clamp( ( ( event.clientX - rect.left ) / rect.width ) * 100 ),
				y: clamp( ( ( event.clientY - rect.top ) / rect.height ) * 100 ),
			};
		}

		function onStageClick( event ) {
			if ( justDragged.current || event.target.closest( '.shopnook-pin' ) ) {
				justDragged.current = false;
				return;
			}
			var pos = position( event );
			var spot = { id: uid(), x: pos.x, y: pos.y, product: 0, label: '' };
			props.onChange( hotspots.concat( [ spot ] ) );
			selectedState[ 1 ]( spot.id );
		}

		function onPointerDown( event, spot ) {
			if ( 0 !== event.button ) {
				return;
			}
			event.preventDefault();
			event.currentTarget.setPointerCapture( event.pointerId );
			drag.current = { id: spot.id, x: event.clientX, y: event.clientY, moved: false };
			selectedState[ 1 ]( spot.id );
			event.currentTarget.focus();
		}

		function onPointerMove( event ) {
			var d = drag.current;
			if ( ! d ) {
				return;
			}
			if ( ! d.moved && Math.abs( event.clientX - d.x ) + Math.abs( event.clientY - d.y ) < 4 ) {
				return;
			}
			d.moved = true;
			update( d.id, position( event ) );
		}

		function onPointerUp() {
			if ( drag.current && drag.current.moved ) {
				justDragged.current = true;
			}
			drag.current = null;
		}

		function onPinKey( event, spot ) {
			var step = event.shiftKey ? 5 : 0.5;
			var moves = { ArrowLeft: [ -step, 0 ], ArrowRight: [ step, 0 ], ArrowUp: [ 0, -step ], ArrowDown: [ 0, step ] };
			if ( moves[ event.key ] ) {
				event.preventDefault();
				update( spot.id, { x: clamp( spot.x + moves[ event.key ][ 0 ] ), y: clamp( spot.y + moves[ event.key ][ 1 ] ) } );
			} else if ( 'Delete' === event.key || 'Backspace' === event.key ) {
				event.preventDefault();
				remove( spot.id );
			}
		}

		var pinClass = 'shopnook-pin shopnook-pin--' + look.pin_style + ' shopnook-pin--' + look.pin_size;
		var styleVars = { '--shopnook-pin-fg': look.pin_color, '--shopnook-pin-bg': look.pin_bg };

		return el(
			'div',
			{ className: 'shopnook-pe' },
			el(
				'div',
				{ className: 'shopnook-pe__canvas' },
				el(
					'div',
					{
						className: 'shopnook-pe__stage',
						ref: stage,
						style: styleVars,
						onClick: onStageClick,
						onPointerMove: onPointerMove,
						onPointerUp: onPointerUp,
						onPointerCancel: onPointerUp,
					},
					el( 'img', { src: props.imageUrl, alt: '', draggable: false } ),
					hotspots.map( function ( spot, i ) {
						var product = spot.product ? products.get( spot.product ) : null;
						return el(
							'button',
							{
								key: spot.id,
								type: 'button',
								className: pinClass + ( spot.id === selected ? ' is-selected' : '' ) + ( spot.product ? '' : ' is-empty' ),
								style: { left: spot.x + '%', top: spot.y + '%' },
								'aria-label': sprintf(
									/* translators: 1: pin number, 2: product name */
									__( 'Pin %1$d: %2$s. Use arrow keys to move, Delete to remove.', 'shopnook-shoppable-images' ),
									i + 1,
									product ? product.name : __( 'no product yet', 'shopnook-shoppable-images' )
								),
								onPointerDown: function ( event ) {
									onPointerDown( event, spot );
								},
								onKeyDown: function ( event ) {
									onPinKey( event, spot );
								},
								onFocus: function () {
									selectedState[ 1 ]( spot.id );
								},
							},
							pinInner( look, i + 1 )
						);
					} )
				),
				el( 'p', { className: 'shopnook-pe-help' }, __( 'Click the image to add a pin. Drag pins to move them, or select one and use the arrow keys.', 'shopnook-shoppable-images' ) )
			),
			el(
				'div',
				{ className: 'shopnook-pe__side' },
				hotspots.length
					? el(
							'ol',
							{ className: 'shopnook-pe__list', ref: list },
							hotspots.map( function ( spot, i ) {
								var isSelected = spot.id === selected;
								return el(
									'li',
									{
										key: spot.id,
										className: 'shopnook-pe__row' + ( isSelected ? ' is-selected' : '' ),
										onFocusCapture: function () {
											selectedState[ 1 ]( spot.id );
										},
										onClick: function () {
											selectedState[ 1 ]( spot.id );
										},
									},
									el(
										'div',
										{ className: 'shopnook-pe__row-head' },
										el( 'span', { className: 'shopnook-pe__num', 'aria-hidden': 'true' }, i + 1 ),
										/* translators: %d: pin number */
										el( 'span', { className: 'shopnook-pe__row-title' }, sprintf( __( 'Pin %d', 'shopnook-shoppable-images' ), i + 1 ) ),
										el( c.Button, {
											icon: 'trash',
											/* translators: %d: pin number */
											label: sprintf( __( 'Remove pin %d', 'shopnook-shoppable-images' ), i + 1 ),
											size: 'small',
											isDestructive: true,
											onClick: function ( event ) {
												event.stopPropagation();
												remove( spot.id );
											},
										} )
									),
									el( ProductPicker, {
										value: spot.product,
										autoFocus: isSelected && ! spot.product,
										onChange: function ( id ) {
											update( spot.id, { product: id } );
										},
									} ),
									isSelected
										? el( c.TextControl, {
												__nextHasNoMarginBottom: true,
												__next40pxDefaultSize: true,
												label: __( 'Label (optional)', 'shopnook-shoppable-images' ),
												help: __( 'Shown when hovering the pin. Defaults to the product name.', 'shopnook-shoppable-images' ),
												value: spot.label || '',
												onChange: function ( value ) {
													update( spot.id, { label: value } );
												},
										  } )
										: null
								);
							} )
					  )
					: el( 'div', { className: 'shopnook-pe__empty' }, el( 'p', null, __( 'No pins yet.', 'shopnook-shoppable-images' ) ), el( 'p', null, __( 'Click anywhere on the image to place the first one.', 'shopnook-shoppable-images' ) ) )
			)
		);
	}

	/* ---------------------------------------------------------------- */
	/* Look controls                                                     */
	/* ---------------------------------------------------------------- */

	function withDefault( map, current ) {
		var options = [
			{
				value: '',
				/* translators: %s: the inherited value */
				label: sprintf( __( 'Default (%s)', 'shopnook-shoppable-images' ), map[ current ] || current ),
			},
		];
		Object.keys( map ).forEach( function ( key ) {
			options.push( { value: key, label: map[ key ] } );
		} );
		return options;
	}

	function ColorField( props ) {
		var id = useRef( 'shopnook-color-' + uid() );
		var value = props.value || '';
		return el(
			'div',
			{ className: 'shopnook-pe-color components-base-control' },
			el( 'label', { className: 'components-base-control__label', htmlFor: id.current }, props.label ),
			el(
				'div',
				{ className: 'shopnook-pe-color__row' },
				el( 'input', {
					id: id.current,
					type: 'color',
					value: value || props.inherited || '#000000',
					onChange: function ( event ) {
						props.onChange( event.target.value );
					},
				} ),
				el( 'code', null, value || props.inherited ),
				value
					? el(
							c.Button,
							{
								variant: 'link',
								onClick: function () {
									props.onChange( '' );
								},
							},
							__( 'Use default', 'shopnook-shoppable-images' )
					  )
					: el( 'span', { className: 'shopnook-pe-muted' }, __( 'Default', 'shopnook-shoppable-images' ) )
			)
		);
	}

	function PinImageField( props ) {
		function choose() {
			var frame = wp.media( {
				title: __( 'Choose a pin image', 'shopnook-shoppable-images' ),
				library: { type: 'image' },
				button: { text: __( 'Use as pin', 'shopnook-shoppable-images' ) },
				multiple: false,
			} );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				var url = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
				props.onChange( a.id, url );
			} );
			frame.open();
		}
		return el(
			'div',
			{ className: 'shopnook-pe-pinimage components-base-control' },
			props.url ? el( 'img', { src: props.url, alt: '', width: 32, height: 32 } ) : null,
			wp.media
				? el( c.Button, { variant: 'secondary', onClick: choose }, props.value ? __( 'Change pin image', 'shopnook-shoppable-images' ) : __( 'Choose pin image', 'shopnook-shoppable-images' ) )
				: null
		);
	}

	/**
	 * Optional overrides of the site-wide look. Empty values inherit.
	 *
	 * props.value     overrides object
	 * props.inherited the look these overrides sit on (site settings, or site settings + Media Library)
	 * props.sections  which groups to show: pins, display, card
	 */
	function LookControls( props ) {
		var value = props.value || {};
		var inherited = props.inherited || config.defaults;
		var sections = props.sections || [ 'pins', 'display', 'card' ];
		var pinUrlState = useState( '' );

		function set( key, v ) {
			var next = Object.assign( {}, value );
			if ( '' === v || null === v || undefined === v ) {
				delete next[ key ];
			} else {
				next[ key ] = v;
			}
			props.onChange( next );
		}

		function select( key, label, map ) {
			return el( c.SelectControl, {
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				label: label,
				value: value[ key ] || '',
				options: withDefault( map, inherited[ key ] ),
				onChange: function ( v ) {
					set( key, v );
				},
			} );
		}

		var parts = [];
		if ( sections.indexOf( 'pins' ) > -1 ) {
			var style = value.pin_style || inherited.pin_style;
			parts.push(
				el(
					'div',
					{ key: 'pins', className: 'shopnook-pe-look__group' },
					select( 'pin_style', __( 'Pin style', 'shopnook-shoppable-images' ), config.pinStyles ),
					'custom' === style && value.pin_style
						? el( PinImageField, {
								value: value.pin_image,
								url: pinUrlState[ 0 ],
								onChange: function ( id, url ) {
									pinUrlState[ 1 ]( url );
									set( 'pin_image', id );
								},
						  } )
						: null,
					select( 'pin_size', __( 'Pin size', 'shopnook-shoppable-images' ), config.sizes ),
					select( 'pin_visibility', __( 'Show pins', 'shopnook-shoppable-images' ), config.visibility ),
					el( ColorField, {
						label: __( 'Pin background', 'shopnook-shoppable-images' ),
						value: value.pin_bg,
						inherited: inherited.pin_bg,
						onChange: function ( v ) {
							set( 'pin_bg', v );
						},
					} ),
					el( ColorField, {
						label: __( 'Pin icon color', 'shopnook-shoppable-images' ),
						value: value.pin_color,
						inherited: inherited.pin_color,
						onChange: function ( v ) {
							set( 'pin_color', v );
						},
					} )
				)
			);
		}
		if ( sections.indexOf( 'display' ) > -1 ) {
			parts.push(
				el(
					'div',
					{ key: 'display', className: 'shopnook-pe-look__group' },
					select( 'display', __( 'When a pin is clicked, show', 'shopnook-shoppable-images' ), config.displays ),
					select( 'mobile', __( 'On phones', 'shopnook-shoppable-images' ), config.mobiles )
				)
			);
		}
		if ( sections.indexOf( 'card' ) > -1 ) {
			var custom = Array.isArray( value.card_fields );
			var fields = custom ? value.card_fields : inherited.card_fields;
			parts.push(
				el(
					'div',
					{ key: 'card', className: 'shopnook-pe-look__group' },
					el( c.ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Choose what the product card shows', 'shopnook-shoppable-images' ),
						help: custom ? null : __( 'Using the default card.', 'shopnook-shoppable-images' ),
						checked: custom,
						onChange: function ( on ) {
							set( 'card_fields', on ? inherited.card_fields.slice() : null );
						},
					} ),
					custom
						? el(
								'fieldset',
								{ className: 'shopnook-pe-fields' },
								el( 'legend', { className: 'screen-reader-text' }, __( 'Card fields', 'shopnook-shoppable-images' ) ),
								Object.keys( config.cardFields ).map( function ( key ) {
									return el( c.CheckboxControl, {
										__nextHasNoMarginBottom: true,
										key: key,
										label: config.cardFields[ key ],
										checked: fields.indexOf( key ) > -1,
										onChange: function ( on ) {
											var next = Object.keys( config.cardFields ).filter( function ( k ) {
												return k === key ? on : fields.indexOf( k ) > -1;
											} );
											set( 'card_fields', next );
										},
									} );
								} )
						  )
						: null,
					el( c.TextControl, {
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
						label: __( 'Add to cart button text', 'shopnook-shoppable-images' ),
						placeholder: inherited.cart_text || __( 'Add to cart', 'shopnook-shoppable-images' ),
						value: value.cart_text || '',
						onChange: function ( v ) {
							set( 'cart_text', v );
						},
					} ),
					el( c.TextControl, {
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
						label: __( 'View product link text', 'shopnook-shoppable-images' ),
						placeholder: inherited.view_text || __( 'View product', 'shopnook-shoppable-images' ),
						value: value.view_text || '',
						onChange: function ( v ) {
							set( 'view_text', v );
						},
					} ),
					el( ColorField, {
						label: __( 'Button color', 'shopnook-shoppable-images' ),
						value: value.accent,
						inherited: inherited.accent,
						onChange: function ( v ) {
							set( 'accent', v );
						},
					} )
				)
			);
		}
		return el( 'div', { className: 'shopnook-pe-look' }, parts );
	}

	/* ---------------------------------------------------------------- */
	/* Modal                                                             */
	/* ---------------------------------------------------------------- */

	/**
	 * props.title, props.imageUrl, props.hotspots, props.overrides (or undefined to hide the Look tab),
	 * props.inherited, props.onSave( { hotspots, overrides } ) → Promise|void, props.onClose, props.intro
	 */
	function EditorModal( props ) {
		var hotspotsState = useState( function () {
			return ( props.hotspots || [] ).map( function ( h ) {
				return Object.assign( { id: uid(), label: '' }, h );
			} );
		} );
		var overridesState = useState( props.overrides || {} );
		var tabState = useState( 'pins' );
		var savingState = useState( false );
		var errorState = useState( '' );
		var hasLook = undefined !== props.overrides;
		var look = Object.assign( {}, props.inherited || config.defaults, overridesState[ 0 ] );
		var empty = hotspotsState[ 0 ].filter( function ( h ) {
			return ! h.product;
		} ).length;

		function save() {
			var result = props.onSave( {
				hotspots: hotspotsState[ 0 ].filter( function ( h ) {
					return h.product;
				} ),
				overrides: overridesState[ 0 ],
			} );
			if ( result && result.then ) {
				savingState[ 1 ]( true );
				errorState[ 1 ]( '' );
				result.then(
					function () {
						savingState[ 1 ]( false );
						props.onClose();
					},
					function ( error ) {
						savingState[ 1 ]( false );
						errorState[ 1 ]( ( error && error.message ) || __( 'Could not save. Please try again.', 'shopnook-shoppable-images' ) );
					}
				);
			} else {
				props.onClose();
			}
		}

		var body =
			'look' === tabState[ 0 ]
				? el( LookControls, {
						value: overridesState[ 0 ],
						inherited: props.inherited,
						onChange: overridesState[ 1 ],
				  } )
				: el( PinEditor, {
						imageUrl: props.imageUrl,
						hotspots: hotspotsState[ 0 ],
						look: look,
						onChange: hotspotsState[ 1 ],
				  } );

		return el(
			c.Modal,
			{
				title: props.title,
				onRequestClose: props.onClose,
				className: 'shopnook-modal',
				overlayClassName: 'shopnook-modal-overlay',
				size: 'large',
				shouldCloseOnClickOutside: false,
			},
			props.intro ? el( 'p', { className: 'shopnook-modal__intro' }, props.intro ) : null,
			hasLook
				? el(
						'div',
						{ className: 'shopnook-modal__tabs', role: 'tablist' },
						[
							[ 'pins', __( 'Pins', 'shopnook-shoppable-images' ) ],
							[ 'look', __( 'Look & display', 'shopnook-shoppable-images' ) ],
						].map( function ( tab ) {
							return el(
								'button',
								{
									key: tab[ 0 ],
									type: 'button',
									role: 'tab',
									'aria-selected': tabState[ 0 ] === tab[ 0 ] ? 'true' : 'false',
									className: 'shopnook-modal__tab' + ( tabState[ 0 ] === tab[ 0 ] ? ' is-active' : '' ),
									onClick: function () {
										tabState[ 1 ]( tab[ 0 ] );
									},
								},
								tab[ 1 ]
							);
						} )
				  )
				: null,
			el( 'div', { className: 'shopnook-modal__body' }, body ),
			errorState[ 0 ] ? el( c.Notice, { status: 'error', isDismissible: false }, errorState[ 0 ] ) : null,
			el(
				'div',
				{ className: 'shopnook-modal__footer' },
				empty
					? el(
							'span',
							{ className: 'shopnook-pe-muted' },
							/* translators: %d: number of pins */
							sprintf( _n( '%d pin without a product will not be saved.', '%d pins without a product will not be saved.', empty, 'shopnook-shoppable-images' ), empty )
					  )
					: el( 'span' ),
				el(
					'div',
					{ className: 'shopnook-modal__buttons' },
					el( c.Button, { variant: 'tertiary', onClick: props.onClose }, __( 'Cancel', 'shopnook-shoppable-images' ) ),
					el( c.Button, { variant: 'primary', isBusy: savingState[ 0 ], disabled: savingState[ 0 ], onClick: save }, props.saveLabel || __( 'Save', 'shopnook-shoppable-images' ) )
				)
			)
		);
	}

	window.shopnook = Object.assign( window.shopnook || {}, {
		EditorModal: EditorModal,
		LookControls: LookControls,
		ProductPicker: ProductPicker,
		products: products,
		config: config,
		Fragment: Fragment,
	} );
} )( window.wp );
