<?php
namespace Arshid6Social\Admin;

/**
 * Bootstrap Icons picker for WordPress Pages.
 *
 * Icons are bundled directly inside the plugin at assets/icons/bootstrap-icons.json.
 * No internet connection or manual download step is needed by the end user.
 *
 * To regenerate the bundled data file run (once, during development):
 *   php build/download-bootstrap-icons.php
 *
 * Bootstrap Icons © The Bootstrap Authors — MIT License
 * https://icons.getbootstrap.com/
 *
 * @package Arshid6Social\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Page_Icons
 */
final class Admin_Page_Icons {

	const META_KEY   = '_arshid6social_page_icon';
	const BI_VERSION = '1.11.3';

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes',             array( $this, 'add_meta_box'   ) );
		add_action( 'save_post_page',             array( $this, 'save_meta'      ) );
		add_action( 'admin_enqueue_scripts',      array( $this, 'enqueue_assets' ) );
		add_filter( 'manage_pages_columns',       array( $this, 'add_column'     ) );
		add_action( 'manage_pages_custom_column', array( $this, 'render_column'  ), 10, 2 );
	}

	// ── Bundled asset paths ───────────────────────────────────────────────────

	private function icons_json_path(): string {
		return ARSHID6SOCIAL_PLUGIN_DIR . 'assets/icons/bootstrap-icons.json';
	}

	private function icons_json_url(): string {
		return ARSHID6SOCIAL_ASSETS_URL . 'icons/bootstrap-icons.json';
	}

	public function icons_available(): bool {
		return file_exists( $this->icons_json_path() );
	}

	// ── Meta box ──────────────────────────────────────────────────────────────

	public function add_meta_box(): void {
		add_meta_box(
			'arshid6social-page-icon',
			__( 'Page Icon', '6arshid social community' ),
			array( $this, 'render_meta_box' ),
			'page',
			'side',
			'default'
		);
	}

	public function render_meta_box( \WP_Post $post ): void {
		$current   = (string) get_post_meta( $post->ID, self::META_KEY, true );
		$available = $this->icons_available();
		wp_nonce_field( 'arshid6social_page_icon_' . $post->ID, 'arshid6social_page_icon_nonce' );
		?>
		<div id="arshid6social-icp-wrap"
			data-available="<?php echo $available ? '1' : '0'; ?>"
			data-icons-url="<?php echo esc_url( $this->icons_json_url() ); ?>">

			<input type="hidden" id="arshid6social_page_icon" name="arshid6social_page_icon"
				value="<?php echo esc_attr( $current ); ?>">

			<!-- Current icon preview -->
			<div id="arshid6social-icp-preview">
				<span id="arshid6social-icp-preview-svg">
					<?php
					if ( $current && $available ) {
						echo wp_kses( $this->get_icon_svg( $current, 28 ), $this->allowed_svg_kses() );
					}
					?>
				</span>
				<span id="arshid6social-icp-preview-name">
					<?php echo $current ? esc_html( $current ) : esc_html__( '— none —', '6arshid social community' ); ?>
				</span>
			</div>

			<?php if ( $available ) : ?>
			<div class="arshid6social-icp-actions">
				<button type="button" id="arshid6social-icp-open" class="button button-primary">
					<?php esc_html_e( 'Choose Icon', '6arshid social community' ); ?>
				</button>
				<button type="button" id="arshid6social-icp-clear" class="button"
					<?php echo $current ? '' : 'style="display:none"'; ?>>
					<?php esc_html_e( 'Clear', '6arshid social community' ); ?>
				</button>
			</div>
			<?php else : ?>
			<p style="color:#b91c1c;font-size:12px;margin:6px 0 0;">
				<?php esc_html_e( 'Icon data file not found. Please re-install the plugin.', '6arshid social community' ); ?>
			</p>
			<?php endif; ?>
		</div>

		<!-- ── Picker modal ── -->
		<div id="arshid6social-icp-modal" hidden>
			<div id="arshid6social-icp-backdrop"></div>
			<div id="arshid6social-icp-dialog" role="dialog" aria-modal="true"
				aria-label="<?php esc_attr_e( 'Select an icon', '6arshid social community' ); ?>">

				<div id="arshid6social-icp-dialog-head">
					<strong><?php esc_html_e( 'Select an Icon', '6arshid social community' ); ?></strong>
					<span id="arshid6social-icp-icon-count"></span>
					<button type="button" id="arshid6social-icp-close"
						aria-label="<?php esc_attr_e( 'Close', '6arshid social community' ); ?>">&#10005;</button>
				</div>

				<div id="arshid6social-icp-search-row">
					<input type="search" id="arshid6social-icp-search"
						placeholder="<?php esc_attr_e( 'Search icons…', '6arshid social community' ); ?>"
						autocomplete="off" spellcheck="false">
				</div>

				<div id="arshid6social-icp-grid-wrap">
					<div id="arshid6social-icp-grid"></div>
					<div id="arshid6social-icp-load-more-wrap">
						<button type="button" id="arshid6social-icp-load-more" class="button" hidden>
							<?php esc_html_e( 'Load more', '6arshid social community' ); ?>
						</button>
					</div>
				</div>

				<div id="arshid6social-icp-dialog-foot">
					Bootstrap Icons v<?php echo esc_html( self::BI_VERSION ); ?> &mdash; MIT License &mdash;
					<a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener noreferrer">icons.getbootstrap.com</a>
				</div>
			</div>
		</div>
		<?php
	}

	public function save_meta( int $post_id ): void {
		if ( empty( $_POST['arshid6social_page_icon_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['arshid6social_page_icon_nonce'] ), 'arshid6social_page_icon_' . $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$icon = sanitize_text_field( wp_unslash( $_POST['arshid6social_page_icon'] ?? '' ) );
		if ( $icon ) {
			update_post_meta( $post_id, self::META_KEY, $icon );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	// ── Pages list column ─────────────────────────────────────────────────────

	public function add_column( array $columns ): array {
		$out = array();
		foreach ( $columns as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'title' === $k ) {
				$out['arshid6social_icon'] = __( 'Icon', '6arshid social community' );
			}
		}
		return $out;
	}

	public function render_column( string $column, int $post_id ): void {
		if ( 'arshid6social_icon' !== $column ) {
			return;
		}
		$icon = (string) get_post_meta( $post_id, self::META_KEY, true );
		if ( $icon && $this->icons_available() ) {
			$svg = $this->get_icon_svg( $icon, 22 );
			if ( $svg ) {
				echo wp_kses( $svg, $this->allowed_svg_kses() );
				echo '<br><small style="color:#888;font-size:11px;">' . esc_html( $icon ) . '</small>';
				return;
			}
		}
		echo '<span style="color:#ccc;">—</span>';
	}

	// ── Enqueue ───────────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || 'page' !== $screen->post_type ) {
			return;
		}
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'arshid6social-icon-picker',
			ARSHID6SOCIAL_ASSETS_URL . 'css/admin-icon-picker.css',
			array(),
			ARSHID6SOCIAL_VERSION
		);

		wp_enqueue_script(
			'arshid6social-icon-picker',
			ARSHID6SOCIAL_ASSETS_URL . 'js/arshid6social-icon-picker.js',
			array(),
			ARSHID6SOCIAL_VERSION,
			true
		);

		wp_localize_script( 'arshid6social-icon-picker', 'ARSHID6SOCIALICP', array(
			'iconsUrl' => $this->icons_json_url(),
			'version'  => self::BI_VERSION,
		) );
	}

	// ── Get SVG for a named icon ──────────────────────────────────────────────

	public function get_icon_svg( string $name, int $size = 16 ): string {
		if ( ! $this->icons_available() ) {
			return '';
		}

		static $data = null;
		if ( null === $data ) {
			$raw  = @file_get_contents( $this->icons_json_path() ); // phpcs:ignore
			$data = $raw ? (array) json_decode( $raw, true ) : array();
		}

		if ( ! isset( $data[ $name ] ) ) {
			return '';
		}

		$inner = wp_kses( (string) $data[ $name ], $this->allowed_svg_kses() );
		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">%2$s</svg>',
			(int) $size,
			$inner
		);
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function allowed_svg_kses(): array {
		$common = array(
			'fill'      => true,
			'fill-rule' => true,
			'clip-rule' => true,
			'opacity'   => true,
		);
		return array(
			'path'      => array_merge( $common, array( 'd' => true ) ),
			'circle'    => array_merge( $common, array( 'cx' => true, 'cy' => true, 'r' => true ) ),
			'ellipse'   => array_merge( $common, array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ) ),
			'rect'      => array_merge( $common, array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ) ),
			'polyline'  => array_merge( $common, array( 'points' => true ) ),
			'polygon'   => array_merge( $common, array( 'points' => true ) ),
			'line'      => array_merge( $common, array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true ) ),
			'g'         => $common,
		);
	}
}
