<?php
/**
 * Plugin Name: Advanced WooCommerce discount code generator
 * Description: An advanced WordPress/WooCommerce plugin for creating flexible discount coupons with product, category, brand, and shipping-based rules.
 * Version: 3.0.0
 * Author: Alireza Sayadi
 * Author URI: https://github.com/alirezasayadi
 * Text Domain: wdb-discount-builder
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.0
 * WC requires at least: 7.0
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') || exit;

define('WDBP_VERSION', '3.0.0');
define('WDBP_URL', plugin_dir_url(__FILE__));
define('WDBP_PATH', plugin_dir_path(__FILE__));

/**
 * Load plugin text domain for translations.
 */
add_action('plugins_loaded', 'wdb_load_textdomain');

function wdb_load_textdomain() {
	load_plugin_textdomain('wdb-discount-builder', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Load the main bootstrap class
require_once WDBP_PATH . 'includes/class-wdb-discount-builder.php';

/**
 * Initialize the plugin after all plugins are loaded.
 */
add_action('plugins_loaded', 'wdb_init');

function wdb_init() {
	Wdb_Discount_Builder::instance()->init();
}
