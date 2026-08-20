<?php
/**
 * Free Shipping coupon system.
 *
 * Filters WooCommerce shipping rates to replace all methods with a single
 * free shipping rate when a valid free-shipping coupon is applied.
 * Manages session state, handles coupon apply/remove events,
 * and renders the free shipping notification banner.
 *
 * @since      1.0.0
 * @package    Wdb_Discount_Builder
 * @subpackage Wdb_Discount_Builder/shipping
 * @author     Alireza Sayadi <https://github.com/alirezasayadi>
 */

defined('ABSPATH') || exit;

/**
 * Manages free shipping logic, session state, and display.
 */
class Wdb_Free_Shipping {

	/**
	 * Register all hooks.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Shipping rates filter
		add_filter('woocommerce_package_rates', array($this, 'filter_shipping_rates'), 9999, 2);

		// Coupon event handlers
		add_action('woocommerce_applied_coupon', array($this, 'on_coupon_applied'));
		add_action('woocommerce_removed_coupon', array($this, 'on_coupon_removed'));
		add_action('woocommerce_checkout_update_order_review', array($this, 'on_address_change'));

		// Session cleanup
		add_action('wp_logout', array($this, 'clear_session'));
		add_action('woocommerce_thankyou', array($this, 'clear_session'));

		// Display
		add_action('woocommerce_before_cart_table', array($this, 'show_cart_message'), 5);
		add_action('woocommerce_before_checkout_form', array($this, 'show_checkout_message'), 5);

		// Frontend assets
		add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

		// Save to order
		add_action('woocommerce_checkout_create_order', array($this, 'save_to_order'), 10, 2);
	}

	/**
	 * Check if free shipping is active based on applied coupons.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_active() {
		if (!Wdb_Helpers::cart_ready()) {
			return false;
		}

		$applied_coupons = WC()->cart->get_applied_coupons();
		if (empty($applied_coupons)) {
			return false;
		}

		$free_shipping_rules = Wdb_Helpers::get_free_shipping_rules();
		if (empty($free_shipping_rules)) {
			return false;
		}

		foreach ($applied_coupons as $coupon_code) {
			$coupon_code = strtolower($coupon_code);

			if (!isset($free_shipping_rules[$coupon_code]) || !$free_shipping_rules[$coupon_code]) {
				continue;
			}

			$coupon_id = wc_get_coupon_id_by_code($coupon_code);
			if ($coupon_id) {
				$coupon = new WC_Coupon($coupon_id);
				if ($coupon->get_status() === 'publish') {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get the applied free shipping coupon code.
	 *
	 * @since 1.0.0
	 * @return string Coupon code or empty string.
	 */
	public static function get_applied_coupon() {
		if (!Wdb_Helpers::cart_ready()) {
			return '';
		}

		$applied_coupons    = WC()->cart->get_applied_coupons();
		$free_shipping_rules = Wdb_Helpers::get_free_shipping_rules();

		foreach ($applied_coupons as $coupon_code) {
			$coupon_code_lower = strtolower($coupon_code);
			if (isset($free_shipping_rules[$coupon_code_lower]) && $free_shipping_rules[$coupon_code_lower]) {
				return $coupon_code;
			}
		}

		return '';
	}

	/**
	 * Filter shipping rates to show only free shipping when active.
	 *
	 * @since 1.0.0
	 * @param array  $rates  Shipping rates.
	 * @param array  $package Shipping package.
	 * @return array
	 */
	public function filter_shipping_rates( $rates, $package ) {
		if (!self::is_active()) {
			if (WC()->session) {
				$chosen = WC()->session->get('chosen_shipping_methods');
				if ($chosen && isset($chosen[0]) && $chosen[0] === 'wdb_free_shipping') {
					WC()->session->set('chosen_shipping_methods', array());
				}
				WC()->session->set('wdb_free_shipping_active', false);
			}
			return $rates;
		}

		if (empty($rates)) {
			return $rates;
		}

		if (WC()->session) {
			WC()->session->set('wdb_free_shipping_active', true);
			$chosen = WC()->session->get('chosen_shipping_methods');
			if (empty($chosen) || $chosen[0] !== 'wdb_free_shipping') {
				WC()->session->set('chosen_shipping_methods', array('wdb_free_shipping'));
			}
		}

		$free_shipping_rate = new WC_Shipping_Rate(
			'wdb_free_shipping',
			'🚚 ' . esc_html__('Free Shipping with Coupon Code', 'wdb-discount-builder'),
			0,
			array(),
			'wdb_free_shipping'
		);

		return array('wdb_free_shipping' => $free_shipping_rate);
	}

