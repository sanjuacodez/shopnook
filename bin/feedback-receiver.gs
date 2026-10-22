/**
 * Plugins by Sanjay Shankar: deactivation feedback receiver (Google Apps Script).
 *
 * Paste this into the feedback spreadsheet: Extensions > Apps Script, replace
 * the code, Save, then Deploy > New deployment > type "Web app",
 * Execute as "Me", Who has access "Anyone". Copy the Web app URL (…/exec)
 * into SanjayShankar_Connect::FEEDBACK_URL.
 *
 * Each plugin's answers go to the tab named after the plugin's slug
 * ("shopnook", "likenook", "advanced-preloader", …, matched without regard to
 * case; the display name also matches). A missing tab is created.
 * An empty tab gets the default headings; a tab with its own headings is
 * filled by heading name, and missing known columns are added on the right.
 */

// Filters out random requests; it ships in public plugin code, so it is not a secret.
var TOKEN = 'ssc-feedback-1';

var HEADERS = [ 'Date', 'Plugin', 'Version', 'Reason', 'Details', 'WordPress', 'WooCommerce', 'PHP', 'Language', 'Contact name', 'Contact email' ];

// Normalised heading name => field in the request.
var ALIASES = {
	date: 'date', timestamp: 'date', time: 'date',
	plugin: 'plugin', pluginname: 'plugin',
	version: 'version', pluginversion: 'version',
	reason: 'reason',
	details: 'details', detail: 'details', comment: 'details', comments: 'details', message: 'details', feedback: 'details',
	wordpress: 'wp', wordpressversion: 'wp', wp: 'wp', wpversion: 'wp',
	woocommerce: 'wc', woocommerceversion: 'wc', wc: 'wc', wcversion: 'wc',
	php: 'php', phpversion: 'php',
	language: 'locale', locale: 'locale',
	contactname: 'name', name: 'name',
	contactemail: 'email', email: 'email', contact: 'email'
};

function doPost( e ) {
	var lock = LockService.getScriptLock();
	try {
		var data = JSON.parse( ( e && e.postData && e.postData.contents ) || '{}' );
		if ( data.token !== TOKEN ) {
			return reply( { ok: false, error: 'bad token' } );
		}

		var plugin = clean( data.plugin, 60 ) || 'Unknown';
		var slug = String( data.slug || '' ).toLowerCase().replace( /[^a-z0-9_-]/g, '' ).slice( 0, 60 );
		var values = {
			date: new Date(),
			plugin: plugin,
			version: clean( data.version, 20 ),
			reason: clean( data.reason, 120 ),
			details: clean( data.details, 2000 ),
			wp: clean( data.wp, 20 ),
			wc: clean( data.wc, 20 ),
			php: clean( data.php, 20 ),
			locale: clean( data.locale, 20 ),
			name: clean( data.name, 60 ),
			email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( String( data.email || '' ) ) ? clean( data.email, 120 ) : ''
		};

		lock.waitLock( 10000 );
		var sheet = tabFor( slug, plugin );
		var headers = headings( sheet );
		var row = headers.map( function ( h ) {
			var key = ALIASES[ normalise( h ) ];
			return key ? values[ key ] : '';
		} );
		sheet.appendRow( row );
		return reply( { ok: true } );
	} catch ( err ) {
		return reply( { ok: false, error: String( err ) } );
	} finally {
		try {
			lock.releaseLock();
		} catch ( ignore ) {}
	}
}

// A quick check in the browser: opening the Web app URL shows this.
function doGet() {
	return reply( { ok: true, service: 'Plugins by Sanjay Shankar feedback' } );
}

// The tab named after the slug (or, failing that, the display name); created if missing.
function tabFor( slug, plugin ) {
	var book = SpreadsheetApp.getActiveSpreadsheet();
	var sheets = book.getSheets();
	var wanted = [ slug, plugin.toLowerCase() ].filter( String );
	for ( var w = 0; w < wanted.length; w++ ) {
		for ( var i = 0; i < sheets.length; i++ ) {
			if ( sheets[ i ].getName().trim().toLowerCase() === wanted[ w ] ) {
				return sheets[ i ];
			}
		}
	}
	return book.insertSheet( slug || plugin );
}

// The tab's headings, adding defaults to an empty tab and missing known columns at the end.
function headings( sheet ) {
	var width = sheet.getLastColumn();
	var current = width ? sheet.getRange( 1, 1, 1, width ).getValues()[ 0 ].map( String ) : [];
	if ( ! current.filter( function ( h ) { return h.trim(); } ).length ) {
		sheet.getRange( 1, 1, 1, HEADERS.length ).setValues( [ HEADERS ] ).setFontWeight( 'bold' );
		sheet.setFrozenRows( 1 );
		return HEADERS.slice();
	}
	var have = {};
	current.forEach( function ( h ) {
		var key = ALIASES[ normalise( h ) ];
		if ( key ) {
			have[ key ] = true;
		}
	} );
	var added = HEADERS.filter( function ( h ) {
		return ! have[ ALIASES[ normalise( h ) ] ];
	} );
	if ( added.length ) {
		sheet.getRange( 1, current.length + 1, 1, added.length ).setValues( [ added ] ).setFontWeight( 'bold' );
		current = current.concat( added );
	}
	return current;
}

function normalise( heading ) {
	return String( heading ).toLowerCase().replace( /[^a-z]/g, '' );
}

// Plain text only; a leading = + - @ would make the sheet treat it as a formula.
function clean( value, max ) {
	var text = String( value == null ? '' : value ).replace( /[\u0000-\u001f]+/g, ' ' ).trim().slice( 0, max );
	return /^[=+\-@]/.test( text ) ? "'" + text : text;
}

function reply( obj ) {
	return ContentService.createTextOutput( JSON.stringify( obj ) ).setMimeType( ContentService.MimeType.JSON );
}
