<?php
/**
 * Storefront UX polish: an order "what happens next" panel on the thank-you
 * page, quick links on the My Account dashboard, and a rich empty-cart state.
 * Pure hooks - no query or checkout behaviour is altered.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Storefront {

	private static ?Storefront $instance = null;

	public static function instance(): Storefront {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'woocommerce_thankyou', array( $this, 'what_happens_next' ), 4 );
		add_action( 'woocommerce_account_dashboard', array( $this, 'account_quicklinks' ), 5 );
		add_action( 'woocommerce_cart_is_empty', array( $this, 'empty_cart_cta' ), 20 );
	}

	public function what_happens_next( $order_id ): void {
		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order ) {
			return;
		}
		$b     = frontmall_business();
		$phone = preg_replace( '/\D+/', '', (string) $b['phone'] );
		$msg   = sprintf( __( 'Hello, I just placed order #%s on your website.', 'frontmall' ), $order->get_order_number() );
		$wa    = $phone ? 'https://wa.me/' . $phone . '?text=' . rawurlencode( $msg ) : frontmall_whatsapp_url();

		$steps = array(
			array( __( 'Order received', 'frontmall' ), __( 'We have your order and payment details.', 'frontmall' ) ),
			array( __( 'Confirmation call', 'frontmall' ), __( 'Our team confirms items, delivery address and payment.', 'frontmall' ) ),
			array( __( 'Dispatch', 'frontmall' ), __( 'Same or next-day dispatch within Nairobi; countrywide via courier.', 'frontmall' ) ),
			array( __( 'Delivery or pickup', 'frontmall' ), __( 'Receive your order or collect it at our CBD shop.', 'frontmall' ) ),
		);
		?>
		<section class="fm-next" aria-label="<?php esc_attr_e( 'What happens next', 'frontmall' ); ?>">
			<h2 class="fm-next__title"><?php esc_html_e( 'What happens next', 'frontmall' ); ?></h2>
			<ol class="fm-next__steps">
				<?php
				$i = 1;
				foreach ( $steps as $s ) :
					?>
					<li class="fm-next__step">
						<span class="fm-next__num"><?php echo esc_html( (string) $i ); ?></span>
						<span class="fm-next__body">
							<span class="fm-next__label"><?php echo esc_html( $s[0] ); ?></span>
							<span class="fm-next__text"><?php echo esc_html( $s[1] ); ?></span>
						</span>
					</li>
					<?php
					$i++;
				endforeach;
				?>
			</ol>
			<p class="fm-next__help">
				<?php esc_html_e( 'Questions about your order?', 'frontmall' ); ?>
				<a class="fm-next__wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Chat with us on WhatsApp', 'frontmall' ); ?></a>
			</p>
		</section>
		<?php
	}

	public function account_quicklinks(): void {
		$links = array(
			array( wc_get_account_endpoint_url( 'orders' ), __( 'My Orders', 'frontmall' ), '&#128230;' ),
			array( wc_get_account_endpoint_url( 'edit-address' ), __( 'Addresses', 'frontmall' ), '&#127968;' ),
			array( home_url( '/wishlist/' ), __( 'Wishlist', 'frontmall' ), '&#9825;' ),
			array( home_url( '/track-order/' ), __( 'Track Order', 'frontmall' ), '&#128666;' ),
		);
		echo '<div class="fm-account-quick">';
		foreach ( $links as $l ) {
			printf(
				'<a class="fm-account-quick__item" href="%1$s"><span class="fm-account-quick__ic" aria-hidden="true">%2$s</span><span>%3$s</span></a>',
				esc_url( $l[0] ),
				wp_kses_post( $l[2] ),
				esc_html( $l[1] )
			);
		}
		echo '</div>';
	}

	public function empty_cart_cta(): void {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
		$cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 6,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			)
		);
		echo '<div class="fm-empty-cart">';
		echo '<p class="fm-empty-cart__lead">' . esc_html__( 'Popular categories to get you started:', 'frontmall' ) . '</p>';
		if ( ! is_wp_error( $cats ) && $cats ) {
			echo '<div class="fm-empty-cart__cats">';
			foreach ( $cats as $c ) {
				printf( '<a class="fm-chip" href="%s">%s</a>', esc_url( get_term_link( $c ) ), esc_html( $c->name ) );
			}
			echo '</div>';
		}
		printf( '<a class="fm-btn" href="%s">%s</a>', esc_url( $shop ), esc_html__( 'Browse all products', 'frontmall' ) );
		echo '</div>';
	}
}
