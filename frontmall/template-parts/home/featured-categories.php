<?php
/**
 * Featured categories: a horizontal snap scroller. Desktop shows 6 cards per
 * view, mobile shows 2, and the rest scroll horizontally with arrow controls.
 *
 * @package Frontmall
 */

defined( 'ABSPATH' ) || exit;
if ( ! function_exists( 'get_terms' ) ) {
	return;
}
$terms = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'number'     => 18,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
	)
);
if ( is_wp_error( $terms ) || empty( $terms ) ) {
	return;
}
?>
<section class="fm-section fm-featured-cats">
	<div class="fm-container">
		<div class="fm-section__head">
			<h2 class="fm-section__title"><?php esc_html_e( 'Featured Categories', 'frontmall' ); ?></h2>
			<?php if ( count( $terms ) > 6 ) : ?>
				<div class="fm-scroller__nav" aria-hidden="true">
					<button class="fm-scroller__btn fm-scroller__btn--prev" type="button" data-scroll="prev" aria-label="<?php esc_attr_e( 'Scroll categories left', 'frontmall' ); ?>">&#8249;</button>
					<button class="fm-scroller__btn fm-scroller__btn--next" type="button" data-scroll="next" aria-label="<?php esc_attr_e( 'Scroll categories right', 'frontmall' ); ?>">&#8250;</button>
				</div>
			<?php endif; ?>
		</div>
		<div class="fm-scroller" data-fm-scroller>
			<ul class="fm-scroller__track fm-cats-scroller" role="list">
				<?php
				foreach ( $terms as $term ) :
					$thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
					$img      = $thumb_id ? wp_get_attachment_image( $thumb_id, 'frontmall-category', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => $term->name, 'sizes' => '(max-width:575px) 45vw, (max-width:991px) 22vw, 180px' ) ) : '';
					?>
					<li class="fm-scroller__cell">
						<a class="fm-cat-card" href="<?php echo esc_url( get_term_link( $term ) ); ?>">
							<span class="fm-cat-card__media"><?php echo $img ? wp_kses_post( $img ) : '<span class="fm-cat-card__ph" aria-hidden="true"></span>'; ?></span>
							<span class="fm-cat-card__body">
								<span class="fm-cat-card__name"><?php echo esc_html( $term->name ); ?></span>
								<span class="fm-cat-card__count"><?php echo esc_html( sprintf( _n( '%d product', '%d products', $term->count, 'frontmall' ), $term->count ) ); ?></span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
