<?php
/**
 * Storefront filtering: a left sidebar (category / brand / price / in-stock /
 * on-sale) that drives the main product query via GET params (works with no
 * JavaScript and is fully crawlable), removable filter chips, and a live
 * result count supplied by WooCommerce's own result-count output.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Filters {

	private static ?Filters $instance = null;

	public static function instance(): Filters {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'pre_get_posts', array( $this, 'apply' ) );
		add_action( 'frontmall_shop_sidebar', array( $this, 'render_sidebar' ) );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'render_toolbar' ), 5 );
	}

	public static function brand_taxonomy(): string {
		foreach ( array( 'product_brand', 'pwb-brand', 'pa_brand' ) as $t ) {
			if ( taxonomy_exists( $t ) ) {
				return $t;
			}
		}
		return '';
	}

	/**
	 * Mutate the main archive query with the active filters.
	 */
	public function apply( \WP_Query $q ): void {
		if ( is_admin() || ! $q->is_main_query() || ! frontmall_is_shop_archive() ) {
			return;
		}
		$tax  = (array) $q->get( 'tax_query' );
		$meta = (array) $q->get( 'meta_query' );

		$brand = self::brand_taxonomy();
		if ( $brand && ! empty( $_GET['fm_brand'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$slugs = array_map( 'sanitize_title', (array) wp_unslash( $_GET['fm_brand'] ) ); // phpcs:ignore
			$tax[] = array(
				'taxonomy' => $brand,
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}

		$min = ( isset( $_GET['fm_min_price'] ) && '' !== $_GET['fm_min_price'] ) ? (float) $_GET['fm_min_price'] : null; // phpcs:ignore
		$max = ( isset( $_GET['fm_max_price'] ) && '' !== $_GET['fm_max_price'] ) ? (float) $_GET['fm_max_price'] : null; // phpcs:ignore
		if ( null !== $min || null !== $max ) {
			$price = array(
				'key'  => '_price',
				'type' => 'NUMERIC',
			);
			if ( null !== $min && null !== $max ) {
				$price['value']   = array( min( $min, $max ), max( $min, $max ) );
				$price['compare'] = 'BETWEEN';
			} elseif ( null !== $min ) {
				$price['value']   = $min;
				$price['compare'] = '>=';
			} else {
				$price['value']   = $max;
				$price['compare'] = '<=';
			}
			$meta[] = $price;
		}

		if ( ! empty( $_GET['fm_instock'] ) ) { // phpcs:ignore
			$meta[] = array(
				'key'   => '_stock_status',
				'value' => 'instock',
			);
		}

		if ( ! empty( $_GET['fm_onsale'] ) && function_exists( 'wc_get_product_ids_on_sale' ) ) { // phpcs:ignore
			$on_sale   = wc_get_product_ids_on_sale();
			$on_sale[] = 0;
			$existing  = $q->get( 'post__in' );
			$q->set( 'post__in', $existing ? array_values( array_intersect( (array) $existing, $on_sale ) ) : $on_sale );
		}

		if ( count( $tax ) ) {
			if ( count( $tax ) > 1 ) {
				$tax['relation'] = 'AND';
			}
			$q->set( 'tax_query', $tax );
		}
		if ( count( $meta ) ) {
			if ( count( $meta ) > 1 ) {
				$meta['relation'] = 'AND';
			}
			$q->set( 'meta_query', $meta );
		}
	}

	private function base_url(): string {
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return wc_get_page_permalink( 'shop' );
		}
		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$t = get_queried_object();
			if ( $t instanceof \WP_Term ) {
				$link = get_term_link( $t );
				if ( ! is_wp_error( $link ) ) {
					return $link;
				}
			}
		}
		if ( is_search() ) {
			return add_query_arg(
				array(
					'post_type' => 'product',
					's'         => rawurlencode( get_search_query() ),
				),
				home_url( '/' )
			);
		}
		return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	}

	/**
	 * The full set of currently-active filter params (for building chip URLs).
	 */
	private function active_params(): array {
		$p = array();
		if ( ! empty( $_GET['fm_brand'] ) ) { // phpcs:ignore
			$p['fm_brand'] = array_values( array_map( 'sanitize_title', (array) wp_unslash( $_GET['fm_brand'] ) ) ); // phpcs:ignore
		}
		if ( isset( $_GET['fm_min_price'] ) && '' !== $_GET['fm_min_price'] ) { // phpcs:ignore
			$p['fm_min_price'] = (float) $_GET['fm_min_price']; // phpcs:ignore
		}
		if ( isset( $_GET['fm_max_price'] ) && '' !== $_GET['fm_max_price'] ) { // phpcs:ignore
			$p['fm_max_price'] = (float) $_GET['fm_max_price']; // phpcs:ignore
		}
		if ( ! empty( $_GET['fm_instock'] ) ) { // phpcs:ignore
			$p['fm_instock'] = 1;
		}
		if ( ! empty( $_GET['fm_onsale'] ) ) { // phpcs:ignore
			$p['fm_onsale'] = 1;
		}
		if ( ! empty( $_GET['orderby'] ) ) { // phpcs:ignore
			$p['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore
		}
		return $p;
	}

	private function url_with( array $params ): string {
		return $params ? add_query_arg( $params, $this->base_url() ) : $this->base_url();
	}

	public function render_toolbar(): void {
		if ( ! frontmall_is_shop_archive() ) {
			return;
		}
		$params = $this->active_params();
		$chips  = array();
		$brand  = self::brand_taxonomy();

		if ( $brand && ! empty( $params['fm_brand'] ) ) {
			foreach ( $params['fm_brand'] as $slug ) {
				$term = get_term_by( 'slug', $slug, $brand );
				if ( $term ) {
					$rest        = $params;
					$rest['fm_brand'] = array_values( array_diff( $params['fm_brand'], array( $slug ) ) );
					if ( empty( $rest['fm_brand'] ) ) {
						unset( $rest['fm_brand'] );
					}
					$chips[] = array( 'label' => $term->name, 'url' => $this->url_with( $rest ) );
				}
			}
		}
		if ( isset( $params['fm_min_price'] ) || isset( $params['fm_max_price'] ) ) {
			$rest = $params;
			unset( $rest['fm_min_price'], $rest['fm_max_price'] );
			$lo    = isset( $params['fm_min_price'] ) ? wp_strip_all_tags( wc_price( $params['fm_min_price'] ) ) : '';
			$hi    = isset( $params['fm_max_price'] ) ? wp_strip_all_tags( wc_price( $params['fm_max_price'] ) ) : '';
			$label = trim( $lo . ' - ' . $hi, ' -' );
			$chips[] = array( 'label' => sprintf( __( 'Price: %s', 'frontmall' ), $label ), 'url' => $this->url_with( $rest ) );
		}
		if ( ! empty( $params['fm_instock'] ) ) {
			$rest = $params;
			unset( $rest['fm_instock'] );
			$chips[] = array( 'label' => __( 'In stock', 'frontmall' ), 'url' => $this->url_with( $rest ) );
		}
		if ( ! empty( $params['fm_onsale'] ) ) {
			$rest = $params;
			unset( $rest['fm_onsale'] );
			$chips[] = array( 'label' => __( 'On sale', 'frontmall' ), 'url' => $this->url_with( $rest ) );
		}

		echo '<div class="fm-shop-toolbar">';
		echo '<button type="button" class="fm-filters-toggle" aria-controls="fm-shop-filters" aria-expanded="false">' . esc_html__( 'Filters', 'frontmall' ) . '</button>';
		if ( $chips ) {
			echo '<div class="fm-active-filters">';
			foreach ( $chips as $c ) {
				printf(
					'<a class="fm-chip" href="%s"><span>%s</span> <span class="fm-chip__x" aria-hidden="true">&times;</span></a>',
					esc_url( $c['url'] ),
					esc_html( $c['label'] )
				);
			}
			$keep = array();
			if ( ! empty( $params['orderby'] ) ) {
				$keep['orderby'] = $params['orderby'];
			}
			printf( '<a class="fm-chip fm-chip--clear" href="%s">%s</a>', esc_url( $this->url_with( $keep ) ), esc_html__( 'Clear all', 'frontmall' ) );
			echo '</div>';
		}
		echo '</div>';
	}

	public function render_sidebar(): void {
		if ( ! frontmall_is_shop_archive() ) {
			return;
		}
		$brand_tax   = self::brand_taxonomy();
		$sel_brands  = ! empty( $_GET['fm_brand'] ) ? array_map( 'sanitize_title', (array) wp_unslash( $_GET['fm_brand'] ) ) : array(); // phpcs:ignore
		$min_val     = isset( $_GET['fm_min_price'] ) ? esc_attr( wp_unslash( $_GET['fm_min_price'] ) ) : ''; // phpcs:ignore
		$max_val     = isset( $_GET['fm_max_price'] ) ? esc_attr( wp_unslash( $_GET['fm_max_price'] ) ) : ''; // phpcs:ignore
		$is_search   = is_search();
		$action      = $is_search ? home_url( '/' ) : $this->base_url();
		$cats        = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0,
				'orderby'    => 'name',
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			)
		);
		$current_cat = ( function_exists( 'is_product_category' ) && is_product_category() ) ? get_queried_object_id() : 0;
		?>
		<aside class="fm-shop-sidebar" id="fm-shop-filters" aria-label="<?php esc_attr_e( 'Product filters', 'frontmall' ); ?>">
			<div class="fm-shop-sidebar__head">
				<h2 class="fm-shop-sidebar__title"><?php esc_html_e( 'Filter', 'frontmall' ); ?></h2>
				<button type="button" class="fm-filters-close" aria-label="<?php esc_attr_e( 'Close filters', 'frontmall' ); ?>">&times;</button>
			</div>
			<form class="fm-filters" method="get" action="<?php echo esc_url( $action ); ?>" data-fm-filters>
				<?php
				if ( $is_search ) {
					printf( '<input type="hidden" name="s" value="%s">', esc_attr( get_search_query() ) );
					echo '<input type="hidden" name="post_type" value="product">';
				}
				if ( ! empty( $_GET['orderby'] ) ) { // phpcs:ignore
					printf( '<input type="hidden" name="orderby" value="%s">', esc_attr( sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) ) ); // phpcs:ignore
				}
				?>

				<?php if ( ! is_wp_error( $cats ) && $cats ) : ?>
					<div class="fm-filter">
						<h3 class="fm-filter__title"><?php esc_html_e( 'Categories', 'frontmall' ); ?></h3>
						<ul class="fm-filter__cats">
							<?php foreach ( $cats as $cat ) : ?>
								<li>
									<a class="<?php echo (int) $current_cat === (int) $cat->term_id ? 'is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $cat ) ); ?>">
										<span><?php echo esc_html( $cat->name ); ?></span>
										<span class="fm-filter__n"><?php echo esc_html( (string) $cat->count ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php
				if ( $brand_tax ) :
					$brands = get_terms(
						array(
							'taxonomy'   => $brand_tax,
							'hide_empty' => true,
							'number'     => 30,
							'orderby'    => 'count',
							'order'      => 'DESC',
						)
					);
					if ( ! is_wp_error( $brands ) && $brands ) :
						?>
						<div class="fm-filter">
							<h3 class="fm-filter__title"><?php esc_html_e( 'Brands', 'frontmall' ); ?></h3>
							<div class="fm-filter__list" role="group" aria-label="<?php esc_attr_e( 'Brands', 'frontmall' ); ?>">
								<?php foreach ( $brands as $b ) : ?>
									<label class="fm-check">
										<input type="checkbox" name="fm_brand[]" value="<?php echo esc_attr( $b->slug ); ?>" <?php checked( in_array( $b->slug, $sel_brands, true ) ); ?>>
										<span class="fm-check__label"><?php echo esc_html( $b->name ); ?></span>
										<span class="fm-check__n"><?php echo esc_html( (string) $b->count ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
						<?php
					endif;
				endif;
				?>

				<div class="fm-filter">
					<h3 class="fm-filter__title"><?php echo esc_html( sprintf( __( 'Price (%s)', 'frontmall' ), get_woocommerce_currency_symbol() ) ); ?></h3>
					<div class="fm-price-range">
						<label class="screen-reader-text" for="fm-min-price"><?php esc_html_e( 'Minimum price', 'frontmall' ); ?></label>
						<input type="number" min="0" step="1" inputmode="numeric" id="fm-min-price" name="fm_min_price" value="<?php echo $min_val; // phpcs:ignore ?>" placeholder="<?php esc_attr_e( 'Min', 'frontmall' ); ?>">
						<span aria-hidden="true">&ndash;</span>
						<label class="screen-reader-text" for="fm-max-price"><?php esc_html_e( 'Maximum price', 'frontmall' ); ?></label>
						<input type="number" min="0" step="1" inputmode="numeric" id="fm-max-price" name="fm_max_price" value="<?php echo $max_val; // phpcs:ignore ?>" placeholder="<?php esc_attr_e( 'Max', 'frontmall' ); ?>">
					</div>
				</div>

				<div class="fm-filter">
					<label class="fm-check">
						<input type="checkbox" name="fm_instock" value="1" <?php checked( ! empty( $_GET['fm_instock'] ) ); // phpcs:ignore ?>>
						<span class="fm-check__label"><?php esc_html_e( 'In stock only', 'frontmall' ); ?></span>
					</label>
					<label class="fm-check">
						<input type="checkbox" name="fm_onsale" value="1" <?php checked( ! empty( $_GET['fm_onsale'] ) ); // phpcs:ignore ?>>
						<span class="fm-check__label"><?php esc_html_e( 'On sale', 'frontmall' ); ?></span>
					</label>
				</div>

				<div class="fm-filter__actions">
					<button type="submit" class="fm-btn fm-btn--block"><?php esc_html_e( 'Apply filters', 'frontmall' ); ?></button>
					<a class="fm-filter__clear" href="<?php echo esc_url( $this->base_url() ); ?>"><?php esc_html_e( 'Clear all', 'frontmall' ); ?></a>
				</div>
			</form>
		</aside>
		<?php
	}
}
