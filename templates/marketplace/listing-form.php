<?php
/**
 * Listing creation wizard template.
 *
 * Variables:
 *  $categories  array   Flat list from Marketplace_Listings::get_category_select_options()
 *  $max_photos  int     Max number of photos allowed
 *  $max_mb      int     Max photo size in MB
 *
 * @package Arshid6Social\Components\Marketplace
 */

defined( 'ABSPATH' ) || exit;

$nonce       = wp_create_nonce( 'arshid6social_marketplace' );
$form_token  = wp_generate_uuid4();
$ajax_url    = admin_url( 'admin-ajax.php' );
$base_url    = get_permalink( (int) get_option( 'arshid6social_page_marketplace', 0 ) )
	?: home_url( '/' . get_option( 'arshid6social_marketplace_slug', 'marketplace' ) . '/' );

$step_labels = array(
	1 => __( 'Category', '6arshid social community' ),
	2 => __( 'Photos', '6arshid social community' ),
	3 => __( 'Details', '6arshid social community' ),
	4 => __( 'Location', '6arshid social community' ),
	5 => __( 'Review', '6arshid social community' ),
);

$conditions = array(
	'new'      => __( 'New', '6arshid social community' ),
	'like_new' => __( 'Like New', '6arshid social community' ),
	'good'     => __( 'Good', '6arshid social community' ),
	'fair'     => __( 'Fair', '6arshid social community' ),
	'poor'     => __( 'Poor', '6arshid social community' ),
);
?>

