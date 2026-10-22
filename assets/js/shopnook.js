/**
 * Shopnook front end: pins, product cards, add to cart, gallery popups,
 * stories carousel and anonymous analytics. No dependencies.
 */
( function () {
	'use strict';

	var cfg = window.shopnookConfig || {};
	var i18n = cfg.i18n || {};
	var phone = window.matchMedia( '(max-width: 600px)' );
	var uid = 0;

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	function make( tag, className, attrs ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		Object.keys( attrs || {} ).forEach( function ( key ) {
			node.setAttribute( key, attrs[ key ] );
		} );
		return node;
	}

	function settingsOf( root ) {
		if ( ! root._shopnook ) {
			try {
				root._shopnook = JSON.parse( root.getAttribute( 'data-shopnook' ) || '{}' );
			} catch ( e ) {
				root._shopnook = {};
			}
			root._shopnook.src = root._shopnook.src || { s: 1, a: 0, p: 0 };
		}
		return root._shopnook;
	}

	/** Where an interaction came from: the root's source, with the photo's attachment for galleries. */
	function sourceOf( node ) {
		var holder = node.closest( '[data-shopnook-a]' );
		var popup = node.closest( '.shopnook-pop, .shopnook-drawer, .shopnook-sheet, .shopnook-lightbox' );
		var root = node.closest( '.shopnook' ) || ( popup && popup._shopnookRoot );
		var src = root ? Object.assign( {}, settingsOf( root ).src ) : { s: 1, a: 0, p: 0 };
		if ( holder ) {
			src.a = parseInt( holder.getAttribute( 'data-shopnook-a' ), 10 ) || 0;
		}
		return src;
	}

	function closeIcon() {
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';
	}

	function arrowIcon( dir ) {
		return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' + ( 'prev' === dir ? 'M15 18l-6-6 6-6' : 'M9 18l6-6-6-6' ) + '"/></svg>';
	}

	function focusables( container ) {
		return Array.prototype.filter.call( container.querySelectorAll( 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])' ), function ( n ) {
			return n.offsetParent !== null || n === document.activeElement;
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Analytics                                                           */
	/* ------------------------------------------------------------------ */

	var queue = [];
	var flushTimer = null;

	function canTrack() {
		if ( ! cfg.track || navigator.webdriver ) {
			return false;
		}
		// WP Consent API: only count when statistics cookies are allowed.
		if ( 'function' === typeof window.wp_has_consent && ! window.wp_has_consent( 'statistics' ) ) {
			return false;
		}
		return true;
	}

	function track( event, src, product ) {
		if ( ! canTrack() || ! cfg.events ) {
			return;
		}
		queue.push( { e: event, s: src.s, a: src.a, p: src.p, pr: product || 0 } );
		clearTimeout( flushTimer );
		if ( queue.length >= 20 ) {
			flush();
		} else {
			flushTimer = setTimeout( flush, 4000 );
		}
	}

	function flush() {
		clearTimeout( flushTimer );
		while ( queue.length ) {
			var body = JSON.stringify( { events: queue.splice( 0, 50 ) } );
			var sent = false;
			if ( navigator.sendBeacon ) {
				try {
					sent = navigator.sendBeacon( cfg.events, new Blob( [ body ], { type: 'text/plain' } ) );
				} catch ( e ) {
					sent = false;
				}
			}
			if ( ! sent && window.fetch ) {
				window.fetch( cfg.events, { method: 'POST', body: body, keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'application/json' } } ).catch( function () {} );
			}
		}
	}

	document.addEventListener( 'visibilitychange', function () {
		if ( 'hidden' === document.visibilityState ) {
			flush();
		}
	} );
	window.addEventListener( 'pagehide', flush );

	/** Remembers the product and image for sales credit (last click wins). */
	function credit( product, src ) {
		if ( ! canTrack() || ! cfg.cookie ) {
			return;
		}
		var now = Math.floor( Date.now() / 1000 );
		var match = document.cookie.match( new RegExp( '(?:^|; )' + cfg.cookie + '=([^;]*)' ) );
		var list = ( match ? decodeURIComponent( match[ 1 ] ) : '' ).split( '_' ).filter( function ( entry ) {
			return entry && entry.split( '.' )[ 0 ] !== String( product );
		} );
		list.unshift( [ product, src.s, src.a, src.p, now ].join( '.' ) );
		document.cookie =
			cfg.cookie + '=' + list.slice( 0, 20 ).join( '_' ) + '; path=/; max-age=' + ( cfg.creditDays || 7 ) * 86400 + '; SameSite=Lax' + ( 'https:' === location.protocol ? '; Secure' : '' );
	}

	var seen = typeof WeakSet === 'function' ? new WeakSet() : null;
	var viewObserver =
		'IntersectionObserver' in window
			? new IntersectionObserver(
					function ( entries ) {
						entries.forEach( function ( entry ) {
							if ( ! entry.isIntersecting || ( seen && seen.has( entry.target ) ) ) {
								return;
							}
							if ( seen ) {
								seen.add( entry.target );
							}
							viewObserver.unobserve( entry.target );
							track( 'view', sourceOf( entry.target ), 0 );
						} );
					},
					{ threshold: 0.5 }
			  )
			: null;

	function watchView( node ) {
		if ( viewObserver ) {
			viewObserver.observe( node );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Cards and add to cart                                               */
	/* ------------------------------------------------------------------ */

	function cardFor( root, product ) {
		var template = root.querySelector( 'template.shopnook-cards' );
		var card = template && template.content.querySelector( '.shopnook-card[data-product="' + product + '"]' );
		return card ? card.cloneNode( true ) : null;
	}

	function applyFragments( fragments ) {
		Object.keys( fragments || {} ).forEach( function ( selector ) {
			document.querySelectorAll( selector ).forEach( function ( node ) {
				var holder = document.createElement( 'div' );
				holder.innerHTML = fragments[ selector ];
				if ( holder.firstElementChild ) {
					node.replaceWith( holder.firstElementChild );
				}
			} );
		} );
	}

	function addToCart( button ) {
		if ( button.disabled ) {
			return;
		}
		var product = button.getAttribute( 'data-shopnook-cart' );
		var src = sourceOf( button );
		var label = button.textContent;
		var data = new FormData();
		data.append( 'product_id', product );
		data.append( 'quantity', '1' );

		button.disabled = true;
		button.classList.add( 'is-loading' );
		button.textContent = i18n.adding || label;

		window
			.fetch( cfg.addToCart, { method: 'POST', body: data, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || json.error ) {
					if ( json && json.product_url ) {
						window.location.href = json.product_url;
						return;
					}
					throw new Error( 'add_to_cart' );
				}
				track( 'cart', src, product );
				credit( product, src );
				flush();

				if ( cfg.redirect && cfg.cartUrl ) {
					window.location.href = cfg.cartUrl;
					return;
				}

				button.classList.remove( 'is-loading' );
				button.classList.add( 'is-added' );
				button.textContent = i18n.added || label;
				setTimeout( function () {
					button.disabled = false;
					button.textContent = label;
					button.classList.remove( 'is-added' );
				}, 2500 );

				var actions = button.parentNode;
				if ( cfg.cartUrl && ! actions.querySelector( '.shopnook-card__viewcart' ) ) {
					var link = make( 'a', 'shopnook-card__viewcart', { href: cfg.cartUrl } );
					link.textContent = i18n.viewCart || 'View cart';
					actions.appendChild( link );
				}

				// Let the theme's mini cart update: classic themes listen on jQuery, block themes on this event.
				applyFragments( json.fragments );
				// No button argument: WooCommerce would add its own "View cart" link next to it.
				if ( window.jQuery ) {
					window.jQuery( document.body ).trigger( 'added_to_cart', [ json.fragments, json.cart_hash ] );
				}
				document.body.dispatchEvent( new CustomEvent( 'wc-blocks_added_to_cart', { bubbles: true, cancelable: true } ) );
			} )
			.catch( function () {
				button.disabled = false;
				button.classList.remove( 'is-loading' );
				button.textContent = label;
				var error = button.parentNode.querySelector( '.shopnook-card__error' );
				if ( ! error ) {
					error = make( 'p', 'shopnook-card__error', { role: 'alert' } );
					button.parentNode.appendChild( error );
				}
				error.textContent = i18n.error || '';
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var cart = event.target.closest( '[data-shopnook-cart]' );
		if ( cart ) {
			event.preventDefault();
			addToCart( cart );
			return;
		}
		var link = event.target.closest( '[data-shopnook-click]' );
		if ( link ) {
			var card = link.closest( '.shopnook-card' );
			var product = card ? card.getAttribute( 'data-product' ) : 0;
			if ( product ) {
				var src = sourceOf( link );
				track( 'click', src, product );
				credit( product, src );
				flush();
			}
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Opening a product: tooltip, panel, drawer, sheet                    */
	/* ------------------------------------------------------------------ */

	var active = null;

	function close( returnFocus ) {
		if ( ! active ) {
			return;
		}
		var current = active;
		active = null;
		current.pin.setAttribute( 'aria-expanded', 'false' );
		current.pin.classList.remove( 'is-active' );
		current.teardown();
		if ( returnFocus && document.contains( current.pin ) ) {
			current.pin.focus( { preventScroll: true } );
		}
	}

	function modeFor( settings ) {
		var mode = settings.display || 'tooltip';
		if ( phone.matches ) {
			if ( 'sheet' === settings.mobile ) {
				return 'sheet';
			}
			if ( 'panel' === settings.mobile ) {
				return 'panel';
			}
		}
		return mode;
	}

	function shell( kind, card, labelText ) {
		var id = 'shopnook-' + kind + '-' + ++uid;
		var box = make( 'div', 'shopnook-' + kind, { id: id, role: 'dialog', 'aria-label': labelText } );
		var closeButton = make( 'button', 'shopnook-close', { type: 'button', 'aria-label': i18n.close || 'Close' } );
		closeButton.innerHTML = closeIcon();
		closeButton.addEventListener( 'click', function () {
			close( true );
		} );
		box.appendChild( closeButton );
		box.appendChild( card );
		return box;
	}

	function placeTooltip( box, pin ) {
		var rect = pin.getBoundingClientRect();
		var margin = 12;
		var width = box.offsetWidth;
		var height = box.offsetHeight;
		var vw = document.documentElement.clientWidth;
		var vh = window.innerHeight;
		var left = rect.left + rect.width / 2 - width / 2;
		left = Math.max( margin, Math.min( left, vw - width - margin ) );
		var below = rect.bottom + 10;
		var top = below + height <= vh - margin || rect.top - height - 10 < margin ? below : rect.top - height - 10;
		box.classList.toggle( 'is-above', top < rect.top );
		box.style.left = left + window.scrollX + 'px';
		box.style.top = top + window.scrollY + 'px';
		box.style.setProperty( '--shopnook-arrow', rect.left + rect.width / 2 - left + 'px' );
	}

	function openProduct( pin, root, options ) {
		options = options || {};
		var settings = settingsOf( root );
		var product = pin.getAttribute( 'data-product' );

		if ( active && active.pin === pin ) {
			close( true );
			return;
		}
		close( false );

		var src = sourceOf( pin );
		track( 'open', src, product );

		var mode = options.mode || modeFor( settings );
		if ( 'list' === mode && ! root.querySelector( '.shopnook-list' ) ) {
			mode = 'panel';
		}
		if ( 'list' === mode ) {
			highlightList( root, pin.getAttribute( 'data-index' ), true );
			return;
		}

		var card = cardFor( root, product );
		if ( ! card ) {
			return;
		}
		var name = pin.getAttribute( 'data-label' ) || '';
		var box = shell( { tooltip: 'pop', panel: 'inline', drawer: 'drawer', sheet: 'sheet' }[ mode ] || 'pop', card, name );
		box._shopnookRoot = root;
		box.setAttribute( 'data-shopnook-a', String( src.a ) );
		box.style.cssText = root.getAttribute( 'style' ) || '';
		var teardown;

		if ( 'tooltip' === mode ) {
			box.style.position = 'absolute';
			document.body.appendChild( box );
			placeTooltip( box, pin );
			var reposition = function () {
				placeTooltip( box, pin );
			};
			var outside = function ( event ) {
				if ( ! box.contains( event.target ) && ! pin.contains( event.target ) ) {
					close( false );
				}
			};
			window.addEventListener( 'resize', reposition );
			setTimeout( function () {
				document.addEventListener( 'pointerdown', outside );
			} );
			teardown = function () {
				window.removeEventListener( 'resize', reposition );
				document.removeEventListener( 'pointerdown', outside );
				box.remove();
			};
		} else if ( 'panel' === mode ) {
			var anchor = root.querySelector( '.shopnook-stories' ) || root.querySelector( '.shopnook-stage' );
			anchor.parentNode.insertBefore( box, anchor.nextSibling );
			box.setAttribute( 'role', 'region' );
			teardown = function () {
				box.remove();
			};
		} else {
			// Drawer and bottom sheet are modal.
			var backdrop = make( 'div', 'shopnook-backdrop' );
			box.setAttribute( 'aria-modal', 'true' );
			backdrop.addEventListener( 'click', function () {
				close( true );
			} );
			document.body.appendChild( backdrop );
			document.body.appendChild( box );
			document.documentElement.classList.add( 'shopnook-locked' );
			var trap = function ( event ) {
				if ( 'Tab' !== event.key ) {
					return;
				}
				var items = focusables( box );
				if ( ! items.length ) {
					return;
				}
				var first = items[ 0 ];
				var last = items[ items.length - 1 ];
				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			};
			box.addEventListener( 'keydown', trap );
			requestAnimationFrame( function () {
				backdrop.classList.add( 'is-open' );
				box.classList.add( 'is-open' );
			} );
			teardown = function () {
				document.documentElement.classList.remove( 'shopnook-locked' );
				backdrop.remove();
				box.remove();
			};
		}

		pin.setAttribute( 'aria-expanded', 'true' );
		pin.setAttribute( 'aria-controls', box.id );
		pin.classList.add( 'is-active' );
		active = { pin: pin, box: box, mode: mode, teardown: teardown };

		var focusTarget = box.querySelector( '.shopnook-card__name, .shopnook-card__cart, .shopnook-card__view' ) || box.querySelector( '.shopnook-close' );
		// Modal views always take focus; the tooltip and panel only when opened from the keyboard.
		if ( focusTarget && ( ( 'drawer' === mode || 'sheet' === mode ) || options.keyboard ) ) {
			focusTarget.focus( { preventScroll: true } );
		}
	}

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && active ) {
			close( true );
		}
	} );

	/* ------------------------------------------------------------------ */
	/* "Shop this look" list                                               */
	/* ------------------------------------------------------------------ */

	function highlightList( root, index, scroll ) {
		root.querySelectorAll( '.shopnook-list__item' ).forEach( function ( item ) {
			var on = item.getAttribute( 'data-index' ) === String( index );
			item.classList.toggle( 'is-active', on );
			if ( on && scroll ) {
				item.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
			}
		} );
		root.querySelectorAll( '.shopnook-pin' ).forEach( function ( pin ) {
			pin.classList.toggle( 'is-active', pin.getAttribute( 'data-index' ) === String( index ) );
		} );
	}

	function bindList( root ) {
		root.addEventListener( 'pointerover', function ( event ) {
			var item = event.target.closest( '.shopnook-list__item, .shopnook-pin' );
			if ( item && root.contains( item ) ) {
				highlightList( root, item.getAttribute( 'data-index' ), false );
			}
		} );
		root.addEventListener( 'pointerleave', function () {
			highlightList( root, -1, false );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Shoppable images                                                    */
	/* ------------------------------------------------------------------ */

	function bindPins( root, container ) {
		container.addEventListener( 'click', function ( event ) {
			var pin = event.target.closest( '.shopnook-pin' );
			if ( ! pin || ! container.contains( pin ) ) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			openProduct( pin, root, { keyboard: 0 === event.detail } );
		} );
	}

	function initImage( root ) {
		bindPins( root, root );
		if ( root.querySelector( '.shopnook-list' ) ) {
			bindList( root );
		}
		var stage = root.querySelector( '.shopnook-stage' );
		if ( stage ) {
			watchView( stage );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Stories carousel                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Places pins on a cover-cropped image. The crop is centred on the pins so
	 * as many as possible stay visible; pins in the cropped-away area are hidden.
	 */
	function placeCover( stage ) {
		var naturalW = parseFloat( stage.getAttribute( 'data-w' ) ) || 1;
		var naturalH = parseFloat( stage.getAttribute( 'data-h' ) ) || 1;
		var w = stage.clientWidth;
		var h = stage.clientHeight;
		var scale = Math.max( w / naturalW, h / naturalH );
		var dw = naturalW * scale;
		var dh = naturalH * scale;
		var pins = stage.querySelectorAll( '.shopnook-pin' );
		var sumX = 0;
		var sumY = 0;

		pins.forEach( function ( pin ) {
			if ( ! pin.hasAttribute( 'data-x' ) ) {
				pin.setAttribute( 'data-x', parseFloat( pin.style.left ) || 0 );
				pin.setAttribute( 'data-y', parseFloat( pin.style.top ) || 0 );
			}
			sumX += parseFloat( pin.getAttribute( 'data-x' ) );
			sumY += parseFloat( pin.getAttribute( 'data-y' ) );
		} );

		// object-position p% lines up p% of the image with p% of the frame.
		var fx = pins.length ? sumX / pins.length : 50;
		var fy = pins.length ? sumY / pins.length : 50;
		var img = stage.querySelector( 'img' );
		if ( img ) {
			img.style.objectPosition = fx + '% ' + fy + '%';
		}
		var ox = ( w - dw ) * ( fx / 100 );
		var oy = ( h - dh ) * ( fy / 100 );

		pins.forEach( function ( pin ) {
			var left = ox + ( parseFloat( pin.getAttribute( 'data-x' ) ) / 100 ) * dw;
			var top = oy + ( parseFloat( pin.getAttribute( 'data-y' ) ) / 100 ) * dh;
			pin.hidden = ! ( left >= 8 && left <= w - 8 && top >= 8 && top <= h - 8 );
			pin.style.left = left + 'px';
			pin.style.top = top + 'px';
		} );
	}

	function initStories( root ) {
		var track = root.querySelector( '.shopnook-stories__track' );
		var prev = root.querySelector( '.shopnook-stories__nav--prev' );
		var next = root.querySelector( '.shopnook-stories__nav--next' );
		var stages = root.querySelectorAll( '.shopnook-stage--cover' );

		stages.forEach( function ( stage ) {
			stage.setAttribute( 'data-shopnook-a', stage.getAttribute( 'data-a' ) );
			placeCover( stage );
			watchView( stage );
		} );
		if ( 'ResizeObserver' in window ) {
			var observer = new ResizeObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					placeCover( entry.target );
				} );
			} );
			stages.forEach( function ( stage ) {
				observer.observe( stage );
			} );
		}

		function update() {
			var max = track.scrollWidth - track.clientWidth - 2;
			var start = Math.abs( track.scrollLeft );
			prev.disabled = start <= 2;
			next.disabled = start >= max;
			root.classList.toggle( 'shopnook-stories--static', max <= 0 );
		}
		function step( dir ) {
			var slide = track.querySelector( '.shopnook-stories__slide' );
			var amount = slide ? slide.getBoundingClientRect().width + 12 : track.clientWidth * 0.8;
			var rtl = 'rtl' === getComputedStyle( track ).direction ? -1 : 1;
			track.scrollBy( { left: dir * amount * rtl, behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
		}
		prev.addEventListener( 'click', function () {
			step( -1 );
		} );
		next.addEventListener( 'click', function () {
			step( 1 );
		} );
		track.addEventListener(
			'scroll',
			function () {
				window.requestAnimationFrame( update );
				if ( active && 'tooltip' === active.mode ) {
					close( false );
				}
			},
			{ passive: true }
		);
		window.addEventListener( 'resize', update );
		update();

		bindPins( root, track );
	}

	/* ------------------------------------------------------------------ */
	/* Instagram and masonry popup                                         */
	/* ------------------------------------------------------------------ */

	function initGrid( root ) {
		var dataNode = root.querySelector( '.shopnook-gallery__data' );
		var items = [];
		try {
			items = JSON.parse( dataNode ? dataNode.textContent : '[]' );
		} catch ( e ) {
			items = [];
		}
		var pinTemplate = root.querySelector( 'template.shopnook-pin-template' );
		var dialog = null;
		var parts = {};
		var index = 0;
		var opener = null;

		root.querySelectorAll( '.shopnook-gallery__tile' ).forEach( function ( tile ) {
			tile.setAttribute( 'data-shopnook-a', tile.getAttribute( 'data-a' ) );
			watchView( tile );
			tile.addEventListener( 'click', function () {
				opener = tile;
				show( parseInt( tile.getAttribute( 'data-index' ), 10 ) || 0 );
			} );
		} );

		function build() {
			dialog = make( 'dialog', 'shopnook-lightbox', { 'aria-label': root.getAttribute( 'aria-label' ) || i18n.products || '' } );
			dialog._shopnookRoot = root;
			dialog.style.cssText = root.getAttribute( 'style' ) || '';
			dialog.innerHTML =
				'<div class="shopnook-lightbox__inner">' +
				'<div class="shopnook-lightbox__media">' +
				'<button type="button" class="shopnook-lightbox__nav shopnook-lightbox__nav--prev"></button>' +
				'<div class="shopnook-stage"><img alt="" decoding="async"></div>' +
				'<button type="button" class="shopnook-lightbox__nav shopnook-lightbox__nav--next"></button>' +
				'</div>' +
				'<div class="shopnook-lightbox__side">' +
				'<div class="shopnook-lightbox__head"><span class="shopnook-lightbox__counter" aria-live="polite"></span><button type="button" class="shopnook-close"></button></div>' +
				'<div class="shopnook-lightbox__caption"></div>' +
				'<p class="shopnook-lightbox__title"></p>' +
				'<ol class="shopnook-list__items shopnook-lightbox__list"></ol>' +
				'</div></div>';
			parts.stage = dialog.querySelector( '.shopnook-stage' );
			parts.img = dialog.querySelector( 'img' );
			parts.prev = dialog.querySelector( '.shopnook-lightbox__nav--prev' );
			parts.next = dialog.querySelector( '.shopnook-lightbox__nav--next' );
			parts.counter = dialog.querySelector( '.shopnook-lightbox__counter' );
			parts.caption = dialog.querySelector( '.shopnook-lightbox__caption' );
			parts.title = dialog.querySelector( '.shopnook-lightbox__title' );
			parts.list = dialog.querySelector( '.shopnook-lightbox__list' );
			parts.close = dialog.querySelector( '.shopnook-close' );

			parts.prev.innerHTML = arrowIcon( 'prev' );
			parts.prev.setAttribute( 'aria-label', i18n.previous || 'Previous' );
			parts.next.innerHTML = arrowIcon( 'next' );
			parts.next.setAttribute( 'aria-label', i18n.next || 'Next' );
			parts.close.innerHTML = closeIcon();
			parts.close.setAttribute( 'aria-label', i18n.close || 'Close' );
			parts.title.textContent = i18n.products || '';

			parts.prev.addEventListener( 'click', function () {
				show( index - 1 );
			} );
			parts.next.addEventListener( 'click', function () {
				show( index + 1 );
			} );
			parts.close.addEventListener( 'click', function () {
				dialog.close();
			} );
			dialog.addEventListener( 'click', function ( event ) {
				if ( event.target === dialog ) {
					dialog.close();
				}
				var pin = event.target.closest( '.shopnook-pin' );
				if ( pin ) {
					track( 'open', sourceOf( pin ), pin.getAttribute( 'data-product' ) );
					highlightList( dialog, pin.getAttribute( 'data-index' ), true );
				}
			} );
			dialog.addEventListener( 'keydown', function ( event ) {
				if ( event.target.closest( 'input, select, textarea' ) ) {
					return;
				}
				var rtl = 'rtl' === getComputedStyle( dialog ).direction;
				if ( 'ArrowLeft' === event.key ) {
					show( rtl ? index + 1 : index - 1 );
				} else if ( 'ArrowRight' === event.key ) {
					show( rtl ? index - 1 : index + 1 );
				}
			} );
			dialog.addEventListener( 'close', function () {
				document.documentElement.classList.remove( 'shopnook-locked' );
				if ( opener && document.contains( opener ) ) {
					opener.focus( { preventScroll: true } );
				}
			} );
			bindList( dialog );
			document.body.appendChild( dialog );
		}

		function show( i ) {
			if ( ! items.length ) {
				return;
			}
			if ( ! dialog ) {
				build();
			}
			index = ( i + items.length ) % items.length;
			var item = items[ index ];

			parts.img.src = item.src;
			if ( item.srcset ) {
				parts.img.srcset = item.srcset;
				parts.img.sizes = '(max-width: 782px) 100vw, 60vw';
			} else {
				parts.img.removeAttribute( 'srcset' );
			}
			parts.img.width = item.w;
			parts.img.height = item.h;
			parts.img.alt = item.alt || '';
			parts.stage.setAttribute( 'data-shopnook-a', String( item.a ) );
			parts.list.setAttribute( 'data-shopnook-a', String( item.a ) );
			parts.counter.textContent = ( i18n.counter || '%1$d / %2$d' ).replace( '%1$d', index + 1 ).replace( '%2$d', items.length );
			parts.caption.innerHTML = item.caption || '';
			parts.caption.hidden = ! item.caption;
			parts.prev.hidden = parts.next.hidden = items.length < 2;

			parts.stage.querySelectorAll( '.shopnook-pin' ).forEach( function ( pin ) {
				pin.remove();
			} );
			parts.list.innerHTML = '';

			( item.spots || [] ).forEach( function ( spot, n ) {
				if ( pinTemplate ) {
					var holder = document.createElement( 'div' );
					holder.innerHTML = pinTemplate.innerHTML.replace( '{n}', String( n + 1 ) );
					var pin = holder.firstElementChild;
					pin.style.left = spot.x + '%';
					pin.style.top = spot.y + '%';
					pin.setAttribute( 'data-product', spot.p );
					pin.setAttribute( 'data-index', n );
					pin.setAttribute( 'data-label', spot.label );
					pin.setAttribute( 'aria-label', ( n + 1 ) + ': ' + spot.label );
					parts.stage.appendChild( pin );
				}
				var card = cardFor( root, spot.p );
				if ( card ) {
					var li = make( 'li', 'shopnook-list__item', { 'data-index': n } );
					var num = make( 'span', 'shopnook-list__num', { 'aria-hidden': 'true' } );
					num.textContent = n + 1;
					li.appendChild( num );
					li.appendChild( card );
					parts.list.appendChild( li );
				}
			} );
			parts.title.hidden = ! parts.list.children.length;
			if ( ! parts.list.children.length ) {
				var empty = make( 'li', 'shopnook-lightbox__empty' );
				empty.textContent = i18n.none || '';
				parts.list.appendChild( empty );
			}

			if ( ! dialog.open ) {
				document.documentElement.classList.add( 'shopnook-locked' );
				dialog.showModal();
				parts.close.focus();
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                */
	/* ------------------------------------------------------------------ */

	function init( root ) {
		if ( root._shopnookReady ) {
			return;
		}
		root._shopnookReady = true;
		var layout = root.getAttribute( 'data-layout' );
		if ( 'stories' === layout ) {
			initStories( root );
		} else if ( layout ) {
			initGrid( root );
		} else {
			initImage( root );
		}
	}

	function boot() {
		document.querySelectorAll( '.shopnook[data-shopnook]' ).forEach( init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	window.shopnookInit = boot;
} )();
