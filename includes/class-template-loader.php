<?php
namespace Arshid6Social;

/**
 * Template loader with theme override support.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Template_Loader
 *
 * Loads templates from:
 *   1. Active theme:        {theme}/social-network/{template}.php
 *   2. Active child theme:  {child-theme}/social-network/{template}.php
 *   3. Plugin default:      {plugin}/templates/{template}.php
 */
final class Template_Loader {

	private static ?Template_Loader $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Locates and loads a template file, optionally extracting variables.
	 *
	 * @param string               $template  Relative path, e.g. 'members/profile.php'.
	 * @param array<string, mixed> $args      Variables to extract into the template scope.
	 * @param bool                 $return    When true, returns output as string.
	 * @return string Rendered HTML if $return is true, empty string otherwise.
	 */
	public function get_template( string $template, array $args = array(), bool $return = false ): string {
		$file = $this->locate( $template );

		if ( ! $file ) {
			return '';
		}

		if ( $args ) {
			// phpcs:ignore WordPress.PHP.DontExtract
			extract( $args, EXTR_SKIP );
		}

		if ( $return ) {
			ob_start();
			include $file;
			return ob_get_clean() ?: '';
		}

		include $file;
		return '';
	}

	/**
	 * Locates the highest-priority version of a template file.
	 *
	 * @param string $template Relative path to the template.
	 * @return string|false Absolute path or false if not found.
	 */
	public function locate( string $template ): string|false {
		// Sanitise — strip leading slashes, resolve no directory traversal.
		$template = ltrim( $template, '/\\' );
		if ( str_contains( $template, '..' ) ) {
			return false;
		}

		$locations = array(
			get_stylesheet_directory() . '/social-network/' . $template,
			get_template_directory()   . '/social-network/' . $template,
			ARSHID6SOCIAL_TEMPLATES_DIR . $template,
		);

		foreach ( $locations as $file ) {
			if ( file_exists( $file ) ) {
				return $file;
			}
		}

		return false;
	}

	/**
	 * Returns the URL to a plugin asset (for use in templates).
	 *
	 * @param string $relative_path Path relative to /assets/.
	 * @return string Escaped URL.
	 */
	public function asset_url( string $relative_path ): string {
		return esc_url( ARSHID6SOCIAL_ASSETS_URL . ltrim( $relative_path, '/' ) );
	}
}
