<?php
/**
 * Merchant Compliance Inspector.
 *
 * Adds WooCommerce > Merchant Compliance. Audits the store against Google
 * Merchant Center policy with emphasis on Misrepresentation, grading each
 * item critical / warning / pass with a one-click link to the exact setting
 * to fix. When zero criticals remain, it surfaces a "Request review" button.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Merchant_Compliance {

	private static ?Merchant_Compliance $instance = null;

	public static function instance(): Merchant_Compliance {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 99 );
	}

	public function menu(): void {
		$parent = class_exists( 'WooCommerce' ) ? 'woocommerce' : 'tools.php';
		add_submenu_page(
			$parent,
			__( 'Merchant Compliance', 'frontmall' ),
			__( 'Merchant Compliance', 'frontmall' ),
			'manage_woocommerce',
			'frontmall-merchant-compliance',
			array( $this, 'render' )
		);
	}

	/**
	 * Each check returns array( status, label, detail, fix_url, fix_label ).
	 * status: critical | warning | pass.
	 */
	private function run_checks(): array {
		$checks = array();

		// HTTPS.
		$https = ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ) && is_ssl();
		$checks[] = $this->row(
			$https ? 'pass' : 'critical',
			__( 'Secure HTTPS site-wide', 'frontmall' ),
			$https ? __( 'Your site URL uses HTTPS.', 'frontmall' ) : __( 'Merchant Center requires a valid SSL certificate and HTTPS on all pages, especially checkout.', 'frontmall' ),
			admin_url( 'options-general.php' ),
			__( 'Fix site URL', 'frontmall' )
		);

		// Indexability.
		$indexable = (int) get_option( 'blog_public' ) === 1;
		$checks[] = $this->row(
			$indexable ? 'pass' : 'critical',
			__( 'Site is indexable by search engines', 'frontmall' ),
			$indexable ? __( 'Search engine visibility is enabled.', 'frontmall' ) : __( 'Reading settings are blocking search engines. Merchant Center cannot crawl a hidden store.', 'frontmall' ),
			admin_url( 'options-reading.php' ),
			__( 'Enable indexing', 'frontmall' )
		);

		// Business name.
		$name = trim( (string) get_bloginfo( 'name' ) );
		$checks[] = $this->row(
			$name ? 'pass' : 'critical',
			__( 'Business name set', 'frontmall' ),
			$name ? sprintf( __( 'Business name: %s', 'frontmall' ), $name ) : __( 'A consistent business name is required to avoid misrepresentation flags.', 'frontmall' ),
			admin_url( 'options-general.php' ),
			__( 'Set site title', 'frontmall' )
		);

		// Physical address (WooCommerce store address).
		$street  = get_option( 'woocommerce_store_address', '' );
		$city    = get_option( 'woocommerce_store_city', '' );
		$checks[] = $this->row(
			( $street && $city ) ? 'pass' : 'critical',
			__( 'Physical business address', 'frontmall' ),
			( $street && $city ) ? sprintf( __( 'Store address: %s, %s', 'frontmall' ), $street, $city ) : __( 'A verifiable physical address reduces Misrepresentation and Business-information issues.', 'frontmall' ),
			admin_url( 'admin.php?page=wc-settings&tab=general' ),
			__( 'Set store address', 'frontmall' )
		);

		// Visible contact details.
		$b = frontmall_business();
		$has_contact = ! empty( $b['phone'] ) && ! empty( $b['email'] );
		$checks[] = $this->row(
			$has_contact ? 'pass' : 'warning',
			__( 'Visible contact details (phone + email)', 'frontmall' ),
			$has_contact ? sprintf( __( 'Phone %1$s and email %2$s are published in the header/footer.', 'frontmall' ), $b['phone'], $b['email'] ) : __( 'Publish a working phone number and email customers can reach.', 'frontmall' ),
			admin_url( 'customize.php' ),
			__( 'Edit contact info', 'frontmall' )
		);

		// Required policy pages.
		$required_pages = array(
			'Privacy Policy'             => 'privacy',
			'Terms & Conditions'         => 'terms',
			'Return & Refund Policy'     => 'returns',
			'Shipping & Delivery Policy' => 'shipping',
			'Contact Us'                 => 'contact',
		);
		foreach ( $required_pages as $title => $slug ) {
			$page = get_page_by_path( sanitize_title( $title ) );
			$ok   = $page && 'publish' === $page->post_status && str_word_count( wp_strip_all_tags( $page->post_content ) ) > 40;
			$checks[] = $this->row(
				$ok ? 'pass' : 'critical',
				sprintf( __( 'Policy page: %s', 'frontmall' ), $title ),
				$ok ? __( 'Published with substantive content.', 'frontmall' ) : __( 'This page is required by Merchant Center and must contain real, adequate content.', 'frontmall' ),
				$page ? get_edit_post_link( $page->ID, 'raw' ) : admin_url( 'post-new.php?post_type=page' ),
				$page ? __( 'Edit page', 'frontmall' ) : __( 'Create page', 'frontmall' )
			);
		}

		// WooCommerce core pages + payment + shipping + currency + product data.
		if ( class_exists( 'WooCommerce' ) ) {
			$this->woo_checks( $checks );
		} else {
			$checks[] = $this->row(
				'critical',
				__( 'WooCommerce active', 'frontmall' ),
				__( 'WooCommerce powers cart, checkout, payments and product data - all required for Merchant Center.', 'frontmall' ),
				admin_url( 'themes.php?page=frontmall-setup' ),
				__( 'Install WooCommerce', 'frontmall' )
			);
		}

		return $checks;
	}

	private function woo_checks( array &$checks ): void {
		$core = array(
			'woocommerce_shop_page_id'      => __( 'Shop', 'frontmall' ),
			'woocommerce_cart_page_id'      => __( 'Cart', 'frontmall' ),
			'woocommerce_checkout_page_id'  => __( 'Checkout', 'frontmall' ),
			'woocommerce_myaccount_page_id' => __( 'My Account', 'frontmall' ),
		);
		foreach ( $core as $opt => $label ) {
			$id = (int) get_option( $opt );
			$ok = $id && 'publish' === get_post_status( $id );
			$checks[] = $this->row(
				$ok ? 'pass' : 'critical',
				sprintf( __( 'WooCommerce page: %s', 'frontmall' ), $label ),
				$ok ? __( 'Configured and published.', 'frontmall' ) : __( 'A clear checkout path is required. This core page is missing.', 'frontmall' ),
				admin_url( 'admin.php?page=wc-settings&tab=advanced' ),
				__( 'Set page', 'frontmall' )
			);
		}

		// Payment gateway enabled.
		$enabled = array_filter( WC()->payment_gateways()->get_available_payment_gateways() );
		$checks[] = $this->row(
			$enabled ? 'pass' : 'critical',
			__( 'At least one payment method enabled', 'frontmall' ),
			$enabled ? sprintf( __( '%d active gateway(s).', 'frontmall' ), count( $enabled ) ) : __( 'Customers must be able to pay. Enable M-Pesa, card or another gateway.', 'frontmall' ),
			admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
			__( 'Enable payments', 'frontmall' )
		);

		// Shipping zones.
		$zones = function_exists( 'WC_Shipping_Zones' ) ? \WC_Shipping_Zones::get_zones() : array();
		$checks[] = $this->row(
			$zones ? 'pass' : 'warning',
			__( 'Shipping configured', 'frontmall' ),
			$zones ? sprintf( __( '%d shipping zone(s) defined.', 'frontmall' ), count( $zones ) ) : __( 'Define at least one shipping zone with rates for accurate Merchant Center shipping.', 'frontmall' ),
			admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
			__( 'Set up shipping', 'frontmall' )
		);

		// Currency.
		$currency = get_woocommerce_currency();
		$checks[] = $this->row(
			$currency ? 'pass' : 'warning',
			__( 'Store currency set', 'frontmall' ),
			sprintf( __( 'Currency: %s', 'frontmall' ), $currency ),
			admin_url( 'admin.php?page=wc-settings&tab=general' ),
			__( 'Change currency', 'frontmall' )
		);

		// Product data quality (sample scan).
		$this->product_quality_checks( $checks );
	}

	private function product_quality_checks( array &$checks ): void {
		$ids = wc_get_products(
			array( 'status' => 'publish', 'limit' => 200, 'return' => 'ids' )
		);
		$total = count( $ids );
		if ( ! $total ) {
			$checks[] = $this->row(
				'warning',
				__( 'Products published', 'frontmall' ),
				__( 'No published products found. Import your catalogue to be reviewable.', 'frontmall' ),
				admin_url( 'edit.php?post_type=product' ),
				__( 'Add products', 'frontmall' )
			);
			return;
		}

		$no_image = 0; $no_price = 0; $no_sku = 0; $no_brand = 0;
		$brand_taxes = array_filter( array( 'product_brand', 'pwb-brand', 'pa_brand' ), 'taxonomy_exists' );

		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) { continue; }
			if ( ! $p->get_image_id() ) { $no_image++; }
			if ( '' === $p->get_price() ) { $no_price++; }
			if ( ! $p->get_sku() ) { $no_sku++; }
			$has_brand = false;
			foreach ( $brand_taxes as $tax ) {
				if ( wp_get_post_terms( $id, $tax, array( 'fields' => 'ids' ) ) ) { $has_brand = true; break; }
			}
			if ( ! $has_brand ) { $no_brand++; }
		}

		$pct = fn( int $bad ) => round( $bad / $total * 100, 1 );

		$checks[] = $this->quality_row( __( 'Products with an image', 'frontmall' ), $no_image, $total, 'critical', $pct( $no_image ) );
		$checks[] = $this->quality_row( __( 'Products with a price', 'frontmall' ), $no_price, $total, 'critical', $pct( $no_price ) );
		$checks[] = $this->quality_row( __( 'Products with a SKU (MPN)', 'frontmall' ), $no_sku, $total, 'warning', $pct( $no_sku ) );
		$checks[] = $this->quality_row( __( 'Products with a Brand', 'frontmall' ), $no_brand, $total, 'warning', $pct( $no_brand ), true );
	}

	private function quality_row( string $label, int $bad, int $total, string $sev, float $pct, bool $is_brand = false ): array {
		$status = 0 === $bad ? 'pass' : $sev;
		$detail = 0 === $bad
			? sprintf( __( 'All %d sampled products pass.', 'frontmall' ), $total )
			: sprintf( __( '%1$d of %2$d sampled products (%3$s%%) are missing this. Missing identifiers cause Misrepresentation / Product-data disapprovals.', 'frontmall' ), $bad, $total, $pct );
		return $this->row(
			$status,
			$label,
			$detail,
			admin_url( 'edit.php?post_type=product' ),
			__( 'Review products', 'frontmall' )
		);
	}

	private function row( string $status, string $label, string $detail, string $fix_url, string $fix_label ): array {
		return compact( 'status', 'label', 'detail', 'fix_url', 'fix_label' );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'frontmall' ) );
		}
		$checks   = $this->run_checks();
		$critical = count( array_filter( $checks, fn( $c ) => 'critical' === $c['status'] ) );
		$warning  = count( array_filter( $checks, fn( $c ) => 'warning' === $c['status'] ) );
		$pass     = count( array_filter( $checks, fn( $c ) => 'pass' === $c['status'] ) );

		echo '<div class="wrap fm-mc">';
		echo '<h1>' . esc_html__( 'Merchant Compliance Inspector', 'frontmall' ) . '</h1>';
		echo '<p>' . esc_html__( 'Audits your store against Google Merchant Center policy, with emphasis on Misrepresentation. Fix criticals first, then request a review. Refresh this page to re-scan.', 'frontmall' ) . '</p>';

		printf(
			'<p style="font-size:15px"><span style="color:#b32d2e;font-weight:600">&#9679; %1$d critical</span> &nbsp; <span style="color:#996800;font-weight:600">&#9679; %2$d warning</span> &nbsp; <span style="color:#1e7e34;font-weight:600">&#9679; %3$d pass</span></p>',
			$critical,
			$warning,
			$pass
		);

		if ( 0 === $critical ) {
			$mc = 'https://merchants.google.com/mc/products/diagnostics';
			printf(
				'<div class="notice notice-success inline"><p><strong>%s</strong> %s</p><p><a class="button button-primary button-hero" target="_blank" rel="noopener" href="%s">%s</a></p></div>',
				esc_html__( 'No critical issues remaining.', 'frontmall' ),
				esc_html__( 'Your store meets the core Merchant Center requirements checked here.', 'frontmall' ),
				esc_url( $mc ),
				esc_html__( 'Request review in Merchant Center', 'frontmall' )
			);
		} else {
			printf(
				'<div class="notice notice-error inline"><p>%s</p></div>',
				esc_html__( 'Resolve all critical issues below before requesting a Merchant Center review.', 'frontmall' )
			);
		}

		echo '<table class="widefat striped" style="margin-top:16px"><thead><tr>';
		echo '<th style="width:90px">' . esc_html__( 'Status', 'frontmall' ) . '</th>';
		echo '<th>' . esc_html__( 'Check', 'frontmall' ) . '</th>';
		echo '<th>' . esc_html__( 'Details', 'frontmall' ) . '</th>';
		echo '<th style="width:150px">' . esc_html__( 'Action', 'frontmall' ) . '</th>';
		echo '</tr></thead><tbody>';

		$badge = array(
			'critical' => '<span style="color:#b32d2e;font-weight:600">&#9679; ' . esc_html__( 'Critical', 'frontmall' ) . '</span>',
			'warning'  => '<span style="color:#996800;font-weight:600">&#9679; ' . esc_html__( 'Warning', 'frontmall' ) . '</span>',
			'pass'     => '<span style="color:#1e7e34;font-weight:600">&#9679; ' . esc_html__( 'Pass', 'frontmall' ) . '</span>',
		);

		// Sort: critical, warning, pass.
		$order = array( 'critical' => 0, 'warning' => 1, 'pass' => 2 );
		usort( $checks, fn( $a, $b ) => $order[ $a['status'] ] <=> $order[ $b['status'] ] );

		foreach ( $checks as $c ) {
			printf(
				'<tr><td>%1$s</td><td><strong>%2$s</strong></td><td>%3$s</td><td>%4$s</td></tr>',
				$badge[ $c['status'] ],
				esc_html( $c['label'] ),
				esc_html( $c['detail'] ),
				'pass' === $c['status'] ? '&mdash;' : sprintf( '<a class="button" href="%s">%s</a>', esc_url( (string) $c['fix_url'] ), esc_html( $c['fix_label'] ) )
			);
		}
		echo '</tbody></table>';
		echo '<p style="margin-top:12px"><a class="button" href="' . esc_url( admin_url( 'admin.php?page=frontmall-merchant-compliance' ) ) . '">' . esc_html__( 'Re-scan', 'frontmall' ) . '</a></p>';
		echo '</div>';
	}
}
