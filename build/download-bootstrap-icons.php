<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Build script — Download & bundle Bootstrap Icons.
 *
 * Run ONCE from the project root (during development, not on the server):
 *
 *   php build/download-bootstrap-icons.php
 *
 * Output: assets/icons/bootstrap-icons.json
 *
 * The generated file is committed to the repository so end users
 * never need to download anything.
 *
 * Bootstrap Icons © The Bootstrap Authors — MIT License
 * https://icons.getbootstrap.com/
 *
 * @package Arshid6Social
 */

// This is a command-line development tool, not runtime plugin code.
// phpcs:ignoreFile

$bi_version = '1.11.3';
$zip_url    = 'https://github.com/twbs/icons/releases/download/v' . $bi_version . '/bootstrap-icons-' . $bi_version . '.zip';
$out_file   = __DIR__ . '/../assets/icons/bootstrap-icons.json';

// ── Direct-access protection ────────────────────────────────────────────────
// Block direct web access. Allow CLI execution for development builds.
if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

echo "Downloading Bootstrap Icons v" . $bi_version . "…\n";

// ── Download ──────────────────────────────────────────────────────────────────

$tmp = sys_get_temp_dir() . '/bootstrap-icons-' . $bi_version . '.zip';

if ( ! file_exists( $tmp ) ) {
	$ctx = stream_context_create( array(
		'http' => array(
			'method'          => 'GET',
			'follow_location' => 1,
			'timeout'         => 120,
			'user_agent'      => 'arshid6social-build/1.0',
			'header'          => "Accept: application/octet-stream\r\n",
		),
		'ssl' => array(
			'verify_peer'      => true,
			'verify_peer_name' => true,
		),
	) );

	$data = @file_get_contents( $zip_url, false, $ctx );
	if ( $data === false ) {
		fwrite( STDERR, "Error: could not download " . $zip_url . "\n" );
		exit( 1 );
	}
	file_put_contents( $tmp, $data );
	echo "  Downloaded to $tmp\n";
} else {
	echo "  Using cached $tmp\n";
}

// ── Extract SVGs ──────────────────────────────────────────────────────────────

echo "Extracting SVG icons…\n";

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "Error: PHP ZipArchive extension is required.\n" );
	exit( 1 );
}

$zip = new ZipArchive();
if ( $zip->open( $tmp ) !== true ) {
	fwrite( STDERR, "Error: could not open zip file.\n" );
	exit( 1 );
}

$prefix    = 'bootstrap-icons-' . $bi_version . '/';
$icons_map = array();

for ( $i = 0; $i < $zip->numFiles; $i++ ) {
	$entry = $zip->getNameIndex( $i );
	if ( ! str_starts_with( $entry, $prefix ) || ! str_ends_with( $entry, '.svg' ) ) {
		continue;
	}

	$name    = basename( $entry, '.svg' );
	$svg_raw = $zip->getFromIndex( $i );
	if ( ! $svg_raw ) {
		continue;
	}

	// Strip the outer <svg …> wrapper — keep only inner elements (path, circle, etc.)
	if ( preg_match( '/<svg[^>]*>\s*(.*?)\s*<\/svg>/s', $svg_raw, $m ) ) {
		$icons_map[ $name ] = trim( $m[1] );
	}
}

$zip->close();

if ( empty( $icons_map ) ) {
	fwrite( STDERR, "Error: no icons found in the zip.\n" );
	exit( 1 );
}

ksort( $icons_map );

$count = count( $icons_map );
echo "  Found $count icons.\n";

// ── Write output ──────────────────────────────────────────────────────────────

$out_dir = dirname( $out_file );
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0755, true );
}

$ok = file_put_contents( $out_file, json_encode( $icons_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
if ( $ok === false ) {
	fwrite( STDERR, "Error: could not write " . $out_file . "\n" );
	exit( 1 );
}

$kb = round( filesize( $out_file ) / 1024 );
echo "  Written to " . realpath( $out_file ) . " ({$kb} KB)\n";
echo "Done. Commit assets/icons/bootstrap-icons.json to your repository.\n";
