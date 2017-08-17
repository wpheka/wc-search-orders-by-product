<?php
class WC_Search_Orders_By_Product_Admin {

	public function __construct() {
		//admin script and style
		add_action('admin_enqueue_scripts', array(&$this, 'sobp_enqueue_admin_script'));
		add_action( 'restrict_manage_posts', array(&$this,'sobp_display_products_search_dropdown_restrict'));
		add_filter( 'request', array(&$this,'sobp_filter_orders_request_by_product'));
		// Search orders Settings
		add_action('admin_init', array( $this,'sobp_search_settings_init'));
		add_action( 'admin_menu', array( $this, 'sobp_search_settings_menu' ), 20 );
		// Reorders woocommerce sub menus
		add_filter( 'menu_order', array( $this, 'sobp_menu_order' ) );
		add_filter( 'custom_menu_order', array( $this, 'sobp_custom_menu_order' ) );
	}

	/**
	 * Admin Scripts
	 */

	public function sobp_enqueue_admin_script() {
		global $WC_Search_Orders_By_Product;
		$screen       = get_current_screen();
		$screen_id    = $screen ? $screen->id : '';
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$WC_Search_Orders_By_Product->library->sobp_load_select2_lib();
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
		}

	}

	public function sobp_display_products_search_dropdown_restrict() {
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
		// Product type filtering
		if($this->is_sobp_search_settings_active('search_orders_by_product_type')) {
			$terms   = get_terms( 'product_type' );
			$output  = '<select name="search_product_type" id="dropdown_product_type">';
			$output .= '<option value="">' . __( 'Filter by product types', $WC_Search_Orders_By_Product->text_domain ) . '</option>';
			if(!empty($terms)) {
					foreach ( $terms as $term ) {
				$output .= '<option value="' . sanitize_title( $term->name ) . '" ';

				if ( isset( $_GET['search_product_type'] ) ) {
					$output .= selected( $term->slug, $_GET['search_product_type'], false );
				}

				$output .= '>';

				switch ( $term->name ) {
					case 'grouped' :
						$output .= __( 'Grouped product', $WC_Search_Orders_By_Product->text_domain );
						break;
					case 'external' :
						$output .= __( 'External/Affiliate product', $WC_Search_Orders_By_Product->text_domain );
						break;
					case 'variable' :
						$output .= __( 'Variable product', $WC_Search_Orders_By_Product->text_domain );
						break;
					case 'simple' :
						$output .= __( 'Simple product', $WC_Search_Orders_By_Product->text_domain );
						break;
					default :
						// Assuming that we have other types in future
						$output .= ucfirst( $term->name );
						break;
				}

				$output .= '</option>';

				if ( 'simple' == $term->name ) {

					$output .= '<option value="downloadable" ';

					if ( isset( $_GET['search_product_type'] ) ) {
						$output .= selected( 'downloadable', $_GET['search_product_type'], false );
					}

					$output .= '> ' . ( is_rtl() ? '&larr;' : '&rarr;' ) . ' ' . __( 'Downloadable', $WC_Search_Orders_By_Product->text_domain ) . '</option>';

					$output .= '<option value="virtual" ';

					if ( isset( $_GET['search_product_type'] ) ) {
						$output .= selected( 'virtual', $_GET['search_product_type'], false );
					}

					$output .= '> ' . ( is_rtl() ? '&larr;' : '&rarr;' ) . ' ' . __( 'Virtual', $WC_Search_Orders_By_Product->text_domain ) . '</option>';
				}
			}
			}

			echo $output .= '</select>';			
		}

		// Filter orders by product category
		if($this->is_sobp_search_settings_active('search_orders_by_product_category')) {
			$cat_terms = get_terms( 'product_cat');
			$cat_output  = "<select name='search_product_cat' class='dropdown_product_cat'>";
			$cat_output .= '<option value="">' . __( 'Filter by product category', $WC_Search_Orders_By_Product->text_domain ) . '</option>';
			if(!empty($cat_terms)) {
				foreach ($cat_terms as $cat_term) {
					$cat_output .= '<option value="' . sanitize_title( $cat_term->name ) . '" ';

					if ( isset( $_GET['search_product_cat'] ) ) {
					$cat_output .= selected( $cat_term->slug, $_GET['search_product_cat'], false );
					}

					$cat_output .= '>'.$cat_term->name;
					$cat_output .= '</option>';
				}
			}
			$cat_output .= "</select>";
			echo $cat_output;
		}
		
	}

	public function sobp_filter_orders_request_by_product($vars) {
		global $typenow, $wp_query, $wpdb, $wp_post_statuses;
		if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ) ) ) {
			$final_order_ids = array();

			// Search orders by product
			if(!empty( $_GET['product_id'] ) && empty($_GET['search_product_type']) && empty($_GET['search_product_cat'])) {
				$product_order_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT order_id
				FROM {$wpdb->prefix}woocommerce_order_items
				WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND meta_value = %d )
				AND order_item_type = 'line_item'
				", $_GET['product_id'] ) );

				// Force WP_Query return empty if don't found any order.
				$product_order_ids = ! empty( $product_order_ids ) ? $product_order_ids : array( 0 );

				$vars['post__in'] = $product_order_ids;
			}

			// Search orders by product type
			if (!empty($_GET['search_product_type']) && empty($_GET['product_id']) && empty($_GET['search_product_cat'])) {
				$product_type_order_ids = $this->sobp_get_orders_by_product_type($_GET['search_product_type']);
				$product_type_order_ids = ! empty( $product_type_order_ids ) ? $product_type_order_ids : array( 0 );
				$vars['post__in'] = $product_type_order_ids;
			}

			// Search orders by product category
			if (!empty($_GET['search_product_cat']) && empty($_GET['product_id']) && empty($_GET['search_product_type'])) {
				$product_category_order_ids = $this->sobp_get_orders_by_product_category($_GET['search_product_cat']);
				$product_category_order_ids = ! empty( $product_category_order_ids ) ? $product_category_order_ids : array( 0 );
				$vars['post__in'] = $product_category_order_ids;
			}

			// Search orders by product and product type
			if(!empty( $_GET['product_id'] ) && !empty($_GET['search_product_type']) && empty($_GET['search_product_cat'])) {
				$product_order_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT order_id
				FROM {$wpdb->prefix}woocommerce_order_items
				WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND meta_value = %d )
				AND order_item_type = 'line_item'
				", $_GET['product_id'] ) );

				$product_type_order_ids = $this->sobp_get_orders_by_product_type($_GET['search_product_type']);

				if(!empty($product_order_ids) && !empty($product_type_order_ids)){
					$vars['post__in'] = array_unique(array_intersect($product_order_ids, $product_type_order_ids));
				}else{
					$vars['post__in'] = array( 0 );
				}

			}

			// Search orders by product and product category
			if(!empty( $_GET['product_id'] ) && !empty($_GET['search_product_cat']) && empty($_GET['search_product_type'])) {
				$product_order_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT order_id
				FROM {$wpdb->prefix}woocommerce_order_items
				WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND meta_value = %d )
				AND order_item_type = 'line_item'
				", $_GET['product_id'] ) );

				$product_category_order_ids = $this->sobp_get_orders_by_product_category($_GET['search_product_cat']);

				if(!empty($product_order_ids) && !empty($product_category_order_ids)){
					$vars['post__in'] = array_unique(array_intersect($product_order_ids, $product_category_order_ids));
				}else{
					$vars['post__in'] = array( 0 );
				}

			}

			// Search orders by product type and product category
			if(!empty($_GET['search_product_type']) && !empty($_GET['search_product_cat']) && empty( $_GET['product_id'] )) {

				$product_type_order_ids = $this->sobp_get_orders_by_product_type($_GET['search_product_type']);

				$product_category_order_ids = $this->sobp_get_orders_by_product_category($_GET['search_product_cat']);

				if(!empty($product_type_order_ids) && !empty($product_category_order_ids)){
					$vars['post__in'] = array_unique(array_intersect($product_type_order_ids, $product_category_order_ids));
				}else{
					$vars['post__in'] = array( 0 );
				}
			}

			// Search orders by product,product type and product category
			if(!empty( $_GET['product_id'] ) && !empty($_GET['search_product_type']) && !empty($_GET['search_product_cat'])) {
				$product_order_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT order_id
				FROM {$wpdb->prefix}woocommerce_order_items
				WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND meta_value = %d )
				AND order_item_type = 'line_item'
				", $_GET['product_id'] ) );

				$product_type_order_ids = $this->sobp_get_orders_by_product_type($_GET['search_product_type']);

				$product_category_order_ids = $this->sobp_get_orders_by_product_category($_GET['search_product_cat']);

				if(!empty($product_order_ids) && !empty($product_type_order_ids) && !empty($product_category_order_ids)){
					$final_order_ids_arr = array( $product_order_ids, $product_type_order_ids, $product_category_order_ids);
					$vars['post__in'] = call_user_func_array('array_intersect',$final_order_ids_arr);
				}else{
					$vars['post__in'] = array( 0 );
				}

			}
		}
		return $vars;
	}
	/**
	 * Get all order ids by product type
	 *
	 * @param  str $product_type
	 * @return array
	 */
    public function sobp_get_orders_by_product_type($product_type) {
    	$filtered_order_ids = array();	
    	$all_orders = wc_get_orders( array(
    		'limit'    => -1,
    		'return'   => 'ids',
    	) );	
    	if ( ! empty( $all_orders ) ) {
    		foreach ( $all_orders as $order_id ) {
    			$order = wc_get_order( $order_id );
    			foreach ( $order->get_items() as $item_id => $item ) {
    				$product = $item->get_product();
    				if(is_object($product)) {
    					if ( $product->is_type($product_type)) {
    						$filtered_order_ids[] = $order_id;						
    					}
    				}	
    			}	
    		}
    	}
    	if(!empty($filtered_order_ids)) {
    		return array_unique($filtered_order_ids);	
    	}
    	return $filtered_order_ids;
    }
    
    /**
	 * Get all order ids by product category
	 *
	 * @param  str $product_category_slug
	 * @return array
	 */
    public function sobp_get_orders_by_product_category($product_category_slug) {
    	$filtered_order_ids = array();	
    	$term = get_term_by( 'slug', $product_category_slug, 'product_cat');
    	if ( $term && ! is_wp_error( $term ) ) {
    		$category_id = $term->term_id;	
    		$all_orders = wc_get_orders( array(
    			'limit'    => -1,
    			'return'   => 'ids',
    		) );	
    		if ( ! empty( $all_orders ) ) {
    			foreach ( $all_orders as $order_id ) {
    				$order = wc_get_order( $order_id );
    				foreach ( $order->get_items() as $item_id => $item ) {
    					$product = $item->get_product();
    					if(is_object($product)) {
    						if(!empty($product->get_category_ids())){
    							if (in_array($category_id,$product->get_category_ids())) {
    								$filtered_order_ids[] = $order_id;	
    							}					
    						}					
    					}	
    				}	
    			}
    		}
    		if(!empty($filtered_order_ids)) {
    			return array_unique($filtered_order_ids);	
    		}
    		return $filtered_order_ids;
    	}
    	return $filtered_order_ids;
    }

	/**
	 * Get all product ids in a category (and its children).
	 *
	 * @param  int $category_slug
	 * @return array
	 */
	public function get_products_in_category( $category_slug ) {
		$term = get_term_by( 'slug', $category_slug, 'product_cat');
		if ( $term && ! is_wp_error( $term ) ) {
			$category_id = $term->term_id;
			$term_ids    = get_term_children( $category_id, 'product_cat' );
			$term_ids[]  = $category_id;
			$product_ids = get_objects_in_term( $term_ids, 'product_cat' );

			return array_unique( apply_filters( 'woocommerce_report_sales_by_category_get_products_in_category', $product_ids, $category_id ) );
		}else{
			return false;
		}
	}
	
	function sobp_search_settings_init() {
	    register_setting( 'sobp_search_options', 'sobp_settings', array($this, 'sobp_search_options_validate') );
	}
	
	function sobp_search_settings_menu() {
	    global $WC_Search_Orders_By_Product;
	    if ( current_user_can( 'manage_woocommerce' ) ) {
			add_submenu_page( 'woocommerce', __( 'WC Search Orders By Product Settings', $WC_Search_Orders_By_Product->text_domain ),  __( 'WC Search Orders By Product Settings', $WC_Search_Orders_By_Product->text_domain ) , 'manage_woocommerce', 'wc-search-orders-by-product-settings', array( $this, 'sobp_search_settings_page' ) );
		}
	}

	function is_sobp_search_settings_active($option) {
		$settings = get_option('sobp_settings');

		if (empty($settings)) {
			return false;
		}

		return $settings[$option];
	}
	
	function sobp_search_settings_page() {?>
        <div class="wrap">
            <h1>WC Search Orders By Product Settings</h1>
            <?php settings_errors(); ?>
            <div class="card">
            <form action="options.php" method="post">
                <?php settings_fields('sobp_search_options'); ?>
                <?php $options = get_option('sobp_settings'); ?>
                <table class="form-table">
                    <tr>
                        <td class="td-full">
                            <label for="search_orders_by_product_type">
                                <input name="sobp_settings[search_orders_by_product_type]" type="checkbox" id="search_orders_by_product_type" value="1"<?php checked('1', $options['search_orders_by_product_type']); ?> />
                                <?php _e('Search Orders By Product Types'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td class="td-full">
                            <label for="search_orders_by_product_category">
                                <input name="sobp_settings[search_orders_by_product_category]" type="checkbox" id="search_orders_by_product_category" value="1"<?php checked('1', $options['search_orders_by_product_category']); ?> />
                                <?php _e('Search Orders By Product Categories'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            </div>
        </div>
	<?php }
	
    // Sanitize and validate input. Accepts an array, return a sanitized array.
    function sobp_search_options_validate($input) {
        $input['search_orders_by_product_type'] = ( $input['search_orders_by_product_type'] == 1 ? 1 : 0 );
        $input['search_orders_by_product_category'] = ( $input['search_orders_by_product_category'] == 1 ? 1 : 0 );
        return $input;
    }
	
	/**
	 * Reorder the woocommerce menu items in admin.
	 *
	 * @param mixed $menu_order
	 * @return array
	 */
	public function sobp_menu_order($menu_order) {
	    global $submenu;
        $settings = $submenu['woocommerce'];
            foreach ( $settings as $key => $details ) {
                if ( $details[2] == 'wc-search-orders-by-product-settings' ) {
                    $index = $key;
                    $store_index_data = $details;
                }
        }
        if(!empty($index) && !empty($store_index_data)) {
            $submenu['woocommerce'][] = $store_index_data;
            unset( $submenu['woocommerce'][$index] );
            # Reorder the menu based on the keys in ascending order
            ksort( $submenu['woocommerce'] );
        }
	    return $menu_order;
	}
	
	/**
	 * Custom menu order.
	 *
	 * @return bool
	 */
	public function sobp_custom_menu_order() {
		return current_user_can( 'manage_woocommerce' );
	}
}