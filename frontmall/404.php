<?php
/**
 * 404 template with search and popular departments.
 *
 * @package Frontmall
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="fm-container fm-main-wrap">
	<div class="fm-404">
		<h1 class="fm-404__code">404</h1>
		<h2 class="fm-404__title"><?php esc_html_e( 'Page not found', 'frontmall' ); ?></h2>
		<p><?php esc_html_e( 'The page you are looking for may have moved. Try searching or browse our departments.', 'frontmall' ); ?></p>
		<?php get_search_form(); ?>
		<p><a class="fm-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'frontmall' ); ?></a></p>
		<div class="fm-404__depts">
			<?php foreach ( array_slice( frontmall_departments(), 0, 8 ) as $d ) :
				$term = get_term_by( 'name', $d, 'product_cat' );
				$url  = $term ? get_term_link( $term ) : add_query_arg( array( 's' => rawurlencode( $d ), 'post_type' => 'product' ), home_url( '/' ) );
				?>
				<a class="fm-chip" href="<?php echo esc_url( is_wp_error( $url ) ? '#' : $url ); ?>"><?php echo esc_html( $d ); ?></a>
			<?php endforeach; ?>
		</div>
	</div>
</div>
<?php
get_footer();
