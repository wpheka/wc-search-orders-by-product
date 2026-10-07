<?php
/**
 * Orders screen: filter controls, filtering and the purchased items column.
 *
 * @package WC_Search_Orders_By_Product
 * @since   1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Search_Orders_By_Product_Admin class.
 */
class WC_Search_Orders_By_Product_Admin {

	/**
	 * Query argument / query var carrying the filter values to the SQL hooks.
	 */
	const QUERY_KEY = 'wc_search_orders_by_product';

	/**
	 * Filter registry.
	 *
	 * @var WC_Search_Orders_By_Product_Filters
	 */
	public $filters;

	/**
	 * Query engine.
	 *
	 * @var WC_Search_Orders_By_Product_Engine
	 */
	public $engine;

	/**
	 * Constructor.
	 *
	 * @param WC_Search_Orders_By_Product_Filters $filters Filter registry.
	 * @param WC_Search_Orders_By_Product_Engine  $engine  Query engine.
	 */
	public function __construct( WC_Search_Orders_By_Product_Filters $filters, WC_Search_Orders_By_Product_Engine $engine ) {
		$this->filters = $filters;
		$this->engine  = $engine;

		add_filter( 'plugin_action_links_' . WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_BASENAME, array( __CLASS__, 'plugin_action_links' ) );

		// Legacy (posts) orders screen.
		add_action( 'restrict_manage_posts', array( $this, 'render_filters_legacy' ) );
		add_filter( 'request', array( $this, 'filter_legacy_request' ), PHP_INT_MAX );
		add_filter( 'posts_where', array( $this, 'legacy_posts_where' ), 10, 2 );

		// HPOS orders screen. The list table's own query-args filter fires only
		// for the shop_order admin list, never for other order queries (My
		// Account, REST, WooCommerce Subscriptions' lookups).
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'render_filters_hpos' ) );
		add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', array( $this, 'filter_hpos_query_args' ), PHP_INT_MAX );
		add_filter( 'woocommerce_orders_table_query_clauses', array( $this, 'hpos_query_clauses' ), 10, 3 );

		// Purchased items column.
		add_filter( 'woocommerce_shop_order_list_table_columns', array( $this, 'add_items_column' ) );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( $this, 'render_items_column_hpos' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_items_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_items_column_legacy' ), 10, 2 );
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=wc_search_orders_by_product' ) ) . '" aria-label="' . esc_attr__( 'View plugin settings', 'wc-search-orders-by-product' ) . '">' . esc_html__( 'Settings', 'wc-search-orders-by-product' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

	/**
	 * Filter controls on the legacy orders screen.
	 *
	 * @return void
	 */
	public function render_filters_legacy() {
		global $typenow;

		// shop_order only: the queries match shop orders, so on the
		// subscriptions screen every filter would empty the list.
		if ( 'shop_order' === $typenow && $this->user_can_filter() ) {
			$this->filters->render( 'posts' );
		}
	}

	/**
	 * Filter controls on the HPOS orders screen.
	 *
	 * @param string $order_type Order type of the list table.
	 * @return void
	 */
	public function render_filters_hpos( $order_type = 'shop_order' ) {
		if ( 'shop_order' === $order_type && $this->user_can_filter() ) {
			$this->filters->render( 'hpos' );
		}
	}

	/**
	 * Pass the filter values to the legacy list query.
	 *
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public function filter_legacy_request( $query_vars ) {
		global $typenow;

		if ( 'shop_order' !== $typenow || ! $this->user_can_filter() ) {
			return $query_vars;
		}

		// The Trash view is left unfiltered, as it always has been.
		if ( ! empty( $query_vars['post_status'] ) && 'trash' === $query_vars['post_status'] ) {
			return $query_vars;
		}

		$values = $this->filters->get_values();
		if ( ! $this->filters->is_filtering( $values ) ) {
			return $query_vars;
		}

		$query_vars[ self::QUERY_KEY ] = $values;
		self::count_filtered_search();

		return $query_vars;
	}

	/**
	 * Add the filter conditions to the legacy list query.
	 *
	 * @param string   $where WHERE clause.
	 * @param WP_Query $query The query.
	 * @return string
	 */
	public function legacy_posts_where( $where, $query ) {
		global $wpdb;

		$values = $query instanceof WP_Query ? $query->get( self::QUERY_KEY ) : null;
		if ( empty( $values ) || ! is_array( $values ) ) {
			return $where;
		}

		foreach ( $this->get_conditions( $values, "{$wpdb->posts}.ID", 'posts' ) as $condition ) {
			$where .= " AND {$condition}";
		}

		return $where;
	}

	/**
	 * Pass the filter values to the HPOS list query.
	 *
	 * @param array $query_args Arguments the list table passes to wc_get_orders().
	 * @return array
	 */
	public function filter_hpos_query_args( $query_args ) {
		if ( ! $this->user_can_filter() ) {
			return $query_args;
		}

		if ( ! empty( $query_args['status'] ) && in_array( 'trash', (array) $query_args['status'], true ) ) {
			return $query_args;
		}

		$values = $this->filters->get_values();
		if ( ! $this->filters->is_filtering( $values ) ) {
			return $query_args;
		}

		$query_args[ self::QUERY_KEY ] = $values;
		self::count_filtered_search();

		/**
		 * Arguments of the filtered HPOS list query, for add-ons that filter by
		 * fields wc_get_orders() already understands.
		 *
		 * @since 4.0
		 * @param array $query_args wc_get_orders() arguments.
		 * @param array $values     Filter values.
		 */
		return (array) apply_filters( 'wc_search_orders_by_product_query_args', $query_args, $values );
	}

	/**
	 * Add the filter conditions to the HPOS list query and its count.
	 *
	 * @param array                                                               $clauses Query pieces.
	 * @param \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery $query   The query.
	 * @param array                                                               $args    Query arguments.
	 * @return array
	 */
	public function hpos_query_clauses( $clauses, $query, $args ) {
		if ( empty( $args[ self::QUERY_KEY ] ) || ! is_array( $args[ self::QUERY_KEY ] ) ) {
			return $clauses;
		}

		foreach ( $this->get_conditions( $args[ self::QUERY_KEY ], $query->get_table_name( 'orders' ) . '.id', 'hpos' ) as $condition ) {
			$clauses['where'] .= " AND {$condition}";
		}

		return $clauses;
	}

	/**
	 * SQL conditions for a set of filter values.
	 *
	 * @param array  $values    Filter values.
	 * @param string $id_column Order id column.
	 * @param string $storage   'hpos' or 'posts'.
	 * @return string[]
	 */
	public function get_conditions( array $values, $id_column, $storage ) {
		/**
		 * SQL conditions added to the orders list query, each ANDed in.
		 *
		 * @since 4.0
		 * @param string[] $conditions Conditions from the engine.
		 * @param array    $values     Filter values.
		 * @param string   $storage    'hpos' or 'posts'.
		 * @param string   $id_column  Order id column of the query.
		 */
		$conditions = (array) apply_filters( 'wc_search_orders_by_product_conditions', $this->engine->get_conditions( $values, $id_column, $storage ), $values, $storage, $id_column );

		/**
		 * After the filter conditions were built for the orders list.
		 *
		 * @since 4.0
		 * @param array  $values  Filter values.
		 * @param string $storage 'hpos' or 'posts'.
		 */
		do_action( 'wc_search_orders_by_product_filtered', $values, $storage );

		return $conditions;
	}

	/**
	 * Add the purchased items column after the order number.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_items_column( $columns ) {
		if ( ! wc_search_orders_by_product_setting_enabled( 'purchased_items_column', true ) ) {
			return $columns;
		}

		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_number' === $key ) {
				$out['sobp_items'] = __( 'Purchased', 'wc-search-orders-by-product' );
			}
		}
		if ( ! isset( $out['sobp_items'] ) ) {
			$out['sobp_items'] = __( 'Purchased', 'wc-search-orders-by-product' );
		}

		return $out;
	}

	/**
	 * Purchased items cell, HPOS.
	 *
	 * @param string   $column Column id.
	 * @param WC_Order $order  Order.
	 * @return void
	 */
	public function render_items_column_hpos( $column, $order ) {
		if ( 'sobp_items' === $column ) {
			$this->print_items( $order );
		}
	}

	/**
	 * Purchased items cell, legacy.
	 *
	 * @param string $column  Column id.
	 * @param int    $post_id Order id.
	 * @return void
	 */
	public function render_items_column_legacy( $column, $post_id ) {
		if ( 'sobp_items' === $column ) {
			$this->print_items( wc_get_order( $post_id ) );
		}
	}

	/**
	 * Print "2 × Hoodie - L" lines, at most five, then "+ N more".
	 *
	 * @param WC_Order|false $order Order.
	 * @return void
	 */
	private function print_items( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$lines = array();
		foreach ( $order->get_items() as $item ) {
			$lines[] = sprintf(
				/* translators: 1: quantity, 2: product name */
				__( '%1$s &times; %2$s', 'wc-search-orders-by-product' ),
				esc_html( wc_stock_amount( $item->get_quantity() ) ),
				esc_html( wp_strip_all_tags( $item->get_name() ) )
			);
		}

		if ( empty( $lines ) ) {
			echo '&ndash;';
			return;
		}

		$shown = array_slice( $lines, 0, 5 );
		echo '<span class="sobp-items">' . implode( '<br />', $shown ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.

		if ( count( $lines ) > 5 ) {
			/* translators: %d: number of further items */
			echo '<br /><span class="sobp-items-more">' . esc_html( sprintf( __( '+ %d more', 'wc-search-orders-by-product' ), count( $lines ) - 5 ) ) . '</span>';
		}
	}

	/**
	 * Whether the current user may use the filters.
	 *
	 * @return bool
	 */
	private function user_can_filter() {
		return current_user_can( wc_search_orders_by_product_capability( 'edit_shop_orders' ) );
	}

	/**
	 * Count order screens where this plugin filtered something.
	 *
	 * Feeds the review prompt's threshold. Called only from the shop_order
	 * screens, on both storage engines. Stops writing once the threshold is met.
	 *
	 * @since 3.3
	 * @return void
	 */
	public static function count_filtered_search() {
		$count = (int) get_option( 'sobp_filtered_search_count', 0 );

		if ( $count >= 3 ) {
			return;
		}

		update_option( 'sobp_filtered_search_count', $count + 1, false );
	}
}
