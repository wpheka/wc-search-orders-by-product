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

			$plugin_token = str_replace( '-', '_', wc_search_orders_by_product()->text_domain );

			add_action( 'wp_ajax_save_sobp_plugin_data', array( $this, 'action_save_sobp_plugin_data' ) );
			add_action( 'wp_ajax_' . $plugin_token . '_deactivation_popup', array( $this, 'action_save_sobp_deactivation_popup_data' ) );
		}

		/**
		 * AJAX Action to save all plugin data
		 *
		 * @return void
		 */
		public function action_save_sobp_plugin_data() {
			check_ajax_referer( 'save-plugin-data', 'sobp_nonce' );
			update_option( 'sobp_settings', $_POST );
			wp_send_json_success();
			wp_die();
		}

		/**
		 * AJAX Action to save deactivation popup data
		 *
		 * @return void
		 */
		public function action_save_sobp_deactivation_popup_data() {

			$plugin_token = str_replace( '-', '_', wc_search_orders_by_product()->text_domain );
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], $plugin_token . 'deactivate_feedback_nonce' ) ) {
				wp_send_json_error();
			}

			$feedback_url = wc_search_orders_by_product()->api_feedback_url;

			$deactivation_reason = '';
			$deactivation_domain = '';
			$deactivation_license_key = '';

			if ( ! empty( $_POST['deactivation_reason'] ) ) {
				$deactivation_reason = $_POST['deactivation_reason'];

				if ( $deactivation_reason == 'Other' ) {
					if ( ! empty( $_POST['deactivation_reason_other'] ) ) {
						$deactivation_reason = $_POST['deactivation_reason_other'];
					}
				}
			}

			if ( ! empty( $_POST['deactivation_domain'] ) ) {
				$deactivation_domain = $_POST['deactivation_domain'];
			}

			if ( ! empty( $_POST['deactivation_license_key'] ) ) {
				$deactivation_license_key = $_POST['deactivation_license_key'];
			}

			if ( ! empty( $_POST['email'] ) ) {
				$email = $_POST['email'];
			}

			wp_remote_post(
				$feedback_url,
				array(
					'timeout' => 30,
					'body' => array(
						'plugin' => wc_search_orders_by_product()->plugin_name,
						'deactivation_reason' => $deactivation_reason,
						'deactivation_domain' => $deactivation_domain,
						'deactivation_license_key' => $deactivation_license_key,
						'email' => $email,
					),
				)
			);

			wp_send_json_success();

			wp_die();
		}

	}

endif;

new WC_Search_Orders_By_Product_Admin_Ajax();
