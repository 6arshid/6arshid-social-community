<?php
namespace Arshid6Social\Components\Monetization;

/**
 * Creator payout settings section — rendered inside the member settings page.
 *
 * Sections:
 *  1. IBAN form   — user enters their bank IBAN; stored encrypted in user meta.
 *  2. Earnings    — balance summary + transaction history.
 *
 * Admin payout panel is rendered via the arshid6social_settings_tab_monetization
 * action (appended to the Monetization tab in wp-admin).
 *
 * @package Arshid6Social\Components\Monetization
 */

defined( 'ABSPATH' ) || exit;

class Creator_Settings {

	/** User-meta key that holds the encrypted IBAN. */
	const IBAN_META = 'sixarshidsc_iban';

	/** Prevents double-registration when both Monetization_Settings and Monetization boot. */
	private static bool $booted = false;

	public function __construct() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'arshid6social_member_settings_after',     array( $this, 'render' ), 10, 1 );
		add_action( 'arshid6social_monetization_payouts_tab', array( $this, 'render_admin_payout_panel' ) );
		add_action( 'wp_ajax_sixarshidsc_save_payout_address',     array( $this, 'ajax_save_address' ) );
		add_action( 'wp_ajax_sixarshidsc_save_iban',               array( $this, 'ajax_save_iban' ) );
		add_action( 'wp_ajax_sixarshidsc_admin_get_iban',          array( $this, 'ajax_admin_get_iban' ) );
		add_action( 'wp_ajax_sixarshidsc_admin_mark_paid',         array( $this, 'ajax_admin_mark_paid' ) );
		add_action( 'wp_ajax_sixarshidsc_request_cashout',         array( $this, 'ajax_request_cashout' ) );
	}

	// -------------------------------------------------------------------------
	// Main render entry point
	// -------------------------------------------------------------------------

	public function render( \WP_User $profile_user ): void {
		if ( get_current_user_id() !== $profile_user->ID ) {
			return;
		}
		if ( ! get_option( 'sixarshidsc_enabled' ) ) {
			return;
		}

		$this->render_payout_account_card( $profile_user );
		$this->render_earnings_card( $profile_user->ID );
	}

	// -------------------------------------------------------------------------
	// Payout account card — IBAN form
	// -------------------------------------------------------------------------

	private function render_payout_account_card( \WP_User $user ): void {
		$encrypted_iban = (string) get_user_meta( $user->ID, self::IBAN_META, true );
		$has_iban       = '' !== $encrypted_iban;

		$address = array(
			'line1'   => (string) get_user_meta( $user->ID, 'sixarshidsc_address_line1',   true ),
			'city'    => (string) get_user_meta( $user->ID, 'sixarshidsc_address_city',    true ),
			'postal'  => (string) get_user_meta( $user->ID, 'sixarshidsc_address_postal',  true ),
			'country' => (string) get_user_meta( $user->ID, 'sixarshidsc_address_country', true ),
		);

		$iban_nonce    = wp_create_nonce( 'sixarshidsc_save_iban_' . $user->ID );
		$address_nonce = wp_create_nonce( 'sixarshidsc_save_address_' . $user->ID );
		?>
		<div class="arshid6social-card arshid6social-user-settings-card" id="sixarshidsc-payout-account-card">
			<div class="arshid6social-card__header"><?php esc_html_e( 'Payout Account', '6arshid-social-community' ); ?></div>
			<div class="arshid6social-card__body">

				<!-- IBAN form -->
				<form id="sixarshidsc-iban-form" autocomplete="off">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $iban_nonce ); ?>" />

					<div class="arshid6social-settings-field">
						<label class="arshid6social-settings-label" for="sixarshidsc-iban-input">
							<?php esc_html_e( 'Bank Account (IBAN)', '6arshid-social-community' ); ?>
						</label>
						<p class="arshid6social-settings-desc" style="margin-block-end:.75rem;">
							<?php esc_html_e( 'Your IBAN is stored securely and is only visible to site administrators for processing payouts.', '6arshid-social-community' ); ?>
						</p>
						<input
							type="text"
							id="sixarshidsc-iban-input"
							name="iban"
							class="arshid6social-input"
							placeholder="<?php echo $has_iban ? esc_attr__( '— IBAN saved; enter a new value to replace —', '6arshid-social-community' ) : 'IR000000000000000000000000'; ?>"
							value=""
							maxlength="34"
							autocomplete="off"
							spellcheck="false"
							style="width:100%;box-sizing:border-box;font-family:monospace;"
						/>
						<?php if ( $has_iban ) : ?>
							<p class="arshid6social-settings-desc" style="color:var(--sn-color-success,#16a34a);margin-block-start:.4rem;">
								&#10003; <?php esc_html_e( 'IBAN saved. Leave blank to keep the existing one.', '6arshid-social-community' ); ?>
							</p>
						<?php endif; ?>
					</div>

					<div class="arshid6social-settings-actions" style="margin-block-start:.75rem;">
						<button type="submit" class="arshid6social-btn arshid6social-btn--primary" id="sixarshidsc-iban-save-btn">
							<?php esc_html_e( 'Save IBAN', '6arshid-social-community' ); ?>
						</button>
						<span class="sixarshidsc-iban-saved-msg" hidden aria-live="polite">
							&#10003; <?php esc_html_e( 'Saved!', '6arshid-social-community' ); ?>
						</span>
						<span class="sixarshidsc-iban-error-msg" hidden aria-live="polite" style="color:var(--sn-color-danger,#dc2626);"></span>
					</div>
				</form>

				<hr style="border:none;border-block-start:1px solid var(--sn-border,#e5e7eb);margin:1.25rem 0;" />

				<!-- Address form -->
				<form id="sixarshidsc-address-form">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $address_nonce ); ?>" />

					<p class="arshid6social-settings-label" style="margin-block-end:.75rem;font-weight:600;">
						<?php esc_html_e( 'Billing / Contact Address', '6arshid-social-community' ); ?>
					</p>
					<p class="arshid6social-settings-desc" style="margin-block-end:1rem;">
						<?php esc_html_e( 'Used for invoicing and tax records. Not shared publicly.', '6arshid-social-community' ); ?>
					</p>

					<div class="arshid6social-settings-field">
						<label class="arshid6social-settings-label" for="sixarshidsc-address-line1">
							<?php esc_html_e( 'Address Line', '6arshid-social-community' ); ?>
						</label>
						<input type="text" id="sixarshidsc-address-line1" name="line1"
							class="arshid6social-input"
							value="<?php echo esc_attr( $address['line1'] ); ?>"
							placeholder="<?php esc_attr_e( 'Street address, apartment, etc.', '6arshid-social-community' ); ?>"
							style="width:100%;box-sizing:border-box;" />
					</div>

					<div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-block-start:.75rem;">
						<div class="arshid6social-settings-field">
							<label class="arshid6social-settings-label" for="sixarshidsc-address-city">
								<?php esc_html_e( 'City', '6arshid-social-community' ); ?>
							</label>
							<input type="text" id="sixarshidsc-address-city" name="city"
								class="arshid6social-input"
								value="<?php echo esc_attr( $address['city'] ); ?>"
								placeholder="<?php esc_attr_e( 'City', '6arshid-social-community' ); ?>"
								style="width:100%;box-sizing:border-box;" />
						</div>
						<div class="arshid6social-settings-field">
							<label class="arshid6social-settings-label" for="sixarshidsc-address-postal">
								<?php esc_html_e( 'Postal Code', '6arshid-social-community' ); ?>
							</label>
							<input type="text" id="sixarshidsc-address-postal" name="postal"
								class="arshid6social-input"
								value="<?php echo esc_attr( $address['postal'] ); ?>"
								placeholder="<?php esc_attr_e( 'Postal / ZIP code', '6arshid-social-community' ); ?>"
								style="width:100%;box-sizing:border-box;" />
						</div>
					</div>

					<div class="arshid6social-settings-field" style="margin-block-start:.75rem;">
						<label class="arshid6social-settings-label" for="sixarshidsc-address-country">
							<?php esc_html_e( 'Country', '6arshid-social-community' ); ?>
						</label>
						<select id="sixarshidsc-address-country" name="country"
							class="arshid6social-input"
							style="width:100%;box-sizing:border-box;">
							<?php
							// Sanctioned / OFAC-restricted countries are excluded: IR (Iran), CU (Cuba), KP (North Korea), SY (Syria), RU (Russia), BY (Belarus), VE (Venezuela), MM (Myanmar), SD (Sudan), SS (South Sudan), ZW (Zimbabwe), LY (Libya), SO (Somalia), YE (Yemen), ER (Eritrea).
							$countries = array(
								''   => __( '— Select country —', '6arshid-social-community' ),
								'AF' => 'Afghanistan',
								'AL' => 'Albania',
								'DZ' => 'Algeria',
								'AD' => 'Andorra',
								'AO' => 'Angola',
								'AG' => 'Antigua and Barbuda',
								'AR' => 'Argentina',
								'AM' => 'Armenia',
								'AU' => 'Australia',
								'AT' => 'Austria',
								'AZ' => 'Azerbaijan',
								'BS' => 'Bahamas',
								'BH' => 'Bahrain',
								'BD' => 'Bangladesh',
								'BB' => 'Barbados',
								'BE' => 'Belgium',
								'BZ' => 'Belize',
								'BJ' => 'Benin',
								'BT' => 'Bhutan',
								'BO' => 'Bolivia',
								'BA' => 'Bosnia and Herzegovina',
								'BW' => 'Botswana',
								'BR' => 'Brazil',
								'BN' => 'Brunei',
								'BG' => 'Bulgaria',
								'BF' => 'Burkina Faso',
								'BI' => 'Burundi',
								'CV' => 'Cabo Verde',
								'KH' => 'Cambodia',
								'CM' => 'Cameroon',
								'CA' => 'Canada',
								'CF' => 'Central African Republic',
								'TD' => 'Chad',
								'CL' => 'Chile',
								'CN' => 'China',
								'CO' => 'Colombia',
								'KM' => 'Comoros',
								'CG' => 'Congo',
								'CD' => 'Congo (DRC)',
								'CR' => 'Costa Rica',
								'CI' => 'Côte d\'Ivoire',
								'HR' => 'Croatia',
								'CY' => 'Cyprus',
								'CZ' => 'Czech Republic',
								'DK' => 'Denmark',
								'DJ' => 'Djibouti',
								'DM' => 'Dominica',
								'DO' => 'Dominican Republic',
								'EC' => 'Ecuador',
								'EG' => 'Egypt',
								'SV' => 'El Salvador',
								'GQ' => 'Equatorial Guinea',
								'EE' => 'Estonia',
								'SZ' => 'Eswatini',
								'ET' => 'Ethiopia',
								'FJ' => 'Fiji',
								'FI' => 'Finland',
								'FR' => 'France',
								'GA' => 'Gabon',
								'GM' => 'Gambia',
								'GE' => 'Georgia',
								'DE' => 'Germany',
								'GH' => 'Ghana',
								'GR' => 'Greece',
								'GD' => 'Grenada',
								'GT' => 'Guatemala',
								'GN' => 'Guinea',
								'GW' => 'Guinea-Bissau',
								'GY' => 'Guyana',
								'HT' => 'Haiti',
								'HN' => 'Honduras',
								'HU' => 'Hungary',
								'IS' => 'Iceland',
								'IN' => 'India',
								'ID' => 'Indonesia',
								'IQ' => 'Iraq',
								'IE' => 'Ireland',
								'IL' => 'Israel',
								'IT' => 'Italy',
								'JM' => 'Jamaica',
								'JP' => 'Japan',
								'JO' => 'Jordan',
								'KZ' => 'Kazakhstan',
								'KE' => 'Kenya',
								'KI' => 'Kiribati',
								'KW' => 'Kuwait',
								'KG' => 'Kyrgyzstan',
								'LA' => 'Laos',
								'LV' => 'Latvia',
								'LB' => 'Lebanon',
								'LS' => 'Lesotho',
								'LR' => 'Liberia',
								'LI' => 'Liechtenstein',
								'LT' => 'Lithuania',
								'LU' => 'Luxembourg',
								'MG' => 'Madagascar',
								'MW' => 'Malawi',
								'MY' => 'Malaysia',
								'MV' => 'Maldives',
								'ML' => 'Mali',
								'MT' => 'Malta',
								'MH' => 'Marshall Islands',
								'MR' => 'Mauritania',
								'MU' => 'Mauritius',
								'MX' => 'Mexico',
								'FM' => 'Micronesia',
								'MD' => 'Moldova',
								'MC' => 'Monaco',
								'MN' => 'Mongolia',
								'ME' => 'Montenegro',
								'MA' => 'Morocco',
								'MZ' => 'Mozambique',
								'NA' => 'Namibia',
								'NR' => 'Nauru',
								'NP' => 'Nepal',
								'NL' => 'Netherlands',
								'NZ' => 'New Zealand',
								'NI' => 'Nicaragua',
								'NE' => 'Niger',
								'NG' => 'Nigeria',
								'MK' => 'North Macedonia',
								'NO' => 'Norway',
								'OM' => 'Oman',
								'PK' => 'Pakistan',
								'PW' => 'Palau',
								'PA' => 'Panama',
								'PG' => 'Papua New Guinea',
								'PY' => 'Paraguay',
								'PE' => 'Peru',
								'PH' => 'Philippines',
								'PL' => 'Poland',
								'PT' => 'Portugal',
								'QA' => 'Qatar',
								'RO' => 'Romania',
								'RW' => 'Rwanda',
								'KN' => 'Saint Kitts and Nevis',
								'LC' => 'Saint Lucia',
								'VC' => 'Saint Vincent and the Grenadines',
								'WS' => 'Samoa',
								'SM' => 'San Marino',
								'ST' => 'São Tomé and Príncipe',
								'SA' => 'Saudi Arabia',
								'SN' => 'Senegal',
								'RS' => 'Serbia',
								'SC' => 'Seychelles',
								'SL' => 'Sierra Leone',
								'SG' => 'Singapore',
								'SK' => 'Slovakia',
								'SI' => 'Slovenia',
								'SB' => 'Solomon Islands',
								'ZA' => 'South Africa',
								'KR' => 'South Korea',
								'ES' => 'Spain',
								'LK' => 'Sri Lanka',
								'SR' => 'Suriname',
								'SE' => 'Sweden',
								'CH' => 'Switzerland',
								'TW' => 'Taiwan',
								'TJ' => 'Tajikistan',
								'TZ' => 'Tanzania',
								'TH' => 'Thailand',
								'TL' => 'Timor-Leste',
								'TG' => 'Togo',
								'TO' => 'Tonga',
								'TT' => 'Trinidad and Tobago',
								'TN' => 'Tunisia',
								'TR' => 'Turkey',
								'TM' => 'Turkmenistan',
								'TV' => 'Tuvalu',
								'UG' => 'Uganda',
								'UA' => 'Ukraine',
								'AE' => 'United Arab Emirates',
								'GB' => 'United Kingdom',
								'US' => 'United States',
								'UY' => 'Uruguay',
								'UZ' => 'Uzbekistan',
								'VU' => 'Vanuatu',
								'VA' => 'Vatican City',
								'VN' => 'Vietnam',
								'XK' => 'Kosovo',
								'ZM' => 'Zambia',
							);
							foreach ( $countries as $code => $label ) :
							?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $address['country'], $code ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="arshid6social-settings-actions" style="margin-block-start:1rem;">
						<button type="submit" class="arshid6social-btn arshid6social-btn--primary" id="sixarshidsc-address-save-btn">
							<?php esc_html_e( 'Save Address', '6arshid-social-community' ); ?>
						</button>
						<span class="sixarshidsc-address-saved-msg" hidden aria-live="polite">
							&#10003; <?php esc_html_e( 'Saved!', '6arshid-social-community' ); ?>
						</span>
						<span class="sixarshidsc-address-error-msg" hidden aria-live="polite" style="color:var(--sn-color-danger,#dc2626);">
							<?php esc_html_e( 'Error saving. Please try again.', '6arshid-social-community' ); ?>
						</span>
					</div>
				</form>

			</div>
		</div>

		<?php
		$js_payout  = '(function(){';
		$js_payout .= 'var ajaxUrl=(window.ARSHID6SOCIALConfig&&window.ARSHID6SOCIALConfig.ajaxUrl)||' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';';
		$js_payout .= 'var txtIbanSaved=' . wp_json_encode( __( '— IBAN saved; enter a new value to replace —', '6arshid-social-community' ) ) . ';';
		$js_payout .= 'var txtIbanError=' . wp_json_encode( __( 'Error saving IBAN.', '6arshid-social-community' ) ) . ';';
		$js_payout .= 'var txtNetError=' . wp_json_encode( __( 'Network error. Please try again.', '6arshid-social-community' ) ) . ';';
		$js_payout .= <<<'ENDJS'
