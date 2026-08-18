<?php
/**
 * WooCommerce integration: layout wrappers, loop columns, related products,
 * AJAX-friendly add-to-cart and mini-cart fragments.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class WooCommerce {

	private static ?WooCommerce $instance = null;

	public static function instance(): WooCommerce {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'after_setup_theme', array( $this, 'image_sizes' ) );

		// AJAX add-to-cart on archives + single.
		add_filter( 'woocommerce_product_add_to_cart_url', '__return_empty_string', 5 );
		add_action( 'wp', array( $this, 'maybe_enable_ajax' ) );

		// Layout wrappers.
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
		add_action( 'woocommerce_before_main_content', array( $this, 'wrapper_open' ), 10 );
		add_action( 'woocommerce_after_main_content', array( $this, 'wrapper_close' ), 10 );

		// Catalog density.
		add_filter( 'loop_shop_columns', static fn() => 4 );
		add_filter( 'loop_shop_per_page', static fn() => 24 );
		add_filter( 'woocommerce_related_products_args', array( $this, 'related_args' ) );

		// Mini-cart count fragment.
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_fragments' ) );

		// Sensible defaults.
		add_filter( 'woocommerce_output_related_products_args', array( $this, 'related_args' ) );

		// Full-width storefront: drop the default widget sidebar on shop/single.
		remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

		// Order on WhatsApp button, right next to Add to cart on product pages.
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'whatsapp_order_button' ), 15 );

		// Single-product polish: trust badges under the buy box + sticky bar.
		add_action( 'woocommerce_single_product_summary', array( $this, 'trust_badges' ), 35 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'product_assurance' ), 25 );
		add_action( 'woocommerce_after_single_product', array( $this, 'sticky_add_to_cart' ), 20 );
	}

	public function image_sizes(): void {
		add_theme_support(
			'woocommerce',
			array(
				'thumbnail_image_width' => 400,
				'single_image_width'    => 900,
				'product_grid'          => array(
					'default_columns' => 4,
					'default_rows'    => 6,
				),
			)
		);
	}

	public function maybe_enable_ajax(): void {
		// Ensure the theme card markup always requests AJAX add-to-cart.
	}

	public function wrapper_open(): void {
		if ( frontmall_is_shop_archive() ) {
			echo '<div class="fm-container fm-shop fm-shop--filtered"><div class="fm-shop-layout">';
			do_action( 'frontmall_shop_sidebar' );
			echo '<main id="primary" class="fm-main fm-shop-main" tabindex="-1">';
		} else {
			echo '<div class="fm-container fm-shop"><main id="primary" class="fm-main" tabindex="-1">';
		}
	}

	public function wrapper_close(): void {
		if ( frontmall_is_shop_archive() ) {
			echo '</main></div></div>';
		} else {
			echo '</main></div>';
		}
	}

	public function related_args( array $args ): array {
		$args['posts_per_page'] = 4;
		$args['columns']        = 4;
		return $args;
	}

	public function cart_fragments( array $fragments ): array {
		$count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		ob_start();
		?>
		<span class="fm-cart-count" data-count="<?php echo esc_attr( (string) $count ); ?>"><?php echo esc_html( (string) $count ); ?></span>
		<?php
		$fragments['span.fm-cart-count'] = ob_get_clean();
		return $fragments;
	}

	public function whatsapp_order_button(): void {
		if ( ! get_theme_mod( 'frontmall_wa_enable', true ) ) {
			return;
		}
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$label = (string) get_theme_mod( 'frontmall_wa_label', __( 'Order on WhatsApp', 'frontmall' ) );
		printf(
			'<a class="fm-wa-order" href="%s" target="_blank" rel="noopener nofollow">%s<span>%s</span></a>',
			esc_url( frontmall_wa_order_url( $product ) ),
			frontmall_wa_icon(),
			esc_html( $label )
		);
	}

	public function trust_badges(): void {
		$items = array(
			array( __( '100% Genuine', 'frontmall' ), __( 'Warranty-backed brands', 'frontmall' ) ),
			array( __( 'Fast Delivery', 'frontmall' ), __( 'Same/next-day in Nairobi', 'frontmall' ) ),
			array( __( 'Secure Payment', 'frontmall' ), __( 'M-Pesa, card or on delivery', 'frontmall' ) ),
			array( __( 'Real Support', 'frontmall' ), __( 'Call, WhatsApp or email', 'frontmall' ) ),
		);
		echo '<ul class="fm-trust" aria-label="' . esc_attr__( 'Why buy from us', 'frontmall' ) . '">';
		foreach ( $items as $it ) {
			printf(
				'<li class="fm-trust__item"><span class="fm-trust__t">%s</span><span class="fm-trust__s">%s</span></li>',
				esc_html( $it[0] ),
				esc_html( $it[1] )
			);
		}
		echo '</ul>';
	}

	public function product_assurance(): void {
		global $product;
		if ( is_a( $product, 'WC_Product' ) === false ) {
			return;
		}
		$returns  = home_url( '/return-refund-policy/' );
		$warranty = home_url( '/warranty-policy/' );
		$shipping = home_url( '/shipping-delivery-policy/' );

		$condition = (string) $product->get_attribute( 'condition' );
		if ( '' === $condition ) {
			$condition = __( 'New', 'frontmall' );
		}

		$brand = '';
		foreach ( array( 'product_brand', 'pwb-brand', 'pa_brand' ) as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$terms = wp_get_post_terms( $product->get_id(), $tax, array( 'fields' => 'names' ) );
				if ( $terms && ( is_wp_error( $terms ) === false ) ) {
					$brand = implode( ', ', $terms );
					break;
				}
			}
		}
		$model = (string) $product->get_attribute( 'model' );
		$sku   = (string) $product->get_sku();

		$rows = array();
		if ( $brand ) { $rows[] = array( __( 'Brand', 'frontmall' ), esc_html( $brand ) ); }
		if ( $model ) { $rows[] = array( __( 'Model', 'frontmall' ), esc_html( $model ) ); }
		if ( $sku ) { $rows[] = array( __( 'SKU / MPN', 'frontmall' ), esc_html( $sku ) ); }
		$rows[] = array( __( 'Condition', 'frontmall' ), esc_html( $condition ) );
		$rows[] = array( __( 'Warranty', 'frontmall' ), sprintf( '<a href="%s">%s</a>', esc_url( $warranty ), esc_html__( 'Manufacturer warranty where applicable', 'frontmall' ) ) );
		$rows[] = array( __( 'Delivery', 'frontmall' ), sprintf( '<a href="%s">%s</a>', esc_url( $shipping ), esc_html__( 'Same/next-day in Nairobi, countrywide delivery', 'frontmall' ) ) );
		$rows[] = array( __( 'Returns', 'frontmall' ), sprintf( '<a href="%s">%s</a>', esc_url( $returns ), esc_html__( 'Easy returns under our Return & Refund Policy', 'frontmall' ) ) );

		echo '<ul class="fm-assurance" aria-label="' . esc_attr__( 'Product assurance', 'frontmall' ) . '">';
		foreach ( $rows as $r ) {
			printf( '<li><span class="fm-assurance__k">%s</span><span class="fm-assurance__v">%s</span></li>', esc_html( $r[0] ), wp_kses_post( $r[1] ) );
		}
		echo '</ul>';
	}

	public function sticky_add_to_cart(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$can_ajax = $product->is_in_stock() && $product->is_purchasable() && $product->is_type( 'simple' );
		?>
		<div class="fm-sticky-atc" data-fm-sticky-atc hidden>
			<div class="fm-container fm-sticky-atc__inner">
				<span class="fm-sticky-atc__media"><?php echo $product->get_image( 'thumbnail', array( 'loading' => 'lazy' ) ); ?></span>
				<span class="fm-sticky-atc__info">
					<span class="fm-sticky-atc__name"><?php echo esc_html( $product->get_name() ); ?></span>
					<span class="fm-sticky-atc__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
				</span>
				<?php if ( $can_ajax ) : ?>
					<button class="fm-btn fm-btn--cart fm-ajax-add" type="button" data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Add %s to cart', 'frontmall' ), $product->get_name() ) ); ?>"><?php esc_html_e( 'Add to Cart', 'frontmall' ); ?></button>
				<?php else : ?>
					<a class="fm-btn fm-btn--cart" href="#" data-fm-sticky-scroll><?php esc_html_e( 'View options', 'frontmall' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
