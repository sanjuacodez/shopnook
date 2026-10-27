/**
 * "Tag products" in the Media Library: opens the pin editor for an image and
 * saves the pins on the attachment.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;
	var shopnook = window.shopnook;
	var root = null;
	var opener = null;

	function MediaPins( props ) {
		var dataState = useState( null );
		var errorState = useState( '' );

		useEffect( function () {
			apiFetch( { path: '/shopnook/v1/images/' + props.id } ).then( dataState[ 1 ], function ( error ) {
				errorState[ 1 ]( ( error && error.message ) || __( 'Could not load this image.', 'shopnook-shoppable-images' ) );
			} );
		}, [] );

		if ( errorState[ 0 ] ) {
			return el(
				wp.components.Modal,
				{ title: __( 'Tag products', 'shopnook-shoppable-images' ), onRequestClose: props.onClose, overlayClassName: 'shopnook-modal-overlay' },
				el( wp.components.Notice, { status: 'error', isDismissible: false }, errorState[ 0 ] )
			);
		}
		if ( ! dataState[ 0 ] ) {
			return el(
				wp.components.Modal,
				{ title: __( 'Tag products', 'shopnook-shoppable-images' ), onRequestClose: props.onClose, overlayClassName: 'shopnook-modal-overlay' },
				el( wp.components.Spinner )
			);
		}

		var data = dataState[ 0 ];
		return el( shopnook.EditorModal, {
			title: __( 'Tag products', 'shopnook-shoppable-images' ),
			intro: __( 'Pins saved here show wherever this image is shoppable. A post can still give the image its own pins in the block settings.', 'shopnook-shoppable-images' ),
			imageUrl: data.url,
			hotspots: data.hotspots,
			overrides: Array.isArray( data.overrides ) ? {} : data.overrides,
			inherited: shopnook.config.defaults,
			onClose: props.onClose,
			onSave: function ( values ) {
				return apiFetch( {
					path: '/shopnook/v1/images/' + props.id,
					method: 'POST',
					data: values,
				} ).then( function ( saved ) {
					document.querySelectorAll( '.shopnook-media-count[data-id="' + props.id + '"]' ).forEach( function ( node ) {
						node.textContent = saved.count;
					} );
				} );
			},
		} );
	}

	function close() {
		if ( root ) {
			root.unmount();
			root = null;
		}
		if ( opener && document.contains( opener ) ) {
			opener.focus();
		}
	}

	function open( id, button ) {
		var host = document.getElementById( 'shopnook-media-root' );
		if ( ! host ) {
			host = document.createElement( 'div' );
			host.id = 'shopnook-media-root';
			document.body.appendChild( host );
		}
		close();
		opener = button;
		root = wp.element.createRoot( host );
		root.render( el( MediaPins, { id: id, onClose: close } ) );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest && event.target.closest( '.shopnook-media-button' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		open( parseInt( button.getAttribute( 'data-id' ), 10 ), button );
	} );
} )( window.wp );