	/**
	 * Update free shipping state after coupon is applied.
	 *
	 * @since 1.0.0
	 * @param string $coupon_code Coupon code.
	 */
	public function on_coupon_applied( $coupon_code ) {
		if (!function_exists('WC') || !WC()->cart) {
			return;
		}

		$active = self::is_active();
		if (WC()->session) {
			WC()->session->set('wdb_free_shipping_active', $active);
		}
	}

	/**
	 * Clear free shipping state after coupon is removed.
	 *
	 * @since 1.0.0
	 * @param string $coupon_code Coupon code.
	 */
	public function on_coupon_removed( $coupon_code ) {
		if (!function_exists('WC') || !WC()->cart) {
			return;
		}

		$active = self::is_active();
		if (WC()->session) {
			WC()->session->set('wdb_free_shipping_active', $active);

			if (!$active) {
				$chosen = WC()->session->get('chosen_shipping_methods');
				if ($chosen && isset($chosen[0]) && $chosen[0] === 'wdb_free_shipping') {
					WC()->session->set('chosen_shipping_methods', array());
				}
			}
		}
	}

	/**
	 * Update free shipping state on address change during checkout.
	 *
	 * @since 1.0.0
	 */
	public function on_address_change() {
		if (!Wdb_Helpers::cart_ready()) {
			return;
		}

		$active = self::is_active();
		WC()->session->set('wdb_free_shipping_active', $active);

		if (!$active) {
			$chosen = WC()->session->get('chosen_shipping_methods');
			if ($chosen && isset($chosen[0]) && $chosen[0] === 'wdb_free_shipping') {
				WC()->session->set('chosen_shipping_methods', array());
			}
		}
	}

	/**
	 * Clear free shipping session on logout.
	 *
	 * @since 1.0.0
	 */
	public function clear_session() {
		if (WC()->session) {
			WC()->session->set('wdb_free_shipping_active', false);
		}
	}

	/**
	 * Render the free shipping notification message.
	 *
	 * @since 1.0.0
	 * @param string $css_class Additional CSS class.
	 */
	public static function render_message( $css_class = '' ) {
		if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
			return;
		}

		$free_coupon = self::get_applied_coupon();
		if (empty($free_coupon)) {
			return;
		}

		$extra_class = $css_class ? ' ' . esc_attr($css_class) : '';
		$safe_coupon = esc_html($free_coupon);
		?>
		<div class="wdb-fs-message<?php echo $extra_class; ?>" role="status" aria-live="polite">
			<div class="wdb-fs-card">
				<div class="wdb-fs-card-left">
					<div class="wdb-fs-icon-wrap">
						<span class="wdb-fs-truck" aria-hidden="true">🚚</span>
					</div>
				</div>
				<div class="wdb-fs-card-body">
					<div class="wdb-fs-title-row">
						<span class="wdb-fs-check" aria-hidden="true">✓</span>
						<span class="wdb-fs-title"><?php esc_html_e('Free shipping activated', 'wdb-discount-builder'); ?></span>
					</div>
					<p class="wdb-fs-desc"><?php esc_html_e('Your shipping cost has been successfully set to zero.', 'wdb-discount-builder'); ?></p>
					<div class="wdb-fs-coupon-row">
						<span class="wdb-fs-coupon-label"><?php esc_html_e('Active coupon:', 'wdb-discount-builder'); ?></span>
						<span class="wdb-fs-coupon-badge"><?php echo $safe_coupon; ?></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Show free shipping message on cart page.
	 *
	 * @since 1.0.0
	 */
	public function show_cart_message() {
		if (is_admin()) {
			return;
		}
		self::render_message();
	}

	/**
	 * Show free shipping message on checkout page.
	 *
	 * @since 1.0.0
	 */
	public function show_checkout_message() {
		if (is_admin()) {
			return;
		}
		self::render_message('wdb-fs-checkout');
	}

