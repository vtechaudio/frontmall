<?php
/**
 * Auto-create and maintain the legal / policy / support pages required by
 * Google Merchant Center, with detailed, verifiable, editable content.
 *
 * Behaviour:
 *  - On theme activation, missing pages are created.
 *  - Theme-generated pages that the admin has NOT edited are refreshed to the
 *    latest content (edits are detected via a stored content hash and respected).
 *  - A one-click "Generate / refresh content pages" button (Frontmall Setup)
 *    force-updates every defined page to the latest content.
 *
 * @package Frontmall
 */

namespace Frontmall;

defined( 'ABSPATH' ) || exit;

final class Pages {

	private static ?Pages $instance = null;
	private const VERSION_OPT     = 'frontmall_pages_version';
	private const CONTENT_VERSION = '6';

	public static function instance(): Pages {
		return self::$instance ??= new self();
	}

	private function __construct() {
		add_action( 'after_switch_theme', array( $this, 'sync' ) );
		add_action( 'after_switch_theme', array( $this, 'configure_homepage' ) );
		add_action( 'admin_init', array( $this, 'maybe_configure_homepage' ) );
		add_action( 'admin_post_frontmall_regen_pages', array( $this, 'handle_regen' ) );
	}

	/**
	 * Make the front page a static "Home" page so the designed homepage shows
	 * without manual Settings > Reading changes. Respects an existing static
	 * front page if the user has already set one.
	 */
	public function configure_homepage(): void {
		if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) > 0 ) {
			$current = get_post( (int) get_option( 'page_on_front' ) );
			if ( $current instanceof \WP_Post && 'publish' === $current->post_status ) {
				return;
			}
		}

		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'title'          => 'Home',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( empty( $existing ) ) {
			$home_id = (int) wp_insert_post(
				array(
					'post_title'   => 'Home',
					'post_name'    => 'home',
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => '',
					'menu_order'   => -1,
				)
			);
		} else {
			$home_id = (int) $existing[0];
		}

		if ( $home_id > 0 ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home_id );
			update_option( 'frontmall_home_id', $home_id );
		}
	}

	/**
	 * One-time homepage setup for installs upgrading the theme (activation
	 * alone does not re-fire when replacing the active theme).
	 */
	public function maybe_configure_homepage(): void {
		if ( '1' === (string) get_option( 'frontmall_home_ready', '' ) ) {
			return;
		}
		$this->configure_homepage();
		update_option( 'frontmall_home_ready', '1' );
	}

	/** Create missing pages; refresh unedited generated pages. */
	public function sync(): void {
		$ids = get_option( 'frontmall_page_ids', array() );
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		foreach ( $this->definitions() as $key => $page ) {
			$existing = $this->find_page( $key, $page['title'], $ids );
			if ( ! $existing ) {
				$id = $this->insert_page( $page );
				if ( $id ) {
					$ids[ $key ] = $id;
				}
			} else {
				$ids[ $key ] = $existing->ID;
				$gen  = get_post_meta( $existing->ID, '_frontmall_generated', true );
				$hash = get_post_meta( $existing->ID, '_frontmall_hash', true );
				if ( $gen && $hash && $hash === md5( $existing->post_content ) ) {
					$this->update_page( $existing->ID, $page );
				}
			}
		}
		$this->finalize( $ids );
	}

	/** Force create/overwrite every defined page. */
	public function force_regenerate(): int {
		$ids = get_option( 'frontmall_page_ids', array() );
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		$count = 0;
		foreach ( $this->definitions() as $key => $page ) {
			$existing = $this->find_page( $key, $page['title'], $ids );
			if ( $existing ) {
				$this->update_page( $existing->ID, $page );
				$ids[ $key ] = $existing->ID;
			} else {
				$id = $this->insert_page( $page );
				if ( $id ) {
					$ids[ $key ] = $id;
				}
			}
			$count++;
		}
		$this->finalize( $ids );
		return $count;
	}

	public function handle_regen(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'frontmall' ) );
		}
		check_admin_referer( 'frontmall_regen' );
		$n = $this->force_regenerate();
		wp_safe_redirect( add_query_arg( array( 'frontmall_done' => 'pages', 'fn' => $n ), admin_url( 'themes.php?page=frontmall-setup' ) ) );
		exit;
	}

	private function find_page( string $key, string $title, array $ids ): ?\WP_Post {
		if ( ! empty( $ids[ $key ] ) ) {
			$p = get_post( (int) $ids[ $key ] );
			if ( $p instanceof \WP_Post && 'page' === $p->post_type && 'trash' !== $p->post_status ) {
				return $p;
			}
		}
		$p = get_page_by_path( sanitize_title( $title ) );
		return ( $p instanceof \WP_Post ) ? $p : null;
	}

	private function insert_page( array $page ): int {
		$id = wp_insert_post(
			array(
				'post_title'     => $page['title'],
				'post_content'   => $page['content'],
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_author'    => 1,
				'comment_status' => 'closed',
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		$this->stamp( (int) $id );
		return (int) $id;
	}

	private function update_page( int $id, array $page ): void {
		wp_update_post(
			array(
				'ID'           => $id,
				'post_title'   => $page['title'],
				'post_content' => $page['content'],
				'post_status'  => 'publish',
			)
		);
		$this->stamp( $id );
	}

	private function stamp( int $id ): void {
		$saved = get_post( $id );
		update_post_meta( $id, '_frontmall_generated', self::CONTENT_VERSION );
		update_post_meta( $id, '_frontmall_hash', $saved ? md5( $saved->post_content ) : '' );
	}

	private function finalize( array $ids ): void {
		if ( ! empty( $ids['privacy'] ) ) {
			update_option( 'wp_page_for_privacy_policy', (int) $ids['privacy'] );
		}
		update_option( 'frontmall_page_ids', $ids );
		update_option( self::VERSION_OPT, self::CONTENT_VERSION );
	}

	private function definitions(): array {
		$b       = frontmall_business();
		$name    = $b['name'];
		$phone   = $b['phone'];
		$telraw  = preg_replace( '/\s+/', '', $phone );
		$email   = $b['email'];
		$wa      = frontmall_whatsapp_url();
		$addr1   = $b['street'];
		$addr2   = $b['city'] . ', ' . $b['region'] . ', Kenya';
		$hours   = 'Monday to Saturday, 8:00 AM to 6:00 PM (East Africa Time). Closed on Sundays and public holidays.';
		$updated = date_i18n( 'F j, Y' );

		$contact = "<h2>Contact Information</h2><p><strong>{$name}</strong><br>{$addr1}<br>{$addr2}<br>"
			. "Phone / WhatsApp: <a href='tel:{$telraw}'>{$phone}</a><br>"
			. "Email: <a href='mailto:{$email}'>{$email}</a><br>"
			. "Business hours: {$hours}</p>";

		return array(

			'about' => array(
				'title'   => 'About Us',
				'content' =>
					"<h2>Who We Are</h2>"
					. "<p>{$name} (trading as {$b['short']}) is a Kenyan-owned and Kenyan-operated online retailer supplying genuine solar power systems, inverters, generators, power and hand tools, electronics and home appliances to homes, contractors and businesses across Kenya and the wider East African region. We operate a physical premises at {$addr1}, {$addr2}, and we fulfil and ship orders nationwide.</p>"
					. "<h2>What We Sell</h2>"
					. "<p>Our catalogue spans over one thousand carefully selected products across departments including Solar Panels, Inverters, Solar Controllers, Solar Street and Wall Lights, Generators, Power and Hand Tools, Home Appliances, Home Theatres, Consumer Electronics, Water Pumps, Water Dispensers, Phones and Accessories, and more. We stock recognised brands such as Makita, DeWalt, Total, Ingco, Solarmax, Jadever, Hisense and many others, alongside quality value options.</p>"
					. "<h2>Our Mission</h2>"
					. "<p>Our mission is simple: make genuine, fairly priced products easy to buy and quick to receive, backed by real human support. We believe every customer, whether furnishing a home, powering a farm or equipping a worksite, deserves authentic goods, transparent pricing and dependable after-sales care.</p>"
					. "<h2>Why Customers Trust {$b['short']}</h2>"
					. "<ul>"
					. "<li><strong>Genuine products</strong> sourced from reputable manufacturers and authorised distributors, backed by manufacturer warranties where applicable.</li>"
					. "<li><strong>Transparent pricing</strong> displayed in Kenyan Shillings (KES), inclusive of applicable taxes, with delivery costs shown clearly before you pay.</li>"
					. "<li><strong>Fast, countrywide delivery</strong> and in-person pickup at our Nairobi CBD premises.</li>"
					. "<li><strong>Responsive support</strong> by phone, WhatsApp and email during business hours.</li>"
					. "<li><strong>Secure shopping</strong> over encrypted HTTPS with trusted payment providers including M-Pesa and card gateways.</li>"
					. "</ul>"
					. "<h2>Our Physical Presence</h2>"
					. "<p>Unlike marketplace resellers, we maintain a verifiable physical location where customers can visit, inspect selected products and collect orders. You will find us at {$addr1}, {$addr2}.</p>"
					. $contact,
			),

			'contact' => array(
				'title'   => 'Contact Us',
				'content' =>
					"<h2>We Are Here to Help</h2>"
					. "<p>Our customer support team is available during business hours to assist with product enquiries, orders, delivery, payments, returns and after-sales service. We aim to respond to every message within one business day, and usually much sooner.</p>"
					. $contact
					. "<h3>Chat on WhatsApp</h3>"
					. "<p>For the quickest response, message us on WhatsApp: <a href='{$wa}' rel='noopener'>{$phone}</a>.</p>"
					. "<h3>Visit Our Premises</h3>"
					. "<p>{$addr1}, {$addr2}. Please contact us in advance if you plan to collect a specific item so we can have it ready.</p>"
					. "<h3>What to Contact Us About</h3>"
					. "<ul><li>Product availability, specifications and pricing</li><li>Order status, delivery and tracking</li><li>Payment and invoicing</li><li>Returns, refunds and warranty claims</li><li>Bulk, corporate and contractor enquiries</li></ul>"
					. "<h3>Send Us a Message</h3>"
					. "<p>Use the contact form on this page or email <a href='mailto:{$email}'>{$email}</a> and a member of our team will get back to you promptly.</p>",
			),

			'track' => array(
				'title'   => 'Track Order',
				'content' =>
					"<h2>Track Your Order</h2>"
					. "<p>You can follow your order from checkout to delivery. Here is how:</p>"
					. "<h3>1. Through Your Account</h3>"
					. "<p>Log in to <a href='/my-account/'>My Account</a> and open <strong>Orders</strong> to see the current status of every order you have placed, along with items, totals and delivery details.</p>"
					. "<h3>2. Order Status Updates</h3>"
					. "<p>After you place an order we send updates by SMS, email and, where provided, WhatsApp at key stages:</p>"
					. "<ul>"
					. "<li><strong>Processing</strong> - payment confirmed and your order is being prepared.</li>"
					. "<li><strong>Dispatched</strong> - your order has left our premises with the courier.</li>"
					. "<li><strong>Out for delivery</strong> - the courier is delivering to your address.</li>"
					. "<li><strong>Delivered / Ready for pickup</strong> - your order has arrived or is ready for collection.</li>"
					. "</ul>"
					. "<h3>3. Need Help Locating an Order?</h3>"
					. "<p>Have your order number ready and contact us: phone or WhatsApp <a href='tel:{$telraw}'>{$phone}</a>, or email <a href='mailto:{$email}'>{$email}</a>. We will confirm your order status and expected delivery time.</p>"
					. $contact,
			),

			'faq' => array(
				'title'   => 'Frequently Asked Questions',
				'content' =>
					"<h2>Ordering</h2>"
					. "<p><strong>How do I place an order?</strong><br>Browse or search for a product, add it to your cart, and proceed to checkout. You can check out as a guest or create an account. You will receive an order confirmation once your order is placed.</p>"
					. "<p><strong>Can I change or cancel my order?</strong><br>Yes, if it has not yet been dispatched. Contact us as soon as possible with your order number and we will help.</p>"
					. "<h2>Delivery</h2>"
					. "<p><strong>Where do you deliver?</strong><br>We deliver to all 47 counties in Kenya. Delivery time and cost are calculated and shown at checkout based on your location.</p>"
					. "<p><strong>How long does delivery take?</strong><br>Within Nairobi, same-day or next-day delivery is usually available. Other regions typically receive orders within 1 to 5 business days. See our Shipping & Delivery Policy for details.</p>"
					. "<p><strong>Can I collect my order myself?</strong><br>Yes. Pickup is available at our Nairobi CBD premises during business hours.</p>"
					. "<h2>Payment</h2>"
					. "<p><strong>What payment methods do you accept?</strong><br>M-Pesa, Visa and Mastercard, bank transfer, and pay on delivery on eligible orders. See our Payment Methods page.</p>"
					. "<p><strong>Are my payments secure?</strong><br>Yes. All transactions are encrypted and processed by trusted, PCI-compliant providers. We do not store your full card details.</p>"
					. "<p><strong>In what currency are prices shown?</strong><br>All prices are in Kenyan Shillings (KES), inclusive of applicable taxes unless stated otherwise.</p>"
					. "<h2>Returns & Warranty</h2>"
					. "<p><strong>Can I return an item?</strong><br>Yes, within 7 days of delivery if it is unused and in its original packaging. See our Return & Refund Policy.</p>"
					. "<p><strong>What if my item is faulty or damaged?</strong><br>Contact us within 48 hours of delivery and we will arrange a replacement, repair or refund at no extra cost.</p>"
					. "<p><strong>Do products have a warranty?</strong><br>Most products carry a manufacturer warranty. The period is stated on the product page and covered by our Warranty Policy.</p>"
					. "<h2>Account & Support</h2>"
					. "<p><strong>Do I need an account to shop?</strong><br>No, guest checkout is available, but an account lets you track orders and reorder faster.</p>"
					. "<p><strong>How do I reach you?</strong><br>Call or WhatsApp <a href='tel:{$telraw}'>{$phone}</a>, or email <a href='mailto:{$email}'>{$email}</a> during business hours.</p>",
			),

			'payments' => array(
				'title'   => 'Payment Methods',
				'content' =>
					"<h2>How You Can Pay</h2>"
					. "<p>{$name} offers secure, convenient payment options for shoppers in Kenya. Choose whichever suits you best at checkout.</p>"
					. "<h3>M-Pesa</h3>"
					. "<p>Pay instantly using Lipa na M-Pesa. At checkout, select M-Pesa, enter your Safaricom number, and approve the STK push prompt on your phone, or use the Paybill / Till details shown. Your order is confirmed as soon as payment is received.</p>"
					. "<h3>Debit and Credit Cards</h3>"
					. "<p>We accept Visa and Mastercard through a secure, PCI-compliant payment gateway. Card details are entered on an encrypted page and are never stored on our servers.</p>"
					. "<h3>Bank Transfer</h3>"
					. "<p>For larger or corporate orders, bank transfer is available. Our banking details are provided at checkout or on request, and your order is processed once funds are confirmed.</p>"
					. "<h3>Pay on Delivery</h3>"
					. "<p>Available on eligible orders within selected areas. You pay by cash or M-Pesa when your order arrives. Eligibility is shown at checkout.</p>"
					. "<h2>Pricing and Currency</h2>"
					. "<p>All prices are displayed in Kenyan Shillings (KES) and include applicable taxes unless stated otherwise. The price shown is the price you pay for the product; delivery is calculated separately and displayed clearly before you confirm your order. We do not add hidden charges.</p>"
					. "<h2>Secure Transactions</h2>"
					. "<p>Our entire site runs over encrypted HTTPS. Payments are handled by established providers, and sensitive payment data is processed securely by those providers, not stored by us.</p>"
					. "<h2>Invoices and Receipts</h2>"
					. "<p>You receive an order confirmation for every purchase. A receipt or tax invoice is available on request; contact us with your order number.</p>"
					. $contact,
			),

			'privacy' => array(
				'title'   => 'Privacy Policy',
				'content' =>
					"<p><em>Last updated: {$updated}</em></p>"
					. "<h2>1. Introduction</h2>"
					. "<p>{$name} (\"we\", \"us\", \"our\") is committed to protecting your privacy and handling your personal data responsibly. This Privacy Policy explains what personal data we collect when you use {$name} at this website or visit our premises, how we use it, who we share it with, and the rights available to you. We process personal data in accordance with the Data Protection Act, 2019 of Kenya and applicable regulations.</p>"
					. "<h2>2. Who We Are (Data Controller)</h2>"
					. "<p>The data controller responsible for your personal data is {$name}, {$addr1}, {$addr2}. You can contact us regarding privacy matters at <a href='mailto:{$email}'>{$email}</a> or <a href='tel:{$telraw}'>{$phone}</a>.</p>"
					. "<h2>3. Information We Collect</h2>"
					. "<ul>"
					. "<li><strong>Identity and contact data</strong>: name, delivery and billing address, email address, and phone number.</li>"
					. "<li><strong>Order and transaction data</strong>: products purchased, order value, delivery details, and payment confirmation (we do not store full card numbers).</li>"
					. "<li><strong>Account data</strong>: username and password if you create an account.</li>"
					. "<li><strong>Communications</strong>: messages you send us by email, WhatsApp, phone or the contact form.</li>"
					. "<li><strong>Technical and usage data</strong>: IP address, browser type, device information, and pages visited, collected through cookies and similar technologies.</li>"
					. "</ul>"
					. "<h2>4. How and Why We Use Your Data</h2>"
					. "<ul>"
					. "<li>To process, fulfil and deliver your orders and to take payment.</li>"
					. "<li>To provide customer support, handle returns, refunds and warranty claims.</li>"
					. "<li>To send transactional messages such as order confirmations and delivery updates.</li>"
					. "<li>To send marketing communications where you have consented, which you can opt out of at any time.</li>"
					. "<li>To operate, secure and improve our website and prevent fraud.</li>"
					. "<li>To comply with legal and regulatory obligations.</li>"
					. "</ul>"
					. "<h2>5. Legal Bases</h2>"
					. "<p>We rely on the performance of our contract with you (to fulfil orders), your consent (for optional marketing and non-essential cookies), our legitimate interests (to secure and improve our services and prevent fraud), and compliance with legal obligations.</p>"
					. "<h2>6. Payment Data</h2>"
					. "<p>Payments are processed by trusted third-party providers such as Safaricom M-Pesa and licensed card gateways. We do not collect or store your full card details on our servers.</p>"
					. "<h2>7. Sharing Your Data</h2>"
					. "<p>We share personal data only as needed to run our business: with delivery and courier partners to fulfil your order, with payment processors to take payment, with service providers who host and support our website under confidentiality obligations, and with authorities where required by law. We do not sell your personal data.</p>"
					. "<h2>8. Cookies</h2>"
					. "<p>We use cookies for essential functions (cart and checkout), analytics, and, with your consent, marketing. See our Cookie Policy for details and how to manage them.</p>"
					. "<h2>9. Data Retention</h2>"
					. "<p>We keep personal data only for as long as necessary to fulfil the purposes described here, including legal, accounting and reporting requirements. Order records are typically retained for the period required by Kenyan tax and commercial law.</p>"
					. "<h2>10. Data Security</h2>"
					. "<p>We apply appropriate technical and organisational measures, including HTTPS encryption and access controls, to protect your data against unauthorised access, loss or misuse.</p>"
					. "<h2>11. Your Rights</h2>"
					. "<p>Under the Data Protection Act, 2019 you have the right to be informed, to access your data, to request correction or deletion, to object to or restrict certain processing, and to data portability. To exercise any right, contact us at <a href='mailto:{$email}'>{$email}</a>. You also have the right to lodge a complaint with the Office of the Data Protection Commissioner (ODPC) of Kenya.</p>"
					. "<h2>12. Children</h2>"
					. "<p>Our services are intended for adults. We do not knowingly collect data from children without appropriate consent.</p>"
					. "<h2>13. Changes to This Policy</h2>"
					. "<p>We may update this policy from time to time. The latest version will always be published on this page with its effective date.</p>"
					. $contact,
			),

			'terms' => array(
				'title'   => 'Terms & Conditions',
				'content' =>
					"<p><em>Last updated: {$updated}</em></p>"
					. "<h2>1. About These Terms</h2>"
					. "<p>These Terms & Conditions govern your use of {$name} and any purchase you make from us. By accessing our website or placing an order you agree to these terms. If you do not agree, please do not use our services. Our registered trading address is {$addr1}, {$addr2}.</p>"
					. "<h2>2. Eligibility</h2>"
					. "<p>You must be at least 18 years old and able to enter into a binding contract to purchase from us.</p>"
					. "<h2>3. Products and Descriptions</h2>"
					. "<p>We take care to describe and picture products accurately, including specifications, availability and price. Product images are for illustration and may vary slightly from the item supplied. Where a material error is found, we reserve the right to correct it and, if necessary, cancel the affected order with a full refund.</p>"
					. "<h2>4. Pricing</h2>"
					. "<p>All prices are shown in Kenyan Shillings (KES) and include applicable taxes unless stated otherwise. Delivery charges are shown separately at checkout before you pay. We reserve the right to change prices at any time, but changes do not affect orders already confirmed.</p>"
					. "<h2>5. Orders and Acceptance</h2>"
					. "<p>Your order is an offer to buy. A contract is formed when we confirm your order and, where applicable, receive payment. We may decline or cancel an order suspected of fraud, pricing error or stock unavailability, and will refund any amount paid.</p>"
					. "<h2>6. Payment</h2>"
					. "<p>We accept M-Pesa, card, bank transfer and pay on delivery on eligible orders, as described on our Payment Methods page. Full payment (or accepted pay-on-delivery arrangement) is required before dispatch.</p>"
					. "<h2>7. Delivery</h2>"
					. "<p>Delivery timelines and charges are set out in our Shipping & Delivery Policy. Delivery estimates are made in good faith but are not guaranteed and may be affected by factors outside our control.</p>"
					. "<h2>8. Returns, Refunds and Warranty</h2>"
					. "<p>Returns and refunds are handled under our Return & Refund Policy, and product warranties under our Warranty Policy. Nothing in these terms limits your statutory rights under Kenyan consumer law.</p>"
					. "<h2>9. Acceptable Use</h2>"
					. "<p>You agree not to misuse the website, attempt unauthorised access, or use it for unlawful purposes.</p>"
					. "<h2>10. Intellectual Property</h2>"
					. "<p>All content on this website, including text, logos, images and design, is owned by or licensed to {$name} and may not be reproduced without permission.</p>"
					. "<h2>11. Limitation of Liability</h2>"
					. "<p>To the extent permitted by law, our total liability for any claim arising from a purchase is limited to the price paid for the product concerned. We are not liable for indirect or consequential loss.</p>"
					. "<h2>12. Force Majeure</h2>"
					. "<p>We are not responsible for delays or failure to perform caused by events beyond our reasonable control.</p>"
					. "<h2>13. Governing Law and Disputes</h2>"
					. "<p>These terms are governed by the laws of the Republic of Kenya, and any dispute is subject to the jurisdiction of the Kenyan courts. We encourage you to contact us first so we can resolve any issue amicably.</p>"
					. "<h2>14. Changes</h2>"
					. "<p>We may update these terms from time to time. The current version is always published on this page.</p>"
					. $contact,
			),

			'returns' => array(
				'title'   => 'Return & Refund Policy',
				'content' =>
					"<h2>Our Commitment</h2>"
					. "<p>Your satisfaction matters to us. If something is not right with your order, we will work with you to put it right, in line with this policy and your rights under Kenyan consumer law.</p>"
					. "<h2>Return Window</h2>"
					. "<p>You may request a return within <strong>7 days</strong> of receiving your order, provided the item is unused, in its original condition and packaging, with all accessories, manuals and free gifts included, and accompanied by proof of purchase.</p>"
					. "<h2>Items That Cannot Be Returned</h2>"
					. "<p>For hygiene, safety or practical reasons, some items cannot be returned unless faulty, including consumables, cut cable, used batteries, installed or wired products, and custom or special-order items. Any exceptions are noted on the product page.</p>"
					. "<h2>Faulty, Damaged or Incorrect Items</h2>"
					. "<p>If your item arrives damaged, defective, or is not what you ordered, contact us within <strong>48 hours</strong> of delivery at <a href='mailto:{$email}'>{$email}</a> or <a href='tel:{$telraw}'>{$phone}</a>. We will arrange a replacement, repair or full refund, including any return delivery cost, at no charge to you.</p>"
					. "<h2>How to Return an Item</h2>"
					. "<ol>"
					. "<li>Contact our support team to log the return and receive instructions.</li>"
					. "<li>Pack the item securely in its original packaging with all accessories.</li>"
					. "<li>Return it to {$addr1}, {$addr2}, or hand it to our appointed courier.</li>"
					. "</ol>"
					. "<h2>Refunds</h2>"
					. "<p>Once we receive and inspect your return, we will notify you of approval. Approved refunds are issued to your original payment method (M-Pesa, card or bank) within <strong>3 to 7 business days</strong>. Payment providers may take additional time to reflect the funds.</p>"
					. "<h2>Exchanges</h2>"
					. "<p>Prefer a different model or size? Let us know and, subject to availability, we will arrange an exchange.</p>"
					. "<h2>Return Delivery Costs</h2>"
					. "<p>If the return is due to our error or a faulty product, we cover the return delivery cost. For change-of-mind returns, the return delivery cost is the customer's responsibility.</p>"
					. $contact,
			),

			'shipping' => array(
				'title'   => 'Shipping & Delivery Policy',
				'content' =>
					"<h2>Delivery Coverage</h2>"
					. "<p>{$name} delivers to all 47 counties in Kenya. We dispatch from our premises in Nairobi and work with trusted couriers to reach you wherever you are.</p>"
					. "<h2>Handling Time</h2>"
					. "<p>Orders are processed and dispatched within <strong>1 business day</strong> (Monday to Saturday). Orders placed before <strong>2:00 PM</strong> on a business day are usually dispatched the same day; orders placed after the cut-off, on Sundays or on public holidays are dispatched on the next business day.</p>"
					. "<h2>Transit Time</h2>"
					. "<p>Transit time is counted in business days from dispatch:</p>"
					. "<ul>"
					. "<li><strong>Nairobi and environs</strong>: same-day delivery, typically within 1 to 3 hours of dispatch.</li>"
					. "<li><strong>Major towns</strong>: 1 to 2 business days.</li>"
					. "<li><strong>Remote and upcountry areas</strong>: 2 to 5 business days.</li>"
					. "</ul>"
					. "<p>Your total delivery time is the handling time plus the transit time. Times may vary with courier schedules, product availability, weather and location.</p>"
					. "<h2>Delivery Charges</h2>"
					. "<ul>"
					. "<li><strong>Nairobi CBD pickup</strong>: free - collect in person at our shop.</li>"
					. "<li><strong>Nairobi delivery</strong>: a flat fee of KSh 300.</li>"
					. "<li><strong>Outside Nairobi (lightweight items)</strong>: a flat rate of KSh 500, countrywide.</li>"
					. "<li><strong>Outside Nairobi (bulky or heavy items)</strong>: charged by destination and size, and confirmed with you before dispatch.</li>"
					. "</ul>"
					. "<p>All delivery charges are shown at checkout before payment. There are no hidden fees, handling surcharges or card charges added at the final step - the total you confirm is the total you pay.</p>"
					. "<h2>Pickup</h2>"
					. "<p>You may collect your order in person, free of charge, at {$addr1}, {$addr2}, during business hours ({$hours}). Please wait for a confirmation that your order is ready before collecting.</p>"
					. "<h2>Tracking and Updates</h2>"
					. "<p>We keep you informed by SMS, email and, where provided, WhatsApp from dispatch to delivery. See our Track Order page for details.</p>"
					. "<h2>Damaged in Transit</h2>"
					. "<p>Please inspect your order on delivery. If anything is damaged in transit, report it to us within 48 hours and we will make it right.</p>"
					. "<h2>Failed or Delayed Deliveries</h2>"
					. "<p>If a delivery fails because of an incorrect address or because no one is available to receive it, we will contact you to reschedule. Repeated failed deliveries may attract an additional delivery fee.</p>"
					. $contact,
			),

			'warranty' => array(
				'title'   => 'Warranty Policy',
				'content' =>
					"<h2>Manufacturer Warranty</h2>"
					. "<p>Products sold by {$name} carry the manufacturer's warranty where applicable. The warranty period and specific terms vary by product and brand, and are stated on the product page and on any warranty card supplied with the item. Typical periods range from 3 months to 24 months depending on the product.</p>"
					. "<h2>What the Warranty Covers</h2>"
					. "<p>Warranty covers genuine manufacturing defects and component failures under normal, intended use during the warranty period.</p>"
					. "<h2>What the Warranty Does Not Cover</h2>"
					. "<ul>"
					. "<li>Damage from misuse, mishandling, accident or negligence.</li>"
					. "<li>Unauthorised repair, modification or tampering.</li>"
					. "<li>Damage from power surges where a recommended protective device was not used.</li>"
					. "<li>Normal wear and tear, and consumable parts.</li>"
					. "<li>Damage from incorrect installation not carried out or approved by an authorised technician.</li>"
					. "</ul>"
					. "<h2>How to Make a Warranty Claim</h2>"
					. "<ol>"
					. "<li>Contact us at <a href='mailto:{$email}'>{$email}</a> or <a href='tel:{$telraw}'>{$phone}</a> with your order number and a description of the fault.</li>"
					. "<li>Provide proof of purchase and, where possible, photos or a video of the issue.</li>"
					. "<li>We will guide you on returning the item for assessment, repair or replacement in line with the manufacturer's terms.</li>"
					. "</ol>"
					. "<h2>Assessment and Turnaround</h2>"
					. "<p>Warranty assessments are typically completed within 7 to 14 business days, subject to the manufacturer's or distributor's response times. Where a repair or replacement is approved, we will arrange it at no cost to you under the warranty terms.</p>"
					. "<h2>Proof of Purchase</h2>"
					. "<p>Please keep your order confirmation or receipt. A valid proof of purchase is required for all warranty claims. Warranty is non-transferable unless stated otherwise by the manufacturer.</p>"
					. $contact,
			),

			'cookies' => array(
				'title'   => 'Cookie Policy',
				'content' =>
					"<p><em>Last updated: {$updated}</em></p>"
					. "<h2>What Are Cookies?</h2>"
					. "<p>Cookies are small text files placed on your device when you visit a website. They help the site function, remember your preferences, and understand how the site is used.</p>"
					. "<h2>How We Use Cookies</h2>"
					. "<ul>"
					. "<li><strong>Essential cookies</strong>: required for core features such as your shopping cart, checkout and secure login. The site cannot work properly without these.</li>"
					. "<li><strong>Performance and analytics cookies</strong>: help us understand how visitors use the site so we can improve it.</li>"
					. "<li><strong>Functional cookies</strong>: remember choices such as recently viewed products.</li>"
					. "<li><strong>Marketing cookies</strong>: used, only with your consent, to show relevant offers.</li>"
					. "</ul>"
					. "<h2>Managing Cookies</h2>"
					. "<p>You can control or delete cookies through your browser settings at any time. Please note that disabling essential cookies may affect cart and checkout functionality.</p>"
					. "<h2>Third-Party Cookies</h2>"
					. "<p>Some cookies may be set by trusted third-party services we use, such as analytics or payment providers. Their use of cookies is governed by their own privacy and cookie policies.</p>"
					. "<h2>More Information</h2>"
					. "<p>For questions about our use of cookies, or about how we handle your data, see our Privacy Policy or contact us at <a href='mailto:{$email}'>{$email}</a>.</p>",
			),
		);
	}
}
