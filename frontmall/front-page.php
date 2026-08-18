<?php
/**
 * Homepage: hero + promos, trust bar, department strip, featured categories,
 * and the configured category sections (frontmall_homepage_categories()).
 * Each section shows up to 6 products; grid collapses 6 -> 4 -> 3 -> 2.
 *
 * @package Frontmall
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>

<?php get_template_part( 'template-parts/home/hero' ); ?>
<?php get_template_part( 'template-parts/home/feature-bar' ); ?>
<div class="fm-container"><?php get_template_part( 'template-parts/home/category-strip' ); ?></div>
<?php get_template_part( 'template-parts/home/featured-categories' ); ?>

<?php
if ( function_exists( 'wc_get_products' ) ) :
	$rendered = 0;
	foreach ( frontmall_homepage_categories() as $entry ) :
		$terms = frontmall_resolve_terms( $entry['terms'] );
		if ( empty( $terms ) ) {
			continue;
		}
		$slugs    = wp_list_pluck( $terms, 'slug' );
		$products = frontmall_homepage_products( implode( ',', $slugs ), $slugs, 6 );
		if ( empty( $products ) ) {
			continue;
		}
		$rendered++;
		$primary  = $terms[0];
		$more_url = get_term_link( $primary );
		$anchor   = 'fm-cat-' . sanitize_html_class( $primary->slug );
		?>
		<section class="fm-section fm-cat-section" aria-labelledby="<?php echo esc_attr( $anchor ); ?>">
			<div class="fm-container">
				<div class="fm-section__head">
					<h2 id="<?php echo esc_attr( $anchor ); ?>" class="fm-section__title"><?php echo esc_html( $entry['label'] ); ?></h2>
					<a class="fm-section__more" href="<?php echo esc_url( is_wp_error( $more_url ) ? '#' : $more_url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View more %s', 'frontmall' ), $entry['label'] ) ); ?>"><?php esc_html_e( 'View more', 'frontmall' ); ?> &rarr;</a>
				</div>
				<div class="fm-grid fm-grid--products">
					<?php foreach ( $products as $product ) { frontmall_product_card( $product ); } ?>
				</div>
				<div class="fm-section__foot">
					<a class="fm-btn fm-btn--outline" href="<?php echo esc_url( is_wp_error( $more_url ) ? '#' : $more_url ); ?>"><?php printf( esc_html__( 'Shop all %s', 'frontmall' ), esc_html( $entry['label'] ) ); ?></a>
				</div>
			</div>
		</section>
		<?php
	endforeach;

	if ( 0 === $rendered ) :
		?>
		<section class="fm-section"><div class="fm-container"><div class="fm-empty">
			<h2><?php esc_html_e( 'Your storefront is ready', 'frontmall' ); ?></h2>
			<p><?php esc_html_e( 'Import your product catalogue and these category sections fill in automatically.', 'frontmall' ); ?></p>
			<a class="fm-btn" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&page=product_importer' ) ); ?>"><?php esc_html_e( 'Import products', 'frontmall' ); ?></a>
		</div></div></section>
		<?php
	endif;
else :
	?>
	<section class="fm-section"><div class="fm-container"><div class="fm-empty">
		<h2><?php esc_html_e( 'Activate WooCommerce to launch your shop', 'frontmall' ); ?></h2>
		<a class="fm-btn" href="<?php echo esc_url( admin_url( 'themes.php?page=frontmall-setup' ) ); ?>"><?php esc_html_e( 'Run Frontmall setup', 'frontmall' ); ?></a>
	</div></div></section>
	<?php
endif;

get_template_part( 'template-parts/home/store-info' );

get_footer();
