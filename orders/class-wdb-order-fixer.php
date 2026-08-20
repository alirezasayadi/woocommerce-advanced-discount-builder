<?php
/**
 * Order correction tools for fixing historical orders.
 *
 * Provides functions and AJAX handlers for correcting orders
 * that were saved with incorrect discount data. Supports
 * single-order fixes, bulk fixes, and fee removal.
 *
 * @since      1.0.0
 * @package    Wdb_Discount_Builder
 * @subpackage Wdb_Discount_Builder/orders
 * @author     Alireza Sayadi <https://github.com/alirezasayadi>
 */

defined('ABSPATH') || exit;

/**
 * Order correction tools: UI, fix logic, batch processing, AJAX.
 */
class Wdb_Order_Fixer {

	/**
	 * Register all hooks.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		add_action('wp_ajax_wdb_remove_fees', array($this, 'ajax_remove_fees'));
		add_action('wp_ajax_wdb_fix_single_order', array($this, 'ajax_fix_single_order'));
		add_action('wp_ajax_wdb_fix_bulk_orders', array($this, 'ajax_fix_bulk_orders'));
		add_action('wp_ajax_wdb_get_bulk_count', array($this, 'ajax_get_bulk_count'));
		add_action('wp_ajax_wdb_fix_bulk_orders_batch', array($this, 'ajax_fix_bulk_orders_batch'));
		add_action('wp_ajax_wdb_get_bulk_progress', array($this, 'ajax_get_bulk_progress'));
		add_action('wp_ajax_wdb_reset_bulk_progress', array($this, 'ajax_reset_bulk_progress'));
	}

	/**
	 * Render the order correction tool HTML.
	 *
	 * @since 1.0.0
	 */
	public static function render_tool() {
		?>
		<div class="order-fix-tool">
			<h2><?php esc_html_e('Order Correction Tool', 'wdb-discount-builder'); ?></h2>
			<p style="color:#666;"><?php esc_html_e('Use this tool to correct previous orders that were placed with this plugin discount codes.', 'wdb-discount-builder'); ?></p>

			<div class="fix-input-group">
				<label style="font-weight:bold;"><?php esc_html_e('Fix a specific order:', 'wdb-discount-builder'); ?></label>
				<input type="number"
					   id="wdb-order-id-input"
					   class="fix-input"
					   placeholder="<?php esc_attr_e('Enter order number', 'wdb-discount-builder'); ?>">
				<button type="button"
						id="wdb-fix-order-btn"
						class="button button-primary">
					<?php esc_html_e('Check and Fix Order', 'wdb-discount-builder'); ?>
				</button>
			</div>

			<div id="wdb-fix-order-result" class="fix-result"></div>

			<hr style="margin:20px 0;">

			<div class="fix-input-group">
				<label style="font-weight:bold;"><?php esc_html_e('Remove fees only:', 'wdb-discount-builder'); ?></label>
				<input type="number"
					   id="wdb-remove-fee-order-id"
					   class="fix-input"
					   placeholder="<?php esc_attr_e('Order number', 'wdb-discount-builder'); ?>">
				<button type="button"
						id="wdb-remove-fee-btn"
						class="button button-secondary"
						style="background:#f44336;color:#fff;border-color:#f44336;">
					<?php esc_html_e('Remove Fees', 'wdb-discount-builder'); ?>
				</button>
			</div>

			<div id="wdb-remove-fee-result" class="fix-result"></div>

			<hr style="margin:20px 0;">

			<div class="fix-input-group">
				<label style="font-weight:bold;"><?php esc_html_e('Bulk order correction:', 'wdb-discount-builder'); ?></label>
				<input type="text"
					   id="wdb-coupon-code-input"
					   class="fix-input"
					   placeholder="<?php esc_attr_e('Coupon code (e.g.: SUMMER2026)', 'wdb-discount-builder'); ?>">
				<button type="button"
						id="wdb-fix-bulk-btn"
						class="button button-secondary">
					<?php esc_html_e('Bulk Fix', 'wdb-discount-builder'); ?>
				</button>
			</div>

			<div id="wdb-fix-bulk-result" class="fix-result"></div>
		</div>
		<?php
	}

