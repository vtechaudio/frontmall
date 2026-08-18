<?php
/**
 * Header: top contact bar, main header (logo + AJAX search + account/cart),
 * primary navigation with sticky behaviour.
 *
 * @package Frontmall
 */

defined( 'ABSPATH' ) || exit;
$fm = frontmall_business();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="fm-skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'frontmall' ); ?></a>

<div class="fm-topbar">
	<div class="fm-container fm-topbar__inner">
		<p class="fm-topbar__msg"><?php esc_html_e( 'Genuine products. Fast countrywide delivery. Pay on Delivery accepted - cash or M-Pesa.', 'frontmall' ); ?></p>
		<ul class="fm-topbar__links">
			<li><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $fm['phone'] ) ); ?>"><?php echo esc_html( $fm['phone'] ); ?></a></li>
			<li><a href="<?php echo esc_url( frontmall_whatsapp_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'WhatsApp', 'frontmall' ); ?></a></li>
			<li><a href="mailto:<?php echo esc_attr( $fm['email'] ); ?>"><?php echo esc_html( $fm['email'] ); ?></a></li>
			<li class="fm-topbar__hours"><?php echo esc_html( $fm['hours'] ); ?></li>
		</ul>
	</div>
</div>

<header id="masthead" class="fm-header" data-sticky>
	<div class="fm-container fm-header__main">
		<button class="fm-burger" aria-label="<?php esc_attr_e( 'Open menu', 'frontmall' ); ?>" aria-expanded="false" aria-controls="fm-mobile-nav">
			<span></span><span></span><span></span>
		</button>

		<div class="fm-header__brand">
			<?php if ( has_custom_logo() ) : the_custom_logo(); else : ?>
				<a class="fm-logo-text" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
			<?php endif; ?>
		</div>

		<div class="fm-header__search">
			<form role="search" method="get" class="fm-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="screen-reader-text" for="fm-search-input"><?php esc_html_e( 'Search products', 'frontmall' ); ?></label>
				<input type="search" id="fm-search-input" class="fm-search__input" name="s"
					placeholder="<?php esc_attr_e( 'Search products, brands, SKU or categories...', 'frontmall' ); ?>"
					autocomplete="off" aria-expanded="false" aria-owns="fm-search-results" role="combobox" aria-autocomplete="list">
				<input type="hidden" name="post_type" value="product">
				<button type="submit" class="fm-search__btn" aria-label="<?php esc_attr_e( 'Search', 'frontmall' ); ?>">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
				</button>
				<div id="fm-search-results" class="fm-search__results" role="listbox" hidden></div>
			</form>
		</div>

		<div class="fm-header__actions">
			<a class="fm-action" href="<?php echo esc_url( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ) ); ?>">
				<span class="fm-action__icon" aria-hidden="true">&#128100;</span>
				<span class="fm-action__label"><?php esc_html_e( 'Account', 'frontmall' ); ?></span>
			</a>
			<a class="fm-action fm-action--wishlist" href="<?php echo esc_url( home_url( '/wishlist/' ) ); ?>">
				<span class="fm-action__icon" aria-hidden="true">&#9825;</span>
				<span class="fm-action__label"><?php esc_html_e( 'Wishlist', 'frontmall' ); ?></span>
				<span class="fm-wish-count" data-count="0" hidden></span>
			</a>
			<a class="fm-action fm-action--cart" href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>">
				<span class="fm-action__icon" aria-hidden="true">&#128722;</span>
				<span class="fm-action__label"><?php esc_html_e( 'Cart', 'frontmall' ); ?></span>
				<span class="fm-cart-count" data-count="<?php echo esc_attr( function_exists( 'WC' ) && WC()->cart ? (string) WC()->cart->get_cart_contents_count() : '0' ); ?>"><?php echo esc_html( function_exists( 'WC' ) && WC()->cart ? (string) WC()->cart->get_cart_contents_count() : '0' ); ?></span>
			</a>
		</div>
	</div>

	<nav class="fm-nav" id="fm-primary-nav" aria-label="<?php esc_attr_e( 'Primary', 'frontmall' ); ?>">
		<div class="fm-container fm-nav__inner">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'fm-nav__menu',
						'depth'          => 2,
						'fallback_cb'    => false,
					)
				);
			} else {
				echo '<ul class="fm-nav__menu">';
				$links = array(
					home_url( '/about-us/' )                 => __( 'About Us', 'frontmall' ),
					function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) => __( 'Shop', 'frontmall' ),
					home_url( '/shipping-delivery-policy/' )  => __( 'Shipping', 'frontmall' ),
					home_url( '/return-refund-policy/' )      => __( 'Returns', 'frontmall' ),
					home_url( '/frequently-asked-questions/' )=> __( 'FAQ', 'frontmall' ),
					home_url( '/contact-us/' )                => __( 'Contact Us', 'frontmall' ),
				);
				foreach ( $links as $url => $label ) {
					printf( '<li class="menu-item"><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
				}
				echo '</ul>';
			}
			?>
		</div>
	</nav>
</header>

<nav id="fm-mobile-nav" class="fm-mobile-nav" aria-label="<?php esc_attr_e( 'Mobile', 'frontmall' ); ?>" hidden>
	<div class="fm-mobile-nav__inner">
		<?php
		if ( has_nav_menu( 'primary' ) ) {
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'fm-mobile-nav__menu',
					'depth'          => 2,
					'fallback_cb'    => false,
				)
			);
		} else {
			echo '<ul class="fm-mobile-nav__menu">';
			$fm_links = array(
				home_url( '/about-us/' )                   => __( 'About Us', 'frontmall' ),
				( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ) => __( 'Shop', 'frontmall' ),
				home_url( '/shipping-delivery-policy/' )   => __( 'Shipping', 'frontmall' ),
				home_url( '/return-refund-policy/' )       => __( 'Returns', 'frontmall' ),
				home_url( '/frequently-asked-questions/' ) => __( 'FAQ', 'frontmall' ),
				home_url( '/contact-us/' )                 => __( 'Contact Us', 'frontmall' ),
			);
			foreach ( $fm_links as $fm_url => $fm_label ) {
				printf( '<li class="menu-item"><a href="%s">%s</a></li>', esc_url( $fm_url ), esc_html( $fm_label ) );
			}
			echo '</ul>';
		}

		$fm_mcats = function_exists( 'frontmall_nav_categories' ) ? frontmall_nav_categories( 40 ) : array();
		if ( ! empty( $fm_mcats ) ) :
			$fm_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
			?>
			<h3 class="fm-mobile-nav__heading"><?php esc_html_e( 'Shop by Department', 'frontmall' ); ?></h3>
			<ul class="fm-mobile-nav__cats">
				<?php foreach ( $fm_mcats as $fm_term ) : ?>
					<li>
						<a href="<?php echo esc_url( get_term_link( $fm_term ) ); ?>">
							<span class="fm-mobile-nav__cat-name"><?php echo esc_html( $fm_term->name ); ?></span>
							<span class="fm-mobile-nav__count"><?php echo esc_html( (string) $fm_term->count ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<a class="fm-btn fm-mobile-nav__all" href="<?php echo esc_url( $fm_shop ); ?>"><?php esc_html_e( 'View all products', 'frontmall' ); ?></a>
		<?php endif; ?>
	</div>
</nav>

<div id="content" class="fm-site-content">
<?php if ( ! frontmall_is_wc_wrapped() ) : ?><main id="primary" class="fm-main" tabindex="-1"><?php endif; ?>