(function(){
	var form=document.getElementById('sixarshidsc-iban-form');if(!form)return;
	var saveBtn=document.getElementById('sixarshidsc-iban-save-btn');
	var savedEl=form.querySelector('.sixarshidsc-iban-saved-msg');
	var errEl=form.querySelector('.sixarshidsc-iban-error-msg');
	form.addEventListener('submit',async function(e){
		e.preventDefault();
		var ibanVal=form.querySelector('#sixarshidsc-iban-input').value.trim();if(!ibanVal)return;
		if(saveBtn)saveBtn.disabled=true;
		var data=new FormData(form);data.set('action','sixarshidsc_save_iban');data.set('iban',ibanVal.replace(/\s+/g,'').toUpperCase());
		try{
			var resp=await fetch(ajaxUrl,{method:'POST',credentials:'same-origin',body:data});var json=await resp.json();
			if(json.success){if(savedEl){savedEl.hidden=false;setTimeout(function(){savedEl.hidden=true;},3000);}if(errEl)errEl.hidden=true;form.querySelector('#sixarshidsc-iban-input').value='';form.querySelector('#sixarshidsc-iban-input').placeholder=txtIbanSaved;}
			else{var msg=(json.data&&json.data.message)?json.data.message:txtIbanError;if(errEl){errEl.textContent=msg;errEl.hidden=false;}}
		}catch{if(errEl){errEl.textContent=txtNetError;errEl.hidden=false;}}
		finally{if(saveBtn)saveBtn.disabled=false;}
	});
})();
(function(){
	var form=document.getElementById('sixarshidsc-address-form');if(!form)return;
	var saveBtn=document.getElementById('sixarshidsc-address-save-btn');
	var savedEl=form.querySelector('.sixarshidsc-address-saved-msg');
	var errEl=form.querySelector('.sixarshidsc-address-error-msg');
	form.addEventListener('submit',async function(e){
		e.preventDefault();if(saveBtn)saveBtn.disabled=true;
		var data=new FormData(form);data.set('action','sixarshidsc_save_payout_address');
		try{var resp=await fetch(ajaxUrl,{method:'POST',credentials:'same-origin',body:data});var json=await resp.json();if(json.success){if(savedEl){savedEl.hidden=false;setTimeout(function(){savedEl.hidden=true;},3000);}if(errEl)errEl.hidden=true;}else{if(errEl)errEl.hidden=false;}}
		catch{if(errEl)errEl.hidden=false;}
		finally{if(saveBtn)saveBtn.disabled=false;}
	});
})();
})();
ENDJS;
		wp_add_inline_script( 'arshid6social-main', $js_payout );
	}

	// -------------------------------------------------------------------------
	// Earnings card
	// -------------------------------------------------------------------------

	private function render_earnings_card( int $user_id ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'sixarshidsc_transactions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			return;
		}

		// Total income: completed PPV + subscription receipts where user is creator.
		$total_income = (float) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COALESCE( SUM( amount - platform_fee ), 0 )
			   FROM {$table}
			  WHERE creator_id = %d
			    AND type IN ('ppv','subscription')
			    AND status = 'completed'",
			$user_id
		) );

		// Total paid out: completed payout transactions.
		$total_paid_out = (float) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COALESCE( SUM( amount ), 0 )
			   FROM {$table}
			  WHERE creator_id = %d
			    AND type = 'payout'
			    AND status = 'completed'",
			$user_id
		) );

		// Pending cashout requests (payout in pending state).
		$pending_cashout = (float) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COALESCE( SUM( amount ), 0 )
			   FROM {$table}
			  WHERE creator_id = %d
			    AND type = 'payout'
			    AND status = 'pending'",
			$user_id
		) );

		$available = max( 0.0, $total_income - $total_paid_out - $pending_cashout );

		$currency = strtoupper( (string) get_option( 'sixarshidsc_currency', 'USD' ) );

		$has_iban    = '' !== (string) get_user_meta( $user_id, self::IBAN_META, true );
		$can_cashout = $available > 0 && $has_iban;

		$cashout_nonce = wp_create_nonce( 'sixarshidsc_request_cashout_' . $user_id );

		// All transactions visible to this user (as creator or payer).
		$rows = (array) $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT type, amount, platform_fee, currency, status, created_at
			   FROM {$table}
			  WHERE creator_id = %d OR payer_id = %d
			  ORDER BY created_at DESC
			  LIMIT 50",
			$user_id,
			$user_id
		) );
		?>
		<div class="arshid6social-card arshid6social-user-settings-card" id="sixarshidsc-earnings-card">
			<div class="arshid6social-card__header"><?php esc_html_e( 'Earnings & Transactions', '6arshid-social-community' ); ?></div>
			<div class="arshid6social-card__body">

				<!-- Balance summary -->
				<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-block-end:1.25rem;">
					<div style="padding:1rem;background:var(--sn-surface-alt,#f3f4f6);border-radius:8px;text-align:center;">
						<div style="font-size:.8rem;color:var(--sn-text-muted,#6b7280);text-transform:uppercase;letter-spacing:.05em;">
							<?php esc_html_e( 'Available Balance', '6arshid-social-community' ); ?>
						</div>
						<div style="font-size:1.5rem;font-weight:700;margin-block-start:.25rem;color:var(--sn-color-success,#16a34a);">
							<?php echo esc_html( number_format( $available, 2 ) . ' ' . $currency ); ?>
						</div>
					</div>
					<div style="padding:1rem;background:var(--sn-surface-alt,#f3f4f6);border-radius:8px;text-align:center;">
						<div style="font-size:.8rem;color:var(--sn-text-muted,#6b7280);text-transform:uppercase;letter-spacing:.05em;">
							<?php esc_html_e( 'Pending Cashout', '6arshid-social-community' ); ?>
						</div>
						<div style="font-size:1.5rem;font-weight:700;margin-block-start:.25rem;color:var(--sn-color-warning,#d97706);">
							<?php echo esc_html( number_format( $pending_cashout, 2 ) . ' ' . $currency ); ?>
						</div>
					</div>
				</div>

				<!-- Cashout button -->
				<div id="sixarshidsc-cashout-wrap" style="margin-block-end:1.5rem;">
					<?php if ( $available > 0 && ! $has_iban ) : ?>
						<p class="arshid6social-settings-desc" style="color:var(--sn-color-warning,#d97706);">
							<?php esc_html_e( 'Please save your IBAN above before requesting a cashout.', '6arshid-social-community' ); ?>
						</p>
					<?php elseif ( $pending_cashout > 0 ) : ?>
						<p class="arshid6social-settings-desc" style="color:var(--sn-color-warning,#d97706);">
							<?php esc_html_e( 'You have a pending cashout request. The administrator will process it shortly.', '6arshid-social-community' ); ?>
						</p>
					<?php elseif ( $available <= 0 ) : ?>
						<p class="arshid6social-settings-desc">
							<?php esc_html_e( 'Payouts are processed manually by the site administrator to the IBAN you provided above.', '6arshid-social-community' ); ?>
						</p>
					<?php else : ?>
						<button
							type="button"
							id="sixarshidsc-cashout-btn"
							class="arshid6social-btn arshid6social-btn--primary"
							data-amount="<?php echo esc_attr( number_format( $available, 2 ) ); ?>"
							data-currency="<?php echo esc_attr( $currency ); ?>"
							data-nonce="<?php echo esc_attr( $cashout_nonce ); ?>"
						>
							<?php
							echo esc_html( sprintf(
								/* translators: %1$s = formatted amount, %2$s = currency */
								__( 'Request Cashout — %1$s %2$s', '6arshid-social-community' ),
								number_format( $available, 2 ),
								$currency
							) );
							?>
						</button>
						<span id="sixarshidsc-cashout-msg" aria-live="polite" style="margin-inline-start:.75rem;font-size:.875rem;"></span>
					<?php endif; ?>
				</div>

				<!-- Transaction table -->
				<div style="overflow-x:auto;">
					<?php if ( empty( $rows ) ) : ?>
						<p class="arshid6social-settings-desc" style="text-align:center;padding:1.5rem 0;">
							<?php esc_html_e( 'No transactions yet.', '6arshid-social-community' ); ?>
						</p>
					<?php else : ?>
						<table style="width:100%;border-collapse:collapse;font-size:.875rem;">
							<thead>
								<tr style="border-block-end:2px solid var(--sn-border,#e5e7eb);">
									<th style="padding:.5rem .75rem;text-align:start;color:var(--sn-text-muted,#6b7280);font-weight:600;"><?php esc_html_e( 'Date', '6arshid-social-community' ); ?></th>
									<th style="padding:.5rem .75rem;text-align:start;color:var(--sn-text-muted,#6b7280);font-weight:600;"><?php esc_html_e( 'Type', '6arshid-social-community' ); ?></th>
									<th style="padding:.5rem .75rem;text-align:end;color:var(--sn-text-muted,#6b7280);font-weight:600;"><?php esc_html_e( 'Amount', '6arshid-social-community' ); ?></th>
									<th style="padding:.5rem .75rem;text-align:end;color:var(--sn-text-muted,#6b7280);font-weight:600;"><?php esc_html_e( 'Fee', '6arshid-social-community' ); ?></th>
									<th style="padding:.5rem .75rem;text-align:end;color:var(--sn-text-muted,#6b7280);font-weight:600;"><?php esc_html_e( 'Net', '6arshid-social-community' ); ?></th>
									<th style="padding:.5rem .75rem;text-align:start;color:var(--sn-text-muted,#6b7280);font-weight:600;"><?php esc_html_e( 'Status', '6arshid-social-community' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $row ) :
									$type_label = $this->type_label( $row->type );
									$net        = 'payout' === $row->type
										? -( (float) $row->amount )
										: (float) $row->amount - (float) $row->platform_fee;
									$status_css = $this->status_class( $row->status );
								?>
								<tr style="border-block-end:1px solid var(--sn-border,#e5e7eb);">
									<td style="padding:.5rem .75rem;white-space:nowrap;">
										<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row->created_at ) ) ); ?>
									</td>
									<td style="padding:.5rem .75rem;"><?php echo esc_html( $type_label ); ?></td>
									<td style="padding:.5rem .75rem;text-align:end;">
										<?php echo esc_html( number_format( (float) $row->amount, 2 ) . ' ' . strtoupper( $row->currency ) ); ?>
									</td>
									<td style="padding:.5rem .75rem;text-align:end;color:var(--sn-text-muted,#6b7280);">
										<?php echo esc_html( number_format( (float) $row->platform_fee, 2 ) ); ?>
									</td>
									<td style="padding:.5rem .75rem;text-align:end;font-weight:600;<?php echo 'payout' === $row->type ? 'color:var(--sn-color-danger,#dc2626);' : ''; ?>">
										<?php echo esc_html( ( 'payout' === $row->type ? '−' : '' ) . number_format( abs( $net ), 2 ) ); ?>
									</td>
									<td style="padding:.5rem .75rem;">
										<span style="<?php echo esc_attr( $status_css ); ?>">
											<?php echo esc_html( ucfirst( $row->status ) ); ?>
										</span>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

			</div>
		</div>

		<?php
		// translators: %1$s is the cashout amount formatted as a number, %2$s is the currency code.
		$cashout_confirm_txt = sprintf( __( 'Request cashout of %1$s %2$s? The admin will transfer it to your IBAN.', '6arshid-social-community' ), number_format( $available, 2 ), $currency );
		$js_cashout  = '(function(){';
		$js_cashout .= 'var ajaxUrl=(window.ARSHID6SOCIALConfig&&window.ARSHID6SOCIALConfig.ajaxUrl)||' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';';
		$js_cashout .= 'var txtConfirm=' . wp_json_encode( $cashout_confirm_txt ) . ';';
		$js_cashout .= 'var txtSubmitting=' . wp_json_encode( __( 'Submitting…', '6arshid-social-community' ) ) . ';';
		$js_cashout .= 'var txtSuccess=' . wp_json_encode( __( 'Cashout requested! The admin will process it shortly.', '6arshid-social-community' ) ) . ';';
		$js_cashout .= 'var txtError=' . wp_json_encode( __( 'Error. Please try again.', '6arshid-social-community' ) ) . ';';
		$js_cashout .= 'var txtNetError=' . wp_json_encode( __( 'Network error. Please try again.', '6arshid-social-community' ) ) . ';';
		$js_cashout .= <<<'ENDJS'
