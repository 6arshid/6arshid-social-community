<?php
/**
 * Hashtag archive template — rendered at /hashtags/{slug}/.
 *
 * @package Arshid6Social\Engagement
 */

defined( 'ABSPATH' ) || exit;

/** @var object|null $hashtag */
/** @var object|null $feature */

$feature = isset( $feature ) ? $feature : arshid6social_eng()->feature( 'hashtags' );

if ( ! isset( $hashtag ) || ! $hashtag ) {
	wp_redirect( home_url() );
	exit;
}

$slug        = $hashtag->slug;
$user_id     = get_current_user_id();
$is_followed = $feature ? $feature->is_followed( (int) $hashtag->id, $user_id ) : false;
$post_count  = (int) $hashtag->post_count;

?>
<div class="arshid6social-wrap" id="arshid6social-hashtag-page">
<div class="arshid6social-container" style="padding-block:2rem;">

	<div class="arshid6social-card arshid6social-hashtag-header-card">
		<div class="arshid6social-hashtag-header-inner">
			<div class="arshid6social-hashtag-icon" aria-hidden="true">#</div>
			<div class="arshid6social-hashtag-info">
				<h1 class="arshid6social-hashtag-title">#<?php echo esc_html( $slug ); ?></h1>
				<p class="arshid6social-hashtag-count">
					<?php
					printf(
						/* translators: %d: number of posts */
						esc_html( _n( '%d post', '%d posts', $post_count, '6arshid-social-community' ) ),
						absint( $post_count )
					);
					?>
				</p>
			</div>
			<?php if ( $user_id ) : ?>
				<button
					type="button"
					class="arshid6social-btn <?php echo $is_followed ? 'arshid6social-btn-secondary arshid6social-hashtag-following' : 'arshid6social-btn-primary'; ?> arshid6social-hashtag-follow-btn"
					data-hashtag-id="<?php echo absint( $hashtag->id ); ?>"
					data-followed="<?php echo $is_followed ? '1' : '0'; ?>"
					aria-pressed="<?php echo $is_followed ? 'true' : 'false'; ?>"
				>
					<?php echo $is_followed
						? esc_html__( 'Following', '6arshid-social-community' )
						: esc_html__( 'Follow', '6arshid-social-community' );
					?>
				</button>
			<?php endif; ?>
		</div>
	</div>

	<div class="arshid6social-activity-block"
		data-hashtag="<?php echo esc_attr( $slug ); ?>"
		data-per-page="10">
		<div class="arshid6social-activity-feed" role="main"
			aria-label="<?php
			/* translators: %s: hashtag */
			echo esc_attr( sprintf( __( 'Posts tagged %s', '6arshid-social-community' ), '#' . $slug ) ); ?>">
		</div>
		<div class="arshid6social-load-more-sentinel" style="height:1px;"></div>
	</div>

</div>
</div>

<?php
$hashtag_i18n = array(
	'following' => __( 'Following', '6arshid-social-community' ),
	'follow'    => __( 'Follow', '6arshid-social-community' ),
);
wp_add_inline_script( 'arshid6social-main', 'var ARSHID6SOCIALHashtagArchiveI18n=' . wp_json_encode( $hashtag_i18n ) . ';' );
$hashtag_archive_js = <<<'ENDHASHJSBLK'
// Prevent any non-hashtag activity blocks on this page from loading all activities.
document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '.arshid6social-activity-block:not([data-hashtag])' ).forEach( function ( el ) {
		el.dataset.initialized = '1';
		el.style.display = 'none';
	} );
} );

( function () {
	const I18N = window.ARSHID6SOCIALHashtagArchiveI18n || {};
	const btn  = document.querySelector( '.arshid6social-hashtag-follow-btn' );
	if ( ! btn ) return;

	btn.addEventListener( 'click', function () {
		const cfg      = window.ARSHID6SOCIALEng || {};
		const id       = btn.dataset.hashtagId;
		const followed = btn.dataset.followed === '1';
		btn.disabled   = true;

		fetch( cfg.restUrl + 'hashtags/' + id + '/follow', {
			method: followed ? 'DELETE' : 'POST',
			headers: { 'X-WP-Nonce': cfg.restNonce },
		} )
			.then( r => {
				if ( ! r.ok ) return;
				const nowFollowing = ! followed;
				btn.dataset.followed = nowFollowing ? '1' : '0';
				btn.setAttribute( 'aria-pressed', nowFollowing ? 'true' : 'false' );
				btn.textContent = nowFollowing ? I18N.following : I18N.follow;
				btn.classList.toggle( 'arshid6social-btn-primary',   ! nowFollowing );
				btn.classList.toggle( 'arshid6social-btn-secondary', nowFollowing );
				btn.classList.toggle( 'arshid6social-hashtag-following', nowFollowing );
			} )
			.catch( () => {} )
			.finally( () => { btn.disabled = false; } );
	} );
} )();
ENDHASHJSBLK;
wp_add_inline_script( 'arshid6social-main', $hashtag_archive_js );
?>
