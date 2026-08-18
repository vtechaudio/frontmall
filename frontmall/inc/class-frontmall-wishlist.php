<?php
/**
 * Native, plugin-free wishlist. Items are stored client-side in localStorage
 * (no database bloat, instant, works for guests). This class provides the
 * [frontmall_wishlist] page, a rate-limited AJAX endpoint that renders product
 * cards for saved IDs, and one-time creation of the Wishlist page. It exists so
 * the store never needs a heavy React wishlist plugin.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Wishlist {

	private static ?Wishlist $instance = null;

	public static function instance(): Wishlist {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_shortcode( 'frontmall_wishlist', array( $this, 'shortcode' ) );
		add_action( 'wp_ajax_frontmall_wishlist_items', array( $this, 'items' ) );
		add_action( 'wp_ajax_nopriv_frontmall_wishlist_items', array( $this, 'items' ) );
		add_action( 'after_switch_theme', array( $this, 'ensure_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_ensure_page' ) );
	}

	public function shortcode(): string {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
		ob_start();
		?>
		<div class="fm-wishlist" data-fm-wishlist>
			<div class="fm-wishlist__loading" data-loading><?php esc_html_e( 'Loading your saved items...', 'frontmall' ); ?></div>
			<div class="fm-wishlist__empty" data-empty hidden>
				<div class="fm-empty">
					<span class="fm-empty__icon" aria-hidden="true">&#9825;</span>
					<h2><?php esc_html_e( 'Your wishlist is empty', 'frontmall' ); ?></h2>
					<p><?php esc_html_e( 'Tap the heart on any product to save it here for later.', 'frontmall' ); ?></p>
					<a class="fm-btn" href="<?php echo esc_url( $shop ); ?>"><?php esc_html_e( 'Start shopping', 'frontmall' ); ?></a>
				</div>
			</div>
			<div class="fm-grid fm-grid--products" data-items hidden></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function items(): void {
		check_ajax_referer( 'frontmall_wishlist', 'nonce' );
		if ( ! Security::rate_ok( 'wishlist', 60, 60 ) ) {
			wp_send_json_success( array( 'html' => '', 'count' => 0 ) );
		}
		$raw = isset( $_GET['ids'] ) ? sanitize_text_field( wp_unslash( $_GET['ids'] ) ) : ''; // phpcs:ignore
		$ids = array_filter( array_unique( array_map( 'absint', explode( ',', $raw ) ) ) );
		if ( empty( $ids ) || ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_success( array( 'html' => '', 'count' => 0 ) );
		}
		$ids = array_slice( $ids, 0, 60 );
		ob_start();
		$n = 0;
		foreach ( $ids as $id ) {
			if ( 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( $product instanceof \WC_Product && function_exists( 'frontmall_product_card' ) ) {
				frontmall_product_card( $product );
				$n++;
			}
		}
		wp_send_json_success( array( 'html' => ob_get_clean(), 'count' => $n ) );
	}

	public function ensure_page(): void {
		if ( get_page_by_path( 'wishlist' ) ) {
			return;
		}
		$id = wp_insert_post(
			array(
				'post_title'   => __( 'Wishlist', 'frontmall' ),
				'post_name'    => 'wishlist',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[frontmall_wishlist]',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( 'frontmall_wishlist_page', (int) $id );
		}
	}

	public function maybe_ensure_page(): void {
		if ( get_option( 'frontmall_wishlist_ready' ) ) {
			return;
		}
		$this->ensure_page();
		update_option( 'frontmall_wishlist_ready', 1 );
	}
}
