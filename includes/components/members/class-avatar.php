<?php
namespace Arshid6Social\Components\Members;

/**
 * Avatar and cover photo upload handler.
 *
 * @package Arshid6Social\Components\Members
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Avatar
 *
 * Handles avatar and cover photo uploads: MIME validation, re-encoding,
 * EXIF stripping, size limits, and randomised filenames.
 */
class Avatar {

	/** @var string[] Allowed MIME types for uploads. */
	private array $allowed_mime_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

	public function __construct() {
		add_action( 'wp_ajax_arshid6social_upload_avatar', array( $this, 'ajax_upload_avatar' ) );
		add_action( 'wp_ajax_arshid6social_upload_cover', array( $this, 'ajax_upload_cover' ) );
		add_action( 'wp_ajax_arshid6social_delete_avatar', array( $this, 'ajax_delete_avatar' ) );
		add_action( 'wp_ajax_arshid6social_delete_cover', array( $this, 'ajax_delete_cover' ) );
	}

	/**
	 * Returns the avatar URL for a user.
	 *
	 * Falls back to Gravatar if no custom avatar is set.
	 *
	 * @param int $user_id User ID.
	 * @param int $size    Requested size in pixels.
	 * @return string Escaped URL.
	 */
	public function get_avatar_url( int $user_id, int $size = 150 ): string {
		$custom = get_user_meta( $user_id, 'arshid6social_avatar_url', true );
		if ( $custom ) {
			return esc_url( $custom );
		}

		$user = get_userdata( $user_id );
		return esc_url( get_avatar_url( $user ? $user->user_email : $user_id, array( 'size' => $size, 'default' => 'mm' ) ) );
	}

	/**
	 * Returns the cover photo URL for a user, or an empty string if none set.
	 *
	 * @param int $user_id User ID.
	 * @return string Escaped URL.
	 */
	public function get_cover_url( int $user_id ): string {
		$url = get_user_meta( $user_id, 'arshid6social_cover_url', true );
		return $url ? esc_url( $url ) : '';
	}