var btn=document.getElementById('sixarshidsc-cashout-btn');if(!btn)return;
var msgEl=document.getElementById('sixarshidsc-cashout-msg');
btn.addEventListener('click',async function(){
	if(!confirm(txtConfirm))return;
	btn.disabled=true;if(msgEl)msgEl.textContent=txtSubmitting;
	try{
		var fd=new FormData();fd.set('action','sixarshidsc_request_cashout');fd.set('nonce',btn.dataset.nonce);
		var resp=await fetch(ajaxUrl,{method:'POST',credentials:'same-origin',body:fd});var json=await resp.json();
		if(json.success){btn.style.display='none';if(msgEl){msgEl.style.color='var(--sn-color-success,#16a34a)';msgEl.textContent=(json.data&&json.data.message)?json.data.message:txtSuccess;}}
		else{btn.disabled=false;if(msgEl){msgEl.style.color='var(--sn-color-danger,#dc2626)';msgEl.textContent=(json.data&&json.data.message)?json.data.message:txtError;}}
	}catch{btn.disabled=false;if(msgEl){msgEl.style.color='var(--sn-color-danger,#dc2626)';msgEl.textContent=txtNetError;}}
});
})();
ENDJS;
		wp_add_inline_script( 'arshid6social-main', $js_cashout );
	}

	// -------------------------------------------------------------------------
	// Admin payout panel — rendered at the bottom of the Monetization settings tab
	// -------------------------------------------------------------------------

	/**
	 * Renders the admin-only IBAN payout panel.
	 * Hooked to arshid6social_settings_tab_monetization at priority 20.
	 */
	public function render_admin_payout_panel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		$transactions_table = $wpdb->prefix . 'sixarshidsc_transactions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$transactions_table}'" ) === $transactions_table;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] )     ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$per_page = (int) get_user_option( 'arshid6social_payouts_per_page' );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}
		$offset = ( $paged - 1 ) * $per_page;

		// Build query with optional search join.
		$meta_key  = esc_sql( self::IBAN_META );
		$where_sql = "um.meta_key = '{$meta_key}' AND um.meta_value != ''";
		$join_sql  = "LEFT JOIN {$wpdb->users} u ON u.ID = um.user_id";
		$vals      = array();

		if ( '' !== $search ) {
			$like          = '%' . $wpdb->esc_like( $search ) . '%';
			$where_sql    .= ' AND ( u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s )';
			array_push( $vals, $like, $like, $like );
		}

		// Count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$wpdb->usermeta} um {$join_sql} WHERE {$where_sql}";
		$total = (int) ( empty( $vals )
			? $wpdb->get_var( $count_sql ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			: $wpdb->get_var( $wpdb->prepare( $count_sql, ...$vals ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		// Fetch page.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows_sql   = "SELECT um.user_id, um.meta_value AS iban_enc FROM {$wpdb->usermeta} um {$join_sql} WHERE {$where_sql} ORDER BY um.user_id ASC LIMIT %d OFFSET %d";
		$fetch_vals = array_merge( $vals, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$iban_users = $wpdb->get_results( $wpdb->prepare( $rows_sql, ...$fetch_vals ) );

		$total_pages = (int) ceil( $total / $per_page );
		$admin_nonce = wp_create_nonce( 'sixarshidsc_admin_payout' );
		$currency    = strtoupper( (string) get_option( 'sixarshidsc_currency', 'USD' ) );

		$base_url = add_query_arg(
			array_filter( array(
				'page'  => 'arshid6social-monetization',
				'tab'   => 'payouts',
				's'     => $search ?: null,
			) ),
			admin_url( 'admin.php' )
		);
		?>
		<h2 style="margin-top:0;"><?php esc_html_e( 'Creator Payouts — IBAN List', '6arshid-social-community' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Creators who have saved their bank IBAN. Use this list to process manual bank transfers. IBANs are stored encrypted and only visible to administrators.', '6arshid-social-community' ); ?>
		</p>

		<form method="get" style="display:flex;gap:8px;margin-bottom:16px;align-items:center;">
			<input type="hidden" name="page" value="arshid6social-monetization" />
			<input type="hidden" name="tab"  value="payouts" />
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
				placeholder="<?php esc_attr_e( 'Search by name or email…', '6arshid-social-community' ); ?>"
				style="padding:5px 9px;border:1px solid #8c8f94;border-radius:4px;min-width:260px;" />
			<?php submit_button( __( 'Search', '6arshid-social-community' ), 'secondary', '', false, array( 'style' => 'padding:4px 12px;' ) ); ?>
			<?php if ( $search ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'arshid6social-monetization', 'tab' => 'payouts' ), admin_url( 'admin.php' ) ) ); ?>" class="button"><?php esc_html_e( 'Clear', '6arshid-social-community' ); ?></a>
			<?php endif; ?>
		</form>

		<p class="description" style="margin-bottom:8px;">
			<?php
			/* translators: %d: total number of creators */
			printf( esc_html__( '%d creator(s) with saved IBAN.', '6arshid-social-community' ), (int) $total );
			?>
		</p>

		<?php if ( empty( $iban_users ) ) : ?>
			<p class="description" style="padding:1rem;background:#f6f7f7;border-radius:4px;">
				<?php esc_html_e( 'No creators match the current search.', '6arshid-social-community' ); ?>
			</p>
		<?php else : ?>
			<table class="widefat striped" id="sixarshidsc-payout-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', '6arshid-social-community' ); ?></th>
						<th><?php esc_html_e( 'IBAN', '6arshid-social-community' ); ?></th>
						<?php if ( $table_exists ) : ?>
						<th><?php esc_html_e( 'Available Balance', '6arshid-social-community' ); ?></th>
						<th><?php esc_html_e( 'Pending Cashout', '6arshid-social-community' ); ?></th>
						<?php endif; ?>
						<th><?php esc_html_e( 'Address', '6arshid-social-community' ); ?></th>
						<?php if ( $table_exists ) : ?>
						<th><?php esc_html_e( 'Action', '6arshid-social-community' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $iban_users as $row ) :
					$uid  = (int) $row->user_id;
					$user = get_userdata( $uid );
					if ( ! $user ) continue;

					$iban_plain = Monetization_Crypto::decrypt( (string) $row->iban_enc );

					$line1   = (string) get_user_meta( $uid, 'sixarshidsc_address_line1',   true );
					$city    = (string) get_user_meta( $uid, 'sixarshidsc_address_city',    true );
					$postal  = (string) get_user_meta( $uid, 'sixarshidsc_address_postal',  true );
					$country = (string) get_user_meta( $uid, 'sixarshidsc_address_country', true );
					$address_parts = array_filter( array( $line1, $city, $postal, $country ) );

					$available       = 0.0;
					$pending_cashout = 0.0;
					$pending_tx_id   = 0;

					if ( $table_exists ) {
						$income = (float) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
							"SELECT COALESCE( SUM( amount - platform_fee ), 0 ) FROM {$transactions_table}
							  WHERE creator_id = %d AND type IN ('ppv','subscription') AND status = 'completed'",
							$uid
						) );
						$paid_out = (float) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
							"SELECT COALESCE( SUM( amount ), 0 ) FROM {$transactions_table}
							  WHERE creator_id = %d AND type = 'payout' AND status = 'completed'",
							$uid
						) );
						$pending_row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
							"SELECT id, amount FROM {$transactions_table}
							  WHERE creator_id = %d AND type = 'payout' AND status = 'pending'
							  ORDER BY created_at DESC LIMIT 1",
							$uid
						) );
						$pending_cashout = $pending_row ? (float) $pending_row->amount : 0.0;
						$pending_tx_id   = $pending_row ? (int) $pending_row->id      : 0;
						$available       = max( 0.0, $income - $paid_out - $pending_cashout );
					}
				?>
				<tr id="sixarshidsc-admin-row-<?php echo (int) $uid; ?>">
					<td>
						<strong><?php echo esc_html( $user->display_name ); ?></strong><br />
						<span style="color:#666;font-size:.85em;"><?php echo esc_html( $user->user_email ); ?></span><br />
						<a href="<?php echo esc_url( get_edit_user_link( $uid ) ); ?>" style="font-size:.85em;"><?php esc_html_e( 'Edit user', '6arshid-social-community' ); ?></a>
					</td>
					<td>
						<?php if ( '' !== $iban_plain ) : ?>
							<code style="font-size:.9em;background:#f0f0f0;padding:3px 6px;border-radius:3px;user-select:all;">
								<?php echo esc_html( $iban_plain ); ?>
							</code>
						<?php else : ?>
							<em style="color:#999;"><?php esc_html_e( '(encrypted — cannot decrypt)', '6arshid-social-community' ); ?></em>
						<?php endif; ?>
					</td>
					<?php if ( $table_exists ) : ?>
					<td style="color:#16a34a;font-weight:600;">
						<?php echo esc_html( number_format( $available, 2 ) . ' ' . $currency ); ?>
					</td>
					<td style="color:#d97706;font-weight:600;">
						<?php echo esc_html( $pending_cashout > 0 ? number_format( $pending_cashout, 2 ) . ' ' . $currency : '—' ); ?>
					</td>
					<?php endif; ?>
					<td style="font-size:.875em;color:#555;">
						<?php echo ! empty( $address_parts ) ? esc_html( implode( ', ', $address_parts ) ) : '—'; ?>
					</td>
					<?php if ( $table_exists ) : ?>
					<td>
						<?php if ( $pending_tx_id > 0 ) : ?>
							<button
								type="button"
								class="button button-primary sixarshidsc-mark-paid-btn"
								data-uid="<?php echo (int) $uid; ?>"
								data-tx-id="<?php echo (int) $pending_tx_id; ?>"
								data-nonce="<?php echo esc_attr( $admin_nonce ); ?>"
								data-amount="<?php echo esc_attr( number_format( $pending_cashout, 2 ) . ' ' . $currency ); ?>"
							>
								<?php esc_html_e( 'Mark as Paid', '6arshid-social-community' ); ?>
							</button>
						<?php else : ?>
							<span style="color:#999;font-size:.85em;"><?php esc_html_e( 'No request', '6arshid-social-community' ); ?></span>
						<?php endif; ?>
					</td>
					<?php endif; ?>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div style="margin-top:16px;">
					<?php
					echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'base'      => add_query_arg( 'paged', '%#%', $base_url ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $total_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					) );
					?>
				</div>
			<?php endif; ?>

			<?php
			$js_paid  = '(function(){';
			$js_paid .= 'var ajaxUrl=(window.ARSHID6SOCIALConfig&&window.ARSHID6SOCIALConfig.ajaxUrl)||' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';';
			$js_paid .= <<<'ENDJS'
