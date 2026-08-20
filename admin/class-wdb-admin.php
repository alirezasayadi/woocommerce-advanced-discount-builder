<?php
/**
 * Admin page UI and admin-side display functionality.
 *
 * Renders the plugin's admin settings page for creating/editing
 * coupon rules, handles form submissions, and displays discount
 * information in admin order views.
 *
 * @since      1.0.0
 * @package    Wdb_Discount_Builder
 * @subpackage Wdb_Discount_Builder/admin
 * @author     Alireza Sayadi <https://github.com/alirezasayadi>
 */

defined('ABSPATH') || exit;

/**
 * Admin controller: menu, page rendering, form handling, admin display, assets.
 */
class Wdb_Admin {

	/**
	 * Register all admin hooks.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		add_action('admin_menu', array($this, 'add_menu_page'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

		// Admin order display
		add_action('woocommerce_before_order_itemmeta', array($this, 'display_item_discount_in_admin'), 10, 3);
		add_action('woocommerce_admin_order_totals_after_discount', array($this, 'display_discount_row_in_admin'), 10, 1);
		add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_free_shipping_status_in_admin'));
	}

	/**
	 * Register the admin menu page.
	 *
	 * @since 1.0.0
	 */
	public function add_menu_page() {
		add_menu_page(
			esc_html__('Advanced Discount Builder', 'wdb-discount-builder'),
			esc_html__('Advanced Discount Builder', 'wdb-discount-builder'),
			'manage_options',
			'wdb-pro',
			array($this, 'render_page'),
			'dashicons-tickets-alt',
			56
		);
	}

