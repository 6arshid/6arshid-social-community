/* global ARSHID6SOCIALEng */
( function () {
	'use strict';

	const cfg = window.ARSHID6SOCIALEng || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( cfg.enabled && cfg.enabled.hashtags )              window.ARSHID6SOCIALHashtags    && window.ARSHID6SOCIALHashtags.init( cfg );
		if ( cfg.enabled && cfg.enabled.tag_friends )           window.ARSHID6SOCIALMentions    && window.ARSHID6SOCIALMentions.init( cfg );
		if ( cfg.enabled && cfg.enabled.bookmarks )             window.ARSHID6SOCIALBookmarks   && window.ARSHID6SOCIALBookmarks.init( cfg );
		if ( cfg.enabled && cfg.enabled.sticky_posts )          window.ARSHID6SOCIALSticky      && window.ARSHID6SOCIALSticky.init( cfg );
		if ( cfg.enabled && cfg.enabled.share_posts )           window.ARSHID6SOCIALShare       && window.ARSHID6SOCIALShare.init( cfg );
		if ( cfg.enabled && cfg.enabled.polls )                 window.ARSHID6SOCIALPolls       && window.ARSHID6SOCIALPolls.init( cfg );
		if ( cfg.enabled && cfg.enabled.comments_gifs )         window.ARSHID6SOCIALGifs        && window.ARSHID6SOCIALGifs.init( cfg );
		if ( cfg.enabled && cfg.enabled.comments_attachments )  window.ARSHID6SOCIALCommentAtt  && window.ARSHID6SOCIALCommentAtt.init( cfg );
		if ( cfg.enabled && cfg.enabled.messages_attachments )  window.ARSHID6SOCIALMsgAtt      && window.ARSHID6SOCIALMsgAtt.init( cfg );
		if ( cfg.enabled && cfg.enabled.social_share_external ) window.ARSHID6SOCIALExtShare    && window.ARSHID6SOCIALExtShare.init( cfg );
	} );

	// Re-bind after AJAX activity loads — modules using event delegation ignore duplicate calls.
	document.addEventListener( 'ARSHID6SOCIAL:activity:loaded', function ( e ) {
		const root = ( e.detail && e.detail.container ) || document;
		if ( cfg.enabled && cfg.enabled.hashtags )              window.ARSHID6SOCIALHashtags    && window.ARSHID6SOCIALHashtags.bindIn( root, cfg );
		if ( cfg.enabled && cfg.enabled.tag_friends )           window.ARSHID6SOCIALMentions    && window.ARSHID6SOCIALMentions.bindIn( root, cfg );
		if ( cfg.enabled && cfg.enabled.bookmarks )             window.ARSHID6SOCIALBookmarks   && window.ARSHID6SOCIALBookmarks.bindIn( root, cfg );
		if ( cfg.enabled && cfg.enabled.sticky_posts )          window.ARSHID6SOCIALSticky      && window.ARSHID6SOCIALSticky.bindIn( root, cfg );
		if ( cfg.enabled && cfg.enabled.share_posts )           window.ARSHID6SOCIALShare       && window.ARSHID6SOCIALShare.bindIn( root, cfg );
		if ( cfg.enabled && cfg.enabled.polls )                 window.ARSHID6SOCIALPolls       && window.ARSHID6SOCIALPolls.bindIn( root, cfg );
		if ( cfg.enabled && cfg.enabled.comments_gifs )         window.ARSHID6SOCIALGifs        && window.ARSHID6SOCIALGifs.bindIn( root, cfg );
		if ( cfg.enabled && cfg.enabled.comments_attachments )  window.ARSHID6SOCIALCommentAtt  && window.ARSHID6SOCIALCommentAtt.bindIn( root, cfg );
		if ( cfg.enabled && cfg.enabled.messages_attachments )  window.ARSHID6SOCIALMsgAtt      && window.ARSHID6SOCIALMsgAtt.bindIn( root, cfg );
		if ( cfg.enabled && cfg.enabled.social_share_external ) window.ARSHID6SOCIALExtShare    && window.ARSHID6SOCIALExtShare.bindIn( root, cfg );
	} );
} )();
