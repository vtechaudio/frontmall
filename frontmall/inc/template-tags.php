<?php
/**
 * Reusable, filterable template helpers and business data.
 *
 * @package Frontmall
 */

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for business identity.
 *
 * Every field is editable in Appearance > Customize > "Frontmall: Business &
 * Contact" (the WhatsApp number lives under "WhatsApp Order Button"). The
 * hardcoded values below are only the fallback defaults.
 */
function frontmall_business(): array {
	$d = array(
		'name'     => 'Frontmall Online Shopping',
		'short'    => 'Frontmall',
		'phone'    => '+254 741 262 053',
		'whatsapp' => '254741262053',
		'email'    => 'info@frontmallke.com',
		'street'   => 'Ronald Ngala Street, Mozart Bet Building, 1st Floor, Room F25 (along Githurai 44 matatu stage)',
		'city'     => 'Nairobi CBD',
		'region'   => 'Nairobi',
		'postcode' => '00100',
		'country'  => 'KE',
		'hours'    => 'Mon - Sat: 8:00 AM - 6:00 PM (EAT)',
	);
	$defaults = array(
		'name'     => (string) get_theme_mod( 'frontmall_biz_name', $d['name'] ),
		'short'    => (string) get_theme_mod( 'frontmall_biz_short', $d['short'] ),
		'phone'    => (string) get_theme_mod( 'frontmall_biz_phone', $d['phone'] ),
		'whatsapp' => (string) get_theme_mod( 'frontmall_wa_number', $d['whatsapp'] ),
		'email'    => (string) get_theme_mod( 'frontmall_biz_email', $d['email'] ),
		'street'   => (string) get_theme_mod( 'frontmall_biz_street', $d['street'] ),
		'city'     => (string) get_theme_mod( 'frontmall_biz_city', $d['city'] ),
		'region'   => (string) get_theme_mod( 'frontmall_biz_region', $d['region'] ),
		'postcode' => (string) get_theme_mod( 'frontmall_biz_postcode', $d['postcode'] ),
		'country'  => $d['country'],
		'hours'    => (string) get_theme_mod( 'frontmall_biz_hours', $d['hours'] ),
		'founded'      => (string) get_theme_mod( 'frontmall_founded', '' ),
		'registration' => (string) get_theme_mod( 'frontmall_registration', '' ),
		'vat'          => (string) get_theme_mod( 'frontmall_vat', '' ),
	);
	return apply_filters( 'frontmall_business', $defaults );
}

/**
 * Fallback department list (used only when no product categories exist yet).
 */
function frontmall_departments(): array {
	return apply_filters(
		'frontmall_departments',
		array(
			'Solar Panels', 'Solar Street Lights', 'Water Pumps', 'Hardware Tools',
			'Weighing Scales', 'Batteries', 'Agricultural Equipment', 'Home Appliances',
			'Generators', 'Solar Inverters', 'Welding Machines', 'Drills', 'Saws', 'Grinders',
		)
	);
}

/**
 * The category sections rendered on the homepage, in order. Each entry has a
 * display label and one or more product_cat names/slugs to pull from (so we can
 * merge related categories, e.g. Solar Panels + Solar Street Lights).
 *
 * If the site owner has chosen categories in the Customizer
 * ( Appearance > Customize > "Frontmall: Homepage Category Sections" ), those
 * slots drive the homepage in that order. Otherwise the default order below is
 * used. Filter `frontmall_homepage_categories` to customise in code.
 */
function frontmall_homepage_categories(): array {
	// 1) Customizer-chosen slots take priority.
	$selected = array();
	if ( taxonomy_exists( 'product_cat' ) ) {
		for ( $i = 1; $i <= 8; $i++ ) {
			$term_id = (int) get_theme_mod( "frontmall_home_cat_{$i}", 0 );
			if ( $term_id <= 0 ) {
				continue;
			}
			$term = get_term( $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$selected[] = array( 'label' => $term->name, 'terms' => array( $term->slug ) );
			}
		}
	}
	if ( ! empty( $selected ) ) {
		return apply_filters( 'frontmall_homepage_categories', $selected );
	}

	// 2) Default order (used until you choose your own slots in the Customizer).
	return apply_filters(
		'frontmall_homepage_categories',
		array(
			array( 'label' => 'Egg Incubators',                     'terms' => array( 'Egg Incubators', 'Egg Incubator' ) ),
			array( 'label' => 'Solar Panels & Solar Street Lights', 'terms' => array( 'Solar Panels', 'Solar Street Lights' ) ),
			array( 'label' => 'Car Wash Equipment',                 'terms' => array( 'Car Wash Equipment', 'Car Wash' ) ),
			array( 'label' => 'Demolition Breakers',                'terms' => array( 'Demolition Breakers', 'Demolition Breaker' ) ),
			array( 'label' => 'Water Pumps',                        'terms' => array( 'Water Pumps', 'Water Pump' ) ),
			array( 'label' => 'Welding Machines',                   'terms' => array( 'Welding Machines', 'Welding Machine' ) ),
			array( 'label' => 'Vacuum Cleaners',                    'terms' => array( 'Vacuum Cleaners', 'Vacuum Cleaner' ) ),
		)
	);
}

