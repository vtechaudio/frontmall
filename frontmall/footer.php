<?php
/**
 * Footer with company info, quick links, policies, newsletter, payments.
 *
 * @package Frontmall
 */

defined( 'ABSPATH' ) || exit;
$fm = frontmall_business();
?>
<?php if ( ! frontmall_is_wc_wrapped() ) : ?></main><?php endif; ?>
</div><!-- #content -->

<footer id="colophon" class="fm-footer">
	<div class="fm-container fm-footer__grid">
		<div class="fm-footer__col fm-footer__about">
			<h4 class="fm-footer-widget__title"><?php echo esc_html( $fm['name'] ); ?></h4>
			<p><?php esc_html_e( 'Your trusted online store for solar, power tools, electronics and home appliances in Kenya.', 'frontmall' ); ?></p>
			<p>
				<?php echo esc_html( $fm['street'] ); ?><br>
				<?php echo esc_html( $fm['city'] . ', ' . $fm['region'] ); ?><br>
				<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $fm['phone'] ) ); ?>"><?php echo esc_html( $fm['phone'] ); ?></a><br>
				<a href="mailto:<?php echo esc_attr( $fm['email'] ); ?>"><?php echo esc_html( $fm['email'] ); ?></a>
			</p>
			<p class="fm-footer__hours"><?php echo esc_html( $fm['hours'] ); ?></p>
			<p><a href="<?php echo esc_url( frontmall_maps_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get directions', 'frontmall' ); ?></a></p>
			<?php if ( $fm['registration'] || $fm['vat'] ) : ?>
				<p class="fm-footer__reg"><?php if ( $fm['registration'] ) : ?><?php printf( esc_html__( 'Business Reg: %s', 'frontmall' ), esc_html( $fm['registration'] ) ); ?><br><?php endif; ?><?php if ( $fm['vat'] ) : ?><?php printf( esc_html__( 'KRA PIN: %s', 'frontmall' ), esc_html( $fm['vat'] ) ); ?><?php endif; ?></p>
			<?php endif; ?>
		</div>

		<div class="fm-footer__col">
			<h4 class="fm-footer-widget__title"><?php esc_html_e( 'Customer Service', 'frontmall' ); ?></h4>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>"><?php esc_html_e( 'About Us', 'frontmall' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'frontmall' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/track-order/' ) ); ?>"><?php esc_html_e( 'Track Order', 'frontmall' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/frequently-asked-questions/' ) ); ?>"><?php esc_html_e( 'FAQ', 'frontmall' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/payment-methods/' ) ); ?>"><?php esc_html_e( 'Payment Methods', 'frontmall' ); ?></a></li>
			</ul>
		</div>

		<div class="fm-footer__col">
			<h4 class="fm-footer-widget__title"><?php esc_html_e( 'Policies', 'frontmall' ); ?></h4>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'frontmall' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/terms-conditions/' ) ); ?>"><?php esc_html_e( 'Terms & Conditions', 'frontmall' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/return-refund-policy/' ) ); ?>"><?php esc_html_e( 'Return & Refund Policy', 'frontmall' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/shipping-delivery-policy/' ) ); ?>"><?php esc_html_e( 'Shipping & Delivery Policy', 'frontmall' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/warranty-policy/' ) ); ?>"><?php esc_html_e( 'Warranty Policy', 'frontmall' ); ?></a></li>
			</ul>
		</div>

		<div class="fm-footer__col fm-footer__newsletter">
			<h4 class="fm-footer-widget__title"><?php esc_html_e( 'Stay in the Loop', 'frontmall' ); ?></h4>
			<p><?php esc_html_e( 'Get deals and new arrivals in your inbox.', 'frontmall' ); ?></p>
			<form class="fm-newsletter" method="post" data-fm-newsletter action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
				<input type="hidden" name="action" value="frontmall_subscribe">
				<?php wp_nonce_field( 'frontmall_subscribe', 'fm_nl_nonce' ); ?>
				<input type="hidden" name="fm_nl_t" value="<?php echo esc_attr( (string) time() ); ?>">
				<label class="screen-reader-text" for="fm-nl"><?php esc_html_e( 'Email address', 'frontmall' ); ?></label>
				<p class="fm-hp" aria-hidden="true"><label>Leave this field empty<input type="text" name="fm_hp" tabindex="-1" autocomplete="off" value=""></label></p>
				<input type="email" id="fm-nl" name="fm_nl_email" placeholder="<?php esc_attr_e( 'Your email', 'frontmall' ); ?>" required>
				<button type="submit"><?php esc_html_e( 'Subscribe', 'frontmall' ); ?></button>
				<p class="fm-newsletter__msg" data-nl-msg role="status" aria-live="polite" hidden></p>
			</form>
			<a class="fm-footer__wa" href="<?php echo esc_url( frontmall_whatsapp_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Chat on WhatsApp', 'frontmall' ); ?></a>
		</div>
	</div>

	<div class="fm-footer__bar">
		<div class="fm-container fm-footer__bar-inner">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $fm['name'] ); ?>. <?php esc_html_e( 'All rights reserved.', 'frontmall' ); ?></p>
			<p class="fm-payments"><?php esc_html_e( 'Secured Payment Methods', 'frontmall' ); ?> <span>M-Pesa</span> <span>Visa</span> <span>Mastercard</span> <span>Bank Transfer</span></p>
		</div>
	</div>
</footer>

<button class="fm-to-top" aria-label="<?php esc_attr_e( 'Back to top', 'frontmall' ); ?>" hidden>&#8593;</button>

<?php wp_footer(); ?>
</body>
</html>
