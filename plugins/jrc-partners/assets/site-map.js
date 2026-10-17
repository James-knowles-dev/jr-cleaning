/**
 * School site map: Leaflet map + building cards (affiliate-site-map.php).
 *
 * The cards are server-rendered; this adds the map and links pins and cards both ways:
 * a pin opens and scrolls to its card, a card's header flies the map to its pin.
 */
( function () {
	var el = document.getElementById( 'sm-map' );
	var cards = document.querySelectorAll( '.sm-b' );

	function open( id, scroll ) {
		cards.forEach( function ( c ) {
			var on = c.dataset.building === id;
			c.classList.toggle( 'is-active', on );
			var head = c.querySelector( '.sm-b__head' ), body = c.querySelector( '.sm-b__body' );
			if ( on ) {
				head.setAttribute( 'aria-expanded', 'true' ); body.hidden = false;
				if ( scroll ) { c.scrollIntoView( { behavior: 'smooth', block: 'nearest' } ); }
			}
		} );
	}

	// Cards work as an accordion with or without the map.
	cards.forEach( function ( c ) {
		c.querySelector( '.sm-b__head' ).addEventListener( 'click', function () {
			var head = this, body = c.querySelector( '.sm-b__body' ), wasOpen = 'true' === head.getAttribute( 'aria-expanded' );
			head.setAttribute( 'aria-expanded', wasOpen ? 'false' : 'true' ); body.hidden = wasOpen;
			if ( ! wasOpen ) { focusPin( c.dataset.building ); }
		} );
	} );

	if ( ! el || typeof L === 'undefined' ) { return; }
	var site; try { site = JSON.parse( el.dataset.site ); } catch ( e ) { return; }
	var pins = site.buildings.filter( function ( b ) { return b.lat !== null && b.lng !== null; } );
	if ( ! pins.length ) { return; }

	var map = L.map( el, { scrollWheelZoom: false } ).setView( [ site.view.lat, site.view.lng ], site.view.zoom );
	L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
	} ).addTo( map );

	var markers = {};
	site.buildings.forEach( function ( b, i ) {
		if ( b.lat === null || b.lng === null ) { return; }
		var icon = L.divIcon( {
			className: 'sm-pin' + ( b.soon ? '' : ' sm-pin--idle' ),
			html: '<span>' + ( i + 1 ) + '</span>',
			iconSize: [ 30, 30 ], iconAnchor: [ 15, 15 ]
		} );
		var m = L.marker( [ b.lat, b.lng ], { icon: icon, title: b.name, alt: b.name, keyboard: true } ).addTo( map );
		m.bindTooltip( b.name, { direction: 'top', offset: [ 0, -14 ] } );
		m.on( 'click', function () { setActive( b.id ); open( b.id, true ); } );
		markers[ b.id ] = m;
	} );

	if ( pins.length > 1 ) {
		map.fitBounds( L.latLngBounds( pins.map( function ( b ) { return [ b.lat, b.lng ]; } ) ), { padding: [ 40, 40 ], maxZoom: 18 } );
	}

	function setActive( id ) {
		Object.keys( markers ).forEach( function ( k ) {
			var node = markers[ k ].getElement();
			if ( node ) { node.classList.toggle( 'is-active', k === id ); }
		} );
	}
	function focusPin( id ) {
		var m = markers[ id ];
		if ( ! m ) { return; }
		setActive( id );
		map.flyTo( m.getLatLng(), Math.max( map.getZoom(), 18 ), { duration: 0.6 } );
		m.openTooltip();
	}

	// Start with the first card's building highlighted.
	if ( cards[ 0 ] ) { setActive( cards[ 0 ].dataset.building ); }
	// Leaflet measures its box on load; the tab can render before the layout settles.
	setTimeout( function () { map.invalidateSize(); }, 250 );
} )();
