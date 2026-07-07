<?php
namespace Arshid6Social\Components\Monetization;

/**
 * Database schema and incremental migrations for the Monetization component.
 *
 * All tables use the 'sixarshidsc_' prefix. No raw bank/IBAN data is stored here —
 * only Stripe Connect account references; bank details live with Stripe.
 *
 * @package Arshid6Social\Components\Monetization
 */

defined( 'ABSPATH' ) || exit;

class Monetization_DB {

	/** Increment this constant to trigger a schema migration. */
	const VERSION        = '1.0.1';
	const VERSION_OPTION = 'sixarshidsc_db_version';

	/**
	 * Runs on every request: creates or updates tables when the stored schema
	 * version is behind the current VERSION constant. The version check makes
	 * this a cheap no-op once the schema is current.
	 */
	public static function maybe_upgrade(): void {
		if ( version_compare( (string) get_option( self::VERSION_OPTION, '0' ), self::VERSION, '>=' ) ) {
			return;
		}
		self::create_tables();
		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * Creates or updates all Monetization tables via dbDelta().
	 */
	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		/**
		 * Creator accounts — stores only the Stripe Connect account reference.
		 * No raw IBAN or bank details are ever written here; those live with Stripe.
		 */
		dbDelta(
			"CREATE TABLE {$p}sixarshidsc_creator_accounts (
				id                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id            bigint(20) UNSIGNED NOT NULL,
				gateway            varchar(64)  NOT NULL DEFAULT 'stripe_connect',
				connect_account_id varchar(128) NOT NULL DEFAULT '',
				status             varchar(32)  NOT NULL DEFAULT 'pending',
				payouts_enabled    tinyint(1)   NOT NULL DEFAULT 0,
				charges_enabled    tinyint(1)   NOT NULL DEFAULT 0,
				details_submitted  tinyint(1)   NOT NULL DEFAULT 0,
				created_at         datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at         datetime     NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY user_id (user_id),
				KEY status (status)
			) $charset;"
		);

