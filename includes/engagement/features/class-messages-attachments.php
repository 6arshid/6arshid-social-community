<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Message Attachments feature.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Messages_Attachments {

	private static array $mime_map = array(
		'image'    => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ),
		'audio'    => array( 'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/mp4' ),
		'document' => array( 'application/pdf' ),
	);

	public function __construct() {
		add_action( 'wp_ajax_arshid6social_message_upload_attachment', array( $this, 'ajax_upload' ) );
		add_action( 'wp_ajax_arshid6social_message_delete_attachment', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_arshid6social_message_attachment_serve',  array( $this, 'ajax_serve' ) );

		// Clean up when a thread is deleted.
		add_action( 'arshid6social_thread_deleted', array( $this, 'delete_for_thread' ) );
	}

	// ── Upload ────────────────────────────────────────────────────────────────

	public function ajax_upload(): void {
		try {
			$this->do_upload();
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				array( 'message' => 'PHP Error: ' . $e->getMessage() . ' in ' . basename( $e->getFile() ) . ':' . $e->getLine() ),
				500
			);
		}
	}

	private function do_upload(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, 'arshid6social_suspended', true ) ) {
			wp_send_json_error( null, 403 );
		}

		$message_id = absint( $_POST['message_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $message_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid message ID.', '6arshid-social-community' ) ), 400 );
		}

		// Verify caller is a participant in the message's thread.
		global $wpdb;
		$is_participant = (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_messages m
			JOIN {$wpdb->prefix}sn_messages_recipients r ON r.thread_id = m.thread_id
			WHERE m.id = %d AND r.user_id = %d AND r.is_deleted = 0",
			$message_id, $user_id
		) );

		if ( ! $is_participant ) {
			wp_send_json_error( null, 403 );
		}

		if ( empty( $_FILES['attachment']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', '6arshid-social-community' ) ), 400 );
		}

		$file      = $_FILES['attachment']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$max_bytes = (int) get_option( 'arshid6social_eng_msg_att_max_mb', 10 ) * MB_IN_BYTES;

		if ( (int) $file['size'] > $max_bytes ) {
			wp_send_json_error( array( 'message' => __( 'File too large.', '6arshid-social-community' ) ), 413 );
		}

		$allowed_types = (array) get_option( 'arshid6social_eng_msg_att_types', array( 'image', 'audio' ) );
		$allowed_mimes = array();
		foreach ( $allowed_types as $type ) {
			$allowed_mimes = array_merge( $allowed_mimes, self::$mime_map[ $type ] ?? array() );
		}

		$finfo     = new \finfo( FILEINFO_MIME_TYPE );
		$real_mime = $finfo->file( $file['tmp_name'] );

		if ( ! in_array( $real_mime, $allowed_mimes, true ) ) {
			wp_send_json_error( array( 'message' => __( 'File type not allowed.', '6arshid-social-community' ) ), 415 );
		}

		$media_type = 'document';
		foreach ( self::$mime_map as $type => $mimes ) {
			if ( in_array( $real_mime, $mimes, true ) ) {
				$media_type = $type;
				break;
			}
		}

		// Re-encode images to strip EXIF/GPS.
		if ( 'image' === $media_type ) {
			$this->strip_exif( $file['tmp_name'], $real_mime );
		}

		// Store in protected, non-guessable path.
		$uuid          = wp_generate_uuid4();
		$ext           = pathinfo( (string) $file['name'], PATHINFO_EXTENSION );
		$safe_filename = $uuid . '.' . $ext;

		$subdir_filter = function( array $dir ) use ( $user_id ): array {
			$dir['subdir'] = '/social-network/messages/' . $user_id;
			$dir['path']   = $dir['basedir'] . $dir['subdir'];
			$dir['url']    = $dir['baseurl'] . $dir['subdir'];
			return $dir;
		};

		add_filter( 'upload_dir', $subdir_filter );
		$upload_dir = wp_upload_dir();
		wp_mkdir_p( $upload_dir['path'] );

		// Protect the directory from direct browsing.
		$htaccess = $upload_dir['path'] . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Options -Indexes\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upload_file = array(
			'name'     => $safe_filename,
			'type'     => $real_mime,
			'tmp_name' => $file['tmp_name'],
			'error'    => (int) $file['error'],
			'size'     => (int) $file['size'],
		);
		$moved = wp_handle_upload( $upload_file, array( 'test_form' => false ) );
		remove_filter( 'upload_dir', $subdir_filter );

		if ( ! isset( $moved['file'] ) || isset( $moved['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Upload failed.', '6arshid-social-community' ) ), 500 );
		}

		// Store with a signed serve URL (not the real path) for IDOR protection.
		$att_token = wp_generate_uuid4();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'arshid6social_attachments',
			array(
				'parent_id'   => $message_id,
				'parent_type' => 'message',
				'file_url'    => $att_token,   // Store token, not public URL.
				'file_path'   => $moved['file'],
				'file_name'   => sanitize_file_name( (string) $file['name'] ),
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
			sanitize_file_name( (string) $file['name'] ),
			$user_id
		);
		if ( $wp_att_id ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'arshid6social_attachments',
				array( 'wp_attachment_id' => $wp_att_id ),
				array( 'id' => $att_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		// The serve URL goes through our AJAX handler for access control.
		$serve_url = add_query_arg( array(
			'action'  => 'arshid6social_message_attachment_serve',
			'id'      => $att_id,
			'token'   => $att_token,
			'nonce'   => wp_create_nonce( 'arshid6social_att_' . $att_id ),
		), admin_url( 'admin-ajax.php' ) );

		wp_send_json_success( array(
			'attachment_id' => $att_id,
			'serve_url'     => esc_url( $serve_url ),
			'file_name'     => sanitize_file_name( (string) $file['name'] ),
			'media_type'    => $media_type,
			'mime_type'     => $real_mime,
			'file_size'     => (int) $file['size'],
		) );
	}

	public function ajax_serve(): void {
		// phpcs:disable WordPress.Security.NonceVerification
		$att_id = absint( $_GET['id'] ?? 0 );
		$token  = sanitize_key( $_GET['token'] ?? '' );
		$nonce  = sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) );
		// phpcs:enable

		if ( ! $att_id || ! wp_verify_nonce( $nonce, 'arshid6social_att_' . $att_id ) ) {
			status_header( 403 );
			exit;
		}

		global $wpdb;
		$att = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}arshid6social_attachments WHERE id = %d AND parent_type = 'message'",
			$att_id
		) );

		if ( ! $att || $att->file_url !== $token ) {
			status_header( 404 );
			exit;
		}

		// IDOR check: requester must be a conversation participant.
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			status_header( 403 );
			exit;
		}

		$is_participant = (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_messages m
			JOIN {$wpdb->prefix}sn_messages_recipients r ON r.thread_id = m.thread_id
			WHERE m.id = %d AND r.user_id = %d",
			(int) $att->parent_id, $user_id
		) );

		if ( ! $is_participant && ! current_user_can( 'arshid6social_manage_activity' ) ) {
			status_header( 403 );
			exit;
		}

		// Serve the file.
		$file_path = (string) $att->file_path;
		if ( ! file_exists( $file_path ) ) {
			status_header( 404 );
			exit;
		}

		$mime = (string) $att->mime_type;
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Content-Disposition: inline; filename="' . esc_attr( $att->file_name ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	public function ajax_delete(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		$att_id  = absint( $_POST['attachment_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id = get_current_user_id();

		global $wpdb;
		$att = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}arshid6social_attachments WHERE id = %d AND parent_type = 'message'",
			$att_id
		) );

		if ( ! $att || ( (int) $att->uploader_id !== $user_id && ! current_user_can( 'arshid6social_manage_activity' ) ) ) {
			wp_send_json_error( null, 403 );
		}

		if ( ! empty( $att->wp_attachment_id ) ) {
			wp_delete_attachment( (int) $att->wp_attachment_id, true );
		} elseif ( ! empty( $att->file_path ) ) {
			\Arshid6Social\Media_Handler::delete_file( (string) $att->file_path );
		}

		$wpdb->delete( $wpdb->prefix . 'arshid6social_attachments', array( 'id' => $att_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_send_json_success();
	}

	// ── Cleanup ───────────────────────────────────────────────────────────────

	public function delete_for_thread( int $thread_id ): void {
		global $wpdb;

		$message_ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_messages WHERE thread_id = %d",
			$thread_id
		) );

		foreach ( $message_ids as $mid ) {
			$atts = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT file_path, wp_attachment_id FROM {$wpdb->prefix}arshid6social_attachments WHERE parent_id = %d AND parent_type = 'message'",
				(int) $mid
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
				array( 'parent_id' => (int) $mid, 'parent_type' => 'message' ),
				array( '%d', '%s' )
			);
		}
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

	public function get_for_message( int $message_id, int $viewer_id ): array {
		global $wpdb;

		// IDOR: only participants can list attachments.
		$is_participant = (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_messages m
			JOIN {$wpdb->prefix}sn_messages_recipients r ON r.thread_id = m.thread_id
			WHERE m.id = %d AND r.user_id = %d",
			$message_id, $viewer_id
		) );

		if ( ! $is_participant ) {
			return array();
		}

		$atts = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, file_url AS token, file_name, file_size, mime_type, media_type
			FROM {$wpdb->prefix}arshid6social_attachments
			WHERE parent_id = %d AND parent_type = 'message' ORDER BY created_at ASC",
			$message_id
		), ARRAY_A ) ?: array();

		// Build serve URLs.
		return array_map( function( array $att ): array {
			$serve_url = add_query_arg( array(
				'action' => 'arshid6social_message_attachment_serve',
				'id'     => $att['id'],
				'token'  => $att['token'],
				'nonce'  => wp_create_nonce( 'arshid6social_att_' . $att['id'] ),
			), admin_url( 'admin-ajax.php' ) );

			unset( $att['token'] );
			$att['serve_url'] = esc_url( $serve_url );
			return $att;
		}, $atts );
	}

	// ── REST ──────────────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		( new Messages_Attachments_REST() )->register_routes();
	}
}