document.querySelectorAll('.sixarshidsc-mark-paid-btn').forEach(function(btn){
	btn.addEventListener('click',async function(){
		var uid=btn.dataset.uid;var txId=btn.dataset.txId;var amount=btn.dataset.amount;
		if(!confirm('Mark cashout of '+amount+' as paid for this creator?'))return;
		btn.disabled=true;btn.textContent='Processing…';
		var fd=new FormData();fd.set('action','sixarshidsc_admin_mark_paid');fd.set('nonce',btn.dataset.nonce);fd.set('user_id',uid);fd.set('tx_id',txId);
		try{
			var resp=await fetch(ajaxUrl,{method:'POST',credentials:'same-origin',body:fd});var json=await resp.json();
			if(json.success){btn.textContent='✓ Paid';btn.style.background='#16a34a';btn.style.borderColor='#16a34a';}
			else{btn.disabled=false;btn.textContent='Mark as Paid';alert(json.data&&json.data.message?json.data.message:'Error.');}
		}catch{btn.disabled=false;btn.textContent='Mark as Paid';alert('Network error.');}
	});
});
})();
ENDJS;
			wp_add_inline_script( 'arshid6social-admin', $js_paid );
			?>
		<?php endif; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public function ajax_save_iban(): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in.', '6arshid-social-community' ) ), 401 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sixarshidsc_save_iban_' . $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community' ) ), 403 );
		}

		$raw_iban = isset( $_POST['iban'] ) ? strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( $_POST['iban'] ) ) ) ) : '';

		if ( '' === $raw_iban ) {
			wp_send_json_error( array( 'message' => __( 'IBAN cannot be empty.', '6arshid-social-community' ) ) );
		}

		// Basic IBAN format check: 2 letters + 2 digits + up to 30 alphanumeric chars.
		if ( ! preg_match( '/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $raw_iban ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid IBAN format. Please check and try again.', '6arshid-social-community' ) ) );
		}

		$encrypted = Monetization_Crypto::encrypt( $raw_iban );
		if ( '' === $encrypted ) {
			wp_send_json_error( array( 'message' => __( 'Could not save IBAN securely. Please contact support.', '6arshid-social-community' ) ) );
		}

		update_user_meta( $user_id, self::IBAN_META, $encrypted );

		wp_send_json_success( array( 'message' => __( 'IBAN saved.', '6arshid-social-community' ) ) );
	}

	public function ajax_save_address(): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in.', '6arshid-social-community' ) ), 401 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sixarshidsc_save_address_' . $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community' ) ), 403 );
		}

		$fields = array(
			'sixarshidsc_address_line1'   => 'line1',
			'sixarshidsc_address_city'    => 'city',
			'sixarshidsc_address_postal'  => 'postal',
			'sixarshidsc_address_country' => 'country',
		);

		foreach ( $fields as $meta_key => $post_key ) {
			$value = isset( $_POST[ $post_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) : '';
			update_user_meta( $user_id, $meta_key, $value );
		}

		wp_send_json_success( array( 'message' => __( 'Address saved.', '6arshid-social-community' ) ) );
	}

	/** Creator requests a cashout of their available balance. */
	public function ajax_request_cashout(): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in.', '6arshid-social-community' ) ), 401 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sixarshidsc_request_cashout_' . $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community' ) ), 403 );
		}

		$has_iban = '' !== (string) get_user_meta( $user_id, self::IBAN_META, true );
		if ( ! $has_iban ) {
			wp_send_json_error( array( 'message' => __( 'Please save your IBAN before requesting a cashout.', '6arshid-social-community' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'sixarshidsc_transactions';

		// Check there's no pending cashout already.
		$pending = (float) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COALESCE( SUM( amount ), 0 ) FROM {$table} WHERE creator_id = %d AND type = 'payout' AND status = 'pending'",
			$user_id
		) );
		if ( $pending > 0 ) {
			wp_send_json_error( array( 'message' => __( 'You already have a pending cashout request.', '6arshid-social-community' ) ) );
		}

		// Recalculate available balance server-side.
		$total_income = (float) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COALESCE( SUM( amount - platform_fee ), 0 ) FROM {$table}
			  WHERE creator_id = %d AND type IN ('ppv','subscription') AND status = 'completed'",
			$user_id
		) );
		$paid_out = (float) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COALESCE( SUM( amount ), 0 ) FROM {$table}
			  WHERE creator_id = %d AND type = 'payout' AND status = 'completed'",
			$user_id
		) );
		$available = $total_income - $paid_out;

		if ( $available <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'No balance available for cashout.', '6arshid-social-community' ) ) );
		}

		$currency = strtoupper( (string) get_option( 'sixarshidsc_currency', 'USD' ) );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'type'         => 'payout',
				'payer_id'     => 0,
				'creator_id'   => $user_id,
				'amount'       => $available,
				'platform_fee' => 0.00,
				'currency'     => $currency,
				'gateway'      => 'manual',
				'gateway_ref'  => 'cashout_request_' . $user_id . '_' . time(),
				'status'       => 'pending',
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %1$s = amount, %2$s = currency */
				__( 'Cashout of %1$s %2$s requested! The admin will transfer it to your IBAN.', '6arshid-social-community' ),
				number_format( $available, 2 ),
				$currency
			),
		) );
	}

	/** Admin-only: returns decrypted IBAN for a given user (used via AJAX if needed). */
	public function ajax_admin_get_iban(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'sixarshidsc_admin_payout' );

		$uid       = absint( $_POST['user_id'] ?? 0 );
		$enc       = (string) get_user_meta( $uid, self::IBAN_META, true );
		$plain     = Monetization_Crypto::decrypt( $enc );

		wp_send_json_success( array( 'iban' => $plain ) );
	}

	/** Admin-only: marks a specific pending payout transaction as completed (payout sent). */
	public function ajax_admin_mark_paid(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'sixarshidsc_admin_payout' );

		global $wpdb;
		$uid   = absint( $_POST['user_id'] ?? 0 );
		$tx_id = absint( $_POST['tx_id']   ?? 0 );

		if ( ! $uid ) {
			wp_send_json_error( array( 'message' => 'Invalid user.' ) );
		}

		$table = $wpdb->prefix . 'sixarshidsc_transactions';

		if ( $tx_id ) {
			// Mark specific payout transaction as completed.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array( 'status' => 'completed' ),
				array( 'id' => $tx_id, 'creator_id' => $uid, 'type' => 'payout', 'status' => 'pending' ),
				array( '%s' ),
				array( '%d', '%d', '%s', '%s' )
			);
		} else {
			// Fallback: mark all pending payouts for this creator.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array( 'status' => 'completed' ),
				array( 'creator_id' => $uid, 'type' => 'payout', 'status' => 'pending' ),
				array( '%s' ),
				array( '%d', '%s', '%s' )
			);
		}

		wp_send_json_success( array( 'message' => 'Marked as paid.' ) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function type_label( string $type ): string {
		$map = array(
			'subscription' => __( 'Subscription', '6arshid-social-community' ),
			'ppv'          => __( 'Pay-per-view', '6arshid-social-community' ),
			'refund'       => __( 'Refund', '6arshid-social-community' ),
			'payout'       => __( 'Payout', '6arshid-social-community' ),
		);
		return $map[ $type ] ?? ucfirst( $type );
	}

	private function status_class( string $status ): string {
		$map = array(
			'completed' => 'color:#16a34a;font-weight:600;',
			'pending'   => 'color:#d97706;',
			'failed'    => 'color:#dc2626;',
			'refunded'  => 'color:#6b7280;',
		);
		return $map[ $status ] ?? '';
	}
}
