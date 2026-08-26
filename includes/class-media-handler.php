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
		'story_image'      => array(
			'allowed_mime' => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ),
			'max_size_opt' => 'arshid6social_max_upload_size_mb',
			'subdir'       => 'social-network/stories',
			'public'       => true,
		),
		'story_video'      => array(
			'allowed_mime' => array( 'video/mp4', 'video/webm', 'video/ogg' ),
			'max_size_opt' => 'arshid6social_max_upload_size_mb',
			'subdir'       => 'social-network/stories',
			'public'       => true,
		),
		'verification_doc' => array(
			'allowed_mime' => array(
				'image/jpeg',
				'image/png',
				'image/webp',
				'application/pdf',
			),
			'max_size_opt' => 'arshid6social_max_upload_size_mb',
			'subdir'       => 'social-network/verification-docs',
			'public'       => false,
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
		if ( ! empty( $upload_dir['error'] ) ) {
			return new \WP_Error( 'upload_dir_error', __( 'Unable to determine upload directory.', '6arshid-social-community' ) );
		}

		// Public files stay in normal wp_upload_dir(); private files go to
		// wp_upload_dir()['basedir']/6arshid/private/ and must be encrypted.
		if ( $cfg['public'] ) {
			$base     = $upload_dir['basedir'];
			$dest_dir = $base . '/' . $cfg['subdir'] . '/' . $user_id;
		} else {
			$private_dir = arshid6social_get_private_dir();
			if ( is_wp_error( $private_dir ) ) {
				return $private_dir;
			}
			$dest_dir = $private_dir . $cfg['subdir'] . '/' . $user_id;
		}

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

		// Encrypt non-public files — mandatory, fail closed if unavailable.
		$stored_path = $dest;
		if ( ! $cfg['public'] ) {
			$enc_result = self::encrypt_private_file( $dest );
			if ( is_wp_error( $enc_result ) ) {
				// Clean up the plaintext file before returning error.
				if ( is_file( $dest ) ) {
					unlink( $dest ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
				return $enc_result;
			}
			$stored_path = $enc_result;
		}

		// Strip EXIF/GPS from images (skip encrypted files).
		if ( str_starts_with( $real_mime, 'image/' ) && ! str_ends_with( $stored_path, '.enc' ) ) {
			self::strip_exif( $stored_path, $real_mime );
		}

		// Private files have no public URL — they are served only through PHP.
		if ( $cfg['public'] ) {
			$base_url = $upload_dir['baseurl'] . '/' . $cfg['subdir'] . '/' . $user_id . '/' . $filename;
		} else {
			$base_url = '';
		}

		return array(
			'url'  => $base_url,
			'path' => $stored_path,
			'mime' => $real_mime,
		);
	}

	/**
	 * Encrypts a private file and replaces it with the encrypted blob.
	 *
	 * @param string $path Absolute path to the plaintext file.
	 * @return string|\WP_Error Path to the encrypted file on success, or WP_Error.
	 */
	public static function encrypt_private_file_for_handler( string $path ) {
		return self::encrypt_private_file( $path );
	}

	/**
	 * Encrypts a private file (internal implementation).
	 *
	 * @param string $path Absolute path to the plaintext file.
	 * @return string|\WP_Error Path to the encrypted file on success, or WP_Error.
	 */
	private static function encrypt_private_file( string $path ) {
		if ( ! Private_Encryption::is_available() ) {
			return new \WP_Error(
				'arshid6social_encryption_unavailable',
				__( 'Private file encryption is required but no authenticated encryption backend is available.', '6arshid-social-community' )
			);
		}

		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return new \WP_Error(
				'arshid6social_source_unreadable',
				__( 'The source file could not be read for encryption.', '6arshid-social-community' )
			);
		}

		$plain   = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$envelope = Private_Encryption::encrypt( $plain );
		Private_Encryption::safe_sodium_memzero( $plain );

		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}

		$enc_dest = $path . '.enc';
		$bytes    = file_put_contents( $enc_dest, $envelope ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		Private_Encryption::safe_sodium_memzero( $envelope );

		if ( false === $bytes ) {
			return new \WP_Error(
				'arshid6social_enc_write_failed',
				__( 'Failed to write encrypted file.', '6arshid-social-community' )
			);
		}

		// Remove the original plaintext.
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		return $enc_dest;
	}

	/**
	 * Deletes an uploaded file from disk and removes its WP media library entry if one exists.
	 *
	 * @param string $path Absolute file path.
	 */
	public static function delete_file( string $path ): void {
		$private_dir = arshid6social_get_private_dir();
		$old_private = function_exists( 'arshid6social_get_legacy_private_dir' ) ? arshid6social_get_legacy_private_dir() : '';
		$is_social   = str_contains( $path, 'social-network' );
		$is_priv     = is_string( $private_dir ) && str_starts_with( $path, $private_dir );
		$is_old_priv = '' !== $old_private && str_starts_with( $path, $old_private );
		if ( ! $path || ( ! $is_social && ! $is_priv && ! $is_old_priv ) ) {
			return;
		}

		// Resolve .enc files: stored path may lack .enc extension.
		$resolved = $path;
		if ( ! file_exists( $resolved ) && ! str_ends_with( $resolved, '.enc' ) ) {
			$enc_candidate = $resolved . '.enc';
			if ( file_exists( $enc_candidate ) ) {
				$resolved = $enc_candidate;
			}
		}

		// Try to find and delete via WP media library (handles thumbnails + post cleanup).
		$upload_dir = wp_upload_dir();
		$rel_path   = ltrim( str_replace( $upload_dir['basedir'], '', $resolved ), '/\\' );

		global $wpdb;
		$attach_id = (int) $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
				$rel_path
			)
		);

		if ( $attach_id ) {
			wp_delete_attachment( $attach_id, true );
		} elseif ( file_exists( $resolved ) ) {
			wp_delete_file( $resolved );
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
		$private_dir = arshid6social_get_private_dir();
		$old_private = function_exists( 'arshid6social_get_legacy_private_dir' ) ? arshid6social_get_legacy_private_dir() : '';
		$is_legacy   = str_contains( $path, 'social-network/verification-docs' );
		$is_private  = is_string( $private_dir ) && str_starts_with( $path, $private_dir ) && str_contains( $path, 'verification-docs' );
		$is_old_priv = '' !== $old_private && str_starts_with( $path, $old_private ) && str_contains( $path, 'verification-docs' );

		// Resolve encrypted files: stored path may lack .enc extension.
		$resolved_path = $path;
		if ( ! file_exists( $resolved_path ) && ! str_ends_with( $resolved_path, '.enc' ) ) {
			$enc_candidate = $resolved_path . '.enc';
			if ( file_exists( $enc_candidate ) ) {
				$resolved_path = $enc_candidate;
			}
		}

		if ( ! file_exists( $resolved_path ) || ( ! $is_legacy && ! $is_private && ! $is_old_priv ) ) {
			status_header( 404 );
			exit;
		}

		if ( ! current_user_can( 'arshid6social_manage_members' ) ) {
			status_header( 403 );
			exit;
		}

		// Decrypt encrypted files before serving.
		if ( Private_Encryption::is_encrypted( $resolved_path ) ) {
			$size = filesize( $resolved_path );
			if ( false === $size ) {
				status_header( 404 );
				exit;
			}
			status_header( 200 );
			header( 'Content-Type: ' . $mime_type );
			header( 'Content-Length: ' . $size );
			header( 'Content-Disposition: inline; filename="' . esc_attr( basename( $path ) ) . '"' );
			header( 'X-Content-Type-Options: nosniff' );
			if ( ! Private_Encryption::decrypt_file_to_output( $resolved_path ) ) {
				status_header( 404 );
				exit;
			}
			exit;
		}

		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . filesize( $resolved_path ) );
		header( 'Content-Disposition: inline; filename="' . esc_attr( basename( $path ) ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $resolved_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
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

		if ( ! is_file( $path ) ) {
			return;
		}

		$img = null;
		switch ( $mime ) {
			case 'image/jpeg':
				$img = imagecreatefromjpeg( $path );
				if ( $img ) {
					imagejpeg( $img, $path, 90 );
				}
				break;
			case 'image/png':
				$img = imagecreatefrompng( $path );
				if ( $img ) {
					imagesavealpha( $img, true );
					imagepng( $img, $path, 6 );
				}
				break;
			case 'image/webp':
				$img = imagecreatefromwebp( $path );
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

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

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
			'image/jpeg'      => 'jpg',
			'image/png'       => 'png',
			'image/gif'       => 'gif',
			'image/webp'      => 'webp',
			'video/mp4'       => 'mp4',
			'video/webm'      => 'webm',
			'video/ogg'       => 'ogv',
			'application/pdf' => 'pdf',
		);
		return $map[ $mime ] ?? 'bin';
	}
}
