<?php
/**
 * Spam-hardened newsletter capture with no external scripts. Subscribers are
 * validated with a nonce, a honeypot, a submit-time trap, a per-IP rate limit
 * and email validation, then stored as a private "Subscriber" post type you
 * can view and export from wp-admin. No reCAPTCHA, so no extra bandwidth.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Newsletter {

	private static ?Newsletter $instance = null;

	public static function instance(): Newsletter {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'wp_ajax_frontmall_subscribe', array( $this, 'subscribe' ) );
		add_action( 'wp_ajax_nopriv_frontmall_subscribe', array( $this, 'subscribe' ) );
		add_action( 'admin_menu', array( $this, 'export_menu' ) );
	}

	public function register_cpt(): void {
		register_post_type(
			'fm_subscriber',
			array(
				'labels'       => array(
					'name'          => __( 'Subscribers', 'frontmall' ),
					'singular_name' => __( 'Subscriber', 'frontmall' ),
					'menu_name'     => __( 'Subscribers', 'frontmall' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-email-alt',
				'menu_position'=> 26,
				'supports'     => array( 'title' ),
				'map_meta_cap' => true,
				'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			)
		);
	}

	public function subscribe(): void {
		$nonce = isset( $_POST['fm_nl_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['fm_nl_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'frontmall_subscribe' ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Please refresh and try again.', 'frontmall' ) ) );
		}

		// Honeypot: pretend success, store nothing.
		if ( Security::is_spam_submission( 'fm_hp' ) ) {
			wp_send_json_success( array( 'message' => __( 'Thanks for subscribing!', 'frontmall' ) ) );
		}

		// Time trap: humans take a moment; bots submit instantly. Silently drop.
		$t     = isset( $_POST['fm_nl_t'] ) ? absint( $_POST['fm_nl_t'] ) : 0;
		$delta = time() - $t;
		if ( $t <= 0 || $delta < 2 || $delta > HOUR_IN_SECONDS ) {
			wp_send_json_success( array( 'message' => __( 'Thanks for subscribing!', 'frontmall' ) ) );
		}

		if ( ! Security::rate_ok( 'subscribe', 8, 600 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many attempts. Please try again later.', 'frontmall' ) ) );
		}

		$email = isset( $_POST['fm_nl_email'] ) ? sanitize_email( wp_unslash( $_POST['fm_nl_email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'frontmall' ) ) );
		}

		$dupe = new \WP_Query(
			array(
				'post_type'      => 'fm_subscriber',
				'title'          => $email,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( $dupe->have_posts() ) {
			wp_send_json_success( array( 'message' => __( 'You are already on the list. Thank you!', 'frontmall' ) ) );
		}

		$id = wp_insert_post(
			array(
				'post_type'   => 'fm_subscriber',
				'post_title'  => $email,
				'post_status' => 'publish',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			update_post_meta( $id, '_fm_ip', $ip );
			update_post_meta( $id, '_fm_source', esc_url_raw( (string) wp_get_referer() ) );
			update_post_meta( $id, '_fm_date', current_time( 'mysql' ) );
		}
		wp_send_json_success( array( 'message' => __( 'Thanks for subscribing!', 'frontmall' ) ) );
	}

	public function export_menu(): void {
		add_submenu_page(
			'edit.php?post_type=fm_subscriber',
			__( 'Export Subscribers', 'frontmall' ),
			__( 'Export CSV', 'frontmall' ),
			'manage_options',
			'fm-subscribers-export',
			array( $this, 'export_page' )
		);
	}

	public function export_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_GET['fm_do_export'] ) && check_admin_referer( 'fm_export' ) ) {
			$this->stream_csv();
		}
		$url = wp_nonce_url( add_query_arg( 'fm_do_export', '1' ), 'fm_export' );
		echo '<div class="wrap"><h1>' . esc_html__( 'Export Subscribers', 'frontmall' ) . '</h1>';
		echo '<p>' . esc_html__( 'Download every confirmed newsletter subscriber as a CSV file.', 'frontmall' ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Download CSV', 'frontmall' ) . '</a></p></div>';
	}

	private function stream_csv(): void {
		$q = new \WP_Query(
			array(
				'post_type'      => 'fm_subscriber',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=frontmall-subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Email', 'Subscribed', 'Source', 'IP' ) );
		foreach ( $q->posts as $post ) {
			fputcsv(
				$out,
				array(
					$post->post_title,
					(string) get_post_meta( $post->ID, '_fm_date', true ),
					(string) get_post_meta( $post->ID, '_fm_source', true ),
					(string) get_post_meta( $post->ID, '_fm_ip', true ),
				)
			);
		}
		fclose( $out );
		exit;
	}
}
