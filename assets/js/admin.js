/**
 * Shopnook admin: settings preview, pin image picker, trend tabs and chart tooltips.
 */
( function () {
	'use strict';

	var data = window.shopnookAdmin || {};

	/* Settings: live pin preview ------------------------------------------ */

	var form = document.querySelector( '.shopnook-settings--pins' );
	if ( form ) {
		var preview = form.querySelector( '.shopnook-preview__image' );
		var imageField = form.querySelector( '.shopnook-pin-image' );
		var imageInput = imageField.querySelector( 'input[type="hidden"]' );
		var imagePreview = imageField.querySelector( '.shopnook-pin-image__preview' );
		var removeButton = imageField.querySelector( '.shopnook-pin-image__remove' );

		var value = function ( name ) {
			var checked = form.querySelector( '[name="shopnook_settings[' + name + ']"]:checked' );
			return checked ? checked.value : '';
		};

		var inner = function ( style, n ) {
			var img = imagePreview.querySelector( 'img' );
			if ( 'number' === style ) {
				return '<span class="shopnook-pin__num">' + n + '</span>';
			}
			if ( 'custom' === style && img ) {
				return '<img class="shopnook-pin__img" src="' + img.getAttribute( 'src' ) + '" alt="">';
			}
			if ( 'pulse' === style ) {
				return '<span class="shopnook-pin__ring"></span><span class="shopnook-pin__dot"></span>';
			}
			if ( data.icons && data.icons[ style ] ) {
				return data.icons[ style ];
			}
			return '<span class="shopnook-pin__dot"></span>';
		};

		var update = function () {
			var style = value( 'pin_style' ) || 'dot';
			var size = value( 'pin_size' ) || 'md';
			imageField.hidden = 'custom' !== style;
			preview.style.setProperty( '--shopnook-pin-bg', form.querySelector( '[data-preview="pin_bg"]' ).value );
			preview.style.setProperty( '--shopnook-pin-fg', form.querySelector( '[data-preview="pin_color"]' ).value );
			form.querySelectorAll( '.shopnook-pin-choice__box' ).forEach( function ( box ) {
				box.style.setProperty( '--shopnook-pin-bg', form.querySelector( '[data-preview="pin_bg"]' ).value );
				box.style.setProperty( '--shopnook-pin-fg', form.querySelector( '[data-preview="pin_color"]' ).value );
			} );
			preview.classList.toggle( 'shopnook--pins-hover', 'hover' === value( 'pin_visibility' ) );
			preview.querySelectorAll( '.shopnook-pin' ).forEach( function ( pin ) {
				pin.className = 'shopnook-pin shopnook-pin--' + style + ' shopnook-pin--' + size;
				pin.innerHTML = inner( style, pin.getAttribute( 'data-n' ) );
			} );
		};

		form.addEventListener( 'change', update );
		form.addEventListener( 'input', update );

		imageField.querySelector( '.shopnook-pin-image__choose' ).addEventListener( 'click', function () {
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			var frame = window.wp.media( {
				title: data.i18n.choose,
				button: { text: data.i18n.use },
				library: { type: 'image' },
				multiple: false,
			} );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				var url = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
				imageInput.value = a.id;
				imagePreview.innerHTML = '';
				var img = document.createElement( 'img' );
				img.src = url;
				img.width = 48;
				img.height = 48;
				img.alt = '';
				imagePreview.appendChild( img );
				removeButton.hidden = false;
				update();
			} );
			frame.open();
		} );

		removeButton.addEventListener( 'click', function () {
			imageInput.value = '0';
			imagePreview.innerHTML = '';
			removeButton.hidden = true;
			update();
		} );

		update();
	}

	/* Dashboard: measure tabs ---------------------------------------------- */

	var tablist = document.querySelector( '.shopnook-tabs[role="tablist"]' );
	if ( tablist ) {
		var tabs = Array.prototype.slice.call( tablist.querySelectorAll( '[role="tab"]' ) );
		var select = function ( tab ) {
			tabs.forEach( function ( t ) {
				var on = t === tab;
				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				t.tabIndex = on ? 0 : -1;
				document.getElementById( t.getAttribute( 'aria-controls' ) ).hidden = ! on;
			} );
		};
		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				select( tab );
			} );
			tab.addEventListener( 'keydown', function ( event ) {
				var next = null;
				if ( 'ArrowRight' === event.key ) {
					next = tabs[ ( i + 1 ) % tabs.length ];
				} else if ( 'ArrowLeft' === event.key ) {
					next = tabs[ ( i - 1 + tabs.length ) % tabs.length ];
				} else if ( 'Home' === event.key ) {
					next = tabs[ 0 ];
				} else if ( 'End' === event.key ) {
					next = tabs[ tabs.length - 1 ];
				}
				if ( next ) {
					event.preventDefault();
					select( next );
					next.focus();
				}
			} );
		} );
	}

	/* Dashboard: chart tooltips (hover and arrow keys) ---------------------- */

	document.querySelectorAll( '.shopnook-chart' ).forEach( function ( chart ) {
		var svg = chart.querySelector( 'svg' );
		var tip = chart.querySelector( '.shopnook-chart__tip' );
		var cols = Array.prototype.slice.call( chart.querySelectorAll( '.shopnook-chart__col' ) );
		var current = -1;

		var show = function ( index ) {
			cols.forEach( function ( col, i ) {
				col.classList.toggle( 'is-active', i === index );
			} );
			current = index;
			if ( index < 0 ) {
				tip.hidden = true;
				return;
			}
			var col = cols[ index ];
			tip.querySelector( 'strong' ).textContent = col.getAttribute( 'data-value' );
			tip.querySelector( 'span' ).textContent = col.getAttribute( 'data-label' );
			tip.hidden = false;
			var box = col.querySelector( '.shopnook-chart__hit' ).getBoundingClientRect();
			var host = chart.getBoundingClientRect();
			var left = box.left - host.left + box.width / 2 - tip.offsetWidth / 2;
			tip.style.left = Math.max( 0, Math.min( left, host.width - tip.offsetWidth ) ) + 'px';
			tip.style.top = '4px';
		};

		cols.forEach( function ( col, i ) {
			col.addEventListener( 'pointerenter', function () {
				show( i );
			} );
		} );
		svg.addEventListener( 'pointerleave', function () {
			show( -1 );
		} );
		svg.addEventListener( 'focus', function () {
			show( current < 0 ? cols.length - 1 : current );
		} );
		svg.addEventListener( 'blur', function () {
			show( -1 );
		} );
		svg.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowRight' === event.key || 'ArrowLeft' === event.key ) {
				event.preventDefault();
				var next = current + ( 'ArrowRight' === event.key ? 1 : -1 );
				show( Math.max( 0, Math.min( cols.length - 1, next ) ) );
			}
		} );
	} );
} )();
