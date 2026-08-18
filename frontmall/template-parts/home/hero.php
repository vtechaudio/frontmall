<?php
/**
 * Hero: vertical department menu (real live categories), a 3-slide rotating
 * slider, and two editable side promo banners. The first slide image is
 * rendered as an eager, high-priority <img> so it is the discoverable LCP
 * element (no CSS background hidden from the preload scanner).
 *
 * @package Frontmall
 */
defined( 'ABSPATH' ) || exit;
$shop   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$nav    = frontmall_nav_categories( 30 );
$slides = frontmall_slides();
$promos = frontmall_promos();
?>
<section class="fm-hero fm-container" aria-label="<?php esc_attr_e( 'Featured', 'frontmall' ); ?>">
	<aside class="fm-hero__departments" aria-label="<?php esc_attr_e( 'Shop by department', 'frontmall' ); ?>">
		<h2 class="fm-hero__dept-title"><?php esc_html_e( 'Shop By Department', 'frontmall' ); ?></h2>
		<ul class="fm-dept-list">
			<?php
			if ( ! empty( $nav ) ) {
				foreach ( $nav as $term ) {
					printf( '<li><a href="%s">%s</a></li>', esc_url( get_term_link( $term ) ), esc_html( $term->name ) );
				}
			} else {
				foreach ( frontmall_departments() as $dept ) {
					$url = add_query_arg( array( 's' => rawurlencode( $dept ), 'post_type' => 'product' ), home_url( '/' ) );
					printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $dept ) );
				}
			}
			?>
		</ul>
	</aside>

	<div class="fm-hero__slider" data-fm-slider>
		<div class="fm-slider__track">
			<?php
			$slide_i = 0;
			foreach ( $slides as $s ) :
				$has_img  = ( '' !== $s['image'] );
				$is_first = ( 0 === $slide_i );
				$classes  = 'fm-slide fm-slide--' . (int) $s['index'] . ( $has_img ? ' fm-slide--img' : '' );
				$link     = $s['link'] ? $s['link'] : $shop;
				?>
				<div class="<?php echo esc_attr( $classes ); ?>">
					<?php if ( $has_img ) : ?>
						<img class="fm-slide__img" src="<?php echo esc_url( $s['image'] ); ?>"<?php echo frontmall_slide_srcset( $s['image'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped attribute string. ?> alt="<?php echo esc_attr( $s['title'] ? $s['title'] : get_bloginfo( 'name' ) ); ?>"
							<?php if ( $is_first ) : ?>fetchpriority="high" loading="eager"<?php else : ?>loading="lazy"<?php endif; ?> decoding="async">
					<?php endif; ?>
					<div class="fm-slide__content">
						<?php if ( $s['eyebrow'] ) : ?><span class="fm-slide__eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span><?php endif; ?>
						<?php if ( $s['title'] ) : ?><h2 class="fm-slide__title"><?php echo esc_html( $s['title'] ); ?></h2><?php endif; ?>
						<?php if ( $s['text'] ) : ?><p class="fm-slide__text"><?php echo esc_html( $s['text'] ); ?></p><?php endif; ?>
						<?php if ( $s['btn'] ) : ?><a class="fm-btn fm-btn--lg" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $s['btn'] ); ?></a><?php endif; ?>
					</div>
				</div>
				<?php
				$slide_i++;
			endforeach;
			?>
		</div>
		<?php if ( count( $slides ) > 1 ) : ?>
			<button class="fm-slider__arrow fm-slider__arrow--prev" type="button" aria-label="<?php esc_attr_e( 'Previous slide', 'frontmall' ); ?>">&#8249;</button>
			<button class="fm-slider__arrow fm-slider__arrow--next" type="button" aria-label="<?php esc_attr_e( 'Next slide', 'frontmall' ); ?>">&#8250;</button>
			<div class="fm-slider__dots">
				<?php foreach ( $slides as $idx => $s ) : ?>
					<button class="fm-slider__dot<?php echo 0 === $idx ? ' is-active' : ''; ?>" type="button" data-slide="<?php echo esc_attr( $idx ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Go to slide %d', 'frontmall' ), $idx + 1 ) ); ?>"></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="fm-hero__promos">
		<?php
		foreach ( $promos as $p ) :
			$has_img = ( '' !== $p['image'] );
			$style   = $has_img ? ' style="background-image:url(' . esc_url( $p['image'] ) . ')"' : '';
			$classes = 'fm-promo fm-promo--' . (int) $p['index'] . ( $has_img ? ' fm-promo--img' : '' );
			$link    = $p['link'] ? $p['link'] : $shop;
			?>
			<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $link ); ?>"<?php echo $style; // phpcs:ignore ?>>
				<span class="fm-promo__inner">
					<?php if ( $p['title'] ) : ?><span class="fm-promo__t"><?php echo esc_html( $p['title'] ); ?></span><?php endif; ?>
					<?php if ( $p['text'] ) : ?><span class="fm-promo__k"><?php echo esc_html( $p['text'] ); ?></span><?php endif; ?>
					<span class="fm-promo__l"><?php esc_html_e( 'Shop now', 'frontmall' ); ?></span>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
</section>
