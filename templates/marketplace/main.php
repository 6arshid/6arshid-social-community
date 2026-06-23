<?php
/**
 * Marketplace homepage template.
 *
 * Available variables:
 *  $categories   array  Top-level category objects from wp_arshid6social_categories.
 *  $current_user WP_User
 *
 * @package Arshid6Social\Components\Marketplace
 */

defined( 'ABSPATH' ) || exit;

$is_logged_in    = is_user_logged_in();
$marketplace_url = get_permalink( (int) get_option( 'arshid6social_page_marketplace', 0 ) ) ?: home_url( '/' . get_option( 'arshid6social_marketplace_slug', 'marketplace' ) . '/' );
$post_url        = add_query_arg( 'action', 'post', $marketplace_url );
$ajax_url        = admin_url( 'admin-ajax.php' );

// Active filters from URL.
$search_q   = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
$active_cat = absint( $_GET['cat'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
$nonce      = wp_create_nonce( 'arshid6social_marketplace' );
?>

<div class="arshid6social-mkt-wrap" id="arshid6social-marketplace"
	data-nonce="<?php echo esc_attr( $nonce ); ?>"
	data-ajax="<?php echo esc_url( $ajax_url ); ?>"
	data-url="<?php echo esc_url( $marketplace_url ); ?>">

	<?php /* ── Hero / search bar ─────────────────────────────────────────── */ ?>
	<div class="arshid6social-mkt-hero">
		<form class="arshid6social-mkt-search-form" method="get" action="<?php echo esc_url( $marketplace_url ); ?>" role="search">
			<div class="arshid6social-mkt-search-inner">
				<span class="arshid6social-mkt-search-icon" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				</span>
				<input
					type="search"
					name="q"
					id="arshid6social-mkt-search"
					class="arshid6social-mkt-search-input"
					value="<?php echo esc_attr( $search_q ); ?>"
					placeholder="<?php esc_attr_e( 'Search listings…', 'social-network-6' ); ?>"
					autocomplete="off"
					aria-label="<?php esc_attr_e( 'Search marketplace listings', 'social-network-6' ); ?>"
				/>
				<button type="submit" class="arshid6social-mkt-search-btn">
					<?php esc_html_e( 'Search', 'social-network-6' ); ?>
				</button>
			</div>
		</form>

		<?php if ( $is_logged_in ) : ?>
			<a href="<?php echo esc_url( $post_url ); ?>" class="arshid6social-mkt-post-btn">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
				<?php esc_html_e( 'Post a Listing', 'social-network-6' ); ?>
			</a>
		<?php else : ?>
			<a href="<?php echo esc_url( wp_login_url( $post_url ) ); ?>" class="arshid6social-mkt-post-btn arshid6social-mkt-post-btn--guest">
				<?php esc_html_e( 'Sign in to Sell', 'social-network-6' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php /* ── Category grid ──────────────────────────────────────────────── */ ?>
	<?php if ( ! empty( $categories ) ) : ?>
	<section class="arshid6social-mkt-section" aria-labelledby="arshid6social-mkt-cats-heading">
		<h2 class="arshid6social-mkt-section-title" id="arshid6social-mkt-cats-heading">
			<?php esc_html_e( 'Browse by Category', 'social-network-6' ); ?>
		</h2>
		<div class="arshid6social-mkt-cat-grid" role="list">
			<?php foreach ( $categories as $cat ) :
				$cat_url = add_query_arg( 'cat', $cat->id, $marketplace_url );
				$is_active = ( $active_cat === (int) $cat->id );
			?>
			<a href="<?php echo esc_url( $cat_url ); ?>"
				class="arshid6social-mkt-cat-card<?php echo $is_active ? ' arshid6social-mkt-cat-card--active' : ''; ?>"
				role="listitem"
				aria-current="<?php echo $is_active ? 'page' : 'false'; ?>">
				<span class="arshid6social-mkt-cat-icon" aria-hidden="true"><?php echo esc_html( $cat->icon ); ?></span>
				<span class="arshid6social-mkt-cat-name"><?php echo esc_html( $cat->name ); ?></span>
			</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php /* ── Active filters bar ─────────────────────────────────────────── */ ?>
	<?php if ( $search_q || $active_cat ) : ?>
	<div class="arshid6social-mkt-filters-bar">
		<span><?php esc_html_e( 'Filters:', 'social-network-6' ); ?></span>
		<?php if ( $search_q ) : ?>
			<span class="arshid6social-mkt-filter-chip">
				<?php echo esc_html( '"' . $search_q . '"' ); ?>
				<a href="<?php echo esc_url( remove_query_arg( 'q', $marketplace_url ) ); ?>" class="arshid6social-mkt-filter-remove" aria-label="<?php esc_attr_e( 'Remove search filter', 'social-network-6' ); ?>">×</a>
			</span>
		<?php endif; ?>
		<?php if ( $active_cat ) :
			global $wpdb;
			$cat_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}arshid6social_categories WHERE id = %d", $active_cat ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		?>
			<span class="arshid6social-mkt-filter-chip">
				<?php echo esc_html( $cat_name ?: __( 'Category', 'social-network-6' ) ); ?>
				<a href="<?php echo esc_url( remove_query_arg( 'cat', $marketplace_url ) ); ?>" class="arshid6social-mkt-filter-remove" aria-label="<?php esc_attr_e( 'Remove category filter', 'social-network-6' ); ?>">×</a>
			</span>
		<?php endif; ?>
		<a href="<?php echo esc_url( $marketplace_url ); ?>" class="arshid6social-mkt-clear-all">
			<?php esc_html_e( 'Clear all', 'social-network-6' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<?php /* ── Listings grid (populated via AJAX in Steps 3–4) ───────────── */ ?>
	<section class="arshid6social-mkt-section" aria-labelledby="arshid6social-mkt-listings-heading">
		<div class="arshid6social-mkt-listings-header">
			<h2 class="arshid6social-mkt-section-title" id="arshid6social-mkt-listings-heading">
				<?php echo $search_q || $active_cat
					? esc_html__( 'Results', 'social-network-6' )
					: esc_html__( 'Recently Listed', 'social-network-6' ); ?>
			</h2>
			<div class="arshid6social-mkt-view-sort">
				<button class="arshid6social-mkt-view-btn arshid6social-mkt-view-btn--active" data-view="grid" aria-label="<?php esc_attr_e( 'Grid view', 'social-network-6' ); ?>" title="<?php esc_attr_e( 'Grid view', 'social-network-6' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/></svg>
				</button>
				<button class="arshid6social-mkt-view-btn" data-view="list" aria-label="<?php esc_attr_e( 'List view', 'social-network-6' ); ?>" title="<?php esc_attr_e( 'List view', 'social-network-6' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="4" width="18" height="2" rx="1"/><rect x="3" y="11" width="18" height="2" rx="1"/><rect x="3" y="18" width="18" height="2" rx="1"/></svg>
				</button>
				<select class="arshid6social-mkt-sort-select" aria-label="<?php esc_attr_e( 'Sort listings', 'social-network-6' ); ?>">
					<option value="newest"><?php esc_html_e( 'Newest', 'social-network-6' ); ?></option>
					<option value="price_asc"><?php esc_html_e( 'Price: Low to High', 'social-network-6' ); ?></option>
					<option value="price_desc"><?php esc_html_e( 'Price: High to Low', 'social-network-6' ); ?></option>
					<option value="most_viewed"><?php esc_html_e( 'Most Viewed', 'social-network-6' ); ?></option>
				</select>
			</div>
		</div>

		<?php /* Skeleton loaders shown while AJAX fetches listings */ ?>
		<div id="arshid6social-mkt-listings-grid" class="arshid6social-mkt-grid" data-view="grid"
			data-q="<?php echo esc_attr( $search_q ); ?>"
			data-cat="<?php echo esc_attr( $active_cat ); ?>"
			data-sort="newest"
			data-page="1">

			<?php /* Skeleton cards — replaced by real cards once JS loads */ ?>
			<?php for ( $s = 0; $s < 6; $s++ ) : ?>
			<div class="arshid6social-mkt-card arshid6social-mkt-skeleton" aria-hidden="true">
				<div class="arshid6social-mkt-card-img arshid6social-skeleton-box"></div>
				<div class="arshid6social-mkt-card-body">
					<div class="arshid6social-skeleton-line arshid6social-skeleton-line--short"></div>
					<div class="arshid6social-skeleton-line"></div>
					<div class="arshid6social-skeleton-line arshid6social-skeleton-line--thin"></div>
				</div>
			</div>
			<?php endfor; ?>
		</div>

		<div id="arshid6social-mkt-listings-empty" class="arshid6social-mkt-empty" hidden>
			<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
			<p><?php esc_html_e( 'No listings found.', 'social-network-6' ); ?></p>
			<?php if ( $is_logged_in ) : ?>
				<a href="<?php echo esc_url( $post_url ); ?>" class="arshid6social-mkt-post-btn" style="margin-top:12px">
					<?php esc_html_e( 'Be the first to post!', 'social-network-6' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<div id="arshid6social-mkt-listings-more" class="arshid6social-mkt-load-more" hidden>
			<button type="button" id="arshid6social-mkt-load-more-btn" class="arshid6social-btn arshid6social-btn--outline">
				<?php esc_html_e( 'Load More', 'social-network-6' ); ?>
			</button>
		</div>
	</section>

</div><!-- .arshid6social-mkt-wrap -->

<?php /* ── Styles ─────────────────────────────────────────────────────────── */ ?>
<style id="arshid6social-mkt-styles">
:root {
	--mkt-primary:    <?php echo esc_attr( get_option( 'arshid6social_primary_color', '#2563eb' ) ); ?>;
	--mkt-primary-10: color-mix(in srgb, var(--mkt-primary) 10%, transparent);
	--mkt-radius:     10px;
	--mkt-shadow:     0 1px 4px rgba(0,0,0,.08);
	--mkt-border:     #e2e8f0;
	--mkt-text:       #0f172a;
	--mkt-muted:      #64748b;
	--mkt-bg:         #f8fafc;
}
.arshid6social-dark-mode {
	--mkt-border: #334155;
	--mkt-text:   #f1f5f9;
	--mkt-muted:  #94a3b8;
	--mkt-bg:     #1e293b;
}

/* Wrap */
.arshid6social-mkt-wrap { max-width:1200px; margin:0 auto; padding:0 16px 48px; }

/* Hero */
.arshid6social-mkt-hero { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin:24px 0 28px; }
.arshid6social-mkt-search-form { flex:1; min-width:240px; }
.arshid6social-mkt-search-inner { display:flex; align-items:center; border:2px solid var(--mkt-border); border-radius:var(--mkt-radius); overflow:hidden; background:#fff; transition:border-color .2s; }
.arshid6social-mkt-search-inner:focus-within { border-color:var(--mkt-primary); }
.arshid6social-mkt-search-icon { padding:0 10px; color:var(--mkt-muted); flex-shrink:0; display:flex; align-items:center; }
.arshid6social-mkt-search-input { flex:1; border:none; outline:none; background:transparent; padding:11px 4px; font-size:.9375rem; color:var(--mkt-text); min-width:0; }
.arshid6social-mkt-search-btn { padding:0 20px; height:46px; background:var(--mkt-primary); color:#fff; border:none; cursor:pointer; font-size:.875rem; font-weight:600; transition:opacity .15s; white-space:nowrap; }
.arshid6social-mkt-search-btn:hover { opacity:.9; }

/* Post button */
.arshid6social-mkt-post-btn { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; background:var(--mkt-primary); color:#fff !important; border-radius:var(--mkt-radius); font-weight:600; font-size:.9rem; text-decoration:none !important; white-space:nowrap; transition:opacity .15s; }
.arshid6social-mkt-post-btn:hover { opacity:.88; }
.arshid6social-mkt-post-btn--guest { background:#64748b; }

/* Sections */
.arshid6social-mkt-section { margin-bottom:36px; }
.arshid6social-mkt-section-title { font-size:1.125rem; font-weight:700; color:var(--mkt-text); margin:0 0 16px; }

/* Category grid */
.arshid6social-mkt-cat-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(110px,1fr)); gap:10px; }
.arshid6social-mkt-cat-card { display:flex; flex-direction:column; align-items:center; gap:8px; padding:16px 8px; background:#fff; border:2px solid var(--mkt-border); border-radius:var(--mkt-radius); text-decoration:none !important; color:var(--mkt-text) !important; transition:border-color .15s, transform .15s, box-shadow .15s; }
.arshid6social-mkt-cat-card:hover { border-color:var(--mkt-primary); transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.1); }
.arshid6social-mkt-cat-card--active { border-color:var(--mkt-primary); background:var(--mkt-primary-10); }
.arshid6social-mkt-cat-icon { font-size:1.75rem; line-height:1; }
.arshid6social-mkt-cat-name { font-size:.78rem; font-weight:600; text-align:center; color:var(--mkt-text); }

/* Filters bar */
.arshid6social-mkt-filters-bar { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:20px; font-size:.875rem; color:var(--mkt-muted); }
.arshid6social-mkt-filter-chip { display:inline-flex; align-items:center; gap:6px; background:var(--mkt-primary-10); color:var(--mkt-primary); padding:4px 10px; border-radius:20px; font-weight:500; }
.arshid6social-mkt-filter-remove { color:inherit; text-decoration:none; font-size:1rem; line-height:1; opacity:.7; }
.arshid6social-mkt-filter-remove:hover { opacity:1; }
.arshid6social-mkt-clear-all { color:var(--mkt-muted); text-decoration:underline; }

/* Listings header */
.arshid6social-mkt-listings-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.arshid6social-mkt-view-sort { display:flex; align-items:center; gap:8px; }
.arshid6social-mkt-view-btn { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border:1px solid var(--mkt-border); border-radius:6px; background:#fff; cursor:pointer; color:var(--mkt-muted); transition:color .15s, border-color .15s; }
.arshid6social-mkt-view-btn--active, .arshid6social-mkt-view-btn:hover { color:var(--mkt-primary); border-color:var(--mkt-primary); }
.arshid6social-mkt-sort-select { border:1px solid var(--mkt-border); border-radius:6px; padding:6px 10px; font-size:.875rem; background:#fff; color:var(--mkt-text); cursor:pointer; }

/* Grid */
.arshid6social-mkt-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:16px; }
.arshid6social-mkt-grid[data-view="list"] { grid-template-columns:1fr; }

/* Listing card */
.arshid6social-mkt-card { background:#fff; border:1px solid var(--mkt-border); border-radius:var(--mkt-radius); overflow:hidden; box-shadow:var(--mkt-shadow); transition:transform .15s, box-shadow .15s; }
.arshid6social-mkt-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(0,0,0,.1); }
.arshid6social-mkt-card-img { aspect-ratio:4/3; background:var(--mkt-bg); overflow:hidden; }
.arshid6social-mkt-card-img img { width:100%; height:100%; object-fit:cover; display:block; }
.arshid6social-mkt-card-body { padding:12px; }
.arshid6social-mkt-card-price { font-size:1.05rem; font-weight:700; color:var(--mkt-primary); margin:0 0 4px; }
.arshid6social-mkt-card-title { font-size:.9rem; font-weight:600; color:var(--mkt-text); margin:0 0 6px; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
.arshid6social-mkt-card-meta { font-size:.78rem; color:var(--mkt-muted); display:flex; align-items:center; gap:6px; }
.arshid6social-mkt-card-badge { display:inline-block; padding:2px 7px; border-radius:4px; font-size:.72rem; font-weight:600; }
.arshid6social-mkt-card-badge--free { background:#dcfce7; color:#16a34a; }
.arshid6social-mkt-card-badge--sold { background:#fee2e2; color:#dc2626; }
.arshid6social-mkt-card-badge--negot { background:#fef9c3; color:#854d0e; }

/* List-view card override */
.arshid6social-mkt-grid[data-view="list"] .arshid6social-mkt-card { display:flex; flex-direction:row; }
.arshid6social-mkt-grid[data-view="list"] .arshid6social-mkt-card-img { width:140px; flex-shrink:0; aspect-ratio:unset; min-height:110px; }

/* Skeleton */
.arshid6social-mkt-skeleton { pointer-events:none; }
.arshid6social-skeleton-box { background:linear-gradient(90deg,#e2e8f0 25%,#f1f5f9 50%,#e2e8f0 75%); background-size:200% 100%; animation:arshid6social-shimmer 1.4s infinite; }
.arshid6social-skeleton-line { height:12px; border-radius:6px; margin-bottom:8px; background:linear-gradient(90deg,#e2e8f0 25%,#f1f5f9 50%,#e2e8f0 75%); background-size:200% 100%; animation:arshid6social-shimmer 1.4s infinite; }
.arshid6social-skeleton-line--short { width:55%; }
.arshid6social-skeleton-line--thin  { width:70%; height:9px; }
@keyframes arshid6social-shimmer { to { background-position:-200% 0; } }

/* Empty state */
.arshid6social-mkt-empty { text-align:center; padding:56px 16px; color:var(--mkt-muted); }
.arshid6social-mkt-empty p { margin:12px 0 0; font-size:.9375rem; }

/* Load more */
.arshid6social-mkt-load-more { text-align:center; margin-top:24px; }
.arshid6social-btn--outline { padding:10px 32px; border:2px solid var(--mkt-primary); border-radius:var(--mkt-radius); background:transparent; color:var(--mkt-primary); font-size:.9375rem; font-weight:600; cursor:pointer; transition:background .15s, color .15s; }
.arshid6social-btn--outline:hover { background:var(--mkt-primary); color:#fff; }

/* RTL */
[dir="rtl"] .arshid6social-mkt-search-icon { padding:0 10px 0 4px; }
[dir="rtl"] .arshid6social-mkt-grid[data-view="list"] .arshid6social-mkt-card { flex-direction:row-reverse; }

/* Dark mode */
.arshid6social-dark-mode .arshid6social-mkt-search-inner,
.arshid6social-dark-mode .arshid6social-mkt-cat-card,
.arshid6social-dark-mode .arshid6social-mkt-card,
.arshid6social-dark-mode .arshid6social-mkt-sort-select,
.arshid6social-dark-mode .arshid6social-mkt-view-btn { background:#1e293b; }
.arshid6social-dark-mode .arshid6social-mkt-search-input { color:#f1f5f9; }

/* Responsive */
@media (max-width:640px) {
	.arshid6social-mkt-hero { flex-direction:column; align-items:stretch; }
	.arshid6social-mkt-post-btn { justify-content:center; }
	.arshid6social-mkt-cat-grid { grid-template-columns:repeat(auto-fill,minmax(88px,1fr)); }
	.arshid6social-mkt-grid { grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); }
	.arshid6social-mkt-grid[data-view="list"] .arshid6social-mkt-card-img { width:100px; }
}
</style>

<?php /* ── JavaScript ──────────────────────────────────────────────────────── */ ?>
<script>
(function () {
	'use strict';

	const wrap    = document.getElementById( 'arshid6social-marketplace' );
	if ( ! wrap ) return;

	const grid    = document.getElementById( 'arshid6social-mkt-listings-grid' );
	const empty   = document.getElementById( 'arshid6social-mkt-listings-empty' );
	const moreWrap= document.getElementById( 'arshid6social-mkt-listings-more' );
	const moreBtn = document.getElementById( 'arshid6social-mkt-load-more-btn' );
	const AJAX    = wrap.dataset.ajax;

	let currentPage = 1;
	let isLoading   = false;
	let hasMore     = false;
	let currentQ    = grid.dataset.q    || '';
	let currentCat  = grid.dataset.cat  || '0';
	let currentSort = grid.dataset.sort || 'newest';
	let currentView = 'grid';

	// ── Fetch listings via WordPress AJAX ────────────────────────────────────
	async function fetchListings( page, append ) {
		if ( isLoading ) return;
		isLoading = true;

		if ( ! append ) {
			showSkeletons();
			empty.hidden    = true;
			moreWrap.hidden = true;
		} else {
			moreBtn.textContent = '<?php echo esc_js( __( 'Loading…', 'social-network-6' ) ); ?>';
			moreBtn.disabled    = true;
		}

		try {
			const fd = new FormData();
			fd.append( 'action', 'arshid6social_mkt_get_listings' );
			fd.append( 'q',      currentQ );
			fd.append( 'cat',    currentCat );
			fd.append( 'sort',   currentSort );
			fd.append( 'page',   page );

			const res  = await fetch( AJAX, { method: 'POST', body: fd } );
			const json = await res.json();

			if ( ! append ) clearGrid();

			if ( json.success && json.data.listings && json.data.listings.length ) {
				json.data.listings.forEach( listing => appendCard( listing ) );
				hasMore     = json.data.has_more || false;
				currentPage = page;
				moreWrap.hidden = ! hasMore;
			} else if ( ! append ) {
				empty.hidden = false;
			}
		} catch ( err ) {
			clearGrid();
			empty.hidden = false;
		} finally {
			isLoading           = false;
			moreBtn.textContent = '<?php echo esc_js( __( 'Load More', 'social-network-6' ) ); ?>';
			moreBtn.disabled    = false;
		}
	}

	function showSkeletons() {
		clearGrid();
		for ( let i = 0; i < 6; i++ ) {
			const el = document.createElement( 'div' );
			el.className = 'arshid6social-mkt-card arshid6social-mkt-skeleton';
			el.setAttribute( 'aria-hidden', 'true' );
			el.innerHTML = '<div class="arshid6social-mkt-card-img arshid6social-skeleton-box"></div>' +
				'<div class="arshid6social-mkt-card-body">' +
				'<div class="arshid6social-skeleton-line arshid6social-skeleton-line--short"></div>' +
				'<div class="arshid6social-skeleton-line"></div>' +
				'<div class="arshid6social-skeleton-line arshid6social-skeleton-line--thin"></div>' +
				'</div>';
			grid.appendChild( el );
		}
	}

	function clearGrid() {
		grid.innerHTML = '';
	}

	function appendCard( listing ) {
		const a = document.createElement( 'a' );
		a.href      = listing.url || '#';
		a.className = 'arshid6social-mkt-card';

		let badge = '';
		if ( listing.is_free )       badge = '<span class="arshid6social-mkt-card-badge arshid6social-mkt-card-badge--free"><?php echo esc_js( __( 'Free', 'social-network-6' ) ); ?></span>';
		else if ( listing.status === 'sold' ) badge = '<span class="arshid6social-mkt-card-badge arshid6social-mkt-card-badge--sold"><?php echo esc_js( __( 'Sold', 'social-network-6' ) ); ?></span>';
		else if ( listing.is_negotiable ) badge = '<span class="arshid6social-mkt-card-badge arshid6social-mkt-card-badge--negot"><?php echo esc_js( __( 'Negotiable', 'social-network-6' ) ); ?></span>';

		const imgHtml = listing.thumb
			? '<img src="' + escAttr( listing.thumb ) + '" alt="' + escAttr( listing.title ) + '" loading="lazy">'
			: '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#cbd5e1;font-size:2rem">🏷️</div>';

		a.innerHTML =
			'<div class="arshid6social-mkt-card-img">' + imgHtml + '</div>' +
			'<div class="arshid6social-mkt-card-body">' +
				'<p class="arshid6social-mkt-card-price">' + escHtml( listing.price_formatted ) + ' ' + badge + '</p>' +
				'<p class="arshid6social-mkt-card-title">' + escHtml( listing.title ) + '</p>' +
				'<div class="arshid6social-mkt-card-meta">' +
					'<span>' + escHtml( listing.location_city || '' ) + '</span>' +
					( listing.location_city ? '<span>·</span>' : '' ) +
					'<span>' + escHtml( listing.date_relative || '' ) + '</span>' +
				'</div>' +
			'</div>';

		grid.appendChild( a );
	}

	function escHtml( s ) {
		const d = document.createElement( 'div' );
		d.textContent = s || '';
		return d.innerHTML;
	}
	function escAttr( s ) {
		return ( s || '' ).replace( /"/g, '&quot;' );
	}

	// ── Event: sort change ────────────────────────────────────────────────────
	const sortSelect = wrap.querySelector( '.arshid6social-mkt-sort-select' );
	if ( sortSelect ) {
		sortSelect.addEventListener( 'change', () => {
			currentSort = sortSelect.value;
			fetchListings( 1, false );
		} );
	}

	// ── Event: view toggle ────────────────────────────────────────────────────
	wrap.querySelectorAll( '.arshid6social-mkt-view-btn' ).forEach( btn => {
		btn.addEventListener( 'click', () => {
			wrap.querySelectorAll( '.arshid6social-mkt-view-btn' ).forEach( b => b.classList.remove( 'arshid6social-mkt-view-btn--active' ) );
			btn.classList.add( 'arshid6social-mkt-view-btn--active' );
			currentView = btn.dataset.view;
			grid.dataset.view = currentView;
			localStorage.setItem( 'arshid6social_mkt_view', currentView );
		} );
	} );

	// Restore saved view preference.
	const savedView = localStorage.getItem( 'arshid6social_mkt_view' );
	if ( savedView ) {
		grid.dataset.view = savedView;
		wrap.querySelectorAll( '.arshid6social-mkt-view-btn' ).forEach( btn => {
			btn.classList.toggle( 'arshid6social-mkt-view-btn--active', btn.dataset.view === savedView );
		} );
	}

	// ── Event: load more ──────────────────────────────────────────────────────
	if ( moreBtn ) {
		moreBtn.addEventListener( 'click', () => fetchListings( currentPage + 1, true ) );
	}

	// ── Initial load ──────────────────────────────────────────────────────────
	fetchListings( 1, false );

})();
</script>
