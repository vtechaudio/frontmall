<?php
/**
 * Front-end asset loading with performance in mind: minified stylesheets,
 * deferred JS, self preconnect, conditional WooCommerce CSS and AJAX
 * localization. The full stylesheets are made non-render-blocking by the
 * Performance class (inlined critical CSS + async swap), so here we simply
 * enqueue the smallest possible files.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Assets {

	private static ?Assets $instance = null;

	public static function instance(): Assets {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'script_loader_tag', array( $this, 'defer' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'resource_hints' ), 1 );
		add_filter( 'wp_resource_hints', array( $this, 'preconnect' ), 10, 2 );
	}

	/**
	 * Return the minified asset path when the .min file exists, otherwise fall
	 * back to the readable source (keeps a dev checkout working).
	 */
	private function asset( string $rel_min, string $rel_src ): string {
		$path = FRONTMALL_DIR . '/' . ltrim( $rel_min, '/' );
		return is_readable( $path ) ? $rel_min : $rel_src;
	}

	public function enqueue(): void {
		$v = FRONTMALL_VERSION;

		wp_enqueue_style(
			'frontmall-main',
			FRONTMALL_URI . '/' . $this->asset( 'assets/css/main.min.css', 'assets/css/main.css' ),
			array(),
			$v
		);
		wp_style_add_data( 'frontmall-main', 'rtl', 'replace' );

		if ( function_exists( 'is_woocommerce' ) ) {
			wp_enqueue_style(
				'frontmall-woocommerce',
				FRONTMALL_URI . '/' . $this->asset( 'assets/css/woocommerce.min.css', 'assets/css/woocommerce.css' ),
				array( 'frontmall-main' ),
				$v
			);
		}

		wp_enqueue_script( 'frontmall-main', FRONTMALL_URI . '/assets/js/main.js', array(), $v, true );
		wp_enqueue_script( 'frontmall-search', FRONTMALL_URI . '/assets/js/ajax-search.js', array(), $v, true );
		wp_enqueue_script( 'frontmall-cart', FRONTMALL_URI . '/assets/js/ajax-cart.js', array(), $v, true );

		wp_localize_script(
			'frontmall-main',
			'FRONTMALL',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'searchNonce'  => wp_create_nonce( 'frontmall_search' ),
				'cartNonce'    => wp_create_nonce( 'frontmall_cart' ),
				'wishlistNonce' => wp_create_nonce( 'frontmall_wishlist' ),
				'quickviewNonce' => wp_create_nonce( 'frontmall_quickview' ),
				'cartUrl'      => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
				'checkoutUrl'  => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
				'isRtl'        => is_rtl(),
				'i18n'         => array(
					'searching'  => __( 'Searching...', 'frontmall' ),
					'noResults'  => __( 'No products found. Try another keyword.', 'frontmall' ),
					'added'      => __( 'Added to cart', 'frontmall' ),
					'viewCart'   => __( 'View cart', 'frontmall' ),
					'error'      => __( 'Something went wrong. Please try again.', 'frontmall' ),
					'checkout'   => __( 'Checkout', 'frontmall' ),
					'subtotal'   => __( 'Subtotal', 'frontmall' ),
					'inCart'     => __( 'Added to your cart', 'frontmall' ),
					'qty'        => __( 'Qty', 'frontmall' ),
					'continueShopping' => __( 'Continue shopping', 'frontmall' ),
					'wishAdded'  => __( 'Saved to wishlist', 'frontmall' ),
					'wishRemoved'=> __( 'Removed from wishlist', 'frontmall' ),
				),
			)
		);

		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}

	/**
	 * Non-render-blocking JS.
	 */
	public function defer( string $tag, string $handle ): string {
		$deferred = array( 'frontmall-main', 'frontmall-search', 'frontmall-cart' );
		if ( in_array( $handle, $deferred, true ) && ! is_admin() ) {
			return str_replace( ' src', ' defer src', $tag );
		}
		return $tag;
	}

	public function resource_hints(): void {
		echo "<link rel='preconnect' href='" . esc_url( home_url() ) . "' crossorigin>\n";
	}

	public function preconnect( array $hints, string $relation ): array {
		return $hints;
	}
}
