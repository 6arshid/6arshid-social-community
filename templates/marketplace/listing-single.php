<?php
/**
 * Single listing detail page template.
 *
 * Variables:
 *  $listing          object   Row from wp_arshid6social_listings
 *  $photos           array    Rows from wp_arshid6social_listing_media (ordered)
 *  $seller           WP_User|false
 *  $category         object|null
 *  $is_owner         bool
 *  $is_admin         bool
 *  $current_user_id  int
 *  $base_url         string   Marketplace homepage URL
 *
 * @package Arshid6Social\Components\Marketplace
 */

defined( 'ABSPATH' ) || exit;

use ARSHID6SOCIAL\Components\Marketplace\Marketplace;

$nonce      = wp_create_nonce( 'arshid6social_marketplace' );
$msg_nonce  = wp_create_nonce( 'arshid6social_ajax_nonce' );
$ajax_url   = admin_url( 'admin-ajax.php' );
$listing_id = (int) $listing->id;
$uid        = $listing->uid ?: $listing_id;
$seller_id  = $seller ? (int) $seller->ID : 0;

// Check if current user has saved this listing.
$_raw_saved = is_user_logged_in() ? get_user_meta( get_current_user_id(), 'arshid6social_mkt_saved_listings', true ) : array();
$saved_ids  = array_values( array_filter( array_map( 'intval', is_array( $_raw_saved ) ? $_raw_saved : array() ) ) );
$is_saved   = in_array( $listing_id, $saved_ids, true );

// Messages page base URL.
$messages_page_id  = (int) get_option( 'arshid6social_page_messages', 0 );
$messages_base_url = $messages_page_id ? get_permalink( $messages_page_id ) : home_url( '/messages/' );

// Photo URLs (prefer WP attachment; fall back to stored file_url)
$photo_urls = array();
foreach ( $photos as $ph ) {
	$full  = $ph->attachment_id ? wp_get_attachment_url( (int) $ph->attachment_id ) : '';
	$thumb = $ph->attachment_id ? wp_get_attachment_image_url( (int) $ph->attachment_id, 'medium' ) : '';
	$photo_urls[] = array(
		'full'  => $full  ?: $ph->file_url,
		'thumb' => $thumb ?: $ph->file_url,
	);
}

// Price display
$price_html = '';
if ( $listing->is_free ) {
	$price_html = '<span class="arshid6social-mkt-s-free">' . esc_html__( 'Free', '6arshid-social-community-main' ) . '</span>';
} else {
	$price_html = '<span class="arshid6social-mkt-s-price">' . esc_html( Marketplace::format_price( $listing->price ) ) . '</span>';
	if ( $listing->is_negotiable ) {
		$price_html .= ' <span class="arshid6social-mkt-s-neg">' . esc_html__( 'Negotiable', '6arshid-social-community-main' ) . '</span>';
	}
}

// Condition label
$condition_labels = array(
	'new'      => __( 'New', '6arshid-social-community-main' ),
	'like_new' => __( 'Like New', '6arshid-social-community-main' ),
	'good'     => __( 'Good', '6arshid-social-community-main' ),
	'fair'     => __( 'Fair', '6arshid-social-community-main' ),
	'poor'     => __( 'Poor', '6arshid-social-community-main' ),
	'used'     => __( 'Used', '6arshid-social-community-main' ),
);
$condition_label = $condition_labels[ $listing->item_condition ] ?? ucfirst( $listing->item_condition );

// Status badge
$status_label = array(
	'active'  => '',
	'pending' => __( 'Pending Review', '6arshid-social-community-main' ),
	'sold'    => __( 'Sold', '6arshid-social-community-main' ),
	'draft'   => __( 'Draft', '6arshid-social-community-main' ),
	'archived'=> __( 'Archived', '6arshid-social-community-main' ),
);
?>

