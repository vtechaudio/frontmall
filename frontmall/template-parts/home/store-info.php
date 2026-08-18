<?php
/**
 * Homepage store-info / trust strip: physical shop, hours, contact,
 * directions, and business registration / KRA PIN when provided.
 *
 * @package Frontmall
 */
defined( 'ABSPATH' ) || exit;
$fm = frontmall_business();
?>
<section class="fm-section fm-storeinfo" aria-labelledby="fm-storeinfo-title">
  <div class="fm-container fm-storeinfo__grid">
    <div class="fm-storeinfo__col">
      <h2 id="fm-storeinfo-title" class="fm-section__title"><?php esc_html_e( 'Visit Our Nairobi Shop', 'frontmall' ); ?></h2>
      <p><?php echo esc_html( $fm['street'] ); ?><br><?php echo esc_html( $fm['city'] . ', ' . $fm['region'] ); ?></p>
      <p class="fm-storeinfo__hours"><?php echo esc_html( $fm['hours'] ); ?></p>
      <p>
        <a href="tel:<?php echo esc_attr( str_replace( ' ', '', $fm['phone'] ) ); ?>"><?php echo esc_html( $fm['phone'] ); ?></a> &middot;
        <a href="mailto:<?php echo esc_attr( $fm['email'] ); ?>"><?php echo esc_html( $fm['email'] ); ?></a>
      </p>
      <p>
        <a class="fm-btn fm-btn--outline" href="<?php echo esc_url( frontmall_maps_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get directions', 'frontmall' ); ?></a>
        <a class="fm-btn fm-btn--outline" href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>"><?php esc_html_e( 'About Frontmall', 'frontmall' ); ?></a>
      </p>
      <?php if ( $fm['registration'] || $fm['vat'] ) : ?>
        <p class="fm-storeinfo__reg"><?php if ( $fm['registration'] ) : ?><?php printf( esc_html__( 'Business Reg: %s', 'frontmall' ), esc_html( $fm['registration'] ) ); ?><br><?php endif; ?><?php if ( $fm['vat'] ) : ?><?php printf( esc_html__( 'KRA PIN: %s', 'frontmall' ), esc_html( $fm['vat'] ) ); ?><?php endif; ?></p>
      <?php endif; ?>
    </div>
    <div class="fm-storeinfo__col fm-storeinfo__why">
      <h3><?php esc_html_e( 'Why shop with Frontmall', 'frontmall' ); ?></h3>
      <ul>
        <li><?php esc_html_e( 'Genuine, warranty-backed brands', 'frontmall' ); ?></li>
        <li><?php esc_html_e( 'Transparent prices in KES, taxes included', 'frontmall' ); ?></li>
        <li><?php esc_html_e( 'Pay on delivery, M-Pesa or card', 'frontmall' ); ?></li>
        <li><?php esc_html_e( 'A real shop you can visit in Nairobi CBD', 'frontmall' ); ?></li>
      </ul>
    </div>
  </div>
</section>
