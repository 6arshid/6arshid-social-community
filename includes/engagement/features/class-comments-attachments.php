<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Comment Attachments feature.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Comments_Attachments {

	private static array $mime_map = array(
		'image'    => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ),
		'document' => array( 'application/pdf' ),
	);

	public function __construct() {
		add_action( 'wp_ajax_arshid6social_comment_upload_attachment', array( $this, 'ajax_upload' ) );
		add_action( 'wp_ajax_arshid6social_comment_delete_attachment', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_comment_attachment_url', array( $this, 'ajax_serve_attachment' ) );
		add_action( 'wp_ajax_arshid6social_comment_attachment_url',     array( $this, 'ajax_serve_attachment' ) );

		// Clean up when comment (activity) is deleted.
		add_action( 'arshid6social_activity_deleted', array( $this, 'delete_for_comment' ) );
	}

	// ── Upload ────────────────────────────────────────────────────────────────

	/**
	 * Shared upload logic: validates and saves an attachment for the given comment.
	 * Returns attachment data array on success, null on failure.
	 *
	 * @param int   $comment_id
	 * @param int   $user_id
	 * @param array $file  Entry from $_FILES.
	 */
	public function upload_for_comment( int $comment_id, int $user_id, array $file ): ?array {
		if ( empty( $file['tmp_name'] ) ) {
			return null;
		}

		$max_bytes = (int) get_option( 'arshid6social_eng_comment_att_max_mb', 5 ) * MB_IN_BYTES;
		if ( (int) $file['size'] > $max_bytes ) {
			return null;
		}

		$allowed_types = (array) get_option( 'arshid6social_eng_comment_att_types', array( 'image' ) );
		$allowed_mimes = array();
		foreach ( $allowed_types as $type ) {
			$allowed_mimes = array_merge( $allowed_mimes, self::$mime_map[ $type ] ?? array() );
		}

		$finfo     = new \finfo( FILEINFO_MIME_TYPE );
		$real_mime = $finfo->file( $file['tmp_name'] );
		if ( ! in_array( $real_mime, $allowed_mimes, true ) ) {
			return null;
		}

		$media_type = 'document';
		foreach ( self::$mime_map as $type => $mimes ) {
			if ( in_array( $real_mime, $mimes, true ) ) {
				$media_type = $type;
				break;
			}
		}

		if ( 'image' === $media_type ) {
			$this->strip_exif( $file['tmp_name'], $real_mime );
		}

		$subdir_filter = function( array $dir ) use ( $comment_id ): array {
			$dir['subdir'] = '/social-network/comments/' . $comment_id;
			$dir['path']   = $dir['basedir'] . $dir['subdir'];
			$dir['url']    = $dir['baseurl'] . $dir['subdir'];
			return $dir;
		};

		add_filter( 'upload_dir', $subdir_filter );
		$upload_dir = wp_upload_dir();
		wp_mkdir_p( $upload_dir['path'] );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upload_data = array(
			'name'     => wp_generate_uuid4() . '.' . pathinfo( $file['name'], PATHINFO_EXTENSION ),
			'type'     => $real_mime,
			'tmp_name' => $file['tmp_name'],
			'error'    => (int) $file['error'],
			'size'     => (int) $file['size'],
		);
		$moved = wp_handle_upload( $upload_data, array( 'test_form' => false ) );
		remove_filter( 'upload_dir', $subdir_filter );

		if ( ! isset( $moved['file'] ) || isset( $moved['error'] ) ) {
			return null;
		}

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'arshid6social_attachments',
			array(
				'parent_id'   => $comment_id,
				'parent_type' => 'comment',
				'file_url'    => $moved['url'],
				'file_path'   => $moved['file'],
				'file_name'   => sanitize_file_name( $file['name'] ),
				'file_size'   => (int) $file['size'],
				'mime_type'   => $real_mime,
				'media_type'  => $media_type,
				'uploader_id' => $user_id,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		$att_id    = (int) $wpdb->insert_id;
		$wp_att_id = \Arshid6Social\Media_Handler::register_to_media_library(
			$moved['file'],
			$moved['url'],
			$real_mime,
			sanitize_file_name( $file['name'] ),
			$user_id
		);
		if ( $wp_att_id && $att_id ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'arshid6social_attachments',
				array( 'wp_attachment_id' => $wp_att_id ),
				array( 'id' => $att_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		return array(
			'url'        => esc_url( $moved['url'] ),
			'file_name'  => sanitize_file_name( $file['name'] ),
			'media_type' => $media_type,
			'mime_type'  => $real_mime,
		);
	}

	public function ajax_upload(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		$user_id = get_current_user_id();

		if ( get_user_meta( $user_id, 'arshid6social_suspended', true ) ) {
			wp_send_json_error( null, 403 );
		}

		$comment_id = absint( $_POST['comment_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $comment_id ) {
			wp_send_json_error( array( 'message' => 'Invalid comment ID.' ), 400 );
		}

		global $wpdb;
		$comment = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id, item_id FROM {$wpdb->prefix}sn_activity WHERE id = %d AND type = 'activity_comment'",
			$comment_id
		) );
		if ( ! $comment || (int) $comment->user_id !== $user_id ) {
			wp_send_json_error( array( 'message' => 'Comment not found or access denied. user=' . $user_id . ' comment_user=' . ( $comment ? $comment->user_id : 'null' ) ), 403 );
		}

		if ( empty( $_FILES['attachment']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'No file uploaded.' ), 400 );
		}

		try {
			$file      = $_FILES['attachment']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$max_bytes = (int) get_option( 'arshid6social_eng_comment_att_max_mb', 5 ) * MB_IN_BYTES;

			if ( (int) $file['size'] > $max_bytes ) {
				wp_send_json_error( array( 'message' => 'File too large. size=' . $file['size'] . ' max=' . $max_bytes ), 413 );
			}

			$allowed_types = (array) get_option( 'arshid6social_eng_comment_att_types', array( 'image' ) );
			$allowed_mimes = array();
			foreach ( $allowed_types as $type ) {
				$allowed_mimes = array_merge( $allowed_mimes, self::$mime_map[ $type ] ?? array() );
			}

			// Detect real MIME type.
			if ( class_exists( '\finfo' ) ) {
				$finfo     = new \finfo( FILEINFO_MIME_TYPE );
				$real_mime = $finfo->file( $file['tmp_name'] );
			} elseif ( function_exists( 'mime_content_type' ) ) {
				$real_mime = mime_content_type( $file['tmp_name'] );
			} else {
				$real_mime = $file['type'];
			}

			if ( ! in_array( $real_mime, $allowed_mimes, true ) ) {
				wp_send_json_error( array( 'message' => 'File type not allowed. detected=' . $real_mime . ' allowed=' . implode( ',', $allowed_mimes ) ), 415 );
			}

			$media_type = 'document';
			foreach ( self::$mime_map as $type => $mimes ) {
				if ( in_array( $real_mime, $mimes, true ) ) {
					$media_type = $type;
					break;
				}
			}

			if ( 'image' === $media_type ) {
				$this->strip_exif( $file['tmp_name'], $real_mime );
			}

			$subdir_filter = function( array $dir ) use ( $comment_id ): array {
				$dir['subdir'] = '/social-network/comments/' . $comment_id;
				$dir['path']   = $dir['basedir'] . $dir['subdir'];
				$dir['url']    = $dir['baseurl'] . $dir['subdir'];
				return $dir;
			};

			add_filter( 'upload_dir', $subdir_filter );
			$upload_dir = wp_upload_dir();
			wp_mkdir_p( $upload_dir['path'] );

			require_once ABSPATH . 'wp-admin/includes/file.php';
			$upload_data = array(
				'name'     => wp_generate_uuid4() . '.' . pathinfo( $file['name'], PATHINFO_EXTENSION ),
				'type'     => $real_mime,
				'tmp_name' => $file['tmp_name'],
				'error'    => (int) $file['error'],
				'size'     => (int) $file['size'],
			);
			$moved = wp_handle_upload( $upload_data, array( 'test_form' => false ) );
			remove_filter( 'upload_dir', $subdir_filter );

			if ( ! isset( $moved['file'] ) || isset( $moved['error'] ) ) {
				wp_send_json_error( array( 'message' => 'wp_handle_upload failed: ' . ( $moved['error'] ?? 'no file in result' ) ), 500 );
			}

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'arshid6social_attachments',
				array(
					'parent_id'   => $comment_id,
					'parent_type' => 'comment',
					'file_url'    => $moved['url'],
					'file_path'   => $moved['file'],
					'file_name'   => sanitize_file_name( $file['name'] ),
					'file_size'   => (int) $file['size'],
					'mime_type'   => $real_mime,
					'media_type'  => $media_type,
					'uploader_id' => $user_id,
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
			);

			$att_id    = (int) $wpdb->insert_id;
			$wp_att_id = \Arshid6Social\Media_Handler::register_to_media_library(
				$moved['file'],
				$moved['url'],
				$real_mime,
				sanitize_file_name( $file['name'] ),
				$user_id
			);
			if ( $wp_att_id && $att_id ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prefix . 'arshid6social_attachments',
					array( 'wp_attachment_id' => $wp_att_id ),
					array( 'id' => $att_id ),
					array( '%d' ),
					array( '%d' )
				);
			}

			wp_send_json_success( array(
				'attachment_id' => $att_id,
				'url'           => esc_url( $moved['url'] ),
				'file_name'     => sanitize_file_name( $file['name'] ),
				'media_type'    => $media_type,
				'mime_type'     => $real_mime,
			) );

		} catch ( \Throwable $e ) {
			wp_send_json_error( array(
				'message' => 'PHP error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
			), 500 );
		}
	}

	public function ajax_delete(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		$att_id = absint( $_POST['attachment_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->delete_attachment( $att_id, get_current_user_id() )
			? wp_send_json_success()
			: wp_send_json_error( null, 403 );
	}

	public function ajax_serve_attachment(): void {
		$att_id = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $att_id ) {
			wp_send_json_error( null, 400 );
		}

		global $wpdb;
		$att = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT a.*, act.privacy, act.user_id AS post_owner
			FROM {$wpdb->prefix}arshid6social_attachments a
			JOIN {$wpdb->prefix}sn_activity act ON act.id = a.parent_id
			WHERE a.id = %d AND a.parent_type = 'comment'",
			$att_id
		) );

		if ( ! $att ) {
			wp_send_json_error( null, 404 );
		}

		// Access control: private post → only owner + admin can access attachment.
		if ( 'private' === $att->privacy ) {
			$current = get_current_user_id();
			if ( ! $current || ( (int) $att->post_owner !== $current && ! current_user_can( 'arshid6social_manage_activity' ) ) ) {
				wp_send_json_error( null, 403 );
			}
		}

		wp_redirect( esc_url_raw( $att->file_url ) );
		exit;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function strip_exif( string $path, string $mime ): void {
		if ( ! extension_loaded( 'gd' ) ) {
			return;
		}

		$img = match ( $mime ) {
			'image/jpeg' => imagecreatefromjpeg( $path ),
			'image/png'  => imagecreatefrompng( $path ),
			'image/webp' => imagecreatefromwebp( $path ),
			default      => null,
		};

		if ( ! $img ) {
			return;
		}

		match ( $mime ) {
			'image/jpeg' => imagejpeg( $img, $path, 85 ),
			'image/png'  => imagepng( $img, $path ),
			'image/webp' => imagewebp( $img, $path, 85 ),
		};

		imagedestroy( $img );
	}

	private function delete_attachment( int $att_id, int $user_id ): bool {
		global $wpdb;
		$att = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}arshid6social_attachments WHERE id = %d",
			$att_id
		) );

		if ( ! $att || ( (int) $att->uploader_id !== $user_id && ! current_user_can( 'arshid6social_manage_activity' ) ) ) {
			return false;
		}

		if ( ! empty( $att->wp_attachment_id ) ) {
			wp_delete_attachment( (int) $att->wp_attachment_id, true );
		} elseif ( ! empty( $att->file_path ) ) {
			\Arshid6Social\Media_Handler::delete_file( (string) $att->file_path );
		}

		$wpdb->delete( $wpdb->prefix . 'arshid6social_attachments', array( 'id' => $att_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return true;
	}

	public function delete_for_comment( int $comment_id ): void {
		global $wpdb;
		$atts = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, file_path, wp_attachment_id FROM {$wpdb->prefix}arshid6social_attachments WHERE parent_id = %d AND parent_type = 'comment'",
			$comment_id
		) );

		foreach ( $atts as $att ) {
			if ( ! empty( $att->wp_attachment_id ) ) {
				wp_delete_attachment( (int) $att->wp_attachment_id, true );
			} elseif ( ! empty( $att->file_path ) ) {
				\Arshid6Social\Media_Handler::delete_file( (string) $att->file_path );
			}
		}

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'arshid6social_attachments',
			array( 'parent_id' => $comment_id, 'parent_type' => 'comment' ),
			array( '%d', '%s' )
		);
	}

	public function get_for_comment( int $comment_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, file_url, file_name, file_size, mime_type, media_type FROM {$wpdb->prefix}arshid6social_attachments
			WHERE parent_id = %d AND parent_type = 'comment' ORDER BY created_at ASC",
			$comment_id
		), ARRAY_A ) ?: array();
	}

	// ── REST ──────────────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		( new Comments_Attachments_REST() )->register_routes();
	}
}
