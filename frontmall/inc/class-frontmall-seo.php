<?php
/**
 * Lightweight social + meta output: document title tuning, meta description,
 * Open Graph and Twitter Card tags. Automatically bails when a dedicated SEO
 * plugin is active so we never emit duplicate tags. JSON-LD lives in Schema.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class SEO {

	private static ?SEO $instance = null;

	public static function instance(): SEO {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'wp_head', array( $this, 'meta' ), 2 );
		add_filter( 'document_title_separator', array( $this, 'title_separator' ), 20 );
		add_filter( 'document_title_parts', array( $this, 'title_parts' ), 20 );
	}

	private function seo_plugin_active(): bool {
		return defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' );
	}

	public function title_separator( string $sep ): string {
		if ( $this->seo_plugin_active() ) {
			return $sep;
		}
		return '|';
	}

	public function title_parts( array $parts ): array {
		if ( $this->seo_plugin_active() ) {
			return $parts;
		}
		$b = frontmall_business();
		if ( is_front_page() ) {
			$parts['title']   = $b['name'];
			$parts['tagline'] = 'Power Tools, Solar, Generators & Appliances in Kenya';
			unset( $parts['site'] );
		} elseif ( isset( $parts['site'] ) ) {
			$parts['site'] = $b['name'];
		}
		return $parts;
	}

	public function meta(): void {
		if ( $this->seo_plugin_active() ) {
			return;
		}

		$desc  = $this->description();
		$title = wp_get_document_title();
		$url   = $this->current_url();
		$image = $this->image();
		$type  = ( function_exists( 'is_product' ) && is_product() ) ? 'product' : ( is_singular() ? 'article' : 'website' );

		$out = "\n";
		if ( $desc ) {
			$out .= '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		$out .= '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
		$out .= '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
		$out .= '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $desc ) {
			$out .= '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		$out .= '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
		if ( $image ) {
			$out .= '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
			$out .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
		} else {
			$out .= '<meta name="twitter:card" content="summary">' . "\n";
		}
		$out .= '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $desc ) {
			$out .= '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function description(): string {
		$b     = frontmall_business();
		$brand = $b['name'];
		$text  = '';

		if ( is_front_page() ) {
			$text = 'Shop power tools, solar systems, generators, home appliances and electronics online at ' . $brand . '. Genuine brands, countrywide delivery and pay on delivery in Nairobi, Kenya.';
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$text = 'Browse the full ' . $b['short'] . ' catalogue: power tools, solar, generators, appliances and electronics. Genuine brands, fair prices and fast delivery across Kenya.';
		} elseif ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_the_ID() );
			if ( $product ) {
				$text = $product->get_short_description() ?: $product->get_description();
			}
		} elseif ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$text = term_description( $term );
				if ( $this->is_meaningful( $text ) === false ) {
					$text = $this->category_description( $term, $b );
				}
			}
		} elseif ( is_singular() ) {
			$text = get_the_excerpt();
			if ( $this->is_meaningful( $text ) === false ) {
				$text = $this->page_fallback( (int) get_the_ID(), $b );
			}
		}

		if ( $this->is_meaningful( $text ) === false ) {
			$text = get_bloginfo( 'description' );
		}
		if ( $this->is_meaningful( $text ) === false ) {
			$text = 'Shop power tools, solar, generators, appliances and electronics online at ' . $brand . '. Genuine brands and countrywide delivery in Kenya.';
		}

		$text = wp_strip_all_tags( (string) $text );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		if ( mb_strlen( $text ) > 160 ) {
			$text = mb_substr( $text, 0, 157 ) . '...';
		}
		return $text;
	}

	private function is_meaningful( $text ): bool {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		return mb_strlen( $text ) >= 50;
	}

	private function category_description( \WP_Term $term, array $b ): string {
		$name  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term->name ) : strtolower( $term->name );
		$count = (int) $term->count;
		$qty   = $count > 0 ? ( $count . '+ ' ) : '';
		return sprintf(
			'Shop %s online at %s. %sgenuine-brand products with fast, countrywide delivery and pay on delivery in Nairobi, Kenya.',
			$name,
			$b['name'],
			$qty
		);
	}

	private function page_fallback( int $id, array $b ): string {
		$slug  = (string) get_post_field( 'post_name', $id );
		$title = get_the_title( $id );
		$map   = array(
			'privacy-policy'   => 'Read the ' . $b['name'] . ' privacy policy: how we collect, use, store and protect your personal information when you shop with us in Kenya.',
			'terms-conditions' => 'The ' . $b['name'] . ' terms and conditions covering orders, pricing, payment, delivery, returns and your rights when shopping with us in Kenya.',
		);
		if ( isset( $map[ $slug ] ) ) {
			return $map[ $slug ];
		}
		return sprintf(
			'%s at %s. Shop power tools, solar, generators and appliances online with countrywide delivery in Kenya.',
			$title,
			$b['name']
		);
	}

	private function image(): string {
		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_the_ID() );
			if ( $product && $product->get_image_id() ) {
				return (string) wp_get_attachment_image_url( $product->get_image_id(), 'large' );
			}
		}
		if ( is_singular() && has_post_thumbnail() ) {
			return (string) get_the_post_thumbnail_url( get_the_ID(), 'large' );
		}
		if ( has_custom_logo() ) {
			return (string) wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
		}
		return '';
	}

	private function current_url(): string {
		if ( is_singular() ) {
			$link = get_permalink();
			if ( $link ) {
				return $link;
			}
		}
		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) === false ) {
					return $link;
				}
			}
		}
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ?? '' ) );
	}
}
