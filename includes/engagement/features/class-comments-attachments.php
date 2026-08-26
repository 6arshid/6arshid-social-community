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
		add_action( 'wp_ajax_arshid6social_comment_attachment_url', array( $this, 'ajax_serve_attachment' ) );

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

		$private_dir = arshid6social_get_private_dir();
		if ( is_wp_error( $private_dir ) ) {
			return null;
		}
		$dest_dir = $private_dir . 'comments/' . $comment_id;

		if ( ! wp_mkdir_p( $dest_dir ) ) {
			return null;
		}

		$filename = wp_generate_uuid4() . '.' . pathinfo( $file['name'], PATHINFO_EXTENSION );
		$dest     = $dest_dir . '/' . $filename;

		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return null;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		if ( ! $wp_filesystem || ! $wp_filesystem->move( $file['tmp_name'], $dest, true ) ) {
			return null;
		}

		// Encrypt the file at rest — mandatory, fail closed if unavailable.
		$enc_result = \Arshid6Social\Media_Handler::encrypt_private_file_for_handler( $dest );
		if ( is_wp_error( $enc_result ) ) {
			if ( is_file( $dest ) ) {
				unlink( $dest ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			return null;
		}
		$dest = $enc_result;

		$moved = array(
			'file' => $dest,
			'url'  => '',
		);

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
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		if ( ! is_user_logged_in() ) {
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
		$comment = $wpdb->get_row(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT user_id, item_id FROM {$wpdb->prefix}arshid6social_activity WHERE id = %d AND type = 'activity_comment'",
				$comment_id
			)
		);
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

			$private_dir = arshid6social_get_private_dir();
			if ( is_wp_error( $private_dir ) ) {
				wp_send_json_error( array( 'message' => $private_dir->get_error_message() ), 500 );
			}
			$dest_dir = $private_dir . 'comments/' . $comment_id;

			if ( ! wp_mkdir_p( $dest_dir ) ) {
				wp_send_json_error( array( 'message' => __( 'Unable to create upload directory.', '6arshid-social-community' ) ), 500 );
			}

			$filename = wp_generate_uuid4() . '.' . pathinfo( $file['name'], PATHINFO_EXTENSION );
			$dest     = $dest_dir . '/' . $filename;

			if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
				wp_send_json_error( array( 'message' => 'Upload failed.' ), 500 );
			}

			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			if ( ! $wp_filesystem || ! $wp_filesystem->move( $file['tmp_name'], $dest, true ) ) {
				wp_send_json_error( array( 'message' => 'Upload failed.' ), 500 );
			}

			// Encrypt the file at rest — mandatory, fail closed if unavailable.
			$enc_result = \Arshid6Social\Media_Handler::encrypt_private_file_for_handler( $dest );
			if ( is_wp_error( $enc_result ) ) {
				if ( is_file( $dest ) ) {
					unlink( $dest ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
				wp_send_json_error( array( 'message' => $enc_result->get_error_message() ), 500 );
			}
			$dest = $enc_result;

			$moved = array(
				'file' => $dest,
				'url'  => '',
			);

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

			wp_send_json_success(
				array(
					'attachment_id' => $att_id,
					'url'           => esc_url( $moved['url'] ),
					'file_name'     => sanitize_file_name( $file['name'] ),
					'media_type'    => $media_type,
					'mime_type'     => $real_mime,
				)
			);

		} catch ( \Throwable $e ) {
			wp_send_json_error(
				array(
					'message' => 'PHP error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
				),
				500
			);
		}
	}

	public function ajax_delete(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		if ( ! is_user_logged_in() ) {
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
		$att = $wpdb->get_row(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT a.*, act.privacy, act.user_id AS post_owner
			FROM {$wpdb->prefix}arshid6social_attachments a
			JOIN {$wpdb->prefix}arshid6social_activity act ON act.id = a.parent_id
			WHERE a.id = %d AND a.parent_type = 'comment'",
				$att_id
			)
		);

		if ( ! $att ) {
			wp_send_json_error( null, 404 );
		}

		// Access control: the viewer must be allowed to see the parent activity
		// (covers private, friends-only, and any other privacy level).
		if ( ! arshid6social_current_user_can_view_activity( (int) $att->parent_id ) ) {
			wp_send_json_error( null, 403 );
		}

		$file_path = (string) $att->file_path;

		// Resolve encrypted files: stored path may lack .enc extension.
		if ( ! file_exists( $file_path ) && ! str_ends_with( $file_path, '.enc' ) ) {
			$enc_candidate = $file_path . '.enc';
			if ( file_exists( $enc_candidate ) ) {
				$file_path = $enc_candidate;
			}
		}

		if ( ! file_exists( $file_path ) || ! is_file( $file_path ) ) {
			status_header( 404 );
			exit;
		}

		$mime = (string) $att->mime_type;

		// Decrypt encrypted files before serving.
		if ( \Arshid6Social\Private_Encryption::is_encrypted( $file_path ) ) {
			$size = filesize( $file_path );
			if ( false === $size ) {
				status_header( 404 );
				exit;
			}
			status_header( 200 );
			header( 'Content-Type: ' . $mime );
			header( 'Content-Length: ' . $size );
			header( 'Content-Disposition: inline; filename="' . esc_attr( $att->file_name ) . '"' );
			header( 'X-Content-Type-Options: nosniff' );
			if ( ! \Arshid6Social\Private_Encryption::decrypt_file_to_output( $file_path ) ) {
				status_header( 404 );
				exit;
			}
			exit;
		}

		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Content-Disposition: inline; filename="' . esc_attr( $att->file_name ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
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
		$att = $wpdb->get_row(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT * FROM {$wpdb->prefix}arshid6social_attachments WHERE id = %d",
				$att_id
			)
		);

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
		$atts = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT id, file_path, wp_attachment_id FROM {$wpdb->prefix}arshid6social_attachments WHERE parent_id = %d AND parent_type = 'comment'",
				$comment_id
			)
		);

		foreach ( $atts as $att ) {
			if ( ! empty( $att->wp_attachment_id ) ) {
				wp_delete_attachment( (int) $att->wp_attachment_id, true );
			} elseif ( ! empty( $att->file_path ) ) {
				\Arshid6Social\Media_Handler::delete_file( (string) $att->file_path );
			}
		}

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'arshid6social_attachments',
			array(
				'parent_id'   => $comment_id,
				'parent_type' => 'comment',
			),
			array( '%d', '%s' )
		);
	}

	public function get_for_comment( int $comment_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT id, file_url, file_name, file_size, mime_type, media_type FROM {$wpdb->prefix}arshid6social_attachments
			WHERE parent_id = %d AND parent_type = 'comment' ORDER BY created_at ASC",
				$comment_id
			),
			ARRAY_A
		) ?: array();
	}

	// ── REST ──────────────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		( new Comments_Attachments_REST() )->register_routes();
	}
}
