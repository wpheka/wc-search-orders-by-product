<?php
/**
 * Plugin Name: WC Search Orders By Product
 * Plugin URI: https://www.wpheka.com/product/wc-search-orders-by-product
 * Description: The <code><strong>WC Search Orders By Product</strong></code> plugin helps you search your WooCommerce orders by product name, type and category.
 * Author: WPHEKA
 * Author URI: https://www.wpheka.com/
 * Version: 3.1
 * Requires at least: 4.8
 * Tested up to: 6.8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 3.0
 * WC tested up to: 9.8.5
 * Text Domain: wc-search-orders-by-product
 * Domain Path: /languages
 * License: GPLv3 or later
 *
 * @package WC_Search_Orders_By_Product
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE.
if (!defined('WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE')) {
    define('WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE', __FILE__);
}

// Include the main WC_Search_Orders_By_Product class.
if (!class_exists('WC_Search_Orders_By_Product')) {
    include_once dirname(__FILE__) . '/includes/class-wc-search-orders-by-product.php';
}

/**
 * Main instance of WC_Search_Orders_By_Product.
 *
 * Returns the main instance of WC_Search_Orders_By_Product to prevent the need to use globals.
 *
 * @since  1.0
 * @return WC_Search_Orders_By_Product
 */
function wc_search_orders_by_product()
{
    return WC_Search_Orders_By_Product::instance();
}

// Global for backwards compatibility.
$GLOBALS['WC_Search_Orders_By_Product'] = wc_search_orders_by_product();

/**
 * Declares support for HPOS.
 *
 * @since 3.7
 * @return void
 */
function wc_search_orders_by_product_declare_hpos_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}
add_action('before_woocommerce_init', 'wc_search_orders_by_product_declare_hpos_compatibility');
