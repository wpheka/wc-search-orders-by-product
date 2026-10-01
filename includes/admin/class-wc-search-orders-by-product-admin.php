<?php
/**
 * WC_Search_Orders_By_Product
 *
 * @package WC_Search_Orders_By_Product
 * @author      WPHEKA
 * @link        https://wpheka.com/
 * @since       1.0
 * @version     1.0
 */

use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore;

defined('ABSPATH') || exit;

/**
 * WC_Search_Orders_By_Product_Admin Class.
 *
 * @class WC_Search_Orders_By_Product_Admin
 */
class WC_Search_Orders_By_Product_Admin
{

    /**
     * WC_Search_Orders_By_Product_Admin Constructor.
     */
    public function __construct()
    {
        add_filter('plugin_action_links_' . WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_BASENAME, array( __CLASS__, 'plugin_action_links' ));

        add_action('restrict_manage_posts', array( &$this, 'sobp_display_products_search_dropdown_restrict' ));
        add_filter('request', array( &$this, 'sobp_filter_orders' ), PHP_INT_MAX);

        // HPOS Hooks
        // The list table's own query-args filter, not woocommerce_hpos_pre_query.
        // That one fires for every HPOS order query on the site (My Account,
        // REST, WooCommerce Subscriptions' subscription lookups) and replaced
        // its results whenever a filter value was in the URL. This one fires
        // only for the shop_order admin list, and WooCommerce still applies
        // the status tab, trash view and pagination on top of the ids we add.
        add_action('woocommerce_order_list_table_restrict_manage_orders', array( &$this, 'display_products_search_dropdown' ));
        add_filter('woocommerce_shop_order_list_table_prepare_items_query_args', array( &$this, 'sobp_filter_orders_hpos'), PHP_INT_MAX);
    }

    /**
     * Show action links on the plugin screen.
     *
     * @param mixed $links Plugin Action links.
     *
     * @return array
     */
    public static function plugin_action_links($links)
    {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=wc-search-orders-by-product-settings') . '" aria-label="' . esc_attr__('View plugin settings', 'wc-search-orders-by-product') . '">' . esc_html__('Settings', 'wc-search-orders-by-product') . '</a>',
        );

