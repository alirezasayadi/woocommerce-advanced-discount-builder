<?php
/**
 * Frontend display and public-facing functionality.
 *
 * Handles cart discount display, product discount badges,
 * cart item price overrides, coupon label customization,
 * frontend styles, and the AJAX coupon removal script.
 *
 * @since      1.0.0
 * @package    Wdb_Discount_Builder
 * @subpackage Wdb_Discount_Builder/public
 * @author     Alireza Sayadi <https://github.com/alirezasayadi>
 */

defined('ABSPATH') || exit;

/**
 * Frontend display: cart UI, product badges, order display, email display, styles.
 */
class Wdb_Public {

	/**
	 * Register all frontend hooks.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Cart discount status
		add_action('woocommerce_before_cart_table', array($this, 'show_discount_status'));

		// Product discount badge
		add_action('woocommerce_single_product_summary', array($this, 'show_product_discount_badge'), 15);

		// Pack products notice
		add_action('woocommerce_before_cart', array($this, 'pack_discount_notice'));

		// Cart item display overrides
		add_action('woocommerce_cart_item_price', array($this, 'display_cart_item_original_price'), 10, 3);
		add_action('woocommerce_after_cart_item_name', array($this, 'display_cart_item_discount'), 10, 2);
		add_filter('woocommerce_cart_item_subtotal', array($this, 'display_cart_item_subtotal_with_discount'), 10, 3);

		// Customer order display
		add_filter('woocommerce_get_order_item_totals', array($this, 'add_discount_row_to_order_totals'), 10, 3);
		add_action('woocommerce_order_item_meta_start', array($this, 'display_order_item_discount'), 10, 4);
		add_filter('woocommerce_order_formatted_line_subtotal', array($this, 'custom_order_subtotal'), 10, 3);

		// Email display
		add_action('woocommerce_email_after_order_table', array($this, 'add_discount_to_email'), 10, 4);

		// Coupon label
		add_filter('woocommerce_cart_totals_coupon_label', array($this, 'custom_coupon_label'), 10, 2);

		// Frontend styles and JS
		add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
	}

	/**
	 * Show discount status at the top of the cart.
	 *
	 * @since 1.0.0
	 */
	public function show_discount_status() {
		if (is_admin() || !function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
			return;
		}

		$applied_coupons = WC()->cart->get_applied_coupons();
		if (empty($applied_coupons)) {
			return;
		}

		$all_rules      = Wdb_Helpers::get_all_rules();
		$cart_items     = WC()->cart->get_cart();

		$discounts_per_item = array();
		$total_discount     = 0;
		$pack_products      = array();

		foreach ($applied_coupons as $coupon_code) {
			$coupon_code = strtolower($coupon_code);
			if (!isset($all_rules[$coupon_code])) {
				continue;
			}

			foreach ($cart_items as $cart_item) {
				$product_id = !empty($cart_item['variation_id'])
					? intval($cart_item['variation_id'])
					: intval($cart_item['product_id']);

				if (Wdb_Helpers::is_pack_product($product_id)) {
					$pack_products[] = $cart_item['data']->get_name();
					continue;
				}

				$original_price = Wdb_Helpers::get_base_price($cart_item['data']);
				$current_price  = floatval($cart_item['data']->get_price());
				$item_discount  = ($original_price - $current_price) * $cart_item['quantity'];

				if ($item_discount > 0) {
					$discount_info = Wdb_Helpers::get_discount_for_product(
						$product_id, $all_rules, $coupon_code, $original_price
					);

					$discounts_per_item[] = array(
						'name'     => $cart_item['data']->get_name(),
						'discount' => $item_discount,
						'text'     => $discount_info['type_text'],
					);
					$total_discount += $item_discount;
				}
			}
		}

		if (empty($discounts_per_item) && empty($pack_products)) {
			return;
		}

		$this->render_discount_status_output($discounts_per_item, $total_discount, $pack_products, $applied_coupons);
	}