	/**
	 * Remove coupon-related fees from an order.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order Order object.
	 * @return bool Whether any fees were removed.
	 */
	public static function remove_coupon_fees_from_order( $order ) {
		global $wpdb;

		$all_rules = Wdb_Helpers::get_all_rules();
		$fees      = $order->get_fees();

		if (empty($fees)) {
			return false;
		}

		$fees_removed = false;

		foreach ($fees as $fee_id => $fee) {
			$fee_name = strtolower($fee->get_name());

			$is_our_fee = false;

			foreach ($all_rules as $coupon_code => $rules) {
				if (strpos($fee_name, $coupon_code) !== false || strpos($fee_name, 'تخفیف') !== false) {
					$is_our_fee = true;
					break;
				}
			}

			if ($is_our_fee) {
				$wpdb->delete(
					$wpdb->prefix . 'woocommerce_order_items',
					array('order_item_id' => $fee_id),
					array('%d')
				);

				$wpdb->delete(
					$wpdb->prefix . 'woocommerce_order_itemmeta',
					array('order_item_id' => $fee_id),
					array('%d')
				);

				$fees_removed = true;
			}
		}

		if ($fees_removed) {
			$order->save();
			$order->calculate_totals();
			$order->save();
		}

		return $fees_removed;
	}

	/**
	 * Fix a single order by recalculating discounts and removing fees.
	 *
	 * @since 1.0.0
	 * @param int $order_id Order ID.
	 * @return array Result with success, modified, results, total, total_discount.
	 */
	public static function fix_single_order( $order_id ) {
		global $wpdb;

		$order = wc_get_order($order_id);

		if (!$order) {
			return array(
				'success' => false,					'message' => sprintf(
						/* translators: %d: order ID */
						esc_html__('Order #%d not found!', 'wdb-discount-builder'),
						$order_id
					),
			);
		}

		$order_coupons = $order->get_coupon_codes();

		if (empty($order_coupons)) {
			return array(
				'success' => false,					'message' => esc_html__('This order has no coupons!', 'wdb-discount-builder'),
			);
		}

		$all_rules               = Wdb_Helpers::get_all_rules();
		$other_products_settings = get_option('wdb_other_products_settings', array());

		$results                = array();
		$order_modified         = false;
		$total_discount_applied = 0;

		// Remove all fees
		$fees = $order->get_fees();
		if (!empty($fees)) {
			foreach ($fees as $fee_id => $fee) {
				$fee_name = strtolower($fee->get_name());

				$should_delete = false;

				foreach ($all_rules as $coupon_code => $rules) {
					if (strpos($fee_name, $coupon_code) !== false || strpos($fee_name, 'تخفیف') !== false) {
						$should_delete = true;
						break;
					}
				}

				if ($should_delete) {
					$wpdb->delete(
						$wpdb->prefix . 'woocommerce_order_items',
						array('order_item_id' => $fee_id),
						array('%d')
					);

					$wpdb->delete(
						$wpdb->prefix . 'woocommerce_order_itemmeta',
						array('order_item_id' => $fee_id),
						array('%d')
					);

					$order_modified = true;
					$results[] = array(
						'item_name' => sprintf(
							/* translators: %s: fee name */
							esc_html__('Fee removed: %s', 'wdb-discount-builder'),
							$fee->get_name()
						),
						'status'    => 'fixed',
						'message'   => esc_html__('Fee removed', 'wdb-discount-builder'),
					);
				}
			}
		}

		// Reset coupon discount
		$order->set_discount_total(0);
		$order->set_discount_tax(0);

		// Apply discount to items
		foreach ($order_coupons as $coupon_code) {
			$coupon_code = strtolower($coupon_code);

			if (!isset($all_rules[$coupon_code])) {
				continue;
			}

			$rules          = $all_rules[$coupon_code];
			$other_settings = isset($other_products_settings[$coupon_code])
				? $other_products_settings[$coupon_code]
				: array('type' => 'none', 'value' => 0);

			$eligible_product_ids = array();
			foreach ($rules as $rule) {
				$eligible_product_ids = array_merge(
					$eligible_product_ids,
					Wdb_Helpers::get_products_by_rule($rule)
				);
			}
			$eligible_product_ids = array_unique(array_map('intval', $eligible_product_ids));

			foreach ($order->get_items() as $item_id => $item) {
				$existing_discount = $item->get_meta('_wdb_discount_amount');
				if ($existing_discount > 0) {
					continue;
				}

				$product_id         = $item->get_product_id();
				$variation_id       = $item->get_variation_id();
				$actual_product_id  = $variation_id ? $variation_id : $product_id;
				$quantity           = $item->get_quantity();

				if (Wdb_Helpers::is_pack_product($actual_product_id)) {
					continue;
				}

				$product = wc_get_product($actual_product_id);
				if (!$product) {
					continue;
				}

				$original_price = $product->get_regular_price();
				if ($product->get_sale_price()) {
					$original_price = $product->get_sale_price();
				}

				$parent_id   = wp_get_post_parent_id($actual_product_id);
				$is_eligible = in_array($actual_product_id, $eligible_product_ids, true) ||
							  ($parent_id && in_array($parent_id, $eligible_product_ids, true));

				$discount_per_item = 0;

				if ($is_eligible) {
					foreach ($rules as $rule) {
						$rule_products = Wdb_Helpers::get_products_by_rule($rule);
						if (empty($rule_products)) {
							continue;
						}

						$product_ids = array_map('intval', $rule_products);
						$is_valid    = in_array($actual_product_id, $product_ids, true) ||
									   ($parent_id && in_array($parent_id, $product_ids, true));

						if ($is_valid) {
							$type  = $rule['type'] ?? 'percent';
							$value = floatval($rule['value'] ?? 0);

							if ($type === 'percent') {
								$discount_per_item = ($original_price * $value) / 100;
							} else {
								$discount_per_item = min($original_price, $value);
							}
							break;
						}
					}
				} else {
					if ($other_settings['type'] !== 'none' && $other_settings['value'] > 0) {
						if ($other_settings['type'] === 'percent') {
							$discount_per_item = ($original_price * $other_settings['value']) / 100;
						} else {
							$discount_per_item = min($original_price, $other_settings['value']);
						}
					}
				}

				if ($discount_per_item > 0) {
					$total_discount = $discount_per_item * $quantity;
					$new_price      = $original_price - $discount_per_item;

					if ($new_price < 0) {
						$new_price = 0;
					}

					$item->set_subtotal($original_price * $quantity);
					$item->set_total($new_price * $quantity);

					$item->update_meta_data('_wdb_discount_amount', $total_discount);
					$item->update_meta_data('_wdb_discount_per_item', $discount_per_item);
					$item->update_meta_data('_wdb_original_price', $original_price);

					$item->save();
					$order_modified = true;
					$total_discount_applied += $total_discount;

					$results[] = array(
						'item_name' => $item->get_name(),
						'status'    => 'fixed',
						'message'   => sprintf(
							/* translators: 1: discount amount, 2: currency */
							esc_html__('Discount: %1$s %2$s', 'wdb-discount-builder'),
							number_format($total_discount, 0),
							esc_html__('Toman', 'wdb-discount-builder')
						),
					);
				}
			}
		}

		if ($order_modified) {
			$items_total = 0;
			foreach ($order->get_items() as $item) {
				$items_total += $item->get_total();
			}

			$shipping_total = $order->get_shipping_total();
			$tax_total      = $order->get_total_tax();

			$new_total = $items_total + $shipping_total + $tax_total;

			$order->set_total($new_total);

			$order->save();

			wc_delete_shop_order_transients($order_id);
		}

		return array(
			'success'        => true,
			'order_id'       => $order_id,
			'modified'       => $order_modified,
			'results'        => $results,
			'total'          => wc_price($order->get_total()),
			'total_discount' => $total_discount_applied,
		);
	}