/**
 * Resolve a list of category names/slugs to WC product_cat terms (deduped).
 *
 * @return \WP_Term[]
 */
function frontmall_resolve_terms( array $names ): array {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}
	$out = array();
	foreach ( $names as $n ) {
		$t = get_term_by( 'name', $n, 'product_cat' );
		if ( ! $t ) {
			$t = get_term_by( 'slug', sanitize_title( $n ), 'product_cat' );
		}
		if ( $t && ! is_wp_error( $t ) ) {
			$out[ $t->term_id ] = $t;
		}
	}
	return array_values( $out );
}

/**
 * Real product categories for the header/hero menu and strip (by product count).
 *
 * @return \WP_Term[]
 */
function frontmall_nav_categories( int $limit = 30 ): array {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'number'     => $limit,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		)
	);
	return ( is_wp_error( $terms ) || ! $terms ) ? array() : $terms;
}

/**
 * Render a single product card via the editable template part.
 */
function frontmall_product_card( $product ): void {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	set_query_var( 'frontmall_card_product', $product );
	get_template_part( 'template-parts/content', 'product-card' );
}

/**
 * WhatsApp click-to-chat URL.
 */
function frontmall_whatsapp_url( string $text = '' ): string {
	$b   = frontmall_business();
	$url = 'https://wa.me/' . preg_replace( '/\D/', '', $b['whatsapp'] );
	if ( $text ) {
		$url = add_query_arg( 'text', rawurlencode( $text ), $url );
	}
	return $url;
}


/**
 * Resolve a published page by exact title -> permalink (robust to slug variants).
 */
function frontmall_find_page( string $title ): string {
	$q = new WP_Query(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'title'          => $title,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		)
	);
	$url = $q->have_posts() ? get_permalink( (int) $q->posts[0] ) : '';
	wp_reset_postdata();
	return $url ? $url : '';
}

/**
 * Titles used for the "Helpful links" card on static pages.
 */
function frontmall_info_links(): array {
	return apply_filters(
		'frontmall_info_links',
		array(
			'About Us',
			'Shipping & Delivery Policy',
			'Return & Refund Policy',
			'Track Order',
			'Frequently Asked Questions',
			'Contact Us',
		)
	);
}


/**
 * Default homepage slides (used when the Customizer values are empty).
 */
function frontmall_slide_defaults(): array {
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	return array(
		array( 'eyebrow' => __( 'Powering Kenya', 'frontmall' ),  'title' => __( 'Solar, Power & Home Essentials', 'frontmall' ), 'text' => __( 'Genuine products at honest prices, delivered fast across the country.', 'frontmall' ), 'btn' => __( 'Shop Now', 'frontmall' ),   'link' => $shop ),
		array( 'eyebrow' => __( 'Solar Season', 'frontmall' ),    'title' => __( 'Solar Panels & Street Lights', 'frontmall' ),   'text' => __( 'Cut your power bills with reliable solar, backed by warranty.', 'frontmall' ),      'btn' => __( 'Shop Solar', 'frontmall' ), 'link' => $shop ),
		array( 'eyebrow' => __( 'Tools & Hardware', 'frontmall' ), 'title' => __( 'Power Tools Built to Last', 'frontmall' ),      'text' => __( 'Makita, DeWalt, Total, Ingco and more at the best prices.', 'frontmall' ),          'btn' => __( 'Shop Tools', 'frontmall' ), 'link' => $shop ),
	);
}

/**
 * Default side promo banners.
 */
