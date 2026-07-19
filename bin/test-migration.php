<?php
/**
 * Dev-only CLI test harness — NOT part of the distributed plugin (excluded via
 * .distignore and .gitattributes export-ignore). It is only ever run manually from
 * the command line on a staging server; it is never loaded by WordPress at runtime.
 * The phpcs:ignoreFile below exempts it from plugin runtime rules (escaped output,
 * WP_Filesystem, etc.) that do not apply to a standalone CLI script.
 */
// phpcs:ignoreFile

// Block any direct web access; allow CLI execution only.
if ( ! defined( 'ABSPATH' ) && 'cli' !== php_sapi_name() ) {
	exit;
}

/**
 * Staging-server test for the sn_ -> arshid6social_ table rename migration.
 *
 * Runs the REAL Arshid6Social\Activator::rename_legacy_tables() against your live
 * WordPress database engine (proving the exact INFORMATION_SCHEMA / RENAME TABLE SQL
 * works there), but under a TEMPORARY SANDBOX table prefix so it NEVER touches your
 * real plugin tables or data. All sandbox tables are dropped again at the end.
 *
 * HOW TO RUN (from anywhere on the staging server):
 *
 *     php wp-content/plugins/6arshid-social-community/bin/test-migration.php
 *
 *   or with an explicit path to WordPress:
 *
 *     php bin/test-migration.php /full/path/to/wordpress
 *
 * WHAT A PASS LOOKS LIKE:
 *   The script prints a series of "PASS ..." lines and ends with
 *   "RESULT: N passed, 0 failed" and exit code 0.
 *   Any "FAIL" line (exit code 1) means the migration needs attention.
 *
 * SAFETY: the script only ever creates/renames/drops tables whose names begin with
 *   {$wpdb->prefix}migtest_  — it does not read, write, rename or drop any other table.
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 2 );
}

// ── Locate and load WordPress ────────────────────────────────────────────────
$wp_load = null;
$explicit = $argv[1] ?? '';
if ( $explicit ) {
	$cand = rtrim( $explicit, '/\\' ) . '/wp-load.php';
	if ( is_file( $cand ) ) {
		$wp_load = $cand;
	}
}
if ( ! $wp_load ) {
	// Walk up from this file until wp-load.php is found (…/wp-content/plugins/slug/bin).
	$dir = __DIR__;
	for ( $i = 0; $i < 10; $i++ ) {
		if ( is_file( $dir . '/wp-load.php' ) ) {
			$wp_load = $dir . '/wp-load.php';
			break;
		}
		$parent = dirname( $dir );
		if ( $parent === $dir ) {
			break;
		}
		$dir = $parent;
	}
}
if ( ! $wp_load ) {
	fwrite( STDERR, "Could not locate wp-load.php. Pass the WordPress root path as the first argument.\n" );
	exit( 2 );
}

define( 'WP_USE_THEMES', false );
require $wp_load;

global $wpdb;

// Make sure the Activator class is available (load directly if the plugin isn't active).
if ( ! class_exists( '\\Arshid6Social\\Activator' ) ) {
	$activator = dirname( __DIR__ ) . '/includes/class-activator.php';
	if ( is_file( $activator ) ) {
		if ( ! defined( 'ABSPATH' ) ) {
			fwrite( STDERR, "ABSPATH not defined after loading WordPress.\n" );
			exit( 2 );
		}
		require $activator;
	}
}
if ( ! class_exists( '\\Arshid6Social\\Activator' ) ) {
	fwrite( STDERR, "Arshid6Social\\Activator not found. Is the plugin installed?\n" );
	exit( 2 );
}

$passes = 0;
$fails  = 0;
function ok( $cond, $label ) {
	global $passes, $fails;
	if ( $cond ) {
		$passes++;
		echo "  PASS  $label\n";
	} else {
		$fails++;
		echo "  FAIL  $label\n";
	}
}

$real_prefix    = $wpdb->prefix;
$sandbox_prefix = $real_prefix . 'migtest_';

// The migration reads suffixes from the private legacy_table_map(); mirror it via reflection.
$ref = new ReflectionMethod( 'Arshid6Social\\Activator', 'legacy_table_map' );
$ref->setAccessible( true );
$map = $ref->invoke( null );

// Use a representative subset so the test is quick; the migration still iterates the full map.
$subset = array_slice( $map, 0, 12, true );

/** Drop every sandbox table (old + new names) for a clean slate. */
$drop_sandbox = function () use ( $wpdb, $sandbox_prefix, $map ) {
	foreach ( $map as $old => $new ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $sandbox_prefix . $old . '`' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $sandbox_prefix . $new . '`' );
	}
};

$table_exists = function ( $name ) use ( $wpdb ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
			DB_NAME,
			$name
		)
	);
};
$row_count = function ( $name ) use ( $wpdb ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $name . '`' );
};

