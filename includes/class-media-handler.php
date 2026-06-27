<?php
namespace Arshid6Social;

/**
 * Shared media upload handler.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Media_Handler
 *
 * Handles file uploads for stories and verification documents.
 * Each context uses a different sub-directory and MIME whitelist.
 */
class Media_Handler {

	/** Contexts and their allowed MIME types. */
	const CONTEXTS = array(
		'story_image' => array(
			'allowed_mime'  => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ),
			'max_size_opt'  => 'arshid6social_max_upload_size_mb',
			'subdir'        => 'social-network/stories',
			'public'        => true,
		),
		'story_video' => array(
			'allowed_mime'  => array( 'video/mp4', 'video/webm', 'video/ogg' ),
			'max_size_opt'  => 'arshid6social_max_upload_size_mb',
			'subdir'        => 'social-network/stories',
			'public'        => true,
		),
		'verification_doc' => array(
			'allowed_mime'  => array(
				'image/jpeg', 'image/png', 'image/webp',
				'application/pdf',
			),
			'max_size_opt'  => 'arshid6social_max_upload_size_mb',
			'subdir'        => 'social-network/verification-docs',
			'public'        => false,
		),
	);

	/**
	 * Handles a single file upload for the given context.
	 *
	 * @param array  $file    Entry from $_FILES.
	 * @param string $context One of the CONTEXTS keys.
	 * @param int    $user_id Owner user ID (used for sub-directory).
	 * @return array{url: string, path: string, mime: string}|WP_Error
	 */
	public static function handle( array $file, string $context, int $user_id ): array|\WP_Error {
		if ( ! isset( self::CONTEXTS[ $context ] ) ) {
			return new \WP_Error( 'invalid_context', __( 'Invalid upload context.', '6arshid-social-community' ) );
		}

		$cfg = self::CONTEXTS[ $context ];

		// Size check.
		$max_bytes = (int) get_option( $cfg['max_size_opt'], 5 ) * MB_IN_BYTES;
		if ( $file['size'] > $max_bytes ) {
			return new \WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: max size in MB */
					__( 'File exceeds the maximum size of %s MB.', '6arshid-social-community' ),
					(int) get_option( $cfg['max_size_opt'], 5 )
				)
			);
		}

		// MIME check via fileinfo (not trust client MIME).
		$finfo     = new \finfo( FILEINFO_MIME_TYPE );
		$real_mime = $finfo->file( $file['tmp_name'] );
		if ( ! in_array( $real_mime, $cfg['allowed_mime'], true ) ) {
			return new \WP_Error( 'invalid_mime', __( 'File type not allowed.', '6arshid-social-community' ) );
		}

		// Build destination directory.
		$upload_dir = wp_upload_dir();
		$base       = $upload_dir['basedir'];
		$dest_dir   = $base . '/' . $cfg['subdir'] . '/' . $user_id;

		if ( ! wp_mkdir_p( $dest_dir ) ) {
			return new \WP_Error( 'mkdir_failed', __( 'Could not create upload directory.', '6arshid-social-community' ) );
		}

		// Protect non-public directories with .htaccess.
		if ( ! $cfg['public'] ) {
			self::protect_directory( $dest_dir );
		}

		// Randomised filename to prevent enumeration.
		$ext      = self::mime_to_ext( $real_mime );
		$filename = wp_generate_uuid4() . '.' . $ext;
		$dest     = $dest_dir . '/' . $filename;

		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'move_failed', __( 'Failed to save uploaded file.', '6arshid-social-community' ) );
		}
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		if ( ! $wp_filesystem || ! $wp_filesystem->move( $file['tmp_name'], $dest, true ) ) {
			return new \WP_Error( 'move_failed', __( 'Failed to save uploaded file.', '6arshid-social-community' ) );
		}

		// Strip EXIF/GPS from images.
		if ( str_starts_with( $real_mime, 'image/' ) ) {
			self::strip_exif( $dest, $real_mime );
		}

		$base_url = $upload_dir['baseurl'] . '/' . $cfg['subdir'] . '/' . $user_id . '/' . $filename;

		return array(
			'url'  => $cfg['public'] ? $base_url : '',
			'path' => $dest,
			'mime' => $real_mime,
		);
	}

	/**
	 * Deletes an uploaded file from disk and removes its WP media library entry if one exists.
	 *
	 * @param string $path Absolute file path.
	 */
	public static function delete_file( string $path ): void {
		if ( ! $path || ! str_contains( $path, 'social-network' ) ) {
			return;
		}

		// Try to find and delete via WP media library (handles thumbnails + post cleanup).
		$upload_dir = wp_upload_dir();
		$rel_path   = ltrim( str_replace( $upload_dir['basedir'], '', $path ), '/\\' );

		global $wpdb;
		$attach_id = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
			$rel_path
		) );

		if ( $attach_id ) {
			wp_delete_attachment( $attach_id, true );
		} elseif ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Serves a protected (non-public) file after ownership/admin check.
	 * Call this from a REST endpoint or custom handler — never serve raw path.
	 *
	 * @param string $path      Absolute file path.
	 * @param string $mime_type File MIME type.
	 */
	public static function serve_protected_file( string $path, string $mime_type ): void {
		if ( ! file_exists( $path ) || ! str_contains( $path, 'social-network/verification-docs' ) ) {
			status_header( 404 );
			exit;
		}

		if ( ! current_user_can( 'arshid6social_manage_members' ) ) {
			status_header( 403 );
			exit;
		}

		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . esc_attr( basename( $path ) ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function protect_directory( string $dir ): void {
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$htaccess,
				"Options -Indexes\nOrder Allow,Deny\nDeny from all\n"
			);
		}
	}

	private static function strip_exif( string $path, string $mime ): void {
		if ( ! function_exists( 'imagecreatefromjpeg' ) ) {
			return;
		}

		$img = null;
		switch ( $mime ) {
			case 'image/jpeg':
				$img = @imagecreatefromjpeg( $path );
				if ( $img ) {
					imagejpeg( $img, $path, 90 );
				}
				break;
			case 'image/png':
				$img = @imagecreatefrompng( $path );
				if ( $img ) {
					imagesavealpha( $img, true );
					imagepng( $img, $path, 6 );
				}
				break;
			case 'image/webp':
				$img = @imagecreatefromwebp( $path );
				if ( $img ) {
					imagewebp( $img, $path, 85 );
				}
				break;
		}

		if ( $img ) {
			imagedestroy( $img );
		}
	}

	/**
	 * Registers an uploaded file into the WordPress Media Library.
	 *
	 * @param string $file_path Absolute path to the uploaded file.
	 * @param string $file_url  Public URL of the file (may be empty for protected files).
	 * @param string $mime_type MIME type.
	 * @param string $title     Attachment title.
	 * @param int    $user_id   Author user ID.
	 * @return int Attachment post ID, or 0 on failure.
	 */
	public static function register_to_media_library( string $file_path, string $file_url, string $mime_type, string $title, int $user_id ): int {
		if ( ! file_exists( $file_path ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment = array(
			'guid'           => $file_url ?: $file_path,
			'post_mime_type' => $mime_type,
			'post_title'     => sanitize_text_field( $title ?: basename( $file_path ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_author'    => $user_id,
		);

		$attach_id = wp_insert_attachment( $attachment, $file_path );

		if ( ! is_wp_error( $attach_id ) && $attach_id > 0 && str_starts_with( $mime_type, 'image/' ) ) {
			$metadata = wp_generate_attachment_metadata( $attach_id, $file_path );
			wp_update_attachment_metadata( $attach_id, $metadata );
		}

		return is_wp_error( $attach_id ) ? 0 : (int) $attach_id;
	}

	private static function mime_to_ext( string $mime ): string {
		$map = array(
			'image/jpeg'       => 'jpg',
			'image/png'        => 'png',
			'image/gif'        => 'gif',
			'image/webp'       => 'webp',
			'video/mp4'        => 'mp4',
			'video/webm'       => 'webm',
			'video/ogg'        => 'ogv',
			'application/pdf'  => 'pdf',
		);
		return $map[ $mime ] ?? 'bin';
	}
}