function frontmall_promo_defaults(): array {
	$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	return array(
		array( 'title' => __( 'Water Pumps', 'frontmall' ),     'text' => __( 'For home, farm & industry', 'frontmall' ), 'link' => $shop ),
		array( 'title' => __( 'Weighing Scales', 'frontmall' ), 'text' => __( 'Accurate & built to last', 'frontmall' ),  'link' => $shop ),
	);
}

/**
 * Cached wrapper around attachment_url_to_postid(). The core call runs an
 * uncached meta query, and the homepage resolves the same slide URLs on every
 * uncached render - which is exactly what happens under a bot flood. We memoise
 * per request and persist the URL->ID map in a 12h transient (self-healing: a
 * new slide image has a new URL, so a new cache key) so repeated / attacked
 * page loads do far less database work.
 */
function frontmall_attachment_id_from_url( string $url ): int {
	if ( '' === $url ) {
		return 0;
	}
	static $memo = array();
	if ( array_key_exists( $url, $memo ) ) {
		return $memo[ $url ];
	}
	$key    = 'frontmall_att_' . md5( $url );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		$memo[ $url ] = (int) $cached;
		return (int) $cached;
	}
	$id = (int) attachment_url_to_postid( $url );
	set_transient( $key, $id, 12 * HOUR_IN_SECONDS );
	$memo[ $url ] = $id;
	return $id;
}

/**
 * Build responsive srcset/sizes attributes for a hero slide image that was
 * saved as a plain URL in the Customizer. Resolving the URL to an attachment
 * lets the browser pick a much smaller file on mobile instead of the full
 * ~1300px hero. Returns '' for external URLs or images with no generated
 * sizes, in which case the plain src is used as-is.
 */
function frontmall_slide_srcset( string $url ): string {
	if ( '' === $url ) {
		return '';
	}
	$id = frontmall_attachment_id_from_url( $url );
	if ( ! $id ) {
		return '';
	}
	$srcset = wp_get_attachment_image_srcset( $id, 'full' );
	if ( ! $srcset ) {
		return '';
	}
	return sprintf(
		' srcset="%s" sizes="%s"',
		esc_attr( $srcset ),
		esc_attr( '(max-width: 991px) 100vw, 720px' )
	);
}

/**
 * Merge Customizer values with defaults for the 3 hero slides.
 */
function frontmall_slides(): array {
	$out = array();
	foreach ( frontmall_slide_defaults() as $i => $d ) {
		$n     = $i + 1;
		$out[] = array(
			'index'   => $n,
			'image'   => (string) get_theme_mod( "frontmall_slide{$n}_image", '' ),
			'eyebrow' => (string) get_theme_mod( "frontmall_slide{$n}_eyebrow", $d['eyebrow'] ),
			'title'   => (string) get_theme_mod( "frontmall_slide{$n}_title", $d['title'] ),
			'text'    => (string) get_theme_mod( "frontmall_slide{$n}_text", $d['text'] ),
			'btn'     => (string) get_theme_mod( "frontmall_slide{$n}_btn", $d['btn'] ),
			'link'    => (string) get_theme_mod( "frontmall_slide{$n}_link", $d['link'] ),
		);
	}
	return $out;
}

/**
 * Merge Customizer values with defaults for the 2 side promos.
 */
function frontmall_promos(): array {
	$out = array();
	foreach ( frontmall_promo_defaults() as $i => $d ) {
		$n     = $i + 1;
		$out[] = array(
			'index' => $n,
			'image' => (string) get_theme_mod( "frontmall_promo{$n}_image", '' ),
			'title' => (string) get_theme_mod( "frontmall_promo{$n}_title", $d['title'] ),
			'text'  => (string) get_theme_mod( "frontmall_promo{$n}_text", $d['text'] ),
			'link'  => (string) get_theme_mod( "frontmall_promo{$n}_link", $d['link'] ),
		);
	}
	return $out;
}

/**
 * Default WhatsApp order intro message (real newlines for the chat).
 */
function frontmall_wa_default_message(): string {
	return "Hello Frontmall, I would like to order this item:\n\n{product}\nPrice: {price}\n{url}\n\nPlease assist me with the order.";
}

/**
 * Build the click-to-chat WhatsApp order URL for a product.
 */
