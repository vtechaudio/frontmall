<?php
/**
 * WooCommerce loop item override -> Frontmall product card.
 * Keeps shop/category/search archives visually consistent with the homepage.
 *
 * @package Frontmall
 */

defined( 'ABSPATH' ) || exit;

global $product;
if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}
frontmall_product_card( $product );
