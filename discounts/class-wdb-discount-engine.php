<?php
/**
 * Discount calculation engine and coupon management.
 *
 * Handles applying plugin-specific discounts to cart items and orders,
 * validating coupons against product restrictions, suppressing WooCommerce's
 * default coupon discount (since we apply our own), and managing fee removal.
 *
 * @since      1.0.0
 * @package    Wdb_Discount_Builder
 * @subpackage Wdb_Discount_Builder/discounts
 * @author     Alireza Sayadi <https://github.com/alirezasayadi>
 */

defined('ABSPATH') || exit;

/**
 * Core discount engine: application, validation, fee handling, order processing.
 */
class Wdb_Discount_Engine {

	/**
	 * Register all hooks.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Suppress WooCommerce default coupon discount
		add_filter('woocommerce_coupon_get_discount_amount', array($this, 'zero_discount_for_our_coupons'), 1, 5);

		// Apply discounts in cart
		add_action('woocommerce_before_calculate_totals', array($this, 'apply_discounts_to_cart_items'), 9999, 1);

		// Apply discounts in admin orders
		add_action('woocommerce_order_before_calculate_totals', array($this, 'apply_discounts_to_admin_order'), 9999, 2);

		// Validate coupon for eligible products
		add_filter('woocommerce_coupon_is_valid', array($this, 'validate_coupon_for_products'), 10, 3);

		// Fee management
		add_action('woocommerce_cart_calculate_fees', array($this, 'remove_coupon_fees'), 1, 1);
		add_filter('woocommerce_cart_totals_coupon_html', array($this, 'remove_coupon_fee_display'), 9999, 2);
		add_filter('woocommerce_cart_totals_fee_html', array($this, 'hide_coupon_fee'), 9999, 2);
		add_action('woocommerce_before_cart_table', array($this, 'set_coupon_amount_zero'), 1);

		// Enforce single coupon usage
		add_filter('woocommerce_coupon_is_valid', array($this, 'enforce_single_coupon'), 10, 3);
		add_action('woocommerce_applied_coupon', array($this, 'enforce_single_coupon_on_apply'), 1);

		// Order processing
		add_action('woocommerce_checkout_create_order_line_item', array($this, 'transfer_discount_meta_to_order'), 10, 4);
		add_action('woocommerce_checkout_order_processed', array($this, 'recalculate_order_totals'), 10, 3);
		add_action('woocommerce_checkout_order_processed', array($this, 'save_coupon_without_fee'), 10, 3);
	}

	/**
	 * Return zero discount for plugin-managed coupons.
	 *
	 * Prevents WooCommerce from applying its own discount calculation.
	 *
	 * @since 1.0.0
	 */
	public function zero_discount_for_our_coupons( $discount, $amount, $cart_item, $single, $coupon ) {
		$coupon_code = strtolower($coupon->get_code());
		$all_rules   = Wdb_Helpers::get_all_rules();

		if (isset($all_rules[$coupon_code])) {
			return 0;
		}

		return $discount;
	}

	/**
	 * Apply plugin discounts to cart items.
	 *
	 * @since 1.0.0
	 * @param WC_Cart $cart Cart object.
	 */
	public function apply_discounts_to_cart_items( $cart ) {
		if (is_admin() && !defined('DOING_AJAX')) {
			return;
		}

		if ($cart->is_empty()) {
			return;
		}

		$all_rules = Wdb_Helpers::get_all_rules();
		if (empty($all_rules)) {
			return;
		}

		$applied_coupons = $cart->get_applied_coupons();
		if (empty($applied_coupons)) {
			foreach ($cart->get_cart() as $cart_item) {
				$stored_original = floatval($cart_item['data']->get_meta('_wdb_original_price'));
				if ($stored_original > 0) {
					$cart_item['data']->set_price(Wdb_Helpers::get_base_price($cart_item['data']));
					$cart_item['data']->update_meta_data('_wdb_discount_amount', 0);
				}
			}
			return;
		}

		foreach ($applied_coupons as $coupon_code) {
			$coupon_code = strtolower($coupon_code);
			if (!isset($all_rules[$coupon_code])) {
				continue;
			}

			foreach ($cart->get_cart() as $cart_item) {
				$product_id = !empty($cart_item['variation_id'])
					? intval($cart_item['variation_id'])
					: intval($cart_item['product_id']);

				if (Wdb_Helpers::is_pack_product($product_id)) {
					continue;
				}

				$original_price = Wdb_Helpers::get_base_price($cart_item['data']);
				$discount_info  = Wdb_Helpers::get_discount_for_product(
					$product_id, $all_rules, $coupon_code, $original_price
				);

				if ($discount_info['amount'] > 0) {
					$cart_item['data']->update_meta_data('_wdb_discount_amount', $discount_info['amount']);
					$cart_item['data']->update_meta_data('_wdb_original_price', $original_price);

					$new_price = max(0, $original_price - $discount_info['amount']);
					$cart_item['data']->set_price($new_price);
				}
			}
		}
	}

