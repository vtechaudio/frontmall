<?php
/**
 * Horizontal category pill strip. Pulls real product categories when available,
 * otherwise falls back to the static department list.
 *
 * @package Frontmall
 */
defined( 'ABSPATH' ) || exit;
$nav = frontmall_nav_categories( 16 );
?>
<section class="fm-cat-strip" aria-label="<?php esc_attr_e( 'Browse departments', 'frontmall' ); ?>">
	<div class="fm-container">
		<div class="fm-cat-strip__scroll">
			<?php
			if ( ! empty( $nav ) ) {
				foreach ( $nav as $term ) {
					printf( '<a class="fm-cat-pill" href="%s"><span aria-hidden="true"></span>%s</a>', esc_url( get_term_link( $term ) ), esc_html( $term->name ) );
				}
			} else {
				foreach ( frontmall_departments() as $dept ) {
					$url = add_query_arg( array( 's' => rawurlencode( $dept ), 'post_type' => 'product' ), home_url( '/' ) );
					printf( '<a class="fm-cat-pill" href="%s"><span aria-hidden="true"></span>%s</a>', esc_url( $url ), esc_html( $dept ) );
				}
			}
			?>
		</div>
	</div>
</section>
