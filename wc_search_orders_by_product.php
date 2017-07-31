<?php
/*
Plugin Name: WC Search Orders By Product
Plugin URI: https://github.com/AkshayaDev/WC-Search-Orders-By-Product
Description: Search your woocommerce orders by product
Author: Akshaya Swaroop
Version: 1.0.0
Author URI: https://github.com/AkshayaDev
Requires at least: 4.0
Tested up to: 4.8
Text Domain: search_orders_by_product
Domain Path: /languages/
*/

if(!defined('ABSPATH')) exit; // Exit if accessed directly
if ( ! class_exists( 'WC_Dependencies_Search_Order' ) )
	require_once trailingslashit(dirname(__FILE__)).'includes/class-wc-search-orders-by-product-dependencies.php';
require_once trailingslashit(dirname(__FILE__)).'includes/wc-search-orders-by-product-core-functions.php';
require_once trailingslashit(dirname(__FILE__)).'config.php';
if(!defined('WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_TOKEN')) exit;
if(!defined('WC_SEARCH_ORDERS_BY_PRODUCT_TEXT_DOMAIN')) exit;

/* Check if another search orders by product plugin exist */
register_activation_hook(__FILE__, 'check_another_search_order_plugin_exists');
/* Remove rewrite rules on activation. */
register_activation_hook(__FILE__, 'flush_rewrite_rules');

if(!class_exists('WC_Search_Orders_By_Product') && WC_Dependencies_Search_Order::is_woocommerce_active()) {
	require_once( trailingslashit(dirname(__FILE__)).'classes/class-wc-search-orders-by-product.php' );
	global $WC_Search_Orders_By_Product;
	$WC_Search_Orders_By_Product = new WC_Search_Orders_By_Product( __FILE__ );
	$GLOBALS['WC_Search_Orders_By_Product'] = $WC_Search_Orders_By_Product;
}else {
    add_action('admin_notices', 'search_orders_by_product_admin_notice');
    function search_orders_by_product_admin_notice() {
        ?>
        <div class="error">
            <p><?php _e('WC Search Orders By Product plugin requires <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> plugins to be active!', WC_SEARCH_ORDERS_BY_PRODUCT_TEXT_DOMAIN); ?></p>
        </div>
        <?php
    }
}?>