<?php
/**
 * Sidebar for static pages: contact / help card + quick links to info pages.
 * Fills what would otherwise be empty space beside the content column.
 *
 * @package Frontmall
 */

defined( 'ABSPATH' ) || exit;

$b       = frontmall_business();
$tel     = preg_replace( '/[^0-9+]/', '', $b['phone'] );
$current = get_queried_object_id();

$links = array();
foreach ( frontmall_info_links() as $t ) {
	$url = frontmall_find_page( $t );
	if ( $url && $url !== get_permalink( $current ) ) {
		$links[ $t ] = $url;
	}
}
?>
<aside class="fm-page-aside" aria-label="<?php esc_attr_e( 'Need help', 'frontmall' ); ?>">
	<div class="fm-aside-card fm-aside-card--help">
		<h2 class="fm-aside-card__title"><?php esc_html_e( 'Need help?', 'frontmall' ); ?></h2>
		<p class="fm-aside-card__lead"><?php esc_html_e( 'Talk to our team, we reply fast on WhatsApp.', 'frontmall' ); ?></p>
		<a class="fm-btn fm-btn--wa" href="<?php echo esc_url( frontmall_whatsapp_url( 'Hello Frontmall, I would like some help.' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Chat on WhatsApp', 'frontmall' ); ?></a>
		<ul class="fm-aside-contact">
			<li><span><?php esc_html_e( 'Call / WhatsApp', 'frontmall' ); ?></span><a href="tel:<?php echo esc_attr( $tel ); ?>"><?php echo esc_html( $b['phone'] ); ?></a></li>
			<li><span><?php esc_html_e( 'Email', 'frontmall' ); ?></span><a href="mailto:<?php echo esc_attr( $b['email'] ); ?>"><?php echo esc_html( $b['email'] ); ?></a></li>
			<li><span><?php esc_html_e( 'Hours', 'frontmall' ); ?></span><?php echo esc_html( $b['hours'] ); ?></li>
			<li><span><?php esc_html_e( 'Visit', 'frontmall' ); ?></span><?php echo esc_html( $b['street'] . ', ' . $b['city'] ); ?></li>
		</ul>
	</div>

	<?php if ( ! empty( $links ) ) : ?>
	<div class="fm-aside-card">
		<h2 class="fm-aside-card__title"><?php esc_html_e( 'Helpful links', 'frontmall' ); ?></h2>
		<ul class="fm-aside-links">
			<?php foreach ( $links as $label => $url ) : ?>
				<li><a href="<?php echo esc_url( $url ); ?>"><span><?php echo esc_html( $label ); ?></span><span class="fm-aside-links__arrow" aria-hidden="true">&rarr;</span></a></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>
</aside>