	/**
	 * Fix a single order and save the discount total to order meta.
	 *
	 * @since 1.0.0
	 * @param int $order_id Order ID.
	 * @return array Result from fix_single_order.
	 */
	public static function fix_single_order_with_discount( $order_id ) {
		$result = self::fix_single_order($order_id);

		if ($result['success'] && $result['modified'] && $result['total_discount'] > 0) {
			Wdb_Discount_Engine::save_discount_total_to_order($order_id, $result['total_discount']);
		}

		return $result;
	}

	/**
	 * Get total number of orders containing a specific coupon.
	 *
	 * @since 1.0.0
	 * @param string $coupon_code Coupon code.
	 * @return int Total order count.
	 */
	public static function get_total_orders_with_coupon( $coupon_code ) {
		global $wpdb;

		$coupon_code = strtolower($coupon_code);

		$count = $wpdb->get_var($wpdb->prepare("
			SELECT COUNT(DISTINCT oi.order_id)
			FROM {$wpdb->prefix}woocommerce_order_items oi
			WHERE oi.order_item_type = 'coupon'
			AND LOWER(oi.order_item_name) = %s
		", $coupon_code));

		return intval($count);
	}

	/**
	 * Get orders containing a specific coupon with pagination.
	 *
	 * @since 1.0.0
	 * @param string $coupon_code Coupon code.
	 * @param int    $offset      Pagination offset.
	 * @param int    $limit       Number of orders per page.
	 * @return array List of WC_Order objects.
	 */
	public static function get_orders_with_coupon( $coupon_code, $offset = 0, $limit = 20 ) {
		global $wpdb;

		$coupon_code = strtolower($coupon_code);

		$order_ids = $wpdb->get_col($wpdb->prepare("
			SELECT DISTINCT oi.order_id
			FROM {$wpdb->prefix}woocommerce_order_items oi
			WHERE oi.order_item_type = 'coupon'
			AND LOWER(oi.order_item_name) = %s
			ORDER BY oi.order_id ASC
			LIMIT %d OFFSET %d
		", $coupon_code, $limit, $offset));

		$orders = array();
		foreach ($order_ids as $order_id) {
			$order = wc_get_order($order_id);
			if ($order) {
				$orders[] = $order;
			}
		}

		return $orders;
	}

	/**
	 * Batch-fix orders containing a specific coupon.
	 *
	 * @since 1.0.0
	 * @param string $coupon_code Coupon code.
	 * @param int    $offset      Starting offset.
	 * @param int    $batch_size  Number of orders to process.
	 * @return array Batch result with progress info.
	 */
	public static function fix_bulk_orders_batch( $coupon_code, $offset = 0, $batch_size = 20 ) {
		$coupon_code = strtolower($coupon_code);
		$all_rules   = Wdb_Helpers::get_all_rules();

		if (!isset($all_rules[$coupon_code])) {
			return array(
				'success' => false,					'message' => esc_html__('Coupon code not found!', 'wdb-discount-builder'),
			);
		}

		$orders = self::get_orders_with_coupon($coupon_code, $offset, $batch_size);

		$fixed_orders  = 0;
		$fixed_items   = 0;
		$processed     = 0;
		$total_discount = 0;

		foreach ($orders as $order) {
			$processed++;

			$result = self::fix_single_order_with_discount($order->get_id());

			if ($result['success'] && $result['modified']) {
				$fixed_orders++;
				$total_discount += $result['total_discount'];
				foreach ($result['results'] as $item_result) {
					if ($item_result['status'] === 'fixed') {
						$fixed_items++;
					}
				}
			}

			unset($result);
		}

		wp_cache_flush();

		return array(
			'success'                 => true,
			'processed'               => $processed,
			'fixed_orders'            => $fixed_orders,
			'fixed_items'             => $fixed_items,
			'total_discount'          => $total_discount,
			'next_offset'             => $offset + $processed,
			'has_more'                => ($processed >= $batch_size),
			'total_orders_with_coupon' => self::get_total_orders_with_coupon($coupon_code),
		);
	}

	/**
	 * Fix all orders containing a specific coupon.
	 *
	 * @since 1.0.0
	 * @param string $coupon_code Coupon code.
	 * @return array Result with counts.
	 */
	public static function fix_bulk_orders( $coupon_code ) {
		$coupon_code = strtolower($coupon_code);
		$all_rules   = Wdb_Helpers::get_all_rules();

		if (!isset($all_rules[$coupon_code])) {
			return array(
				'success' => false,					'message' => sprintf(
						/* translators: %s: coupon code */
						esc_html__('Coupon code %s not found!', 'wdb-discount-builder'),
						$coupon_code
					),
			);
		}

		$fixed_orders   = 0;
		$fixed_items    = 0;
		$skipped_items  = 0;
		$error_items    = 0;
		$total_discount = 0;

		$per_page = 50;
		$page     = 1;

		while (true) {
			$orders = wc_get_orders(array(
				'limit'    => $per_page,
				'page'     => $page,
				'status'   => array_keys(wc_get_order_statuses()),
				'orderby'  => 'date',
				'order'    => 'ASC',
			));

			if (empty($orders)) {
				break;
			}

			foreach ($orders as $order) {
				$order_coupons = array_map('strtolower', $order->get_coupon_codes());

				if (!in_array($coupon_code, $order_coupons)) {
					continue;
				}

				$result = self::fix_single_order_with_discount($order->get_id());

				if ($result['success'] && $result['modified']) {
					$fixed_orders++;
					$total_discount += $result['total_discount'];

					foreach ($result['results'] as $item_result) {
						if ($item_result['status'] === 'fixed') {
							$fixed_items++;
						} elseif ($item_result['status'] === 'skipped') {
							$skipped_items++;
						} else {
							$error_items++;
						}
					}
				}

				unset($result);
				wp_cache_flush();
			}

			if (count($orders) < $per_page) {
				break;
			}

			$page++;
		}

		return array(
			'success'        => true,
			'fixed_orders'   => $fixed_orders,
			'fixed_items'    => $fixed_items,
			'skipped_items'  => $skipped_items,
			'error_items'    => $error_items,
			'total_discount' => $total_discount,
		);
	}

	/**
	 * AJAX: Remove fees from an order.
	 *
	 * @since 1.0.0
	 */
	public function ajax_remove_fees() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Unauthorized access.', 'wdb-discount-builder')));
		}

		global $wpdb;

		$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

		if (!$order_id) {
			wp_send_json_error(array('message' => esc_html__('Invalid order number.', 'wdb-discount-builder')));
		}

		$order = wc_get_order($order_id);
		if (!$order) {
			wp_send_json_error(array('message' => esc_html__('Order not found.', 'wdb-discount-builder')));
		}

		$fees = $order->get_fees();
		$removed_count = 0;
		$removed_names = array();
		$total_fee_amount = 0;

		if (empty($fees)) {
			wp_send_json_success(array(
				'removed_count' => 0,					'message'       => esc_html__('No fees found in this order.', 'wdb-discount-builder'),
			));
		}

		foreach ($fees as $fee_id => $fee) {
			$removed_names[] = $fee->get_name();
			$total_fee_amount += $fee->get_total();

			$wpdb->delete(
				$wpdb->prefix . 'woocommerce_order_items',
				array('order_item_id' => $fee_id),
				array('%d')
			);

			$wpdb->delete(
				$wpdb->prefix . 'woocommerce_order_itemmeta',
				array('order_item_id' => $fee_id),
				array('%d')
			);

			$removed_count++;
		}

		$current_total = $order->get_total();
		$new_total     = $current_total + abs($total_fee_amount);

		$order->set_total($new_total);
		$order->set_discount_total(0);
		$order->set_discount_tax(0);
		$order->save();

		wc_delete_shop_order_transients($order_id);

		wp_send_json_success(array(
			'removed_count' => $removed_count,
			'removed_names' => $removed_names,
			'fee_amount'    => abs($total_fee_amount),
			'total'         => wc_price($new_total),				'message'       => sprintf(
					/* translators: %d: number of fees removed */
					esc_html__('%d fee(s) removed and total corrected.', 'wdb-discount-builder'),
					$removed_count
				),
		));
	}

	/**
	 * AJAX: Fix a single order with discount recalculation.
	 *
	 * @since 1.0.0
	 */
	public function ajax_fix_single_order() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Unauthorized access.', 'wdb-discount-builder')));
		}

		$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

		if (!$order_id) {
			wp_send_json_error(array('message' => esc_html__('Invalid order number.', 'wdb-discount-builder')));
		}

		$result = self::fix_single_order_with_discount($order_id);

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	/**
	 * AJAX: Fix bulk orders.
	 *
	 * @since 1.0.0
	 */
	public function ajax_fix_bulk_orders() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Unauthorized access.', 'wdb-discount-builder')));
		}

