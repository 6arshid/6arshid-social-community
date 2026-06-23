<?php
namespace Arshid6Social\Components\Marketplace;

/**
 * Marketplace admin settings tab + categories manager.
 *
 * Responsibilities:
 *  • Adds a "Marketplace" tab to the plugin settings page (via arshid6social_settings_tabs filter)
 *  • Registers and saves all Marketplace options with the WordPress Settings API
 *  • Auto-creates (or re-creates) the /marketplace/ WordPress page when the module is enabled
 *  • Adds the Marketplace page to the arshid6social-pages admin screen (via arshid6social_page_definitions filter)
 *  • Provides a full inline Categories Manager (add / edit / delete / reorder) via AJAX
 *
 * Instantiated unconditionally from Plugin::load_admin() so the tab is always
 * visible regardless of whether the Marketplace component is currently active.
 *
 * @package Arshid6Social\Components\Marketplace
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Marketplace_Settings
 */
class Marketplace_Settings {

	public function __construct() {
		// Settings tab.
		add_filter( 'arshid6social_settings_tabs',         array( $this, 'add_tab' ) );
		add_action( 'arshid6social_settings_tab_marketplace', array( $this, 'render_tab' ) );
		add_action( 'admin_init',                    array( $this, 'register_settings' ) );

		// Pages admin screen.
		add_filter( 'arshid6social_page_definitions', array( $this, 'add_page_definition' ) );

		// Auto-create the Marketplace WP page when the setting is enabled.
		add_action( 'update_option_arshid6social_marketplace_enabled', array( $this, 'on_enabled_saved' ), 10, 2 );

		// Categories manager AJAX.
		add_action( 'wp_ajax_arshid6social_marketplace_save_category',    array( $this, 'ajax_save_category' ) );
		add_action( 'wp_ajax_arshid6social_marketplace_delete_category',  array( $this, 'ajax_delete_category' ) );
		add_action( 'wp_ajax_arshid6social_marketplace_reorder_categories', array( $this, 'ajax_reorder_categories' ) );
	}

	// ── Tab registration ─────────────────────────────────────────────────────

	/** @param array<string,string> $tabs */
	public function add_tab( array $tabs ): array {
		$tabs['marketplace'] = __( 'Marketplace', 'social-network-6' );
		return $tabs;
	}

	// ── Page definitions ─────────────────────────────────────────────────────

	/** @param array<string, array> $pages */
	public function add_page_definition( array $pages ): array {
		if ( ! get_option( 'arshid6social_marketplace_enabled', false ) ) {
			return $pages;
		}

		$slug           = (string) get_option( 'arshid6social_marketplace_slug', 'marketplace' );
		$pages['marketplace'] = array(
			'title'       => __( 'Marketplace', 'social-network-6' ),
			'slug'        => $slug,
			'shortcode'   => '[arshid6social_marketplace]',
			'option'      => 'arshid6social_page_marketplace',
			'description' => __( 'Facebook-style peer-to-peer marketplace', 'social-network-6' ),
		);

		return $pages;
	}

	// ── Auto-create page ──────────────────────────────────────────────────────

	/**
	 * Fires when arshid6social_marketplace_enabled is saved.
	 * Creates the Marketplace WP page on first enable; does nothing on disable.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 */
	public function on_enabled_saved( $old_value, $new_value ): void {
		if ( ! $new_value ) {
			return;
		}
		$this->maybe_create_marketplace_page();
	}

	/**
	 * Creates the Marketplace WordPress page if it does not already exist.
	 * Also runs on DB upgrade so a re-install gets its page back.
	 */
	public static function maybe_create_marketplace_page(): void {
		$existing_id = (int) get_option( 'arshid6social_page_marketplace', 0 );
		if ( $existing_id && 'publish' === get_post_status( $existing_id ) ) {
			return;
		}

		$slug     = (string) get_option( 'arshid6social_marketplace_slug', 'marketplace' );
		$existing = get_page_by_path( $slug );
		if ( $existing && 'publish' === $existing->post_status ) {
			update_option( 'arshid6social_page_marketplace', $existing->ID );
			return;
		}

		$page_id = wp_insert_post( array(
			'post_title'     => __( 'Marketplace', 'social-network-6' ),
			'post_name'      => $slug,
			'post_content'   => '[arshid6social_marketplace]',
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'arshid6social_page_marketplace', $page_id );
			flush_rewrite_rules( false );
		}
	}

