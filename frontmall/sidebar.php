<?php
/**
 * Intentionally empty. Frontmall is a full-width storefront and uses no widget
 * sidebar. This suppresses the default widget area on any get_sidebar() call
 * (shop, category, single product) so the old Search / Pages / Archives /
 * Categories widget block never renders. Cart, checkout and account pages do
 * not use a sidebar and are unaffected.
 *
 * @package Frontmall
 */

defined( 'ABSPATH' ) || exit;
