<?php
namespace Arshid6Social\Components\Monetization;

/**
 * Paid Content & Creator Subscriptions — component bootstrap.
 *
 * Instantiated by Plugin::load_components() when 'sixarshidsc_enabled' is true.
 * The Monetization_Settings class is always loaded in the admin (regardless of
 * this toggle) so the settings tab is always reachable.
 *
 * Build order:
 *   Step 1  — this file + crypto + DB + settings tab  ← current
 *   Step 2  — abstract gateway layer + Stripe Connect implementation
 *   Step 3  — signature-verified webhook handler + transaction reconciliation
 *   Step 4  — creator onboarding, subscription plans, creator dashboard
 *   Step 5  — 'paid' activity privacy level + sixarshidsc_user_can_view_paid()
 *   Step 6  — subscribe + pay-per-view flows + subscriber management
 *   Step 7  — admin transactions / payouts / subscriptions dashboards + refunds
 *   Step 8  — notifications + verification gating + block awareness
 *
 * @package Arshid6Social\Components\Monetization
 */

defined( 'ABSPATH' ) || exit;

class Monetization {

	/** @var Paid_Activity */
	private Paid_Activity $paid_activity;

	/** @var Monetization_Webhook */
	private Monetization_Webhook $webhook;

	public function __construct() {
		// Ensure DB tables exist (cheap version-check no-op when already current).
		try {
			Monetization_DB::maybe_upgrade();
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ARSHID6SOCIAL Monetization] DB upgrade failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}

		// Creator payout settings section + transaction list on member settings page.
		new Creator_Settings();

		// Paid activity enforcement (locks non-entitled viewers out of paid posts).
		$this->paid_activity = new Paid_Activity();

		// Webhook handler (registers its route on rest_api_init via Plugin).
		$this->webhook = new Monetization_Webhook();
	}

	/**
	 * Called by Plugin::register_rest_routes() on rest_api_init.
	 */
	public function register_rest_routes(): void {
		$this->paid_activity->register_rest_routes();
		$this->webhook->register_rest_routes();
	}
}
