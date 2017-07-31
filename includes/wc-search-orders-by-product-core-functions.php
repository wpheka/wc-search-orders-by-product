<?php
if (!function_exists('check_another_search_order_plugin_exists')) {

    /**
     * On activation, check another search orders by product plugin exists.
     *
     * @access public
     * @return void
     */
    function check_another_search_order_plugin_exists() {
        require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
        $search_order_plugins_arr = array();
        $search_order_plugins_arr[] = 'woocommerce-filter-orders-by-product/woocommerce-filter-orders-by-product.php';
        foreach ($search_order_plugins_arr as $plugin) {
            if (is_plugin_active($plugin)) {
                deactivate_plugins('wc-search-orders-by-product/wc_search_orders_by_product.php');
                exit(__('Another orders search by product plugin is already installed, Please deactivate it first to install this plugin.', WC_SEARCH_ORDERS_BY_PRODUCT_TEXT_DOMAIN));
            }
        }
    }
}?>