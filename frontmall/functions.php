<?php
/**
 * Frontmall theme bootstrap.
 *
 * @package Frontmall
 */

defined( 'ABSPATH' ) || exit;

define( 'FRONTMALL_VERSION', '0.3.4' );
define( 'FRONTMALL_DIR', get_template_directory() );
define( 'FRONTMALL_URI', get_template_directory_uri() );
define( 'FRONTMALL_MIN_PHP', '8.1' );

// Defence in depth: disable the built-in theme/plugin file editors so a
// compromised admin session cannot edit PHP from wp-admin. Guarded so a
// value already set in wp-config.php always wins.
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

/**
 * Fail gracefully on unsupported PHP instead of white-screening.
 */
if ( version_compare( PHP_VERSION, FRONTMALL_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Frontmall requires PHP 8.1 or higher. Please upgrade your hosting PHP version.', 'frontmall' );
			echo '</p></div>';
		}
	);
	return;
}

require_once FRONTMALL_DIR . '/inc/template-tags.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-setup.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-assets.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-woocommerce.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-ajax.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-plugins.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-schema.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-pages.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-merchant-compliance.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-customizer.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-performance.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-security.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-seo.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-filters.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-wishlist.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-storefront.php';
require_once FRONTMALL_DIR . '/inc/class-frontmall-newsletter.php';

// Boot singletons. Each class registers its own hooks in its constructor.
Frontmall\Setup::instance();
Frontmall\Assets::instance();
Frontmall\WooCommerce::instance();
Frontmall\Ajax::instance();
Frontmall\Plugins::instance();
Frontmall\Schema::instance();
Frontmall\Pages::instance();
Frontmall\Merchant_Compliance::instance();
Frontmall\Customizer::instance();
Frontmall\Performance::instance();
Frontmall\Security::instance();
Frontmall\SEO::instance();
Frontmall\Filters::instance();
Frontmall\Wishlist::instance();
Frontmall\Storefront::instance();
Frontmall\Newsletter::instance();

add_action( 'wp_footer', 'frontmall_render_wa_float' );

/**
 * Keep the header logo eager, high-priority and correctly sized.
 *
 * When a front-end optimisation plugin JS-lazy-loads the site logo it strips
 * the intrinsic width/height, so the logo renders "unsized" and shifts the
 * header on load - a Cumulative Layout Shift regression that shows up on
 * mobile. Forcing loading="eager" + fetchpriority="high" keeps the logo out of
 * any lazy-load queue and prioritises it; the width/height WordPress already
 * emits is preserved, so the header box is reserved from first paint.
 */
add_filter(
	'get_custom_logo',
	static function ( $html ) {
		if ( ! is_string( $html ) || false === strpos( $html, '<img' ) ) {
			return $html;
		}
		return preg_replace_callback(
			'/<img\b[^>]*>/',
			static function ( $m ) {
				$tag = $m[0];
				if ( false === strpos( $tag, 'fetchpriority=' ) ) {
					$tag = str_replace( '<img', '<img fetchpriority="high"', $tag );
				}
				if ( preg_match( '/\sloading=(["\'])[^"\']*\1/', $tag ) ) {
					$tag = preg_replace( '/\sloading=(["\'])[^"\']*\1/', ' loading="eager"', $tag );
				} else {
					$tag = str_replace( '<img', '<img loading="eager"', $tag );
				}
				if ( false === strpos( $tag, 'decoding=' ) ) {
					$tag = str_replace( '<img', '<img decoding="async"', $tag );
				}
				return $tag;
			},
			$html,
			1
		);
	}
);
