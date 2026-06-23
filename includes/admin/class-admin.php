<?php
namespace Arshid6Social\Admin;

/**
 * Admin panel bootstrap.
 *
 * @package Arshid6Social\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin
 *
 * Registers top-level admin menu, sub-menus, and delegates to specialised admin classes.
 */
final class Admin {

	private static ?Admin $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function __construct() {}

	/** @var Admin_Pages|null */
	private ?Admin_Pages $admin_pages = null;

	private function hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_create_pages' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_setup_notice' ) );
		add_filter( 'plugin_action_links_' . ARSHID6SOCIAL_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
		add_filter( 'set_screen_option_arshid6social_payouts_per_page',      array( $this, 'save_monetization_per_page' ), 10, 3 );
		add_filter( 'set_screen_option_arshid6social_tx_per_page',           array( $this, 'save_monetization_per_page' ), 10, 3 );
		add_filter( 'set-screen-option',                                      array( $this, 'save_monetization_per_page_legacy' ), 10, 3 );

		// Instantiate sub-admin classes.
		Admin_Settings::instance();
		Admin_Members::instance();
		Admin_Moderation::instance();
		Admin_Notifications::instance();
		Admin_Marketplace::instance();
		Admin_Activity::instance();
		Admin_Verification::instance();
		Admin_Page_Icons::instance();
		Admin_Ads::instance();
		Sample_Data::instance();
		$this->admin_pages = new Admin_Pages();
	}

	/**
	 * Registers the admin menu tree.
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'social-network-6', 'social-network-6' ),
			__( 'social-network-6', 'social-network-6' ),
			'arshid6social_manage_settings',
			'arshid6social-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-groups',
			30
		);

		add_submenu_page(
			'arshid6social-dashboard',
			__( 'Dashboard', 'social-network-6' ),
			__( 'Dashboard', 'social-network-6' ),
			'arshid6social_manage_settings',
			'arshid6social-dashboard',
			array( $this, 'render_dashboard' )
		);

		$activity_hook = add_submenu_page(
			'arshid6social-dashboard',
			__( 'Activity Items', 'social-network-6' ),
			__( 'Activity Items', 'social-network-6' ),
			'arshid6social_manage_settings',
			'arshid6social-activity',
			array( Admin_Activity::instance(), 'render' )
		);
		if ( $activity_hook ) {
			add_action( 'load-' . $activity_hook, array( Admin_Activity::instance(), 'setup_screen_options' ) );
		}

		$members_hook = add_submenu_page(
			'arshid6social-dashboard',
			__( 'Members', 'social-network-6' ),
			__( 'Members', 'social-network-6' ),
			'arshid6social_manage_members',
			'arshid6social-members',
			array( Admin_Members::instance(), 'render' )
		);
		if ( $members_hook ) {
			add_action( 'load-' . $members_hook, array( Admin_Members::instance(), 'setup_screen_options' ) );
		}

		$moderation_hook = add_submenu_page(
			'arshid6social-dashboard',
			__( 'Reports & Moderation', 'social-network-6' ),
			__( 'Moderation', 'social-network-6' ),
			'arshid6social_manage_reports',
			'arshid6social-moderation',
			array( Admin_Moderation::instance(), 'render' )
		);
		if ( $moderation_hook ) {
			add_action( 'load-' . $moderation_hook, array( Admin_Moderation::instance(), 'setup_screen_options' ) );
		}

		if ( get_option( 'arshid6social_verification_enabled', false ) ) {
			$verification_hook = add_submenu_page(
				'arshid6social-dashboard',
				__( 'Verification Queue', 'social-network-6' ),
				__( 'Verification', 'social-network-6' ),
				'arshid6social_manage_members',
				'arshid6social-verification',
				array( Admin_Verification::instance(), 'render' )
			);
			if ( $verification_hook ) {
				add_action( 'load-' . $verification_hook, array( Admin_Verification::instance(), 'setup_screen_options' ) );
			}
		}

		add_submenu_page(
			'arshid6social-dashboard',
			__( 'Pages & Shortcodes', 'social-network-6' ),
			__( 'Pages & Shortcodes', 'social-network-6' ),
			'arshid6social_manage_settings',
			'arshid6social-pages',
			array( $this->admin_pages, 'render' )
		);

		add_submenu_page(
			'arshid6social-dashboard',
			__( 'Notifications', 'social-network-6' ),
			__( 'Notifications', 'social-network-6' ),
			'arshid6social_manage_settings',
			'arshid6social-notifications',
			array( Admin_Notifications::instance(), 'render' )
		);

		$mkt_hook = add_submenu_page(
			'arshid6social-dashboard',
			__( 'Products Archive', 'social-network-6' ),
			__( 'Products Archive', 'social-network-6' ),
			'arshid6social_manage_settings',
			'arshid6social-marketplace',
			array( Admin_Marketplace::instance(), 'render' )
		);
		if ( $mkt_hook ) {
			add_action( 'load-' . $mkt_hook, array( Admin_Marketplace::instance(), 'setup_screen_options' ) );
		}

		add_submenu_page(
			'arshid6social-dashboard',
			__( 'Ads Manager', 'social-network-6' ),
			__( 'Ads Manager', 'social-network-6' ),
			'arshid6social_manage_settings',
			'arshid6social-ads',
			array( Admin_Ads::instance(), 'render' )
		);

		$monetization_hook = add_submenu_page(
			'arshid6social-dashboard',
			__( 'Monetization', 'social-network-6' ),
			__( 'Monetization', 'social-network-6' ),
			'arshid6social_manage_settings',
			'arshid6social-monetization',
			array( $this, 'render_monetization' )
		);
		if ( $monetization_hook ) {
			add_action( 'load-' . $monetization_hook, array( $this, 'setup_monetization_screen_options' ) );
		}

		add_submenu_page(
			'arshid6social-dashboard',
			__( 'Settings', 'social-network-6' ),
			__( 'Settings', 'social-network-6' ),
			'arshid6social_manage_settings',
			'arshid6social-settings',
			array( Admin_Settings::instance(), 'render' )
		);

	}

	/**
	 * Registers all plugin settings with the Settings API.
	 */
	public function register_settings(): void {
		Admin_Settings::instance()->register();
	}

	/**
	 * Renders the dashboard overview page.
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'social-network-6' ) );
		}

		$stats = $this->get_dashboard_stats();

		echo '<div class="wrap arshid6social-admin-dashboard">';
		echo '<h1>' . esc_html__( '6Arshid Social Community Dashboard', 'social-network-6' ) . '</h1>';

		$card_defs = array(
			array(
				'label'  => __( 'Activity Items', 'social-network-6' ),
				'count'  => $stats['activity'],
				'icon'   => 'dashicons-format-status',
				'url'    => admin_url( 'admin.php?page=arshid6social-activity' ),
			),
			array(
				'label'  => __( 'Groups', 'social-network-6' ),
				'count'  => $stats['groups'],
				'icon'   => 'dashicons-groups',
				'url'    => admin_url( 'admin.php?page=arshid6social-moderation' ),
			),
			array(
				'label'  => __( 'Members', 'social-network-6' ),
				'count'  => $stats['members'],
				'icon'   => 'dashicons-admin-users',
				'url'    => admin_url( 'admin.php?page=arshid6social-members' ),
			),
			array(
				'label'  => __( 'Pending Reports', 'social-network-6' ),
				'count'  => $stats['reports'],
				'icon'   => 'dashicons-flag',
				'url'    => admin_url( 'admin.php?page=arshid6social-moderation' ),
			),
			array(
				'label'  => __( 'Products Archive', 'social-network-6' ),
				'count'  => $stats['products'],
				'icon'   => 'dashicons-store',
				'url'    => admin_url( 'admin.php?page=arshid6social-marketplace' ),
			),
		);

		if ( get_option( 'arshid6social_verification_enabled', false ) ) {
			$card_defs[] = array(
				'label'  => __( 'Pending Verifications', 'social-network-6' ),
				'count'  => $stats['verifications'],
				'icon'   => 'dashicons-yes-alt',
				'url'    => admin_url( 'admin.php?page=arshid6social-verification' ),
			);
		}

		usort( $card_defs, fn( $a, $b ) => strcmp( $a['label'], $b['label'] ) );

		$cards = '';
		foreach ( $card_defs as $card ) {
			$inner = sprintf(
				'<span class="dashicons %1$s"></span><strong>%2$s</strong><p>%3$s</p>',
				esc_attr( $card['icon'] ),
				esc_html( number_format_i18n( $card['count'] ) ),
				esc_html( $card['label'] )
			);
			if ( $card['url'] ) {
				$cards .= '<div class="arshid6social-stat-card"><a href="' . esc_url( $card['url'] ) . '" style="text-decoration:none;color:inherit;">' . $inner . '</a></div>';
			} else {
				$cards .= '<div class="arshid6social-stat-card">' . $inner . '</div>';
			}
		}

		echo '<div class="arshid6social-stat-cards">' . $cards . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '</div>';
	}

	/**
	 * Fetches quick stats for the dashboard overview.
	 *
	 * @return array<string, int>
	 */
	private function get_dashboard_stats(): array {
		global $wpdb;

		$stats = array(
			'members'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ), // phpcs:ignore WordPress.DB
			'activity' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sn_activity WHERE is_spam = 0" ), // phpcs:ignore WordPress.DB
			'groups'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sn_groups" ), // phpcs:ignore WordPress.DB
			'reports'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sn_reports WHERE status = %s", 'pending' ) ), // phpcs:ignore WordPress.DB
			'products' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}arshid6social_listings" ), // phpcs:ignore WordPress.DB
		);

		if ( get_option( 'arshid6social_verification_enabled', false ) ) {
			$stats['verifications'] = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB
				"SELECT COUNT(*) FROM {$wpdb->prefix}sn_verification_requests WHERE status = %s",
				'pending'
			) );
		}

		return $stats;
	}

	/**
	 * Shows notices: setup wizard prompt and missing pages warning.
	 */
	public function maybe_show_setup_notice(): void {
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			return;
		}

		if ( get_transient( 'arshid6social_setup_redirect' ) ) {
			delete_transient( 'arshid6social_setup_redirect' );
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Welcome to 6Arshid Social Community!', 'social-network-6' ),
				esc_url( admin_url( 'index.php?page=arshid6social-setup' ) ),
				esc_html__( 'Run the setup wizard →', 'social-network-6' )
			);
		}

		// Warn if required pages are missing.
		$page_options = array( 'arshid6social_page_members', 'arshid6social_page_activity', 'arshid6social_page_groups', 'arshid6social_page_messages' );
		$missing      = array();
		foreach ( $page_options as $opt ) {
			$page_id = (int) get_option( $opt, 0 );
			if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
				$missing[] = $opt;
			}
		}

		if ( $missing ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s <a href="%s"><strong>%s</strong></a></p></div>',
				esc_html__( '6Arshid Social Community: Some required pages are missing.', 'social-network-6' ),
				esc_url( admin_url( 'admin.php?page=arshid6social-pages&arshid6social_create_pages=1&_wpnonce=' . wp_create_nonce( 'arshid6social_create_pages' ) ) ),
				esc_html__( 'Click here to create them automatically →', 'social-network-6' )
			);
		}
	}

	/**
	 * Adds quick links under the plugin name on the Plugins page.
	 *
	 * @param string[] $links Default plugin action links.
	 * @return string[]
	 */
	public function plugin_action_links( array $links ): array {
		$links['settings'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=arshid6social-settings' ) ),
			esc_html__( 'Settings', 'social-network-6' )
		);
		$links['pages'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=arshid6social-pages' ) ),
			esc_html__( 'Pages', 'social-network-6' )
		);
		return $links;
	}

	/**
	 * Handles the one-click "create pages" action from the admin notice link.
	 */
	public function maybe_create_pages(): void {
		if ( ! isset( $_GET['arshid6social_create_pages'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'arshid6social_create_pages' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			return;
		}

		\Arshid6Social\Activator::create_pages();
		flush_rewrite_rules();

		wp_safe_redirect( admin_url( 'admin.php?page=arshid6social-pages&pages_created=1' ) );
		exit;
	}

	/** Registers per-page Screen Option for the active Monetization tab. */
	public function setup_monetization_screen_options(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';

		if ( 'payouts' === $tab ) {
			add_screen_option( 'per_page', array(
				'label'   => __( 'IBANs per page', 'social-network-6' ),
				'default' => 20,
				'option'  => 'arshid6social_payouts_per_page',
			) );
		} elseif ( 'transactions' === $tab ) {
			add_screen_option( 'per_page', array(
				'label'   => __( 'Transactions per page', 'social-network-6' ),
				'default' => 25,
				'option'  => 'arshid6social_tx_per_page',
			) );
		}
	}

	/** Saves monetization per-page screen options (WP 5.4.2+). */
	public function save_monetization_per_page( $status, string $option, $value ): int {
		return (int) $value;
	}

	/** Saves monetization per-page screen options (WP < 5.4.2). */
	public function save_monetization_per_page_legacy( $status, string $option, $value ) {
		if ( in_array( $option, array( 'arshid6social_payouts_per_page', 'arshid6social_tx_per_page' ), true ) ) {
			return (int) $value;
		}
		return $status;
	}

	/**
	 * Renders the standalone Monetization settings page with tab navigation.
	 */
	public function render_monetization(): void {
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'social-network-6' ) );
		}

		wp_enqueue_media();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';

		$tabs = array(
			'settings'     => __( 'Settings', 'social-network-6' ),
			'payouts'      => __( 'Creator Payouts — IBAN List', 'social-network-6' ),
			'transactions' => __( 'Financial Transactions', 'social-network-6' ),
		);

		echo '<div class="wrap arshid6social-admin-settings">';
		echo '<h1>' . esc_html__( 'Monetization', 'social-network-6' ) . '</h1>';

		echo '<nav class="nav-tab-wrapper" style="margin-bottom:0;">';
		foreach ( $tabs as $key => $label ) {
			$url    = add_query_arg( array( 'page' => 'arshid6social-monetization', 'tab' => $key ), admin_url( 'admin.php' ) );
			$active = $current_tab === $key ? ' nav-tab-active' : '';
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:20px 24px;">';

		if ( 'payouts' === $current_tab ) {
			do_action( 'arshid6social_monetization_payouts_tab' );
		} elseif ( 'transactions' === $current_tab ) {
			$this->render_monetization_transactions_tab();
		} else {
			echo '<form method="post" action="options.php">';
			settings_fields( 'arshid6social_monetization' );
			do_action( 'arshid6social_settings_tab_monetization' );
			submit_button( __( 'Save Settings', 'social-network-6' ) );
			echo '</form>';
		}

		echo '</div>';
		echo '</div>';
	}

	/** Renders the Financial Transactions tab inside the Monetization page. */
	private function render_monetization_transactions_tab(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'sixarshidsc_transactions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			echo '<p class="description">' . esc_html__( 'Transactions table does not exist yet. Enable Monetization and save settings first.', 'social-network-6' ) . '</p>';
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search      = isset( $_GET['s'] )     ? sanitize_text_field( wp_unslash( $_GET['s'] ) )     : '';
		$filter_type = isset( $_GET['txtype'] ) ? sanitize_key( $_GET['txtype'] )                    : '';
		$filter_status = isset( $_GET['txstatus'] ) ? sanitize_key( $_GET['txstatus'] )              : '';
		$paged       = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged']                        : 1 );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$per_page = (int) get_user_option( 'arshid6social_tx_per_page' );
		if ( $per_page < 1 ) {
			$per_page = 25;
		}
		$offset = ( $paged - 1 ) * $per_page;

		// Build WHERE clauses.
		$where_parts = array( '1=1' );
		$where_vals  = array();

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where_parts[] = '( t.gateway_ref LIKE %s OR pu.user_login LIKE %s OR pu.user_email LIKE %s OR pu.display_name LIKE %s OR cu.user_login LIKE %s OR cu.user_email LIKE %s OR cu.display_name LIKE %s )';
			array_push( $where_vals, $like, $like, $like, $like, $like, $like, $like );
		}
		if ( '' !== $filter_type ) {
			$where_parts[] = 't.type = %s';
			$where_vals[]  = $filter_type;
		}
		if ( '' !== $filter_status ) {
			$where_parts[] = 't.status = %s';
			$where_vals[]  = $filter_status;
		}

		$where_sql = implode( ' AND ', $where_parts );
		$join_sql  = "LEFT JOIN {$wpdb->users} pu ON pu.ID = t.payer_id LEFT JOIN {$wpdb->users} cu ON cu.ID = t.creator_id";

		// Count total matching rows.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$table} t {$join_sql} WHERE {$where_sql}";
		$total = (int) ( empty( $where_vals )
			? $wpdb->get_var( $count_sql ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			: $wpdb->get_var( $wpdb->prepare( $count_sql, ...$where_vals ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		// Fetch page rows.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows_sql = "SELECT t.*, pu.display_name AS payer_name, pu.user_email AS payer_email, cu.display_name AS creator_name, cu.user_email AS creator_email FROM {$table} t {$join_sql} WHERE {$where_sql} ORDER BY t.id DESC LIMIT %d OFFSET %d";
		$fetch_vals = array_merge( $where_vals, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, ...$fetch_vals ) );

		$total_pages = (int) ceil( $total / $per_page );

		$base_url = add_query_arg(
			array_filter( array(
				'page'     => 'arshid6social-monetization',
				'tab'      => 'transactions',
				's'        => $search ?: null,
				'txtype'   => $filter_type ?: null,
				'txstatus' => $filter_status ?: null,
			) ),
			admin_url( 'admin.php' )
		);

		$type_colors = array(
			'subscription' => '#2563eb',
			'ppv'          => '#7c3aed',
			'payout'       => '#d97706',
			'refund'       => '#dc2626',
		);
		$status_colors = array(
			'completed' => '#16a34a',
			'pending'   => '#d97706',
			'failed'    => '#dc2626',
			'refunded'  => '#6b7280',
		);
		?>
		<h2 style="margin-top:0;"><?php esc_html_e( 'Financial Transactions', 'social-network-6' ); ?></h2>

		<form method="get" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;align-items:center;">
			<input type="hidden" name="page" value="arshid6social-monetization" />
			<input type="hidden" name="tab"  value="transactions" />

			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
				placeholder="<?php esc_attr_e( 'Name, email, gateway ref…', 'social-network-6' ); ?>"
				style="padding:5px 9px;border:1px solid #8c8f94;border-radius:4px;min-width:240px;" />

			<select name="txtype" style="padding:5px;border:1px solid #8c8f94;border-radius:4px;">
				<option value=""><?php esc_html_e( 'All types', 'social-network-6' ); ?></option>
				<?php foreach ( array( 'subscription', 'ppv', 'payout', 'refund' ) as $t ) : ?>
					<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $filter_type, $t ); ?>><?php echo esc_html( ucfirst( $t ) ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="txstatus" style="padding:5px;border:1px solid #8c8f94;border-radius:4px;">
				<option value=""><?php esc_html_e( 'All statuses', 'social-network-6' ); ?></option>
				<?php foreach ( array( 'completed', 'pending', 'failed', 'refunded' ) as $st ) : ?>
					<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $filter_status, $st ); ?>><?php echo esc_html( ucfirst( $st ) ); ?></option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( __( 'Filter', 'social-network-6' ), 'secondary', '', false, array( 'style' => 'padding:4px 12px;' ) ); ?>
			<?php if ( $search || $filter_type || $filter_status ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'arshid6social-monetization', 'tab' => 'transactions' ), admin_url( 'admin.php' ) ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'social-network-6' ); ?></a>
			<?php endif; ?>
		</form>

		<p class="description" style="margin-bottom:8px;">
			<?php
			/* translators: %d: total number of transactions */
			printf( esc_html__( '%d transaction(s) found.', 'social-network-6' ), (int) $total );
			?>
		</p>

		<?php if ( empty( $rows ) ) : ?>
			<p class="description" style="padding:1rem;background:#f6f7f7;border-radius:4px;">
				<?php esc_html_e( 'No transactions match the current filter.', 'social-network-6' ); ?>
			</p>
		<?php else : ?>
			<table class="widefat striped" style="margin-top:0;">
				<thead>
					<tr>
						<th>#</th>
						<th><?php esc_html_e( 'Type', 'social-network-6' ); ?></th>
						<th><?php esc_html_e( 'Payer', 'social-network-6' ); ?></th>
						<th><?php esc_html_e( 'Creator', 'social-network-6' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'social-network-6' ); ?></th>
						<th><?php esc_html_e( 'Fee', 'social-network-6' ); ?></th>
						<th><?php esc_html_e( 'Status', 'social-network-6' ); ?></th>
						<th><?php esc_html_e( 'Gateway Ref', 'social-network-6' ); ?></th>
						<th><?php esc_html_e( 'Date', 'social-network-6' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $tx ) :
					$type_color   = $type_colors[ $tx->type ]   ?? '#374151';
					$status_color = $status_colors[ $tx->status ] ?? '#374151';
					?>
					<tr>
						<td style="color:#6b7280;font-size:.85em;"><?php echo (int) $tx->id; ?></td>
						<td>
							<span style="color:<?php echo esc_attr( $type_color ); ?>;font-weight:600;font-size:.8em;text-transform:uppercase;letter-spacing:.04em;">
								<?php echo esc_html( $tx->type ); ?>
							</span>
						</td>
						<td style="font-size:.875em;">
							<?php if ( $tx->payer_name ) : ?>
								<strong><?php echo esc_html( $tx->payer_name ); ?></strong><br />
								<span style="color:#666;"><?php echo esc_html( $tx->payer_email ); ?></span>
							<?php else : ?>
								<span style="color:#999;">—</span>
							<?php endif; ?>
						</td>
						<td style="font-size:.875em;">
							<?php if ( $tx->creator_name ) : ?>
								<strong><?php echo esc_html( $tx->creator_name ); ?></strong><br />
								<span style="color:#666;"><?php echo esc_html( $tx->creator_email ); ?></span>
							<?php else : ?>
								<span style="color:#999;">—</span>
							<?php endif; ?>
						</td>
						<td style="font-weight:600;">
							<?php echo esc_html( number_format( (float) $tx->amount, 2 ) . ' ' . strtoupper( $tx->currency ) ); ?>
						</td>
						<td style="color:#6b7280;font-size:.875em;">
							<?php echo esc_html( number_format( (float) $tx->platform_fee, 2 ) ); ?>
						</td>
						<td>
							<span style="color:<?php echo esc_attr( $status_color ); ?>;font-weight:600;font-size:.8em;text-transform:uppercase;">
								<?php echo esc_html( $tx->status ); ?>
							</span>
						</td>
						<td style="font-size:.8em;color:#555;word-break:break-all;max-width:160px;">
							<?php echo esc_html( $tx->gateway_ref ?: '—' ); ?>
						</td>
						<td style="font-size:.85em;white-space:nowrap;color:#555;">
							<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' H:i', strtotime( $tx->created_at ) ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) :
				$pagination_args = array(
					'base'      => add_query_arg( 'paged', '%#%', $base_url ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				);
				?>
				<div style="margin-top:16px;">
					<?php echo paginate_links( $pagination_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}
}
