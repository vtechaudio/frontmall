<?php
/**
 * Structured data (JSON-LD): Organization/Store, WebSite + SearchAction,
 * Product (offers, brand, rating) and BreadcrumbList. Rich, valid product
 * data supports Google Merchant Center and rich results.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Schema {

	private static ?Schema $instance = null;

	public static function instance(): Schema {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'wp_head', array( $this, 'organization' ), 5 );
		add_action( 'wp_head', array( $this, 'website' ), 5 );
		add_action( 'wp_footer', array( $this, 'product' ), 20 );
		add_action( 'wp_footer', array( $this, 'breadcrumb' ), 21 );
	}

	private function emit( array $data ): void {
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $data ) . "</script>\n";
	}

	public function organization(): void {
		$b    = frontmall_business();
		$logo = has_custom_logo() ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) : '';

		$this->emit(
			array(
				'@context'     => 'https://schema.org',
				'@type'        => array( 'Organization', 'Store' ),
				'name'         => $b['name'],
				'url'          => home_url( '/' ),
				'logo'         => $logo,
				'email'        => $b['email'],
				'telephone'    => $b['phone'],
				'address'      => array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $b['street'],
					'addressLocality' => $b['city'],
					'addressRegion'   => $b['region'],
					'postalCode'      => $b['postcode'],
					'addressCountry'  => $b['country'],
				),
				'contactPoint' => array(
					'@type'             => 'ContactPoint',
					'telephone'         => $b['phone'],
					'contactType'       => 'customer support',
					'email'             => $b['email'],
					'areaServed'        => 'KE',
					'availableLanguage' => array( 'en', 'sw' ),
				),
			)
		);
	}

	public function website(): void {
		$this->emit(
			array(
				'@context'        => 'https://schema.org',
				'@type'           => 'WebSite',
				'name'            => get_bloginfo( 'name' ),
				'url'             => home_url( '/' ),
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => home_url( '/?s={search_term_string}&post_type=product' ),
					),
					'query-input' => 'required name=search_term_string',
				),
			)
		);
	}

	public function product(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			$product = wc_get_product( get_the_ID() );
		}
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$b    = frontmall_business();
		$data = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $product->get_name(),
			'sku'         => $product->get_sku(),
			'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
			'image'       => wp_get_attachment_image_url( $product->get_image_id(), 'full' ) ?: '',
			'offers'      => array(
				'@type'         => 'Offer',
				'url'           => $product->get_permalink(),
				'priceCurrency' => get_woocommerce_currency(),
				'price'         => wc_get_price_to_display( $product ),
				'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'seller'        => array( '@type' => 'Organization', 'name' => $b['name'] ),
			),
		);

		$gtin = '';
		if ( method_exists( $product, 'get_global_unique_id' ) ) {
			$gtin = (string) $product->get_global_unique_id();
		}
		if ( ! $gtin ) {
			foreach ( array( '_gtin', '_ean', '_upc', '_barcode' ) as $mk ) {
				$val = get_post_meta( $product->get_id(), $mk, true );
				if ( $val ) {
					$gtin = (string) $val;
					break;
				}
			}
		}
		if ( $gtin ) {
			$data['gtin'] = $gtin;
		}
		$mpn = (string) get_post_meta( $product->get_id(), '_mpn', true );
		if ( ! $mpn ) {
			$mpn = (string) $product->get_sku();
		}
		if ( $mpn ) {
			$data['mpn'] = $mpn;
		}
		$data['offers']['itemCondition'] = 'https://schema.org/NewCondition';
		if ( $product->is_on_sale() && $product->get_date_on_sale_to() ) {
			$data['offers']['priceValidUntil'] = $product->get_date_on_sale_to()->date( 'Y-m-d' );
		}

		$brand = $this->product_brand( $product );
		if ( $brand ) {
			$data['brand'] = array( '@type' => 'Brand', 'name' => $brand );
		}
		if ( $product->get_rating_count() ) {
			$data['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $product->get_average_rating(),
				'reviewCount' => $product->get_review_count(),
			);
		}

		$this->emit( $data );
	}

	public function breadcrumb(): void {
		$items = array();
		$pos   = 1;
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => __( 'Home', 'frontmall' ),
			'item'     => home_url( '/' ),
		);

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_the_ID() );
			$terms   = get_the_terms( get_the_ID(), 'product_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$term    = $terms[0];
				$link    = get_term_link( $term );
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'name'     => $term->name,
					'item'     => is_wp_error( $link ) ? home_url( '/' ) : $link,
				);
			}
			if ( $product instanceof \WC_Product ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'name'     => $product->get_name(),
					'item'     => $product->get_permalink(),
				);
			}
		} elseif ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$link    = get_term_link( $term );
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'name'     => $term->name,
					'item'     => is_wp_error( $link ) ? home_url( '/' ) : $link,
				);
			}
		} elseif ( is_singular() ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => get_the_title(),
				'item'     => get_permalink(),
			);
		} else {
			return;
		}

		$this->emit(
			array(
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			)
		);
	}

	private function product_brand( \WC_Product $product ): string {
		foreach ( array( 'product_brand', 'pwb-brand', 'pa_brand' ) as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$terms = wp_get_post_terms( $product->get_id(), $tax, array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $terms ) && $terms ) {
					return (string) $terms[0];
				}
			}
		}
		return '';
	}
}