function frontmall_wa_order_url( $product ): string {
	$number = preg_replace( '/\D/', '', (string) get_theme_mod( 'frontmall_wa_number', frontmall_business()['whatsapp'] ) );
	$tmpl   = (string) get_theme_mod( 'frontmall_wa_message', frontmall_wa_default_message() );
	$name   = $product instanceof WC_Product ? $product->get_name() : '';
	$price  = $product instanceof WC_Product ? trim( wp_strip_all_tags( $product->get_price_html() ) ) : '';
	$url    = $product instanceof WC_Product ? $product->get_permalink() : home_url( '/' );
	$msg    = strtr( $tmpl, array( '{product}' => $name, '{price}' => $price, '{url}' => $url ) );
	return 'https://wa.me/' . $number . '?text=' . rawurlencode( $msg );
}

/**
 * Inline WhatsApp glyph (safe static markup).
 */
function frontmall_wa_icon(): string {
	return '<svg class="fm-wa-svg" width="19" height="19" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M16 .6C7.4.6.5 7.5.5 16.1c0 2.8.8 5.5 2.1 7.8L.4 31.6l7.9-2.1c2.2 1.2 4.7 1.9 7.4 1.9h.3c8.6 0 15.5-6.9 15.5-15.5C31.5 7.5 24.6.6 16 .6zm0 28.3h-.2c-2.4 0-4.7-.6-6.7-1.8l-.5-.3-4.7 1.2 1.3-4.6-.3-.5c-1.3-2.1-2-4.5-2-7 0-7.1 5.8-12.9 13-12.9 3.5 0 6.7 1.4 9.2 3.8 2.4 2.4 3.8 5.7 3.8 9.1-.1 7.2-5.9 13-12.9 13zm7.1-9.6c-.4-.2-2.3-1.1-2.6-1.3-.4-.1-.6-.2-.9.2-.3.4-1 1.2-1.2 1.5-.2.2-.4.3-.8.1-.4-.2-1.6-.6-3.1-1.9-1.1-1-1.9-2.3-2.2-2.7-.2-.4 0-.6.2-.8.2-.2.4-.4.6-.7.2-.2.2-.4.4-.6.1-.3 0-.5 0-.7-.1-.2-.9-2.2-1.3-3-.3-.7-.6-.6-.9-.6h-.7c-.2 0-.6.1-.9.4-.3.4-1.2 1.2-1.2 2.9s1.2 3.4 1.4 3.7c.2.2 2.4 3.7 5.9 5.2.8.4 1.5.6 2 .7.8.3 1.6.2 2.2.1.7-.1 2.3-.9 2.6-1.8.3-.9.3-1.6.2-1.8-.1-.1-.3-.2-.7-.4z"/></svg>';
}


if ( ! function_exists( 'frontmall_is_wc_wrapped' ) ) {
	/**
	 * True on WooCommerce pages where the WooCommerce class already emits the
	 * <main id="primary"> wrapper, so header/footer must NOT emit a second one.
	 */
	function frontmall_is_wc_wrapped(): bool {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return false;
		}
		return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
	}
}


if ( ! function_exists( 'frontmall_is_shop_archive' ) ) {
	/**
	 * True on product listing archives (shop, product taxonomy, product search)
	 * where the filter sidebar and toolbar should appear.
	 */
	function frontmall_is_shop_archive(): bool {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}
		if ( is_shop() || is_product_taxonomy() ) {
			return true;
		}
		if ( is_search() && 'product' === get_query_var( 'post_type' ) ) {
			return true;
		}
		return false;
	}
}

/**
 * Digits-only WhatsApp number, editable in the Customizer (falls back to business default).
 */
function frontmall_wa_number(): string {
	$b   = frontmall_business();
	$num = (string) get_theme_mod( 'frontmall_wa_number', $b['whatsapp'] );
	return preg_replace( '/\D/', '', $num );
}

/**
 * Site-wide floating WhatsApp chat button. Fully editable in the Customizer
 * ( Appearance > Customize > Frontmall: Floating WhatsApp Button ). No JavaScript.
 * Hooked to wp_footer.
 */