<div class="arshid6social-mkt-wrap arshid6social-mkt-form-page" id="arshid6social-listing-wizard"
	data-ajax="<?php echo esc_url( $ajax_url ); ?>"
	data-nonce="<?php echo esc_attr( $nonce ); ?>"
	data-token="<?php echo esc_attr( $form_token ); ?>"
	data-max-photos="<?php echo esc_attr( $max_photos ); ?>"
	data-max-mb="<?php echo esc_attr( $max_mb ); ?>"
	data-back="<?php echo esc_url( $base_url ); ?>">

	<?php /* ── Back link ─────────────────────────────────────────────────── */ ?>
	<a href="<?php echo esc_url( $base_url ); ?>" class="arshid6social-mkt-back-link">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
		<?php esc_html_e( 'Back to Marketplace', '6arshid social community' ); ?>
	</a>

	<h1 class="arshid6social-mkt-page-title"><?php esc_html_e( 'Post a Listing', '6arshid social community' ); ?></h1>

	<?php /* ── Step progress ───────────────────────────────────────────────── */ ?>
	<div class="arshid6social-mkt-steps" role="tablist" aria-label="<?php esc_attr_e( 'Listing wizard steps', '6arshid social community' ); ?>">
		<?php foreach ( $step_labels as $num => $label ) : ?>
		<div class="arshid6social-mkt-step-indicator<?php echo 1 === $num ? ' is-active' : ''; ?>"
			data-step="<?php echo esc_attr( $num ); ?>"
			role="tab"
			aria-selected="<?php echo 1 === $num ? 'true' : 'false'; ?>">
			<span class="arshid6social-mkt-step-num"><?php echo esc_html( $num ); ?></span>
			<span class="arshid6social-mkt-step-label"><?php echo esc_html( $label ); ?></span>
		</div>
		<?php endforeach; ?>
	</div>

	<?php /* ── Global error bar ────────────────────────────────────────────── */ ?>
	<div id="arshid6social-mkt-form-error" class="arshid6social-mkt-form-error" hidden role="alert"></div>

	<form id="arshid6social-mkt-listing-form" class="arshid6social-mkt-form" novalidate>
		<input type="hidden" name="nonce"  value="<?php echo esc_attr( $nonce ); ?>">
		<input type="hidden" name="token"  value="<?php echo esc_attr( $form_token ); ?>">
		<input type="hidden" name="action" value="arshid6social_mkt_save_listing">
		<input type="hidden" name="submit_action" id="arshid6social-mkt-submit-action" value="draft">

		<?php /* ══ STEP 1: Category & Title ══════════════════════════════════ */ ?>
		<div class="arshid6social-mkt-panel is-active" data-panel="1">
			<h2 class="arshid6social-mkt-panel-title"><?php esc_html_e( 'What are you selling?', '6arshid social community' ); ?></h2>

			<div class="arshid6social-mkt-field">
				<label class="arshid6social-mkt-label" for="mkt-title">
					<?php esc_html_e( 'Title', '6arshid social community' ); ?>
					<span class="arshid6social-mkt-required" aria-hidden="true">*</span>
				</label>
				<input type="text" id="mkt-title" name="title" class="arshid6social-mkt-input"
					maxlength="200"
					placeholder="<?php esc_attr_e( 'e.g. iPhone 15 Pro 256 GB, Black', '6arshid social community' ); ?>"
					required
					aria-describedby="mkt-title-hint">
				<span id="mkt-title-hint" class="arshid6social-mkt-hint">
					<span id="mkt-title-count">0</span>/200
				</span>
			</div>

			<div class="arshid6social-mkt-field">
				<label class="arshid6social-mkt-label" for="mkt-category">
					<?php esc_html_e( 'Category', '6arshid social community' ); ?>
					<span class="arshid6social-mkt-required" aria-hidden="true">*</span>
				</label>
				<select id="mkt-category" name="category_id" class="arshid6social-mkt-select" required>
					<option value=""><?php esc_html_e( '— Select a category —', '6arshid social community' ); ?></option>
					<?php foreach ( $categories as $cat ) :
						$indent = str_repeat( '　', $cat['depth'] );
					?>
					<option value="<?php echo esc_attr( $cat['id'] ); ?>">
						<?php echo esc_html( $indent . $cat['icon'] . ' ' . $cat['name'] ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="arshid6social-mkt-panel-nav">
				<span></span>
				<button type="button" class="arshid6social-mkt-btn arshid6social-mkt-btn--primary arshid6social-mkt-next" data-next="2">
					<?php esc_html_e( 'Next: Photos', '6arshid social community' ); ?>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
				</button>
			</div>
		</div>

		<?php /* ══ STEP 2: Photos ════════════════════════════════════════════ */ ?>
		<div class="arshid6social-mkt-panel" data-panel="2">
			<h2 class="arshid6social-mkt-panel-title"><?php esc_html_e( 'Add Photos', '6arshid social community' ); ?></h2>
			<p class="arshid6social-mkt-panel-desc">
				<?php printf(
					/* translators: 1: max photos, 2: max MB */
					esc_html__( 'Upload up to %1$d photos (max %2$d MB each). The first photo will be the cover.', '6arshid social community' ),
					absint( $max_photos ),
					absint( $max_mb )
				); ?>
			</p>

			<div id="arshid6social-mkt-photo-drop" class="arshid6social-mkt-photo-drop" tabindex="0" role="button"
				aria-label="<?php esc_attr_e( 'Upload photos — click or drag and drop', '6arshid social community' ); ?>">
				<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
				<p><?php esc_html_e( 'Click to add photos or drag &amp; drop here', '6arshid social community' ); ?></p>
				<span class="arshid6social-mkt-photo-count-label" id="arshid6social-mkt-photo-count-label">
					<?php printf(
						/* translators: 1: current count, 2: max count */
						esc_html__( '%1$d / %2$d photos', '6arshid social community' ),
						0,
						absint( $max_photos )
					); ?>
				</span>
				<input type="file" id="arshid6social-mkt-photo-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple aria-hidden="true">
			</div>

			<div id="arshid6social-mkt-photo-grid" class="arshid6social-mkt-photo-grid" aria-label="<?php esc_attr_e( 'Uploaded photos', '6arshid social community' ); ?>"></div>

			<div id="arshid6social-mkt-photo-error" class="arshid6social-mkt-form-error" hidden role="alert"></div>

			<div class="arshid6social-mkt-panel-nav">
				<button type="button" class="arshid6social-mkt-btn arshid6social-mkt-btn--outline arshid6social-mkt-prev" data-prev="1">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
					<?php esc_html_e( 'Back', '6arshid social community' ); ?>
				</button>
				<button type="button" class="arshid6social-mkt-btn arshid6social-mkt-btn--primary arshid6social-mkt-next" data-next="3">
					<?php esc_html_e( 'Next: Details', '6arshid social community' ); ?>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
				</button>
			</div>
		</div>

		<?php /* ══ STEP 3: Details ══════════════════════════════════════════ */ ?>
		<div class="arshid6social-mkt-panel" data-panel="3">
			<h2 class="arshid6social-mkt-panel-title"><?php esc_html_e( 'Listing Details', '6arshid social community' ); ?></h2>

			<?php /* Price row */ ?>
			<div class="arshid6social-mkt-field">
				<label class="arshid6social-mkt-label"><?php esc_html_e( 'Price', '6arshid social community' ); ?></label>

				<div class="arshid6social-mkt-price-row">
					<div class="arshid6social-mkt-price-input-wrap" id="arshid6social-mkt-price-wrap">
						<span class="arshid6social-mkt-currency-sym"><?php echo esc_html( get_option( 'arshid6social_marketplace_currency_symbol', '$' ) ); ?></span>
						<input type="number" id="mkt-price" name="price" class="arshid6social-mkt-input arshid6social-mkt-price-input"
							min="0" step="any"
							placeholder="0.00">
					</div>
					<label class="arshid6social-mkt-toggle-label" for="mkt-is-free">
						<input type="checkbox" id="mkt-is-free" name="is_free" class="arshid6social-mkt-toggle-cb" value="1">
						<span class="arshid6social-mkt-toggle-text"><?php esc_html_e( 'Free', '6arshid social community' ); ?></span>
					</label>
					<label class="arshid6social-mkt-toggle-label" id="arshid6social-mkt-neg-label" for="mkt-is-negotiable">
						<input type="checkbox" id="mkt-is-negotiable" name="is_negotiable" class="arshid6social-mkt-toggle-cb" value="1">
						<span class="arshid6social-mkt-toggle-text"><?php esc_html_e( 'Negotiable', '6arshid social community' ); ?></span>
					</label>
				</div>
			</div>

			<?php /* Condition */ ?>
			<div class="arshid6social-mkt-field">
				<label class="arshid6social-mkt-label"><?php esc_html_e( 'Condition', '6arshid social community' ); ?></label>
				<div class="arshid6social-mkt-condition-grid" role="group" aria-label="<?php esc_attr_e( 'Item condition', '6arshid social community' ); ?>">
					<?php foreach ( $conditions as $val => $label ) : ?>
					<label class="arshid6social-mkt-condition-opt">
						<input type="radio" name="item_condition" value="<?php echo esc_attr( $val ); ?>"
							<?php checked( $val, 'good' ); ?> class="arshid6social-mkt-condition-radio">
						<span><?php echo esc_html( $label ); ?></span>
					</label>
					<?php endforeach; ?>
				</div>
			</div>

			<?php /* Description */ ?>
			<div class="arshid6social-mkt-field">
				<label class="arshid6social-mkt-label" for="mkt-description">
					<?php esc_html_e( 'Description', '6arshid social community' ); ?>
				</label>
				<textarea id="mkt-description" name="description" class="arshid6social-mkt-textarea" rows="6"
					placeholder="<?php esc_attr_e( 'Describe your item — condition, dimensions, reason for selling, included accessories…', '6arshid social community' ); ?>"
					maxlength="5000"
					aria-describedby="mkt-desc-count"></textarea>
				<span id="mkt-desc-count" class="arshid6social-mkt-hint"><span id="mkt-desc-num">0</span>/5000</span>
			</div>

			<div class="arshid6social-mkt-panel-nav">
				<button type="button" class="arshid6social-mkt-btn arshid6social-mkt-btn--outline arshid6social-mkt-prev" data-prev="2">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
					<?php esc_html_e( 'Back', '6arshid social community' ); ?>
				</button>
				<button type="button" class="arshid6social-mkt-btn arshid6social-mkt-btn--primary arshid6social-mkt-next" data-next="4">
					<?php esc_html_e( 'Next: Location', '6arshid social community' ); ?>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
				</button>
			</div>
		</div>

		<?php /* ══ STEP 4: Location ══════════════════════════════════════════ */ ?>
		<div class="arshid6social-mkt-panel" data-panel="4">
			<h2 class="arshid6social-mkt-panel-title"><?php esc_html_e( 'Location', '6arshid social community' ); ?></h2>
			<p class="arshid6social-mkt-panel-desc"><?php esc_html_e( 'Your exact address is never shown. Only city/region is displayed to buyers.', '6arshid social community' ); ?></p>

			<div class="arshid6social-mkt-field">
				<label class="arshid6social-mkt-label" for="mkt-city"><?php esc_html_e( 'City / Region', '6arshid social community' ); ?></label>
				<input type="text" id="mkt-city" name="location_city" class="arshid6social-mkt-input"
					placeholder="<?php esc_attr_e( 'e.g. Tehran, New York…', '6arshid social community' ); ?>">
			</div>

			<div class="arshid6social-mkt-field">
				<label class="arshid6social-mkt-label" for="mkt-country"><?php esc_html_e( 'Country', '6arshid social community' ); ?></label>
				<select id="mkt-country" name="location_country" class="arshid6social-mkt-select">
					<option value=""><?php esc_html_e( '— Select country —', '6arshid social community' ); ?></option>
					<?php
					$countries = array(
						'IR' => 'Iran', 'US' => 'United States', 'GB' => 'United Kingdom',
						'DE' => 'Germany', 'FR' => 'France', 'AE' => 'UAE', 'SA' => 'Saudi Arabia',
						'TR' => 'Turkey', 'CA' => 'Canada', 'AU' => 'Australia', 'DK' => 'Denmark',
						'SE' => 'Sweden', 'NO' => 'Norway', 'PK' => 'Pakistan', 'AF' => 'Afghanistan',
					);
					foreach ( $countries as $code => $name ) :
					?>
					<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="arshid6social-mkt-field">
				<button type="button" id="arshid6social-mkt-geolocate" class="arshid6social-mkt-btn arshid6social-mkt-btn--outline arshid6social-mkt-btn--sm">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
					<?php esc_html_e( 'Use my current location', '6arshid social community' ); ?>
				</button>
				<span id="arshid6social-mkt-geo-status" class="arshid6social-mkt-hint" aria-live="polite"></span>
				<input type="hidden" name="lat" id="mkt-lat" value="">
				<input type="hidden" name="lng" id="mkt-lng" value="">
			</div>

			<div class="arshid6social-mkt-panel-nav">
				<button type="button" class="arshid6social-mkt-btn arshid6social-mkt-btn--outline arshid6social-mkt-prev" data-prev="3">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
					<?php esc_html_e( 'Back', '6arshid social community' ); ?>
				</button>
				<button type="button" class="arshid6social-mkt-btn arshid6social-mkt-btn--primary arshid6social-mkt-next" data-next="5">
					<?php esc_html_e( 'Review Listing', '6arshid social community' ); ?>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
				</button>
			</div>
		</div>

		<?php /* ══ STEP 5: Review & Publish ════════════════════════════════ */ ?>
		<div class="arshid6social-mkt-panel" data-panel="5">
			<h2 class="arshid6social-mkt-panel-title"><?php esc_html_e( 'Review Your Listing', '6arshid social community' ); ?></h2>

			<div id="arshid6social-mkt-preview-card" class="arshid6social-mkt-preview-card">
				<div class="arshid6social-mkt-preview-img" id="arshid6social-mkt-preview-img">
					<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
				</div>
				<div class="arshid6social-mkt-preview-body">
					<p class="arshid6social-mkt-preview-price" id="arshid6social-mkt-preview-price">—</p>
					<p class="arshid6social-mkt-preview-title" id="arshid6social-mkt-preview-title"><?php esc_html_e( '(no title)', '6arshid social community' ); ?></p>
					<p class="arshid6social-mkt-preview-meta" id="arshid6social-mkt-preview-meta"></p>
					<p class="arshid6social-mkt-preview-desc" id="arshid6social-mkt-preview-desc"></p>
				</div>
			</div>

			<?php
			$moderation = get_option( 'arshid6social_marketplace_moderation', 'auto' );
			if ( 'manual' === $moderation ) : ?>
			<div class="arshid6social-mkt-moderation-notice">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				<?php esc_html_e( 'Your listing will be reviewed before it appears publicly.', '6arshid social community' ); ?>
			</div>
			<?php endif; ?>

			<div class="arshid6social-mkt-panel-nav arshid6social-mkt-panel-nav--publish">
				<button type="button" class="arshid6social-mkt-btn arshid6social-mkt-btn--outline arshid6social-mkt-prev" data-prev="4">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
					<?php esc_html_e( 'Back', '6arshid social community' ); ?>
				</button>
				<div class="arshid6social-mkt-publish-actions">
					<button type="button" id="arshid6social-mkt-save-draft" class="arshid6social-mkt-btn arshid6social-mkt-btn--outline">
						<?php esc_html_e( 'Save as Draft', '6arshid social community' ); ?>
					</button>
					<button type="button" id="arshid6social-mkt-publish" class="arshid6social-mkt-btn arshid6social-mkt-btn--primary">
						<span class="arshid6social-mkt-btn-text">
							<?php echo 'manual' === $moderation
								? esc_html__( 'Submit for Review', '6arshid social community' )
								: esc_html__( 'Publish Listing', '6arshid social community' ); ?>
						</span>
						<span class="arshid6social-mkt-btn-spinner" hidden aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="arshid6social-spin" aria-label="<?php esc_attr_e( 'Loading', '6arshid social community' ); ?>"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
						</span>
					</button>
				</div>
			</div>
		</div>

	</form>
</div><!-- .arshid6social-listing-wizard -->

<?php
/* ── Styles ──────────────────────────────────────────────────────────── */
$mkt_primary_color = esc_attr( get_option( 'arshid6social_primary_color', '#2563eb' ) );
$mkt_form_css      = '
:root {
	--mkt-primary:  ' . $mkt_primary_color . ';
	--mkt-radius:   10px;
	--mkt-border:   #e2e8f0;
	--mkt-text:     #0f172a;
	--mkt-muted:    #64748b;
	--mkt-bg:       #f8fafc;
	--mkt-error:    #dc2626;
	--mkt-success:  #16a34a;
}
.arshid6social-dark-mode { --mkt-border:#334155; --mkt-text:#f1f5f9; --mkt-muted:#94a3b8; --mkt-bg:#1e293b; }

/* Wrap */
.arshid6social-mkt-form-page { max-width:680px; margin:0 auto; padding:0 16px 60px; }
.arshid6social-mkt-back-link { display:inline-flex; align-items:center; gap:6px; color:var(--mkt-muted); font-size:.875rem; text-decoration:none; margin:20px 0 12px; }
.arshid6social-mkt-back-link:hover { color:var(--mkt-primary); }
.arshid6social-mkt-page-title { font-size:1.5rem; font-weight:800; color:var(--mkt-text); margin:0 0 24px; }

/* Steps indicator */
.arshid6social-mkt-steps { display:flex; gap:0; margin-bottom:32px; border-radius:var(--mkt-radius); overflow:hidden; border:1px solid var(--mkt-border); }
.arshid6social-mkt-step-indicator { flex:1; display:flex; flex-direction:column; align-items:center; padding:12px 8px; background:#fff; cursor:default; transition:background .15s; border-right:1px solid var(--mkt-border); position:relative; }
.arshid6social-mkt-step-indicator:last-child { border-right:none; }
.arshid6social-mkt-step-indicator.is-done { background:#f0fdf4; }
.arshid6social-mkt-step-indicator.is-active { background:var(--mkt-primary); }
.arshid6social-mkt-step-num { width:24px; height:24px; border-radius:50%; border:2px solid var(--mkt-border); display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; color:var(--mkt-muted); background:#fff; margin-bottom:4px; }
.arshid6social-mkt-step-indicator.is-active .arshid6social-mkt-step-num { border-color:#fff; color:var(--mkt-primary); }
.arshid6social-mkt-step-indicator.is-done .arshid6social-mkt-step-num { border-color:var(--mkt-success); color:var(--mkt-success); }
.arshid6social-mkt-step-label { font-size:.7rem; font-weight:600; color:var(--mkt-muted); }
.arshid6social-mkt-step-indicator.is-active .arshid6social-mkt-step-label { color:#fff; }
.arshid6social-mkt-step-indicator.is-done .arshid6social-mkt-step-label { color:var(--mkt-success); }
.arshid6social-dark-mode .arshid6social-mkt-step-indicator { background:#1e293b; }
.arshid6social-dark-mode .arshid6social-mkt-step-indicator.is-active { background:var(--mkt-primary); }

/* Error bar */
.arshid6social-mkt-form-error { background:#fef2f2; border:1px solid #fecaca; color:var(--mkt-error); padding:12px 16px; border-radius:var(--mkt-radius); margin-bottom:16px; font-size:.9rem; }

/* Panel */
.arshid6social-mkt-panel { display:none; }
.arshid6social-mkt-panel.is-active { display:block; }
.arshid6social-mkt-panel-title { font-size:1.125rem; font-weight:700; color:var(--mkt-text); margin:0 0 8px; }
.arshid6social-mkt-panel-desc { font-size:.875rem; color:var(--mkt-muted); margin:0 0 20px; }

/* Fields */
.arshid6social-mkt-field { margin-bottom:20px; }
.arshid6social-mkt-label { display:block; font-size:.875rem; font-weight:600; color:var(--mkt-text); margin-bottom:6px; }
.arshid6social-mkt-required { color:var(--mkt-error); margin-left:2px; }
.arshid6social-mkt-input, .arshid6social-mkt-select, .arshid6social-mkt-textarea {
	width:100%; padding:10px 14px; border:2px solid var(--mkt-border); border-radius:var(--mkt-radius);
	font-size:.9375rem; color:var(--mkt-text); background:#fff; transition:border-color .15s; box-sizing:border-box;
}
.arshid6social-mkt-input:focus, .arshid6social-mkt-select:focus, .arshid6social-mkt-textarea:focus { outline:none; border-color:var(--mkt-primary); }
.arshid6social-mkt-textarea { resize:vertical; min-height:120px; }
.arshid6social-mkt-hint { font-size:.78rem; color:var(--mkt-muted); margin-top:4px; display:block; }
.arshid6social-dark-mode .arshid6social-mkt-input,
.arshid6social-dark-mode .arshid6social-mkt-select,
.arshid6social-dark-mode .arshid6social-mkt-textarea { background:#0f172a; color:#f1f5f9; }

/* Price row */
.arshid6social-mkt-price-row { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.arshid6social-mkt-price-input-wrap { position:relative; display:flex; align-items:center; flex:1; min-width:120px; }
.arshid6social-mkt-currency-sym { position:absolute; left:14px; color:var(--mkt-muted); font-weight:600; pointer-events:none; z-index:1; }
.arshid6social-mkt-price-input { padding-left:36px; }
.arshid6social-mkt-toggle-label { display:flex; align-items:center; gap:6px; cursor:pointer; font-size:.875rem; color:var(--mkt-text); white-space:nowrap; }
.arshid6social-mkt-toggle-cb { width:16px; height:16px; cursor:pointer; }

/* Condition grid */
.arshid6social-mkt-condition-grid { display:flex; flex-wrap:wrap; gap:8px; }
.arshid6social-mkt-condition-opt { display:flex; align-items:center; cursor:pointer; }
.arshid6social-mkt-condition-radio { display:none; }
.arshid6social-mkt-condition-opt span {
	padding:8px 16px; border:2px solid var(--mkt-border); border-radius:20px;
	font-size:.875rem; font-weight:500; color:var(--mkt-muted); transition:all .15s; white-space:nowrap;
}
.arshid6social-mkt-condition-radio:checked + span { border-color:var(--mkt-primary); color:var(--mkt-primary); background:color-mix(in srgb,var(--mkt-primary) 8%,transparent); }

/* Photo upload zone */
.arshid6social-mkt-photo-drop {
	border:2px dashed var(--mkt-border); border-radius:var(--mkt-radius);
	padding:40px 20px; text-align:center; cursor:pointer; transition:border-color .15s, background .15s;
	margin-bottom:16px; position:relative;
}
.arshid6social-mkt-photo-drop:hover, .arshid6social-mkt-photo-drop.is-dragover { border-color:var(--mkt-primary); background:color-mix(in srgb,var(--mkt-primary) 5%,transparent); }
.arshid6social-mkt-photo-drop p { margin:8px 0 4px; font-size:.875rem; color:var(--mkt-muted); }
.arshid6social-mkt-photo-count-label { font-size:.78rem; color:var(--mkt-muted); }
#arshid6social-mkt-photo-input { position:absolute; inset:0; opacity:0; cursor:pointer; }

/* Photo grid */
.arshid6social-mkt-photo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(100px,1fr)); gap:10px; margin-bottom:16px; }
.arshid6social-mkt-photo-thumb { position:relative; border-radius:8px; overflow:hidden; aspect-ratio:1; background:var(--mkt-bg); border:2px solid transparent; }
.arshid6social-mkt-photo-thumb.is-primary { border-color:var(--mkt-primary); }
.arshid6social-mkt-photo-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.arshid6social-mkt-photo-thumb .arshid6social-mkt-photo-primary { position:absolute; bottom:4px; left:4px; background:var(--mkt-primary); color:#fff; font-size:.65rem; font-weight:700; padding:2px 6px; border-radius:4px; }
.arshid6social-mkt-photo-thumb .arshid6social-mkt-photo-del {
	position:absolute; top:4px; right:4px; width:22px; height:22px; border-radius:50%;
	background:rgba(0,0,0,.55); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; color:#fff; font-size:14px; line-height:1;
}
.arshid6social-mkt-photo-thumb .arshid6social-mkt-photo-del:hover { background:var(--mkt-error); }
.arshid6social-mkt-photo-uploading::after { content:""; position:absolute; inset:0; background:rgba(255,255,255,.6); }

/* Buttons */
.arshid6social-mkt-btn { display:inline-flex; align-items:center; gap:6px; padding:11px 22px; border-radius:var(--mkt-radius); font-size:.9375rem; font-weight:600; cursor:pointer; transition:all .15s; border:2px solid transparent; text-decoration:none; }
.arshid6social-mkt-btn--primary { background:var(--mkt-primary); color:#fff; border-color:var(--mkt-primary); }
.arshid6social-mkt-btn--primary:hover { opacity:.88; }
.arshid6social-mkt-btn--outline { background:transparent; color:var(--mkt-text); border-color:var(--mkt-border); }
.arshid6social-mkt-btn--outline:hover { border-color:var(--mkt-primary); color:var(--mkt-primary); }
.arshid6social-mkt-btn--sm { padding:8px 14px; font-size:.8125rem; }
.arshid6social-mkt-btn:disabled { opacity:.55; cursor:not-allowed; }

/* Nav row */
.arshid6social-mkt-panel-nav { display:flex; justify-content:space-between; align-items:center; margin-top:28px; padding-top:20px; border-top:1px solid var(--mkt-border); gap:12px; }
.arshid6social-mkt-panel-nav--publish { flex-wrap:wrap; }
.arshid6social-mkt-publish-actions { display:flex; gap:10px; flex-wrap:wrap; }

/* Preview card (Step 5) */
.arshid6social-mkt-preview-card { display:flex; gap:16px; background:#fff; border:1px solid var(--mkt-border); border-radius:var(--mkt-radius); overflow:hidden; margin-bottom:20px; }
.arshid6social-mkt-preview-img { width:140px; flex-shrink:0; background:var(--mkt-bg); display:flex; align-items:center; justify-content:center; min-height:110px; overflow:hidden; }
.arshid6social-mkt-preview-img img { width:100%; height:100%; object-fit:cover; }
.arshid6social-mkt-preview-body { padding:16px; }
.arshid6social-mkt-preview-price { font-size:1.1rem; font-weight:700; color:var(--mkt-primary); margin:0 0 4px; }
.arshid6social-mkt-preview-title { font-size:.9375rem; font-weight:600; color:var(--mkt-text); margin:0 0 6px; }
.arshid6social-mkt-preview-meta { font-size:.78rem; color:var(--mkt-muted); margin:0 0 8px; }
.arshid6social-mkt-preview-desc { font-size:.8125rem; color:var(--mkt-muted); margin:0; max-height:60px; overflow:hidden; }
.arshid6social-dark-mode .arshid6social-mkt-preview-card { background:#1e293b; }

/* Moderation notice */
.arshid6social-mkt-moderation-notice { display:flex; align-items:center; gap:8px; background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:12px 16px; border-radius:var(--mkt-radius); font-size:.875rem; margin-bottom:20px; }

/* Geolocate */
#arshid6social-mkt-geo-status { margin-left:8px; }

/* Spinner */
.arshid6social-spin { animation:arshid6social-rotate 1s linear infinite; }
@keyframes arshid6social-rotate { to { transform:rotate(360deg); } }

/* RTL */
[dir="rtl"] .arshid6social-mkt-currency-sym { left:auto; right:14px; }
[dir="rtl"] .arshid6social-mkt-price-input  { padding-left:14px; padding-right:36px; }
[dir="rtl"] .arshid6social-mkt-photo-thumb .arshid6social-mkt-photo-del { right:auto; left:4px; }
[dir="rtl"] .arshid6social-mkt-photo-thumb .arshid6social-mkt-photo-primary { left:auto; right:4px; }

/* Responsive */
@media (max-width:520px) {
	.arshid6social-mkt-steps { gap:0; }
	.arshid6social-mkt-step-label { display:none; }
	.arshid6social-mkt-step-indicator { padding:10px 4px; }
	.arshid6social-mkt-preview-card { flex-direction:column; }
	.arshid6social-mkt-preview-img { width:100%; min-height:160px; }
	.arshid6social-mkt-publish-actions { width:100%; }
	.arshid6social-mkt-publish-actions .arshid6social-mkt-btn { flex:1; justify-content:center; }
}
';
wp_add_inline_style( 'arshid6social-main', $mkt_form_css );

/* ── JavaScript ──────────────────────────────────────────────────────── */
?>
<script>
(function () {
'use strict';

const wizard   = document.getElementById( 'arshid6social-listing-wizard' );
if ( ! wizard ) return;

const AJAX     = wizard.dataset.ajax;
const NONCE    = wizard.dataset.nonce;
const TOKEN    = wizard.dataset.token;
const MAX_PH   = parseInt( wizard.dataset.maxPhotos, 10 ) || 10;
const MAX_MB   = parseInt( wizard.dataset.maxMb, 10 ) || 5;
const BACK_URL = wizard.dataset.back;

const form     = document.getElementById( 'arshid6social-mkt-listing-form' );
const errBar   = document.getElementById( 'arshid6social-mkt-form-error' );

// ── Step navigation ────────────────────────────────────────────────────────
let currentStep = 1;
const panels    = document.querySelectorAll( '.arshid6social-mkt-panel' );
const indicators= document.querySelectorAll( '.arshid6social-mkt-step-indicator' );

function goToStep( n ) {
	if ( n < 1 || n > 5 ) return;
	if ( n > currentStep && ! validateStep( currentStep ) ) return;

	panels.forEach( p => p.classList.remove( 'is-active' ) );
	const target = document.querySelector( '.arshid6social-mkt-panel[data-panel="' + n + '"]' );
	if ( target ) target.classList.add( 'is-active' );

	indicators.forEach( ind => {
		const s = parseInt( ind.dataset.step, 10 );
		ind.classList.remove( 'is-active', 'is-done' );
		ind.setAttribute( 'aria-selected', 'false' );
		if ( s < n )  { ind.classList.add( 'is-done' ); ind.querySelector( '.arshid6social-mkt-step-num' ).textContent = '✓'; }
		if ( s === n ) { ind.classList.add( 'is-active' ); ind.setAttribute( 'aria-selected', 'true' ); ind.querySelector( '.arshid6social-mkt-step-num' ).textContent = s; }
		if ( s > n )  { ind.querySelector( '.arshid6social-mkt-step-num' ).textContent = s; }
	} );

	currentStep = n;
	if ( n === 5 ) buildPreview();
	window.scrollTo( { top: wizard.getBoundingClientRect().top + window.scrollY - 20, behavior: 'smooth' } );
}

document.querySelectorAll( '.arshid6social-mkt-next' ).forEach( btn => {
	btn.addEventListener( 'click', () => goToStep( parseInt( btn.dataset.next, 10 ) ) );
} );
document.querySelectorAll( '.arshid6social-mkt-prev' ).forEach( btn => {
	btn.addEventListener( 'click', () => goToStep( parseInt( btn.dataset.prev, 10 ) ) );
} );

// ── Validation per step ────────────────────────────────────────────────────
function showErr( msg ) {
	errBar.textContent = msg;
	errBar.hidden = false;
	errBar.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
}
function clearErr() { errBar.hidden = true; errBar.textContent = ''; }

function validateStep( step ) {
	clearErr();
	if ( step === 1 ) {
		const title = document.getElementById( 'mkt-title' ).value.trim();
		const cat   = document.getElementById( 'mkt-category' ).value;
		if ( ! title ) { showErr( '<?php echo esc_js( __( 'Please enter a title for your listing.', '6arshid social community' ) ); ?>' ); return false; }
		if ( ! cat )   { showErr( '<?php echo esc_js( __( 'Please select a category.', '6arshid social community' ) ); ?>' ); return false; }
	}
	return true;
}

// ── Character counters ─────────────────────────────────────────────────────
const titleInput = document.getElementById( 'mkt-title' );
const titleCount = document.getElementById( 'mkt-title-count' );
if ( titleInput && titleCount ) {
	titleInput.addEventListener( 'input', () => { titleCount.textContent = titleInput.value.length; } );
}
const descInput = document.getElementById( 'mkt-description' );
const descCount = document.getElementById( 'mkt-desc-num' );
if ( descInput && descCount ) {
	descInput.addEventListener( 'input', () => { descCount.textContent = descInput.value.length; } );
}

// ── Price / Free / Negotiable toggling ────────────────────────────────────
const isFree    = document.getElementById( 'mkt-is-free' );
const priceWrap = document.getElementById( 'arshid6social-mkt-price-wrap' );
const negLabel  = document.getElementById( 'arshid6social-mkt-neg-label' );
if ( isFree ) {
	isFree.addEventListener( 'change', () => {
		const free = isFree.checked;
		if ( priceWrap ) priceWrap.style.opacity = free ? '0.4' : '1';
		if ( priceWrap ) priceWrap.querySelector( 'input' ).disabled = free;
		if ( negLabel )  negLabel.style.opacity = free ? '0.4' : '1';
		if ( free && negLabel ) negLabel.querySelector( 'input' ).checked = false;
	} );
}

// ── Photo upload ───────────────────────────────────────────────────────────
const dropZone   = document.getElementById( 'arshid6social-mkt-photo-drop' );
const photoInput = document.getElementById( 'arshid6social-mkt-photo-input' );
const photoGrid  = document.getElementById( 'arshid6social-mkt-photo-grid' );
const photoErr   = document.getElementById( 'arshid6social-mkt-photo-error' );
const cntLabel   = document.getElementById( 'arshid6social-mkt-photo-count-label' );

let uploadedPhotos = []; // [{id, url, thumb}]

function updatePhotoCount() {
	if ( cntLabel ) cntLabel.textContent = uploadedPhotos.length + ' / ' + MAX_PH + ' <?php echo esc_js( __( 'photos', '6arshid social community' ) ); ?>';
	if ( photoInput ) photoInput.disabled = uploadedPhotos.length >= MAX_PH;
}

function showPhotoErr( msg ) {
	photoErr.textContent = msg;
	photoErr.hidden = false;
}
function clearPhotoErr() { photoErr.hidden = true; photoErr.textContent = ''; }

function renderPhotoGrid() {
	photoGrid.innerHTML = '';
	uploadedPhotos.forEach( ( p, i ) => {
		const div = document.createElement( 'div' );
		div.className = 'arshid6social-mkt-photo-thumb' + ( i === 0 ? ' is-primary' : '' );
		div.dataset.id = p.id;

		const img = document.createElement( 'img' );
		img.src = p.thumb;
		img.alt = '<?php echo esc_js( __( 'Listing photo', '6arshid social community' ) ); ?>';
		div.appendChild( img );

		if ( i === 0 ) {
			const badge = document.createElement( 'span' );
			badge.className = 'arshid6social-mkt-photo-primary';
			badge.textContent = '<?php echo esc_js( __( 'Cover', '6arshid social community' ) ); ?>';
			div.appendChild( badge );
		}

		const del = document.createElement( 'button' );
		del.type = 'button';
		del.className = 'arshid6social-mkt-photo-del';
		del.setAttribute( 'aria-label', '<?php echo esc_js( __( 'Remove photo', '6arshid social community' ) ); ?>' );
		del.innerHTML = '×';
		del.addEventListener( 'click', () => removePhoto( p.id ) );
		div.appendChild( del );

		photoGrid.appendChild( div );
	} );
	updatePhotoCount();
}

async function uploadPhoto( file ) {
	if ( uploadedPhotos.length >= MAX_PH ) {
		showPhotoErr( '<?php echo esc_js( __( 'Maximum photos reached.', '6arshid social community' ) ); ?>' );
		return;
	}
	if ( file.size > MAX_MB * 1024 * 1024 ) {
		showPhotoErr( '<?php echo esc_js( __( 'File is too large.', '6arshid social community' ) ); ?>' );
		return;
	}

	// Optimistic preview
	const tempId = 'temp_' + Date.now();
	const reader = new FileReader();
	reader.onload = e => {
		uploadedPhotos.push( { id: tempId, url: e.target.result, thumb: e.target.result } );
		renderPhotoGrid();
		// Mark temp thumb as uploading
		const thumb = photoGrid.querySelector( '[data-id="' + tempId + '"]' );
		if ( thumb ) thumb.classList.add( 'arshid6social-mkt-photo-uploading' );
	};
	reader.readAsDataURL( file );

	const fd = new FormData();
	fd.append( 'action', 'arshid6social_mkt_upload_photo' );
	fd.append( 'nonce',  NONCE );
	fd.append( 'token',  TOKEN );
	fd.append( 'photo',  file, file.name );

	try {
		const res  = await fetch( AJAX, { method: 'POST', body: fd } );
		const data = await res.json();

		// Replace temp entry with real one
		const idx = uploadedPhotos.findIndex( p => p.id === tempId );
		if ( data.success && idx > -1 ) {
			uploadedPhotos[ idx ] = { id: data.data.id, url: data.data.url, thumb: data.data.thumb };
			clearPhotoErr();
		} else {
			uploadedPhotos = uploadedPhotos.filter( p => p.id !== tempId );
			showPhotoErr( ( data.data && data.data.message ) || '<?php echo esc_js( __( 'Upload failed.', '6arshid social community' ) ); ?>' );
		}
	} catch ( err ) {
		uploadedPhotos = uploadedPhotos.filter( p => p.id !== tempId );
		showPhotoErr( '<?php echo esc_js( __( 'Network error — please try again.', '6arshid social community' ) ); ?>' );
	}

	renderPhotoGrid();
}

async function removePhoto( id ) {
	uploadedPhotos = uploadedPhotos.filter( p => p.id !== id );
	renderPhotoGrid();

	if ( typeof id === 'number' || ( typeof id === 'string' && ! id.startsWith( 'temp_' ) ) ) {
		const fd = new FormData();
		fd.append( 'action', 'arshid6social_mkt_remove_photo' );
		fd.append( 'nonce',  NONCE );
		fd.append( 'token',  TOKEN );
		fd.append( 'id',     id );
		fetch( AJAX, { method: 'POST', body: fd } ).catch( () => {} );
	}
}

function handleFiles( files ) {
	clearPhotoErr();
	Array.from( files ).slice( 0, MAX_PH - uploadedPhotos.length ).forEach( uploadPhoto );
}

if ( photoInput ) {
	photoInput.addEventListener( 'change', e => {
		handleFiles( e.target.files );
		e.target.value = '';
	} );
}
if ( dropZone ) {
	dropZone.addEventListener( 'dragover', e => { e.preventDefault(); dropZone.classList.add( 'is-dragover' ); } );
	dropZone.addEventListener( 'dragleave', ()  => dropZone.classList.remove( 'is-dragover' ) );
	dropZone.addEventListener( 'drop', e => {
		e.preventDefault();
		dropZone.classList.remove( 'is-dragover' );
		handleFiles( e.dataTransfer.files );
	} );
	dropZone.addEventListener( 'keydown', e => { if ( e.key === 'Enter' || e.key === ' ' ) photoInput.click(); } );
}

// ── Geolocation ────────────────────────────────────────────────────────────
const geoBtn    = document.getElementById( 'arshid6social-mkt-geolocate' );
const geoStatus = document.getElementById( 'arshid6social-mkt-geo-status' );
const latInput  = document.getElementById( 'mkt-lat' );
const lngInput  = document.getElementById( 'mkt-lng' );

if ( geoBtn ) {
	geoBtn.addEventListener( 'click', () => {
		if ( ! navigator.geolocation ) {
			geoStatus.textContent = '<?php echo esc_js( __( 'Geolocation not supported.', '6arshid social community' ) ); ?>';
			return;
		}
		geoStatus.textContent = '<?php echo esc_js( __( 'Detecting location…', '6arshid social community' ) ); ?>';
		geoBtn.disabled = true;

		navigator.geolocation.getCurrentPosition(
			pos => {
				latInput.value = pos.coords.latitude.toFixed( 7 );
				lngInput.value = pos.coords.longitude.toFixed( 7 );
				geoStatus.textContent = '<?php echo esc_js( __( 'Location captured!', '6arshid social community' ) ); ?>';
				geoBtn.disabled = false;
			},
			() => {
				geoStatus.textContent = '<?php echo esc_js( __( 'Could not get location.', '6arshid social community' ) ); ?>';
				geoBtn.disabled = false;
			},
			{ timeout: 8000 }
		);
	} );
}

// ── Step 5: Preview builder ─────────────────────────────────────────────────
function buildPreview() {
	const title     = ( document.getElementById( 'mkt-title' )?.value.trim() ) || '(<?php echo esc_js( __( 'no title', '6arshid social community' ) ); ?>)';
	const isFreeChk = document.getElementById( 'mkt-is-free' )?.checked;
	const priceVal  = document.getElementById( 'mkt-price' )?.value;
	const isNeg     = document.getElementById( 'mkt-is-negotiable' )?.checked;
	const city      = document.getElementById( 'mkt-city' )?.value.trim();
	const descVal   = document.getElementById( 'mkt-description' )?.value.trim();
	const symEl     = document.querySelector( '.arshid6social-mkt-currency-sym' );
	const sym       = symEl?.textContent || '';

	let priceText = isFreeChk ? '<?php echo esc_js( __( 'Free', '6arshid social community' ) ); ?>'
		: ( sym + ( parseFloat( priceVal ) || 0 ).toLocaleString() );
	if ( ! isFreeChk && isNeg ) priceText += ' · <?php echo esc_js( __( 'Negotiable', '6arshid social community' ) ); ?>';

	const condEl   = document.querySelector( 'input[name="item_condition"]:checked' );
	const condText = condEl ? condEl.closest( '.arshid6social-mkt-condition-opt' ).querySelector( 'span' ).textContent : '';

	document.getElementById( 'arshid6social-mkt-preview-title' ).textContent = title;
	document.getElementById( 'arshid6social-mkt-preview-price' ).textContent = priceText;
	document.getElementById( 'arshid6social-mkt-preview-meta' ).textContent  = [ condText, city ].filter( Boolean ).join( ' · ' );
	document.getElementById( 'arshid6social-mkt-preview-desc' ).textContent  = descVal || '';

	// Cover photo
	const imgWrap = document.getElementById( 'arshid6social-mkt-preview-img' );
	if ( uploadedPhotos.length && imgWrap ) {
		imgWrap.innerHTML = '<img src="' + uploadedPhotos[0].thumb + '" alt="">';
	}
}

// ── Form submission ────────────────────────────────────────────────────────
function setPublishing( on ) {
	const btn    = document.getElementById( 'arshid6social-mkt-publish' );
	const draftB = document.getElementById( 'arshid6social-mkt-save-draft' );
	const txt    = btn?.querySelector( '.arshid6social-mkt-btn-text' );
	const spin   = btn?.querySelector( '.arshid6social-mkt-btn-spinner' );
	if ( btn )  btn.disabled = on;
	if ( draftB ) draftB.disabled = on;
	if ( txt )  txt.hidden = on;
	if ( spin ) spin.hidden = ! on;
}

async function submitListing( action ) {
	if ( ! validateStep( 1 ) ) { goToStep( 1 ); return; }
	clearErr();
	setPublishing( true );

	document.getElementById( 'arshid6social-mkt-submit-action' ).value = action;

	const fd = new FormData( form );

	try {
		const res  = await fetch( AJAX, { method: 'POST', body: fd } );
		const data = await res.json();

		if ( data.success ) {
			window.location.href = data.data.url || BACK_URL;
		} else {
			setPublishing( false );
			showErr( ( data.data && data.data.message ) || '<?php echo esc_js( __( 'An error occurred. Please try again.', '6arshid social community' ) ); ?>' );
		}
	} catch ( err ) {
		setPublishing( false );
		showErr( '<?php echo esc_js( __( 'Network error. Please check your connection.', '6arshid social community' ) ); ?>' );
	}
}

const publishBtn = document.getElementById( 'arshid6social-mkt-publish' );
const draftBtn   = document.getElementById( 'arshid6social-mkt-save-draft' );
if ( publishBtn ) publishBtn.addEventListener( 'click', () => submitListing( 'publish' ) );
if ( draftBtn )   draftBtn.addEventListener( 'click',   () => submitListing( 'draft' ) );

})();
</script>