	/**
	 * Render the discount status output in the cart.
	 *
	 * @since 1.0.0
	 * @param array $discounts       Discount items.
	 * @param float $total           Total discount.
	 * @param array $pack_products   Pack product names.
	 * @param array $applied_coupons Applied coupon codes.
	 */
	private function render_discount_status_output( $discounts, $total, $pack_products, $applied_coupons = array() ) {
		?>
		<div class="wdb-discount-status" style="background:#f0f7ff;padding:12px 15px;margin:15px 0;border-left:4px solid #007cba;border-radius:3px;">
			<p style="margin:0 0 10px 0;font-weight:bold;color:#007cba;font-size:15px;">
				<?php esc_html_e('Discount applied on cart products:', 'wdb-discount-builder'); ?>
			</p>

			<ul style="margin:0;padding:0;list-style:none;">
				<?php foreach ($discounts as $item): ?>
					<li style="padding:4px 0;border-bottom:1px solid #e8f0fe;">
						<span style="font-weight:500;">
							&bull; <?php echo esc_html($item['name']); ?>:
						</span>
						<span style="color:#d63031;font-weight:bold;">
							<?php echo esc_html($item['text']); ?>
							(<?php echo number_format($item['discount'], 0); ?> <?php esc_html_e('Toman', 'wdb-discount-builder'); ?>)
						</span>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if (!empty($pack_products)): ?>
				<div style="margin-top:12px;padding-top:10px;border-top:1px dashed #f0ad4e;">
					<p style="margin:0 0 8px;font-weight:bold;color:#d97706;">
						<?php esc_html_e('The following products are in the "Value Pack" category and are not eligible for any discount:', 'wdb-discount-builder'); ?>
					</p>
					<ul style="margin:0;padding:0;list-style:none;">
						<?php foreach (array_unique($pack_products) as $product_name): ?>
							<li style="padding:3px 0;color:#666;">
								&bull; <?php echo esc_html($product_name); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<p style="margin:8px 0 0 0;font-weight:bold;color:#007cba;border-top:1px solid #e8f0fe;padding-top:8px;">
				<?php
				printf(
					/* translators: %s: total discount amount */
					esc_html__('Total discount: %s Toman', 'wdb-discount-builder'),
					number_format($total, 0)
				);
				?>
			</p>

		</div>
		<?php
	}

	/**
	 * Show discount badge on single product pages.
	 *
	 * @since 1.0.0
	 */
	public function show_product_discount_badge() {
		global $product;
		if (!$product) {
			return;
		}

		$product_id = $product->get_id();
		if (Wdb_Helpers::is_pack_product($product_id)) {
			return;
		}

		$all_rules         = Wdb_Helpers::get_all_rules();
		$displayed_coupons = array();

		foreach ($all_rules as $coupon_code => $rules) {
			if (empty($rules)) {
				continue;
			}

			foreach ($rules as $rule) {
				$products = Wdb_Helpers::get_products_by_rule($rule);
				if (empty($products)) {
					continue;
				}

				if (in_array($product_id, array_map('intval', $products), true)) {
					$type  = isset($rule['type']) ? $rule['type'] : 'percent';
					$value = floatval(isset($rule['value']) ? $rule['value'] : 0);

					if ($value > 0) {
						$discount_text = ($type === 'percent')
							? sprintf('%s%% %s', $value, esc_html__('discount', 'wdb-discount-builder'))
							: sprintf('%s %s %s', number_format($value, 0), esc_html__('Toman', 'wdb-discount-builder'), esc_html__('discount', 'wdb-discount-builder'));

						$displayed_coupons[] = array(
							'code' => $coupon_code,
							'text' => $discount_text,
						);
					}
					break;
				}
			}
		}

		if (empty($displayed_coupons)) {
			return;
		}

		foreach ($displayed_coupons as $coupon):
			?>
			<div class="wdb-product-badge" style="display:inline-block;background:#ff6b6b;color:#fff;padding:5px 12px;border-radius:20px;font-size:14px;font-weight:bold;margin:5px 5px 5px 0;">
				<?php echo esc_html($coupon['text']); ?>
				<span style="font-weight:normal;font-size:12px;margin-left:5px;">
					(<?php
					printf(
						/* translators: %s: coupon code */
						esc_html__('Code: %s', 'wdb-discount-builder'),
						esc_html($coupon['code'])
					);
					?>)
				</span>
			</div>
			<?php
		endforeach;
	}

	/**
	 * Show notice about excluded pack products.
	 *
	 * @since 1.0.0
	 */
	public function pack_discount_notice() {
		if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
			return;
		}

		if (empty(WC()->cart->get_applied_coupons())) {
			return;
		}

