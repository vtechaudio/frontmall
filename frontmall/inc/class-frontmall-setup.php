<?php
/**
 * Theme setup: supports, menus, image sizes, widgets.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Setup {

	private static ?Setup $instance = null;

	public static function instance(): Setup {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'after_setup_theme', array( $this, 'supports' ) );
		add_action( 'init', array( $this, 'menus' ) );
		add_action( 'widgets_init', array( $this, 'sidebars' ) );
		add_action( 'after_switch_theme', array( $this, 'ensure_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_ensure_menu' ) );
	}

	public function supports(): void {
		load_theme_textdomain( 'frontmall', FRONTMALL_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'customize-selective-refresh-widgets' );
		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
		);
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 60,
				'width'       => 220,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);

		// WooCommerce + gallery features.
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );

		// 1:1 catalog imagery for uniform product cards.
		add_image_size( 'frontmall-card', 400, 400, true );
		add_image_size( 'frontmall-category', 500, 500, true );

		register_default_headers( array() );
	}

	public function menus(): void {
		register_nav_menus(
			array(
				'primary'    => __( 'Primary Navigation', 'frontmall' ),
				'departments'=> __( 'Shop By Department (Vertical Menu)', 'frontmall' ),
				'footer'     => __( 'Footer Quick Links', 'frontmall' ),
			)
		);
	}

	public function sidebars(): void {
		foreach ( array( 1, 2, 3, 4 ) as $i ) {
			register_sidebar(
				array(
					'name'          => sprintf( __( 'Footer Column %d', 'frontmall' ), $i ),
					'id'            => 'footer-' . $i,
					'before_widget' => '<div class="fm-footer-widget %2$s">',
					'after_widget'  => '</div>',
					'before_title'  => '<h4 class="fm-footer-widget__title">',
					'after_title'   => '</h4>',
				)
			);
		}
	}

	/**
	 * On first activation, if no menu is assigned to the primary location,
	 * build a sensible "Frontmall Primary" menu and assign it so the header
	 * navigation is wired up out of the box.
	 */
	public function ensure_menu(): void {
		$locations = get_theme_mod( 'nav_menu_locations' );
		if ( is_array( $locations ) && ! empty( $locations['primary'] ) && wp_get_nav_menu_object( $locations['primary'] ) ) {
			return;
		}
		$name = __( 'Frontmall Primary', 'frontmall' );
		$menu = wp_get_nav_menu_object( $name );
		if ( $menu ) {
			$menu_id = (int) $menu->term_id;
		} else {
			$menu_id = wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				return;
			}
			$shop  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
			$items = array(
				array( __( 'Home', 'frontmall' ), home_url( '/' ) ),
				array( __( 'Shop', 'frontmall' ), $shop ),
				array( __( 'About Us', 'frontmall' ), home_url( '/about-us/' ) ),
				array( __( 'Shipping', 'frontmall' ), home_url( '/shipping-delivery-policy/' ) ),
				array( __( 'Returns', 'frontmall' ), home_url( '/return-refund-policy/' ) ),
				array( __( 'FAQ', 'frontmall' ), home_url( '/frequently-asked-questions/' ) ),
				array( __( 'Contact Us', 'frontmall' ), home_url( '/contact-us/' ) ),
			);
			foreach ( $items as $it ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => $it[0],
						'menu-item-url'    => $it[1],
						'menu-item-status' => 'publish',
						'menu-item-type'   => 'custom',
					)
				);
			}
		}
		$locations            = is_array( $locations ) ? $locations : array();
		$locations['primary'] = (int) $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	public function maybe_ensure_menu(): void {
		if ( get_option( 'frontmall_menu_ready' ) ) {
			return;
		}
		$this->ensure_menu();
		update_option( 'frontmall_menu_ready', 1 );
	}
}
