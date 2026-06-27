<?php
/**
 * Messages inbox template.
 * Rendered inside a WP page via [arshid6social_messages] shortcode — no get_header/get_footer.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

$thread_id            = isset( $thread_id ) ? (int) $thread_id : 0;
$compose_recipient_id = isset( $compose_recipient_id ) ? (int) $compose_recipient_id : 0;

$_att_enabled = false;
$_att_accept  = '';
$_att_max_mb  = 10;
$_att_types   = array();
if ( function_exists( 'arshid6social_eng' ) ) {
	$_att_feature = arshid6social_eng()->feature( 'messages_attachments' );
	if ( $_att_feature ) {
		$_att_enabled = (bool) get_option( 'arshid6social_eng_messages_attachments', false );
		$_att_max_mb  = (int) get_option( 'arshid6social_eng_msg_att_max_mb', 10 );
		$_att_types   = (array) get_option( 'arshid6social_eng_msg_att_types', array( 'image', 'audio' ) );
		$_mime_map    = array(
			'image'    => 'image/jpeg,image/png,image/gif,image/webp',
			'audio'    => 'audio/mpeg,audio/wav,audio/ogg,audio/webm,audio/mp4',
			'document' => 'application/pdf',
		);
		$_accept_parts = array();
		foreach ( $_att_types as $_t ) {
			if ( isset( $_mime_map[ $_t ] ) ) {
				$_accept_parts[] = $_mime_map[ $_t ];
			}
		}
		$_att_accept = implode( ',', $_accept_parts );
	}
}

$_arshid6social_config = array(
	'ajaxUrl'  => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
	'nonce'    => wp_create_nonce( 'arshid6social_ajax_nonce' ),
	'userId'              => get_current_user_id(),
	'userName'            => (string) wp_get_current_user()->display_name,
	'currentUserProfileUrl' => esc_url( home_url( '/members/' . wp_get_current_user()->user_nicename . '/' ) ),
	'threadId'            => $thread_id,
	'composeRecipientId'  => $compose_recipient_id,
	'attachments'         => array(
		'enabled' => $_att_enabled,
		'maxMb'   => $_att_max_mb,
		'types'   => $_att_types,
		'accept'  => $_att_accept,
	),
	'l10n'     => array(
		'noConversations'   => __( 'No conversations yet. Start one with the + button.', '6arshid social community' ),
		'loading'           => __( 'Loading...', '6arshid social community' ),
		'noUsersFound'      => __( 'No users found.', '6arshid social community' ),
		'couldNotLoadUsers' => __( 'Could not load users.', '6arshid social community' ),
		'loadMore'          => __( 'Load more', '6arshid social community' ),
		'errorPrefix'       => __( 'Error:', '6arshid social community' ),
		'conversation'      => __( 'Conversation', '6arshid social community' ),
		'noMessagesYet'     => __( 'No messages yet. Say hello!', '6arshid social community' ),
		'edited'            => __( 'edited', '6arshid social community' ),
		'edit'              => __( 'Edit', '6arshid social community' ),
		'delete'            => __( 'Delete', '6arshid social community' ),
		'save'              => __( 'Save', '6arshid social community' ),
		'cancel'            => __( 'Cancel', '6arshid social community' ),
		'fileTooLarge'      => sprintf(
			/* translators: %s = max MB */
			__( 'File exceeds %s MB limit.', '6arshid social community' ),
			$_att_max_mb
		),
		'removeAttachment'  => __( 'Remove', '6arshid social community' ),
	),
);
?>
<style>.socialnetworksix-right{display:none!important}.socialnetworksix-shell{grid-template-columns:var(--a6sc-sidebar-w,275px) 1fr!important}@media(max-width:1280px){.socialnetworksix-shell{grid-template-columns:88px 1fr!important}}@media(max-width:700px){.socialnetworksix-shell{grid-template-columns:1fr!important}}.arshid6social-messages-layout,.arshid6social-thread-list,.arshid6social-thread-list__header,.arshid6social-message-pane,.arshid6social-message-pane__header{background:var(--a6sc-bg,#000)!important}#arshid6social-thread-list-inner{background:var(--a6sc-bg,#000)}</style>
<div class="arshid6social-directory-wrap" id="arshid6social-messages-page"
	data-arshid6social-cfg="<?php echo esc_attr( wp_json_encode( $_arshid6social_config, JSON_UNESCAPED_UNICODE ) ); ?>"
>
	<div class="arshid6social-messages-layout" id="arshid6social-messages-layout">

		<!-- Thread list sidebar -->
		<aside class="arshid6social-thread-list" id="arshid6social-thread-list" aria-label="<?php esc_attr_e( 'Conversations', '6arshid social community' ); ?>" role="navigation">
			<div class="arshid6social-thread-list__header">
				<span class="arshid6social-thread-list__title"><?php esc_html_e( 'Messages', '6arshid social community' ); ?></span>
				<button class="arshid6social-compose-btn" id="arshid6social-compose-btn"
					aria-label="<?php esc_attr_e( 'New message', '6arshid social community' ); ?>"
					title="<?php esc_attr_e( 'New message', '6arshid social community' ); ?>">+</button>
			</div>
			<div class="arshid6social-thread-search-wrap">
				<input type="search" id="arshid6social-thread-search" class="arshid6social-thread-search"
					placeholder="<?php esc_attr_e( 'Search messages or users…', '6arshid social community' ); ?>"
					autocomplete="off"
					aria-label="<?php esc_attr_e( 'Search conversations', '6arshid social community' ); ?>" />
			</div>
			<div id="arshid6social-thread-list-inner">
				<div style="padding:1.5rem;text-align:center;"><div class="arshid6social-spinner"></div></div>
			</div>
		</aside>

		<!-- Message pane -->
		<main class="arshid6social-message-pane" id="arshid6social-message-pane" aria-label="<?php esc_attr_e( 'Message thread', '6arshid social community' ); ?>">
			<div class="arshid6social-message-pane__header" id="arshid6social-pane-header">
				<button class="arshid6social-back-btn" id="arshid6social-back-btn" aria-label="<?php esc_attr_e( 'Back to conversations', '6arshid social community' ); ?>">
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<span id="arshid6social-pane-title"><?php esc_html_e( 'Select a conversation', '6arshid social community' ); ?></span>
			</div>
			<div class="arshid6social-message-list" id="arshid6social-message-list" role="log" aria-live="polite"></div>
			<div class="arshid6social-message-composer" id="arshid6social-message-composer" hidden>
				<div class="arshid6social-composer-att-preview" id="arshid6social-att-preview" hidden></div>
				<div class="arshid6social-composer-row">
					<?php if ( $_att_enabled ) : ?>
					<button type="button" class="arshid6social-attach-btn" id="arshid6social-attach-btn"
						aria-label="<?php esc_attr_e( 'Attach file', '6arshid social community' ); ?>"
						title="<?php esc_attr_e( 'Attach file', '6arshid social community' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
					</button>
					<input type="file" id="arshid6social-attach-input" multiple hidden
						accept="<?php echo esc_attr( $_att_accept ); ?>" />
					<?php endif; ?>
					<textarea class="arshid6social-message-composer__input" id="arshid6social-message-input"
						rows="1"
						placeholder="<?php esc_attr_e( 'Type a message…', '6arshid social community' ); ?>"
						aria-label="<?php esc_attr_e( 'Message', '6arshid social community' ); ?>"
					></textarea>
					<button class="arshid6social-btn arshid6social-btn--primary" id="arshid6social-send-btn">
						<?php esc_html_e( 'Send', '6arshid social community' ); ?>
					</button>
				</div>
			</div>
		</main>
	</div>

	<!-- Compose / new-message modal -->
	<div class="arshid6social-compose-modal" id="arshid6social-compose-modal" hidden role="dialog" aria-modal="true"
		aria-label="<?php esc_attr_e( 'New message', '6arshid social community' ); ?>">
		<div class="arshid6social-compose-modal__inner">
			<div class="arshid6social-compose-modal__header">
				<h3><?php esc_html_e( 'New Message', '6arshid social community' ); ?></h3>
				<button class="arshid6social-compose-close" id="arshid6social-compose-close" aria-label="<?php esc_attr_e( 'Close', '6arshid social community' ); ?>">&#x2715;</button>
			</div>
			<input type="search" class="arshid6social-user-search" id="arshid6social-user-search"
				placeholder="<?php esc_attr_e( 'Search users…', '6arshid social community' ); ?>"
				aria-label="<?php esc_attr_e( 'Search users', '6arshid social community' ); ?>"
				autocomplete="off" />
			<div class="arshid6social-user-list" id="arshid6social-user-list" role="listbox">
				<div style="padding:1rem;text-align:center;"><div class="arshid6social-spinner"></div></div>
			</div>
		</div>
	</div>

	<!-- Delete conversation confirm modal -->
	<div class="arshid6social-delete-modal" id="arshid6social-delete-confirm-modal" hidden role="dialog" aria-modal="true"
		aria-label="<?php esc_attr_e( 'Delete conversation', '6arshid social community' ); ?>">
		<div class="arshid6social-delete-modal__inner">
			<h3 class="arshid6social-delete-modal__title"><?php esc_html_e( 'Delete Conversation', '6arshid social community' ); ?></h3>
			<p class="arshid6social-delete-modal__desc"><?php esc_html_e( 'Who do you want to delete this conversation for?', '6arshid social community' ); ?></p>
			<div class="arshid6social-delete-modal__actions">
				<button class="arshid6social-btn arshid6social-btn--danger" id="arshid6social-delete-for-both">
					<?php esc_html_e( 'Delete for Everyone', '6arshid social community' ); ?>
				</button>
				<button class="arshid6social-btn arshid6social-btn--secondary" id="arshid6social-delete-for-self">
					<?php esc_html_e( 'Delete for Me Only', '6arshid social community' ); ?>
				</button>
				<button class="arshid6social-btn arshid6social-btn--ghost" id="arshid6social-delete-cancel">
					<?php esc_html_e( 'Cancel', '6arshid social community' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Delete single message confirm modal -->
	<div class="arshid6social-delete-modal" id="arshid6social-delete-msg-modal" hidden role="dialog" aria-modal="true"
		aria-label="<?php esc_attr_e( 'Delete message', '6arshid social community' ); ?>">
		<div class="arshid6social-delete-modal__inner">
			<h3 class="arshid6social-delete-modal__title"><?php esc_html_e( 'Delete Message', '6arshid social community' ); ?></h3>
			<p class="arshid6social-delete-modal__desc"><?php esc_html_e( 'Who do you want to delete this message for?', '6arshid social community' ); ?></p>
			<div class="arshid6social-delete-modal__actions">
				<button class="arshid6social-btn arshid6social-btn--danger" id="arshid6social-delete-msg-both" hidden>
					<?php esc_html_e( 'Delete for Everyone', '6arshid social community' ); ?>
				</button>
				<button class="arshid6social-btn arshid6social-btn--secondary" id="arshid6social-delete-msg-self">
					<?php esc_html_e( 'Delete for Me Only', '6arshid social community' ); ?>
				</button>
				<button class="arshid6social-btn arshid6social-btn--ghost" id="arshid6social-delete-msg-cancel">
					<?php esc_html_e( 'Cancel', '6arshid social community' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>