	/**
	 * Enqueue free shipping frontend styles.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_frontend_assets() {
		if (!is_cart() && !is_checkout()) {
			return;
		}

		wp_add_inline_style('woocommerce-general', '
			/* === Free Shipping Success Card === */
			.wdb-fs-message {
				margin: 15px 0;
				animation: wdb-fs-slideIn 0.4s cubic-bezier(0.22, 1, 0.36, 1);
			}
			.wdb-fs-checkout {
				margin-bottom: 20px;
			}
			.wdb-fs-card {
				display: flex;
				align-items: stretch;
				background: #f0faf0;
				border: 1px solid #b2dfb2;
				border-right: 4px solid #43a047;
				border-radius: 8px;
				overflow: hidden;
				box-shadow: 0 1px 6px rgba(76, 175, 80, 0.1);
			}
			.wdb-fs-card-left {
				display: flex;
				align-items: center;
				justify-content: center;
				padding: 16px 14px;
				background: #e8f5e9;
			}
			.wdb-fs-icon-wrap {
				display: flex;
				align-items: center;
				justify-content: center;
				width: 44px;
				height: 44px;
				border-radius: 50%;
				background: #c8e6c9;
				font-size: 22px;
				line-height: 1;
			}
			.wdb-fs-truck {
				font-size: 22px;
			}
			.wdb-fs-card-body {
				flex: 1;
				padding: 14px 18px;
				min-width: 0;
			}
			.wdb-fs-title-row {
				display: flex;
				align-items: center;
				gap: 6px;
				margin-bottom: 4px;
				flex-wrap: wrap;
			}
			.wdb-fs-check {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 20px;
				height: 20px;
				border-radius: 50%;
				background: #43a047;
				color: #fff;
				font-size: 12px;
				font-weight: 700;
				line-height: 1;
				flex-shrink: 0;
			}
			.wdb-fs-title {
				font-size: 15px;
				font-weight: 700;
				color: #2e7d32;
			}
			.wdb-fs-desc {
				margin: 0 0 8px;
				font-size: 13px;
				color: #555;
				line-height: 1.5;
			}
			.wdb-fs-coupon-row {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				direction: rtl;
			}
			.wdb-fs-coupon-label {
				font-size: 12px;
				color: #666;
				font-weight: 500;
			}
			.wdb-fs-coupon-badge {
				display: inline-block;
				background: #fff;
				color: #2e7d32;
				font-weight: 700;
				font-size: 13px;
				padding: 3px 12px;
				border-radius: 12px;
				border: 1px dashed #66bb6a;
				letter-spacing: 0.3px;
				font-family: monospace;
				direction: ltr;
				unicode-bidi: isolate;
			}
			@keyframes wdb-fs-slideIn {
				from { opacity: 0; transform: translateY(-6px); }
				to { opacity: 1; transform: translateY(0); }
			}
			@media (max-width: 782px) {
				.wdb-fs-card { border-radius: 6px; border-right-width: 3px; }
				.wdb-fs-card-left { padding: 12px 10px; }
				.wdb-fs-icon-wrap { width: 36px; height: 36px; font-size: 18px; }
				.wdb-fs-truck { font-size: 18px; }
				.wdb-fs-card-body { padding: 10px 12px; }
				.wdb-fs-title { font-size: 14px; }
				.wdb-fs-desc { font-size: 12px; margin-bottom: 6px; }
				.wdb-fs-coupon-row { flex-direction: column; gap: 3px; align-items: flex-start; }
			}
			[dir="ltr"] .wdb-fs-coupon-row { direction: ltr; }
			[dir="ltr"] .wdb-fs-card { border-right: 1px solid #b2dfb2; border-left: 4px solid #43a047; }
		');
	}

	/**
	 * Save free shipping metadata to order at checkout.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order Order object.
	 * @param array    $data  Posted data.
	 */
	public function save_to_order( $order, $data ) {
		if (!self::is_active()) {
			return;
		}

		$order->update_meta_data('_wdb_free_shipping', 'yes');

		$free_coupon = self::get_applied_coupon();
		if ($free_coupon) {
			$order->update_meta_data('_wdb_free_shipping_coupon', $free_coupon);
		}
	}
}