echo "Sandbox prefix: {$sandbox_prefix}\n";
echo str_repeat( '=', 68 ) . "\n";

try {
	// Temporarily point the plugin's migration at the sandbox namespace.
	$wpdb->prefix = $sandbox_prefix;

	// ── Seed sandbox "old" tables with representative rows ────────────────────
	$drop_sandbox();
	$expected_counts = array();
	$n = 0;
	foreach ( $subset as $old => $new ) {
		$tbl = $sandbox_prefix . $old;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "CREATE TABLE `{$tbl}` ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, label VARCHAR(191) NOT NULL, PRIMARY KEY (id) )" );
		$rows = ( $n % 4 ) + 1; // 1..4 rows
		for ( $r = 1; $r <= $rows; $r++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( "INSERT INTO `{$tbl}` (label) VALUES (%s)", $old . "-row-$r" ) );
		}
		$expected_counts[ $old ] = $rows;
		$n++;
	}

	// ── Scenario 1: basic rename + data integrity ────────────────────────────
	echo "SCENARIO 1 — basic rename with data integrity\n";
	\Arshid6Social\Activator::rename_legacy_tables();
	foreach ( $subset as $old => $new ) {
		ok( ! $table_exists( $sandbox_prefix . $old ), "old `{$old}` renamed away" );
		ok( $table_exists( $sandbox_prefix . $new ), "new `{$new}` present" );
		ok( $row_count( $sandbox_prefix . $new ) === $expected_counts[ $old ], "`{$new}` kept {$expected_counts[$old]} row(s)" );
	}

	// ── Scenario 2: idempotency ──────────────────────────────────────────────
	echo "SCENARIO 2 — idempotency (2nd run)\n";
	$err = false;
	try {
		\Arshid6Social\Activator::rename_legacy_tables();
	} catch ( \Throwable $e ) {
		$err = true;
		echo '  exception: ' . $e->getMessage() . "\n";
	}
	ok( ! $err, '2nd run raised no error' );
	$intact = true;
	foreach ( $subset as $old => $new ) {
		if ( ! $table_exists( $sandbox_prefix . $new ) || $row_count( $sandbox_prefix . $new ) !== $expected_counts[ $old ] ) {
			$intact = false;
		}
	}
	ok( $intact, 'data intact and unchanged after 2nd run' );

	// ── Scenario 3: partial-migration resume ─────────────────────────────────
	echo "SCENARIO 3 — partial-migration resume\n";
	$drop_sandbox();
	foreach ( $subset as $old => $new ) {
		$tbl = $sandbox_prefix . $old;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "CREATE TABLE `{$tbl}` ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, label VARCHAR(191) NOT NULL, PRIMARY KEY (id) )" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "INSERT INTO `{$tbl}` (label) VALUES (%s)", "$old-seed" ) );
	}
	$keys = array_keys( $subset );
	$half = array_slice( $keys, 0, (int) ceil( count( $keys ) / 2 ) );
	foreach ( $half as $old ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'RENAME TABLE `' . $sandbox_prefix . $old . '` TO `' . $sandbox_prefix . $subset[ $old ] . '`' );
	}
	\Arshid6Social\Activator::rename_legacy_tables();
	$resume_ok = true;
	foreach ( $subset as $old => $new ) {
		if ( ! $table_exists( $sandbox_prefix . $new ) || $table_exists( $sandbox_prefix . $old ) ) {
			$resume_ok = false;
		}
	}
	ok( $resume_ok, 'resume migrated the remaining half, none left as sn_' );

	// ── Scenario 4: fresh install (no legacy tables) ─────────────────────────
	echo "SCENARIO 4 — fresh install no-op\n";
	$drop_sandbox();
	$err = false;
	try {
		\Arshid6Social\Activator::rename_legacy_tables();
	} catch ( \Throwable $e ) {
		$err = true;
		echo '  exception: ' . $e->getMessage() . "\n";
	}
	ok( ! $err, 'no error when there is nothing to migrate' );

} finally {
	// Always clean up and restore the real prefix.
	$drop_sandbox();
	$wpdb->prefix = $real_prefix;
}

echo str_repeat( '=', 68 ) . "\n";
printf( "RESULT: %d passed, %d failed\n", $passes, $fails );

// Informational: report whether real legacy tables still exist on this site.
$remaining = array();
foreach ( $map as $old => $new ) {
	if ( $table_exists( $real_prefix . $old ) ) {
		$remaining[] = $real_prefix . $old;
	}
}
if ( $remaining ) {
	echo "\nNOTE: this site still has " . count( $remaining ) . " un-migrated legacy table(s):\n  " . implode( "\n  ", $remaining ) . "\n";
	echo "They will be migrated automatically on the next plugin update (DB version bump).\n";
} else {
	echo "\nNOTE: no wp_sn_* legacy tables remain on this site (already migrated or fresh install).\n";
}

exit( $fails ? 1 : 0 );
