<?php
namespace Arshid6Social;

/**
 * PSR-4 autoloader for the 6Arshid Social Community plugin.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 *
 * Maps namespace prefixes to filesystem paths and loads class files on demand.
 */
class Autoloader {

	/**
	 * Namespace-to-directory map.
	 *
	 * @var array<string,string>
	 */
	private static array $prefixes = array(
		'Arshid6Social\\Components\\Members\\'       => 'components/members/',
		'Arshid6Social\\Components\\Activity\\'      => 'components/activity/',
		'Arshid6Social\\Components\\Groups\\'        => 'components/groups/',
		'Arshid6Social\\Components\\Friends\\'       => 'components/friends/',
		'Arshid6Social\\Components\\Messages\\'      => 'components/messages/',
		'Arshid6Social\\Components\\Notifications\\' => 'components/notifications/',
		'Arshid6Social\\Components\\Moderation\\'    => 'components/moderation/',
		'Arshid6Social\\Components\\Blocking\\'      => 'components/blocking/',
		'Arshid6Social\\Components\\Verification\\'  => 'components/verification/',
		'Arshid6Social\\Components\\Stories\\'       => 'components/stories/',
		'Arshid6Social\\Components\\Marketplace\\'   => 'components/marketplace/',
		'Arshid6Social\\Components\\Monetization\\' => 'components/monetization/',
		'Arshid6Social\\Components\\Ads\\'           => 'components/ads/',
		'Arshid6Social\\Components\\Search\\'        => 'components/search/',
		'Arshid6Social\\Engagement\\Features\\'      => 'engagement/features/',
		'Arshid6Social\\Engagement\\'                => 'engagement/',
		'Arshid6Social\\Admin\\'                     => 'admin/',
		'Arshid6Social\\REST\\'                      => 'rest/',
		'Arshid6Social\\'                            => '',
	);

	/**
	 * Registers this autoloader with SPL.
	 */
	public static function register(): void {
		spl_autoload_register( array( static::class, 'load' ) );
	}

	/**
	 * Loads a class file for the given fully-qualified class name.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function load( string $class ): void {
		foreach ( self::$prefixes as $prefix => $relative_dir ) {
			if ( 0 !== strpos( $class, $prefix ) ) {
				continue;
			}

			$relative_class = substr( $class, strlen( $prefix ) );
			$file           = self::class_to_file( $relative_class );
			$full_path      = ARSHID6SOCIAL_INCLUDES_DIR . $relative_dir . $file;

			if ( file_exists( $full_path ) ) {
				require_once $full_path;
				return;
			}
		}
	}

	/**
	 * Converts a class name to a WordPress-standard filename.
	 *
	 * e.g. "Plugin" → "class-plugin.php", "REST_Controller" → "class-rest-controller.php"
	 *
	 * @param string $class_name Bare class name (no namespace).
	 * @return string File name.
	 */
	private static function class_to_file( string $class_name ): string {
		$file = strtolower( str_replace( array( '_', '\\' ), array( '-', '/' ), $class_name ) );
		return 'class-' . $file . '.php';
	}
}
