<?php
/**
 * AJAX endpoints: instant predictive search (products, SKU, categories,
 * brands) and add-to-cart. All input validated, output escaped, nonce-checked.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Ajax {

	private static ?Ajax $instance = null;

	public static function instance(): Ajax {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'wp_ajax_frontmall_search', array( $this, 'search' ) );
		add_action( 'wp_ajax_nopriv_frontmall_search', array( $this, 'search' ) );
		add_action( 'wp_ajax_frontmall_add_to_cart', array( $this, 'add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_frontmall_add_to_cart', array( $this, 'add_to_cart' ) );
		add_action( 'wp_ajax_frontmall_quickview', array( $this, 'quickview' ) );
		add_action( 'wp_ajax_nopriv_frontmall_quickview', array( $this, 'quickview' ) );
	}

	/**
	 * Predictive search across product titles, SKUs, categories and brands.
	 */
	public function search(): void {
		check_ajax_referer( 'frontmall_search', 'nonce' );

		if ( ! Security::rate_ok( 'search', 90, 60 ) ) {
			wp_send_json_success( array( 'html' => '', 'count' => 0 ) );
		}
		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$cache_key = 'fm_search_' . md5( $term );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			wp_send_json_success( $cached );
		}
		if ( mb_strlen( $term ) < 2 ) {
			wp_send_json_success( array( 'html' => '', 'count' => 0 ) );
		}

		if ( ! function_exists( 'wc_get_products' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce inactive' ), 200 );
		}

		$products = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => 8,
				's'        => $term,
				'orderby'  => 'relevance',
			)
		);

		// SKU fallback.
		if ( empty( $products ) ) {
			$by_sku = wc_get_product_id_by_sku( $term );
			if ( $by_sku ) {
				$products = array( wc_get_product( $by_sku ) );
			}
		}

		$cats  = $this->matching_terms( 'product_cat', $term );
		$brands= $this->matching_terms( array( 'product_brand', 'pa_brand', 'pwb-brand' ), $term );

		ob_start();
		if ( $products ) {
			echo '<ul class="fm-search__products" role="listbox">';
			foreach ( $products as $p ) {
				if ( ! $p instanceof \WC_Product ) {
					continue;
				}
				printf(
					'<li class="fm-search__item" role="option"><a href="%1$s"><span class="fm-search__thumb">%2$s</span><span class="fm-search__meta"><span class="fm-search__name">%3$s</span><span class="fm-search__price">%4$s</span></span></a></li>',
					esc_url( $p->get_permalink() ),
					$p->get_image( 'thumbnail' ),
					esc_html( $p->get_name() ),
					wp_kses_post( $p->get_price_html() )
				);
			}
			echo '</ul>';
		}

		if ( $cats ) {
			echo '<div class="fm-search__group"><span class="fm-search__label">' . esc_html__( 'Categories', 'frontmall' ) . '</span>';
			foreach ( $cats as $t ) {
				printf( '<a class="fm-search__chip" href="%s">%s</a>', esc_url( get_term_link( $t ) ), esc_html( $t->name ) );
			}
			echo '</div>';
		}

		if ( $brands ) {
			echo '<div class="fm-search__group"><span class="fm-search__label">' . esc_html__( 'Brands', 'frontmall' ) . '</span>';
			foreach ( $brands as $t ) {
				printf( '<a class="fm-search__chip" href="%s">%s</a>', esc_url( get_term_link( $t ) ), esc_html( $t->name ) );
			}
			echo '</div>';
		}

		$html  = ob_get_clean();
		$count = count( $products ) + count( $cats ) + count( $brands );

		$payload = array( 'html' => $html, 'count' => $count );
		set_transient( $cache_key, $payload, 5 * MINUTE_IN_SECONDS );
		wp_send_json_success( $payload );
	}

	private function matching_terms( $taxonomies, string $term ): array {
		$out = array();
		foreach ( (array) $taxonomies as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$found = get_terms(
				array(
					'taxonomy'   => $tax,
					'name__like' => $term,
					'number'     => 6,
					'hide_empty' => true,
				)
			);
			if ( ! is_wp_error( $found ) && $found ) {
				$out = array_merge( $out, $found );
				break;
			}
		}
		return $out;
	}

	/**
	 * AJAX add to cart (single + variable simple case).
	 */
	public function add_to_cart(): void {
		check_ajax_referer( 'frontmall_cart', 'nonce' );

		if ( ! function_exists( 'WC' ) ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable.', 'frontmall' ) ) );
		}

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity     = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : 1;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'frontmall' ) ) );
		}

		$added = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );

		if ( ! $added ) {
			wp_send_json_error(
				array( 'message' => __( 'Could not add product. It may be out of stock or need options selected.', 'frontmall' ) )
			);
		}

		$product    = wc_get_product( $variation_id ? $variation_id : $product_id );
		$count      = WC()->cart->get_cart_contents_count();
		$count_html = '<span class="fm-cart-count" data-count="' . esc_attr( (string) $count ) . '">' . esc_html( (string) $count ) . '</span>';

		wp_send_json_success(
			array(
				'productName'  => $product ? $product->get_name() : '',
				'productImage' => $product ? $product->get_image( 'thumbnail' ) : '',
				'quantity'     => (int) $quantity,
				'cartCount'    => (int) $count,
				'subtotal'     => WC()->cart->get_cart_subtotal(),
				'cartUrl'      => wc_get_cart_url(),
				'checkoutUrl'  => wc_get_checkout_url(),
				'fragments'    => array( 'span.fm-cart-count' => $count_html ),
			)
		);
	}

	/**
	 * Quick View: compact product summary for the modal.
	 */
	public function quickview(): void {
		check_ajax_referer( 'frontmall_quickview', 'nonce' );
		if ( ! Security::rate_ok( 'quickview', 90, 60 ) ) {
			wp_send_json_error( array( 'message' => 'rate' ) );
		}
		$id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		if ( ! $id || 'publish' !== get_post_status( $id ) || ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_error( array( 'message' => 'invalid' ) );
		}
		$product = wc_get_product( $id );
		if ( ! $product instanceof \WC_Product ) {
			wp_send_json_error( array( 'message' => 'invalid' ) );
		}
		$in_stock = $product->is_in_stock();
		$can_ajax = $in_stock && $product->is_purchasable() && $product->is_type( 'simple' );
		$short    = wp_trim_words( wp_strip_all_tags( $product->get_short_description() ), 40, '&hellip;' );
		ob_start();
		?>
		<div class="fm-qv">
			<div class="fm-qv__media"><?php echo $product->get_image( 'large', array( 'class' => 'fm-qv__img', 'loading' => 'lazy' ) ); ?></div>
			<div class="fm-qv__body">
				<h2 class="fm-qv__title"><?php echo esc_html( $product->get_name() ); ?></h2>
				<div class="fm-qv__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
				<span class="fm-badge <?php echo $in_stock ? 'fm-badge--in' : 'fm-badge--out'; ?>"><?php echo $in_stock ? esc_html__( 'In stock', 'frontmall' ) : esc_html__( 'Out of stock', 'frontmall' ); ?></span>
				<?php if ( $short ) : ?><p class="fm-qv__desc"><?php echo esc_html( $short ); ?></p><?php endif; ?>
				<div class="fm-qv__actions">
					<?php if ( $can_ajax ) : ?>
						<button class="fm-btn fm-btn--cart fm-ajax-add" type="button" data-product-id="<?php echo esc_attr( (string) $id ); ?>"><?php esc_html_e( 'Add to Cart', 'frontmall' ); ?></button>
					<?php endif; ?>
					<a class="fm-btn fm-btn--outline" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php esc_html_e( 'View full details', 'frontmall' ); ?></a>
				</div>
			</div>
		</div>
		<?php
		wp_send_json_success( array( 'html' => ob_get_clean(), 'title' => $product->get_name() ) );
	}
}
