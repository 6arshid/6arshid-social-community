<?php
namespace Arshid6Social\REST;

/**
 * Authentication REST endpoints.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Browser-oriented auth API for the plugin splash forms.
 */
final class Auth_Controller {

	private const NAMESPACE = 'arshid6social/v1';
	private const RATE_TTL  = 15 * MINUTE_IN_SECONDS;

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/auth/register',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register_user' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'username' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => static fn( $value ) => sanitize_user( (string) $value, true ),
					),
					'email'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => static fn( $value ) => (bool) is_email( (string) $value ),
					),
					'password' => array(
						'type'     => 'string',
						'required' => true,
					),
					'hp_field' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/login',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'login' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'username' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'password' => array(
						'type'     => 'string',
						'required' => true,
					),
					'remember' => array(
						'type'              => 'boolean',
						'required'          => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/logout',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'logout' ),
				'permission_callback' => array( $this, 'require_login' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/me',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'me' ),
				'permission_callback' => array( $this, 'require_login' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/forgot-password',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'forgot_password' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'user_login' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/reset-password',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_password' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'key'       => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'login'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'password1' => array(
						'type'     => 'string',
						'required' => true,
					),
					'password2' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	public function register_user( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( is_user_logged_in() ) {
			return new \WP_Error( 'arshid6social_already_logged_in', __( 'You are already logged in.', '6arshid-social-community' ), array( 'status' => 400 ) );
		}

		$rate = $this->rate_limit( 'register', 10 );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		if ( ! get_option( 'arshid6social_allow_registration', true ) || ! get_option( 'users_can_register' ) ) {
			return new \WP_Error( 'arshid6social_registration_closed', __( 'Registration is currently closed.', '6arshid-social-community' ), array( 'status' => 403 ) );
		}

		if ( '' !== (string) $request->get_param( 'hp_field' ) ) {
			return new \WP_Error( 'arshid6social_registration_rejected', __( 'Registration could not be completed.', '6arshid-social-community' ), array( 'status' => 400 ) );
		}

		$username = sanitize_user( (string) $request->get_param( 'username' ), true );
		$email    = sanitize_email( (string) $request->get_param( 'email' ) );
		$password = (string) $request->get_param( 'password' );

		$errors = new \WP_Error();
		if ( '' === $username ) {
			$errors->add( 'empty_username', __( 'Please enter a username.', '6arshid-social-community' ) );
		} else {
			$restriction_errors = \Arshid6Social\Components\Members\Members::validate_username_restrictions( $username );
			if ( $restriction_errors->has_errors() ) {
				foreach ( $restriction_errors->get_error_codes() as $code ) {
					$errors->add( $code, $restriction_errors->get_error_message( $code ) );
				}
			} elseif ( username_exists( $username ) ) {
				$errors->add( 'username_exists', __( 'That username is already taken.', '6arshid-social-community' ) );
			}
		}

		if ( ! is_email( $email ) ) {
			$errors->add( 'invalid_email', __( 'Please enter a valid email address.', '6arshid-social-community' ) );
		} elseif ( email_exists( $email ) ) {
			$errors->add( 'email_exists', __( 'That email is already registered.', '6arshid-social-community' ) );
		}

		if ( strlen( $password ) < 8 ) {
			$errors->add( 'weak_password', __( 'Password must be at least 8 characters.', '6arshid-social-community' ) );
		}

		if ( $errors->has_errors() ) {
			return new \WP_Error(
				'arshid6social_registration_failed',
				implode( ' ', $errors->get_error_messages() ),
				array(
					'status' => 400,
					'errors' => $errors->errors,
				)
			);
		}

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error( 'arshid6social_registration_failed', $user_id->get_error_message(), array( 'status' => 400 ) );
		}

		wp_set_current_user( (int) $user_id );
		wp_set_auth_cookie( (int) $user_id, true, is_ssl() );
		do_action( 'arshid6social_user_registered', (int) $user_id );

		$response = rest_ensure_response(
			array(
				'success'  => true,
				'user'     => $this->safe_user( get_userdata( (int) $user_id ) ),
				'redirect' => $this->profile_url( (int) $user_id ),
			)
		);
		$response->set_status( 201 );
		return $response;
	}

	public function login( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( is_user_logged_in() ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'user'    => $this->safe_user( wp_get_current_user() ),
				)
			);
		}

		$rate = $this->rate_limit( 'login', 8 );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$creds = array(
			'user_login'    => (string) $request->get_param( 'username' ),
			'user_password' => (string) $request->get_param( 'password' ),
			'remember'      => (bool) $request->get_param( 'remember' ),
		);

		$user = wp_signon( $creds, is_ssl() );
		if ( is_wp_error( $user ) ) {
			return new \WP_Error( 'arshid6social_invalid_credentials', __( 'Invalid username, email, or password.', '6arshid-social-community' ), array( 'status' => 401 ) );
		}

		wp_set_current_user( $user->ID );

		return rest_ensure_response(
			array(
				'success'  => true,
				'user'     => $this->safe_user( $user ),
				'redirect' => $this->default_redirect(),
			)
		);
	}

	public function logout(): \WP_REST_Response {
		wp_logout();
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function me(): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'success' => true,
				'user'    => $this->safe_user( wp_get_current_user() ),
			)
		);
	}

	public function forgot_password( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$rate = $this->rate_limit( 'forgot', 5 );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$user_login = trim( (string) $request->get_param( 'user_login' ) );
		if ( '' === $user_login ) {
			return new \WP_Error( 'arshid6social_empty_login', __( 'Please enter your email or username.', '6arshid-social-community' ), array( 'status' => 400 ) );
		}

		$user = false !== strpos( $user_login, '@' )
			? get_user_by( 'email', sanitize_email( $user_login ) )
			: get_user_by( 'login', sanitize_user( $user_login ) );

		$message = __( 'If an account with that information exists, a reset link has been sent.', '6arshid-social-community' );
		if ( ! $user || ! $user->exists() ) {
			return rest_ensure_response( array( 'message' => $message ) );
		}

		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return new \WP_Error( 'arshid6social_reset_key_failed', __( 'Could not generate reset link. Please try again.', '6arshid-social-community' ), array( 'status' => 500 ) );
		}

		$reset_id  = (int) get_option( 'arshid6social_page_reset_password', 0 );
		$reset_url = $reset_id ? get_permalink( $reset_id ) : home_url( '/reset-password/' );
		$url       = add_query_arg(
			array(
				'key'   => rawurlencode( $key ),
				'login' => rawurlencode( $user->user_login ),
			),
			$reset_url
		);

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Password Reset', '6arshid-social-community' ),
			get_bloginfo( 'name' )
		);
		$body    = sprintf(
			/* translators: 1: user email, 2: reset link URL */
			__( "Someone requested a password reset for the account with email %1\$s.\n\nIf this was a mistake, ignore this email.\n\nTo reset your password, visit:\n\n%2\$s\n\nThis link expires in 24 hours.", '6arshid-social-community' ),
			$user->user_email,
			$url
		);

		wp_mail( $user->user_email, $subject, $body );
		return rest_ensure_response( array( 'message' => $message ) );
	}

	public function reset_password( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$rate = $this->rate_limit( 'reset', 5 );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$key   = (string) $request->get_param( 'key' );
		$login = (string) $request->get_param( 'login' );
		$user  = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			return new \WP_Error( 'arshid6social_invalid_reset_link', __( 'This password reset link is invalid or has expired. Please request a new one.', '6arshid-social-community' ), array( 'status' => 400 ) );
		}

		$pass1 = (string) $request->get_param( 'password1' );
		$pass2 = (string) $request->get_param( 'password2' );
		if ( strlen( $pass1 ) < 8 ) {
			return new \WP_Error( 'arshid6social_weak_password', __( 'Password must be at least 8 characters.', '6arshid-social-community' ), array( 'status' => 400 ) );
		}
		if ( $pass1 !== $pass2 ) {
			return new \WP_Error( 'arshid6social_password_mismatch', __( 'Passwords do not match.', '6arshid-social-community' ), array( 'status' => 400 ) );
		}

		reset_password( $user, $pass1 );
		return rest_ensure_response( array( 'message' => __( 'Your password has been reset. You can now log in.', '6arshid-social-community' ) ) );
	}

	public function require_login(): true|\WP_Error {
		if ( is_user_logged_in() ) {
			return true;
		}
		return new \WP_Error( 'arshid6social_login_required', __( 'Authentication required.', '6arshid-social-community' ), array( 'status' => 401 ) );
	}

	private function rate_limit( string $scope, int $limit ): true|\WP_Error {
		$key   = 'a6sc_auth_' . $scope . '_' . md5( $this->request_ip() );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return new \WP_Error( 'arshid6social_rate_limited', __( 'Too many attempts. Please try again later.', '6arshid-social-community' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, self::RATE_TTL );
		return true;
	}

	private function request_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		return preg_replace( '/[^0-9a-fA-F:\.]/', '', $ip ) ?: '0.0.0.0';
	}

	private function safe_user( \WP_User $user ): array {
		return array(
			'id'           => (int) $user->ID,
			'username'     => $user->user_login,
			'display_name' => $user->display_name,
			'nicename'     => $user->user_nicename,
			'avatar_url'   => get_avatar_url( $user->ID ),
		);
	}

	private function profile_url( int $user_id ): string {
		$user = get_userdata( $user_id );
		return $user ? home_url( '/members/' . $user->user_nicename . '/' ) : $this->default_redirect();
	}

	private function default_redirect(): string {
		$activity_id = (int) get_option( 'arshid6social_page_activity', 0 );
		return $activity_id ? (string) get_permalink( $activity_id ) : home_url( '/activity/' );
	}
}
