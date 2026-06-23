<?php
namespace Arshid6Social;

/**
 * Registers Gutenberg blocks and their server-side renderers.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Blocks
 *
 * Registers the three core blocks (member-directory, activity-feed, group-list)
 * using block.json metadata and PHP render callbacks.
 */
class Blocks {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$blocks = array(
			'member-directory' => array( $this, 'render_member_directory' ),
			'activity-feed'    => array( $this, 'render_activity_feed' ),
			'group-list'       => array( $this, 'render_group_list' ),
		);

		foreach ( $blocks as $block_name => $render_callback ) {
			register_block_type(
				ARSHID6SOCIAL_PLUGIN_DIR . 'blocks/' . $block_name . '/block.json',
				array( 'render_callback' => $render_callback )
			);
		}
	}

	/**
	 * Server-side render: member directory.
	 *
	 * @param array $attrs Block attributes.
	 */
	public function render_member_directory( array $attrs ): string {
		$per_page    = absint( $attrs['perPage'] ?? 12 );
		$show_search = (bool) ( $attrs['showSearch'] ?? true );
		$type        = sanitize_key( $attrs['defaultType'] ?? 'newest' );

		/** @var Components\Members\Members|null $members_component */
		$members_component = ARSHID6SOCIAL()->component( 'members' );
		if ( ! $members_component ) {
			return '';
		}

		$result  = $members_component->get_members( array( 'type' => $type, 'per_page' => $per_page ) );
		$members = $result['members'];

		global $arshid6social_is_page;
		$arshid6social_is_page = true;

		return ARSHID6SOCIAL()->template()->get_template(
			'members/directory.php',
			array(
				'members'     => $members,
				'total'       => $result['total'],
				'per_page'    => $per_page,
				'show_search' => $show_search,
				'block_mode'  => true,
			),
			true
		);
	}

	/**
	 * Server-side render: activity feed.
	 *
	 * @param array $attrs Block attributes.
	 */
	public function render_activity_feed( array $attrs ): string {
		$per_page      = absint( $attrs['perPage'] ?? 10 );
		$show_composer = (bool) ( $attrs['showComposer'] ?? true );
		$scope         = sanitize_key( $attrs['scope'] ?? 'site' );

		/** @var Components\Activity\Activity|null $activity_component */
		$activity_component = ARSHID6SOCIAL()->component( 'activity' );
		if ( ! $activity_component ) {
			return '';
		}

		$args = array( 'per_page' => $per_page );
		if ( 'self' === $scope ) {
			$args['user_id'] = get_current_user_id();
		}

		global $arshid6social_is_page;
		$arshid6social_is_page = true;

		$nonce = wp_create_nonce( 'arshid6social_activity' );

		$pagination_type = sanitize_key( get_option( 'arshid6social_activity_pagination_type', 'infinite_scroll' ) );

		ob_start();
		?>
		<div class="arshid6social-activity-block"
			data-scope="<?php echo esc_attr( $scope ); ?>"
			data-per-page="<?php echo esc_attr( $per_page ); ?>"
			data-pagination-type="<?php echo esc_attr( $pagination_type ); ?>">
			<?php if ( $show_composer && is_user_logged_in() ) : ?>
			<form class="arshid6social-activity-form arshid6social-card" data-nonce="<?php echo esc_attr( $nonce ); ?>">
				<?php wp_nonce_field( 'arshid6social_activity', 'arshid6social_nonce' ); ?>
				<textarea name="content" class="arshid6social-activity-composer"
					placeholder="<?php esc_attr_e( "What's on your mind?", 'social-network-6' ); ?>"
					rows="3" maxlength="5000" required></textarea>
				<div class="arshid6social-activity-form-footer">
					<button type="submit" class="arshid6social-btn arshid6social-btn-primary">
						<?php esc_html_e( 'Post', 'social-network-6' ); ?>
					</button>
				</div>
			</form>
			<?php endif; ?>

			<div class="arshid6social-activity-feed" aria-label="<?php esc_attr_e( 'Activity feed', 'social-network-6' ); ?>">
				<?php
				// Render skeletons; JS replaces them on load.
				for ( $i = 0; $i < 3; $i++ ) :
					?>
					<div class="arshid6social-activity-item arshid6social-skeleton-item arshid6social-card" aria-hidden="true">
						<div class="arshid6social-skeleton arshid6social-skeleton-circle" style="width:44px;height:44px;"></div>
						<div style="flex:1;">
							<div class="arshid6social-skeleton" style="height:14px;width:40%;margin-block-end:.5rem;"></div>
							<div class="arshid6social-skeleton" style="height:14px;width:80%;margin-block-end:.25rem;"></div>
							<div class="arshid6social-skeleton" style="height:14px;width:60%;"></div>
						</div>
					</div>
				<?php endfor; ?>
			</div>

			<div class="arshid6social-load-more-sentinel" style="height:1px;"></div>
			<div class="arshid6social-activity-pagination arshid6social-pagination"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Server-side render: group list.
	 *
	 * @param array $attrs Block attributes.
	 */
	public function render_group_list( array $attrs ): string {
		$per_page           = absint( $attrs['perPage'] ?? 9 );
		$show_search        = (bool) ( $attrs['showSearch'] ?? true );
		$show_create_button = (bool) ( $attrs['showCreateButton'] ?? true );
		$status             = sanitize_key( $attrs['status'] ?? 'public' );

		/** @var Components\Groups\Groups|null $groups_component */
		$groups_component = ARSHID6SOCIAL()->component( 'groups' );
		if ( ! $groups_component ) {
			return '';
		}

		$statuses = ( 'all' === $status ) ? array( 'public', 'private' ) : array( 'public' );
		$result   = $groups_component->get_groups( array( 'status' => $statuses, 'per_page' => $per_page ) );
		$groups   = $result['groups'];

		global $arshid6social_is_page;
		$arshid6social_is_page = true;

		return ARSHID6SOCIAL()->template()->get_template(
			'groups/directory.php',
			array(
				'groups'             => $groups,
				'total'              => $result['total'],
				'per_page'           => $per_page,
				'show_search'        => $show_search,
				'show_create_button' => $show_create_button,
				'block_mode'         => true,
			),
			true
		);
	}
}
