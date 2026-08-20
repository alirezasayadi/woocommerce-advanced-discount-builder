<?php
/**
 * Shared helper functions used across the plugin.
 *
 * Centralizes coupon rule lookups, product eligibility checks,
 * discount calculations, and common utility functions.
 *
 * @since      1.0.0
 * @package    Wdb_Discount_Builder
 * @subpackage Wdb_Discount_Builder/includes
 * @author     Alireza Sayadi <https://github.com/alirezasayadi>
 */

defined('ABSPATH') || exit;

/**
 * Core helper functions for rule lookups, product eligibility, and discount math.
 */
class Wdb_Helpers {

	/**
	 * Get all coupon rules from the database.
	 *
	 * Supports both multi-coupon and legacy single-coupon option formats.
	 *
	 * @since 1.0.0
	 * @return array Keyed by lowercase coupon code => array of rules.
	 */
	public static function get_all_rules() {
		$all_rules = array();

		$coupon_rules = get_option('wdb_coupon_rules', array());
		foreach ($coupon_rules as $data) {
			$code = isset($data['code']) ? strtolower($data['code']) : '';
			if (!empty($code)) {
				$all_rules[$code] = isset($data['rules']) ? $data['rules'] : array();
			}
		}

		// Legacy single-coupon support
		$old_coupon_code = get_option('wdb_coupon_code', '');
		$old_rules      = get_option('wdb_rules', array());
		if ($old_coupon_code && !empty($old_rules)) {
			$all_rules[strtolower($old_coupon_code)] = $old_rules;
		}

		return $all_rules;
	}

