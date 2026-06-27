<?php
namespace Arshid6Social\Components\Monetization;

/**
 * Admin settings tab for the Paid Content & Creator Subscriptions component.
 *
 * Registers all sixarshidsc_* options under the 'arshid6social_monetization' group
 * so the core admin-settings form picks it up automatically when the
 * 'monetization' tab is active.
 *
 * Sensitive keys (Stripe secret + webhook secret) are encrypted via
 * Monetization_Crypto before being stored in wp_options.
 *
 * @package Arshid6Social\Components\Monetization
 */

defined( 'ABSPATH' ) || exit;

class Monetization_Settings {

	/** WP Settings API option group — must match 'ARSHID6SOCIAL_' . $tab_key. */
	const OPTION_GROUP = 'arshid6social_monetization';

	public function __construct() {
		// Ensure tables exist whenever an admin loads any page.
		// Wrapped in try-catch so a DB error never causes a white screen.
		try {
			Monetization_DB::maybe_upgrade();
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ARSHID6SOCIAL Monetization] DB upgrade failed: ' . $e->getMessage() );
			}
		}

		add_action( 'admin_init',                                array( $this, 'register_settings' ) );
		add_action( 'arshid6social_settings_tab_monetization',  array( $this, 'render' ) );
		add_action( 'admin_enqueue_scripts',                     array( $this, 'enqueue_tab_styles' ) );

		// Boot creator settings so the admin IBAN payout panel is always shown
		// in the Monetization tab (priority 20, after main settings form).
		// Creator_Settings has a static guard against double-registration.
		new Creator_Settings();
	}

	/** Appends the Monetization tab to the settings nav. */
	public function add_tab( array $tabs ): array {
		$tabs['monetization'] = __( 'Monetization', '6arshid social community' );
		return $tabs;
	}

	// -------------------------------------------------------------------------
	// Settings registration
	// -------------------------------------------------------------------------

	public function register_settings(): void {

		// Feature toggle.
		register_setting( self::OPTION_GROUP, 'sixarshidsc_enabled', array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => static function ( $v ) { return (bool) $v; },
		) );

		// Active gateway (extensible via sixarshidsc_payment_gateways filter).
		register_setting( self::OPTION_GROUP, 'sixarshidsc_active_gateway', array(
			'type'              => 'string',
			'default'           => 'stripe_connect',
			'sanitize_callback' => 'sanitize_key',
		) );

		// Test-mode toggle.
		register_setting( self::OPTION_GROUP, 'sixarshidsc_stripe_test_mode', array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => static function ( $v ) { return (bool) $v; },
		) );

		// Publishable keys — plain text (safe to store unencrypted; these are
		// client-visible by Stripe's own design). Keep existing if submitted blank.
		foreach ( array( 'sixarshidsc_stripe_pub_key_live', 'sixarshidsc_stripe_pub_key_test' ) as $opt ) {
			register_setting( self::OPTION_GROUP, $opt, array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => static function ( $value ) use ( $opt ) {
					$value = sanitize_text_field( wp_unslash( (string) $value ) );
					// Empty submission keeps the existing value.
					if ( '' === trim( $value ) ) {
						return (string) get_option( $opt, '' );
					}
					return $value;
				},
			) );
		}

		// Secret keys + webhook secrets — encrypted before storage.
		// Empty submission keeps the existing encrypted blob (so the admin
		// does not accidentally clear a key just by saving other settings).
		foreach ( array(
			'sixarshidsc_stripe_secret_live',
			'sixarshidsc_stripe_webhook_secret_live',
			'sixarshidsc_stripe_secret_test',
			'sixarshidsc_stripe_webhook_secret_test',
		) as $opt ) {
			register_setting( self::OPTION_GROUP, $opt, array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => static function ( $value ) use ( $opt ) {
					try {
						$value = sanitize_text_field( wp_unslash( (string) $value ) );
						if ( '' === trim( $value ) ) {
							return (string) get_option( $opt, '' );
						}
						// WordPress can fire sanitize_callbacks twice; the second
						// call receives the already-encrypted blob — pass it through.
						if ( Monetization_Crypto::is_encrypted( $value ) ) {
							return $value;
						}
						$encrypted = Monetization_Crypto::encrypt( $value );
						// If encryption returned empty (failure), keep existing value.
						return ( '' !== $encrypted ) ? $encrypted : (string) get_option( $opt, '' );
					} catch ( \Throwable $e ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( '[ARSHID6SOCIAL Monetization] Key save failed for ' . $opt . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
						}
						return (string) get_option( $opt, '' );
					}
				},
			) );
		}

		// Platform fee — percentage (0–100).
		register_setting( self::OPTION_GROUP, 'sixarshidsc_platform_fee_pct', array(
			'type'              => 'number',
			'default'           => 0,
			'sanitize_callback' => static function ( $v ) {
				return max( 0.0, min( 100.0, (float) $v ) );
			},
		) );

		// Platform fee — flat amount per transaction.
		register_setting( self::OPTION_GROUP, 'sixarshidsc_platform_fee_flat', array(
			'type'              => 'number',
			'default'           => 0,
			'sanitize_callback' => static function ( $v ) {
				return max( 0.0, (float) $v );
			},
		) );

		// Currency code.
		register_setting( self::OPTION_GROUP, 'sixarshidsc_currency', array(
			'type'              => 'string',
			'default'           => 'USD',
			'sanitize_callback' => static function ( $v ) {
				$allowed = array(
					'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY',
					'CHF', 'SEK', 'NOK', 'DKK', 'TRY', 'AED', 'SAR',
				);
				$v = strtoupper( sanitize_text_field( (string) $v ) );
				return in_array( $v, $allowed, true ) ? $v : 'USD';
			},
		) );

		// Minimum subscription price creators can set.
		register_setting( self::OPTION_GROUP, 'sixarshidsc_min_sub_price', array(
			'type'              => 'number',
			'default'           => 1.00,
			'sanitize_callback' => static function ( $v ) {
				return max( 0.01, (float) $v );
			},
		) );
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public function enqueue_tab_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'arshid6social-settings' ) ) {
			return;
		}
		wp_add_inline_style(
			'arshid6social-admin',
			'.sixarshidsc-section{margin:24px 0 0}.sixarshidsc-section h3{border-bottom:1px solid #ddd;padding-bottom:8px;margin-bottom:0}.sixarshidsc-key-set{color:#46b450;font-weight:600;margin-left:8px}.sixarshidsc-code{font-family:monospace;background:#f6f7f7;padding:4px 8px;border-radius:3px;word-break:break-all}'
		);
	}

	/**
	 * Outputs the Monetization settings tab content.
	 * Called inside the core admin-settings <form> — do NOT add form tags here.
	 */
	public function render(): void {
		$currency    = esc_html( (string) get_option( 'sixarshidsc_currency', 'USD' ) );
		$webhook_url = esc_url( rest_url( 'sixarshidsc/v1/webhook' ) );
		?>

		<?php if ( ! is_ssl() && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) : ?>
		<div class="notice notice-error inline" style="margin:16px 0 0">
			<p>
				<strong><?php esc_html_e( 'HTTPS required.', '6arshid social community' ); ?></strong>
				<?php esc_html_e( 'Paid Content & Creator Subscriptions requires an SSL certificate. Please enable HTTPS on your site before accepting payments.', '6arshid social community' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Paid Content & Creator Subscriptions', '6arshid social community' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Let creators monetize content with X-style monthly subscriptions and pay-per-view posts. Stripe Connect handles creator identity verification and bank payouts — raw bank details are never stored here.', '6arshid social community' ); ?>
		</p>

		<!-- Enable toggle -->
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Monetization', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="sixarshidsc_enabled" value="1"
							<?php checked( get_option( 'sixarshidsc_enabled' ) ); ?> />
						<?php esc_html_e( 'Activate Paid Content & Creator Subscriptions', '6arshid social community' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'A page reload is required after first enabling this feature.', '6arshid social community' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<!-- Gateway selection -->
		<div class="sixarshidsc-section">
			<h3><?php esc_html_e( 'Payment Gateway', '6arshid social community' ); ?></h3>
		</div>
		<p class="description" style="margin-top:8px">
			<?php esc_html_e( 'Additional gateways can be registered via the sixarshidsc_payment_gateways filter for regions where Stripe is unavailable.', '6arshid social community' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Active Gateway', '6arshid social community' ); ?></th>
				<td>
					<?php
					/**
					 * Filters the list of available payment gateways.
					 *
					 * Add entries here to plug in a local/alternative gateway without
					 * rewriting the Monetization component.
					 *
					 * @param array<string,string> $gateways key => display label.
					 */
					$gateways = (array) apply_filters( 'sixarshidsc_payment_gateways', array(
						'stripe_connect' => __( 'Stripe Connect (recommended)', '6arshid social community' ),
					) );
					$active = (string) get_option( 'sixarshidsc_active_gateway', 'stripe_connect' );
					foreach ( $gateways as $key => $label ) :
					?>
					<label style="display:block;margin-bottom:6px;">
						<input type="radio" name="sixarshidsc_active_gateway"
							value="<?php echo esc_attr( $key ); ?>"
							<?php checked( $active, $key ); ?> />
						<?php echo esc_html( $label ); ?>
					</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<!-- Stripe Connect credentials -->
		<div class="sixarshidsc-section">
			<h3><?php esc_html_e( 'Stripe Connect Credentials', '6arshid social community' ); ?></h3>
		</div>
		<p class="description" style="margin-top:8px">
			<?php
			printf(
				wp_kses(
					/* translators: %s: Stripe dashboard URL */
					__( 'Find your API keys in the <a href="%s" target="_blank" rel="noopener noreferrer">Stripe Dashboard → Developers → API keys</a>. The secret key and webhook signing secret are stored <strong>encrypted</strong> and are never exposed to the browser or REST API.', '6arshid social community' ),
					array(
						'a'      => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
						'strong' => array(),
					)
				),
				'https://dashboard.stripe.com/apikeys'
			);
			?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Mode', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="sixarshidsc_stripe_test_mode" value="1"
							<?php checked( get_option( 'sixarshidsc_stripe_test_mode', true ) ); ?> />
						<?php esc_html_e( 'Test mode — use test keys and no real money is charged', '6arshid social community' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Uncheck only when you are ready to go live.', '6arshid social community' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h4 style="margin:20px 0 0 10px;font-size:13px;color:#3c434a;">
			<?php esc_html_e( 'Live Keys', '6arshid social community' ); ?>
		</h4>
		<table class="form-table" role="presentation">
			<?php $this->render_key_row( 'sixarshidsc_stripe_pub_key_live',          __( 'Publishable Key (Live)', '6arshid social community' ),  'pk_live_…', false ); ?>
			<?php $this->render_key_row( 'sixarshidsc_stripe_secret_live',           __( 'Secret Key (Live)', '6arshid social community' ),       'sk_live_…', true ); ?>
			<?php $this->render_key_row( 'sixarshidsc_stripe_webhook_secret_live',   __( 'Webhook Secret (Live)', '6arshid social community' ),   'whsec_…',   true ); ?>
		</table>

		<h4 style="margin:20px 0 0 10px;font-size:13px;color:#3c434a;">
			<?php esc_html_e( 'Test Keys', '6arshid social community' ); ?>
		</h4>
		<table class="form-table" role="presentation">
			<?php $this->render_key_row( 'sixarshidsc_stripe_pub_key_test',          __( 'Publishable Key (Test)', '6arshid social community' ),  'pk_test_…', false ); ?>
			<?php $this->render_key_row( 'sixarshidsc_stripe_secret_test',           __( 'Secret Key (Test)', '6arshid social community' ),       'sk_test_…', true ); ?>
			<?php $this->render_key_row( 'sixarshidsc_stripe_webhook_secret_test',   __( 'Webhook Secret (Test)', '6arshid social community' ),   'whsec_…',   true ); ?>
		</table>

		<!-- Webhook URL notice -->
		<div class="notice notice-info inline" style="margin:16px 0 0">
			<p>
				<strong><?php esc_html_e( 'Stripe webhook endpoint:', '6arshid social community' ); ?></strong><br />
				<span class="sixarshidsc-code"><?php echo esc_url( $webhook_url ); ?></span><br />
				<span class="description">
					<?php
					printf(
						wp_kses(
							// translators: %s is the URL to the Stripe Dashboard webhooks settings page.
							__( 'Register this URL in <a href="%s" target="_blank" rel="noopener noreferrer">Stripe Dashboard → Webhooks</a>. Listen for: <code>customer.subscription.*</code>, <code>invoice.*</code>, <code>payment_intent.*</code>, <code>account.updated</code>.', '6arshid social community' ),
							array(
								'a'    => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
								'code' => array(),
							)
						),
						'https://dashboard.stripe.com/webhooks'
					);
					?>
				</span>
			</p>
		</div>

		<!-- Platform revenue -->
		<div class="sixarshidsc-section">
			<h3><?php esc_html_e( 'Platform Revenue', '6arshid social community' ); ?></h3>
		</div>
		<p class="description" style="margin-top:8px">
			<?php esc_html_e( 'These fees are applied as a Stripe Connect application fee and deducted before the creator receives their payout. The site owner is responsible for applicable local tax obligations.', '6arshid social community' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="sixarshidsc_platform_fee_pct">
						<?php esc_html_e( 'Platform Fee %', '6arshid social community' ); ?>
					</label>
				</th>
				<td>
					<input type="number" id="sixarshidsc_platform_fee_pct" name="sixarshidsc_platform_fee_pct"
						class="small-text"
						value="<?php echo esc_attr( (string) get_option( 'sixarshidsc_platform_fee_pct', 0 ) ); ?>"
						min="0" max="100" step="0.01" />
					<span class="description">%</span>
					<p class="description">
						<?php esc_html_e( '0 – 100. For example, 10 means the platform retains 10 % of every transaction.', '6arshid social community' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="sixarshidsc_platform_fee_flat">
						<?php esc_html_e( 'Platform Flat Fee', '6arshid social community' ); ?>
					</label>
				</th>
				<td>
					<input type="number" id="sixarshidsc_platform_fee_flat" name="sixarshidsc_platform_fee_flat"
						class="small-text"
						value="<?php echo esc_attr( (string) get_option( 'sixarshidsc_platform_fee_flat', 0 ) ); ?>"
						min="0" step="0.01" />
					<span class="description"><?php echo esc_html( $currency ); ?></span>
					<p class="description">
						<?php esc_html_e( 'Optional fixed amount per transaction, in addition to the percentage fee.', '6arshid social community' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="sixarshidsc_currency">
						<?php esc_html_e( 'Currency', '6arshid social community' ); ?>
					</label>
				</th>
				<td>
					<?php
					$current_currency = (string) get_option( 'sixarshidsc_currency', 'USD' );
					$currencies = array(
						'USD' => 'US Dollar (USD)',
						'EUR' => 'Euro (EUR)',
						'GBP' => 'British Pound (GBP)',
						'CAD' => 'Canadian Dollar (CAD)',
						'AUD' => 'Australian Dollar (AUD)',
						'JPY' => 'Japanese Yen (JPY)',
						'CHF' => 'Swiss Franc (CHF)',
						'SEK' => 'Swedish Krona (SEK)',
						'NOK' => 'Norwegian Krone (NOK)',
						'DKK' => 'Danish Krone (DKK)',
						'TRY' => 'Turkish Lira (TRY)',
						'AED' => 'UAE Dirham (AED)',
						'SAR' => 'Saudi Riyal (SAR)',
					);
					?>
					<select id="sixarshidsc_currency" name="sixarshidsc_currency">
						<?php foreach ( $currencies as $code => $label ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>"
							<?php selected( $current_currency, $code ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Must match your Stripe account\'s settlement currency.', '6arshid social community' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<!-- Subscription pricing -->
		<div class="sixarshidsc-section">
			<h3><?php esc_html_e( 'Subscription Pricing', '6arshid social community' ); ?></h3>
		</div>
		<table class="form-table" role="presentation" style="margin-top:0">
			<tr>
				<th scope="row">
					<label for="sixarshidsc_min_sub_price">
						<?php esc_html_e( 'Minimum Subscription Price', '6arshid social community' ); ?>
					</label>
				</th>
				<td>
					<input type="number" id="sixarshidsc_min_sub_price" name="sixarshidsc_min_sub_price"
						class="small-text"
						value="<?php echo esc_attr( (string) get_option( 'sixarshidsc_min_sub_price', '1.00' ) ); ?>"
						min="0.01" step="0.01" />
					<span class="description"><?php echo esc_html( $currency ); ?></span>
					<p class="description">
						<?php esc_html_e( 'The lowest monthly price a creator can set for their subscription tier.', '6arshid social community' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<!-- Compliance notice -->
		<div class="sixarshidsc-section">
			<h3><?php esc_html_e( 'Compliance', '6arshid social community' ); ?></h3>
		</div>
		<div class="notice notice-warning inline" style="margin:8px 0 0">
			<p>
				<?php esc_html_e( 'This platform facilitates payments via Stripe Connect and is not itself a bank or payment processor. Stripe handles creator identity verification (KYC) and bank payouts. Refunds and chargebacks are processed per Stripe\'s policies. The site owner is solely responsible for applicable taxes and VAT in their jurisdiction.', '6arshid social community' ); ?>
			</p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Renders a single <tr> for an API key field.
	 *
	 * For secret fields the stored value is never output to the browser.
	 * A "Saved" indicator is shown when a value exists, and the placeholder
	 * tells the admin to enter a new value only if they want to replace it.
	 *
	 * @param string $opt_name   WP option name.
	 * @param string $label      Human-readable field label.
	 * @param string $placeholder  Hint shown in empty input (e.g. pk_live_…).
	 * @param bool   $is_secret  True = password input, never pre-fill value.
	 */
	private function render_key_row(
		string $opt_name,
		string $label,
		string $placeholder,
		bool   $is_secret
	): void {
		$is_set = Monetization_Crypto::is_set( $opt_name );

		if ( $is_secret ) {
			// Never output the decrypted value into the browser.
			$input_value  = '';
			$input_placeholder = $is_set
				? __( '— key saved; enter a new value to replace —', '6arshid social community' )
				: $placeholder;
		} else {
			// Publishable keys are client-visible by design — show the saved value.
			$input_value       = (string) get_option( $opt_name, '' );
			$input_placeholder = $placeholder;
		}
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $opt_name ); ?>">
					<?php echo esc_html( $label ); ?>
				</label>
			</th>
			<td>
				<input
					type="<?php echo $is_secret ? 'password' : 'text'; ?>"
					id="<?php echo esc_attr( $opt_name ); ?>"
					name="<?php echo esc_attr( $opt_name ); ?>"
					value="<?php echo esc_attr( $input_value ); ?>"
					class="regular-text"
					autocomplete="off"
					spellcheck="false"
					placeholder="<?php echo esc_attr( $input_placeholder ); ?>"
				/>
				<?php if ( $is_set ) : ?>
					<span class="sixarshidsc-key-set">&#10003; <?php esc_html_e( 'Saved', '6arshid social community' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
