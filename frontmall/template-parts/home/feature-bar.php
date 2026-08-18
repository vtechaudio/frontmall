<?php
/**
 * Trust / feature bar - reassurance signals for conversion + Merchant trust.
 *
 * @package Frontmall
 */
defined( 'ABSPATH' ) || exit;
$items = array(
	array( 'M9 17h6M5 17H3V6h13v11h-2M16 9h4l3 4v4h-3', __( 'Fast Countrywide Delivery', 'frontmall' ), __( 'Same/next-day in Nairobi', 'frontmall' ) ),
	array( 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z M9 12l2 2 4-4', __( '100% Genuine Products', 'frontmall' ), __( 'Warranty-backed brands', 'frontmall' ) ),
	array( 'M5 4h14v16H5z M9 4v16 M12 8h4M12 12h4', __( 'Pay via M-Pesa or Card', 'frontmall' ), __( 'Secure checkout', 'frontmall' ) ),
		array( 'M3 7h13v8H3z M16 10h3l2 3v2h-5 M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z M17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z', __( 'Pay on Delivery Accepted', 'frontmall' ), __( 'Cash or M-Pesa on arrival', 'frontmall' ) ),
	array( 'M4 4h16v12H4z M2 20h20 M8 8h8', __( 'Real Human Support', 'frontmall' ), __( 'Call, WhatsApp or email', 'frontmall' ) ),
);
?>
<section class="fm-feature-bar" aria-label="<?php esc_attr_e( 'Why shop with us', 'frontmall' ); ?>">
	<div class="fm-container fm-feature-bar__grid">
		<?php foreach ( $items as $it ) : ?>
			<div class="fm-feature">
				<span class="fm-feature__ic" aria-hidden="true">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?php echo esc_attr( $it[0] ); ?>"/></svg>
				</span>
				<span>
					<span class="fm-feature__t"><?php echo esc_html( $it[1] ); ?></span><br>
					<span class="fm-feature__s"><?php echo esc_html( $it[2] ); ?></span>
				</span>
			</div>
		<?php endforeach; ?>
	</div>
</section>
