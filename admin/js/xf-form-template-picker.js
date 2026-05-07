( function () {
	'use strict';

	var sidebar = document.getElementById( 'xf-tpl-sidebar' );
	var grid    = document.getElementById( 'xf-tpl-grid' );

	if ( ! sidebar || ! grid ) {
		return;
	}

	var cards = Array.prototype.slice.call( grid.querySelectorAll( '.xf-tpl-card' ) );

	function filterByCategory( cat ) {
		cards.forEach( function ( card ) {
			if ( cat === 'all' ) {
				card.style.display = '';
			} else {
				var cats = ( card.getAttribute( 'data-categories' ) || '' ).split( ' ' );
				card.style.display = cats.indexOf( cat ) !== -1 ? '' : 'none';
			}
		} );
	}

	sidebar.addEventListener( 'click', function ( e ) {
		var target = e.target.closest( '.xf-tpl-cat' );
		if ( ! target ) return;

		sidebar.querySelectorAll( '.xf-tpl-cat' ).forEach( function ( el ) {
			el.classList.remove( 'active' );
		} );
		target.classList.add( 'active' );

		filterByCategory( target.getAttribute( 'data-cat' ) || 'all' );
	} );

	// Keyboard accessibility for category items.
	sidebar.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' || e.key === ' ' ) {
			var target = e.target.closest( '.xf-tpl-cat' );
			if ( target ) {
				e.preventDefault();
				target.click();
			}
		}
	} );
}() );