	/**
	 * Apply plugin discounts in admin order recalculation.
	 *
	 * @since 1.0.0
	 * @param bool    $and_taxes Whether to calculate taxes.
	 * @param WC_Order $order     Order object.
	 */
	public function apply_discounts_to_admin_order( $and_taxes, $order ) {
		if (!is_admin()) {
			return;
		}

		$coupons = $order->get_coupon_codes();
		if (empty($coupons)) {
			return;
		}

		$all_rules = Wdb_Helpers::get_all_rules();

		foreach ($coupons as $coupon_code) {
			$coupon_code = strtolower($coupon_code);
			if (!isset($all_rules[$coupon_code])) {
				continue;
			}

			foreach ($order->get_items() as $item_id => $item) {
				$product_id      = $item->get_product_id();
				$variation_id    = $item->get_variation_id();
				$actual_product_id = $variation_id ? $variation_id : $product_id;

				if (Wdb_Helpers::is_pack_product($actual_product_id)) {
					continue;
				}

				$product = wc_get_product($actual_product_id);
				if (!$product) {
					continue;
				}

				$original_price = Wdb_Helpers::get_base_price($product);
				$discount_info  = Wdb_Helpers::get_discount_for_product(
					$actual_product_id, $all_rules, $coupon_code, $original_price
				);

				if ($discount_info['amount'] > 0) {
					$quantity = $item->get_quantity();
					$new_price = max(0, $original_price - $discount_info['amount']);

					$item->set_subtotal($original_price * $quantity);
					$item->set_total($new_price * $quantity);

					$item->update_meta_data('_wdb_discount_amount', $discount_info['amount'] * $quantity);
					$item->update_meta_data('_wdb_discount_per_item', $discount_info['amount']);
					$item->update_meta_data('_wdb_original_price', $original_price);

					$item->save();
				}
			}
		}
	}

	/**
	 * Validate coupon against eligible products in cart.
	 *
	 * @since 1.0.0
	 * @param bool      $valid    Current validity.
	 * @param WC_Coupon $coupon   Coupon object.
	 * @param array     $discounts Discounts object.
	 * @return bool
	 */
	public function validate_coupon_for_products( $valid, $coupon, $discounts ) {
		if (!$valid) {
			return $valid;
		}

		$coupon_code = strtolower($coupon->get_code());
		$all_rules   = Wdb_Helpers::get_all_rules();

		if (!isset($all_rules[$coupon_code])) {
			return $valid;
		}

		$eligible_product_ids = Wdb_Helpers::get_eligible_product_ids($coupon_code, $all_rules);
		if (empty($eligible_product_ids)) {
			return $valid;
		}

		$has_eligible = false;
		if (WC()->cart) {
			foreach (WC()->cart->get_cart() as $cart_item) {
				$product_id = !empty($cart_item['variation_id'])
					? intval($cart_item['variation_id'])
					: intval($cart_item['product_id']);

				if (Wdb_Helpers::is_product_eligible($product_id, $eligible_product_ids)) {
					$has_eligible = true;
					break;
				}
			}
		}

		if (!$has_eligible) {
			$other_settings = Wdb_Helpers::get_other_products_settings($coupon_code);
			if ($other_settings['type'] !== 'none' && $other_settings['value'] > 0) {
				return $valid;
			}

			wc_add_notice(
				sprintf(
					/* translators: %s: coupon code */
					esc_html__('Warning: coupon %s is only valid for specific products. There are no eligible products in your cart.', 'wdb-discount-builder'),
					$coupon->get_code()
				),
				'error'
			);
			return false;
		}

		return $valid;
	}

