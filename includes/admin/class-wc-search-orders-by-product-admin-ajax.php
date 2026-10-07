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
			add_action( 'wp_ajax_save_sobp_plugin_data', array( $this, 'action_save_sobp_plugin_data' ) );
		}

		/**
		 * AJAX Action to save all plugin data
		 *
		 * @return void
		 */
		public function action_save_sobp_plugin_data() {
			check_ajax_referer( 'save-plugin-data', 'sobp_nonce' );

			if ( ! current_user_can( wc_search_orders_by_product_capability( 'manage_woocommerce' ) ) ) {
				wp_send_json_error( null, 403 );
			}

			$settings = WC_Search_Orders_By_Product_Admin_Settings::sanitize_settings( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field in sanitize_settings().

			wc_search_orders_by_product_save_settings( $settings );
			wp_send_json_success();
			wp_die();
		}

	}

endif;

new WC_Search_Orders_By_Product_Admin_Ajax();
