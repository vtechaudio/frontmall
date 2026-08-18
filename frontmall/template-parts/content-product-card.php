<?php
/**
 * Product card: responsive lazy image (accurate sizes so mobile never over-
 * fetches), sale/stock badges, truncated title + description, price, rating,
 * AJAX add-to-cart, quick view and a native (plugin-free) wishlist toggle.
 *
 * @package Frontmall
 */

defined( 'ABSPATH' ) || exit;

$product = get_query_var( 'frontmall_card_product' );
if ( ! $product instanceof WC_Product ) {
	return;
}
$pid      = $product->get_id();
$on_sale  = $product->is_on_sale();
$in_stock = $product->is_in_stock();
$short    = wp_trim_words( wp_strip_all_tags( $product->get_short_description() ), 18, '&hellip;' );
$rating   = (float) $product->get_average_rating();
?>
<article class="fm-card" data-product-id="<?php echo esc_attr( (string) $pid ); ?>">
	<div class="fm-card__media">
		<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="fm-card__thumb" aria-label="<?php echo esc_attr( $product->get_name() ); ?>">
			<?php
			echo $product->get_image(
				'frontmall-card',
				array(
					'loading'  => 'lazy',
					'decoding' => 'async',
					'class'    => 'fm-card__img',
					'sizes'    => '(max-width:575px) 45vw, (max-width:991px) 30vw, (max-width:1199px) 22vw, 220px',
				)
			);
			?>
		</a>
		<div class="fm-card__badges">
			<?php if ( $on_sale ) : ?>
				<span class="fm-badge fm-badge--sale"><?php esc_html_e( 'Sale', 'frontmall' ); ?></span>
			<?php endif; ?>
			<span class="fm-badge <?php echo $in_stock ? 'fm-badge--in' : 'fm-badge--out'; ?>">
				<?php echo $in_stock ? esc_html__( 'In stock', 'frontmall' ) : esc_html__( 'Out of stock', 'frontmall' ); ?>
			</span>
		</div>
		<div class="fm-card__hover">
			<button class="fm-card__act fm-card__wishlist" type="button" data-wishlist="<?php echo esc_attr( (string) $pid ); ?>" data-title="<?php echo esc_attr( $product->get_name() ); ?>" aria-pressed="false" aria-label="<?php echo esc_attr( sprintf( __( 'Add %s to wishlist', 'frontmall' ), $product->get_name() ) ); ?>">&#9825;</button>
			<a class="fm-card__act fm-card__quickview" href="<?php echo esc_url( $product->get_permalink() ); ?>" data-quickview="<?php echo esc_attr( (string) $pid ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Quick view %s', 'frontmall' ), $product->get_name() ) ); ?>">&#128065;</a>
		</div>
	</div>

	<div class="fm-card__body">
		<?php if ( $rating > 0 ) : ?>
			<div class="fm-card__rating" aria-label="<?php echo esc_attr( sprintf( __( 'Rated %s out of 5', 'frontmall' ), $rating ) ); ?>">
				<span class="fm-stars" style="--fm-rating: <?php echo esc_attr( (string) ( $rating / 5 * 100 ) ); ?>%"></span>
			</div>
		<?php endif; ?>
		<h3 class="fm-card__title"><a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
		<?php if ( $short ) : ?>
			<p class="fm-card__desc"><?php echo esc_html( $short ); ?></p>
		<?php endif; ?>
		<div class="fm-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>

		<div class="fm-card__cart">
			<?php if ( $in_stock && $product->is_purchasable() && $product->is_type( 'simple' ) ) : ?>
				<button class="fm-btn fm-btn--cart fm-ajax-add" type="button"
					data-product-id="<?php echo esc_attr( (string) $pid ); ?>"
					aria-label="<?php echo esc_attr( sprintf( __( 'Add %s to cart', 'frontmall' ), $product->get_name() ) ); ?>">
					<?php esc_html_e( 'Add to Cart', 'frontmall' ); ?>
				</button>
			<?php else : ?>
				<a class="fm-btn fm-btn--cart" href="<?php echo esc_url( $product->get_permalink() ); ?>">
					<?php echo $in_stock ? esc_html__( 'Select options', 'frontmall' ) : esc_html__( 'Read more', 'frontmall' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</article>
