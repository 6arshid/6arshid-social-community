<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Polls feature.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Polls {

	public function __construct() {
		add_action( 'wp_ajax_arshid6social_poll_create',          array( $this, 'ajax_create' ) );
		add_action( 'wp_ajax_arshid6social_poll_vote',            array( $this, 'ajax_vote' ) );
		add_action( 'wp_ajax_arshid6social_poll_results',         array( $this, 'ajax_results' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_poll_results',  array( $this, 'ajax_results' ) );
		add_action( 'wp_ajax_arshid6social_poll_get_html',        array( $this, 'ajax_get_html' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_poll_get_html', array( $this, 'ajax_get_html' ) );
		add_action( 'wp_ajax_arshid6social_poll_suggest_option',  array( $this, 'ajax_suggest_option' ) );

		// Auto-close expired polls.
		add_action( 'arshid6social_poll_expire_check', array( $this, 'close_expired' ) );
		if ( ! wp_next_scheduled( 'arshid6social_poll_expire_check' ) ) {
			wp_schedule_event( time(), 'hourly', 'arshid6social_poll_expire_check' );
		}

		// Remove poll when activity is deleted.
		add_action( 'arshid6social_activity_deleted', array( $this, 'delete_by_activity' ) );

		// Inject poll data into activity API responses so the JS can render it.
		add_filter( 'arshid6social_format_activity', array( $this, 'add_poll_to_activity' ), 10, 2 );
	}

	public function add_poll_to_activity( array $formatted, object $activity ): array {
		try {
			$poll = $this->get_poll_by_activity( (int) $activity->id );
			if ( $poll ) {
				$formatted['poll'] = $this->get_results( (int) $poll->id, get_current_user_id() );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[WPSN Polls] add_poll_to_activity error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}
		return $formatted;
	}

	// ── Core ──────────────────────────────────────────────────────────────────

	/**
	 * Creates a poll and attaches it to an activity.
	 *
	 * @param array $args {
	 *   int    $activity_id
	 *   int    $user_id
	 *   string $question
	 *   array  $options      Array of option texts (or arrays with text + image).
	 *   string $poll_type    'single' | 'multiple' | 'ranked'
	 *   bool   $anonymous
	 *   bool   $allow_change_vote
	 *   string $results_visibility  'always' | 'after_vote' | 'after_close'
	 *   string $end_date     MySQL datetime or empty.
	 *   string $start_date   MySQL datetime or empty.
	 * }
	 * @return int|false Poll ID.
	 */
	public function create( array $args ): int|false {
		global $wpdb;

		$max_opts = (int) get_option( 'arshid6social_eng_polls_max_options', 10 );
		$options  = array_slice( (array) ( $args['options'] ?? array() ), 0, $max_opts );

		if ( count( $options ) < 2 ) {
			return false;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_polls',
			array(
				'activity_id'           => absint( $args['activity_id'] ?? 0 ),
				'user_id'               => absint( $args['user_id'] ?? get_current_user_id() ),
				'question'              => sanitize_text_field( $args['question'] ?? '' ),
				'poll_type'             => in_array( $args['poll_type'] ?? 'single', array( 'single', 'multiple', 'ranked' ), true ) ? $args['poll_type'] : 'single',
				'anonymous'             => (int) ! empty( $args['anonymous'] ),
				'allow_change_vote'     => (int) ( $args['allow_change_vote'] ?? true ),
				'results_visibility'    => in_array( $args['results_visibility'] ?? 'always', array( 'always', 'after_vote', 'after_close' ), true ) ? ( $args['results_visibility'] ?? 'always' ) : 'always',
				'allow_voter_suggest'   => (int) ! empty( $args['allow_voter_suggest'] ) && get_option( 'arshid6social_eng_polls_allow_voter_suggest', false ),
				'end_date'              => ! empty( $args['end_date'] ) ? sanitize_text_field( $args['end_date'] ) : null,
				'start_date'            => ! empty( $args['start_date'] ) ? sanitize_text_field( $args['start_date'] ) : null,
				'status'                => 'open',
				'created_at'            => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		$poll_id = $wpdb->insert_id ? (int) $wpdb->insert_id : 0;
		if ( ! $poll_id ) {
			return false;
		}

		foreach ( $options as $i => $opt ) {
			$text       = is_array( $opt ) ? sanitize_text_field( $opt['text'] ?? '' ) : sanitize_text_field( $opt );
			$image      = is_array( $opt ) ? esc_url_raw( $opt['image'] ?? '' ) : '';
			$is_correct = is_array( $opt ) ? (int) ! empty( $opt['is_correct'] ) : 0;

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_poll_options',
				array(
					'poll_id'    => $poll_id,
					'option_text' => $text,
					'option_image' => $image ?: null,
					'is_correct'  => $is_correct,
					'sort_order'  => $i,
				),
				array( '%d', '%s', '%s', '%d', '%d' )
			);
		}

		return $poll_id;
	}

	/**
	 * Casts a vote. Handles single/multiple/ranked modes.
	 *
	 * @param int   $poll_id    Poll ID.
	 * @param int[] $option_ids Option IDs being voted for.
	 * @param int   $user_id    Voter.
	 * @return array{success:bool, message:string}
	 */
	public function vote( int $poll_id, array $option_ids, int $user_id ): array {
		global $wpdb;

		$poll = $this->get_poll( $poll_id );
		if ( ! $poll ) {
			return array( 'success' => false, 'message' => __( 'Poll not found.', '6arshid-social-community' ) );
		}

		if ( 'open' !== $poll->status ) {
			return array( 'success' => false, 'message' => __( 'This poll is closed.', '6arshid-social-community' ) );
		}

		if ( $poll->start_date && strtotime( $poll->start_date ) > time() ) {
			return array( 'success' => false, 'message' => __( 'This poll has not started yet.', '6arshid-social-community' ) );
		}

		$has_voted = $this->user_has_voted( $poll_id, $user_id );

		if ( $has_voted && ! $poll->allow_change_vote ) {
			return array( 'success' => false, 'message' => __( 'You have already voted.', '6arshid-social-community' ) );
		}

		// Verify options belong to this poll.
		$valid_ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_poll_options WHERE poll_id = %d",
			$poll_id
		) );
		$valid_ids  = array_map( 'intval', $valid_ids );
		$option_ids = array_unique( array_map( 'absint', $option_ids ) );
		$option_ids = array_values( array_intersect( $option_ids, $valid_ids ) );

		if ( empty( $option_ids ) ) {
			return array( 'success' => false, 'message' => __( 'Invalid option(s).', '6arshid-social-community' ) );
		}

		// Enforce single-choice limit.
		if ( 'single' === $poll->poll_type ) {
			$option_ids = array( $option_ids[0] );
		}

		// Remove previous votes (change-vote).
		if ( $has_voted ) {
			$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_poll_votes',
				array( 'poll_id' => $poll_id, 'user_id' => $user_id ),
				array( '%d', '%d' )
			);
		}

		foreach ( $option_ids as $rank => $opt_id ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_poll_votes',
				array(
					'poll_id'   => $poll_id,
					'option_id' => $opt_id,
					'user_id'   => $user_id,
					'rank'      => $rank + 1,
					'voted_at'  => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%d', '%s' )
			);
		}

		return array( 'success' => true, 'message' => __( 'Vote recorded.', '6arshid-social-community' ), 'results' => $this->get_results( $poll_id, $user_id ) );
	}

	public function get_poll( int $poll_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_polls WHERE id = %d",
			$poll_id
		) );
	}

	public function get_poll_by_activity( int $activity_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_polls WHERE activity_id = %d",
			$activity_id
		) );
	}

	public function user_has_voted( int $poll_id, int $user_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_poll_votes WHERE poll_id = %d AND user_id = %d",
			$poll_id, $user_id
		) );
	}

	/**
	 * Returns formatted results for a poll.
	 *
	 * @return array<string, mixed>
	 */
	public function get_results( int $poll_id, int $viewer_id = 0 ): array {
		global $wpdb;

		$poll    = $this->get_poll( $poll_id );
		if ( ! $poll ) {
			return array();
		}

		$options = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT o.*, COUNT(v.id) AS vote_count
			FROM {$wpdb->prefix}sn_poll_options o
			LEFT JOIN {$wpdb->prefix}sn_poll_votes v ON v.option_id = o.id
			WHERE o.poll_id = %d
			GROUP BY o.id ORDER BY o.sort_order ASC",
			$poll_id
		), ARRAY_A ) ?: array();

		$total_votes = array_sum( array_column( $options, 'vote_count' ) );

		// Visibility control.
		$can_see_results = true;
		if ( 'after_vote' === $poll->results_visibility && $viewer_id && ! $this->user_has_voted( $poll_id, $viewer_id ) && 'closed' !== $poll->status ) {
			$can_see_results = false;
		}
		if ( 'after_close' === $poll->results_visibility && 'closed' !== $poll->status ) {
			$can_see_results = false;
		}

		$user_voted_options = array();
		if ( $viewer_id ) {
			$user_voted_options = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT option_id FROM {$wpdb->prefix}sn_poll_votes WHERE poll_id = %d AND user_id = %d",
				$poll_id, $viewer_id
			) ) );
		}

		$formatted_options = array_map( function( array $opt ) use ( $total_votes, $can_see_results, $user_voted_options, $poll ): array {
			$count      = $can_see_results ? (int) $opt['vote_count'] : null;
			$percentage = ( $can_see_results && $total_votes > 0 ) ? round( ( (int) $opt['vote_count'] / $total_votes ) * 100, 1 ) : null;

			return array(
				'id'          => (int) $opt['id'],
				'text'        => esc_html( $opt['option_text'] ),
				'image'       => $opt['option_image'] ? esc_url( $opt['option_image'] ) : null,
				'isCorrect'   => (bool) $opt['is_correct'],
				'voteCount'   => $count,
				'percentage'  => $percentage,
				'userVoted'   => in_array( (int) $opt['id'], $user_voted_options, true ),
			);
		}, $options );

		return array(
			'pollId'        => $poll_id,
			'question'      => esc_html( $poll->question ),
			'pollType'      => esc_attr( $poll->poll_type ),
			'anonymous'     => (bool) $poll->anonymous,
			'status'        => esc_attr( $poll->status ),
			'totalVotes'    => $can_see_results ? $total_votes : null,
			'hasVoted'      => $viewer_id ? $this->user_has_voted( $poll_id, $viewer_id ) : false,
			'canSeeResults' => $can_see_results,
			'allowChange'   => (bool) $poll->allow_change_vote,
			'endDate'       => $poll->end_date,
			'startDate'     => $poll->start_date,
			'options'       => $formatted_options,
		);
	}

	public function close_expired(): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"UPDATE {$wpdb->prefix}sn_polls SET status = 'closed' WHERE status = 'open' AND end_date IS NOT NULL AND end_date <= NOW()"
		);
	}

	public function delete_by_activity( int $activity_id ): void {
		global $wpdb;
		$poll = $this->get_poll_by_activity( $activity_id );
		if ( ! $poll ) {
			return;
		}
		$poll_id = (int) $poll->id;
		$wpdb->delete( $wpdb->prefix . 'sn_poll_votes',   array( 'poll_id' => $poll_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_poll_options', array( 'poll_id' => $poll_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_polls',        array( 'id' => $poll_id ),      array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public function ajax_create(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$activity_id = absint( $_POST['activity_id'] ?? 0 );
		$question    = sanitize_text_field( wp_unslash( $_POST['question'] ?? '' ) );
		$options_raw = $_POST['options'] ?? array();
		$options     = is_array( $options_raw ) ? array_map( 'sanitize_text_field', wp_unslash( $options_raw ) ) : array();
		$poll_type   = sanitize_key( $_POST['poll_type'] ?? 'single' );
		$anonymous   = ! empty( $_POST['anonymous'] );
		$end_date    = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
		// phpcs:enable

		if ( ! $activity_id || ! $question || count( $options ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Question and at least 2 options required.', '6arshid-social-community' ) ), 400 );
		}

		// Verify activity ownership.
		global $wpdb;
		$owner = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_activity WHERE id = %d",
			$activity_id
		) );
		if ( $owner !== get_current_user_id() ) {
			wp_send_json_error( null, 403 );
		}

		$poll_id = $this->create( array(
			'activity_id'       => $activity_id,
			'user_id'           => get_current_user_id(),
			'question'          => $question,
			'options'           => $options,
			'poll_type'         => $poll_type,
			'anonymous'         => $anonymous,
			'allow_change_vote' => true,
			'end_date'          => $end_date,
		) );

		if ( ! $poll_id ) {
			global $wpdb;
			$db_err = $wpdb->last_error ? ' DB: ' . $wpdb->last_error : ' (no DB error — table may not exist)';
			wp_send_json_error( array( 'message' => 'Poll save failed.' . $db_err ), 500 );
		}

		wp_send_json_success( array(
			'poll_id' => $poll_id,
			'results' => $this->get_results( $poll_id, get_current_user_id() ),
		) );
	}

	/**
	 * Renders server-side HTML for a poll so it can be stored in activity content
	 * and displayed in the activity stream without a separate API call.
	 */
	public function render_html( int $poll_id, int $viewer_id = 0 ): string {
		global $wpdb;

		$poll = $this->get_poll( $poll_id );
		if ( ! $poll ) {
			return '';
		}

		$options = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, option_text FROM {$wpdb->prefix}sn_poll_options WHERE poll_id = %d ORDER BY sort_order ASC",
			$poll_id
		), ARRAY_A ) ?: array();

		$results    = $viewer_id ? $this->get_results( $poll_id, $viewer_id ) : array();
		$has_voted  = $viewer_id ? ( $results['hasVoted'] ?? false ) : false;
		$total      = $results['totalVotes'] ?? 0;
		$type       = esc_attr( $poll->poll_type );
		$input_type = 'multiple' === $poll->poll_type ? 'checkbox' : 'radio';

		$opts_html = '';
		foreach ( $options as $opt ) {
			$oid       = (int) $opt['id'];
			$opt_label = esc_html( $opt['option_text'] );
			$voted_opt = array_filter( $results['options'] ?? array(), fn( $o ) => (int) $o['id'] === $oid );
			$voted_opt = reset( $voted_opt );
			$pct       = $has_voted && $voted_opt ? (float) ( $voted_opt['percentage'] ?? 0 ) : 0;
			$voted_cls = $has_voted && $voted_opt && $voted_opt['userVoted'] ? ' arshid6social-poll-voted' : '';

			$opts_html .= '<div class="arshid6social-poll-option' . $voted_cls . '" data-option-id="' . $oid . '">';
			if ( ! $has_voted ) {
				$opts_html .= '<label><input type="' . $input_type . '" name="poll_option" value="' . $oid . '"> ' . $opt_label . '</label>';
			} else {
				$opts_html .= '<span class="arshid6social-poll-option-text">' . $opt_label . '</span>';
				$opts_html .= '<div class="arshid6social-poll-bar-wrap"><div class="arshid6social-poll-bar-track">';
				$opts_html .= '<div class="arshid6social-poll-bar-fill" style="width:' . $pct . '%"></div>';
				$opts_html .= '</div><div class="arshid6social-poll-bar-label">' . $pct . '% &mdash; ' . ( $voted_opt['voteCount'] ?? 0 ) . '</div></div>';
			}
			$opts_html .= '</div>';
		}

		$vote_btn = ( ! $has_voted && 'open' === $poll->status )
			? '<button type="button" class="arshid6social-btn arshid6social-btn--primary arshid6social-btn--sm arshid6social-poll-vote-btn" onclick="ARSHID6SOCIALVote(this)">' . esc_html__( 'Vote', '6arshid-social-community' ) . '</button>'
			: '';

		$meta_text = sprintf(
			/* translators: %d: vote count */
			esc_html( _n( '%d vote', '%d votes', $total, '6arshid-social-community' ) ),
			$total
		);

		return '<div class="arshid6social-poll"'
			. ' data-poll-id="' . $poll_id . '"'
			. ' data-poll-type="' . $type . '"'
			. ' data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"'
			. ' data-nonce="' . esc_attr( wp_create_nonce( 'arshid6social_ajax_nonce' ) ) . '"'
			. '>'
			. '<p class="arshid6social-poll-question"><strong>' . esc_html( $poll->question ) . '</strong></p>'
			. '<div class="arshid6social-poll-options">' . $opts_html . '</div>'
			. '<div class="arshid6social-poll-footer">'
			. $vote_btn
			. '<span class="arshid6social-poll-meta">' . $meta_text . '</span>'
			. '</div></div>';
	}

	public function ajax_vote(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		// Rate limit.
		$rl_key = 'arshid6social_rl_vote_' . get_current_user_id();
		$count  = (int) get_transient( $rl_key );
		if ( $count >= 60 ) {
			wp_send_json_error( array( 'message' => __( 'Too many votes. Please slow down.', '6arshid-social-community' ) ), 429 );
		}
		set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

		// phpcs:disable WordPress.Security.NonceVerification
		$poll_id    = absint( $_POST['poll_id'] ?? 0 );
		$option_ids = array_map( 'absint', (array) ( $_POST['option_ids'] ?? array() ) );
		// phpcs:enable

		if ( ! $poll_id || empty( $option_ids ) ) {
			wp_send_json_error( null, 400 );
		}

		$user_id = get_current_user_id();
		$result  = $this->vote( $poll_id, $option_ids, $user_id );

		if ( ! $result['success'] ) {
			wp_send_json_error( $result, 400 );
		}

		// Return server-rendered HTML so JS just swaps the DOM node — no client-side data mapping needed.
		wp_send_json_success( array(
			'html' => $this->render_html( $poll_id, $user_id ),
		) );
	}

	public function ajax_results(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		$poll_id = absint( $_GET['poll_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $poll_id ) {
			wp_send_json_error( null, 400 );
		}
		wp_send_json_success( $this->get_results( $poll_id, get_current_user_id() ) );
	}

	public function ajax_get_html(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		$poll_id = absint( $_GET['poll_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $poll_id ) {
			wp_send_json_error( null, 400 );
		}
		wp_send_json_success( array( 'html' => $this->render_html( $poll_id, get_current_user_id() ) ) );
	}

	public function ajax_suggest_option(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$poll_id     = absint( $_POST['poll_id'] ?? 0 );
		$option_text = sanitize_text_field( wp_unslash( $_POST['option_text'] ?? '' ) );
		// phpcs:enable

		$poll = $this->get_poll( $poll_id );
		if ( ! $poll || ! $poll->allow_voter_suggest || 'closed' === $poll->status ) {
			wp_send_json_error( null, 403 );
		}

		// Send to moderation queue.
		\Arshid6Social\Components\Moderation\Moderation::add_report(
			get_current_user_id(),
			$poll_id,
			'poll_suggestion',
			$option_text
		);

		wp_send_json_success( array( 'message' => __( 'Your suggestion has been submitted for review.', '6arshid-social-community' ) ) );
	}

	// ── REST ──────────────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		( new Polls_REST() )->register_routes();
	}
}
