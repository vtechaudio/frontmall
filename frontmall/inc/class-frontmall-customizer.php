<?php
/**
 * Customizer: editable business identity & contact details, homepage category
 * sections, homepage slider (3 slides), side promo images (2), and the
 * WhatsApp order button. All editable from Appearance > Customize, no code.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Customizer {

	private static ?Customizer $instance = null;

	public static function instance(): Customizer {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'customize_register', array( $this, 'register' ) );
	}

	public function register( \WP_Customize_Manager $wp ): void {
		$this->home_categories( $wp );
		$this->business( $wp );
		$this->slider( $wp );
		$this->promos( $wp );
		$this->whatsapp( $wp );
		$this->wa_float( $wp );
	}

	private function text( \WP_Customize_Manager $wp, string $id, string $section, string $label, string $default = '', string $type = 'text' ): void {
		$sanitize = ( 'url' === $type ) ? 'esc_url_raw' : ( 'textarea' === $type ? 'sanitize_textarea_field' : 'sanitize_text_field' );
		$wp->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => $sanitize ) );
		$wp->add_control( $id, array( 'label' => $label, 'section' => $section, 'type' => $type ) );
	}

	private function image( \WP_Customize_Manager $wp, string $id, string $section, string $label, string $description = '' ): void {
		$wp->add_setting( $id, array( 'sanitize_callback' => 'esc_url_raw' ) );
		$wp->add_control( new \WP_Customize_Image_Control( $wp, $id, array(
			'label'       => $label,
			'description' => $description,
			'section'     => $section,
		) ) );
	}

	/**
	 * Homepage category sections: up to 8 ordered slots, each choosing a
	 * product category. Leave all on "None" to use the theme's default order.
	 */
	private function home_categories( \WP_Customize_Manager $wp ): void {
		$wp->add_section( 'frontmall_home_cats', array(
			'title'       => __( 'Frontmall: Homepage Category Sections', 'frontmall' ),
			'description' => __( 'Choose which product categories appear on the homepage, and in what order. Each slot shows up to 6 products from that category. Set a slot to "None" to hide it. Leave every slot on "None" to use the default order (Egg Incubators, Solar Panels & Solar Street Lights, Car Wash Equipment, Demolition Breakers, Water Pumps, Welding Machines, Vacuum Cleaners).', 'frontmall' ),
			'priority'    => 28,
		) );

		$choices = array( 0 => __( '— None —', 'frontmall' ) );
		if ( taxonomy_exists( 'product_cat' ) ) {
			$terms = get_terms( array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 200,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					$choices[ (int) $t->term_id ] = $t->name;
				}
			}
		}

		for ( $i = 1; $i <= 8; $i++ ) {
			$wp->add_setting( "frontmall_home_cat_{$i}", array(
				'default'           => 0,
				'sanitize_callback' => 'absint',
			) );
			$wp->add_control( "frontmall_home_cat_{$i}", array(
				'label'   => sprintf( __( 'Homepage section %d', 'frontmall' ), $i ),
				'section' => 'frontmall_home_cats',
				'type'    => 'select',
				'choices' => $choices,
			) );
		}
	}

	private function business( $wp ): void {
		$wp->add_section( 'frontmall_business', array(
			'title'       => __( 'Frontmall: Business & Contact', 'frontmall' ),
			'description' => __( 'Your business name, contact details and address. These feed the footer, homepage store info, contact and policy pages, and structured data (SEO / Google Merchant Center). Edit here to update them across the whole site. Leave a field on its default to keep the current value.', 'frontmall' ),
			'priority'    => 29,
		) );
		$b = frontmall_business();
		$this->text( $wp, 'frontmall_biz_name', 'frontmall_business', __( 'Business name', 'frontmall' ), $b['name'] );
		$this->text( $wp, 'frontmall_biz_short', 'frontmall_business', __( 'Short name', 'frontmall' ), $b['short'] );
		$this->text( $wp, 'frontmall_biz_phone', 'frontmall_business', __( 'Phone (display format)', 'frontmall' ), $b['phone'] );
		$this->text( $wp, 'frontmall_biz_email', 'frontmall_business', __( 'Email address', 'frontmall' ), $b['email'] );
		$this->text( $wp, 'frontmall_biz_street', 'frontmall_business', __( 'Street address', 'frontmall' ), $b['street'] );
		$this->text( $wp, 'frontmall_biz_city', 'frontmall_business', __( 'City / town', 'frontmall' ), $b['city'] );
		$this->text( $wp, 'frontmall_biz_region', 'frontmall_business', __( 'Region / county', 'frontmall' ), $b['region'] );
		$this->text( $wp, 'frontmall_biz_postcode', 'frontmall_business', __( 'Postal code', 'frontmall' ), $b['postcode'] );
		$this->text( $wp, 'frontmall_biz_hours', 'frontmall_business', __( 'Business hours', 'frontmall' ), $b['hours'] );
		$this->text( $wp, 'frontmall_founded', 'frontmall_business', __( 'Year founded (e.g. 2022)', 'frontmall' ) );
		$this->text( $wp, 'frontmall_registration', 'frontmall_business', __( 'Business registration number', 'frontmall' ) );
		$this->text( $wp, 'frontmall_vat', 'frontmall_business', __( 'KRA PIN / VAT number', 'frontmall' ) );
	}

	private function slider( \WP_Customize_Manager $wp ): void {
		$wp->add_section( 'frontmall_slider', array(
			'title'       => __( 'Frontmall: Home Slider', 'frontmall' ),
			'description' => __( 'Three rotating slides on the homepage. Leave an image empty to use a coloured gradient slide.', 'frontmall' ),
			'priority'    => 30,
		) );
		$defaults = frontmall_slide_defaults();
		foreach ( array( 1, 2, 3 ) as $i ) {
			$d = $defaults[ $i - 1 ];
			$this->image( $wp, "frontmall_slide{$i}_image", 'frontmall_slider', sprintf( __( 'Slide %d image', 'frontmall' ), $i ), __( 'Wide image, around 1200x520px.', 'frontmall' ) );
			$this->text( $wp, "frontmall_slide{$i}_eyebrow", 'frontmall_slider', sprintf( __( 'Slide %d label', 'frontmall' ), $i ), $d['eyebrow'] );
			$this->text( $wp, "frontmall_slide{$i}_title", 'frontmall_slider', sprintf( __( 'Slide %d heading', 'frontmall' ), $i ), $d['title'] );
			$this->text( $wp, "frontmall_slide{$i}_text", 'frontmall_slider', sprintf( __( 'Slide %d text', 'frontmall' ), $i ), $d['text'] );
			$this->text( $wp, "frontmall_slide{$i}_btn", 'frontmall_slider', sprintf( __( 'Slide %d button text', 'frontmall' ), $i ), $d['btn'] );
			$this->text( $wp, "frontmall_slide{$i}_link", 'frontmall_slider', sprintf( __( 'Slide %d button link', 'frontmall' ), $i ), $d['link'], 'url' );
		}
	}

	private function promos( \WP_Customize_Manager $wp ): void {
		$wp->add_section( 'frontmall_promos', array(
			'title'       => __( 'Frontmall: Side Promo Images', 'frontmall' ),
			'description' => __( 'The two banners beside the slider. Upload a real image for each, or leave empty for a gradient.', 'frontmall' ),
			'priority'    => 31,
		) );
		$defaults = frontmall_promo_defaults();
		foreach ( array( 1, 2 ) as $i ) {
			$d = $defaults[ $i - 1 ];
			$this->image( $wp, "frontmall_promo{$i}_image", 'frontmall_promos', sprintf( __( 'Promo %d image', 'frontmall' ), $i ), __( 'Around 600x300px.', 'frontmall' ) );
			$this->text( $wp, "frontmall_promo{$i}_title", 'frontmall_promos', sprintf( __( 'Promo %d heading', 'frontmall' ), $i ), $d['title'] );
			$this->text( $wp, "frontmall_promo{$i}_text", 'frontmall_promos', sprintf( __( 'Promo %d text', 'frontmall' ), $i ), $d['text'] );
			$this->text( $wp, "frontmall_promo{$i}_link", 'frontmall_promos', sprintf( __( 'Promo %d link', 'frontmall' ), $i ), $d['link'], 'url' );
		}
	}

	private function whatsapp( \WP_Customize_Manager $wp ): void {
		$wp->add_section( 'frontmall_whatsapp', array(
			'title'       => __( 'Frontmall: WhatsApp Order Button', 'frontmall' ),
			'description' => __( 'Shows an "Order on WhatsApp" button next to Add to Cart on product pages.', 'frontmall' ),
			'priority'    => 32,
		) );
		$b = frontmall_business();

		$wp->add_setting( 'frontmall_wa_enable', array( 'default' => true, 'sanitize_callback' => 'wp_validate_boolean' ) );
		$wp->add_control( 'frontmall_wa_enable', array(
			'label'   => __( 'Show the Order on WhatsApp button', 'frontmall' ),
			'section' => 'frontmall_whatsapp',
			'type'    => 'checkbox',
		) );

		$this->text( $wp, 'frontmall_wa_number', 'frontmall_whatsapp', __( 'WhatsApp number (digits only, e.g. 254741262053)', 'frontmall' ), $b['whatsapp'] );
		$this->text( $wp, 'frontmall_wa_label', 'frontmall_whatsapp', __( 'Button label', 'frontmall' ), __( 'Order on WhatsApp', 'frontmall' ) );
		$this->text( $wp, 'frontmall_wa_message', 'frontmall_whatsapp', __( 'Intro message to admin. Placeholders: {product}, {price}, {url}', 'frontmall' ), frontmall_wa_default_message(), 'textarea' );
	}

	private function wa_float( \WP_Customize_Manager $wp ): void {
		$wp->add_section( 'frontmall_wa_float', array(
			'title'       => __( 'Frontmall: Floating WhatsApp Button', 'frontmall' ),
			'description' => __( 'A floating WhatsApp button on every page. It uses the same number as the Order button (edit the number under "Frontmall: WhatsApp Order Button").', 'frontmall' ),
			'priority'    => 33,
		) );

		$wp->add_setting( 'frontmall_wa_float_enable', array( 'default' => true, 'sanitize_callback' => 'wp_validate_boolean' ) );
		$wp->add_control( 'frontmall_wa_float_enable', array(
			'label'   => __( 'Show the floating WhatsApp button', 'frontmall' ),
			'section' => 'frontmall_wa_float',
			'type'    => 'checkbox',
		) );

		$this->text( $wp, 'frontmall_wa_float_name', 'frontmall_wa_float', __( 'Display name', 'frontmall' ), 'Frontmall Kenya' );
		$this->text( $wp, 'frontmall_wa_float_caption', 'frontmall_wa_float', __( 'Caption under the name', 'frontmall' ), __( 'Typically replies in minutes', 'frontmall' ) );
		$this->text( $wp, 'frontmall_wa_float_message', 'frontmall_wa_float', __( 'Pre-filled chat message', 'frontmall' ), __( 'Hello Frontmall, I would like to make an enquiry.', 'frontmall' ), 'textarea' );
	}
}
