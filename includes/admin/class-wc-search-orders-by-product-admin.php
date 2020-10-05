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

/**
 * WC_Search_Orders_By_Product_Admin Class.
 *
 * @class WC_Search_Orders_By_Product_Admin
 */
class WC_Search_Orders_By_Product_Admin {

    /**
     * WC_Search_Orders_By_Product_Admin Constructor.
     */    
    public function __construct() {
        add_filter( 'plugin_action_links_' . WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_BASENAME, array( __CLASS__, 'plugin_action_links' ) );

		//admin script and style
		add_action('admin_enqueue_scripts', array(&$this, 'sobp_enqueue_admin_scripts_styles'));
		add_action( 'restrict_manage_posts', array(&$this,'sobp_display_products_search_dropdown_restrict'));
		add_filter( 'request', array(&$this,'sobp_filter_orders_request_by_product_type_and_category'));
		add_filter( 'posts_where', array(&$this,'sobp_filter_orders_request_by_product'));

		// Reorders woocommerce sub menus
		add_filter( 'menu_order', array( $this, 'sobp_menu_order' ) );
		add_filter( 'custom_menu_order', array( $this, 'sobp_custom_menu_order' ) );
    }

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param mixed $links Plugin Action links.
	 *
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=wc-search-orders-by-product-settings' ) . '" aria-label="' . esc_attr__( 'View plugin settings', wc_search_orders_by_product()->text_domain ) . '">' . esc_html__( 'Settings', wc_search_orders_by_product()->text_domain ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

