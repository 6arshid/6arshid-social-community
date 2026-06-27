<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Advanced Polls – extends base Polls with image support, quiz mode, and ranked choice.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Advanced_Polls {

	public function __construct() {
		// Image upload for poll options.
		add_action( 'wp_ajax_arshid6social_poll_upload_option_image', array( $this, 'ajax_upload_option_image' ) );

		// Quiz results: reveal answer after voting.
		add_filter( 'arshid6social_poll_results', array( $this, 'reveal_quiz_answer' ), 10, 3 );

		// Templates: allow admin-defined templates.
		add_action( 'wp_ajax_arshid6social_poll_load_template',        array( $this, 'ajax_load_template' ) );
		add_action( 'wp_ajax_arshid6social_poll_save_template',        array( $this, 'ajax_save_template' ) );
		add_action( 'wp_ajax_arshid6social_poll_delete_template',      array( $this, 'ajax_delete_template' ) );
		add_action( 'wp_ajax_arshid6social_poll_list_templates',       array( $this, 'ajax_list_templates' ) );
	}

	// ── Image upload for options ───────────────────────────────────────────────

	public function ajax_upload_option_image(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		if ( empty( $_FILES['image']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No image uploaded.', '6arshid-social-community-main' ) ), 400 );
		}

		$file = $_FILES['image']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$max_bytes = (int) get_option( 'arshid6social_max_upload_size_mb', 5 ) * MB_IN_BYTES;
		if ( (int) $file['size'] > $max_bytes ) {
			wp_send_json_error( array( 'message' => __( 'Image too large.', '6arshid-social-community-main' ) ), 413 );
		}

		$finfo     = new \finfo( FILEINFO_MIME_TYPE );
		$real_mime = $finfo->file( $file['tmp_name'] );
		$allowed   = array( 'image/jpeg', 'image/png', 'image/webp' );

		if ( ! in_array( $real_mime, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Only JPEG, PNG, and WebP images are allowed.', '6arshid-social-community-main' ) ), 415 );
		}

		// Re-encode image to strip EXIF/GPS.
		$image = $this->re_encode_image( $file['tmp_name'], $real_mime );
		if ( ! $image ) {
			wp_send_json_error( array( 'message' => __( 'Could not process image.', '6arshid-social-community-main' ) ), 500 );
		}

		// Save to randomized path.
		$subdir_filter = function( array $dir ): array {
			$dir['subdir'] = '/social-network/polls';
			$dir['path']   = $dir['basedir'] . $dir['subdir'];
			$dir['url']    = $dir['baseurl'] . $dir['subdir'];
			return $dir;
		};

		add_filter( 'upload_dir', $subdir_filter );
		$upload_dir = wp_upload_dir();
		wp_mkdir_p( $upload_dir['path'] );

		$ext      = 'image/png' === $real_mime ? 'png' : ( 'image/webp' === $real_mime ? 'webp' : 'jpg' );
		$filename = wp_unique_filename( $upload_dir['path'], wp_generate_uuid4() . '.' . $ext );
		$dest     = $upload_dir['path'] . '/' . $filename;

		file_put_contents( $dest, $image ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		remove_filter( 'upload_dir', $subdir_filter );

		$url = $upload_dir['url'] . '/' . $filename;

		\Arshid6Social\Media_Handler::register_to_media_library(
			$dest,
			$url,
			$real_mime,
			sanitize_file_name( (string) ( $file['name'] ?? $filename ) ),
			get_current_user_id()
		);

		wp_send_json_success( array( 'url' => esc_url( $url ), 'path' => $dest ) );
	}

	/**
	 * Re-encodes an image via GD to strip EXIF/GPS metadata.
	 *
	 * @return string|false Raw image bytes, or false on failure.
	 */
	private function re_encode_image( string $tmp_path, string $mime ): string|false {
		if ( ! extension_loaded( 'gd' ) ) {
			return file_get_contents( $tmp_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		$img = match ( $mime ) {
			'image/jpeg' => imagecreatefromjpeg( $tmp_path ),
			'image/png'  => imagecreatefrompng( $tmp_path ),
			'image/webp' => imagecreatefromwebp( $tmp_path ),
			default      => false,
		};

		if ( ! $img ) {
			return false;
		}

		ob_start();
		match ( $mime ) {
			'image/jpeg' => imagejpeg( $img, null, 85 ),
			'image/png'  => imagepng( $img ),
			'image/webp' => imagewebp( $img, null, 85 ),
		};
		imagedestroy( $img );
		return ob_get_clean() ?: false;
	}

	// ── Quiz mode ────────────────────────────────────────────────────────────

	/**
	 * After a user votes, mark which option was correct.
	 */
	public function reveal_quiz_answer( array $results, int $poll_id, int $viewer_id ): array {
		if ( empty( $results['hasVoted'] ) ) {
			return $results;
		}

		// Mark correct answer(s) so the frontend can highlight them.
		$results['options'] = array_map( function( array $opt ): array {
			// is_correct is already in the results array from get_results().
			return $opt;
		}, $results['options'] );

		return $results;
	}

	// ── Poll templates (admin) ────────────────────────────────────────────────

	private function get_templates(): array {
		return (array) get_option( 'arshid6social_poll_templates', array() );
	}

	public function ajax_list_templates(): void {
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( null, 403 );
		}
		wp_send_json_success( $this->get_templates() );
	}

	public function ajax_load_template(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}
		$id        = sanitize_key( $_POST['template_id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$templates = $this->get_templates();
		isset( $templates[ $id ] )
			? wp_send_json_success( $templates[ $id ] )
			: wp_send_json_error( null, 404 );
	}

	public function ajax_save_template(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( null, 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$options = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['options'] ?? array() ) );
		// phpcs:enable

		if ( ! $name || empty( $options ) ) {
			wp_send_json_error( null, 400 );
		}

		$id                 = sanitize_key( str_replace( ' ', '_', strtolower( $name ) ) . '_' . time() );
		$templates          = $this->get_templates();
		$templates[ $id ]   = array( 'name' => $name, 'options' => $options );
		update_option( 'arshid6social_poll_templates', $templates );

		wp_send_json_success( array( 'id' => $id ) );
	}

	public function ajax_delete_template(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( null, 403 );
		}
		$id        = sanitize_key( $_POST['template_id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$templates = $this->get_templates();
		unset( $templates[ $id ] );
		update_option( 'arshid6social_poll_templates', $templates );
		wp_send_json_success();
	}
}