	/**
	 * Remove fees created by plugin coupons from cart.
	 *
	 * @since 1.0.0
	 * @param WC_Cart $cart Cart object.
	 */
	public function remove_coupon_fees( $cart ) {
		if (is_admin() && !defined('DOING_AJAX')) {
			return;
		}

		$all_rules        = Wdb_Helpers::get_all_rules();
		$applied_coupons  = $cart->get_applied_coupons();

		if (empty($applied_coupons)) {
			return;
		}

		foreach ($cart->get_fees() as $fee_key => $fee) {
			$fee_name = strtolower($fee->name);

			foreach ($applied_coupons as $coupon_code) {
				$coupon_code = strtolower($coupon_code);

				if (isset($all_rules[$coupon_code]) && strpos($fee_name, $coupon_code) !== false) {
					unset($cart->fees_api()->fees[$fee_key]);
				}
			}
		}
	}

	/**
	 * Custom display for plugin coupon in cart totals.
	 *
	 * Replaces the default coupon HTML with a custom label and remove link.
	 *
	 * @since 1.0.0
	 * @param string     $coupon_html Default HTML.
	 * @param WC_Coupon  $coupon      Coupon object.
	 * @return string
	 */
	public function remove_coupon_fee_display( $coupon_html, $coupon ) {
		$coupon_code = strtolower($coupon->get_code());
		$all_rules   = Wdb_Helpers::get_all_rules();

		if (isset($all_rules[$coupon_code])) {
			$remove_url  = '#';
			$label_html  = '<span class="wdb-coupon-applied-text">✓ ' . esc_html__('Coupon applied', 'wdb-discount-builder') . '</span>';
			$remove_html = '<a href="' . esc_url($remove_url) . '" class="wdb-remove-coupon-link" data-coupon="' . esc_attr($coupon_code) . '">'
				. '<span class="wdb-rcl-icon">✕</span> '
				. esc_html__('Remove coupon', 'wdb-discount-builder')
				. '</a>';
			return $label_html . $remove_html;
		}

		return $coupon_html;
	}

	/**
	 * Hide fee rows in cart totals for plugin coupons.
	 *
	 * @since 1.0.0
	 * @param string $fee_html Default fee HTML.
	 * @param object $fee      Fee object.
	 * @return string
	 */
	public function hide_coupon_fee( $fee_html, $fee ) {
		$fee_name  = strtolower($fee->name);
		$all_rules = Wdb_Helpers::get_all_rules();

		foreach ($all_rules as $coupon_code => $rules) {
			if (strpos($fee_name, $coupon_code) !== false || strpos($fee_name, 'تخفیف') !== false) {
				return '';
			}
		}

		return $fee_html;
	}

	/**
	 * Set plugin coupon amounts to zero in cart.
	 *
	 * @since 1.0.0
	 */
	public function set_coupon_amount_zero() {
		if (is_admin() || !function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
			return;
		}

		$all_rules        = Wdb_Helpers::get_all_rules();
		$applied_coupons  = WC()->cart->get_applied_coupons();

		foreach ($applied_coupons as $coupon_code) {
			$coupon_code = strtolower($coupon_code);

			if (isset($all_rules[$coupon_code])) {
				foreach (WC()->cart->get_coupons() as $coupon) {
					if (strtolower($coupon->get_code()) === $coupon_code) {
						$coupon->set_amount(0);
					}
				}
			}
		}
	}

	/**
	 * Enforce single coupon usage — reject if another coupon is already applied.
	 *
	 * @since 1.0.0
	 * @param bool      $valid    Current validity.
	 * @param WC_Coupon $coupon   Coupon object.
	 * @param array     $discounts Discounts object.
	 * @return bool
	 */
	public function enforce_single_coupon( $valid, $coupon, $discounts ) {
		if (!$valid) {
			return $valid;
		}

		if (!function_exists('WC') || !WC()->cart) {
			return $valid;
		}

		$coupon_code = strtolower($coupon->get_code());
		$applied     = WC()->cart->get_applied_coupons();

		if (!empty($applied)) {
			foreach ($applied as $applied_code) {
				if (strtolower($applied_code) !== $coupon_code) {
					wc_add_notice(
						esc_html__('Only one coupon code is allowed. Please remove the previous one first.', 'wdb-discount-builder'),
						'error'
					);
					return false;
				}
			}
		}

		return $valid;
	}