	/**
	 * Get product IDs eligible for a given rule.
	 *
	 * Resolves product IDs from direct product lists, categories, and brands.
	 *
	 * @since 1.0.0
	 * @param array $rule Rule array with products, categories, brands keys.
	 * @return array Unique int product IDs.
	 */
	public static function get_products_by_rule( $rule ) {
		$product_ids = array();

		if (!empty($rule['products'])) {
			$product_ids = array_merge($product_ids, $rule['products']);
		}

		if (!empty($rule['categories'])) {
			foreach ($rule['categories'] as $cat_id) {
				$products = get_posts(array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'term_id',
							'terms'    => $cat_id,
						),
					),
					'fields' => 'ids',
				));
				$product_ids = array_merge($product_ids, $products);
			}
		}

		if (!empty($rule['brands'])) {
			$brand_taxonomies = array(
				'product_brand',
				'pwb-brand',
				'yith_product_brand',
				'brand',
				'product-brand',
			);

			foreach ($brand_taxonomies as $taxonomy) {
				if (taxonomy_exists($taxonomy)) {
					$products = get_posts(array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'tax_query'      => array(
							array(
								'taxonomy' => $taxonomy,
								'field'    => 'term_id',
								'terms'    => $rule['brands'],
							),
						),
						'fields' => 'ids',
					));
					$product_ids = array_merge($product_ids, $products);
				}
			}
		}

		return array_unique(array_map('intval', $product_ids));
	}

	/**
	 * Check if a product belongs to the "pack" category.
	 *
	 * @since 1.0.0
	 * @param int $product_id Product or variation ID.
	 * @return bool
	 */
	public static function is_pack_product( $product_id ) {
		if (has_term('pack', 'product_cat', $product_id)) {
			return true;
		}

		$parent_id = wp_get_post_parent_id($product_id);
		if ($parent_id && has_term('pack', 'product_cat', $parent_id)) {
			return true;
		}

		return false;
	}

	/**
	 * Get all eligible product IDs for a coupon code.
	 *
	 * @since 1.0.0
	 * @param string $coupon_code Coupon code.
	 * @param array  $all_rules   All coupon rules.
	 * @return array Unique int product IDs.
	 */
	public static function get_eligible_product_ids( $coupon_code, $all_rules ) {
		if (!isset($all_rules[$coupon_code])) {
			return array();
		}

		$rules = $all_rules[$coupon_code];
		if (empty($rules)) {
			return array();
		}

		$ids = array();
		foreach ($rules as $rule) {
			$ids = array_merge($ids, self::get_products_by_rule($rule));
		}

		return array_unique(array_map('intval', $ids));
	}

	/**
	 * Check if a product is eligible given a list of eligible IDs.
	 *
	 * Also checks the parent product for variations.
	 *
	 * @since 1.0.0
	 * @param int   $product_id          Product or variation ID.
	 * @param array $eligible_product_ids Eligible product IDs.
	 * @return bool
	 */
	public static function is_product_eligible( $product_id, $eligible_product_ids ) {
		if (in_array($product_id, $eligible_product_ids, true)) {
			return true;
		}

		$parent_id = wp_get_post_parent_id($product_id);
		if ($parent_id && in_array($parent_id, $eligible_product_ids, true)) {
			return true;
		}

		return false;
	}

	/**
	 * Calculate the discount amount for a product.
	 *
	 * @since 1.0.0
	 * @param float  $original_price Base price.
	 * @param string $type           'percent' or 'fixed'.
	 * @param float  $value          Discount value.
	 * @return float Discount amount.
	 */
	public static function calculate_discount_amount( $original_price, $type, $value ) {
		if ($type === 'percent') {
			return ($original_price * $value) / 100;
		}

		return min($original_price, $value);
	}

	/**
	 * Get the discount info for a specific product under a coupon.
	 *
	 * @since 1.0.0
	 * @param int    $product_id     Product or variation ID.
	 * @param array  $all_rules      All coupon rules.
	 * @param string $coupon_code    Coupon code.
	 * @param float  $original_price Base price.
	 * @return array { amount: float, type_text: string }
	 */
	public static function get_discount_for_product( $product_id, $all_rules, $coupon_code, $original_price ) {
		$result = array(
			'amount'    => 0,
			'type_text' => '',
		);

		$rules = isset($all_rules[$coupon_code]) ? $all_rules[$coupon_code] : array();
		if (empty($rules)) {
			return $result;
		}

		$eligible_ids = self::get_eligible_product_ids($coupon_code, $all_rules);
		$is_eligible  = self::is_product_eligible($product_id, $eligible_ids);

		$other_products_settings = get_option('wdb_other_products_settings', array());
		$other_settings = isset($other_products_settings[$coupon_code])
			? $other_products_settings[$coupon_code]
			: array('type' => 'none', 'value' => 0);

		if ($is_eligible) {
			foreach ($rules as $rule) {
				$rule_products = self::get_products_by_rule($rule);
				if (empty($rule_products)) {
					continue;
				}

				$product_ids = array_map('intval', $rule_products);
				$parent_id   = wp_get_post_parent_id($product_id);

				if (in_array($product_id, $product_ids, true) ||
					($parent_id && in_array($parent_id, $product_ids, true))) {

					$type  = isset($rule['type']) ? $rule['type'] : 'percent';
					$value = floatval(isset($rule['value']) ? $rule['value'] : 0);
					$result['amount'] = self::calculate_discount_amount($original_price, $type, $value);

					if ($type === 'percent') {
						$result['type_text'] = $value . '% تخفیف';
					} else {
						$result['type_text'] = number_format($value, 0) . ' تومان تخفیف';
					}

					break;
				}
			}
		} elseif ($other_settings['type'] !== 'none' && $other_settings['value'] > 0) {
			$result['amount'] = self::calculate_discount_amount(
				$original_price,
				$other_settings['type'],
				$other_settings['value']
			);

			if ($other_settings['type'] === 'percent') {
				$result['type_text'] = $other_settings['value'] . '% تخفیف';
			} else {
				$result['type_text'] = number_format($other_settings['value'], 0) . ' تومان تخفیف';
			}
		}

		return $result;
	}

	/**
	 * Get the base price for discount calculation.
	 *
	 * Returns sale price if available, otherwise regular price.
	 *
	 * @since 1.0.0
	 * @param object $product WC_Product instance.
	 * @return float
	 */
	public static function get_base_price( $product ) {
		$sale_price = $product->get_sale_price();
		if ($sale_price !== '' && $sale_price !== false) {
			return floatval($sale_price);
		}

		return floatval($product->get_regular_price());
	}

	/**
	 * Get "other products" discount settings for a coupon.
	 *
	 * @since 1.0.0
	 * @param string $coupon_code Coupon code.
	 * @return array { type: string, value: float }
	 */
	public static function get_other_products_settings( $coupon_code ) {
		$all_settings = get_option('wdb_other_products_settings', array());

		return isset($all_settings[$coupon_code])
			? $all_settings[$coupon_code]
			: array('type' => 'none', 'value' => 0);
	}

	/**
	 * Get free shipping rules mapping.
	 *
	 * @since 1.0.0
	 * @return array Keyed by coupon code => 1.
	 */
	public static function get_free_shipping_rules() {
		return get_option('wdb_free_shipping_rules', array());
	}

	/**
	 * Check if WooCommerce cart and session are ready.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function cart_ready() {
		return function_exists('WC') && WC()->cart && WC()->session;
	}
}