        return array_merge($action_links, $links);
    }

    /**
     * Product search dropdown restriction
     */
    public function sobp_display_products_search_dropdown_restrict()
    {
        global $typenow;

        // shop_order only. wc_get_order_types( 'order-meta-boxes' ) also
        // returns shop_subscription, where every query below (which reads
        // type 'shop_order') would match nothing.
        if ('shop_order' === $typenow) {
            $this->display_products_search_dropdown();
        }
    }

    /**
     * Display product search dropdown.
     *
     * @param string $order_type Order type of the HPOS list table. The legacy
     *                           screen calls this with no argument.
     */
    public function display_products_search_dropdown($order_type = 'shop_order')
    {
        global $WC_Search_Orders_By_Product;

        if ('shop_order' !== $order_type) {
            return;
        }

        /*
         * Every $_GET read in this method renders the current filter state on
         * the orders list screen. None of them change anything, so a nonce would
         * protect nothing -- WooCommerce's own order filters read their values
         * the same way. Each is still sanitised on the way in.
         */
        $product_name = '';
        $product_id = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter display, no state change.
        if (! empty($_GET['product_id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter display, no state change.
            $product_id = absint($_GET['product_id']);
            $product = wc_get_product($product_id);
            if ($product) {
                $product_name = $product->get_title();
            }
        }
        ?>
        <select class="wc-product-search" id="product_id" name="product_id" data-placeholder="<?php esc_attr_e('Search for a product&hellip;', 'wc-search-orders-by-product'); ?>" data-allow_clear="true">
            <option value="<?php echo esc_attr($product_id); ?>" selected="selected"><?php echo esc_html($product_name); // Encoded, not filtered: selectWoo renders this as HTML. ?></option>
        </select>
        <?php
        // Product type filtering.
        if ($this->is_sobp_search_settings_active('search_orders_by_product_type')) {?>
            <select name="search_product_type" id="dropdown_product_type">
                <option value=""><?php esc_html_e('Filter by product types', 'wc-search-orders-by-product'); ?></option>
                <?php foreach (wc_get_product_types() as $value => $label) { ?>
                    <?php
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter display, no state change.
                    $selected_type = isset($_GET['search_product_type']) ? sanitize_text_field(wp_unslash($_GET['search_product_type'])) : '';
                    ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php echo selected($selected_type, $value, false); ?>><?php echo esc_html($label); ?></option>
                <?php } ?>
            </select>
        <?php }

        // Filter orders by product category
        if ($this->is_sobp_search_settings_active('search_orders_by_product_category')) {
            $product_categories = array();

            foreach (get_terms('product_cat') as $term) {
                $product_categories[ $term->term_id ] = $term->name;
            }
            ?>
            <select name='search_product_cat' class='dropdown_product_cat'>
                <option value=""><?php echo esc_html__('Filter by product category', 'wc-search-orders-by-product'); ?></option>
                <?php if (! empty($product_categories)) : ?>
                    <?php foreach ($product_categories as $cat_id => $cat_name) : ?>
                        <?php
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter display, no state change.
                        $selected_cat = isset($_GET['search_product_cat']) ? absint($_GET['search_product_cat']) : 0;
                        ?>
                        <option value="<?php echo esc_attr($cat_id); ?>" <?php echo selected($cat_id, $selected_cat, false); ?>>
                            <?php echo esc_html($cat_name); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <?php
        }
    }

    /**
     * Get Order IDs
     *
     * @since 1.5
     * @return array
     */
    private static function get_order_ids()
    {
        $default_order_statuses = array_keys((array) wc_get_order_statuses());

        $query_args = array(
            'fields'         => 'ids',
            'post_type'      => 'shop_order',
            'post_status'    => $default_order_statuses,
            'posts_per_page' => -1,
        );

        // get order IDs.
        $order_query = new WP_Query($query_args);
        $order_ids   = $order_query->posts;

        return $order_ids;
    }

    /**
     * Get all WooCommerce order IDs using wc_get_orders (HPOS compatible).
     *
     * @since 3.1
     * @return array
     */
    private function get_order_ids_hpos()
    {
        global $wpdb;

        $statuses = array_keys(wc_get_order_statuses());
        $statuses_sql = implode("','", array_map('esc_sql', $statuses));
        $order_table    = OrdersTableDataStore::get_orders_table_name();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        // $statuses_sql is esc_sql()ed above and $order_table comes from
        // WooCommerce's own data store. Neither an IN list nor a table
        // identifier can be a prepare() placeholder.
        $sql = "SELECT id 
		FROM {$order_table}
        WHERE type = 'shop_order'
		AND status IN ('{$statuses_sql}')";

        return $wpdb->get_col($sql); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- statuses are esc_sql()ed, table name is WooCommerce's own.
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Sanitize a list of IDs
     *
     * Passes each ID through `absint()` to ensure integer ID values.
     * Accepts either a comma-separated string of IDs or an array of IDs
     *
     * @since 4.0.0
     * @param array|string $ids IDs.
     * @return string comma-separated list of IDs
     */
    private static function get_sanitized_id_list($ids)
    {
        return implode(',', array_map('absint', is_string($ids) ? explode(',', $ids) : $ids));
    }

    /**
     * Filter provided order IDs based on whether they contain provided products
     *
     * @since 1.5
     * @param string|array $order_ids A comma-separated list or array of order IDs.
     * @param string|array $product_ids A comma-separated list or array of product IDs.
     * @return array
     */
    private static function filter_orders_containing_products($order_ids, $product_ids)
    {

        global $wpdb;

        $order_id_list   = self::get_sanitized_id_list($order_ids);
        $product_id_list = self::get_sanitized_id_list($product_ids);

        /*
         * An empty list would render as `IN ( )`, which is a MySQL syntax error
         * rather than a query matching nothing -- verified against the database.
         * $order_ids arrives from an earlier query, so a store with no matching
         * orders reaches here legitimately.
         */
        if ('' === $order_id_list || '' === $product_id_list) {
            return array();
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Variables are sanitized via get_sanitized_id_list
        // Every id in these lists is absint()ed by get_sanitized_id_list(),
        // and an IN list cannot be a prepare() placeholder. A phpcs:ignore
        // applies only to the following line, so the interpolations inside
        // the string need a disable/enable pair instead.
        return $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- both IN lists are absint()ed by get_sanitized_id_list().
            "SELECT DISTINCT order_id
			FROM {$wpdb->prefix}woocommerce_order_items items
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im ON items.order_item_id = im.order_item_id
			WHERE items.order_id IN ( {$order_id_list} )
			AND items.order_item_type = 'line_item'
			AND im.meta_key IN ( '_product_id', '_variation_id' )
			AND im.meta_value IN ( {$product_id_list} )
		"
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Filter provided order IDs based on whether they contain provided products (HPOS version).
     *
     * @since 3.1
     * @param string|array $order_ids   A comma-separated list or array of order IDs.
     * @param string|array $product_ids A comma-separated list or array of product IDs.
     * @return array Filtered order IDs.
     */
    private static function filter_orders_containing_products_hpos($order_ids, $product_ids)
    {
        global $wpdb;

        $order_id_list   = self::get_sanitized_id_list($order_ids);
        $product_id_list = self::get_sanitized_id_list($product_ids);

        /*
         * An empty list would render as `IN ( )`, which is a MySQL syntax error
         * rather than a query matching nothing -- verified against the database.
         * $order_ids arrives from an earlier query, so a store with no matching
         * orders reaches here legitimately.
         */
        if ('' === $order_id_list || '' === $product_id_list) {
            return array();
        }

        // Note: In HPOS mode, order items are still stored in the traditional tables
        // Only the main order data moved to wc_orders table
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        // Every id in these lists is absint()ed by get_sanitized_id_list(),
        // and an IN list cannot be a prepare() placeholder. A phpcs:ignore
        // applies only to the following line, so the interpolations inside
        // the string need a disable/enable pair instead.
        return $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- both IN lists are absint()ed by get_sanitized_id_list().
            "SELECT DISTINCT order_id
			FROM {$wpdb->prefix}woocommerce_order_items items
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im ON items.order_item_id = im.order_item_id
			WHERE items.order_id IN ( {$order_id_list} )
			AND items.order_item_type = 'line_item'
			AND im.meta_key IN ( '_product_id', '_variation_id' )
			AND im.meta_value IN ( {$product_id_list} )
		"
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
    }


    /**
     * Filter provided order IDs based on whether they contain
     * products in the provided categories
     *
     * @since 1.5
     * @param string|array $order_ids A comma-separated list or array of order IDs.
     * @param string|array $product_categories A comma-separated list or array of product category IDs.
     * @return array
     */
    private static function filter_orders_containing_product_categories($order_ids, $product_categories)
    {

        global $wpdb;

        $order_id_list    = self::get_sanitized_id_list($order_ids);
        $product_cat_list = self::get_sanitized_id_list($product_categories);

        /*
         * An empty list would render as `IN ( )`, which is a MySQL syntax error
         * rather than a query matching nothing -- verified against the database.
         * $order_ids arrives from an earlier query, so a store with no matching
         * orders reaches here legitimately.
         */
        if ('' === $order_id_list || '' === $product_cat_list) {
            return array();
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        // Every id in these lists is absint()ed by get_sanitized_id_list(),
        // and an IN list cannot be a prepare() placeholder. A phpcs:ignore
        // applies only to the following line, so the interpolations inside
        // the string need a disable/enable pair instead.
        return $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- both IN lists are absint()ed by get_sanitized_id_list().
            "SELECT DISTINCT order_id
			FROM {$wpdb->prefix}woocommerce_order_items items
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im ON items.order_item_id = im.order_item_id
			LEFT JOIN {$wpdb->term_relationships} tr ON im.meta_value = tr.object_id
			LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE items.order_id IN ( {$order_id_list} )
			AND items.order_item_type = 'line_item'
			AND im.meta_key = '_product_id'
			AND tt.taxonomy = 'product_cat'
			AND tt.term_id IN ( {$product_cat_list} )
		"
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Filter provided order IDs based on whether they contain products in the provided categories (HPOS-compatible).
     *
     * @since 3.1
     * @param string|array $order_ids A comma-separated list or array of order IDs.
     * @param string|array $product_categories A comma-separated list or array of product category term IDs.
     * @return array
     */
    private static function filter_orders_containing_product_categories_hpos($order_ids, $product_categories)
    {
        global $wpdb;

        $order_id_list   = self::get_sanitized_id_list($order_ids);
        $product_cat_ids = self::get_sanitized_id_list($product_categories);

        /*
         * An empty list would render as `IN ( )`, which is a MySQL syntax error
         * rather than a query matching nothing -- verified against the database.
         * $order_ids arrives from an earlier query, so a store with no matching
         * orders reaches here legitimately.
         */
        if ('' === $order_id_list || '' === $product_cat_ids) {
            return array();
        }

        // Note: In HPOS mode, order items are still stored in the traditional tables
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        // Both id lists are absint()ed by get_sanitized_id_list(); an IN list
        // cannot be a prepare() placeholder.
        $sql = "
			SELECT DISTINCT order_id
			FROM {$wpdb->prefix}woocommerce_order_items items
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im ON items.order_item_id = im.order_item_id
			LEFT JOIN {$wpdb->prefix}term_relationships tr ON im.meta_value = tr.object_id
			LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE items.order_id IN ( {$order_id_list} )
			AND items.order_item_type = 'line_item'
			AND im.meta_key = '_product_id'
			AND tt.taxonomy = 'product_cat'
			AND tt.term_id IN ( {$product_cat_ids} )
		";

        return $wpdb->get_col($sql); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- both IN lists are absint()ed by get_sanitized_id_list().
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Get order id's by product type
     */
    private static function order_ids_by_product_type($product_type)
    {
        global $wpdb;

        $product_type_order_ids = $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT DISTINCT o.ID
                FROM {$wpdb->prefix}posts o
                INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
                    ON oi.order_id = o.ID
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                    ON oi.order_item_id = oim.order_item_id
                INNER JOIN {$wpdb->prefix}term_relationships tr
                    ON oim.meta_value = tr.object_id
                INNER JOIN {$wpdb->prefix}term_taxonomy tt
                    ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->prefix}terms t
                    ON tt.term_id = t.term_id
                WHERE o.post_type = 'shop_order'
                AND oim.meta_key = '_product_id'
                AND tt.taxonomy = 'product_type'
                AND t.name = %s
            ",
                $product_type
            )
        );

        return $product_type_order_ids;
    }

    /**
     * Get order IDs by product type (HPOS-compatible).
     *
     * @since 3.1
     * @param string $product_type The product type to filter by (e.g., 'simple', 'variable').
     * @return array Order IDs containing at least one product of the given type.
     */
    private static function order_ids_by_product_type_hpos($product_type)
    {
        global $wpdb;

        $order_table = OrdersTableDataStore::get_orders_table_name();

        // Note: In HPOS mode, order items are still stored in the traditional tables
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        // The values here are prepared. $order_table is WooCommerce's own
        // orders table name, and a table identifier cannot be a placeholder.
        $product_type_order_ids = $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is WooCommerce's own; the value goes through prepare().
            $wpdb->prepare(
                "
                SELECT DISTINCT o.id
                FROM {$order_table} o
                INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
                    ON oi.order_id = o.id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                    ON oi.order_item_id = oim.order_item_id
                INNER JOIN {$wpdb->prefix}term_relationships tr
                    ON oim.meta_value = tr.object_id
                INNER JOIN {$wpdb->prefix}term_taxonomy tt
                    ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->prefix}terms t
                    ON tt.term_id = t.term_id
                WHERE o.type = 'shop_order'
                AND oim.meta_key = '_product_id'
                AND tt.taxonomy = 'product_type'
                AND t.name = %s
            ",
                $product_type
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

        return $product_type_order_ids;
    }

    /**
     * Handle order filters.
     *
     * @param array $query_vars Query vars.
     * @return array
     */
    public function sobp_filter_orders($query_vars)
    {
        global $typenow;

        self::count_filtered_search();

        // shop_order only: on the shop_subscription screen this used to set
        // post__in to shop_order ids, which emptied the subscriptions list.
        if ('shop_order' === $typenow) {
            // return $query_vars on trash orders page.
            if (! empty($query_vars['post_status']) && ('trash' == $query_vars['post_status'])) {
                return $query_vars;
            }

            // No filter selected: leave the query alone. This used to load
            // every order id into post__in on each visit to the list, which
            // also hid orders in statuses wc_get_order_statuses() omits.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only, no state change.
            if (empty($_GET['search_product_type']) && empty($_GET['product_id']) && empty($_GET['search_product_cat'])) {
                return $query_vars;
            }

            $order_ids = self::get_order_ids();

            // filter order IDs based on additional filtering criteria (products, product categories and product type).
            // phpcs:disable WordPress.Security.NonceVerification.Recommended
            // These are the admin order-list filter values. The screen is
            // reached by following a GET link, so there is no nonce to verify,
            // and nothing here changes state -- the values only narrow a query.
            // Each is unslashed and sanitized at the point of use.
            if (! empty($_GET['search_product_type'])) {
                $order_ids = self::order_ids_by_product_type(sanitize_text_field(wp_unslash($_GET['search_product_type'])));
            }

            if (! empty($order_ids) && ! empty($_GET['product_id'])) {
                $order_ids = self::filter_orders_containing_products($order_ids, sanitize_text_field(wp_unslash($_GET['product_id'])));
            }

            if (! empty($order_ids) && ! empty($_GET['search_product_cat'])) {
                $order_ids = self::filter_orders_containing_product_categories($order_ids, sanitize_text_field(wp_unslash($_GET['search_product_cat'])));
            }

            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            if (empty($order_ids)) {
                $query_vars['post__in'] = array( 0 );
            } else {
                $final_order_ids = array_unique($order_ids);
                $query_vars['post__in'] = $final_order_ids;
            }
        }

        return $query_vars;
    }

    /**
     * Handle order filters on the HPOS orders list.
     *
     * @since 3.1
     * @param array $query_args Arguments the list table passes to wc_get_orders().
     * @return array
     */
    public function sobp_filter_orders_hpos($query_args)
    {
        // Same as the legacy screen: the Trash view is left unfiltered.
        if (! empty($query_args['status']) && in_array('trash', (array) $query_args['status'], true)) {
            return $query_args;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        // Same as sobp_filter_orders above: admin order-list filter values,
        // arrived at by a GET link, narrowing a query and changing nothing.
        if (empty($_GET['search_product_type']) && empty($_GET['product_id']) && empty($_GET['search_product_cat'])) {
            return $query_args; // Let WooCommerce run the default query
        }

        $order_ids = self::get_order_ids_hpos();

        if (! empty($_GET['search_product_type'])) {
            $order_ids = self::order_ids_by_product_type_hpos(sanitize_text_field(wp_unslash($_GET['search_product_type'])));
        }

        if (! empty($order_ids) && ! empty($_GET['product_id'])) {
            $order_ids = self::filter_orders_containing_products_hpos($order_ids, sanitize_text_field(wp_unslash($_GET['product_id'])));
        }

        if (! empty($order_ids) && ! empty($_GET['search_product_cat'])) {
            $order_ids = self::filter_orders_containing_product_categories_hpos($order_ids, sanitize_text_field(wp_unslash($_GET['search_product_cat'])));
        }

        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        // Narrow the list table's own query. WooCommerce keeps its status,
        // ordering and pagination; array( 0 ) matches nothing.
        $query_args['id'] = empty($order_ids) ? array( 0 ) : array_values(array_unique(array_map('absint', $order_ids)));

        return $query_args;
    }

    /**
     * Check if settings is enabled
     *
     * @param  [type] $option Option name.
     * @return boolean         Settings
     */
    public function is_sobp_search_settings_active($option)
    {
        $settings = wc_search_orders_by_product_settings();

        // Was `return $settings[ $option ]`, which warned on a missing key and
        // returned an int despite the documented boolean.
        return ! empty($settings[ $option ]);
    }

    /**
     * Count order screens where this plugin actually filtered something.
     *
     * Feeds the review prompt's threshold, so the ask follows the plugin having
     * done its job rather than time having passed.
     *
     * Stops writing once the threshold is met, so this costs a handful of option
     * writes over the life of the install rather than one per order-screen load.
     *
     * @since 3.3
     * @return void
     */
    public static function count_filtered_search()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        // The same admin order-list filter values sobp_filter_orders reads.
        // Only their presence is used, and nothing from them is stored.
        $filtering = !empty($_GET['search_product_type']) || !empty($_GET['product_id']) || !empty($_GET['search_product_cat']);
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if (!$filtering) {
            return;
        }

        $count = (int) get_option('sobp_filtered_search_count', 0);

        if ($count >= 3) {
            return;
        }

        update_option('sobp_filtered_search_count', $count + 1, false);
    }
}

new WC_Search_Orders_By_Product_Admin();
