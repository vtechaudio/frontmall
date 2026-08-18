<?php
/**
 * Static page template (About, policies, contact, etc.).
 * Full-width, well-aligned layout: a full-width page header, then a two-column
 * grid (readable content + sticky help/links aside) that fills the container
 * edge-to-edge, matching the site header and footer.
 *
 * @package Frontmall
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="fm-container fm-main-wrap">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<header class="fm-page-head">
			<nav class="fm-page-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'frontmall' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'frontmall' ); ?></a>
				<span class="fm-sep" aria-hidden="true">/</span>
				<span class="fm-here"><?php the_title(); ?></span>
			</nav>
			<h1 class="fm-page-title"><?php the_title(); ?></h1>
		</header>

		<div class="fm-page-layout">
			<div class="fm-page">
				<div class="fm-page__content fm-prose"><?php the_content(); ?></div>
				<?php
				if ( comments_open() || get_comments_number() ) {
					comments_template();
				}
				?>
			</div>
			<?php get_template_part( 'template-parts/page-aside' ); ?>
		</div>
		<?php
	endwhile;
	?>
</div>
<?php
get_footer();