	// ── Settings API registration ─────────────────────────────────────────────

	public function register_settings(): void {
		$group   = 'arshid6social_marketplace';
		$options = array(
			'arshid6social_marketplace_enabled',
			'arshid6social_marketplace_currency_symbol',
			'arshid6social_marketplace_currency_position',
			'arshid6social_marketplace_currency_decimals',
			'arshid6social_marketplace_currency_thousands',
			'arshid6social_marketplace_max_photos',
			'arshid6social_marketplace_max_photo_size_mb',
			'arshid6social_marketplace_expiry_days',
			'arshid6social_marketplace_moderation',
			'arshid6social_marketplace_require_verified',
			'arshid6social_marketplace_auto_hide_threshold',
			'arshid6social_marketplace_banned_words',
			'arshid6social_marketplace_allow_guests',
			'arshid6social_marketplace_max_active_listings',
			'arshid6social_marketplace_daily_new_listings',
			'arshid6social_marketplace_safety_tips',
			'arshid6social_marketplace_prohibited_policy',
			'arshid6social_marketplace_as_homepage',
			'arshid6social_marketplace_social_share',
		);

		foreach ( $options as $option_name ) {
			register_setting( $group, $option_name, array( $this, 'sanitize_option' ) );
		}
	}

	public function sanitize_option( $value ): mixed {
		// Derive the actual option name being saved from the current filter name.
		// WordPress calls the sanitize callback via a filter named "sanitize_option_{option_name}".
		$option = str_replace( 'sanitize_option_', '', current_filter() );

		$bool_opts = array(
			'arshid6social_marketplace_enabled', 'arshid6social_marketplace_require_verified',
			'arshid6social_marketplace_allow_guests', 'arshid6social_marketplace_as_homepage',
			'arshid6social_marketplace_social_share',
		);
		$int_opts = array(
			'arshid6social_marketplace_currency_decimals', 'arshid6social_marketplace_max_photos',
			'arshid6social_marketplace_max_photo_size_mb', 'arshid6social_marketplace_expiry_days',
			'arshid6social_marketplace_auto_hide_threshold', 'arshid6social_marketplace_max_active_listings',
			'arshid6social_marketplace_daily_new_listings',
		);
		$textarea_opts = array(
			'arshid6social_marketplace_banned_words', 'arshid6social_marketplace_safety_tips',
			'arshid6social_marketplace_prohibited_policy',
		);

		if ( in_array( $option, $bool_opts, true ) )     { return (bool) $value; }
		if ( in_array( $option, $int_opts, true ) )      { return absint( $value ); }
		if ( in_array( $option, $textarea_opts, true ) ) { return sanitize_textarea_field( (string) $value ); }

		if ( 'arshid6social_marketplace_currency_position' === $option ) {
			return in_array( $value, array( 'before', 'after' ), true ) ? $value : 'before';
		}
		if ( 'arshid6social_marketplace_moderation' === $option ) {
			return in_array( $value, array( 'auto', 'manual' ), true ) ? $value : 'auto';
		}

		return sanitize_text_field( (string) $value );
	}

	// ── Tab rendering ─────────────────────────────────────────────────────────

	public function render_tab(): void {
		$this->render_section_general();
		$this->render_section_social_share();
		$this->render_section_categories();
		$this->render_section_currency();
		$this->render_section_listings();
		$this->render_section_moderation();
		$this->render_section_access();
		$this->render_section_content();
		$this->render_section_homepage();
		$this->enqueue_categories_script();
	}

	// ── Section: General ─────────────────────────────────────────────────────

