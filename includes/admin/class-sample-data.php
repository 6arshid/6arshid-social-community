<?php
namespace Arshid6Social\Admin;

/**
 * Sample data import / delete utility.
 *
 * @package Arshid6Social\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Sample_Data
 *
 * Bulk-inserts demo content and removes it cleanly.
 *
 * Import counts:
 *   - 50 users
 *   - 50 activity posts
 *   - 100 notifications for admin
 *   - 50 marketplace listings
 *   - 50 groups
 *   - 50 saved posts (bookmarks) for admin
 *   - 50 message threads to admin
 *   - 30 stories (text only)
 *   - 1 ad
 */
final class Sample_Data {

	private static ?Sample_Data $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_arshid6social_import_sample_data', array( $this, 'ajax_import' ) );
		add_action( 'wp_ajax_arshid6social_delete_sample_data', array( $this, 'ajax_delete' ) );
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public function ajax_import(): void {
		if ( ! check_ajax_referer( 'arshid6social_sample_data', 'nonce', false ) || ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		if ( get_option( 'arshid6social_sample_data_imported' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sample data already exists. Delete it first.', '6arshid social community' ) ) );
		}

		$ids = $this->import();

		update_option( 'arshid6social_sample_data_imported', true, false );
		update_option( 'arshid6social_sample_data_ids', $ids, false );

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: counts */
				__( 'Imported: %1$d users, %2$d activities, %3$d notifications, %4$d listings, %5$d groups, %6$d bookmarks, %7$d messages, %8$d stories, %9$d ad.', '6arshid social community' ),
				count( $ids['users'] ),
				count( $ids['activities'] ),
				count( $ids['notifications'] ),
				count( $ids['listings'] ),
				count( $ids['groups'] ),
				count( $ids['bookmarks'] ),
				count( $ids['messages'] ),
				count( $ids['stories'] ),
				count( $ids['ads'] )
			),
		) );
	}

	public function ajax_delete(): void {
		if ( ! check_ajax_referer( 'arshid6social_sample_data', 'nonce', false ) || ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		if ( ! get_option( 'arshid6social_sample_data_imported' ) ) {
			wp_send_json_error( array( 'message' => __( 'No sample data found to delete.', '6arshid social community' ) ) );
		}

		$this->delete();

		delete_option( 'arshid6social_sample_data_imported' );
		delete_option( 'arshid6social_sample_data_ids' );

		wp_send_json_success( array( 'message' => __( 'Sample data deleted successfully.', '6arshid social community' ) ) );
	}

	// ── Data arrays ───────────────────────────────────────────────────────────

	private function first_names(): array {
		return array(
			'James', 'Emma', 'Oliver', 'Sophia', 'William', 'Ava', 'Noah', 'Isabella',
			'Liam', 'Mia', 'Ethan', 'Charlotte', 'Lucas', 'Amelia', 'Mason', 'Harper',
			'Logan', 'Evelyn', 'Alexander', 'Abigail', 'Ryan', 'Emily', 'Daniel', 'Ella',
			'Henry',
		);
	}

	private function last_names(): array {
		return array(
			'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
			'Wilson', 'Taylor', 'Anderson', 'Thomas', 'Jackson', 'White', 'Harris',
			'Martin', 'Thompson', 'Young', 'Robinson', 'Lewis',
		);
	}

	private function activity_contents(): array {
		return array(
			'Just joined this awesome community! Excited to connect with everyone here.',
			'Beautiful morning for a run. Hit 5K in under 25 minutes — personal best!',
			'Anyone else obsessed with the new season? The plot twists are incredible.',
			'Working on a new side project. Startup life is exhausting but rewarding.',
			'Just finished reading Atomic Habits. Highly recommend it to everyone.',
			'Coffee and code — the perfect Saturday morning combo. ☕',
			'Grateful for an amazing team. We shipped the new feature today! 🚀',
			'The mountains are calling and I must go. Planning a hike this weekend.',
			'Quick tip: 10 minutes of journaling each morning changes everything.',
			'Trying to learn Spanish with Duolingo. Day 30 streak!',
			'Homemade pizza turned out better than expected. Dough made from scratch.',
			'That feeling when your code finally compiles. Pure bliss.',
			'Visited the art museum today. Some truly breathtaking exhibitions.',
			'Anyone have good book recommendations for productivity and focus?',
			'Sunsets from the rooftop never get old. Grateful for this view.',
			'Just adopted a rescue dog. Meet Max — the newest member of the family!',
			'Remote work tip: set a hard stop time. Work-life balance matters.',
			'Cooking is my therapy. Tried a new Thai recipe and nailed it.',
			'Networking event was surprisingly fun. Met some brilliant people tonight.',
			'Music is the best productivity hack. What are you listening to right now?',
			'Starting a 30-day fitness challenge. Who is joining me?',
			'Finally launched my portfolio website. Feedback welcome!',
			'Road trip to the coast this weekend. Can not wait for the fresh air.',
			'Three things I am thankful for today: health, friends, and good food.',
			'Late night debugging session. Found the bug — it was a missing semicolon.',
			'Nature photography is my new hobby. Captured this shot on my morning walk.',
			'Attended a fascinating talk on AI ethics. Lots to think about.',
			'Best decision I made this year: buying a standing desk.',
			'Weekend farmers market haul. Supporting local is always worth it.',
			'Woke up early to watch the sunrise. 100% recommend it at least once.',
		);
	}

	private function group_names(): array {
		return array(
			'Photography Enthusiasts', 'Web Developers Hub', 'Book Club', 'Fitness Fanatics',
			'Travel Junkies', 'Home Cooking', 'Movie Buffs', 'Entrepreneurs Network',
			'Music Lovers', 'Art & Creativity', 'Tech Talks', 'Digital Nomads',
			'Gaming Community', 'Yoga & Mindfulness', 'Startup Founders',
			'Data Science Crew', 'Writers Circle', 'Cycling Club', 'Language Exchange',
			'Investment & Finance', 'Pet Owners', 'Gardening Club', 'Chess Players',
			'Hiking Adventures', 'UI/UX Designers', 'Coffee Connoisseurs',
			'Science & Space', 'Sustainability & Eco Life', 'Remote Workers Unite',
			'Podcast Listeners', 'Stand-Up Comedy Fans', 'Anime Club',
			'Football Fanatics', 'Running Club', 'Creative Writing',
			'Blockchain & Crypto', 'Parenting Tips', 'Astronomy Buffs',
			'Architecture & Design', 'History Geeks', 'Health & Nutrition',
			'Street Photography', 'Self-Improvement', 'Career Growth',
			'Vintage & Thrift', '3D Printing Club', 'Machine Learning',
			'Board Games Night', 'Surfing Community', 'Fashion & Style',
		);
	}

	private function listing_titles(): array {
		return array(
			'iPhone 14 Pro Max 256GB', 'Samsung Galaxy S23 Ultra', 'MacBook Pro M3 14"',
			'Dell XPS 15 Laptop', 'Sony WH-1000XM5 Headphones', 'Canon EOS R50 Camera',
			'DJI Mini 3 Drone', 'Apple Watch Series 9', 'iPad Pro 12.9"',
			'Nintendo Switch OLED', 'Xbox Series X', 'PlayStation 5 Console',
			'4K OLED Smart TV 55"', 'Keychron Q1 Mechanical Keyboard', 'Logitech MX Master 3',
			'Bose QuietComfort 45', 'GoPro Hero 11', 'Kindle Paperwhite',
			'Samsung 49" Ultrawide Monitor', 'LG OLED C2 65"',
			'Trek Marlin 7 Mountain Bike', 'Specialized Road Bike 2023',
			'Leather Sofa 3-Seater', 'Standing Desk 160cm', 'Ergonomic Office Chair',
			'KitchenAid Stand Mixer', 'Nespresso Vertuo Machine', 'Vitamix E310 Blender',
			'Dyson V15 Detect Vacuum', 'Roomba i7 Robot Vacuum',
			'Vintage Leather Jacket M', 'Levi\'s 501 Jeans 32x32', 'Nike Air Max 270 US10',
			'Adidas Ultraboost 22 US9', 'Ray-Ban Wayfarer Sunglasses',
			'Canon EF 50mm f/1.8 Lens', 'Sony FE 24-70mm Lens',
			'Acoustic Guitar Yamaha F310', 'Roland FP-30X Digital Piano',
			'Lego Technic Bugatti Set', 'Pokemon Card Collection Binder',
			'Camping Tent 4-Person MSR', 'North Face Backpack 60L',
			'Patagonia Down Jacket L', 'Surfboard 7\'2 Funboard',
			'Road Bike Helmet Giro', 'Garmin Forerunner 955', 'Fitbit Charge 5',
			'Portable Projector XGIMI', 'Electric Scooter Xiaomi Pro 2',
		);
	}

	private function listing_descriptions(): array {
		return array(
			'In excellent condition, barely used. Comes with original box and all accessories.',
			'Selling because I upgraded. Works perfectly, no scratches or dents.',
			'Well maintained, minor wear on the exterior. Fully functional, great deal.',
			'Bought last year, used only a handful of times. Still under warranty.',
			'Great condition overall. Some light scratches on the bottom — not visible in use.',
		);
	}

	private function message_texts(): array {
		return array(
			'Hey! Just wanted to say hi and introduce myself. Loving this platform!',
			'Hi there! Do you have any tips for new members?',
			'Hello! I saw your post and thought it was really insightful.',
			'Hey, thanks for building this awesome community!',
			'Hi! Quick question — how do I update my profile picture?',
			'Hello! Just joined and already loving the vibe here.',
			'Hey! Would love to connect and maybe collaborate sometime.',
			'Hi there, I came across your profile and wanted to reach out.',
			'Hello! Great content you have been sharing lately.',
			'Hey! Is there an event coming up for community members?',
			'Hi, just checking in — how has your week been?',
			'Hello! I shared one of your posts with my friends. Really good stuff.',
			'Hey! Do you know where I can find the marketplace section?',
			'Hi! New here — looking forward to being part of this community.',
			'Hello! I would love to hear your thoughts on the latest tech trends.',
			'Hey there! Hope you are having a great day.',
			'Hi! Is there a way to create a group for our local meetup?',
			'Hello! I noticed we have a lot of interests in common.',
			'Hey! Just wanted to drop a quick thank you note.',
			'Hi there! Could you recommend any good resources for beginners?',
			'Hello! Love what you guys have built here. Keep it up!',
			'Hey! I just posted something new — would love your feedback.',
			'Hi! Do you guys plan any live events or webinars?',
			'Hello! Saw your activity post and wanted to connect.',
			'Hey! What is the best way to get started on this platform?',
		);
	}

	private function story_texts(): array {
		return array(
			'Good morning! Starting the day with positive vibes. ☀️',
			'Just hit a personal milestone today. Hard work pays off!',
			'Coffee first, everything else second. ☕',
			'Weekend mode: ON. Finally some time to unwind.',
			'Grateful for every small win. Keep pushing forward!',
			'New week, new goals. Let\'s make it count!',
			'Loving the community here. So much talent and positivity.',
			'Reminder: take a break and breathe. You deserve it.',
			'Explored a new part of the city today. So much beauty around us.',
			'Just finished an amazing book. 10/10 would recommend.',
			'Team lunch today. Good food, great people. 🍕',
			'Throwback to last summer\'s road trip. Miss those vibes.',
			'Today\'s workout was tough but totally worth it. 💪',
			'Cooking experiment of the day: homemade ramen. Turned out great!',
			'Sunset views never get old. Take a moment to appreciate them.',
			'Learning something new every day. Growth is a lifestyle.',
			'Checked off three things from my to-do list. Productive day!',
			'Spent the morning journaling. Highly recommend for mental clarity.',
			'Found a new coffee shop. Might be my new office.',
			'Late night thoughts: the best ideas come when you least expect them.',
			'Big things are coming. Stay tuned!',
			'Random act of kindness made my whole day. Pass it on.',
			'Nature walk cleared my head completely. Go touch grass!',
			'Trying a new morning routine this week. So far so good.',
			'Friday feeling! Time to celebrate small victories.',
			'Just signed up for an online course. Never stop learning.',
			'Mindset shift: challenges are just opportunities in disguise.',
			'Five years ago I would not believe where I am now. Keep going.',
			'Spent quality time with family today. That is what it is all about.',
			'End of day reflection: it was a good one. See you tomorrow!',
		);
	}

	private function story_bg_colors(): array {
		return array(
			'#2563eb', '#7c3aed', '#db2777', '#dc2626', '#ea580c',
			'#ca8a04', '#16a34a', '#0891b2', '#4f46e5', '#be123c',
		);
	}

	// ── Helper: table existence check ─────────────────────────────────────────

	private function table_exists( string $table ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	// ── Import ────────────────────────────────────────────────────────────────

	private function import(): array {
		global $wpdb;

		$ids = array(
			'users'         => array(),
			'activities'    => array(),
			'notifications' => array(),
			'listings'      => array(),
			'groups'        => array(),
			'bookmarks'     => array(),
			'messages'      => array(),
			'stories'       => array(),
			'ads'           => array(),
		);

		$admin_id    = get_current_user_id();
		$first_names = $this->first_names();
		$last_names  = $this->last_names();

		// ── 50 Users ──────────────────────────────────────────────────────────
		$user_ids = array();

		for ( $i = 1; $i <= 50; $i++ ) {
			$fn    = $first_names[ ( $i - 1 ) % count( $first_names ) ];
			$ln    = $last_names[ ( $i - 1 ) % count( $last_names ) ];
			$login = 'sample_user_' . str_pad( (string) $i, 2, '0', STR_PAD_LEFT );
			$email = 'sample_user_' . $i . '@ARSHID6SOCIAL.example';

			if ( username_exists( $login ) ) {
				$existing = get_user_by( 'login', $login );
				if ( $existing ) {
					$user_ids[]     = $existing->ID;
					$ids['users'][] = $existing->ID;
				}
				continue;
			}

			$uid = wp_create_user( $login, 'SampleUser@123', $email );
			if ( is_wp_error( $uid ) ) {
				continue;
			}

			wp_update_user( array(
				'ID'           => $uid,
				'display_name' => $fn . ' ' . $ln,
				'first_name'   => $fn,
				'last_name'    => $ln,
			) );
			add_user_meta( $uid, '_arshid6social_sample', '1' );

			$user_ids[]     = $uid;
			$ids['users'][] = $uid;
		}

		// ── 50 Activities ─────────────────────────────────────────────────────
		$contents      = $this->activity_contents();
		$activity_ids  = array();
		$date_offset_m = 0;

		for ( $i = 0; $i < 50; $i++ ) {
			$author_id = ! empty( $user_ids ) ? $user_ids[ $i % count( $user_ids ) ] : $admin_id;
			$content   = $contents[ $i % count( $contents ) ];
			$date      = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $date_offset_m . ' minutes', (int) current_time( 'timestamp' ) ) );
			$date_offset_m += 30;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->prefix . 'sn_activity',
				array(
					'user_id'           => $author_id,
					'component'         => 'activity',
					'type'              => 'activity_update',
					'action'            => '',
					'content'           => $content,
					'primary_link'      => '',
					'item_id'           => 0,
					'secondary_item_id' => 0,
					'date_recorded'     => $date,
					'hide_sitewide'     => 0,
					'is_spam'           => 0,
					'privacy'           => 'public',
					'uid'               => substr( bin2hex( random_bytes( 8 ) ), 0, 12 ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s' )
			);

			$activity_id = (int) $wpdb->insert_id;
			if ( $activity_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->insert(
					$wpdb->prefix . 'sn_activity_meta',
					array( 'activity_id' => $activity_id, 'meta_key' => '_arshid6social_sample', 'meta_value' => '1' ),
					array( '%d', '%s', '%s' )
				);
				$activity_ids[]      = $activity_id;
				$ids['activities'][] = $activity_id;
			}
		}

		// ── 100 Notifications for admin ───────────────────────────────────────
		$notif_types  = array(
			array( 'component' => 'activity', 'action' => 'activity_reaction' ),
			array( 'component' => 'activity', 'action' => 'activity_comment' ),
			array( 'component' => 'friends',  'action' => 'friend_request' ),
			array( 'component' => 'friends',  'action' => 'new_follower' ),
			array( 'component' => 'activity', 'action' => 'activity_mention' ),
		);
		$notif_offset = 0;

		for ( $i = 0; $i < 100; $i++ ) {
			$type       = $notif_types[ $i % count( $notif_types ) ];
			$sender_id  = ! empty( $user_ids ) ? $user_ids[ $i % count( $user_ids ) ] : 1;
			$act_id     = ! empty( $activity_ids ) ? $activity_ids[ $i % count( $activity_ids ) ] : 0;
			$date       = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $notif_offset . ' minutes', (int) current_time( 'timestamp' ) ) );
			$notif_offset += 15;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->prefix . 'sn_notifications',
				array(
					'user_id'           => $admin_id,
					'item_id'           => $sender_id,       // sender user ID
					'secondary_item_id' => $act_id,          // related activity/object ID
					'component_name'    => $type['component'],
					'component_action'  => $type['action'],
					'date_notified'     => $date,
					'is_new'            => 1,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%d' )
			);

			$notif_id = (int) $wpdb->insert_id;
			if ( $notif_id ) {
				$ids['notifications'][] = $notif_id;
			}
		}

		// ── 50 Marketplace Listings ───────────────────────────────────────────
		$listings_table = $wpdb->prefix . 'arshid6social_listings';
		if ( $this->table_exists( $listings_table ) ) {
			$listing_titles = $this->listing_titles();
			$listing_descs  = $this->listing_descriptions();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$cat_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}arshid6social_categories ORDER BY id ASC LIMIT 1" );

			for ( $i = 0; $i < 50; $i++ ) {
				$seller_id = ! empty( $user_ids ) ? $user_ids[ $i % count( $user_ids ) ] : $admin_id;
				$date      = gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $i * 2 ) . ' hours', (int) current_time( 'timestamp' ) ) );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->insert(
					$listings_table,
					array(
						'uid'            => substr( bin2hex( random_bytes( 8 ) ), 0, 12 ),
						'seller_id'      => $seller_id,
						'title'          => $listing_titles[ $i % count( $listing_titles ) ],
						'description'    => $listing_descs[ $i % count( $listing_descs ) ],
						'price'          => (float) wp_rand( 20, 2000 ),
						'currency'       => 'USD',
						'item_condition' => ( 0 === $i % 3 ) ? 'new' : 'used',
						'category_id'    => $cat_id,
						'status'         => 'active',
						'is_negotiable'  => $i % 2,
						'is_free'        => 0,
						'views'          => wp_rand( 0, 500 ),
						'created_at'     => $date,
						'updated_at'     => $date,
					),
					array( '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s' )
				);

				$listing_id = (int) $wpdb->insert_id;
				if ( $listing_id ) {
					$ids['listings'][] = $listing_id;
				}
			}
		}

		// ── 50 Groups ─────────────────────────────────────────────────────────
		$group_names  = $this->group_names();
		$statuses     = array( 'public', 'public', 'private', 'public', 'hidden' );
		$group_offset = 0;

		for ( $i = 0; $i < 50; $i++ ) {
			$creator_id = ! empty( $user_ids ) ? $user_ids[ $i % count( $user_ids ) ] : $admin_id;
			$name       = $group_names[ $i % count( $group_names ) ];
			$slug       = sanitize_title( $name ) . '-sample-' . ( $i + 1 );
			$status     = $statuses[ $i % 5 ];
			$date       = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $group_offset . ' hours', (int) current_time( 'timestamp' ) ) );
			$group_offset += 3;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->prefix . 'sn_groups',
				array(
					'creator_id'   => $creator_id,
					'name'         => $name,
					'slug'         => $slug,
					'description'  => 'A community group for ' . strtolower( $name ) . '. Everyone is welcome!',
					'status'       => $status,
					'is_suspended' => 0,
					'enable_forum' => 0,
					'date_created' => $date,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
			);

			$group_id = (int) $wpdb->insert_id;
			if ( $group_id ) {
				$ids['groups'][] = $group_id;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->insert(
					$wpdb->prefix . 'sn_groups_members',
					array(
						'group_id'      => $group_id,
						'user_id'       => $creator_id,
						'inviter_id'    => 0,
						'is_admin'      => 1,
						'is_mod'        => 0,
						'user_title'    => '',
						'date_modified' => $date,
						'comments'      => '',
						'is_confirmed'  => 1,
						'is_banned'     => 0,
						'invite_sent'   => 0,
					),
					array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d' )
				);
			}
		}

		// ── 50 Saved Posts (Bookmarks) for admin ──────────────────────────────
		for ( $i = 0; $i < 50; $i++ ) {
			if ( empty( $activity_ids ) ) {
				break;
			}

			$activity_id = $activity_ids[ $i % count( $activity_ids ) ];
			$date        = gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $i * 20 ) . ' minutes', (int) current_time( 'timestamp' ) ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->insert(
				$wpdb->prefix . 'sn_bookmarks',
				array(
					'user_id'     => $admin_id,
					'object_id'   => $activity_id,
					'object_type' => 'activity',
					'created_at'  => $date,
				),
				array( '%d', '%d', '%s', '%s' )
			);

			if ( $result && $wpdb->insert_id ) {
				$ids['bookmarks'][] = (int) $wpdb->insert_id;
			}
		}

		// ── 50 Message Threads to admin ───────────────────────────────────────
		$msg_texts  = $this->message_texts();
		$msg_offset = 0;

		for ( $i = 0; $i < 50; $i++ ) {
			$sender_id = ! empty( $user_ids ) ? $user_ids[ $i % count( $user_ids ) ] : 1;
			$text      = $msg_texts[ $i % count( $msg_texts ) ];
			$date      = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $msg_offset . ' minutes', (int) current_time( 'timestamp' ) ) );
			$msg_offset += 20;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->prefix . 'sn_messages_threads',
				array(
					'uniqid'       => wp_generate_uuid4(),
					'subject'      => '',
					'is_group'     => 0,
					'date_created' => $date,
				),
				array( '%s', '%s', '%d', '%s' )
			);
			$thread_id = (int) $wpdb->insert_id;
			if ( ! $thread_id ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->prefix . 'sn_messages_recipients',
				array( 'thread_id' => $thread_id, 'user_id' => $sender_id, 'unread_count' => 0, 'sender_only' => 0, 'is_deleted' => 0 ),
				array( '%d', '%d', '%d', '%d', '%d' )
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->prefix . 'sn_messages_recipients',
				array( 'thread_id' => $thread_id, 'user_id' => $admin_id, 'unread_count' => 1, 'sender_only' => 0, 'is_deleted' => 0 ),
				array( '%d', '%d', '%d', '%d', '%d' )
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->prefix . 'sn_messages',
				array(
					'thread_id'  => $thread_id,
					'sender_id'  => $sender_id,
					'message'    => $text,
					'date_sent'  => $date,
					'is_deleted' => 0,
					'is_edited'  => 0,
				),
				array( '%d', '%d', '%s', '%s', '%d', '%d' )
			);

			$msg_id = (int) $wpdb->insert_id;
			if ( $msg_id ) {
				$ids['messages'][] = $msg_id;
			}
		}

		// ── 30 Stories (text only) ────────────────────────────────────────────
		$stories_table      = $wpdb->prefix . 'sn_stories';
		$story_items_table  = $wpdb->prefix . 'sn_story_items';
		$expiry_hours       = (int) get_option( 'arshid6social_stories_expiry_hours', 24 );

		if ( $this->table_exists( $stories_table ) ) {
			$story_texts  = $this->story_texts();
			$bg_colors    = $this->story_bg_colors();
			$story_offset = 0;

			for ( $i = 0; $i < 30; $i++ ) {
				$author_id  = ! empty( $user_ids ) ? $user_ids[ $i % count( $user_ids ) ] : $admin_id;
				$created_at = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $story_offset . ' minutes', (int) current_time( 'timestamp' ) ) );
				$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $expiry_hours . ' hours', strtotime( $created_at ) ) );
				$story_offset += 45;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->insert(
					$stories_table,
					array(
						'user_id'    => $author_id,
						'privacy'    => 'public',
						'close_friends' => 0,
						'created_at' => $created_at,
						'expires_at' => $expires_at,
					),
					array( '%d', '%s', '%d', '%s', '%s' )
				);

				$story_id = (int) $wpdb->insert_id;
				if ( ! $story_id ) {
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->insert(
					$story_items_table,
					array(
						'story_id'     => $story_id,
						'media_type'   => 'text',
						'file_url'     => '',
						'file_path'    => '',
						'text_content' => $story_texts[ $i % count( $story_texts ) ],
						'bg_color'     => $bg_colors[ $i % count( $bg_colors ) ],
						'sort_order'   => 0,
						'duration'     => 5,
					),
					array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
				);

				$ids['stories'][] = $story_id;
			}
		}

		// ── 1 Ad ─────────────────────────────────────────────────────────────
		$ads_table = $wpdb->prefix . 'sn_ads';
		if ( $this->table_exists( $ads_table ) ) {
			$date = current_time( 'mysql' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$ads_table,
				array(
					'title'         => 'Welcome to 6Arshid Social Community — Sample Ad',
					'ad_type'       => 'image',
					'file_url'      => '',
					'click_url'     => '',
					'js_code'       => '',
					'placement'     => 'both',
					'every_n_posts' => 5,
					'impressions'   => 0,
					'clicks'        => 0,
					'status'        => 'active',
					'start_date'    => null,
					'end_date'      => null,
					'date_created'  => $date,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', null, null, '%s' )
			);

			$ad_id = (int) $wpdb->insert_id;
			if ( $ad_id ) {
				$ids['ads'][] = $ad_id;
			}
		}

		return $ids;
	}

	// ── Delete ────────────────────────────────────────────────────────────────

	private function delete(): void {
		global $wpdb;

		// Delete sample users.
		$sample_users = get_users( array(
			'meta_key'   => '_arshid6social_sample',
			'meta_value' => '1',
			'fields'     => 'ids',
			'number'     => -1,
		) );
		foreach ( $sample_users as $uid ) {
			wp_delete_user( (int) $uid );
		}

		// Delete sample activities (found via meta).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$activity_ids = $wpdb->get_col(
			"SELECT activity_id FROM {$wpdb->prefix}sn_activity_meta WHERE meta_key = '_arshid6social_sample' AND meta_value = '1'"
		);
		if ( ! empty( $activity_ids ) ) {
			$activity_ids = array_map( 'intval', $activity_ids );
			$phs          = implode( ',', array_fill( 0, count( $activity_ids ), '%d' ) );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_activity WHERE id IN ($phs)", ...$activity_ids ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_activity_meta WHERE activity_id IN ($phs)", ...$activity_ids ) );
			// phpcs:enable
		}

		// Delete by stored IDs.
		$stored = (array) get_option( 'arshid6social_sample_data_ids', array() );

		$this->delete_by_ids( $stored['notifications'] ?? array(), $wpdb->prefix . 'sn_notifications' );
		$this->delete_by_ids( $stored['bookmarks'] ?? array(), $wpdb->prefix . 'sn_bookmarks' );
		$this->delete_by_ids( $stored['ads'] ?? array(), $wpdb->prefix . 'sn_ads' );

		if ( ! empty( $stored['listings'] ) && $this->table_exists( $wpdb->prefix . 'arshid6social_listings' ) ) {
			$this->delete_by_ids( $stored['listings'], $wpdb->prefix . 'arshid6social_listings' );
		}

		if ( ! empty( $stored['groups'] ) ) {
			$ids = array_map( 'intval', $stored['groups'] );
			$phs = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_groups WHERE id IN ($phs)", ...$ids ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_groups_members WHERE group_id IN ($phs)", ...$ids ) );
			// phpcs:enable
		}

		if ( ! empty( $stored['messages'] ) ) {
			$msg_ids = array_map( 'intval', $stored['messages'] );
			$phs     = implode( ',', array_fill( 0, count( $msg_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
			$thread_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT thread_id FROM {$wpdb->prefix}sn_messages WHERE id IN ($phs)", ...$msg_ids ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_messages WHERE id IN ($phs)", ...$msg_ids ) );
			// phpcs:enable

			if ( ! empty( $thread_ids ) ) {
				$thread_ids = array_map( 'intval', $thread_ids );
				$tphs       = implode( ',', array_fill( 0, count( $thread_ids ), '%d' ) );
				// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_messages_threads WHERE id IN ($tphs)", ...$thread_ids ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_messages_recipients WHERE thread_id IN ($tphs)", ...$thread_ids ) );
				// phpcs:enable
			}
		}

		if ( ! empty( $stored['stories'] ) && $this->table_exists( $wpdb->prefix . 'sn_stories' ) ) {
			$ids  = array_map( 'intval', $stored['stories'] );
			$phs  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_story_items WHERE story_id IN ($phs)", ...$ids ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_stories WHERE id IN ($phs)", ...$ids ) );
			// phpcs:enable
		}
	}

	private function delete_by_ids( array $raw_ids, string $table ): void {
		if ( empty( $raw_ids ) ) {
			return;
		}
		global $wpdb;
		$ids = array_map( 'intval', $raw_ids );
		$phs = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE id IN ($phs)", ...$ids ) );
		// phpcs:enable
	}
}