		/**
		 * Subscription plans created by creators (monthly recurring).
		 */
		dbDelta(
			"CREATE TABLE {$p}sixarshidsc_sub_plans (
				id              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				creator_id      bigint(20) UNSIGNED NOT NULL,
				name            varchar(128) NOT NULL DEFAULT '',
				price           decimal(10,2) NOT NULL DEFAULT 0.00,
				currency        varchar(8)   NOT NULL DEFAULT 'USD',
				interval_type   varchar(16)  NOT NULL DEFAULT 'month',
				trial_days      smallint(5) UNSIGNED NOT NULL DEFAULT 0,
				perks           text         NOT NULL,
				stripe_price_id varchar(128) NOT NULL DEFAULT '',
				active          tinyint(1)   NOT NULL DEFAULT 1,
				created_at      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY creator_id (creator_id),
				KEY active (active)
			) $charset;"
		);

		/**
		 * Active/cancelled subscriptions. Status driven by Stripe webhooks.
		 * Index on (creator_id, status) powers the creator dashboard query.
		 */
		dbDelta(
			"CREATE TABLE {$p}sixarshidsc_subscriptions (
				id                   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				subscriber_id        bigint(20) UNSIGNED NOT NULL,
				creator_id           bigint(20) UNSIGNED NOT NULL,
				plan_id              bigint(20) UNSIGNED NOT NULL,
				gateway_sub_id       varchar(128) NOT NULL DEFAULT '',
				status               varchar(32)  NOT NULL DEFAULT 'active',
				current_period_end   datetime     NULL,
				cancel_at_period_end tinyint(1)   NOT NULL DEFAULT 0,
				created_at           datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at           datetime     NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY subscriber_id (subscriber_id),
				KEY creator_status (creator_id, status),
				KEY gateway_sub_id (gateway_sub_id)
			) $charset;"
		);

		/**
		 * Pay-per-view purchases. UNIQUE (buyer_id, activity_id) prevents double-purchase.
		 */
		dbDelta(
			"CREATE TABLE {$p}sixarshidsc_purchases (
				id                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				buyer_id           bigint(20) UNSIGNED NOT NULL,
				activity_id        bigint(20) UNSIGNED NOT NULL,
				creator_id         bigint(20) UNSIGNED NOT NULL,
				gateway_payment_id varchar(128) NOT NULL DEFAULT '',
				amount             decimal(10,2) NOT NULL DEFAULT 0.00,
				fee                decimal(10,2) NOT NULL DEFAULT 0.00,
				currency           varchar(8)   NOT NULL DEFAULT 'USD',
				status             varchar(32)  NOT NULL DEFAULT 'pending',
				created_at         datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY buyer_activity (buyer_id, activity_id),
				KEY creator_id (creator_id),
				KEY gateway_payment_id (gateway_payment_id)
			) $charset;"
		);

		/**
		 * Entitlements — the fast-path access-control table.
		 *
		 * object_type = 'creator_sub' → object_id = creator user_id (subscription grants)
		 * object_type = 'activity'    → object_id = activity id     (pay-per-view grants)
		 *
		 * expires_at NULL means permanent (pay-per-view).
		 * UNIQUE (user_id, object_type, object_id) so upserts are safe.
		 */
		dbDelta(
			"CREATE TABLE {$p}sixarshidsc_entitlements (
				id          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id     bigint(20) UNSIGNED NOT NULL,
				object_type varchar(32) NOT NULL DEFAULT 'creator_sub',
				object_id   bigint(20) UNSIGNED NOT NULL,
				source      varchar(16) NOT NULL DEFAULT 'sub',
				expires_at  datetime    NULL,
				created_at  datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY user_object (user_id, object_type, object_id),
				KEY expires_at (expires_at)
			) $charset;"
		);

		/**
		 * Transactions — source of truth, reconciled from gateway webhooks only.
		 * Client-side success redirects never write here.
		 */
		dbDelta(
			"CREATE TABLE {$p}sixarshidsc_transactions (
				id           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				type         varchar(32)  NOT NULL DEFAULT 'subscription',
				payer_id     bigint(20) UNSIGNED NOT NULL DEFAULT 0,
				creator_id   bigint(20) UNSIGNED NOT NULL DEFAULT 0,
				amount       decimal(10,2) NOT NULL DEFAULT 0.00,
				platform_fee decimal(10,2) NOT NULL DEFAULT 0.00,
				currency     varchar(8)   NOT NULL DEFAULT 'USD',
				gateway      varchar(64)  NOT NULL DEFAULT 'stripe_connect',
				gateway_ref  varchar(128) NOT NULL DEFAULT '',
				status       varchar(32)  NOT NULL DEFAULT 'pending',
				raw_event_id bigint(20) UNSIGNED NULL,
				created_at   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY payer_id (payer_id),
				KEY creator_id (creator_id),
				KEY gateway_ref (gateway_ref),
				KEY status (status)
			) $charset;"
		);

		/**
		 * Webhook events — idempotency guard.
		 * UNIQUE (gateway, event_id) prevents double-processing the same event.
		 */
		dbDelta(
			"CREATE TABLE {$p}sixarshidsc_webhook_events (
				id           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				gateway      varchar(64)  NOT NULL DEFAULT 'stripe_connect',
				event_id     varchar(128) NOT NULL DEFAULT '',
				type         varchar(128) NOT NULL DEFAULT '',
				payload_hash varchar(64)  NOT NULL DEFAULT '',
				processed_at datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY gateway_event (gateway, event_id)
			) $charset;"
		);
	}

	/**
	 * Drops all Monetization tables. Called by the uninstall routine when the
	 * admin has opted-in to data deletion on uninstall.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$tables = array(
			'sixarshidsc_webhook_events',
			'sixarshidsc_transactions',
			'sixarshidsc_entitlements',
			'sixarshidsc_purchases',
			'sixarshidsc_subscriptions',
			'sixarshidsc_sub_plans',
			'sixarshidsc_creator_accounts',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		delete_option( self::VERSION_OPTION );
	}
}
