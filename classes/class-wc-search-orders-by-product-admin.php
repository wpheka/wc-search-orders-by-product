<?php
class WC_Search_Orders_By_Product_Admin {

	public function __construct() {
		//admin script and style
		add_action('admin_enqueue_scripts', array(&$this, 'enqueue_admin_script'));
		add_action( 'restrict_manage_posts', array(&$this,'display_products_search_dropdown_restrict'));
		add_filter( 'request', array(&$this,'filter_orders_request_by_product'));
	}

	/**
	 * Admin Scripts
	 */

	public function enqueue_admin_script() {
		global $WC_Search_Orders_By_Product;
		$screen       = get_current_screen();
		$screen_id    = $screen ? $screen->id : '';
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$WC_Search_Orders_By_Product->library->load_select2_lib();
		wp_register_script('search_orders_by_product_admin_js', $WC_Search_Orders_By_Product->plugin_url.'assets/admin/js/admin.js', array('jquery', 'select2_js'), $WC_Search_Orders_By_Product->version, true);
		wp_localize_script( 'search_orders_by_product_admin_js', 'wc_products_select_params', array(
			'i18n_no_matches'           => _x( 'No matches found', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_ajax_error'           => _x( 'Loading failed', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_input_too_short_1'    => _x( 'Please enter 1 or more characters', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_input_too_short_n'    => _x( 'Please enter %qty% or more characters', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_input_too_long_1'     => _x( 'Please delete 1 character', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_input_too_long_n'     => _x( 'Please delete %qty% characters', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_selection_too_long_1' => _x( 'You can only select 1 item', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_selection_too_long_n' => _x( 'You can only select %qty% items', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_load_more'            => _x( 'Loading more results&hellip;', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'i18n_searching'            => _x( 'Searching&hellip;', 'products select', $WC_Search_Orders_By_Product->text_domain ),
			'ajax_url'                  => admin_url( 'admin-ajax.php' ),
			'search_woo_products_nonce'     => wp_create_nonce( 'search-woo-products' ),
		) );

		if ( in_array( $screen_id, wc_get_screen_ids() ) ) {
			wp_enqueue_script( 'search_orders_by_product_admin_js' );
			wp_enqueue_style('search_orders_by_product_admin_css',  $WC_Search_Orders_By_Product->plugin_url.'assets/admin/css/admin.css', array('select2_css'), $WC_Search_Orders_By_Product->version);
		}

	}

	public function display_products_search_dropdown_restrict() {
		global $typenow;

		if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ) ) ) {
			$this->display_products_search_dropdown();
		}
	}

	public function display_products_search_dropdown() {
		global $WC_Search_Orders_By_Product;
		$product_name = '';
		$product_id = '';
		if ( ! empty( $_GET['product_id'] ) ) {
			$product_id = absint( $_GET['product_id'] );
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$product_name = $product->get_title();
			}
		}
		?>
		<select class="woo-orders-search-by-product" style="width:203px;" id="product_id" name="product_id" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', $WC_Search_Orders_By_Product->text_domain ); ?>" data-action="search_woo_products">
		<option value="<?php echo esc_attr( $product_id ); ?>" selected="selected"><?php echo htmlspecialchars( $product_name ); ?><option>
		</select>
		<?php
	}

	public function filter_orders_request_by_product($vars) {
		global $typenow, $wp_query, $wpdb, $wp_post_statuses;
		if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ) ) ) {
		// Search orders by product.
		if ( ! empty( $_GET['product_id'] ) ) {
		$order_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT order_id
			FROM {$wpdb->prefix}woocommerce_order_items
			WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND meta_value = %d )
			AND order_item_type = 'line_item'
			", $_GET['product_id'] ) );

			// Force WP_Query return empty if don't found any order.
			$order_ids = ! empty( $order_ids ) ? $order_ids : array( 0 );

			$vars['post__in'] = $order_ids;
			}
		}
		return $vars;
	}
}