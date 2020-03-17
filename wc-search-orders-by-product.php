<?php
/*
* Plugin Name: WC Search Orders By Product
* Plugin URI: https://github.com/AkshayaDev/WC-Search-Orders-By-Product
* Description: A simple plugin that helps you search your WooCommerce orders by product.
* Author: Akshaya Swaroop
* Version: 1.3
* Author URI: https://github.com/AkshayaDev
* Requires at least: 4.4
* Tested up to: 5.3
* Text Domain: wc-search-orders-by-product
* Domain Path: /languages/
* License: GPLv3 or later
* WC requires at least: 3.0
* WC tested up to: 3.8
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE.
if ( ! defined( 'WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE' ) ) {
	define( 'WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE', __FILE__ );
}

// Include the main WC_Search_Orders_By_Product class.
if ( ! class_exists( 'WC_Search_Orders_By_Product' ) ) {
	include_once dirname( __FILE__ ) . '/includes/class-wc-search-orders-by-product.php';
}

/**
 * Main instance of WC_Search_Orders_By_Product.
 *
 * Returns the main instance of WC_Search_Orders_By_Product to prevent the need to use globals.
 *
 * @since  1.0
 * @return WC_Search_Orders_By_Product
 */
function wc_search_orders_by_product() {
	return WC_Search_Orders_By_Product::instance();
}

// Global for backwards compatibility.
$GLOBALS['WC_Search_Orders_By_Product'] = wc_search_orders_by_product();