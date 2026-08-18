<?php
/**
 * Frontmall setup: required-plugin manager, one-click Starter Setup (nav menu),
 * a Generate/refresh content pages action, and a product-import shortcut.
 * Core WP endpoints with capability + nonce checks; no bundled library.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Plugins {

	private static ?Plugins $instance = null;

	public static function instance(): Plugins {
		return self::$instance ??= new self();
	}

	private function recommended(): array {
		return array(
			'woocommerce'               => array( 'WooCommerce', true ),
			'elementor'                 => array( 'Elementor', false ),
			'contact-form-7'            => array( 'Contact Form 7', false ),
			'wordpress-seo'             => array( 'Yoast SEO', false ),
			'litespeed-cache'           => array( 'LiteSpeed Cache', false ),
			'wp-mail-smtp'              => array( 'WP Mail SMTP', false ),
			'google-site-kit'           => array( 'Site Kit by Google', false ),
			'woo-variation-swatches'    => array( 'Variation Swatches for WooCommerce', false ),
			'ewww-image-optimizer'      => array( 'Image Optimizer (EWWW)', false ),
			'yith-woocommerce-wishlist' => array( 'YITH WooCommerce Wishlist', false ),
		);
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_post_frontmall_starter', array( $this, 'run_starter' ) );
	}

	private function map(): array {
		return array(
			'woocommerce'               => 'woocommerce/woocommerce.php',
			'elementor'                 => 'elementor/elementor.php',
			'contact-form-7'            => 'contact-form-7/wp-contact-form-7.php',
			'wordpress-seo'             => 'wordpress-seo/wp-seo.php',
			'litespeed-cache'           => 'litespeed-cache/litespeed-cache.php',
			'wp-mail-smtp'              => 'wp-mail-smtp/wp_mail_smtp.php',
			'google-site-kit'           => 'google-site-kit/google-site-kit.php',
			'woo-variation-swatches'    => 'woo-variation-swatches/woo-variation-swatches.php',
			'ewww-image-optimizer'      => 'ewww-image-optimizer/ewww-image-optimizer.php',
			'yith-woocommerce-wishlist' => 'yith-woocommerce-wishlist/init.php',
		);
	}

	private function is_active( string $slug ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$file = $this->map()[ $slug ] ?? '';
		return $file && is_plugin_active( $file );
	}

	private function installed_file( string $slug ): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( 0 === strpos( $file, $slug . '/' ) ) {
				return $file;
			}
		}
		return '';
	}

	private function missing_required(): array {
		$missing = array();
		foreach ( $this->recommended() as $slug => $data ) {
			if ( $data[1] && ! $this->is_active( $slug ) ) {
				$missing[ $slug ] = $data[0];
			}
		}
		return $missing;
	}

	public function menu(): void {
		add_theme_page(
			__( 'Frontmall Setup', 'frontmall' ),
			__( 'Frontmall Setup', 'frontmall' ),
			'edit_theme_options',
			'frontmall-setup',
			array( $this, 'render' )
		);
	}

	public function notice(): void {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'appearance_page_frontmall-setup' === $screen->id ) {
			return;
		}
		if ( $this->missing_required() ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s <a class="button button-primary" href="%s">%s</a></p></div>',
				esc_html__( 'Frontmall:', 'frontmall' ),
				esc_html__( 'Finish setup - WooCommerce is required for this theme.', 'frontmall' ),
				esc_url( admin_url( 'themes.php?page=frontmall-setup' ) ),
				esc_html__( 'Run setup', 'frontmall' )
			);
		}
	}

	private function action_link( string $slug ): string {
		$installed = $this->installed_file( $slug );
		if ( $installed && $this->is_active( $slug ) ) {
			return '<span style="color:#1e7e34;font-weight:600">' . esc_html__( 'Active', 'frontmall' ) . '</span>';
		}
		if ( $installed ) {
			$url = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $installed ) ), 'activate-plugin_' . $installed );
			return '<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Activate', 'frontmall' ) . '</a>';
		}
		$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $slug ) ), 'install-plugin_' . $slug );
		return '<a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Install', 'frontmall' ) . '</a>';
	}

	public function render(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the theme.', 'frontmall' ) );
		}
		$importer = admin_url( 'edit.php?post_type=product&page=product_importer' );

		echo '<div class="wrap fm-setup"><h1>' . esc_html__( 'Frontmall Setup', 'frontmall' ) . '</h1>';

		if ( isset( $_GET['frontmall_done'] ) ) {
			$done = sanitize_key( wp_unslash( $_GET['frontmall_done'] ) );
			if ( 'pages' === $done ) {
				$n = isset( $_GET['fn'] ) ? absint( $_GET['fn'] ) : 0;
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Content pages generated / refreshed (%d pages). Unedited theme pages were updated to the latest content; your own edits were preserved.', 'frontmall' ), $n ) ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Starter setup complete: the primary navigation menu was created and assigned to the header.', 'frontmall' ) . '</p></div>';
			}
		}

		// Step 1 - plugins.
		echo '<h2>' . esc_html__( '1. Install plugins', 'frontmall' ) . '</h2>';
		echo '<p>' . esc_html__( 'WooCommerce is required. The rest are recommended for SEO, speed and extra features.', 'frontmall' ) . '</p>';
		echo '<table class="widefat striped" style="max-width:760px"><thead><tr><th>' . esc_html__( 'Plugin', 'frontmall' ) . '</th><th>' . esc_html__( 'Type', 'frontmall' ) . '</th><th>' . esc_html__( 'Status', 'frontmall' ) . '</th></tr></thead><tbody>';
		foreach ( $this->recommended() as $slug => $data ) {
			printf(
				'<tr><td><strong>%s</strong></td><td>%s</td><td>%s</td></tr>',
				esc_html( $data[0] ),
				$data[1] ? esc_html__( 'Required', 'frontmall' ) : esc_html__( 'Recommended', 'frontmall' ),
				$this->action_link( $slug )
			);
		}
		echo '</tbody></table>';

		// Step 2 - starter setup.
		echo '<h2 style="margin-top:28px">' . esc_html__( '2. Starter setup', 'frontmall' ) . '</h2>';
		echo '<p>' . esc_html__( 'Build the main navigation menu (About, Shop, Deals, Shipping, Returns, FAQ, Contact) and assign it to the header automatically. Safe to run again anytime.', 'frontmall' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'frontmall_starter' );
		echo '<input type="hidden" name="action" value="frontmall_starter">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Run starter setup', 'frontmall' ) . '</button>';
		echo '</form>';

		// Step 3 - content pages.
		echo '<h2 style="margin-top:28px">' . esc_html__( '3. Legal & content pages', 'frontmall' ) . '</h2>';
		echo '<p>' . esc_html__( 'Create or refresh the detailed, Google Merchant Center-ready pages: About Us, Contact Us, Track Order, FAQ, Payment Methods, Privacy Policy, Terms & Conditions, Return & Refund Policy, Shipping & Delivery Policy, Warranty Policy and Cookie Policy. This updates theme-generated pages to the latest content; pages you have edited yourself are left untouched.', 'frontmall' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'frontmall_regen' );
		echo '<input type="hidden" name="action" value="frontmall_regen_pages">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Generate / refresh content pages', 'frontmall' ) . '</button>';
		echo '</form>';

		// Step 4 - import products.
		echo '<h2 style="margin-top:28px">' . esc_html__( '4. Import your products', 'frontmall' ) . '</h2>';
		echo '<p>' . esc_html__( 'Upload your product CSV. Categories and brands are created automatically, and the homepage sections fill in once products exist.', 'frontmall' ) . '</p>';
		if ( $this->is_active( 'woocommerce' ) ) {
			echo '<a class="button button-primary button-hero" href="' . esc_url( $importer ) . '">' . esc_html__( 'Import products (CSV)', 'frontmall' ) . '</a>';
		} else {
			echo '<p><em>' . esc_html__( 'Activate WooCommerce first to enable the product importer.', 'frontmall' ) . '</em></p>';
		}

		echo '</div>';
	}

	/** Build + assign the primary navigation menu. */
	public function run_starter(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'frontmall' ) );
		}
		check_admin_referer( 'frontmall_starter' );

		$name    = 'Frontmall Primary';
		$menu    = wp_get_nav_menu_object( $name );
		$menu_id = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu( $name );

		if ( ! is_wp_error( $menu_id ) && $menu_id ) {
			$existing = wp_get_nav_menu_items( $menu_id );
			if ( $existing ) {
				foreach ( $existing as $item ) {
					wp_delete_post( $item->ID, true );
				}
			}
			$shop  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
			$links = array(
				__( 'About Us', 'frontmall' )            => home_url( '/about-us/' ),
				__( 'Shop', 'frontmall' )                => $shop,
				__( 'Deals', 'frontmall' )               => $shop,
				__( 'Shipping & Delivery', 'frontmall' ) => home_url( '/shipping-delivery-policy/' ),
				__( 'Returns', 'frontmall' )             => home_url( '/return-refund-policy/' ),
				__( 'FAQ', 'frontmall' )                 => home_url( '/frequently-asked-questions/' ),
				__( 'Contact Us', 'frontmall' )          => home_url( '/contact-us/' ),
			);
			foreach ( $links as $title => $url ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'  => $title,
						'menu-item-url'    => $url,
						'menu-item-status' => 'publish',
						'menu-item-type'   => 'custom',
					)
				);
			}
			$locations = get_theme_mod( 'nav_menu_locations', array() );
			if ( ! is_array( $locations ) ) {
				$locations = array();
			}
			$locations['primary']     = $menu_id;
			$locations['departments'] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}

		wp_safe_redirect( add_query_arg( 'frontmall_done', '1', admin_url( 'themes.php?page=frontmall-setup' ) ) );
		exit;
	}
}
