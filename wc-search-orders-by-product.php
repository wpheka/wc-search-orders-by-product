<?php
/**
 * Plugin Name: WC Search Orders By Product
 * Plugin URI: https://www.wpheka.com/product/wc-search-orders-by-product
 * Description: The <code><strong>WC Search Orders By Product</strong></code> plugin helps you search your WooCommerce orders by product name, type and category.
 * Author: WPHEKA
 * Author URI: https://www.wpheka.com/
 * Version: 4.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Tested up to: 7.1.3
 * Requires Plugins: woocommerce
 * WC requires at least: 3.0
 * WC tested up to: 11.1.2
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
        /*
         * Explicit per-site scope rather than for_plugin(). This option is also
         * written by the Settings API -- register_setting( 'sobp_search_options',
         * 'sobp_settings', ... ) -- and options.php always writes per-site. If
         * for_plugin() resolved network scope on a network activation, reads and
         * the Settings API would use different rows and the settings would read
         * back empty with nothing reporting why.
         *
         * Per-site is also correct on its own terms: these toggles govern the
         * per-site order list screen.
         */
        $options = new \WPHEKA\Framework\V1\Core\Options(
            'sobp_settings',
            array(),
            \WPHEKA\Framework\V1\Core\Options::SCOPE_SITE
        );
    }

    return $options;
}

/**
 * Read the settings, with or without a framework.
 *
 * The three call sites used get_option() before adoption. Routing them through
 * the Options accessor without this guard meant that when no usable framework
 * booted -- which really happens, a stale bundle winning the registry is enough
 * -- `new Options` fataled on a missing class instead of the screen simply
 * rendering. Adoption must not make the degraded path worse than the code it
 * replaced.
 *
 * @since 3.3
 * @return array
 */
function wc_search_orders_by_product_settings()
{
    if (wc_search_orders_by_product_framework_ready()) {
        return wc_search_orders_by_product_options()->all();
    }

    return (array) get_option('sobp_settings', array());
}

/**
 * Persist settings, with or without a framework.
 *
 * Both paths merge. Options::update() merges by design, and the fallback merges
 * explicitly rather than calling update_option() with the partial array, so the
 * two paths cannot disagree about whether an absent key means "unchanged" or
 * "removed".
 *
 * @since 3.3
 * @param array $settings Settings to merge in.
 * @return void
 */
function wc_search_orders_by_product_save_settings($settings)
{
    $settings = (array) $settings;

    if (wc_search_orders_by_product_framework_ready()) {
        wc_search_orders_by_product_options()->update($settings);

        return;
    }

    update_option('sobp_settings', array_merge((array) get_option('sobp_settings', array()), $settings));
}

/**
 * Whether a setting is switched on.
 *
 * The plugin's own settings screen stores 1 or 0 and WooCommerce's settings
 * API stores 'yes' or 'no', so both are understood. A setting that was never
 * saved takes its default.
 *
 * @since 4.0
 * @param string $key     Key in sobp_settings.
 * @param bool   $default Value when never saved.
 * @return bool
 */
function wc_search_orders_by_product_setting_enabled($key, $default = false)
{
    $settings = wc_search_orders_by_product_settings();

    if (!array_key_exists($key, $settings)) {
        return (bool) $default;
    }

    return in_array($settings[$key], array(1, '1', 'yes', true), true);
}

/**
 * Capability needed to use the filters or change the settings.
 *
 * @since 4.0
 * @param string $default Capability for this context.
 * @return string
 */
function wc_search_orders_by_product_capability($default = 'manage_woocommerce')
{
    /**
     * Capability for the filters ('edit_shop_orders') or the settings ('manage_woocommerce').
     *
     * @since 4.0
     * @param string $capability Capability.
     */
    return (string) apply_filters('wc_search_orders_by_product_capability', $default);
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
 * Blocks are deliberately not declared: this plugin has no frontend code, so it
 * makes no claim either way and WooCommerce lists it as uncertain, which is the
 * truth.
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

    /*
     * HPOS only, which is exactly what this plugin declared before adoption.
     * The adapter's default declares Blocks compatibility too, and nobody has
     * verified this plugin against block checkout -- claiming it would replace
     * WooCommerce's "uncertain" listing with an assertion no one made.
     */
    \WPHEKA\Framework\V1\WooCommerce\Compatibility::declare_for(
        WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE,
        array( \WPHEKA\Framework\V1\WooCommerce\Compatibility::HPOS )
    );
}
add_action('plugins_loaded', 'wc_search_orders_by_product_declare_hpos_compatibility');
