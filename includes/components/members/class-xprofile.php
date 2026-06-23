<?php
namespace Arshid6Social\Components\Members;

/**
 * Extended profile (xProfile) field management.
 *
 * @package Arshid6Social\Components\Members
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class XProfile
 *
 * Manages xProfile field groups, fields, and per-user data.
 * All queries use $wpdb->prepare() — zero raw SQL with user input.
 */
class XProfile {

	/**
	 * Returns all xProfile field groups with their fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_groups(): array {
		global $wpdb;

		$cache_key = 'xprofile_groups';
		$found     = false;
		$groups    = \Arshid6Social\Cache::get( $cache_key, $found );

		if ( $found ) {
			return $groups;
		}

		$groups = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_xprofile_groups ORDER BY group_order ASC",
			ARRAY_A
		);

		foreach ( $groups as &$group ) {
			$group['fields'] = $this->get_fields_in_group( (int) $group['id'] );
		}

		\Arshid6Social\Cache::set( $cache_key, $groups, 300 );

		return $groups ?: array();
	}

	/**
	 * Returns all fields in a group.
	 *
	 * @param int $group_id Group ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_fields_in_group( int $group_id ): array {
		global $wpdb;

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sn_xprofile_fields WHERE group_id = %d AND parent_id = 0 ORDER BY field_order ASC",
				$group_id
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Returns a single field's stored value for a user.
	 *
	 * @param int        $user_id  User ID.
	 * @param int|string $field    Field ID (int) or field name (string).
	 * @return string
	 */
	public function get_field_value( int $user_id, int|string $field ): string {
		global $wpdb;

		if ( is_string( $field ) ) {
			$field_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}sn_xprofile_fields WHERE name = %s LIMIT 1",
					$field
				)
			);
		} else {
			$field_id = $field;
		}

		if ( ! $field_id ) {
			return '';
		}

		$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT value FROM {$wpdb->prefix}sn_xprofile_data WHERE field_id = %d AND user_id = %d",
				$field_id,
				$user_id
			)
		);

		return $value ?: '';
	}

	/**
	 * Returns all profile field values for a user, keyed by field name.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, string>
	 */
	public function get_all_field_values( int $user_id ): array {
		global $wpdb;

		$cache_key = "xprofile_user_{$user_id}";
		$found     = false;
		$cached    = \Arshid6Social\Cache::get( $cache_key, $found );

		if ( $found ) {
			return $cached;
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT f.name, d.value
				 FROM {$wpdb->prefix}sn_xprofile_data d
				 JOIN {$wpdb->prefix}sn_xprofile_fields f ON f.id = d.field_id
				 WHERE d.user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		$data = array();
		foreach ( $rows as $row ) {
			$data[ $row['name'] ] = $row['value'];
		}

		\Arshid6Social\Cache::set( $cache_key, $data, 300 );

		return $data;
	}

	/**
	 * Saves a single field value.
	 *
	 * @param int    $user_id  User ID.
	 * @param int    $field_id Field ID.
	 * @param string $value    Raw value (will be sanitized here).
	 */
	public function save_field_value( int $user_id, int $field_id, string $value ): void {
		global $wpdb;

		$field = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sn_xprofile_fields WHERE id = %d", $field_id ),
			ARRAY_A
		);

		if ( ! $field ) {
			return;
		}

		$value = $this->sanitize_field_value( $field['type'], $value );

		$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sn_xprofile_data WHERE field_id = %d AND user_id = %d",
				$field_id,
				$user_id
			)
		);

		if ( $existing ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_xprofile_data',
				array(
					'value'        => $value,
					'last_updated' => current_time( 'mysql' ),
				),
				array(
					'field_id' => $field_id,
					'user_id'  => $user_id,
				),
				array( '%s', '%s' ),
				array( '%d', '%d' )
			);
		} else {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_xprofile_data',
				array(
					'field_id'     => $field_id,
					'user_id'      => $user_id,
					'value'        => $value,
					'last_updated' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}

		\Arshid6Social\Cache::delete( "xprofile_user_{$user_id}" );
	}

	/**
	 * Batch-saves profile field data submitted from the profile edit form.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $fields  Raw POST data array keyed by field_id.
	 * @return array<int, string>  Validation errors keyed by field_id, or empty on success.
	 */
	public function save_profile_data( int $user_id, array $fields ): array {
		global $wpdb;

		$errors = array();

		foreach ( $fields as $field_id => $value ) {
			$field_id = absint( $field_id );
			if ( ! $field_id ) {
				continue;
			}

			$field = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sn_xprofile_fields WHERE id = %d", $field_id ),
				ARRAY_A
			);

			if ( ! $field ) {
				continue;
			}

			$value = $this->sanitize_field_value( $field['type'], (string) $value );

			if ( $field['is_required'] && '' === $value ) {
				/* translators: %s: field name */
				$errors[ $field_id ] = sprintf( __( '%s is required.', 'social-network-6' ), $field['name'] );
				continue;
			}

			$this->save_field_value( $user_id, $field_id, $value );
		}

		// Sync first-group Name field back to wp_users.display_name.
		$name_value = $this->get_field_value( $user_id, 'Name' );
		if ( $name_value ) {
			wp_update_user( array( 'ID' => $user_id, 'display_name' => $name_value ) );
		}

		return $errors;
	}

	/**
	 * Sanitizes a field value based on field type.
	 *
	 * @param string $type  Field type slug.
	 * @param string $value Raw value.
	 * @return string Sanitized value.
	 */
	private function sanitize_field_value( string $type, string $value ): string {
		return match ( $type ) {
			'textarea'  => wp_kses_post( $value ),
			'url'       => esc_url_raw( $value ),
			'email'     => sanitize_email( $value ),
			'number'    => (string) absint( $value ),
			'date'      => sanitize_text_field( $value ),
			'checkbox'  => in_array( $value, array( '0', '1', 'yes', 'no' ), true ) ? $value : '0',
			default     => sanitize_text_field( $value ),
		};
	}

	/**
	 * Syncs a user's WP display_name into the xProfile Name field.
	 *
	 * @param int $user_id Updated user ID.
	 */
	public function sync_display_name( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( $user ) {
			$this->save_field_value( $user_id, 1, $user->display_name );
		}
	}

	/**
	 * GDPR data exporter callback.
	 *
	 * @param string $email    User email address.
	 * @param int    $page     Pagination page.
	 * @return array<string, mixed>
	 */
	public function export_data( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}

		$fields_data = $this->get_all_field_values( $user->ID );

		$data = array();
		foreach ( $fields_data as $name => $value ) {
			$data[] = array(
				'name'  => sanitize_text_field( $name ),
				'value' => wp_kses_post( $value ),
			);
		}

		return array(
			'data' => array(
				array(
					'group_id'          => 'arshid6social-profile',
					'group_label'       => __( 'Social Network Profile', 'social-network-6' ),
					'item_id'           => 'arshid6social-profile-' . $user->ID,
					'data'              => $data,
				),
			),
			'done' => true,
		);
	}

	/**
	 * GDPR data eraser callback.
	 *
	 * @param string $email User email address.
	 * @param int    $page  Pagination page.
	 * @return array<string, mixed>
	 */
	public function erase_data( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}

		global $wpdb;

		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_xprofile_data',
			array( 'user_id' => $user->ID ),
			array( '%d' )
		);

		\Arshid6Social\Cache::delete( "xprofile_user_{$user->ID}" );

		return array(
			'items_removed'  => (bool) $deleted,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
