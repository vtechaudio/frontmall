<?php
/**
 * Front-end performance - fully self-contained (no third-party optimisation
 * plugin required):
 *
 *  - Inlined critical CSS + asynchronously loaded full stylesheets, so the
 *    first paint is never blocked by render-blocking CSS on ANY page type.
 *  - System-font rendering (no web-font file to preload).
 *  - Removal of front-end head bloat (emoji, generator, oEmbed, RSD, etc.).
 *  - Responsive-aware LCP preload for the home hero slide (matches its srcset)
 *    and the single-product main image.
 *  - jQuery moved to the footer on the (script-light) front page, so it no
 *    longer blocks the initial render.
 *  - Fewer scripts on light pages: WooCommerce order-attribution / sourcebuster
 *    (and their polyfills) are dropped where they are not needed.
 *  - Fewer background requests (cart-fragments, heartbeat, wp-embed).
 *  - Edge-cacheable Cache-Control headers so anonymous pages (home AND inner)
 *    can be served straight from Cloudflare's cache at identical speed, while
 *    logged-in / cart / checkout responses stay strictly private.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Performance {

	private static ?Performance $instance = null;
	private static ?string $critical      = null;

	public static function instance(): Performance {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'init', array( $this, 'trim_head' ) );
		add_action( 'wp_head', array( $this, 'inline_critical_css' ), 2 );
		add_action( 'wp_head', array( $this, 'preload_lcp' ), 3 );
		add_filter( 'style_loader_tag', array( $this, 'async_styles' ), 20, 4 );
		add_action( 'wp_enqueue_scripts', array( $this, 'jquery_to_footer' ), 6 );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'trim_light_pages' ), 101 );
		add_action( 'wp_default_scripts', array( $this, 'drop_jquery_migrate' ) );
		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'wp_lazy_loading_enabled', '__return_true' );
		add_filter( 'heartbeat_settings', array( $this, 'slow_heartbeat' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'reduce_requests' ), 99 );
		add_action( 'send_headers', array( $this, 'cache_headers' ) );
	}

	public function trim_head(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'emoji_svg_url', '__return_false' );

		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	}

	/**
	 * Inline the small critical stylesheet so the shared shell paints on the
	 * first byte, before any external CSS is fetched. Loaded once per request.
	 */
	public function inline_critical_css(): void {
		$css = $this->critical_css();
		if ( '' !== $css ) {
			echo '<style id="fm-critical">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted theme file, CSS.
		}
	}

	private function critical_css(): string {
		if ( null !== self::$critical ) {
			return self::$critical;
		}
		$file           = FRONTMALL_DIR . '/assets/css/critical.min.css';
		self::$critical = is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : '';
		return self::$critical;
	}

	/**
	 * Turn every full stylesheet into a non-render-blocking preload that swaps
	 * to a stylesheet once loaded, with a <noscript> fallback. Skipped in the
	 * admin, the Customizer, and on cart / checkout / account pages, where a
	 * brief flash of unstyled content would hurt conversion more than it helps.
	 */
	public function async_styles( string $tag, string $handle, string $href, string $media ): string {
		if ( is_admin() || is_customize_preview() ) {
			return $tag;
		}
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return $tag;
		}
		if ( false === strpos( $tag, "rel='stylesheet'" ) && false === strpos( $tag, 'rel="stylesheet"' ) ) {
			return $tag;
		}
		$async = str_replace(
			array( "rel='stylesheet'", 'rel="stylesheet"' ),
			'rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"',
			$tag
		);
		return $async . '<noscript>' . $tag . '</noscript>';
	}

	/**
	 * Preload the largest-contentful image so the browser discovers it in the
	 * first HTML chunk: the first hero slide on the front page (with a matching
	 * imagesrcset so the responsive candidate is preloaded, not a wasted extra
	 * file), and the main product image on a single-product page.
	 */
	public function preload_lcp(): void {
		if ( is_front_page() && function_exists( 'frontmall_slides' ) ) {
			foreach ( frontmall_slides() as $s ) {
				if ( empty( $s['image'] ) ) {
					continue;
				}
				$id     = function_exists( 'frontmall_attachment_id_from_url' ) ? frontmall_attachment_id_from_url( $s['image'] ) : attachment_url_to_postid( $s['image'] );
				$srcset = $id ? wp_get_attachment_image_srcset( $id, 'full' ) : '';
				if ( $srcset ) {
					printf(
						'<link rel="preload" as="image" href="%s" imagesrcset="%s" imagesizes="%s" fetchpriority="high">' . "\n",
						esc_url( $s['image'] ),
						esc_attr( $srcset ),
						esc_attr( '(max-width: 991px) 100vw, 720px' )
					);
				} else {
					printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( $s['image'] ) );
				}
				break;
			}
			return;
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			$id    = get_queried_object_id();
			$thumb = $id ? get_post_thumbnail_id( $id ) : 0;
			if ( $thumb ) {
				$src = wp_get_attachment_image_url( $thumb, 'woocommerce_single' );
				if ( $src ) {
					printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( $src ) );
				}
			}
		}
	}

	/**
	 * Move jQuery (and its migrate shim) into the footer on the front page.
	 * The homepage is a fully custom, vanilla-JS template, so nothing in the
	 * <head>/body needs jQuery early - deferring it to the footer removes it
	 * from the render-blocking critical path. Scoped to the front page only so
	 * WooCommerce and plugin pages that may inline jQuery in the body are never
	 * affected.
	 */
	public function jquery_to_footer(): void {
		if ( is_admin() || ! is_front_page() ) {
			return;
		}
		global $wp_scripts;
		if ( ! ( $wp_scripts instanceof \WP_Scripts ) ) {
			return;
		}
		foreach ( array( 'jquery', 'jquery-core', 'jquery-migrate' ) as $handle ) {
			if ( isset( $wp_scripts->registered[ $handle ] ) ) {
				$wp_scripts->add_data( $handle, 'group', 1 );
			}
		}
	}

	/**
	 * A "light" page is one the theme renders itself and that has no cart,
	 * checkout, account or WooCommerce archive/product context.
	 */
	private function is_light_page(): bool {
		if ( is_admin() ) {
			return false;
		}
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return false;
		}
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return false;
		}
		return is_front_page() || is_home() || is_page();
	}

	/**
	 * Drop scripts that light pages never use. WooCommerce order-attribution +
	 * sourcebuster (and the wp-polyfill / underscore chain they pull in) only
	 * need to run where an order can start, so we keep them on shop / product /
	 * cart / checkout / account and remove them elsewhere. Filterable so you can
	 * re-add order-attribution on landing pages if you rely on it for marketing
	 * source tracking.
	 */
	public function trim_light_pages(): void {
		if ( ! $this->is_light_page() ) {
			return;
		}
		$drop = apply_filters(
			'frontmall_light_page_dequeue',
			array( 'wc-order-attribution', 'sourcebuster-js' )
		);
		foreach ( (array) $drop as $handle ) {
			wp_dequeue_script( (string) $handle );
		}
	}

	public function dequeue(): void {
		if ( is_admin() ) {
			return;
		}
		wp_dequeue_style( 'classic-theme-styles' );

		// Drop block-editor CSS where we render 100% of the markup ourselves.
		if ( $this->drop_block_css() ) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'wp-block-library-theme' );
			wp_dequeue_style( 'global-styles' );
			wp_dequeue_style( 'wc-blocks-style' );
		}

		// The homepage is a fully custom template - it does not need the heavy
		// WooCommerce core stylesheets (~110KB). On inner pages these now load
		// asynchronously (see async_styles) so they never block first paint.
		if ( is_front_page() ) {
			foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'wc-blocks-packages-style' ) as $h ) {
				wp_dequeue_style( $h );
			}
		}
	}

	private function drop_block_css(): bool {
		if ( is_front_page() ) {
			return true;
		}
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) {
			return true;
		}
		return false;
	}

	public function drop_jquery_migrate( \WP_Scripts $scripts ): void {
		if ( is_admin() ) {
			return;
		}
		$jq = $scripts->registered['jquery'] ?? null;
		if ( $jq && ! empty( $jq->deps ) ) {
			$jq->deps = array_diff( $jq->deps, array( 'jquery-migrate' ) );
		}
	}

	public function slow_heartbeat( array $settings ): array {
		$settings['interval'] = 60;
		return $settings;
	}

	/**
	 * Cut recurring background requests that waste bandwidth and admin-ajax
	 * capacity: WooCommerce cart-fragments polling (fires on every page load),
	 * the front-end Heartbeat API, and the unused wp-embed script. All are kept
	 * on the cart/checkout where WooCommerce may genuinely need them.
	 */
	public function reduce_requests(): void {
		if ( is_admin() ) {
			return;
		}
		wp_deregister_script( 'wp-embed' );
		$keep = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() );
		if ( ! $keep ) {
			wp_dequeue_script( 'wc-cart-fragments' );
			wp_deregister_script( 'heartbeat' );
		}
	}

	/**
	 * Emit Cache-Control headers that let a CDN (Cloudflare) cache anonymous
	 * HTML at the edge (so inner pages serve as fast as the homepage), while
	 * keeping anything user-specific strictly private. s-maxage governs the
	 * shared/edge cache; max-age=0 keeps the browser revalidating.
	 */
	public function cache_headers(): void {
		if ( headers_sent() || is_admin() ) {
			return;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return;
		}

		$no_cache = is_user_logged_in() || is_preview() || is_customize_preview();

		if ( ! $no_cache && function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			$no_cache = true;
		}
		if ( ! $no_cache && function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
			$no_cache = true;
		}
		if ( ! $no_cache && ! empty( $_COOKIE ) && is_array( $_COOKIE ) ) {
			foreach ( array_keys( $_COOKIE ) as $cookie ) {
				$cookie = (string) $cookie;
				if (
					0 === strpos( $cookie, 'wordpress_logged_in' )
					|| 0 === strpos( $cookie, 'woocommerce_items_in_cart' )
					|| 0 === strpos( $cookie, 'wp_woocommerce_session' )
					|| 0 === strpos( $cookie, 'comment_author' )
				) {
					$no_cache = true;
					break;
				}
			}
		}

		if ( $no_cache ) {
			header( 'Cache-Control: private, no-cache, no-store, max-age=0, must-revalidate' );
			header( 'Pragma: no-cache' );
		} else {
			header( 'Cache-Control: public, max-age=0, s-maxage=3600, stale-while-revalidate=86400' );
			header( 'Vary: Accept-Encoding, Cookie' );
		}
	}
}
