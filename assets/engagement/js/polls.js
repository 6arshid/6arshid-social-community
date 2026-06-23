/**
 * Poll voting — self-contained, no module dependency.
 * ARSHID6SOCIALVote(btn) is called directly via onclick on the Vote button.
 */
window.ARSHID6SOCIALVote = function ( btn ) {
	var poll = btn.closest( '.arshid6social-poll' );
	if ( ! poll ) return;

	var pollId   = poll.dataset.pollId;
	var ajaxUrl  = poll.dataset.ajax  || ( window.ARSHID6SOCIALEng && window.ARSHID6SOCIALEng.ajaxUrl ) || '/wp-admin/admin-ajax.php';
	var nonce    = poll.dataset.nonce || ( window.ARSHID6SOCIALEng && window.ARSHID6SOCIALEng.nonce )   || '';

	var checked = poll.querySelectorAll( 'input[name="poll_option"]:checked' );
	if ( ! checked.length ) return;

	btn.disabled    = true;
	btn.textContent = '…';

	var fd = new FormData();
	fd.append( 'action',  'arshid6social_poll_vote' );
	fd.append( 'nonce',   nonce );
	fd.append( 'poll_id', pollId );
	checked.forEach( function ( inp ) { fd.append( 'option_ids[]', inp.value ); } );

	fetch( ajaxUrl, { method: 'POST', body: fd } )
		.then( function ( r ) { return r.text(); } )
		.then( function ( raw ) {
			// Strip any PHP debug output before the JSON.
			var start = raw.indexOf( '{' );
			if ( start < 0 ) { reset( btn ); return; }
			var res;
			try { res = JSON.parse( raw.slice( start ) ); } catch ( e ) { reset( btn ); return; }

			if ( ! res.success ) { reset( btn ); return; }

			// ajax_vote returns {html:'...'} — inject it directly.
			if ( res.data && res.data.html ) {
				inject( poll, res.data.html );
				return;
			}

			// Fallback: fetch rendered HTML separately.
			var qs = 'action=arshid6social_poll_get_html&nonce=' + encodeURIComponent( nonce ) + '&poll_id=' + encodeURIComponent( pollId );
			fetch( ajaxUrl + '?' + qs )
				.then( function ( r2 ) { return r2.text(); } )
				.then( function ( raw2 ) {
					var s2 = raw2.indexOf( '{' );
					if ( s2 < 0 ) return;
					var r2j;
					try { r2j = JSON.parse( raw2.slice( s2 ) ); } catch ( e ) { return; }
					if ( r2j.success && r2j.data && r2j.data.html ) inject( poll, r2j.data.html );
				} )
				.catch( function () {} );
		} )
		.catch( function () { reset( btn ); } );
};

function inject( poll, html ) {
	var tmp = document.createElement( 'div' );
	tmp.innerHTML = html;
	var fresh = tmp.firstElementChild;
	if ( ! fresh ) return;
	if ( poll.parentNode ) {
		poll.parentNode.replaceChild( fresh, poll );
	} else {
		poll.outerHTML = html;
	}
	// Animate progress bars.
	requestAnimationFrame( function () {
		fresh.querySelectorAll( '.arshid6social-poll-bar-fill' ).forEach( function ( fill ) {
			var target = fill.style.width;
			fill.style.transition = 'none';
			fill.style.width = '0';
			requestAnimationFrame( function () {
				fill.style.transition = '';
				fill.style.width = target;
			} );
		} );
	} );
}

function reset( btn ) {
	if ( ! btn ) return;
	btn.disabled    = false;
	btn.textContent = 'Vote';
}

// Keep module API for engagement.js compatibility.
window.ARSHID6SOCIALPolls = { init: function () {}, bindIn: function () {} };

// Event delegation — handles both PHP-rendered polls (with onclick) and
// JS-rendered polls (without onclick, e.g. from the activity feed).
document.addEventListener( 'click', function ( e ) {
	// Vote button.
	var btn = e.target.closest( '.arshid6social-poll-vote-btn' );
	if ( btn ) { window.ARSHID6SOCIALVote( btn ); return; }

	// Ranked poll reorder buttons.
	var up = e.target.closest( '.arshid6social-poll-rank-up' );
	if ( up ) { moveOpt( up.closest( '.arshid6social-poll-option' ), -1 ); return; }
	var dn = e.target.closest( '.arshid6social-poll-rank-down' );
	if ( dn ) { moveOpt( dn.closest( '.arshid6social-poll-option' ), 1 ); }
} );

function moveOpt( opt, dir ) {
	if ( ! opt ) return;
	var p = opt.parentElement;
	var sib = dir === -1 ? opt.previousElementSibling : opt.nextElementSibling;
	if ( ! sib ) return;
	dir === -1 ? p.insertBefore( opt, sib ) : p.insertBefore( sib, opt );
	p.querySelectorAll( '.arshid6social-poll-option' ).forEach( function ( o, i ) {
		var r = o.querySelector( 'input[name="poll_rank"]' );
		if ( r ) r.value = i + 1;
	} );
}