		foreach (WC()->cart->get_cart() as $cart_item) {
			$product_id = !empty($cart_item['variation_id'])
				? $cart_item['variation_id']
				: $cart_item['product_id'];

			if (Wdb_Helpers::is_pack_product($product_id)) {
				wc_print_notice(
					esc_html__('Products in the "Value Pack" category are not eligible for any discounts or coupon codes and are excluded from discount calculations.', 'wdb-discount-builder'),
					'notice'
				);
				break;
			}
		}
	}

	/**
	 * Display original price with discount in cart item price column.
	 *
	 * @since 1.0.0
	 * @param string $price_html  Default price HTML.
	 * @param array  $cart_item   Cart item data.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function display_cart_item_original_price( $price_html, $cart_item, $cart_item_key ) {
		$product        = $cart_item['data'];
		$original_price = floatval($product->get_meta('_wdb_original_price'));

		if ($original_price > 0) {
			$current_price = floatval($product->get_price());
			if ($current_price < $original_price) {
				$discount   = $original_price - $current_price;
				$price_html = '<del>' . wc_price($original_price) . '</del> <ins>' . wc_price($current_price) . '</ins>';
				$price_html .= '<br><small style="color:#d63031;">' . esc_html__('Discount:', 'wdb-discount-builder') . ' ' . wc_price($discount) . '</small>';
			}
		}

		return $price_html;
	}

	/**
	 * Display discount below cart item name.
	 *
	 * @since 1.0.0
	 * @param array  $cart_item     Cart item data.
	 * @param string $cart_item_key Cart item key.
	 */
	public function display_cart_item_discount( $cart_item, $cart_item_key ) {
		$product        = $cart_item['data'];
		$original_price = floatval($product->get_meta('_wdb_original_price'));
		$current_price  = floatval($product->get_price());

		if ($original_price > 0 && $current_price < $original_price) {
			$discount_per_item = $original_price - $current_price;
			$total_discount    = $discount_per_item * $cart_item['quantity'];

			echo '<div style="color:#d63031;font-size:13px;margin-top:5px;">';
			echo esc_html__('Discount:', 'wdb-discount-builder') . ' ' . wc_price($total_discount);

			if ($cart_item['quantity'] > 1) {
				echo ' (' . wc_price($discount_per_item) . ' ' . esc_html__('per item', 'wdb-discount-builder') . ')';
			}

			echo '</div>';
		}
	}

	/**
	 * Customize cart item subtotal with discount display.
	 *
	 * @since 1.0.0
	 * @param string $subtotal_html Default subtotal HTML.
	 * @param array  $cart_item     Cart item data.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function display_cart_item_subtotal_with_discount( $subtotal_html, $cart_item, $cart_item_key ) {
		$product        = $cart_item['data'];
		$original_price = floatval($product->get_meta('_wdb_original_price'));

		if ($original_price > 0) {
			$current_price = floatval($product->get_price());
			$quantity      = $cart_item['quantity'];

			$original_total = $original_price * $quantity;
			$current_total  = $current_price * $quantity;
			$discount_total = $original_total - $current_total;

			$subtotal_html = '<del>' . wc_price($original_total) . '</del> ';
			$subtotal_html .= '<ins>' . wc_price($current_total) . '</ins>';
			$subtotal_html .= '<br><small style="color:#d63031;">' . esc_html__('Discount:', 'wdb-discount-builder') . ' -' . wc_price($discount_total) . '</small>';
		}

		return $subtotal_html;
	}

	/**
	 * Add discount row to customer-facing order totals.
	 *
	 * @since 1.0.0
	 * @param array  $total_rows  Order total rows.
	 * @param object $order       Order object.
	 * @param string $tax_display Tax display mode.
	 * @return array
	 */
	public function add_discount_row_to_order_totals( $total_rows, $order, $tax_display ) {
		$total_discount = 0;
		foreach ($order->get_items() as $item) {
			$discount = floatval($item->get_meta('_wdb_discount_amount'));
			if ($discount > 0) {
				$total_discount += $discount;
			}
		}

		if ($total_discount > 0) {
			$new_rows = array();
			foreach ($total_rows as $key => $row) {
				$new_rows[$key] = $row;
				if ($key === 'cart_subtotal') {
					$new_rows['wdb_discount'] = array(
						'label' => esc_html__('Discount:', 'wdb-discount-builder'),
						'value' => '-' . wc_price($total_discount),
					);
				}
			}
			$total_rows = $new_rows;
		}

		return $total_rows;
	}

	/**
	 * Display discount in order item meta (frontend + email).
	 *
	 * @since 1.0.0
	 * @param int    $item_id    Item ID.
	 * @param object $item       Order item.
	 * @param object $order      Order object.
	 * @param bool   $plain_text Whether plain text.
	 */
	public function display_order_item_discount( $item_id, $item, $order, $plain_text ) {
		$discount_amount  = floatval($item->get_meta('_wdb_discount_amount'));
		$discount_per_item = floatval($item->get_meta('_wdb_discount_per_item'));

		if ($discount_amount > 0) {
			echo '<div class="wdb-order-discount" style="color:#d63031;font-size:13px;margin-top:5px;">';
			echo esc_html__('Discount:', 'wdb-discount-builder') . ' ' . number_format($discount_amount, 0) . ' ' . esc_html__('Toman', 'wdb-discount-builder');

			if ($discount_per_item > 0 && $item->get_quantity() > 1) {
				echo ' (' . number_format($discount_per_item, 0) . ' ' . esc_html__('per item', 'wdb-discount-builder') . ')';
			}

			echo '</div>';
		}
	}

	/**
	 * Customize line subtotal in order/invoice display.
	 *
	 * @since 1.0.0
	 * @param string $subtotal Default subtotal.
	 * @param object $item     Order item.
	 * @param object $order    Order object.
	 * @return string
	 */
	public function custom_order_subtotal( $subtotal, $item, $order ) {
		$original_price   = floatval($item->get_meta('_wdb_original_price'));
		$discount_amount  = floatval($item->get_meta('_wdb_discount_per_item'));

		if ($original_price > 0 && $discount_amount > 0) {
			$quantity    = $item->get_quantity();
			$final_total = ($original_price * $quantity) - ($discount_amount * $quantity);
			return wc_price($final_total);
		}

		return $subtotal;
	}

	/**
	 * Display discount in order emails.
	 *
	 * @since 1.0.0
	 * @param object $order         Order object.
	 * @param bool   $sent_to_admin Whether sent to admin.
	 * @param bool   $plain_text    Whether plain text.
	 * @param object $email         Email object.
	 */
	public function add_discount_to_email( $order, $sent_to_admin, $plain_text, $email ) {
		$total_discount = 0;
		foreach ($order->get_items() as $item) {
			$discount = floatval($item->get_meta('_wdb_discount_amount'));
			if ($discount > 0) {
				$total_discount += $discount;
			}
		}

		if ($total_discount <= 0) {
			return;
		}

		if ($plain_text) {
			echo "\n" . esc_html__('Discount:', 'wdb-discount-builder') . ' -' . number_format($total_discount, 0) . ' ' . esc_html__('Toman', 'wdb-discount-builder') . "\n";
			return;
		}

		?>
		<table width="100%" style="margin-top:10px;border-collapse:collapse;">
			<tr>
				<td style="padding:10px;border:1px solid #ddd;font-weight:bold;background:#f9f9f9;">
					<?php esc_html_e('Discount:', 'wdb-discount-builder'); ?>
				</td>
				<td style="padding:10px;border:1px solid #ddd;color:#d63031;font-weight:bold;">
					-<?php echo wc_price($total_discount); ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Customize coupon label in cart totals.
	 *
	 * @since 1.0.0
	 * @param string     $label  Default label.
	 * @param WC_Coupon  $coupon Coupon object.
	 * @return string
	 */
	public function custom_coupon_label( $label, $coupon ) {
		$coupon_code = strtolower($coupon->get_code());
		$all_rules   = Wdb_Helpers::get_all_rules();

		if (isset($all_rules[$coupon_code])) {
			return sprintf(
				/* translators: %s: coupon code */
				esc_html__('Special Discount (%s)', 'wdb-discount-builder'),
				$coupon_code
			);
		}

		return $label;
	}

	/**
	 * Enqueue frontend styles and inline JS for coupon removal.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_frontend_styles() {
		if (!is_cart() && !is_product() && !is_checkout()) {
			return;
		}

		wp_add_inline_style('woocommerce-general', '
			.wdb-discount-status {
				background: #f0f7ff;
				padding: 12px 15px;
				margin: 15px 0;
				border-left: 4px solid #007cba;
				border-radius: 3px;
			}
			.wdb-discount-status ul {
				margin: 5px 0 0 20px;
				padding: 0;
			}
			.wdb-discount-status ul li {
				list-style: none;
			}
			.wdb-product-badge {
				display: inline-block;
				background: #ff6b6b;
				color: #fff;
				padding: 5px 12px;
				border-radius: 20px;
				font-size: 14px;
				font-weight: bold;
				margin: 5px 5px 5px 0;
			}
			.wdb-order-discount {
				color: #d63031;
				font-size: 13px;
				margin-top: 5px;
				font-weight: bold;
			}
			.wdb-coupon-applied-text {
				display: block;
				font-size: 12px;
				color: #2e7d32;
				margin-bottom: 4px;
				font-weight: 500;
				direction: rtl;
			}
			.wdb-remove-coupon-link {
				display: inline-flex;
				align-items: center;
				gap: 5px;
				margin-top: 6px;
				padding: 5px 14px;
				background: #fff5f5;
				color: #c62828 !important;
				border: 1px solid #ef9a9a;
				border-radius: 6px;
				font-size: 13px;
				font-weight: 600;
				text-decoration: none !important;
				cursor: pointer;
				transition: all 0.2s ease;
				line-height: 1.4;
				direction: rtl;
			}
			.wdb-remove-coupon-link:hover {
				background: #c62828 !important;
				color: #fff !important;
				border-color: #c62828 !important;
				box-shadow: 0 2px 8px rgba(198, 40, 40, 0.25);
			}
			.wdb-remove-coupon-link:active {
				transform: scale(0.97);
			}
			.wdb-remove-coupon-link .wdb-rcl-icon {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 18px;
				height: 18px;
				border-radius: 50%;
				background: rgba(198, 40, 40, 0.1);
				font-size: 11px;
				line-height: 1;
				transition: background 0.2s ease;
				flex-shrink: 0;
			}
			.wdb-remove-coupon-link:hover .wdb-rcl-icon {
				background: rgba(255, 255, 255, 0.25);
			}
			.wdb-remove-coupon-link.wdb-removing {
				opacity: 0.6;
				pointer-events: none;
			}
		');

		// Frontend JS for AJAX coupon removal — cart and checkout pages
		if (is_cart() || is_checkout()) {
			wp_register_script('wdb-remove-coupon', '', array('jquery'), WDBP_VERSION, true);
			wp_enqueue_script('wdb-remove-coupon');
			wp_localize_script('wdb-remove-coupon', 'wdbRemoveCoupon', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('wdb_remove_coupon_nonce'),
			));
			wp_add_inline_script('wdb-remove-coupon', 'jQuery(document).ready(function($) {
				$("body").on("click", ".wdb-remove-coupon-link", function(e) {
					e.preventDefault();
					var $el = $(this);
					var couponCode = $el.data("coupon");
					if (!couponCode) return;

					if (!confirm("' . esc_js(__('Are you sure you want to remove the coupon?', 'wdb-discount-builder')) . '")) return;

					$el.addClass("wdb-removing").html("<span class=\\\"wdb-rcl-icon\\\">⏳</span> ' . esc_js(__('Removing...', 'wdb-discount-builder')) . '");

					$.ajax({
						url: wdbRemoveCoupon.ajax_url,
						type: "POST",
						data: {
							action: "wdb_remove_coupon",
							nonce: wdbRemoveCoupon.nonce,
							coupon_code: couponCode
						},
						dataType: "json",
						success: function(response) {
							if (response && response.success) {
								window.location.reload();
							} else {
								alert((response && response.data && response.data.message) || "' . esc_js(__('Error removing coupon.', 'wdb-discount-builder')) . '");
								$el.removeClass("wdb-removing").html("<span class=\\\"wdb-rcl-icon\\\">✕</span> ' . esc_js(__('Remove coupon', 'wdb-discount-builder')) . '");
							}
						},
						error: function() {
							alert("' . esc_js(__('Server connection error.', 'wdb-discount-builder')) . '");
							$el.removeClass("wdb-removing").html("<span class=\\\"wdb-rcl-icon\\\">✕</span> ' . esc_js(__('Remove coupon', 'wdb-discount-builder')) . '");
						}
					});
				});
			});');
		}
	}
}
