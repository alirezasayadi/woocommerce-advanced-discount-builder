<?php
/**
 * Main plugin bootstrap class.
 *
 * Loads all plugin components and initializes the plugin
 * after WooCommerce is confirmed to be active.
 *
 * @since      1.0.0
 * @package    Wdb_Discount_Builder
 * @subpackage Wdb_Discount_Builder/includes
 * @author     Alireza Sayadi <https://github.com/alirezasayadi>
 */

defined('ABSPATH') || exit;

/**
 * Main plugin bootstrap: loads dependencies and initializes components.
 */
class Wdb_Discount_Builder {

	/**
	 * The single instance of the plugin.
	 *
	 * @since 1.0.0
	 * @var Wdb_Discount_Builder|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 * @return Wdb_Discount_Builder
	 */
	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * Loads all class files and registers component hooks.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		if (!class_exists('WooCommerce')) {
			add_action('admin_notices', array($this, 'missing_woocommerce_notice'));
			return;
		}

		$this->load_dependencies();

		// Initialize components
		$coupon_manager = new Wdb_Coupon_Manager();
		$coupon_manager->register();

		$discount_engine = new Wdb_Discount_Engine();
		$discount_engine->register();

		$free_shipping = new Wdb_Free_Shipping();
		$free_shipping->register();

		$public = new Wdb_Public();
		$public->register();

		$admin = new Wdb_Admin();
		$admin->register();

		$ajax = new Wdb_Ajax();
		$ajax->register();

		$order_fixer = new Wdb_Order_Fixer();
		$order_fixer->register();
	}

	/**
	 * Load all class files.
	 *
	 * @since 1.0.0
	 */
	private function load_dependencies() {
		// Shared infrastructure
		require_once WDBP_PATH . 'includes/class-wdb-helpers.php';

		// Coupon management
		require_once WDBP_PATH . 'coupons/class-wdb-coupon-manager.php';

		// Discount calculation engine
		require_once WDBP_PATH . 'discounts/class-wdb-discount-engine.php';

		// Free shipping
		require_once WDBP_PATH . 'shipping/class-wdb-free-shipping.php';

		// Frontend display
		require_once WDBP_PATH . 'public/class-wdb-public.php';

		// Admin UI
		require_once WDBP_PATH . 'admin/class-wdb-admin.php';

		// AJAX endpoints
		require_once WDBP_PATH . 'ajax/class-wdb-ajax.php';

		// Order correction tools
		require_once WDBP_PATH . 'orders/class-wdb-order-fixer.php';
	}

	/**
	 * Display admin notice when WooCommerce is not active.
	 *
	 * @since 1.0.0
	 */
	public function missing_woocommerce_notice() {
		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e('Advanced WooCommerce Discount Builder', 'wdb-discount-builder');
		echo '</strong> ';
		esc_html_e('requires WooCommerce to be installed and activated.', 'wdb-discount-builder');
		echo '</p></div>';
	}
}