	/**
	 * Enqueue admin assets on the plugin page only.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ($hook !== 'toplevel_page_wdb-pro') {
			return;
		}

		wp_enqueue_style('wdbp-admin-css', WDBP_URL . 'assets/admin/admin.css', array(), WDBP_VERSION);
		wp_enqueue_script('wdbp-admin-js', WDBP_URL . 'assets/admin/admin.js', array('jquery', 'jquery-ui-sortable'), WDBP_VERSION, true);
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');

		wp_localize_script('wdbp-admin-js', 'wdbp_ajax', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('wdbp_ajax_nonce'),
		));
	}

	/**
	 * Render the admin settings page.
	 *
	 * Handles form save and delete operations, then renders the page.
	 *
	 * @since 1.0.0
	 */
	public function render_page() {
		if (isset($_POST['save'])) {
			$this->handle_save();
		}

		if (isset($_GET['saved'])) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'wdb-discount-builder') . '</p></div>';
		}

		if (isset($_GET['delete_coupon']) && check_admin_referer('wdb_delete_coupon')) {
			$this->handle_delete_coupon(sanitize_text_field($_GET['delete_coupon']));
		}

		$coupon_rules = get_option('wdb_coupon_rules', array());

		// Migrate legacy single-coupon format
		$old_coupon_code = get_option('wdb_coupon_code');
		$old_rules       = get_option('wdb_rules');

		if ($old_coupon_code && !empty($old_rules) && empty($coupon_rules)) {
			$coupon_rules[$old_coupon_code] = array(
				'code'  => $old_coupon_code,
				'rules' => $old_rules,
			);
			update_option('wdb_coupon_rules', $coupon_rules);
			delete_option('wdb_coupon_code');
			delete_option('wdb_rules');
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Advanced WooCommerce Discount Builder', 'wdb-discount-builder'); ?></h1>
			<p style="color:#666;font-size:14px;"><?php esc_html_e('Define discount codes and the rules for each code.', 'wdb-discount-builder'); ?></p>

			<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=wdb-pro')); ?>" id="wdb-main-form">
				<?php wp_nonce_field('wdb_save'); ?>

				<div id="wdb-coupons-container">
					<?php if (!empty($coupon_rules)): ?>
						<?php
						$counter = 0;
						foreach ($coupon_rules as $coupon_code => $coupon_data):
						?>
							<?php $this->render_coupon_box($coupon_code, $coupon_data['rules'] ?? array(), $counter); ?>
						<?php
							$counter++;
						endforeach;
						?>
					<?php endif; ?>
				</div>

				<p>
					<button type="button" id="add-coupon" class="button button-secondary">
						<?php esc_html_e('+ Add New Discount Code', 'wdb-discount-builder'); ?>
					</button>
				</p>

				<p>
					<input type="submit"
						   name="save"
						   class="button button-primary"
						   value="<?php esc_attr_e('Save All Settings', 'wdb-discount-builder'); ?>">
				</p>
			</form>

			<?php Wdb_Order_Fixer::render_tool(); ?>
		</div>
		<?php
	}

	/**
	 * Handle form save submission.
	 *
	 * @since 1.0.0
	 */
	private function handle_save() {
		check_admin_referer('wdb_save');

		$old_coupon_rules = get_option('wdb_coupon_rules', array());
		$coupon_rules     = array();

		if (!empty($_POST['coupon_rules']) && is_array($_POST['coupon_rules'])) {
			foreach ($_POST['coupon_rules'] as $coupon_data) {
				$new_coupon_code = strtolower(sanitize_text_field($coupon_data['code'] ?? ''));

				if (empty($new_coupon_code)) {
					continue;
				}

				$rules = array();
				if (!empty($coupon_data['rules']) && is_array($coupon_data['rules'])) {
					foreach ($coupon_data['rules'] as $rule) {
						$rule_data = array(
							'type'       => sanitize_text_field($rule['type'] ?? 'percent'),
							'value'      => floatval($rule['value'] ?? 0),
							'products'   => isset($rule['products']) ? array_map('intval', $rule['products']) : array(),
							'categories' => isset($rule['categories']) ? array_map('intval', $rule['categories']) : array(),
							'brands'     => isset($rule['brands']) ? array_map('intval', $rule['brands']) : array(),
						);

						if (empty($rule_data['products']) && empty($rule_data['categories']) && empty($rule_data['brands'])) {
							continue;
						}

						$rules[] = $rule_data;
					}
				}

				$coupon_rules[$new_coupon_code] = array(
					'code'  => $new_coupon_code,
					'rules' => $rules,
				);

				Wdb_Coupon_Manager::create_or_update_coupon($new_coupon_code);
			}
		}

		// Trash deleted coupons
		$old_codes    = array_keys($old_coupon_rules);
		$new_codes    = array_keys($coupon_rules);
		$deleted_codes = array_diff($old_codes, $new_codes);

		foreach ($deleted_codes as $deleted_code) {
			$coupon_id = wc_get_coupon_id_by_code($deleted_code);
			if ($coupon_id) {
				wp_trash_post($coupon_id);
			}
		}

		update_option('wdb_coupon_rules', $coupon_rules);

		// Save "other products" settings
		$other_products_settings = array();
		if (!empty($_POST['other_products']) && is_array($_POST['other_products'])) {
			foreach ($_POST['other_products'] as $index => $data) {
				$coupon_code = isset($_POST['coupon_rules'][$index]['code'])
					? strtolower(sanitize_text_field($_POST['coupon_rules'][$index]['code']))
					: '';
				if (!empty($coupon_code)) {
					$other_products_settings[$coupon_code] = array(
						'type'  => sanitize_text_field($data['type'] ?? 'none'),
						'value' => floatval($data['value'] ?? 0),
					);
				}
			}
		}
		update_option('wdb_other_products_settings', $other_products_settings);

		// Save free shipping settings
		$free_shipping_rules = array();
		if (!empty($_POST['free_shipping']) && is_array($_POST['free_shipping'])) {
			foreach ($_POST['free_shipping'] as $index => $value) {
				$coupon_code = isset($_POST['coupon_rules'][$index]['code'])
					? strtolower(sanitize_text_field($_POST['coupon_rules'][$index]['code']))
					: '';
				if (!empty($coupon_code)) {
					$free_shipping_rules[$coupon_code] = 1;
				}
			}
		}
		update_option('wdb_free_shipping_rules', $free_shipping_rules);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'wdb-pro',
					'saved' => 1,
				),
				admin_url('admin.php')
			)
		);
		exit;
	}

	/**
	 * Handle coupon deletion.
	 *
	 * @since 1.0.0
	 * @param string $coupon_code Coupon code to delete.
	 */
	private function handle_delete_coupon( $coupon_code ) {
		$coupon_rules = get_option('wdb_coupon_rules', array());

		if (isset($coupon_rules[$coupon_code])) {
			unset($coupon_rules[$coupon_code]);
			update_option('wdb_coupon_rules', $coupon_rules);

			$coupon_id = wc_get_coupon_id_by_code($coupon_code);
			if ($coupon_id) {
				wp_trash_post($coupon_id);
			}

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Discount code deleted successfully.', 'wdb-discount-builder') . '</p></div>';
		}
	}

	/**
	 * Render a coupon box in the admin form.
	 *
	 * @since 1.0.0
	 * @param string $coupon_code Coupon code.
	 * @param array  $rules       Coupon rules.
	 * @param int    $index       Box index.
	 */
	private function render_coupon_box( $coupon_code, $rules = array(), $index = 0 ) {
		$coupon_code = strtolower($coupon_code);

		$other_products_settings = get_option('wdb_other_products_settings', array());
		$other_settings = isset($other_products_settings[$coupon_code])
			? $other_products_settings[$coupon_code]
			: array('type' => 'none', 'value' => 0);

		$free_shipping_rules   = get_option('wdb_free_shipping_rules', array());
		$free_shipping_enabled = isset($free_shipping_rules[$coupon_code]) ? (bool) $free_shipping_rules[$coupon_code] : false;

		$coupon_id = wc_get_coupon_id_by_code($coupon_code);
		$coupon_status = '';
		$status_color  = '';

		if ($coupon_id) {
			$coupon = new WC_Coupon($coupon_id);
			$status = $coupon->get_status();
			if ($status === 'publish') {
				$coupon_status = esc_html__('Active', 'wdb-discount-builder');
				$status_color  = 'green';
			} elseif ($status === 'trash') {
				$coupon_status = esc_html__('Trashed', 'wdb-discount-builder');
				$status_color  = 'red';
			} else {
				$coupon_status = $status;
				$status_color  = 'orange';
			}
		} else {
			$coupon_status = esc_html__('Does not exist', 'wdb-discount-builder');
			$status_color  = 'red';
		}
		?>
		<div class="coupon-box" data-coupon-index="<?php echo $index; ?>">
			<div class="coupon-box-header">
				<span class="coupon-box-title">
					<?php esc_html_e('Discount Code:', 'wdb-discount-builder'); ?>
					<input type="text"
						   name="coupon_rules[<?php echo $index; ?>][code]"
						   class="coupon-code-input"
						   value="<?php echo esc_attr($coupon_code); ?>"
						   placeholder="<?php esc_attr_e('Example: SUMMER2026', 'wdb-discount-builder'); ?>">
					<span style="font-size:12px;color:<?php echo $status_color; ?>;margin-right:10px;">
						<?php
						printf(
							/* translators: %s: coupon status */
							esc_html__('Status: %s', 'wdb-discount-builder'),
							$coupon_status
						);
						?>
					</span>
				</span>
				<span class="coupon-actions">
					<button type="button" class="button add-rule-to-coupon"><?php esc_html_e('+ Add Rule', 'wdb-discount-builder'); ?></button>
					<a href="<?php echo wp_nonce_url(add_query_arg('delete_coupon', $coupon_code), 'wdb_delete_coupon'); ?>"
					   class="delete-coupon-link"
					   onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this discount code?', 'wdb-discount-builder'); ?>');">
					   <?php esc_html_e('Delete Code', 'wdb-discount-builder'); ?>
					</a>
				</span>
			</div>

			<div class="other-products-section" style="background:#f5f9ff;padding:15px;border-radius:4px;margin-bottom:15px;border:1px solid #d1e0f0;">
				<h4 style="margin:0 0 10px 0;color:#0066a0;"><?php esc_html_e('Discount on Other Products (Non-eligible)', 'wdb-discount-builder'); ?></h4>
				<div style="margin-top:5px;">
					<label style="margin-right:15px;">
						<input type="radio"
							   name="other_products[<?php echo $index; ?>][type]"
							   value="none"
							   <?php checked($other_settings['type'], 'none'); ?>>
						<?php esc_html_e('No Discount', 'wdb-discount-builder'); ?>
					</label>
					<label style="margin-right:15px;">
						<input type="radio"
							   name="other_products[<?php echo $index; ?>][type]"
							   value="percent"
							   <?php checked($other_settings['type'], 'percent'); ?>>
						<?php esc_html_e('Percentage', 'wdb-discount-builder'); ?>
					</label>
					<label style="margin-right:15px;">
						<input type="radio"
							   name="other_products[<?php echo $index; ?>][type]"
							   value="fixed"
							   <?php checked($other_settings['type'], 'fixed'); ?>>
						<?php esc_html_e('Fixed Amount', 'wdb-discount-builder'); ?>
					</label>

					<input type="number"
						   step="0.01"
						   min="0"
						   name="other_products[<?php echo $index; ?>][value]"
						   class="other-products-value"
						   placeholder="<?php esc_attr_e('Other products discount amount', 'wdb-discount-builder'); ?>"
						   value="<?php echo esc_attr($other_settings['value']); ?>"
						   style="width:150px;max-width:100%;margin-top:5px;">
					<span style="font-size:12px;color:#666;display:block;margin-top:3px;"><?php esc_html_e('This discount applies to products not specified in the rules above.', 'wdb-discount-builder'); ?></span>
				</div>
			</div>

			<div class="free-shipping-section" style="background:#f0fff4;padding:15px;border-radius:4px;margin-bottom:15px;border:1px solid #c6e6c6;">
				<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
					<input type="checkbox"
						   name="free_shipping[<?php echo $index; ?>]"
						   value="1"
						   <?php checked($free_shipping_enabled); ?>
						   class="wdb-free-shipping-checkbox">
					<span style="font-weight:bold;color:#2e7d32;"><?php esc_html_e('Free Shipping for This Discount Code', 'wdb-discount-builder'); ?></span>
				</label>
				<p style="margin:5px 0 0 0;font-size:12px;color:#555;"><?php esc_html_e('When enabled, only the free shipping option will be displayed when this coupon is used.', 'wdb-discount-builder'); ?></p>
			</div>

			<div class="coupon-rules-container">
				<?php if (!empty($rules)): ?>
					<?php foreach ($rules as $rule_index => $rule): ?>
						<?php $this->render_rule_box($index, $rule_index, $rule); ?>
					<?php endforeach; ?>
				<?php else: ?>
					<div class="empty-message"><?php esc_html_e('No rules defined yet.', 'wdb-discount-builder'); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a rule box within a coupon box.
	 *
	 * @since 1.0.0
	 * @param int   $coupon_index Parent coupon index.
	 * @param int   $rule_index   Rule index.
	 * @param array $rule         Rule data.
	 */
	private function render_rule_box( $coupon_index, $rule_index, $rule = array() ) {
		$type       = $rule['type'] ?? 'percent';
		$value      = $rule['value'] ?? 0;
		$products   = $rule['products'] ?? array();
		$categories = $rule['categories'] ?? array();
		$brands     = $rule['brands'] ?? array();
		?>
		<div class="rule-box" data-rule-index="<?php echo $rule_index; ?>">
			<div class="rule-header">
				<div>
					<select name="coupon_rules[<?php echo $coupon_index; ?>][rules][<?php echo $rule_index; ?>][type]"
							class="rule-type-select">
						<option value="percent" <?php selected($type, 'percent'); ?>><?php esc_html_e('Percentage (%)', 'wdb-discount-builder'); ?></option>
						<option value="fixed" <?php selected($type, 'fixed'); ?>><?php esc_html_e('Fixed Amount (Toman)', 'wdb-discount-builder'); ?></option>
					</select>

					<input type="number"
						   step="0.01"
						   min="0"
						   name="coupon_rules[<?php echo $coupon_index; ?>][rules][<?php echo $rule_index; ?>][value]"
						   class="rule-value-input"
						   placeholder="<?php esc_attr_e('Discount amount', 'wdb-discount-builder'); ?>"
						   value="<?php echo esc_attr($value); ?>">
				</div>
				<div>
					<button type="button" class="button remove-rule"><?php esc_html_e('Delete Rule', 'wdb-discount-builder'); ?></button>
				</div>
			</div>

			<div class="selection-type">
				<label>
					<input type="radio" name="selection_type_<?php echo $coupon_index; ?>_<?php echo $rule_index; ?>"
						   value="products" class="selection-type-radio"
						   data-coupon="<?php echo $coupon_index; ?>"
						   data-rule="<?php echo $rule_index; ?>"
						   <?php echo (!empty($products) || (empty($categories) && empty($brands))) ? 'checked' : ''; ?>>
					<?php esc_html_e('Select by Product', 'wdb-discount-builder'); ?>
				</label>
				<label>
					<input type="radio" name="selection_type_<?php echo $coupon_index; ?>_<?php echo $rule_index; ?>"
						   value="categories" class="selection-type-radio"
						   data-coupon="<?php echo $coupon_index; ?>"
						   data-rule="<?php echo $rule_index; ?>"
						   <?php echo (!empty($categories) && empty($products) && empty($brands)) ? 'checked' : ''; ?>>
					<?php esc_html_e('Select by Category', 'wdb-discount-builder'); ?>
				</label>
				<label>
					<input type="radio" name="selection_type_<?php echo $coupon_index; ?>_<?php echo $rule_index; ?>"
						   value="brands" class="selection-type-radio"
						   data-coupon="<?php echo $coupon_index; ?>"
						   data-rule="<?php echo $rule_index; ?>"
						   <?php echo (!empty($brands) && empty($products) && empty($categories)) ? 'checked' : ''; ?>>
					<?php esc_html_e('Select by Brand', 'wdb-discount-builder'); ?>
				</label>
			</div>

			<div class="search-section products-search-section"
				 style="<?php echo (!empty($products) || empty($categories) && empty($brands)) ? '' : 'display:none;'; ?>">
				<input type="text"
					   class="search-input product-search"
					   placeholder="<?php esc_attr_e('Search products...', 'wdb-discount-builder'); ?>"
					   data-coupon-index="<?php echo $coupon_index; ?>"
					   data-rule-index="<?php echo $rule_index; ?>"
					   data-search-type="products">

				<div class="results" style="display:none;"></div>

				<div class="selected-items selected-products">
					<?php if (!empty($products)): ?>
						<?php foreach ($products as $product_id):
							$product = wc_get_product($product_id);
							if ($product):
						?>
							<div class="selected-item" data-id="<?php echo esc_attr($product_id); ?>">
								<?php echo esc_html($product->get_name()); ?>
								<input type="hidden"
									   name="coupon_rules[<?php echo $coupon_index; ?>][rules][<?php echo $rule_index; ?>][products][]"
									   value="<?php echo esc_attr($product_id); ?>">
								<button type="button" class="remove-item">×</button>
							</div>
						<?php endif; endforeach; ?>
					<?php else: ?>
						<span class="empty-message" style="font-size:13px;"><?php esc_html_e('No products selected.', 'wdb-discount-builder'); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<div class="search-section categories-search-section"
				 style="<?php echo (!empty($categories) && empty($products) && empty($brands)) ? '' : 'display:none;'; ?>">
				<input type="text"
					   class="search-input category-search"
					   placeholder="<?php esc_attr_e('Search categories...', 'wdb-discount-builder'); ?>"
					   data-coupon-index="<?php echo $coupon_index; ?>"
					   data-rule-index="<?php echo $rule_index; ?>"
					   data-search-type="categories">

				<div class="results" style="display:none;"></div>

				<div class="selected-items selected-categories">
					<?php if (!empty($categories)): ?>
						<?php foreach ($categories as $cat_id):
							$cat = get_term($cat_id, 'product_cat');
							if ($cat && !is_wp_error($cat)):
						?>
							<div class="selected-item" data-id="<?php echo esc_attr($cat_id); ?>">
								<?php echo esc_html($cat->name); ?>
								<input type="hidden"
									   name="coupon_rules[<?php echo $coupon_index; ?>][rules][<?php echo $rule_index; ?>][categories][]"
									   value="<?php echo esc_attr($cat_id); ?>">
								<button type="button" class="remove-item">×</button>
							</div>
						<?php endif; endforeach; ?>
					<?php else: ?>
						<span class="empty-message" style="font-size:13px;"><?php esc_html_e('No categories selected.', 'wdb-discount-builder'); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<div class="search-section brands-search-section"
				 style="<?php echo (!empty($brands) && empty($products) && empty($categories)) ? '' : 'display:none;'; ?>">
				<input type="text"
					   class="search-input brand-search"
					   placeholder="<?php esc_attr_e('Search brands...', 'wdb-discount-builder'); ?>"
					   data-coupon-index="<?php echo $coupon_index; ?>"
					   data-rule-index="<?php echo $rule_index; ?>"
					   data-search-type="brands">

				<div class="results" style="display:none;"></div>

				<div class="selected-items selected-brands">
					<?php if (!empty($brands)): ?>
						<?php foreach ($brands as $brand_id):
							$brand = Wdb_Coupon_Manager::get_brand_by_id($brand_id);
							if ($brand):
						?>
							<div class="selected-item" data-id="<?php echo esc_attr($brand_id); ?>">
								<?php echo esc_html($brand['name']); ?>
								<input type="hidden"
									   name="coupon_rules[<?php echo $coupon_index; ?>][rules][<?php echo $rule_index; ?>][brands][]"
									   value="<?php echo esc_attr($brand_id); ?>">
								<button type="button" class="remove-item">×</button>
							</div>
						<?php endif; endforeach; ?>
					<?php else: ?>
						<span class="empty-message" style="font-size:13px;"><?php esc_html_e('No brands selected.', 'wdb-discount-builder'); ?></span>
					<?php endif; ?>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Display discount under each item in admin order view.
	 *
	 * @since 1.0.0
	 * @param int    $item_id  Item ID.
	 * @param object $item     Order item.
	 * @param object $product  Product object.
	 */
	public function display_item_discount_in_admin( $item_id, $item, $product ) {
		if (!is_admin()) {
			return;
		}

		$discount_amount   = floatval($item->get_meta('_wdb_discount_amount'));
		$discount_per_item = floatval($item->get_meta('_wdb_discount_per_item'));

		if ($discount_amount > 0) {
			?>
			<div style="color:#d63031;font-size:12px;margin-top:5px;font-weight:bold;">
				<?php
				printf(
					/* translators: %s: discount amount */
					esc_html__('Discount: -%s', 'wdb-discount-builder'),
					number_format($discount_amount, 0) . ' ' . esc_html__('Toman', 'wdb-discount-builder')
				);
				?>
				<?php if ($discount_per_item > 0 && $item->get_quantity() > 1): ?>
					(<?php
					printf(
						/* translators: 1: discount per item, 2: unit name */
						esc_html__('(%1$s per item)', 'wdb-discount-builder'),
						number_format($discount_per_item, 0) . ' ' . esc_html__('Toman', 'wdb-discount-builder')
					);
					?>)
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Display discount row in admin order totals meta box.
	 *
	 * @since 1.0.0
	 * @param int $order_id Order ID.
	 */
	public function display_discount_row_in_admin( $order_id ) {
		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}

		$total_discount = 0;
		foreach ($order->get_items() as $item) {
			$discount = floatval($item->get_meta('_wdb_discount_amount'));
			if ($discount > 0) {
				$total_discount += $discount;
			}
		}

		if ($total_discount > 0):
			?>
			<tr>
				<td class="label"><?php esc_html_e('Discount:', 'wdb-discount-builder'); ?></td>
				<td width="1%"></td>
				<td class="total">-<?php echo wc_price($total_discount); ?></td>
			</tr>
			<?php
		endif;
	}

	/**
	 * Display free shipping status in admin order data.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order Order object.
	 */
	public function display_free_shipping_status_in_admin( $order ) {
		$free_shipping = $order->get_meta('_wdb_free_shipping');
		$free_coupon   = $order->get_meta('_wdb_free_shipping_coupon');

		if ($free_shipping !== 'yes') {
			return;
		}

		echo '<div style="margin-top:10px;padding:10px;background:#e8f5e9;border:1px solid #c8e6c9;border-radius:4px;">';
		echo '<strong style="color:#2e7d32;">' . esc_html__('Free shipping was active', 'wdb-discount-builder') . '</strong>';
		if ($free_coupon) {
			echo '<br><small>' . sprintf(
				/* translators: %s: coupon code */
				esc_html__('Coupon: %s', 'wdb-discount-builder'),
				esc_html($free_coupon)
			) . '</small>';
		}
		echo '</div>';
	}
}
