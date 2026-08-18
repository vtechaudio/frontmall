<?php
/**
 * Security hardening: safe HTTP response headers, XML-RPC + user-enumeration
 * lockdown, REST user-endpoint restriction for guests, wp_head cleanup
 * (version/RSD/WLW/shortlink), a per-IP failed-login throttle, a reusable
 * honeypot check, and a light per-IP rate limiter for public AJAX. Nothing
 * here edits wp-config or the database, so it is safe to ship inside a theme.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Security {

	private static ?Security $instance = null;

	public static function instance(): Security {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', array( $this, 'kill_pingback_methods' ) );
		add_filter( 'wp_headers', array( $this, 'strip_pingback_header' ) );
		add_action( 'send_headers', array( $this, 'headers' ) );
		add_action( 'template_redirect', array( $this, 'block_author_scan' ) );
		add_filter( 'rest_endpoints', array( $this, 'lock_rest_users' ) );
		add_filter( 'login_errors', array( $this, 'generic_login_error' ) );
		add_filter( 'pings_open', '__return_false', 20 );

		// Stop leaking software version / discovery links from <head>.
		add_action( 'init', array( $this, 'cleanup_head' ) );
		add_filter( 'the_generator', '__return_empty_string' );

		// Brute-force throttle for wp-login.
		add_action( 'wp_login_failed', array( $this, 'note_failed_login' ) );
		add_filter( 'authenticate', array( $this, 'throttle_login' ), 30 );
	}

	public function kill_pingback_methods( array $methods ): array {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	public function strip_pingback_header( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	public function headers(): void {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'X-Permitted-Cross-Domain-Policies: none' );
		header( 'Permissions-Policy: browsing-topics=(), interest-cohort=()' );
		header( 'X-XSS-Protection: 0' );
		header( 'Cross-Origin-Opener-Policy: same-origin-allow-popups' );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=15552000; includeSubDomains' );
		}
	}

	/**
	 * Remove version numbers and legacy discovery links from wp_head.
	 */
	public function cleanup_head(): void {
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
	}

	public function block_author_scan(): void {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		if ( isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	public function lock_rest_users( array $endpoints ): array {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		foreach ( array( '/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)' ) as $route ) {
			if ( isset( $endpoints[ $route ] ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	public function generic_login_error(): string {
		return __( 'The username or password you entered is incorrect.', 'frontmall' );
	}

	/**
	 * Record a failed login against the caller IP (15-minute rolling window).
	 */
	public function note_failed_login( $username ): void {
		unset( $username );
		$key = 'fm_lf_' . md5( self::ip() );
		$n   = (int) get_transient( $key );
		set_transient( $key, $n + 1, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Block further login attempts from an IP after 10 failures in the window.
	 *
	 * @param mixed $user WP_User, WP_Error, or null from earlier filters.
	 * @return mixed
	 */
	public function throttle_login( $user ) {
		$key = 'fm_lf_' . md5( self::ip() );
		if ( (int) get_transient( $key ) >= 10 ) {
			return new \WP_Error( 'fm_locked', __( 'Too many failed login attempts. Please wait a few minutes and try again.', 'frontmall' ) );
		}
		return $user;
	}

	/**
	 * Best-effort caller IP.
	 */
	private static function ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	}

	/**
	 * Per-IP token bucket for public AJAX. Returns true when within the limit.
	 */
	public static function rate_ok( string $bucket, int $max = 40, int $window = 60 ): bool {
		$key  = 'fm_rl_' . md5( $bucket . '|' . self::ip() );
		$hits = (int) get_transient( $key );
		if ( $hits >= $max ) {
			return false;
		}
		set_transient( $key, $hits + 1, $window );
		return true;
	}

	/**
	 * Honeypot check. Pair with a hidden field named fm_hp in any public form.
	 */
	public static function is_spam_submission( string $field = 'fm_hp' ): bool {
		return ! empty( $_POST[ $field ] ); // phpcs:ignore WordPress.Security.NonceVerification
	}
}