	/**
	 * Restrict dropdown on WooCommerce Order post type
	 *
	 */
	private function is_active_on_post_type() {
		global $typenow;

		$access = in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ), true );

		// WooCommerce Subscription compatibility
		if ($typenow === 'shop_subscription') {
		  $access = false;
    }

		return apply_filters('sobp_restrict_by_post_type', $access, $typenow);
	}

	/**
	 * Admin Scripts
	 */
	public function sobp_enqueue_admin_scripts_styles() {
		global $WC_Search_Orders_By_Product;
		$screen       = get_current_screen();
		$screen_id    = $screen ? $screen->id : '';
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script('sobp_select2_js', $WC_Search_Orders_By_Product->plugin_url . 'assets/admin/js/select2.full.min.js', array('jquery'), $WC_Search_Orders_By_Product->version, true);
		wp_enqueue_style('sobp_select2_css',  $WC_Search_Orders_By_Product->plugin_url . 'assets/admin/css/select2.min.css', array(), $WC_Search_Orders_By_Product->version);
		wp_register_script('search_orders_by_product_admin_js', $WC_Search_Orders_By_Product->plugin_url.'assets/admin/js/admin.js', array('jquery', 'sobp_select2_js'), $WC_Search_Orders_By_Product->version, true);
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

	/**
	 * Product search dropdown restriction
	 */
	public function sobp_display_products_search_dropdown_restrict() {
		if ( $this->is_active_on_post_type() ) {
			$this->display_products_search_dropdown();
		}
	}

	/**
	 * Display product search dropdown
	 */
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
			$cat_terms = get_terms( array('taxonomy' => 'product_cat', 'fields' => 'id=>name' ) );
			$cat_output  = "<select name='search_product_cat' class='dropdown_product_cat'>";
			$cat_output .= '<option value="">' . __( 'Filter by product category', $WC_Search_Orders_By_Product->text_domain ) . '</option>';
			if(!empty($cat_terms)) {
				foreach ( $cat_terms as $cat_id => $cat_name ) {
					$cat_output .= '<option value="' . $cat_id . '" ';

					if ( isset( $_GET['search_product_cat'] ) ) {
					    $cat_output .= selected( $cat_id, $_GET['search_product_cat'], false );
					}

					$cat_output .= '>'.$cat_name;
					$cat_output .= '</option>';
				}
			}
			$cat_output .= "</select>";
			echo $cat_output;
		}
		
	}
	
	/**
	 * Get all product ids from orders
	 */
	private function get_order_product_ids() {
		global $wpdb;
		$t_posts = $wpdb->posts;
		$t_order_items = $wpdb->prefix . "woocommerce_order_items";  
		$t_order_itemmeta = $wpdb->prefix . "woocommerce_order_itemmeta";
		$query  = "SELECT $t_order_itemmeta.meta_value FROM";
		$query .= " $t_order_items LEFT JOIN $t_order_itemmeta";
		$query .= " on $t_order_itemmeta.order_item_id=$t_order_items.order_item_id";
		$query .= " WHERE $t_order_items.order_item_type='line_item'";
		$query .= " AND $t_order_itemmeta.meta_key='_product_id'";
		$query .= " AND $t_posts.ID=$t_order_items.order_id";
		return $query;
	}
	
	/**
	 * Product category query
	 */
	private function query_product_category(){
		global $wpdb;
		$t_term_relationships = $wpdb->term_relationships;

		$query  = "SELECT $t_term_relationships.term_taxonomy_id FROM $t_term_relationships WHERE $t_term_relationships.object_id IN (";
		$query .= $this->get_order_product_ids();
		$query .= ")";

		return $query;
	}
	
	/**
	 * Get order id's by product type
	 */
	private function order_ids_by_product_type( $post_type, $product_type ) {
        global $wpdb;
        
        $product_type_order_ids = $wpdb->get_col( "
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
            WHERE o.post_type = '$post_type'
            AND oim.meta_key = '_product_id'
            AND tt.taxonomy = 'product_type'
            AND t.name = '{$product_type}'
        ");
        
        return $product_type_order_ids;
	}
	
	/**
	 * Filter orders as per request
	 */
	public function sobp_filter_orders_request_by_product($where) {
	    global $wpdb;
        
        if ( $this->is_active_on_post_type() ) {
            if( is_search() ) {
                // Search orders by product
                if(!empty( $_GET['product_id'] ) && empty($_GET['search_product_type']) && empty($_GET['search_product_cat'])) {
                    $product_id = intval($_GET['product_id']);

    				// Check if selected product is inside order query
    				$where .= " AND $product_id IN (";
    				$where .= $this->get_order_product_ids();
    				$where .= ")";

                }
                
                // Search orders by product category
                if (!empty($_GET['search_product_cat']) && empty($_GET['product_id']) && empty($_GET['search_product_type'])) {
                    $product_cat = intval($_GET['search_product_cat']);
                    
                    // Check if selected category is inside these orders
                    $where .= " AND $product_cat IN (";
                    $where .= $this->query_product_category();
                    $where .= ")";
                    
                }
            }
        }
        return $where;
	}

	/**
	 * Filter orders
	 */
	public function sobp_filter_orders_request_by_product_type_and_category($vars) {
		global $typenow, $wp_query, $wpdb, $wp_post_statuses, $post_type;
		if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ) ) ) {

			// Search orders by product type
			if (!empty($_GET['search_product_type']) && empty($_GET['product_id']) && empty($_GET['search_product_cat'])) {
				$product_type = $_GET['search_product_type'];
                $product_type_order_ids = $this->order_ids_by_product_type( $post_type, $product_type );
				$product_type_order_ids = ! empty( $product_type_order_ids ) ? $product_type_order_ids : array( 0 );
				$vars['post__in'] = $product_type_order_ids;
			}

			// Search orders by product and product type
			if(!empty( $_GET['product_id'] ) && !empty($_GET['search_product_type']) && empty($_GET['search_product_cat'])) {
				$product_order_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT order_id
				FROM {$wpdb->prefix}woocommerce_order_items
				WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND meta_value = %d )
				AND order_item_type = 'line_item'
				", $_GET['product_id'] ) );

                $product_type = $_GET['search_product_type'];
                $product_type_order_ids = $this->order_ids_by_product_type( $post_type, $product_type );

				if(!empty($product_order_ids) && !empty($product_type_order_ids)){

				    $final_order_ids = array_unique(array_intersect($product_order_ids, $product_type_order_ids));
				    
				    if(empty($final_order_ids)) {
                        $vars['post__in'] = array( 0 );
				    }else{
                        $vars['post__in'] = $final_order_ids;
				    }
					
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
				    
				    $final_order_ids = array_unique(array_intersect($product_order_ids, $product_category_order_ids));
				    
				    if(empty($final_order_ids)) {
                        $vars['post__in'] = array( 0 );
				    }else{
                        $vars['post__in'] = $final_order_ids;
				    }

				}else{
					$vars['post__in'] = array( 0 );
				}

			}

			// Search orders by product type and product category
			if(!empty($_GET['search_product_type']) && !empty($_GET['search_product_cat']) && empty( $_GET['product_id'] )) {

				$product_type = $_GET['search_product_type'];
                $product_type_order_ids = $this->order_ids_by_product_type( $post_type, $product_type );

				$product_category_order_ids = $this->sobp_get_orders_by_product_category($_GET['search_product_cat']);

				if(!empty($product_type_order_ids) && !empty($product_category_order_ids)){
					
				    $final_order_ids = array_unique(array_intersect($product_type_order_ids, $product_category_order_ids));
				    
				    if(empty($final_order_ids)) {
                        $vars['post__in'] = array( 0 );
				    }else{
                        $vars['post__in'] = $final_order_ids;
				    }
					
				}else{
					$vars['post__in'] = array( 0 );
				}
			}

			// Search orders by product, product type and product category
			if(!empty( $_GET['product_id'] ) && !empty($_GET['search_product_type']) && !empty($_GET['search_product_cat'])) {
				$product_order_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT order_id
				FROM {$wpdb->prefix}woocommerce_order_items
				WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND meta_value = %d )
				AND order_item_type = 'line_item'
				", $_GET['product_id'] ) );

				$product_type = $_GET['search_product_type'];
                $product_type_order_ids = $this->order_ids_by_product_type( $post_type, $product_type );

				$product_category_order_ids = $this->sobp_get_orders_by_product_category($_GET['search_product_cat']);

				if(!empty($product_order_ids) && !empty($product_type_order_ids) && !empty($product_category_order_ids)){
					
					$final_order_ids_arr = array( $product_order_ids, $product_type_order_ids, $product_category_order_ids);
				    $final_order_ids = call_user_func_array('array_intersect',$final_order_ids_arr);
				    
				    if(empty($final_order_ids)) {
                        $vars['post__in'] = array( 0 );
				    }else{
                        $vars['post__in'] = $final_order_ids;
				    }
					
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
	 * @param  str $product_category_id
	 * @return array
	 */
    public function sobp_get_orders_by_product_category($product_category_id) {
    	$filtered_order_ids = array();	
    	$term = get_term_by( 'id', $product_category_id, 'product_cat');
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
    						$sobp_product_categories = $product->get_category_ids();
    						if(!empty($sobp_product_categories)){
    							if (in_array($category_id,$sobp_product_categories)) {
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

	/**
	 * Check if settings is enabled
	 */
	public function is_sobp_search_settings_active($option) {
		$settings = get_option('sobp_settings');

		if (empty($settings)) {
			return false;
		}

		return $settings[$option];
	}
	
	/**
	 * Sanitize and validate input. Accepts an array, return a sanitized array.
	 */
    public function sobp_search_options_validate($input) {
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

new WC_Search_Orders_By_Product_Admin();