	/**
	 * AJAX: Handles avatar upload.
	 */
	public function ajax_upload_avatar(): void {
		if ( ! check_ajax_referer( 'arshid6social_upload_avatar', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid-social-community' ) ), 401 );
		}

		$user_id = get_current_user_id();
		$result  = $this->process_upload( $_FILES['avatar'] ?? null, 'avatar', $user_id ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}

		// Store URL; delete old file.
		$old = get_user_meta( $user_id, 'arshid6social_avatar_path', true );
		if ( $old && file_exists( $old ) ) {
			wp_delete_file( $old );
		}

		update_user_meta( $user_id, 'arshid6social_avatar_url', $result['url'] );
		update_user_meta( $user_id, 'arshid6social_avatar_path', $result['file'] );

		\Arshid6Social\Media_Handler::register_to_media_library(
			$result['file'],
			$result['url'],
			$result['mime'] ?? 'image/jpeg',
			'avatar-user-' . $user_id,
			$user_id
		);

		do_action( 'arshid6social_avatar_updated', $user_id, $result['url'] );

		wp_send_json_success( array( 'url' => esc_url( $result['url'] ) ) );
	}

	/**
	 * AJAX: Handles cover photo upload.
	 */
	public function ajax_upload_cover(): void {
		if ( ! check_ajax_referer( 'arshid6social_upload_cover', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid-social-community' ) ), 401 );
		}

		$user_id = get_current_user_id();
		$result  = $this->process_upload( $_FILES['cover'] ?? null, 'cover', $user_id ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}

		$old = get_user_meta( $user_id, 'arshid6social_cover_path', true );
		if ( $old && file_exists( $old ) ) {
			wp_delete_file( $old );
		}

		update_user_meta( $user_id, 'arshid6social_cover_url', $result['url'] );
		update_user_meta( $user_id, 'arshid6social_cover_path', $result['file'] );

		\Arshid6Social\Media_Handler::register_to_media_library(
			$result['file'],
			$result['url'],
			$result['mime'] ?? 'image/jpeg',
			'cover-user-' . $user_id,
			$user_id
		);

		do_action( 'arshid6social_cover_updated', $user_id, $result['url'] );

		wp_send_json_success( array( 'url' => esc_url( $result['url'] ) ) );
	}

	/**
	 * AJAX: Deletes the current user's avatar.
	 */
	public function ajax_delete_avatar(): void {
		if ( ! check_ajax_referer( 'arshid6social_delete_avatar', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community' ) ), 403 );
		}

		$user_id = get_current_user_id();
		$path    = get_user_meta( $user_id, 'arshid6social_avatar_path', true );

		if ( $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}

		delete_user_meta( $user_id, 'arshid6social_avatar_url' );
		delete_user_meta( $user_id, 'arshid6social_avatar_path' );

		wp_send_json_success( array( 'url' => esc_url( get_avatar_url( $user_id ) ) ) );
	}

	/**
	 * AJAX: Deletes the current user's cover photo.
	 */
	public function ajax_delete_cover(): void {
		if ( ! check_ajax_referer( 'arshid6social_delete_cover', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community' ) ), 403 );
		}

		$user_id = get_current_user_id();
		$path    = get_user_meta( $user_id, 'arshid6social_cover_path', true );

		if ( $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}

		delete_user_meta( $user_id, 'arshid6social_cover_url' );
		delete_user_meta( $user_id, 'arshid6social_cover_path' );

		wp_send_json_success( array( 'deleted' => true ) );
	}

	/**
	 * Processes an uploaded image: validates MIME type, re-encodes to strip EXIF,
	 * resizes, and saves with a random filename.
	 *
	 * @param array<string,mixed>|null $file       $_FILES entry.
	 * @param string                   $image_type 'avatar' or 'cover'.
	 * @param int                      $user_id    Owner user ID.
	 * @return array<string, string>|\WP_Error  On success: ['file' => path, 'url' => url].
	 */
	private function process_upload( ?array $file, string $image_type, int $user_id ): array|\WP_Error {
		if ( ! $file || isset( $file['error'] ) && UPLOAD_ERR_OK !== $file['error'] ) {
			return new \WP_Error( 'upload_error', __( 'Upload failed. Please try again.', '6arshid-social-community' ) );
		}

		// ── Size check ──────────────────────────────────────────────────────────
		$max_bytes = (int) get_option( 'arshid6social_max_upload_size_mb', 5 ) * MB_IN_BYTES;
		if ( $file['size'] > $max_bytes ) {
			return new \WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: max file size in MB */
					__( 'File exceeds the maximum allowed size of %s MB.', '6arshid-social-community' ),
					get_option( 'arshid6social_max_upload_size_mb', 5 )
				)
			);
		}

		// ── MIME type check (whitelist) ──────────────────────────────────────────
		$allowed = (array) get_option( 'arshid6social_allowed_upload_types', $this->allowed_mime_types );
		$finfo   = finfo_open( FILEINFO_MIME_TYPE );
		$mime    = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime, $allowed, true ) ) {
			return new \WP_Error( 'invalid_mime', __( 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.', '6arshid-social-community' ) );
		}

		// ── Re-encode via WP image editor (strips EXIF, embedded payloads) ──────
		$editor = wp_get_image_editor( $file['tmp_name'] );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		if ( 'avatar' === $image_type ) {
			$size = (int) get_option( 'arshid6social_profile_photo_size', 150 );
			$editor->resize( $size, $size, true );
		} else {
			$w = (int) get_option( 'arshid6social_cover_photo_width', 1200 );
			$h = (int) get_option( 'arshid6social_cover_photo_height', 350 );
			$editor->resize( $w, $h, true );
		}

		// ── Generate randomised filename ─────────────────────────────────────────
		$upload_dir = wp_upload_dir();
		$sub_dir    = '/social-network/users/' . $user_id . '/' . $image_type . '/';
		$dir_path   = $upload_dir['basedir'] . $sub_dir;
		$dir_url    = $upload_dir['baseurl'] . $sub_dir;

		wp_mkdir_p( $dir_path );

		$extension = $this->mime_to_ext( $mime );
		$filename  = wp_generate_uuid4() . '.' . $extension;
		$full_path = $dir_path . $filename;

		$saved = $editor->save( $full_path );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return array(
			'file' => $full_path,
			'url'  => $dir_url . $filename,
			'mime' => $mime,
		);
	}

	/**
	 * Maps a MIME type to a safe file extension.
	 *
	 * @param string $mime MIME type.
	 * @return string File extension without leading dot.
	 */
	private function mime_to_ext( string $mime ): string {
		return match ( $mime ) {
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
			default      => 'jpg',
		};
	}
}
