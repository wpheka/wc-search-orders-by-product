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

		add_action( 'restrict_manage_posts', array( &$this, 'sobp_display_products_search_dropdown_restrict' ) );
		add_filter( 'request', array( &$this, 'sobp_filter_orders' ), PHP_INT_MAX );
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
	 * Product search dropdown restriction
	 */
	public function sobp_display_products_search_dropdown_restrict() {
		global $typenow;

		if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ) ) ) {
			$this->display_products_search_dropdown();
		}
	}

	/**
	 * Display product search dropdown.
	 */
	protected function display_products_search_dropdown() {
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
		<select class="wc-product-search" id="product_id" name="product_id" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', $WC_Search_Orders_By_Product->text_domain ); ?>" data-allow_clear="true">
			<option value="<?php echo esc_attr( $product_id ); ?>" selected="selected"><?php echo htmlspecialchars( wp_kses_post( $product_name ) ); // htmlspecialchars to prevent XSS when rendered by selectWoo. ?><option>
		</select>
		<?php
		// Product type filtering.
		if ( $this->is_sobp_search_settings_active( 'search_orders_by_product_type' ) ) {
			$terms   = get_terms( 'product_type' );
			$output  = '<select name="search_product_type" id="dropdown_product_type">';
			$output .= '<option value="">' . __( 'Filter by product types', $WC_Search_Orders_By_Product->text_domain ) . '</option>';
			if ( ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$output .= '<option value="' . sanitize_title( $term->name ) . '" ';

					if ( isset( $_GET['search_product_type'] ) ) {
						$output .= selected( $term->slug, $_GET['search_product_type'], false );
					}

					$output .= '>';

					switch ( $term->name ) {
						case 'grouped':
							$output .= __( 'Grouped product', $WC_Search_Orders_By_Product->text_domain );
							break;
						case 'external':
							$output .= __( 'External/Affiliate product', $WC_Search_Orders_By_Product->text_domain );
							break;
						case 'variable':
							$output .= __( 'Variable product', $WC_Search_Orders_By_Product->text_domain );
							break;
						case 'simple':
							$output .= __( 'Simple product', $WC_Search_Orders_By_Product->text_domain );
							break;
						default:
							// Assuming that we have other types in future.
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
		if ( $this->is_sobp_search_settings_active( 'search_orders_by_product_category' ) ) {
			$product_categories = array();

			foreach ( get_terms( 'product_cat' ) as $term ) {
				$product_categories[ $term->term_id ] = $term->name;
			}
			$cat_output  = "<select name='search_product_cat' class='dropdown_product_cat'>";
			$cat_output .= '<option value="">' . __( 'Filter by product category', $WC_Search_Orders_By_Product->text_domain ) . '</option>';
			if ( ! empty( $product_categories ) ) {
				foreach ( $product_categories as $cat_id => $cat_name ) {
					$cat_output .= '<option value="' . $cat_id . '" ';

					if ( isset( $_GET['search_product_cat'] ) ) {
						$cat_output .= selected( $cat_id, $_GET['search_product_cat'], false );
					}

					$cat_output .= '>' . $cat_name;
					$cat_output .= '</option>';
				}
			}
			$cat_output .= '</select>';
			echo $cat_output;
		}
	}

	/**
	 * Get Order IDs
	 *
	 * @since 1.5
	 * @return array
	 */
	private static function get_order_ids() {
		$default_order_statuses = array_keys( (array) wc_get_order_statuses() );

		$query_args = array(
			'fields'         => 'ids',
			'post_type'      => 'shop_order',
			'post_status'    => $default_order_statuses,
			'posts_per_page' => -1,
		);

		// get order IDs.
		$order_query = new WP_Query( $query_args );
		$order_ids   = $order_query->posts;

		return $order_ids;
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
	private static function get_sanitized_id_list( $ids ) {
		return implode( ',', array_map( 'absint', is_string( $ids ) ? explode( ',', $ids ) : $ids ) );
	}

	/**
	 * Filter provided order IDs based on whether they contain provided products
	 *
	 * @since 1.5
	 * @param string|array $order_ids A comma-separated list or array of order IDs.
	 * @param string|array $product_ids A comma-separated list or array of product IDs.
	 * @return array
	 */
	private static function filter_orders_containing_products( $order_ids, $product_ids ) {

		global $wpdb;

		$order_id_list   = self::get_sanitized_id_list( $order_ids );
		$product_id_list = self::get_sanitized_id_list( $product_ids );

		return $wpdb->get_col(
			"SELECT DISTINCT order_id
			FROM {$wpdb->prefix}woocommerce_order_items items
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im ON items.order_item_id = im.order_item_id
			WHERE items.order_id IN ( {$order_id_list} )
			AND items.order_item_type = 'line_item'
			AND im.meta_key IN ( '_product_id', '_variation_id' )
			AND im.meta_value IN ( {$product_id_list} )
		"
		);
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
	private static function filter_orders_containing_product_categories( $order_ids, $product_categories ) {

		global $wpdb;

		$order_id_list    = self::get_sanitized_id_list( $order_ids );
		$product_cat_list = self::get_sanitized_id_list( $product_categories );

		return $wpdb->get_col(
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
	}

	/**
	 * Get order id's by product type
	 */
	private static function order_ids_by_product_type( $product_type ) {
		global $wpdb;

		$product_type_order_ids = $wpdb->get_col(
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
            AND t.name = '{$product_type}'
        "
		);

		return $product_type_order_ids;
	}

	/**
	 * Handle order filters.
	 *
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public function sobp_filter_orders( $query_vars ) {
		global $typenow;
		if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ), true ) ) {

			$order_ids = self::get_order_ids();

			// filter order IDs based on additional filtering criteria (products, product categories and product type).
			if ( ! empty( $_GET['search_product_type'] ) ) {
				$order_ids = self::order_ids_by_product_type( $_GET['search_product_type'] );
			}

			if ( ! empty( $order_ids ) && ! empty( $_GET['product_id'] ) ) {

				$order_ids = self::filter_orders_containing_products( $order_ids, $_GET['product_id'] );
			}

			if ( ! empty( $order_ids ) && ! empty( $_GET['search_product_cat'] ) ) {

				$order_ids = self::filter_orders_containing_product_categories( $order_ids, $_GET['search_product_cat'] );
			}

			if ( empty( $order_ids ) ) {
				$query_vars['post__in'] = array( 0 );
			} else {
				$final_order_ids = array_unique( $order_ids );
				$query_vars['post__in'] = $final_order_ids;
			}
		}

		return $query_vars;
	}

	/**
	 * Check if settings is enabled
	 *
	 * @param  [type] $option Option name.
	 * @return boolean         Settings
	 */
	public function is_sobp_search_settings_active( $option ) {
		$settings = get_option( 'sobp_settings' );

		if ( empty( $settings ) ) {
			return false;
		}

		return $settings[ $option ];
	}

}

new WC_Search_Orders_By_Product_Admin();
