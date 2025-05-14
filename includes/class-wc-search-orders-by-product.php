<?php
/**
 * WC_Search_Orders_By_Product
 *
 * @package WC_Search_Orders_By_Product
 * @since   1.0
 */

defined('ABSPATH') || exit;

/**
 * Main WC_Search_Orders_By_Product Class.
 *
 * @class WC_Search_Orders_By_Product
 */
final class WC_Search_Orders_By_Product
{

    /**
     * WC_Search_Orders_By_Product version.
     *
     * @var string
     */
    public $version;

    /**
     * WC_Search_Orders_By_Product text domain.
     *
     * @var string
     */
    public $text_domain = 'wc-search-orders-by-product';

    /**
     * WC_Search_Orders_By_Product plugin url.
     *
     * @var string
     */
    public $plugin_url;

    /**
     * WC_Search_Orders_By_Product api feedback url.
     *
     * @var string
     */
    public $api_feedback_url;

    /**
     * WC_Search_Orders_By_Product plugin name.
     *
     * @var string
     */
    public $plugin_name;

    /**
     * The single instance of the class.
     *
     * @var WC_Search_Orders_By_Product
     * @since 1.0
     */
    protected static $_instance = null;

    /**
     * Main WC_Search_Orders_By_Product Instance.
     *
     * Ensures only one instance of WC_Search_Orders_By_Product is loaded or can be loaded.
     *
     * @since 1.0
     * @static
     * @see wc_search_orders_by_product()
     * @return WC_Search_Orders_By_Product - Main instance.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Cloning is forbidden.
     *
     * @since 1.0
     */
    public function __clone()
    {
        wc_doing_it_wrong(__FUNCTION__, __('Cloning is forbidden.', 'wc-search-orders-by-product'), '1.0');
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0
     */
    public function __wakeup()
    {
        wc_doing_it_wrong(__FUNCTION__, __('Unserializing instances of this class is forbidden.', 'wc-search-orders-by-product'), '1.0');
    }

    /**
     * WC_Search_Orders_By_Product Constructor.
     */
    public function __construct()
    {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();

        do_action('wc_search_orders_by_product_loaded');
    }

    /**
     * Hook into actions and filters.
     *
     * @since 1.0
     */
    private function init_hooks()
    {
        add_action('init', array( $this, 'init' ), 0);
    }

    /**
     * Init WC_Search_Orders_By_Product when WordPress Initialises.
     */
    public function init()
    {
        // Before init action.
        do_action('before_wc_search_orders_by_product_init');

        // Set up localisation.
        $this->load_plugin_textdomain();

        // Init action.
        do_action('wc_search_orders_by_product_init');
    }

    /**
     * Load Localisation files.
     *
     * Note: the first-loaded translation file overrides any following ones if the same translation is present.
     *
     * Locales found in:
     *      - WP_LANG_DIR/wc-search-orders-by-product/wc-search-orders-by-product-LOCALE.mo
     *      - WP_LANG_DIR/plugins/wc-search-orders-by-product-LOCALE.mo
     */
    public function load_plugin_textdomain()
    {
        $locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $locale = apply_filters('plugin_locale', $locale, $this->text_domain);

        unload_textdomain($this->text_domain);
        load_textdomain($this->text_domain, WP_LANG_DIR . '/wc-search-orders-by-product/wc-search-orders-by-product-' . $locale . '.mo');
        load_plugin_textdomain($this->text_domain, false, plugin_basename(dirname(WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE)) . '/languages');
    }

    /**
     * Define PT Constants.
     */
    private function define_constants()
    {

        if (! function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_data = get_plugin_data(WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE, false, false);
        $this->version = $plugin_data['Version'];
        $this->plugin_name = $plugin_data['Name'];
        $this->plugin_url = trailingslashit(plugins_url('', WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE));
        $this->api_feedback_url = 'https://wpheka.com/wp-json/wpheka/v1/plugins/feedback';

        $this->define('WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_ABSPATH', dirname(WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE) . '/');
        $this->define('WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_BASENAME', plugin_basename(WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE));
        $this->define('WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_VERSION', $this->version);
    }

    /**
     * Define constant if not already set.
     *
     * @param string      $name  Constant name.
     * @param string|bool $value Constant value.
     */
    private function define($name, $value)
    {
        if (! defined($name)) {
            define($name, $value);
        }
    }

    /**
     * What type of request is this?
     *
     * @param  string $type admin, ajax, cron or frontend.
     * @return bool
     */
    private function is_request($type)
    {
        switch ($type) {
            case 'admin':
                return is_admin();
            case 'ajax':
                return defined('DOING_AJAX');
            case 'cron':
                return defined('DOING_CRON');
            case 'frontend':
                return ( ! is_admin() || defined('DOING_AJAX') ) && ! defined('DOING_CRON') && ! defined('REST_REQUEST');
        }
    }

    /**
     * Include required core files used in admin and on the frontend.
     */
    public function includes()
    {

        /**
         * Core classes.
         */

        // Include ajax class.
        if ($this->is_request('ajax')) {
            include_once WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_ABSPATH . 'includes/admin/class-wc-search-orders-by-product-admin-ajax.php';
        }

        // Include admin class.
        if ($this->is_request('admin')) {
            include_once WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_ABSPATH . 'includes/admin/class-wc-search-orders-by-product-admin.php';
            include_once WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_ABSPATH . 'includes/admin/settings/class-wc-search-orders-by-product-admin-settings.php';
            include_once WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_ABSPATH . 'includes/admin/class-wc-search-orders-by-product-deactivation-popup.php';
        }
    }
}