function frontmall_render_wa_float(): void {
	if ( ! get_theme_mod( 'frontmall_wa_float_enable', true ) ) {
		return;
	}
	$number = frontmall_wa_number();
	if ( '' === $number ) {
		return;
	}
	$name    = (string) get_theme_mod( 'frontmall_wa_float_name', 'Frontmall Kenya' );
	$caption = (string) get_theme_mod( 'frontmall_wa_float_caption', __( 'Typically replies in minutes', 'frontmall' ) );
	$message = (string) get_theme_mod( 'frontmall_wa_float_message', __( 'Hello Frontmall, I would like to make an enquiry.', 'frontmall' ) );
	$url     = 'https://wa.me/' . $number;
	if ( '' !== trim( $message ) ) {
		$url = add_query_arg( 'text', rawurlencode( $message ), $url );
	}
	?>
	<a class="fm-wafloat" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener nofollow" aria-label="<?php echo esc_attr( sprintf( __( 'Chat with %s on WhatsApp', 'frontmall' ), $name ) ); ?>">
		<span class="fm-wafloat__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M19.11 17.23c-.29-.15-1.7-.84-1.96-.93-.26-.1-.45-.15-.64.14-.19.29-.74.93-.9 1.12-.17.19-.33.21-.62.07-.29-.15-1.22-.45-2.32-1.43-.86-.77-1.44-1.72-1.6-2-.17-.29-.02-.45.13-.6.13-.13.29-.33.43-.5.15-.17.19-.29.29-.48.1-.19.05-.36-.02-.5-.07-.15-.64-1.55-.88-2.12-.23-.55-.47-.48-.64-.49h-.55c-.19 0-.5.07-.76.36-.26.29-1 .98-1 2.38 0 1.4 1.02 2.76 1.17 2.95.14.19 2.01 3.08 4.88 4.32.68.29 1.21.47 1.63.6.68.22 1.31.19 1.8.12.55-.08 1.7-.69 1.94-1.36.24-.67.24-1.24.17-1.36-.07-.12-.26-.19-.55-.34zM12.05 21.5a9.4 9.4 0 0 1-4.81-1.31l-.34-.2-3.57.94.95-3.48-.22-.36a9.42 9.42 0 0 1-1.44-5.01c0-5.2 4.24-9.44 9.46-9.44 2.53 0 4.9.99 6.69 2.78a9.38 9.38 0 0 1 2.77 6.68c0 5.2-4.24 9.44-9.46 9.44z"/></svg></span>
		<span class="fm-wafloat__body">
			<span class="fm-wafloat__name"><?php echo esc_html( $name ); ?></span>
			<?php if ( '' !== trim( $caption ) ) : ?><span class="fm-wafloat__sub"><?php echo esc_html( $caption ); ?></span><?php endif; ?>
		</span>
	</a>
	<?php
}

function frontmall_maps_url(): string {
	$b = frontmall_business();
	$q = rawurlencode( $b['name'] . ', ' . $b['street'] . ', ' . $b['city'] . ', Kenya' );
	return 'https://www.google.com/maps/search/?api=1&query=' . $q;
}

/**
 * Lightweight transient cache for the heavy homepage product queries.
 * Caches resolved product IDs per section (1 hour) so the costly random-
 * ordered category queries do not run on every uncached page load. The
 * cache version bumps whenever a product changes, so the homepage refreshes
 * promptly without waiting for the TTL.
 */
function frontmall_cache_version(): string {
	return (string) get_option( 'frontmall_cache_ver', '1' );
}

function frontmall_bump_cache_version(): void {
	update_option( 'frontmall_cache_ver', (string) ( (int) get_option( 'frontmall_cache_ver', '1' ) + 1 ) );
}
add_action( 'save_post_product', 'frontmall_bump_cache_version' );
add_action( 'woocommerce_update_product', 'frontmall_bump_cache_version' );
add_action( 'woocommerce_product_set_stock', 'frontmall_bump_cache_version' );
add_action( 'woocommerce_variation_set_stock', 'frontmall_bump_cache_version' );
add_action( 'trashed_post', 'frontmall_bump_cache_version' );

function frontmall_homepage_products( string $key, array $slugs, int $limit = 6 ): array {
	if ( empty( $slugs ) ) {
		return array();
	}
	$ver  = frontmall_cache_version();
	$tkey = 'fm_home_' . md5( $ver . '|' . $key . '|' . implode( ',', $slugs ) . '|' . $limit );
	$ids  = get_transient( $tkey );
	if ( false === $ids ) {
		$ids = wc_get_products( array(
			'status'   => 'publish',
			'limit'    => $limit,
			'category' => $slugs,
			'orderby'  => 'rand',
			'return'   => 'ids',
		) );
		set_transient( $tkey, $ids, HOUR_IN_SECONDS );
	}
	if ( empty( $ids ) ) {
		return array();
	}
	return wc_get_products( array(
		'status'  => 'publish',
		'include' => $ids,
		'limit'   => $limit,
		'orderby' => 'none',
	) );
}