<div class="arshid6social-mkt-wrap arshid6social-mkt-single" id="arshid6social-mkt-single"
	data-id="<?php echo esc_attr( $listing_id ); ?>"
	data-uid="<?php echo esc_attr( $uid ); ?>"
	data-nonce="<?php echo esc_attr( $nonce ); ?>"
	data-msg-nonce="<?php echo esc_attr( $msg_nonce ); ?>"
	data-ajax="<?php echo esc_url( $ajax_url ); ?>"
	data-back="<?php echo esc_url( $base_url ); ?>"
	data-seller-id="<?php echo esc_attr( $seller_id ); ?>"
	data-messages-url="<?php echo esc_url( $messages_base_url ); ?>"
	data-saved="<?php echo $is_saved ? '1' : '0'; ?>"
	data-login="<?php echo is_user_logged_in() ? '' : esc_url( wp_login_url( get_permalink() ) ); ?>"
	data-saved-posts-url="<?php
		$_sp_id = (int) get_option( 'arshid6social_page_saved_posts', 0 );
		echo esc_url( $_sp_id ? get_permalink( $_sp_id ) : home_url( '/saved-posts/' ) );
	?>">

	<?php /* ── Breadcrumb ──────────────────────────────────────────────────── */ ?>
	<nav class="arshid6social-mkt-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', '6arshid-social-community-main' ); ?>">
		<a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Marketplace', '6arshid-social-community-main' ); ?></a>
		<?php if ( $category ) : ?>
			<span aria-hidden="true">›</span>
			<a href="<?php echo esc_url( add_query_arg( 'cat', $listing->category_id, $base_url ) ); ?>">
				<?php echo esc_html( $category->icon . ' ' . $category->name ); ?>
			</a>
		<?php endif; ?>
		<span aria-hidden="true">›</span>
		<span><?php echo esc_html( $listing->title ); ?></span>
	</nav>

	<?php /* ── Status bar (non-active listings) ────────────────────────────── */ ?>
	<?php if ( 'active' !== $listing->status && isset( $status_label[ $listing->status ] ) ) : ?>
	<div class="arshid6social-mkt-status-bar arshid6social-mkt-status-bar--<?php echo esc_attr( $listing->status ); ?>">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
		<?php echo esc_html( $status_label[ $listing->status ] ); ?>
	</div>
	<?php endif; ?>

	<div class="arshid6social-mkt-single-layout">

		<?php /* ── Left: Photos ────────────────────────────────────────────── */ ?>
		<div class="arshid6social-mkt-single-gallery">

			<?php if ( ! empty( $photo_urls ) ) : ?>

			<div class="arshid6social-mkt-gallery-main" id="arshid6social-mkt-gallery-main">
				<img
					src="<?php echo esc_url( $photo_urls[0]['full'] ); ?>"
					alt="<?php echo esc_attr( $listing->title ); ?>"
					class="arshid6social-mkt-gallery-main-img"
					id="arshid6social-mkt-main-img"
					loading="eager">
				<?php if ( 'sold' === $listing->status ) : ?>
				<div class="arshid6social-mkt-sold-overlay">
					<span><?php esc_html_e( 'SOLD', '6arshid-social-community-main' ); ?></span>
				</div>
				<?php endif; ?>
			</div>

			<?php if ( count( $photo_urls ) > 1 ) : ?>
			<div class="arshid6social-mkt-gallery-thumbs" role="list">
				<?php foreach ( $photo_urls as $i => $ph ) : ?>
				<button type="button"
					class="arshid6social-mkt-gallery-thumb<?php echo 0 === $i ? ' is-active' : ''; ?>"
					data-full="<?php echo esc_url( $ph['full'] ); ?>"
					data-index="<?php echo esc_attr( $i ); ?>"
					aria-label="<?php
					/* translators: %d: photo number */
					printf( esc_attr__( 'Photo %d', '6arshid-social-community-main' ), absint( $i + 1 ) ); ?>"
					role="listitem">
					<img src="<?php echo esc_url( $ph['thumb'] ); ?>" alt="" loading="lazy">
				</button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php else : ?>
			<div class="arshid6social-mkt-gallery-placeholder">
				<svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
				<p><?php esc_html_e( 'No photos', '6arshid-social-community-main' ); ?></p>
			</div>
			<?php endif; ?>

		</div><!-- .gallery -->

		<?php /* ── Right: Details ─────────────────────────────────────────── */ ?>
		<div class="arshid6social-mkt-single-details">

			<?php /* Price + Title */ ?>
			<div class="arshid6social-mkt-s-price-wrap">
				<?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>

			<h1 class="arshid6social-mkt-s-title"><?php echo esc_html( $listing->title ); ?></h1>

			<?php /* Meta row */ ?>
			<div class="arshid6social-mkt-s-meta">
				<span class="arshid6social-mkt-s-condition-badge">
					<?php echo esc_html( $condition_label ); ?>
				</span>
				<?php if ( $listing->location_city ) : ?>
				<span>
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
					<?php echo esc_html( $listing->location_city ); ?><?php echo $listing->location_country ? ', ' . esc_html( $listing->location_country ) : ''; ?>
				</span>
				<?php endif; ?>
				<span>
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
					<?php echo esc_html( human_time_diff( strtotime( $listing->created_at ), time() ) . ' ' . __( 'ago', '6arshid-social-community-main' ) ); ?>
				</span>
				<span>
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
					<?php echo esc_html( number_format_i18n( (int) $listing->views ) . ' ' . __( 'views', '6arshid-social-community-main' ) ); ?>
				</span>
			</div>

			<?php /* Action buttons */ ?>
			<?php if ( ! $is_owner ) : ?>
			<div class="arshid6social-mkt-s-actions">
				<?php if ( $seller && 'sold' !== $listing->status ) : ?>
				<button type="button" class="arshid6social-mkt-btn arshid6social-mkt-btn--primary arshid6social-mkt-btn--full" id="arshid6social-mkt-contact-btn"
					<?php echo ! is_user_logged_in() ? 'data-login="' . esc_url( wp_login_url( get_permalink() ) ) . '"' : ''; ?>>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
					<?php esc_html_e( 'Message Seller', '6arshid-social-community-main' ); ?>
				</button>
				<?php endif; ?>

				<div class="arshid6social-mkt-s-secondary-actions">
					<button type="button" class="arshid6social-mkt-s-icon-btn<?php echo $is_saved ? ' is-saved' : ''; ?>" id="arshid6social-mkt-save-btn"
						aria-label="<?php esc_attr_e( 'Save listing', '6arshid-social-community-main' ); ?>"
						data-label-save="<?php esc_attr_e( 'Save', '6arshid-social-community-main' ); ?>"
						data-label-saved="<?php esc_attr_e( 'Saved', '6arshid-social-community-main' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="<?php echo $is_saved ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
						<?php echo $is_saved ? esc_html__( 'Saved', '6arshid-social-community-main' ) : esc_html__( 'Save', '6arshid-social-community-main' ); ?>
					</button>
					<button type="button" class="arshid6social-mkt-s-icon-btn" id="arshid6social-mkt-share-btn" aria-label="<?php esc_attr_e( 'Share listing', '6arshid-social-community-main' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
						<?php esc_html_e( 'Share', '6arshid-social-community-main' ); ?>
					</button>
					<button type="button" class="arshid6social-mkt-s-icon-btn arshid6social-mkt-s-icon-btn--danger" id="arshid6social-mkt-report-btn" aria-label="<?php esc_attr_e( 'Report listing', '6arshid-social-community-main' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
						<?php esc_html_e( 'Report', '6arshid-social-community-main' ); ?>
					</button>
				</div>
			</div>
			<?php else : ?>

			<?php /* Owner controls */ ?>
			<div class="arshid6social-mkt-s-owner-bar">
				<strong><?php esc_html_e( 'Your listing', '6arshid-social-community-main' ); ?></strong>
				<div class="arshid6social-mkt-s-owner-actions">
					<?php if ( 'active' === $listing->status ) : ?>
					<button type="button" class="arshid6social-mkt-btn arshid6social-mkt-btn--outline arshid6social-mkt-btn--sm arshid6social-mkt-mark-sold"
						data-id="<?php echo esc_attr( $listing_id ); ?>">
						<?php esc_html_e( 'Mark as Sold', '6arshid-social-community-main' ); ?>
					</button>
					<?php elseif ( 'sold' === $listing->status ) : ?>
					<button type="button" class="arshid6social-mkt-btn arshid6social-mkt-btn--outline arshid6social-mkt-btn--sm arshid6social-mkt-reactivate"
						data-id="<?php echo esc_attr( $listing_id ); ?>">
						<?php esc_html_e( 'Re-activate', '6arshid-social-community-main' ); ?>
					</button>
					<?php endif; ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => $uid ), $base_url ) ); ?>"
						class="arshid6social-mkt-btn arshid6social-mkt-btn--outline arshid6social-mkt-btn--sm">
						<?php esc_html_e( 'Edit', '6arshid-social-community-main' ); ?>
					</a>
					<button type="button" class="arshid6social-mkt-btn arshid6social-mkt-btn--danger arshid6social-mkt-btn--sm arshid6social-mkt-delete-listing"
						data-id="<?php echo esc_attr( $listing_id ); ?>">
						<?php esc_html_e( 'Delete', '6arshid-social-community-main' ); ?>
					</button>
				</div>
			</div>
			<?php endif; ?>

			<?php /* Seller card */ ?>
			<?php if ( $seller ) : ?>
			<div class="arshid6social-mkt-seller-card">
				<div class="arshid6social-mkt-seller-avatar">
					<?php echo get_avatar( $seller->ID, 52, '', esc_attr( $seller->display_name ) ); ?>
				</div>
				<div class="arshid6social-mkt-seller-info">
					<a href="<?php echo esc_url( get_author_posts_url( $seller->ID ) ); ?>" class="arshid6social-mkt-seller-name">
						<?php echo esc_html( $seller->display_name ); ?>
						<?php echo arshid6social_verified_badge( $seller->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
					<span class="arshid6social-mkt-seller-since">
						<?php printf(
							/* translators: %s: date user joined */
							esc_html__( 'Member since %s', '6arshid-social-community-main' ),
							esc_html( date_i18n( 'M Y', strtotime( $seller->user_registered ) ) )
						); ?>
					</span>
				</div>
			</div>
			<?php endif; ?>

		</div><!-- .details -->
	</div><!-- .layout -->

	<?php /* ── Description ────────────────────────────────────────────────── */ ?>
	<?php if ( $listing->description ) : ?>
	<div class="arshid6social-mkt-s-desc-wrap">
		<h2 class="arshid6social-mkt-s-section-title"><?php esc_html_e( 'Description', '6arshid-social-community-main' ); ?></h2>
		<div class="arshid6social-mkt-s-desc">
			<?php echo wp_kses_post( nl2br( $listing->description ) ); ?>
		</div>
	</div>
	<?php endif; ?>

	<?php /* ── Safety tips ─────────────────────────────────────────────────── */ ?>
	<?php if ( get_option( 'arshid6social_marketplace_safety_tips', true ) ) : ?>
	<div class="arshid6social-mkt-safety-tips">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
		<div>
			<strong><?php esc_html_e( 'Stay safe', '6arshid-social-community-main' ); ?></strong>
			<ul>
				<li><?php esc_html_e( 'Meet in a safe, public place.', '6arshid-social-community-main' ); ?></li>
				<li><?php esc_html_e( 'Inspect items before you pay.', '6arshid-social-community-main' ); ?></li>
				<li><?php esc_html_e( 'Never send money in advance.', '6arshid-social-community-main' ); ?></li>
			</ul>
		</div>
	</div>
	<?php endif; ?>

</div><!-- .arshid6social-mkt-single -->

<style id="arshid6social-mkt-single-styles">
:root {
	--mkt-primary: <?php echo esc_attr( get_option( 'arshid6social_primary_color', '#2563eb' ) ); ?>;
	--mkt-radius:  10px;
	--mkt-border:  #e2e8f0;
	--mkt-text:    #0f172a;
	--mkt-muted:   #64748b;
	--mkt-bg:      #f8fafc;
	--mkt-error:   #dc2626;
}
.arshid6social-dark-mode { --mkt-border:#334155; --mkt-text:#f1f5f9; --mkt-muted:#94a3b8; --mkt-bg:#1e293b; }

/* Wrap */
.arshid6social-mkt-single { max-width:1100px; margin:0 auto; padding:0 16px 60px; }

/* Breadcrumb */
.arshid6social-mkt-breadcrumb { display:flex; align-items:center; gap:8px; font-size:.8125rem; color:var(--mkt-muted); margin:20px 0 16px; flex-wrap:wrap; }
.arshid6social-mkt-breadcrumb a { color:var(--mkt-muted); text-decoration:none; }
.arshid6social-mkt-breadcrumb a:hover { color:var(--mkt-primary); }
.arshid6social-mkt-breadcrumb span:last-child { color:var(--mkt-text); font-weight:500; }

/* Status bar */
.arshid6social-mkt-status-bar { display:flex; align-items:center; gap:8px; padding:12px 16px; border-radius:var(--mkt-radius); margin-bottom:16px; font-size:.875rem; font-weight:500; }
.arshid6social-mkt-status-bar--pending  { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
.arshid6social-mkt-status-bar--sold     { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
.arshid6social-mkt-status-bar--draft    { background:#f8fafc; border:1px solid var(--mkt-border); color:var(--mkt-muted); }
.arshid6social-mkt-status-bar--archived { background:#f8fafc; border:1px solid var(--mkt-border); color:var(--mkt-muted); }

/* Two-column layout */
.arshid6social-mkt-single-layout { display:grid; grid-template-columns:1fr 400px; gap:32px; align-items:start; }
@media (max-width:860px) { .arshid6social-mkt-single-layout { grid-template-columns:1fr; } }

/* Gallery */
.arshid6social-mkt-gallery-main { border-radius:var(--mkt-radius); overflow:hidden; background:var(--mkt-bg); border:1px solid var(--mkt-border); position:relative; }
.arshid6social-mkt-gallery-main-img { width:100%; height:420px; object-fit:cover; display:block; cursor:zoom-in; transition:opacity .2s; }
.arshid6social-mkt-sold-overlay { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.4); }
.arshid6social-mkt-sold-overlay span { background:#dc2626; color:#fff; padding:10px 28px; font-size:1.5rem; font-weight:900; letter-spacing:.1em; border-radius:6px; transform:rotate(-8deg); }
.arshid6social-mkt-gallery-thumbs { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.arshid6social-mkt-gallery-thumb { width:72px; height:72px; border-radius:8px; overflow:hidden; border:2px solid var(--mkt-border); padding:0; cursor:pointer; transition:border-color .15s; flex-shrink:0; }
.arshid6social-mkt-gallery-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.arshid6social-mkt-gallery-thumb.is-active { border-color:var(--mkt-primary); }
.arshid6social-mkt-gallery-placeholder { border-radius:var(--mkt-radius); background:var(--mkt-bg); border:1px solid var(--mkt-border); height:320px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; color:var(--mkt-muted); }

/* Details panel */
.arshid6social-mkt-single-details { display:flex; flex-direction:column; gap:20px; }
.arshid6social-mkt-s-price-wrap { display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; }
.arshid6social-mkt-s-price  { font-size:2rem; font-weight:800; color:var(--mkt-primary); line-height:1; }
.arshid6social-mkt-s-free   { font-size:2rem; font-weight:800; color:#16a34a; line-height:1; }
.arshid6social-mkt-s-neg    { font-size:.875rem; font-weight:600; color:var(--mkt-muted); padding:3px 10px; border:1px solid var(--mkt-border); border-radius:20px; }
.arshid6social-mkt-s-title  { font-size:1.375rem; font-weight:700; color:var(--mkt-text); margin:0; }
.arshid6social-mkt-s-meta   { display:flex; align-items:center; flex-wrap:wrap; gap:12px; font-size:.8125rem; color:var(--mkt-muted); }
.arshid6social-mkt-s-meta > span { display:inline-flex; align-items:center; gap:4px; }
.arshid6social-mkt-s-condition-badge { background:var(--mkt-bg); border:1px solid var(--mkt-border); color:var(--mkt-text); padding:3px 10px; border-radius:20px; font-weight:500; font-size:.8rem; }

/* Action buttons */
.arshid6social-mkt-s-actions { display:flex; flex-direction:column; gap:10px; }
.arshid6social-mkt-btn--full { width:100%; justify-content:center; }
.arshid6social-mkt-s-secondary-actions { display:flex; gap:8px; }
.arshid6social-mkt-s-icon-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 14px; border:1px solid var(--mkt-border); border-radius:var(--mkt-radius); background:#fff; cursor:pointer; font-size:.8125rem; color:var(--mkt-text); font-weight:500; transition:border-color .15s, color .15s; flex:1; justify-content:center; }
.arshid6social-mkt-s-icon-btn:hover { border-color:var(--mkt-primary); color:var(--mkt-primary); }
.arshid6social-mkt-s-icon-btn--danger:hover { border-color:var(--mkt-error); color:var(--mkt-error); }
.arshid6social-mkt-s-icon-btn.is-saved { color:#e11d48; border-color:#fda4af; }
.arshid6social-mkt-s-icon-btn.is-saved svg { stroke:#e11d48; }
.arshid6social-dark-mode .arshid6social-mkt-s-icon-btn { background:#1e293b; }

/* Seller card */
.arshid6social-mkt-seller-card { display:flex; gap:14px; align-items:center; padding:16px; background:var(--mkt-bg); border-radius:var(--mkt-radius); border:1px solid var(--mkt-border); }
.arshid6social-mkt-seller-avatar img { border-radius:50%; }
.arshid6social-mkt-seller-name { display:flex; align-items:center; gap:6px; font-weight:700; color:var(--mkt-text); text-decoration:none; font-size:.9375rem; }
.arshid6social-mkt-seller-name:hover { color:var(--mkt-primary); }
.arshid6social-mkt-seller-since { font-size:.78rem; color:var(--mkt-muted); display:block; margin-top:2px; }
.arshid6social-mkt-seller-info { display:flex; flex-direction:column; }

/* Owner bar */
.arshid6social-mkt-s-owner-bar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 16px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:var(--mkt-radius); font-size:.875rem; }
.arshid6social-mkt-s-owner-actions { display:flex; gap:8px; flex-wrap:wrap; }

/* Buttons */
.arshid6social-mkt-btn { display:inline-flex; align-items:center; gap:6px; padding:10px 18px; border-radius:var(--mkt-radius); font-size:.9rem; font-weight:600; cursor:pointer; border:2px solid transparent; text-decoration:none; transition:all .15s; }
.arshid6social-mkt-btn--primary { background:var(--mkt-primary); color:#fff; border-color:var(--mkt-primary); }
.arshid6social-mkt-btn--primary:hover { opacity:.88; }
.arshid6social-mkt-btn--outline { background:transparent; color:var(--mkt-text); border-color:var(--mkt-border); }
.arshid6social-mkt-btn--outline:hover { border-color:var(--mkt-primary); color:var(--mkt-primary); }
.arshid6social-mkt-btn--danger { background:#dc2626; color:#fff; border-color:#dc2626; }
.arshid6social-mkt-btn--danger:hover { opacity:.88; }
.arshid6social-mkt-btn--sm { padding:7px 14px; font-size:.8125rem; }

/* Description */
.arshid6social-mkt-s-desc-wrap { margin-top:28px; }
.arshid6social-mkt-s-section-title { font-size:1rem; font-weight:700; color:var(--mkt-text); margin:0 0 12px; }
.arshid6social-mkt-s-desc { font-size:.9375rem; color:var(--mkt-text); line-height:1.7; white-space:pre-wrap; word-break:break-word; }

/* Safety tips */
.arshid6social-mkt-safety-tips { display:flex; align-items:flex-start; gap:12px; margin-top:24px; padding:14px 16px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:var(--mkt-radius); color:#166534; font-size:.8125rem; }
.arshid6social-mkt-safety-tips ul { margin:4px 0 0 16px; padding:0; }
.arshid6social-mkt-safety-tips li { margin-bottom:2px; }

/* RTL */
[dir="rtl"] .arshid6social-mkt-safety-tips ul { margin-left:0; margin-right:16px; }
[dir="rtl"] .arshid6social-mkt-breadcrumb { direction:rtl; }

/* Toast notification */
.arshid6social-mkt-toast {
	position:fixed; bottom:28px; left:50%; transform:translateX(-50%) translateY(20px);
	background:#1e293b; color:#fff; padding:12px 20px; border-radius:8px;
	font-size:.9rem; box-shadow:0 4px 20px rgba(0,0,0,.25);
	opacity:0; transition:opacity .3s,transform .3s; z-index:99999;
	white-space:nowrap; pointer-events:none;
}
.arshid6social-mkt-toast a { pointer-events:auto; }
.arshid6social-mkt-toast--visible { opacity:1; transform:translateX(-50%) translateY(0); }
.arshid6social-mkt-toast--success { background:#166534; }
.arshid6social-mkt-toast--info    { background:#1e40af; }
</style>

<script>
(function () {
'use strict';

const wrap  = document.getElementById( 'arshid6social-mkt-single' );
if ( ! wrap ) return;

const AJAX           = wrap.dataset.ajax;
const NONCE          = wrap.dataset.nonce;
const MSG_NONCE      = wrap.dataset.msgNonce;
const ID             = wrap.dataset.id;
const BACK           = wrap.dataset.back;
const SELLER_ID      = wrap.dataset.sellerId;
const MESSAGES_URL   = wrap.dataset.messagesUrl;
const LOGIN_URL      = wrap.dataset.login;
const SAVED_POSTS_URL = wrap.dataset.savedPostsUrl || '';

function ARSHID6SOCIALShowToast( html, type ) {
	const t = document.createElement( 'div' );
	t.className = 'arshid6social-mkt-toast arshid6social-mkt-toast--' + ( type || 'info' );
	t.innerHTML = html;
	document.body.appendChild( t );
	requestAnimationFrame( () => t.classList.add( 'arshid6social-mkt-toast--visible' ) );
	setTimeout( () => {
		t.classList.remove( 'arshid6social-mkt-toast--visible' );
		setTimeout( () => t.remove(), 400 );
	}, 4000 );
}

// ── Photo gallery ─────────────────────────────────────────────────────────
const mainImg = document.getElementById( 'arshid6social-mkt-main-img' );
document.querySelectorAll( '.arshid6social-mkt-gallery-thumb' ).forEach( btn => {
	btn.addEventListener( 'click', () => {
		if ( mainImg ) {
			mainImg.style.opacity = '0.6';
			mainImg.src = btn.dataset.full;
			mainImg.onload = () => { mainImg.style.opacity = '1'; };
		}
		document.querySelectorAll( '.arshid6social-mkt-gallery-thumb' ).forEach( b => b.classList.remove( 'is-active' ) );
		btn.classList.add( 'is-active' );
	} );
} );

// ── Share ─────────────────────────────────────────────────────────────────
const shareBtn = document.getElementById( 'arshid6social-mkt-share-btn' );
if ( shareBtn ) {
	shareBtn.addEventListener( 'click', async () => {
		const url   = window.location.href;
		const title = document.querySelector( '.arshid6social-mkt-s-title' )?.textContent || '';
		if ( navigator.share ) {
			try { await navigator.share( { title, url } ); } catch (e) {}
		} else {
			await navigator.clipboard.writeText( url ).catch( () => {} );
			shareBtn.textContent = '<?php echo esc_js( __( 'Link copied!', '6arshid-social-community-main' ) ); ?>';
			setTimeout( () => { shareBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg><?php echo esc_js( __( 'Share', '6arshid-social-community-main' ) ); ?>'; }, 2500 );
		}
	} );
}

// ── Contact seller ────────────────────────────────────────────────────────
const contactBtn = document.getElementById( 'arshid6social-mkt-contact-btn' );
if ( contactBtn ) {
	contactBtn.addEventListener( 'click', async () => {
		if ( LOGIN_URL ) {
			window.location.href = LOGIN_URL;
			return;
		}
		contactBtn.disabled = true;
		contactBtn.style.opacity = '0.7';
		try {
			const fd = new FormData();
			fd.append( 'action',       'arshid6social_get_or_create_thread' );
			fd.append( 'nonce',        MSG_NONCE );
			fd.append( 'recipient_id', SELLER_ID );
			const res  = await fetch( AJAX, { method: 'POST', body: fd } );
			const data = await res.json();
			if ( data.success && data.data.thread_uid ) {
				window.location.href = MESSAGES_URL.replace( /\/?$/, '/' ) + 'thread/' + data.data.thread_uid + '/';
			} else {
				alert( data.data?.message || '<?php echo esc_js( __( 'Could not open conversation.', '6arshid-social-community-main' ) ); ?>' );
				contactBtn.disabled = false;
				contactBtn.style.opacity = '';
			}
		} catch (e) {
			contactBtn.disabled = false;
			contactBtn.style.opacity = '';
		}
	} );
}

// ── Save listing ──────────────────────────────────────────────────────────
const saveBtn = document.getElementById( 'arshid6social-mkt-save-btn' );
if ( saveBtn ) {
	saveBtn.addEventListener( 'click', async () => {
		if ( LOGIN_URL ) { window.location.href = LOGIN_URL; return; }
		saveBtn.disabled = true;
		const fd = new FormData();
		fd.append( 'action',     'arshid6social_mkt_toggle_save' );
		fd.append( 'nonce',      NONCE );
		fd.append( 'listing_id', ID );
		try {
			const res  = await fetch( AJAX, { method: 'POST', body: fd } );
			const data = await res.json();
			if ( data.success ) {
				const saved = data.data.saved;
				const svg   = saveBtn.querySelector( 'svg' );
				if ( svg ) svg.setAttribute( 'fill', saved ? 'currentColor' : 'none' );
				saveBtn.childNodes[ saveBtn.childNodes.length - 1 ].textContent = ' ' + ( saved ? saveBtn.dataset.labelSaved : saveBtn.dataset.labelSave );
				saveBtn.classList.toggle( 'is-saved', saved );
				if ( saved ) {
					const linkHtml = SAVED_POSTS_URL
						? ' <a href="' + SAVED_POSTS_URL + '" style="color:inherit;text-decoration:underline;font-weight:600;"><?php echo esc_js( __( 'View saved posts', '6arshid-social-community-main' ) ); ?></a>'
						: '';
					ARSHID6SOCIALShowToast( '<?php echo esc_js( __( 'Listing saved!', '6arshid-social-community-main' ) ); ?>' + linkHtml, 'success' );
				} else {
					ARSHID6SOCIALShowToast( '<?php echo esc_js( __( 'Removed from saved posts.', '6arshid-social-community-main' ) ); ?>', 'info' );
				}
			}
		} catch (e) {}
		saveBtn.disabled = false;
	} );
}

// ── Report listing ────────────────────────────────────────────────────────
const reportBtn = document.getElementById( 'arshid6social-mkt-report-btn' );
if ( reportBtn ) {
	reportBtn.addEventListener( 'click', async () => {
		if ( LOGIN_URL ) { window.location.href = LOGIN_URL; return; }
		const reason = prompt( '<?php echo esc_js( __( 'Why are you reporting this listing?', '6arshid-social-community-main' ) ); ?>' );
		if ( null === reason ) return;
		reportBtn.disabled = true;
		const fd = new FormData();
		fd.append( 'action',     'arshid6social_mkt_report_listing' );
		fd.append( 'nonce',      NONCE );
		fd.append( 'listing_id', ID );
		fd.append( 'reason',     reason );
		try {
			const res  = await fetch( AJAX, { method: 'POST', body: fd } );
			const data = await res.json();
			if ( data.success ) {
				reportBtn.textContent = '<?php echo esc_js( __( 'Reported', '6arshid-social-community-main' ) ); ?>';
			} else {
				alert( data.data?.message || '<?php echo esc_js( __( 'Could not submit report.', '6arshid-social-community-main' ) ); ?>' );
				reportBtn.disabled = false;
			}
		} catch (e) {
			reportBtn.disabled = false;
		}
	} );
}

// ── Mark as sold ──────────────────────────────────────────────────────────
async function changeStatus( listingId, status ) {
	const fd = new FormData();
	fd.append( 'action',     'arshid6social_mkt_change_status' );
	fd.append( 'nonce',      NONCE );
	fd.append( 'listing_id', listingId );
	fd.append( 'status',     status );
	const res  = await fetch( AJAX, { method: 'POST', body: fd } );
	const data = await res.json();
	if ( data.success ) window.location.reload();
}

document.querySelectorAll( '.arshid6social-mkt-mark-sold' ).forEach( btn => {
	btn.addEventListener( 'click', () => {
		if ( confirm( '<?php echo esc_js( __( 'Mark this listing as sold?', '6arshid-social-community-main' ) ); ?>' ) ) {
			changeStatus( btn.dataset.id, 'sold' );
		}
	} );
} );
document.querySelectorAll( '.arshid6social-mkt-reactivate' ).forEach( btn => {
	btn.addEventListener( 'click', () => changeStatus( btn.dataset.id, 'active' ) );
} );

// ── Delete listing ────────────────────────────────────────────────────────
document.querySelectorAll( '.arshid6social-mkt-delete-listing' ).forEach( btn => {
	btn.addEventListener( 'click', async () => {
		if ( ! confirm( '<?php echo esc_js( __( 'Permanently delete this listing? This cannot be undone.', '6arshid-social-community-main' ) ); ?>' ) ) return;
		const fd = new FormData();
		fd.append( 'action',     'arshid6social_mkt_delete_listing' );
		fd.append( 'nonce',      NONCE );
		fd.append( 'listing_id', btn.dataset.id );
		const res  = await fetch( AJAX, { method: 'POST', body: fd } );
		const data = await res.json();
		if ( data.success ) window.location.href = BACK;
	} );
} );

})();
</script>
