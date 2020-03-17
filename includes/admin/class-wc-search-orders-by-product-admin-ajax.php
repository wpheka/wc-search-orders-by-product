<?php
/**
 * WC_Search_Orders_By_Product
 *
 * @package WC_Search_Orders_By_Product
 * @author      WC_Search_Orders_By_Product
 * @link        https://github.com/AkshayaDev
 * @since       1.0
 * @version     1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Search_Orders_By_Product_Admin_Ajax', false ) ) :

	/**
	 * WC_Search_Orders_By_Product_Admin_Ajax Class.
	 */
	class WC_Search_Orders_By_Product_Admin_Ajax {

        /**
         * WC_Search_Orders_By_Product_Admin_Ajax Constructor.
         */    
        public function __construct() {
            add_action( 'wp_ajax_search_woo_products', array(&$this,'sobp_search_woo_products'));
            add_action( 'wp_ajax_save_sobp_plugin_data', array( $this, 'action_save_sobp_plugin_data' ) );
        }

        /**
         * Ajax product search
         */
        public function sobp_search_woo_products( $term = '', $include_variations = false ) {
            check_ajax_referer( 'search-woo-products', 'security' );

            $term = wc_clean( empty( $term ) ? stripslashes( $_GET['term'] ) : $term );

            if ( empty( $term ) ) {
                wp_die();
            }

            $data_store = WC_Data_Store::load( 'product' );
            $ids = $data_store->search_products( $term, '', (bool) $include_variations );

            $product_objects = array_filter( array_map( 'wc_get_product', $ids ), 'wc_products_array_filter_editable' );
            $products = array();

            foreach ( $product_objects as $product_object ) {
            $products[ $product_object->get_id() ] = rawurldecode( $product_object->get_formatted_name() );
            }

            wp_send_json($products);
        }

        /**
         * AJAX Action to save all plugin data
         *
         * @return void
         */
        public function action_save_sobp_plugin_data() {
            check_ajax_referer( 'save-plugin-data', 'sobp_nonce' );
            update_option('sobp_settings', $_POST);
            wp_send_json_success();
            wp_die();
        }             

	}

endif;

new WC_Search_Orders_By_Product_Admin_Ajax();