<?php
/**
 * Fallback template (blog / archives).
 *
 * @package Frontmall
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="fm-container fm-main-wrap">
	<div class="fm-list">
		<?php if ( have_posts() ) : ?>
			<?php if ( is_home() && ! is_front_page() ) : ?>
				<header class="fm-page-head"><h1 class="fm-page-title"><?php single_post_title(); ?></h1></header>
			<?php endif; ?>
			<div class="fm-posts">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class( 'fm-post-card' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="fm-post-card__thumb" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?></a>
						<?php endif; ?>
						<h2 class="fm-post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<div class="fm-post-card__excerpt"><?php the_excerpt(); ?></div>
						<a class="fm-btn fm-btn--outline" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read more', 'frontmall' ); ?></a>
					</article>
					<?php
				endwhile;
				the_posts_pagination( array( 'mid_size' => 1 ) );
				?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing found.', 'frontmall' ); ?></p>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