		set_time_limit(0);
		ini_set('memory_limit', '512M');

		$coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';

		if (empty($coupon_code)) {
			wp_send_json_error(array('message' => esc_html__('Invalid coupon code.', 'wdb-discount-builder')));
		}

		$result = self::fix_bulk_orders($coupon_code);

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	/**
	 * AJAX: Get total count for bulk fix.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_bulk_count() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Unauthorized access.', 'wdb-discount-builder')));
		}

		$coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');

		if (empty($coupon_code)) {
			wp_send_json_error(array('message' => esc_html__('Invalid coupon code.', 'wdb-discount-builder')));
		}

		$total = self::get_total_orders_with_coupon($coupon_code);

		wp_send_json_success(array('total' => $total));
	}

	/**
	 * AJAX: Process batch for bulk fix.
	 *
	 * @since 1.0.0
	 */
	public function ajax_fix_bulk_orders_batch() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Unauthorized access.', 'wdb-discount-builder')));
		}

		set_time_limit(0);
		ini_set('memory_limit', '256M');

		$coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
		$offset      = intval($_POST['offset'] ?? 0);
		$batch_size  = intval($_POST['batch_size'] ?? 20);

		if (empty($coupon_code)) {
			wp_send_json_error(array('message' => esc_html__('Invalid coupon code.', 'wdb-discount-builder')));
		}

		$result = self::fix_bulk_orders_batch($coupon_code, $offset, $batch_size);

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	/**
	 * AJAX: Get bulk fix progress.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_bulk_progress() {
		$progress = get_option('wdb_bulk_fix_progress', array(
			'coupon_code'    => '',
			'offset'         => 0,
			'total_orders'   => 0,
			'fixed_orders'   => 0,
			'fixed_items'    => 0,
			'total_discount' => 0,
			'has_more'       => false,
		));

		wp_send_json_success($progress);
	}

	/**
	 * AJAX: Reset bulk fix progress.
	 *
	 * @since 1.0.0
	 */
	public function ajax_reset_bulk_progress() {
		delete_option('wdb_bulk_fix_progress');			wp_send_json_success(array('message' => esc_html__('Progress has been reset.', 'wdb-discount-builder')));
	}
}