	private function render_section_general(): void {
		$page_id  = (int) get_option( 'arshid6social_page_marketplace', 0 );
		$page_url = ( $page_id && 'publish' === get_post_status( $page_id ) ) ? get_permalink( $page_id ) : '';
		?>
		<h2><?php esc_html_e( 'General', 'social-network-6' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Marketplace', 'social-network-6' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_marketplace_enabled" value="1"
							<?php checked( get_option( 'arshid6social_marketplace_enabled', false ) ); ?> />
						<?php esc_html_e( 'Activate the Marketplace module. Disabling this removes all Marketplace hooks, assets, REST routes, and cron jobs.', 'social-network-6' ); ?>
					</label>
					<?php if ( $page_url ) : ?>
						<p class="description">
							<?php esc_html_e( 'Marketplace page:', 'social-network-6' ); ?>
							<a href="<?php echo esc_url( $page_url ); ?>" target="_blank"><?php echo esc_html( $page_url ); ?></a>
						</p>
					<?php else : ?>
						<p class="description" style="color:#b91c1c;">
							<?php esc_html_e( 'No Marketplace page found. Save settings while enabled to auto-create one.', 'social-network-6' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	// ── Section: Social Sharing ──────────────────────────────────────────────

	private function render_section_social_share(): void {
		?>
		<h2><?php esc_html_e( 'Social Sharing', 'social-network-6' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'External Social Sharing', 'social-network-6' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_marketplace_social_share" value="1"
							<?php checked( get_option( 'arshid6social_marketplace_social_share', true ) ); ?> />
						<?php esc_html_e( 'Show social sharing buttons on marketplace listings (Facebook, WhatsApp, Telegram, X, and more).', 'social-network-6' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Requires External Social Sharing to be enabled in the Engagement settings.', 'social-network-6' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<hr style="margin:24px 0">
		<?php
	}

	// ── Section: Categories Manager ───────────────────────────────────────────

	private function render_section_categories(): void {
		global $wpdb;
		$nonce      = wp_create_nonce( 'arshid6social_marketplace_categories' );
		$categories = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}arshid6social_categories ORDER BY parent_id ASC, sort_order ASC, id ASC"
		);

		// Build parent name map for display.
		$name_map = array();
		foreach ( $categories as $cat ) {
			$name_map[ (int) $cat->id ] = $cat->name;
		}
		?>
		<h2><?php esc_html_e( 'Categories', 'social-network-6' ); ?></h2>
		<p class="description" style="margin-bottom:12px">
			<?php esc_html_e( 'Create and manage hierarchical listing categories. Drag rows to reorder.', 'social-network-6' ); ?>
		</p>

		<div id="arshid6social-mkt-cats-wrap" data-nonce="<?php echo esc_attr( $nonce ); ?>">

			<!-- Category table -->
			<table class="widefat arshid6social-mkt-cat-table" style="margin-bottom:12px">
				<thead>
					<tr>
						<th style="width:32px"></th>
						<th style="width:52px"><?php esc_html_e( 'Icon', 'social-network-6' ); ?></th>
						<th><?php esc_html_e( 'Name', 'social-network-6' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'social-network-6' ); ?></th>
						<th><?php esc_html_e( 'Parent', 'social-network-6' ); ?></th>
						<th style="width:60px"><?php esc_html_e( 'Order', 'social-network-6' ); ?></th>
						<th style="width:120px"><?php esc_html_e( 'Actions', 'social-network-6' ); ?></th>
					</tr>
				</thead>
				<tbody id="arshid6social-mkt-cat-list">
					<?php foreach ( $categories as $cat ) : ?>
						<?php $parent_name = $cat->parent_id ? ( $name_map[ (int) $cat->parent_id ] ?? '—' ) : '—'; ?>
						<tr data-id="<?php echo esc_attr( $cat->id ); ?>" class="arshid6social-mkt-cat-row">
							<td class="arshid6social-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'social-network-6' ); ?>" style="cursor:grab;color:#94a3b8;font-size:1.1rem;text-align:center">⠿</td>
							<td style="font-size:1.4rem;text-align:center"><?php echo esc_html( $cat->icon ); ?></td>
							<td>
								<strong><?php echo esc_html( $cat->name ); ?></strong>
								<?php if ( $cat->parent_id ) : ?>
									<span style="margin-left:6px;font-size:.8rem;color:#64748b">↳ <?php echo esc_html( $parent_name ); ?></span>
								<?php endif; ?>
							</td>
							<td><code style="font-size:.8rem"><?php echo esc_html( $cat->slug ); ?></code></td>
							<td><?php echo $cat->parent_id ? esc_html( $parent_name ) : '<span style="color:#94a3b8">—</span>'; ?></td>
							<td><?php echo esc_html( $cat->sort_order ); ?></td>
							<td>
								<button type="button" class="button button-small arshid6social-mkt-cat-edit"
									data-id="<?php echo esc_attr( $cat->id ); ?>"
									data-name="<?php echo esc_attr( $cat->name ); ?>"
									data-slug="<?php echo esc_attr( $cat->slug ); ?>"
									data-icon="<?php echo esc_attr( $cat->icon ); ?>"
									data-parent="<?php echo esc_attr( $cat->parent_id ); ?>"
									data-order="<?php echo esc_attr( $cat->sort_order ); ?>">
									<?php esc_html_e( 'Edit', 'social-network-6' ); ?>
								</button>
								<button type="button" class="button button-small button-link-delete arshid6social-mkt-cat-delete"
									data-id="<?php echo esc_attr( $cat->id ); ?>"
									data-name="<?php echo esc_attr( $cat->name ); ?>"
									style="color:#dc2626;margin-left:4px">
									<?php esc_html_e( 'Delete', 'social-network-6' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $categories ) ) : ?>
						<tr id="arshid6social-mkt-cat-empty">
							<td colspan="7" style="text-align:center;color:#94a3b8;padding:1.5rem">
								<?php esc_html_e( 'No categories yet. Add one below.', 'social-network-6' ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Add / Edit form -->
			<div id="arshid6social-mkt-cat-form-wrap" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:16px;margin-bottom:16px;display:none">
				<h4 id="arshid6social-mkt-cat-form-title" style="margin:0 0 12px"><?php esc_html_e( 'Add Category', 'social-network-6' ); ?></h4>
				<input type="hidden" id="arshid6social-mkt-cat-id" value="0" />
				<table class="form-table" role="presentation" style="margin:0">
					<tr>
						<th scope="row" style="width:100px;padding:8px 0"><?php esc_html_e( 'Icon', 'social-network-6' ); ?></th>
						<td style="padding:8px 0">
							<input type="text" id="arshid6social-mkt-cat-icon" value="" maxlength="10"
								placeholder="🚗" class="small-text" style="font-size:1.3rem;width:60px" />
							<span class="description" style="margin-left:8px"><?php esc_html_e( 'Emoji or short text', 'social-network-6' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row" style="padding:8px 0"><?php esc_html_e( 'Name', 'social-network-6' ); ?></th>
						<td style="padding:8px 0">
							<input type="text" id="arshid6social-mkt-cat-name" value="" class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. Vehicles', 'social-network-6' ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row" style="padding:8px 0"><?php esc_html_e( 'Slug', 'social-network-6' ); ?></th>
						<td style="padding:8px 0">
							<input type="text" id="arshid6social-mkt-cat-slug" value="" class="regular-text"
								placeholder="<?php esc_attr_e( 'auto-generated from name', 'social-network-6' ); ?>" />
							<span class="description"><?php esc_html_e( 'Leave blank to auto-generate.', 'social-network-6' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row" style="padding:8px 0"><?php esc_html_e( 'Parent', 'social-network-6' ); ?></th>
						<td style="padding:8px 0">
							<select id="arshid6social-mkt-cat-parent">
								<option value="0"><?php esc_html_e( '— Top Level —', 'social-network-6' ); ?></option>
								<?php foreach ( $categories as $cat ) :
									if ( $cat->parent_id ) continue; // Only top-level as parents.
								?>
									<option value="<?php echo esc_attr( $cat->id ); ?>">
										<?php echo esc_html( $cat->icon . ' ' . $cat->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row" style="padding:8px 0"><?php esc_html_e( 'Sort Order', 'social-network-6' ); ?></th>
						<td style="padding:8px 0">
							<input type="number" id="arshid6social-mkt-cat-order" value="0" min="0" max="999" class="small-text" />
						</td>
					</tr>
				</table>
				<div style="margin-top:12px;display:flex;gap:8px;align-items:center">
					<button type="button" id="arshid6social-mkt-cat-save" class="button button-primary">
						<?php esc_html_e( 'Save Category', 'social-network-6' ); ?>
					</button>
					<button type="button" id="arshid6social-mkt-cat-cancel" class="button">
						<?php esc_html_e( 'Cancel', 'social-network-6' ); ?>
					</button>
					<span id="arshid6social-mkt-cat-msg" style="font-size:.875rem"></span>
				</div>
			</div>

			<button type="button" id="arshid6social-mkt-cat-add-btn" class="button button-secondary">
				+ <?php esc_html_e( 'Add Category', 'social-network-6' ); ?>
			</button>
		</div>
		<hr style="margin:24px 0">
		<?php
	}

	// ── Section: Currency ─────────────────────────────────────────────────────

	private function render_section_currency(): void {
		?>
		<h2><?php esc_html_e( 'Currency', 'social-network-6' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Currency Symbol', 'social-network-6' ); ?></th>
				<td>
					<input type="text" name="arshid6social_marketplace_currency_symbol"
						value="<?php echo esc_attr( get_option( 'arshid6social_marketplace_currency_symbol', '$' ) ); ?>"
						class="small-text" maxlength="5" />
					<p class="description"><?php esc_html_e( 'e.g. $, €, £, ﷼', 'social-network-6' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Symbol Position', 'social-network-6' ); ?></th>
				<td>
					<select name="arshid6social_marketplace_currency_position">
						<option value="before" <?php selected( get_option( 'arshid6social_marketplace_currency_position', 'before' ), 'before' ); ?>>
							<?php esc_html_e( 'Before amount ($100)', 'social-network-6' ); ?>
						</option>
						<option value="after" <?php selected( get_option( 'arshid6social_marketplace_currency_position', 'before' ), 'after' ); ?>>
							<?php esc_html_e( 'After amount (100$)', 'social-network-6' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Decimal Places', 'social-network-6' ); ?></th>
				<td>
					<input type="number" name="arshid6social_marketplace_currency_decimals" min="0" max="4"
						value="<?php echo esc_attr( get_option( 'arshid6social_marketplace_currency_decimals', 2 ) ); ?>"
						class="small-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Thousands Separator', 'social-network-6' ); ?></th>
				<td>
					<input type="text" name="arshid6social_marketplace_currency_thousands"
						value="<?php echo esc_attr( get_option( 'arshid6social_marketplace_currency_thousands', ',' ) ); ?>"
						class="small-text" maxlength="1" />
					<p class="description"><?php esc_html_e( 'e.g. , or . or leave blank', 'social-network-6' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	// ── Section: Listings ─────────────────────────────────────────────────────

	private function render_section_listings(): void {
		?>
		<h2><?php esc_html_e( 'Listings', 'social-network-6' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Max Photos Per Listing', 'social-network-6' ); ?></th>
				<td>
					<input type="number" name="arshid6social_marketplace_max_photos" min="1" max="30"
						value="<?php echo esc_attr( get_option( 'arshid6social_marketplace_max_photos', 10 ) ); ?>"
						class="small-text" />
					<p class="description"><?php esc_html_e( 'Maximum photos per listing (1–30).', 'social-network-6' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Max Photo Size (MB)', 'social-network-6' ); ?></th>
				<td>
					<input type="number" name="arshid6social_marketplace_max_photo_size_mb" min="1" max="50"
						value="<?php echo esc_attr( get_option( 'arshid6social_marketplace_max_photo_size_mb', 5 ) ); ?>"
						class="small-text" /> MB
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Listing Expiry (Days)', 'social-network-6' ); ?></th>
				<td>
					<input type="number" name="arshid6social_marketplace_expiry_days" min="0" max="365"
						value="<?php echo esc_attr( get_option( 'arshid6social_marketplace_expiry_days', 30 ) ); ?>"
						class="small-text" />
					<p class="description"><?php esc_html_e( 'Auto-archive listings after this many days. 0 = never expire.', 'social-network-6' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	// ── Section: Moderation ───────────────────────────────────────────────────

	private function render_section_moderation(): void {
		?>
		<h2><?php esc_html_e( 'Moderation', 'social-network-6' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Moderation Mode', 'social-network-6' ); ?></th>
				<td>
					<select name="arshid6social_marketplace_moderation">
						<option value="auto" <?php selected( get_option( 'arshid6social_marketplace_moderation', 'auto' ), 'auto' ); ?>>
							<?php esc_html_e( 'Auto-publish (listings go live immediately)', 'social-network-6' ); ?>
						</option>
						<option value="manual" <?php selected( get_option( 'arshid6social_marketplace_moderation', 'auto' ), 'manual' ); ?>>
							<?php esc_html_e( 'Manual approval (admin must review before listing is visible)', 'social-network-6' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Require Verified Account to Post', 'social-network-6' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_marketplace_require_verified" value="1"
							<?php checked( get_option( 'arshid6social_marketplace_require_verified', false ) ); ?> />
						<?php esc_html_e( 'Only users with a verified badge can create listings.', 'social-network-6' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-hide After N Reports', 'social-network-6' ); ?></th>
				<td>
					<input type="number" name="arshid6social_marketplace_auto_hide_threshold" min="0" max="100"
						value="<?php echo esc_attr( get_option( 'arshid6social_marketplace_auto_hide_threshold', 3 ) ); ?>"
						class="small-text" />
					<p class="description"><?php esc_html_e( 'Set listing to "pending review" after this many reports. 0 = disabled.', 'social-network-6' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Banned Words / Phrases', 'social-network-6' ); ?></th>
				<td>
					<textarea name="arshid6social_marketplace_banned_words" rows="4"
						class="large-text"><?php echo esc_textarea( get_option( 'arshid6social_marketplace_banned_words', '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One word or phrase per line. Listings matching these will be held for moderation.', 'social-network-6' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	// ── Section: Access ───────────────────────────────────────────────────────

	private function render_section_access(): void {
		?>
		<h2><?php esc_html_e( 'Access & Limits', 'social-network-6' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Allow Guests to Browse', 'social-network-6' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_marketplace_allow_guests" value="1"
							<?php checked( get_option( 'arshid6social_marketplace_allow_guests', true ) ); ?> />
						<?php esc_html_e( 'Logged-out visitors can view listings. Contacting a seller always requires login.', 'social-network-6' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Max Active Listings Per User', 'social-network-6' ); ?></th>
				<td>
					<input type="number" name="arshid6social_marketplace_max_active_listings" min="1" max="9999"
						value="<?php echo esc_attr( get_option( 'arshid6social_marketplace_max_active_listings', 20 ) ); ?>"
						class="small-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Max New Listings Per Day', 'social-network-6' ); ?></th>
				<td>
					<input type="number" name="arshid6social_marketplace_daily_new_listings" min="1" max="9999"
						value="<?php echo esc_attr( get_option( 'arshid6social_marketplace_daily_new_listings', 5 ) ); ?>"
						class="small-text" />
				</td>
			</tr>
		</table>
		<?php
	}

	// ── Section: Content ──────────────────────────────────────────────────────

	private function render_section_content(): void {
		?>
		<h2><?php esc_html_e( 'Policy & Safety Content', 'social-network-6' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Safety Tips', 'social-network-6' ); ?></th>
				<td>
					<textarea name="arshid6social_marketplace_safety_tips" rows="4"
						class="large-text"><?php echo esc_textarea( get_option( 'arshid6social_marketplace_safety_tips', '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Shown to buyers before their first message to a seller. Plain text only.', 'social-network-6' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Prohibited Items Policy', 'social-network-6' ); ?></th>
				<td>
					<textarea name="arshid6social_marketplace_prohibited_policy" rows="4"
						class="large-text"><?php echo esc_textarea( get_option( 'arshid6social_marketplace_prohibited_policy', '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Shown (with a required checkbox) at the start of the Create Listing wizard.', 'social-network-6' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	// ── Section: Homepage ─────────────────────────────────────────────────────

	private function render_section_homepage(): void {
		?>
		<h2><?php esc_html_e( 'Homepage Placement', 'social-network-6' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Set Marketplace as Site Landing Page', 'social-network-6' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_marketplace_as_homepage" value="1"
							<?php checked( get_option( 'arshid6social_marketplace_as_homepage', false ) ); ?> />
						<?php esc_html_e( 'Use the Marketplace page as the front page. Reversible — uncheck to restore previous front page.', 'social-network-6' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<div class="notice notice-info inline" style="margin-top:16px">
			<p>
				<strong><?php esc_html_e( 'Payment & Liability Notice', 'social-network-6' ); ?></strong><br>
				<?php esc_html_e( 'This plugin does NOT process, hold, or facilitate payments. All transactions are arranged directly between buyer and seller via private messages (peer-to-peer). The plugin operator assumes no liability for any transaction.', 'social-network-6' ); ?>
			</p>
		</div>
		<?php
	}

	// ── Categories AJAX ──────────────────────────────────────────────────────

	public function ajax_save_category(): void {
		if ( ! check_ajax_referer( 'arshid6social_marketplace_categories', 'nonce', false )
			|| ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'arshid6social_categories';

		$id         = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$name       = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$slug_input = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$icon       = sanitize_text_field( wp_unslash( $_POST['icon'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$parent_id  = absint( $_POST['parent_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$sort_order = absint( $_POST['sort_order'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! $name ) {
			wp_send_json_error( array( 'message' => __( 'Name is required.', 'social-network-6' ) ) );
		}

		$slug = $slug_input ?: sanitize_title( $name );

		// Ensure slug uniqueness (exclude current row on edit).
		$slug_exists = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM `{$table}` WHERE slug = %s AND id != %d",
			$slug,
			$id
		) );
		if ( $slug_exists ) {
			$slug .= '-' . time();
		}

		$data   = array( 'name' => $name, 'slug' => $slug, 'icon' => $icon, 'parent_id' => $parent_id, 'sort_order' => $sort_order );
		$format = array( '%s', '%s', '%s', '%d', '%d' );

		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$saved_id = $id;
		} else {
			$wpdb->insert( $table, $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$saved_id = (int) $wpdb->insert_id;
		}

		wp_send_json_success( array(
			'id'         => $saved_id,
			'name'       => $name,
			'slug'       => $slug,
			'icon'       => $icon,
			'parent_id'  => $parent_id,
			'sort_order' => $sort_order,
		) );
	}

	public function ajax_delete_category(): void {
		if ( ! check_ajax_referer( 'arshid6social_marketplace_categories', 'nonce', false )
			|| ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		global $wpdb;
		$id    = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$table = $wpdb->prefix . 'arshid6social_categories';

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'social-network-6' ) ) );
		}

		// Move children to top level before deleting the parent.
		$wpdb->update( $table, array( 'parent_id' => 0 ), array( 'parent_id' => $id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		wp_send_json_success( array( 'id' => $id ) );
	}

	public function ajax_reorder_categories(): void {
		if ( ! check_ajax_referer( 'arshid6social_marketplace_categories', 'nonce', false )
			|| ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'arshid6social_categories';

		// Expects: order = [{id:1},{id:5},{id:3}] (ordered array of IDs).
		$order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification

		foreach ( $order as $position => $cat_id ) {
			if ( $cat_id ) {
				$wpdb->update( $table, array( 'sort_order' => $position + 1 ), array( 'id' => $cat_id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}

		wp_send_json_success();
	}

	// ── Inline JS for categories manager ─────────────────────────────────────

	private function enqueue_categories_script(): void {
		$confirm_delete = __( 'Delete this category? Its sub-categories will be moved to the top level.', 'social-network-6' );
		$label_add      = __( 'Add Category', 'social-network-6' );
		$label_edit     = __( 'Edit Category', 'social-network-6' );
		$label_saving   = __( 'Saving…', 'social-network-6' );
		$label_saved    = __( 'Saved.', 'social-network-6' );
		$label_error    = __( 'Error. Please try again.', 'social-network-6' );
		?>
		<script>
		(function() {
			const wrap    = document.getElementById( 'arshid6social-mkt-cats-wrap' );
			if ( ! wrap ) return;

			const nonce   = wrap.dataset.nonce;
			const form    = document.getElementById( 'arshid6social-mkt-cat-form-wrap' );
			const list    = document.getElementById( 'arshid6social-mkt-cat-list' );
			const idField = document.getElementById( 'arshid6social-mkt-cat-id' );
			const fTitle  = document.getElementById( 'arshid6social-mkt-cat-form-title' );
			const fIcon   = document.getElementById( 'arshid6social-mkt-cat-icon' );
			const fName   = document.getElementById( 'arshid6social-mkt-cat-name' );
			const fSlug   = document.getElementById( 'arshid6social-mkt-cat-slug' );
			const fParent = document.getElementById( 'arshid6social-mkt-cat-parent' );
			const fOrder  = document.getElementById( 'arshid6social-mkt-cat-order' );
			const msg     = document.getElementById( 'arshid6social-mkt-cat-msg' );
			const addBtn  = document.getElementById( 'arshid6social-mkt-cat-add-btn' );

			// Open form for a new category.
			addBtn.addEventListener( 'click', () => {
				idField.value = '0';
				fTitle.textContent = '<?php echo esc_js( $label_add ); ?>';
				fIcon.value = ''; fName.value = ''; fSlug.value = '';
				fParent.value = '0'; fOrder.value = '0';
				msg.textContent = '';
				form.style.display = 'block';
				fName.focus();
			} );

			// Cancel form.
			document.getElementById( 'arshid6social-mkt-cat-cancel' ).addEventListener( 'click', () => {
				form.style.display = 'none';
			} );

			// Open form to edit an existing row.
			list.addEventListener( 'click', e => {
				const editBtn = e.target.closest( '.arshid6social-mkt-cat-edit' );
				if ( ! editBtn ) return;
				idField.value      = editBtn.dataset.id;
				fTitle.textContent = '<?php echo esc_js( $label_edit ); ?>';
				fIcon.value        = editBtn.dataset.icon;
				fName.value        = editBtn.dataset.name;
				fSlug.value        = editBtn.dataset.slug;
				fParent.value      = editBtn.dataset.parent;
				fOrder.value       = editBtn.dataset.order;
				msg.textContent    = '';
				form.style.display = 'block';
				fName.focus();
			} );

			// Delete a category.
			list.addEventListener( 'click', async e => {
				const delBtn = e.target.closest( '.arshid6social-mkt-cat-delete' );
				if ( ! delBtn ) return;
				if ( ! confirm( '<?php echo esc_js( $confirm_delete ); ?>' ) ) return;

				delBtn.disabled = true;
				const body = new FormData();
				body.append( 'action', 'arshid6social_marketplace_delete_category' );
				body.append( 'nonce',  nonce );
				body.append( 'id',     delBtn.dataset.id );

				const res  = await fetch( ajaxurl, { method: 'POST', body } );
				const data = await res.json();
				if ( data.success ) {
					delBtn.closest( 'tr' ).remove();
					removeParentOption( delBtn.dataset.id );
				} else {
					alert( data.data?.message || '<?php echo esc_js( $label_error ); ?>' );
					delBtn.disabled = false;
				}
			} );

			// Save category (add or edit).
			document.getElementById( 'arshid6social-mkt-cat-save' ).addEventListener( 'click', async () => {
				const saveBtn = document.getElementById( 'arshid6social-mkt-cat-save' );
				saveBtn.disabled   = true;
				msg.style.color    = '#64748b';
				msg.textContent    = '<?php echo esc_js( $label_saving ); ?>';

				const body = new FormData();
				body.append( 'action',     'arshid6social_marketplace_save_category' );
				body.append( 'nonce',      nonce );
				body.append( 'id',         idField.value );
				body.append( 'name',       fName.value.trim() );
				body.append( 'slug',       fSlug.value.trim() );
				body.append( 'icon',       fIcon.value.trim() );
				body.append( 'parent_id',  fParent.value );
				body.append( 'sort_order', fOrder.value );

				const res  = await fetch( ajaxurl, { method: 'POST', body } );
				const data = await res.json();
				saveBtn.disabled = false;

				if ( ! data.success ) {
					msg.style.color  = '#dc2626';
					msg.textContent  = data.data?.message || '<?php echo esc_js( $label_error ); ?>';
					return;
				}

				msg.style.color = '#16a34a';
				msg.textContent = '<?php echo esc_js( $label_saved ); ?>';

				// Reload page so table reflects changes (simple, reliable).
				setTimeout( () => location.reload(), 700 );
			} );

			// ── Drag-to-reorder ────────────────────────────────────────────
			let dragRow = null;

			list.addEventListener( 'dragstart', e => {
				dragRow = e.target.closest( 'tr' );
				if ( dragRow ) dragRow.style.opacity = '0.5';
			} );

			list.addEventListener( 'dragend', e => {
				if ( dragRow ) dragRow.style.opacity = '';
				dragRow = null;
			} );

			list.addEventListener( 'dragover', e => {
				e.preventDefault();
				const target = e.target.closest( 'tr' );
				if ( target && dragRow && target !== dragRow ) {
					const rows       = [...list.querySelectorAll( 'tr[data-id]' )];
					const dragIdx    = rows.indexOf( dragRow );
					const targetIdx  = rows.indexOf( target );
					if ( dragIdx > targetIdx ) {
						list.insertBefore( dragRow, target );
					} else {
						list.insertBefore( dragRow, target.nextSibling );
					}
				}
			} );

			list.addEventListener( 'drop', async e => {
				e.preventDefault();
				const order = [...list.querySelectorAll( 'tr[data-id]' )].map( r => r.dataset.id );
				const body  = new FormData();
				body.append( 'action', 'arshid6social_marketplace_reorder_categories' );
				body.append( 'nonce',  nonce );
				order.forEach( id => body.append( 'order[]', id ) );
				await fetch( ajaxurl, { method: 'POST', body } );
			} );

			// Make rows draggable.
			list.querySelectorAll( 'tr[data-id]' ).forEach( row => {
				row.draggable = true;
				const handle = row.querySelector( '.arshid6social-drag-handle' );
				if ( handle ) {
					row.addEventListener( 'mousedown', e => {
						if ( e.target.closest( '.arshid6social-drag-handle' ) ) {
							row.draggable = true;
						}
					} );
				}
			} );

			function removeParentOption( id ) {
				const opt = fParent.querySelector( 'option[value="' + id + '"]' );
				if ( opt ) opt.remove();
			}
		})();
		</script>
		<?php
	}
}