	/**
	 * Remove previously applied coupon if a new one is added (safety net).
	 *
	 * @since 1.0.0
	 * @param string $new_coupon New coupon code.
	 */
	public function enforce_single_coupon_on_apply( $new_coupon ) {
		if (!function_exists('WC') || !WC()->cart) {
			return;
		}

		$new_coupon_lower = strtolower($new_coupon);
		$applied          = WC()->cart->get_applied_coupons();

		if (count($applied) > 1) {
			foreach ($applied as $coupon_code) {
				if (strtolower($coupon_code) !== $new_coupon_lower) {
					WC()->cart->remove_coupon($coupon_code);
				}
			}

			wc_add_notice(
				esc_html__('Only one coupon code is allowed. The previous code has been removed.', 'wdb-discount-builder'),
				'error'
			);
		}
	}

	/**
	 * Transfer discount meta from cart items to order items at checkout.
	 *
	 * @since 1.0.0
	 * @param WC_Order_Item $item         Order item.
	 * @param string        $cart_item_key Cart item key.
	 * @param array         $values       Cart item values.
	 * @param WC_Order      $order        Order object.
	 */
	public function transfer_discount_meta_to_order( $item, $cart_item_key, $values, $order ) {
		$product = $values['data'];
		if (!$product) {
			return;
		}

		$discount_amount = floatval($product->get_meta('_wdb_discount_amount'));
		$original_price  = floatval($product->get_meta('_wdb_original_price'));

		if ($discount_amount <= 0) {
			return;
		}

		$quantity         = $values['quantity'];
		$discount_per_item = $discount_amount;
		$new_price        = max(0, $original_price - $discount_per_item);

		$item->update_meta_data('_wdb_discount_amount', $discount_amount * $quantity);
		$item->update_meta_data('_wdb_discount_per_item', $discount_per_item);
		$item->update_meta_data('_wdb_original_price', $original_price);

		$item->set_subtotal($original_price * $quantity);
		$item->set_total($new_price * $quantity);
	}

	/**
	 * Recalculate order totals after processing if discounts were applied.
	 *
	 * @since 1.0.0
	 * @param int    $order_id   Order ID.
	 * @param array  $posted_data Posted data.
	 * @param WC_Order $order     Order object.
	 */
	public function recalculate_order_totals( $order_id, $posted_data, $order ) {
		if (!$order) {
			return;
		}

		foreach ($order->get_items() as $item) {
			$discount_amount = floatval($item->get_meta('_wdb_discount_amount'));
			if ($discount_amount > 0) {
				$order->calculate_totals();
				return;
			}
		}
	}

	/**
	 * Save plugin coupon to order without WooCommerce fee calculation.
	 *
	 * @since 1.0.0
	 * @param int      $order_id   Order ID.
	 * @param array    $posted_data Posted data.
	 * @param WC_Order $order      Order object.
	 */
	public function save_coupon_without_fee( $order_id, $posted_data, $order ) {
		if (!$order) {
			return;
		}

		$all_rules       = Wdb_Helpers::get_all_rules();
		$applied_coupons = WC()->cart ? WC()->cart->get_applied_coupons() : array();

		foreach ($applied_coupons as $coupon_code) {
			$coupon_code = strtolower($coupon_code);

			if (isset($all_rules[$coupon_code])) {
				$order->update_meta_data('_wdb_coupon_code', $coupon_code);
				$order->apply_coupon($coupon_code);
				$order->set_discount_total(0);
				$order->set_discount_tax(0);
			}
		}

		$order->save();
	}

	/**
	 * Save total discount amount to order post meta.
	 *
	 * @since 1.0.0
	 * @param int   $order_id      Order ID.
	 * @param float $total_discount Total discount amount.
	 */
	public static function save_discount_total_to_order( $order_id, $total_discount ) {
		if ($total_discount > 0) {
			update_post_meta($order_id, '_wdb_total_discount', $total_discount);
		} else {
			delete_post_meta($order_id, '_wdb_total_discount');
		}
	}
}
