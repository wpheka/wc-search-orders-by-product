<?php
/**
 * Plugin Name: WC Search Orders By Product
 * Plugin URI: https://www.wpheka.com/product/wc-search-orders-by-product
 * Description: The <code><strong>WC Search Orders By Product</strong></code> plugin helps you search your WooCommerce orders by product name, type and category.
 * Author: WPHEKA
 * Author URI: https://www.wpheka.com/
 * Version: 3.2
 * Requires at least: 4.8
 * Requires PHP: 8.1
 * Tested up to: 7.1
 * Requires Plugins: woocommerce
 * WC requires at least: 3.0
 * WC tested up to: 11.0.1
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

define('WC_SEARCH_ORDERS_BY_PRODUCT_MIN_FRAMEWORK', '1.0.0');

/*
 * Loaded at include time so the registry resolves before plugins_loaded. The
 * classes themselves are not available yet -- the autoloader registers on
 * plugins_loaded at -100 -- so nothing may touch them until then.
 */
if (is_readable(__DIR__ . '/framework/register.php')) {
    require_once __DIR__ . '/framework/register.php';
}

/**
 * Whether a usable framework booted.
 *
 * @since 3.3
 * @return bool
 */
function wc_search_orders_by_product_framework_ready()
{
    if (!class_exists('WPHEKA_Framework_Versions', false)) {
        return false;
    }

    $active = WPHEKA_Framework_Versions::active_version('1');

    if (!is_string($active) || !version_compare($active, WC_SEARCH_ORDERS_BY_PRODUCT_MIN_FRAMEWORK, '>=')) {
        return false;
    }

    foreach (array(
        '\\WPHEKA\\Framework\\V1\\Core\\Options',
        '\\WPHEKA\\Framework\\V1\\Admin\\Menu',
        '\\WPHEKA\\Framework\\V1\\WooCommerce\\Compatibility',
    ) as $class) {
        if (!class_exists($class)) {
            return false;
        }
    }

    return true;
}

/**
 * The plugin's settings, stored in one row.
 *
 * @since 3.3
 * @return \WPHEKA\Framework\V1\Core\Options
 */
function wc_search_orders_by_product_options()
{
    static $options = null;

    if (null === $options) {
        $options = \WPHEKA\Framework\V1\Core\Options::for_plugin(
            'sobp_settings',
            array(),
            plugin_basename(WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE)
        );
    }

    return $options;
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
 * Declares support for HPOS and Cart/Checkout Blocks.
 *
 * Hooked to plugins_loaded, not called at include time. before_woocommerce_init
 * fires from WooCommerce's init at priority 0, so plugins_loaded is early
 * enough -- but the framework autoloader only registers on plugins_loaded at
 * -100, so at include time Compatibility does not exist and the class_exists()
 * guard would skip the declaration silently. WooCommerce would then list this
 * plugin as HPOS-incompatible while the code looked correct.
 *
 * @since 3.1
 * @return void
 */
function wc_search_orders_by_product_declare_hpos_compatibility()
{
    if (!wc_search_orders_by_product_framework_ready()) {
        // Fall back to declaring it by hand, so a bundle that lost the
        // WooCommerce module does not silently drop the declaration.
        add_action(
            'before_woocommerce_init',
            static function () {
                if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE, true);
                }
            }
        );

        return;
    }

    \WPHEKA\Framework\V1\WooCommerce\Compatibility::declare_for(WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE);
}
add_action('plugins_loaded', 'wc_search_orders_by_product_declare_hpos_compatibility');
