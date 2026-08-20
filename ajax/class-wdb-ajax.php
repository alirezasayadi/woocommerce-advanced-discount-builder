<?php
/**
 * AJAX handler classes for admin and frontend.
 *
 * Provides live search endpoints for products, categories, and brands
 * used by the admin coupon editor, plus the frontend coupon removal endpoint.
 *
 * @since      1.0.0
 * @package    Wdb_Discount_Builder
 * @subpackage Wdb_Discount_Builder/ajax
 * @author     Alireza Sayadi <https://github.com/alirezasayadi>
 */

defined('ABSPATH') || exit;

/**
 * AJAX endpoints for admin search and frontend coupon removal.
 */
class Wdb_Ajax {

	/**
	 * Register all AJAX hooks.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Admin search endpoints
		add_action('wp_ajax_wdb_search_products', array($this, 'search_products'));
		add_action('wp_ajax_wdb_search_categories', array($this, 'search_categories'));
		add_action('wp_ajax_wdb_search_brands', array($this, 'search_brands'));

		// Frontend coupon removal
		add_action('wp_ajax_wdb_remove_coupon', array($this, 'remove_coupon'));
		add_action('wp_ajax_nopriv_wdb_remove_coupon', array($this, 'remove_coupon'));
	}

	/**
	 * AJAX: Search products for admin coupon editor.
	 *
	 * @since 1.0.0
	 */
	public function search_products() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(
				esc_html__('Unauthorized access.', 'wdb-discount-builder'),
				403
			);
		}

		if (!isset($_GET['q'])) {
			wp_send_json(array());
		}

		$term = sanitize_text_field(wp_unslash($_GET['q']));

		if (strlen($term) < 2) {
			wp_send_json(array());
		}

		$query = new WP_Query(array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			's'              => $term,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		));

		$results = array();

		while ($query->have_posts()) {
			$query->the_post();

			$results[] = array(
				'id'   => get_the_ID(),
				'text' => get_the_title(),
			);
		}

		wp_reset_postdata();

		wp_send_json($results);
	}

	/**
	 * AJAX: Search categories for admin coupon editor.
	 *
	 * @since 1.0.0
	 */
	public function search_categories() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(
				esc_html__('Unauthorized access.', 'wdb-discount-builder'),
				403
			);
		}

		if (!isset($_GET['q'])) {
			wp_send_json(array());
		}

		$term = sanitize_text_field(wp_unslash($_GET['q']));

		if (strlen($term) < 2) {
			wp_send_json(array());
		}

		$terms = get_terms(array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'name__like' => $term,
			'number'     => 10,
		));

		$results = array();

		if (!is_wp_error($terms)) {
			foreach ($terms as $term) {
				$results[] = array(
					'id'   => $term->term_id,
					'text' => $term->name . ' (' . $term->count . ' ' . esc_html__('products', 'wdb-discount-builder') . ')',
				);
			}
		}

		wp_send_json($results);
	}

	/**
	 * AJAX: Search brands for admin coupon editor.
	 *
	 * @since 1.0.0
	 */
	public function search_brands() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(
				esc_html__('Unauthorized access.', 'wdb-discount-builder'),
				403
			);
		}

		if (!isset($_GET['q'])) {
			wp_send_json(array());
		}

		$term = sanitize_text_field(wp_unslash($_GET['q']));

		if (strlen($term) < 2) {
			wp_send_json(array());
		}

		$all_brands = Wdb_Coupon_Manager::get_all_brands();
		$results    = array();

		foreach ($all_brands as $brand) {
			if (stripos($brand['name'], $term) !== false || stripos($brand['slug'], $term) !== false) {
				$results[] = array(
					'id'   => $brand['id'],
					'text' => $brand['name'],
				);
			}
			if (count($results) >= 10) {
				break;
			}
		}

		wp_send_json($results);
	}

	/**
	 * AJAX: Remove a coupon from the cart.
	 *
	 * Uses WooCommerce's native remove_coupon() method for proper cleanup.
	 *
	 * @since 1.0.0
	 */
	public function remove_coupon() {
		check_ajax_referer('wdb_remove_coupon_nonce', 'nonce');

		if (!function_exists('WC') || !WC()->cart) {
			wp_send_json_error(array('message' => 'Cart not available.'));
		}

		$coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';
		if (empty($coupon_code)) {
			wp_send_json_error(array('message' => 'No coupon code provided.'));
		}

		$applied = WC()->cart->get_applied_coupons();
		$found   = false;
		foreach ($applied as $code) {
			if (strtolower($code) === strtolower($coupon_code)) {
				$found = true;
				break;
			}
		}

		if (!$found) {
			wp_send_json_error(array('message' => 'Coupon is not applied to the cart.'));
		}

		WC()->cart->remove_coupon($coupon_code);
		WC()->cart->calculate_totals();

		$message = __('Coupon removed successfully.', 'wdb-discount-builder');
		wc_add_notice($message, 'success');

		wp_send_json_success(array(
			'message'     => $message,
			'coupon_code' => $coupon_code,
		));
	}
}
