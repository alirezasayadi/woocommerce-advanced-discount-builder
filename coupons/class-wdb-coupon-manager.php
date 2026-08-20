<?php
/**
 * Coupon management and brand utility functions.
 *
 * Handles creating, updating, and verifying WooCommerce coupons
 * managed by the plugin. Also provides brand taxonomy lookups
 * for product_brand, pwb-brand, and yith_product_brand taxonomies.
 *
 * @since      1.0.0
 * @package    Wdb_Discount_Builder
 * @subpackage Wdb_Discount_Builder/coupons
 * @author     Alireza Sayadi <https://github.com/alirezasayadi>
 */

defined('ABSPATH') || exit;

/**
 * Manages WooCommerce coupon CRUD and brand taxonomy lookups.
 */
class Wdb_Coupon_Manager {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		add_action('admin_init', array($this, 'check_integrity_on_load'));
	}

	/**
	 * Create or restore a WooCommerce coupon for the given code.
	 *
	 * Sets the coupon to fixed_cart type with zero amount (the plugin
	 * applies its own discounts), and syncs the free shipping flag
	 * from plugin options.
	 *
	 * @since 1.0.0
	 * @param string $coupon_code Coupon code (will be lowercased).
	 */
	public static function create_or_update_coupon( $coupon_code ) {
		$coupon_code = strtolower($coupon_code);
		$coupon_id   = wc_get_coupon_id_by_code($coupon_code);

		if (!$coupon_id) {
			$coupon = new WC_Coupon();
			$coupon->set_code($coupon_code);
		} else {
			$coupon = new WC_Coupon($coupon_id);
			if ($coupon->get_status() === 'trash') {
				wp_untrash_post($coupon_id);
				$coupon = new WC_Coupon($coupon_id);
			}
		}

		$free_shipping_rules = get_option('wdb_free_shipping_rules', array());
		$free_shipping       = isset($free_shipping_rules[$coupon_code]) ? (bool) $free_shipping_rules[$coupon_code] : false;

		$coupon->set_discount_type('fixed_cart');
		$coupon->set_amount(0);
		$coupon->set_individual_use(true);
		$coupon->set_free_shipping($free_shipping);
		$coupon->set_usage_limit(0);
		$coupon->set_usage_limit_per_user(0);
		$coupon->set_description(
			esc_html__('Created by Advanced WooCommerce Discount Builder', 'wdb-discount-builder')
		);
		$coupon->set_status('publish');
		$coupon->save();
	}

	/**
	 * Verify that all coupon rules have corresponding WooCommerce coupons.
	 *
	 * Creates missing coupons and restores trashed ones.
	 *
	 * @since 1.0.0
	 * @return bool Whether any updates were made.
	 */
	public static function check_integrity() {
		$coupon_rules = get_option('wdb_coupon_rules', array());
		$needs_update = false;

		foreach ($coupon_rules as $coupon_code => $data) {
			$coupon_code = strtolower($coupon_code);
			$coupon_id   = wc_get_coupon_id_by_code($coupon_code);

			if (!$coupon_id) {
				self::create_or_update_coupon($coupon_code);
				$needs_update = true;
			} else {
				$coupon = new WC_Coupon($coupon_id);
				if ($coupon->get_status() === 'trash') {
					wp_untrash_post($coupon_id);
					$needs_update = true;
				}
			}
		}

		return $needs_update;
	}

	/**
	 * Run coupon integrity check when the plugin admin page is visited.
	 *
	 * @since 1.0.0
	 */
	public function check_integrity_on_load() {
		if (isset($_GET['page']) && $_GET['page'] === 'wdb-pro') {
			self::check_integrity();
		}
	}

	/**
	 * Get a brand by its term ID.
	 *
	 * Searches across all known brand taxonomies.
	 *
	 * @since 1.0.0
	 * @param int $brand_id Brand term ID.
	 * @return array|false Brand data array with id, name, slug or false.
	 */
	public static function get_brand_by_id( $brand_id ) {
		$brands = self::get_all_brands();
		foreach ($brands as $brand) {
			if ($brand['id'] == $brand_id) {
				return $brand;
			}
		}
		return false;
	}

	/**
	 * Get all brands from all known brand taxonomies.
	 *
	 * Supports product_brand, pwb-brand, and yith_product_brand.
	 *
	 * @since 1.0.0
	 * @return array List of brand arrays with id, name, slug.
	 */
	public static function get_all_brands() {
		$brands = array();

		if (taxonomy_exists('product_brand')) {
			$terms = get_terms(array(
				'taxonomy'   => 'product_brand',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			));
			if (!is_wp_error($terms)) {
				foreach ($terms as $term) {
					$brands[$term->term_id] = array(
						'id'   => $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					);
				}
			}
		}

		if (taxonomy_exists('pwb-brand')) {
			$terms = get_terms(array(
				'taxonomy'   => 'pwb-brand',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			));
			if (!is_wp_error($terms)) {
				foreach ($terms as $term) {
					if (!isset($brands[$term->term_id])) {
						$brands[$term->term_id] = array(
							'id'   => $term->term_id,
							'name' => $term->name,
							'slug' => $term->slug,
						);
					}
				}
			}
		}

		if (taxonomy_exists('yith_product_brand')) {
			$terms = get_terms(array(
				'taxonomy'   => 'yith_product_brand',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			));
			if (!is_wp_error($terms)) {
				foreach ($terms as $term) {
					if (!isset($brands[$term->term_id])) {
						$brands[$term->term_id] = array(
							'id'   => $term->term_id,
							'name' => $term->name,
							'slug' => $term->slug,
						);
					}
				}
			}
		}

		return array_values($brands);
	}
}
