<?php
/**
 * Order filtering engine.
 *
 * Turns the filter values from the orders screen into SQL conditions that
 * WooCommerce adds to its own list query, so no list of order ids is ever
 * loaded into PHP. Each condition has the form `<order id column> IN ( ... )`,
 * which MySQL 5.7 and later run as an indexed semi-join.
 *
 * @package WC_Search_Orders_By_Product
 * @since   4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Search_Orders_By_Product_Engine class.
 */
final class WC_Search_Orders_By_Product_Engine {

	/**
	 * Match products through WooCommerce Analytics' indexed lookup table.
	 */
	const SOURCE_LOOKUP = 'lookup';

	/**
	 * Match products through order item meta. Always complete, but unindexed.
	 */
	const SOURCE_ITEMS = 'items';

	/**
	 * Transient holding the result of the lookup table coverage check.
	 */
	const COVERAGE_TRANSIENT = 'wc_search_orders_by_product_lookup_coverage';

	/**
	 * Orders changed this recently may not be in the lookup table yet, because
	 * WooCommerce syncs it in the background. They are matched through order
	 * item meta as well.
	 */
	const RECENT_SECONDS = HOUR_IN_SECONDS;

	/**
	 * Source used by the last call to get_conditions(), for diagnostics.
	 *
	 * @var string
	 */
	public $last_source = '';

	/**
	 * Build the SQL conditions for a set of filter values.
	 *
	 * @param array  $values    Sanitised values: product_ids (int[]), category_ids (int[]), product_type,
	 *                          payment_method, shipping_method, billing_country and sku (strings).
	 * @param string $id_column Fully qualified order id column of the list query.
	 * @param string $storage   'hpos' or 'posts'.
	 * @return string[] Conditions to AND into the query's WHERE clause.
	 */
	public function get_conditions( array $values, $id_column, $storage ) {
		$source            = $this->get_source( $storage );
		$this->last_source = $source;
		$conditions        = array();

		if ( ! empty( $values['product_ids'] ) ) {
			$conditions[] = $this->product_condition( $values['product_ids'], $id_column, $storage, $source );
		}

		if ( ! empty( $values['category_ids'] ) ) {
			$products     = $this->products_in_terms( 'product_cat', $this->with_child_terms( $values['category_ids'] ) );
			$conditions[] = $this->ids_condition( $products, array(), $id_column, $storage, $source );
		}

		if ( ! empty( $values['product_type'] ) ) {
			$conditions[] = $this->ids_condition( $this->products_of_type( $values['product_type'] ), array(), $id_column, $storage, $source );
		}

		if ( isset( $values['sku'] ) && '' !== $values['sku'] ) {
			$conditions[] = $this->product_condition( $this->products_by_sku( $values['sku'] ), $id_column, $storage, $source );
		}

		if ( ! empty( $values['payment_method'] ) ) {
			$conditions[] = $this->order_field_condition( 'payment_method', $values['payment_method'], $id_column, $storage );
		}

		if ( ! empty( $values['billing_country'] ) ) {
			$conditions[] = $this->order_field_condition( 'billing_country', $values['billing_country'], $id_column, $storage );
		}

		if ( ! empty( $values['shipping_method'] ) ) {
			$conditions[] = $this->shipping_method_condition( $values['shipping_method'], $id_column );
		}

		return $conditions;
	}

	/**
	 * Condition on an order field stored in the orders table (HPOS) or post meta (legacy).
	 *
	 * @param string $field     'payment_method' or 'billing_country'.
	 * @param string $value     Value to match exactly.
	 * @param string $id_column Order id column.
	 * @param string $storage   'hpos' or 'posts'.
	 * @return string
	 */
	private function order_field_condition( $field, $value, $id_column, $storage ) {
		global $wpdb;

		if ( 'hpos' === $storage ) {
			if ( 'payment_method' === $field ) {
				return $wpdb->prepare( "{$id_column} IN ( SELECT o.id FROM {$wpdb->prefix}wc_orders o WHERE o.payment_method = %s )", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column and table names are fixed.
			}

			return $wpdb->prepare( "{$id_column} IN ( SELECT a.order_id FROM {$wpdb->prefix}wc_order_addresses a WHERE a.address_type = 'billing' AND a.country = %s )", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column and table names are fixed.
		}

		$meta_key = 'payment_method' === $field ? '_payment_method' : '_billing_country';

		return $wpdb->prepare( "{$id_column} IN ( SELECT pm.post_id FROM {$wpdb->postmeta} pm WHERE pm.meta_key = %s AND pm.meta_value = %s )", $meta_key, $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column name is fixed.
	}

	/**
	 * Condition for orders with a shipping line of the given shipping method.
	 *
	 * @param string $method_id Shipping method id, for example 'flat_rate'.
	 * @param string $id_column Order id column.
	 * @return string
	 */
	private function shipping_method_condition( $method_id, $id_column ) {
		global $wpdb;

		$sql = "SELECT sobp_ship.order_id FROM (
			SELECT DISTINCT i.order_id
			FROM {$wpdb->prefix}woocommerce_order_itemmeta m
			STRAIGHT_JOIN {$wpdb->prefix}woocommerce_order_items i ON i.order_item_id = m.order_item_id
			WHERE i.order_item_type = 'shipping' AND m.meta_key = 'method_id' AND m.meta_value = %s
		) sobp_ship";

		return "{$id_column} IN ( " . $wpdb->prepare( $sql, $method_id ) . ' )'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is a fixed string with one placeholder.
	}

	/**
	 * Product and variation ids whose current SKU starts with the given text.
	 *
	 * Uses WooCommerce's indexed product meta lookup table. Capped at 1,000 matches.
	 *
	 * @param string $sku SKU or its beginning.
	 * @return int[]
	 */
	public function products_by_sku( $sku ) {
		global $wpdb;

		$sku = trim( (string) $sku );
		if ( '' === $sku ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- indexed lookup, runs once per filtered page.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT product_id FROM {$wpdb->prefix}wc_product_meta_lookup WHERE sku LIKE %s LIMIT 1000", $wpdb->esc_like( $sku ) . '%' ) );

		return array_map( 'absint', $ids );
	}

	/**
	 * Which table to match products against.
	 *
	 * @param string $storage 'hpos' or 'posts'.
	 * @return string One of the SOURCE_ constants.
	 */
	public function get_source( $storage ) {
		$cached = get_transient( self::COVERAGE_TRANSIENT );

		if ( ! is_array( $cached ) || ! isset( $cached[ $storage ] ) ) {
			$cached                      = is_array( $cached ) ? $cached : array();
			$cached[ $storage . '_at' ]  = time();
			$cached[ $storage ]          = $this->lookup_is_complete( $storage ) ? self::SOURCE_LOOKUP : self::SOURCE_ITEMS;
			set_transient( self::COVERAGE_TRANSIENT, $cached, 12 * HOUR_IN_SECONDS );
		}

		/**
		 * Whether to match products through WooCommerce's order product lookup table.
		 *
		 * @since 4.0
		 * @param bool   $available Result of the coverage check.
		 * @param string $storage   'hpos' or 'posts'.
		 */
		$available = apply_filters( 'wc_search_orders_by_product_lookup_available', self::SOURCE_LOOKUP === $cached[ $storage ], $storage );

		return $available ? self::SOURCE_LOOKUP : self::SOURCE_ITEMS;
	}

	/**
	 * Orders changed at or after this time are matched through order item meta too.
	 *
	 * The coverage check only proves orders changed an hour before it ran are
	 * in the lookup table, and its result is kept for 12 hours. Everything
	 * changed since then is checked through item meta, so a store whose
	 * background sync has stalled still finds its new orders.
	 *
	 * @param string $storage 'hpos' or 'posts'.
	 * @return int Unix time.
	 */
	public function get_recent_cutoff( $storage ) {
		$cached  = get_transient( self::COVERAGE_TRANSIENT );
		$checked = is_array( $cached ) && ! empty( $cached[ $storage . '_at' ] ) ? (int) $cached[ $storage . '_at' ] : time();

		return min( time(), $checked ) - self::RECENT_SECONDS;
	}

	/**
	 * Whether every line item of every settled order is in the lookup table.
	 *
	 * Orders changed in the last hour are ignored here; get_conditions() covers
	 * them through order item meta instead.
	 *
	 * @param string $storage 'hpos' or 'posts'.
	 * @return bool
	 */
	public function lookup_is_complete( $storage ) {
		global $wpdb;

		$lookup = $wpdb->prefix . 'wc_order_product_lookup';

		if ( $lookup !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $lookup ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- schema check, cached by the caller.
			return false;
		}

		$orders = $this->orders_table_sql( $storage, gmdate( 'Y-m-d H:i:s', time() - self::RECENT_SECONDS ), '<' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		// Table names come from $wpdb and WooCommerce; $orders is built from
		// constants and a prepared date in orders_table_sql().
		$missing = (int) $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $orders comes from orders_table_sql(): fixed SQL with a prepared date.
			"SELECT COUNT(*)
			FROM {$wpdb->prefix}woocommerce_order_items i
			INNER JOIN ( {$orders} ) o ON o.order_id = i.order_id
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta m
				ON m.order_item_id = i.order_item_id AND m.meta_key = '_product_id' AND m.meta_value <> '0'
			LEFT JOIN {$lookup} l ON l.order_item_id = i.order_item_id
			WHERE i.order_item_type = 'line_item'
			AND l.order_item_id IS NULL"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return 0 === $missing;
	}

	/**
	 * Condition for orders containing any of the given products or variations.
	 *
	 * A variable product matches its variations too, as it always has.
	 *
	 * @param int[]  $product_ids Product or variation ids.
	 * @param string $id_column   Order id column.
	 * @param string $storage     'hpos' or 'posts'.
	 * @param string $source      One of the SOURCE_ constants.
	 * @return string
	 */
	private function product_condition( array $product_ids, $id_column, $storage, $source ) {
		$parents    = array();
		$variations = array();

		foreach ( array_unique( array_map( 'absint', $product_ids ) ) as $id ) {
			$parent = $id ? wp_get_post_parent_id( $id ) : 0;
			if ( $parent && 'product_variation' === get_post_type( $id ) ) {
				$variations[ $id ] = $parent;
			} elseif ( $id ) {
				$parents[] = $id;
			}
		}

		return $this->ids_condition( $parents, $variations, $id_column, $storage, $source );
	}

	/**
	 * Condition for orders containing any of the given parent products or variations.
	 *
	 * Ids are resolved in PHP and passed as literal lists rather than nested
	 * subqueries: MySQL 5.7 runs a subquery nested inside an IN subquery once
	 * per row of the outer table, which took minutes on 100,000 orders.
	 *
	 * @param int[]  $parents    Parent or simple product ids (match any line of the product).
	 * @param int[]  $variations Variation id => parent product id.
	 * @param string $id_column  Order id column.
	 * @param string $storage    'hpos' or 'posts'.
	 * @param string $source     One of the SOURCE_ constants.
	 * @return string
	 */
	private function ids_condition( array $parents, array $variations, $id_column, $storage, $source ) {
		global $wpdb;

		$parents = array_values( array_unique( array_filter( array_map( 'absint', $parents ) ) ) );

		if ( empty( $parents ) && empty( $variations ) ) {
			return '1 = 0';
		}

		$items_or = array();
		if ( $parents ) {
			$items_or[] = "( m.meta_key = '_product_id' AND m.meta_value IN ( " . $this->string_list( $parents ) . ' ) )';
		}
		if ( $variations ) {
			$items_or[] = "( m.meta_key = '_variation_id' AND m.meta_value IN ( " . $this->string_list( array_keys( $variations ) ) . ' ) )';
		}
		$items_sql = $this->items_sql( implode( ' OR ', $items_or ) );

		if ( self::SOURCE_ITEMS === $source ) {
			return "{$id_column} IN ( {$items_sql} )";
		}

		// Every branch constrains product_id first, so MySQL can use its index.
		$lookup_or = array();
		if ( $parents ) {
			$lookup_or[] = 'l.product_id IN ( ' . implode( ',', $parents ) . ' )';
		}
		foreach ( $variations as $variation_id => $parent_id ) {
			$lookup_or[] = '( l.product_id = ' . absint( $parent_id ) . ' AND l.variation_id = ' . absint( $variation_id ) . ' )';
		}
		$lookup_sql = "SELECT l.order_id FROM {$wpdb->prefix}wc_order_product_lookup l WHERE " . implode( ' OR ', $lookup_or );

		return $this->with_recent( "{$id_column} IN ( {$lookup_sql} )", $items_sql, $id_column, $storage );
	}

	/**
	 * Add orders changed since the last coverage check, matched through item meta, to a lookup condition.
	 *
	 * @param string $lookup_condition Condition built on the lookup table.
	 * @param string $items_sql        The same match through order item meta.
	 * @param string $id_column        Order id column.
	 * @param string $storage          'hpos' or 'posts'.
	 * @return string
	 */
	private function with_recent( $lookup_condition, $items_sql, $id_column, $storage ) {
		global $wpdb;

		$recent = $this->orders_table_sql( $storage, gmdate( 'Y-m-d H:i:s', $this->get_recent_cutoff( $storage ) ), '>=' );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- built from constants, absint()ed ids and a prepared date.
		$ids = array_map( 'absint', $wpdb->get_col( "SELECT DISTINCT x.order_id FROM ( {$items_sql} ) x INNER JOIN ( {$recent} ) r ON r.order_id = x.order_id" ) );

		if ( empty( $ids ) ) {
			return $lookup_condition;
		}

		return "( {$lookup_condition} OR {$id_column} IN ( " . implode( ',', $ids ) . ' ) )';
	}

	/**
	 * Subquery selecting order_id for line items whose meta matches a clause.
	 *
	 * Two things keep this fast on MySQL 5.7 (measured on 100,000 orders):
	 * STRAIGHT_JOIN makes MySQL start from the meta_key index instead of
	 * scanning every order item (6 s down to 0.6 s for a 1,838-product
	 * category), and the derived table makes it build the order list once
	 * rather than re-checking it for each order.
	 *
	 * @param string $meta_clause WHERE clause on the itemmeta alias `m`.
	 * @return string
	 */
	private function items_sql( $meta_clause ) {
		global $wpdb;

		return "SELECT sobp_items.order_id FROM (
			SELECT DISTINCT i.order_id
			FROM {$wpdb->prefix}woocommerce_order_itemmeta m
			STRAIGHT_JOIN {$wpdb->prefix}woocommerce_order_items i ON i.order_item_id = m.order_item_id
			WHERE i.order_item_type = 'line_item' AND ( {$meta_clause} )
		) sobp_items";
	}

	/**
	 * Product ids in the given terms of a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int[]  $term_ids Term ids.
	 * @return int[]
	 */
	private function products_in_terms( $taxonomy, array $term_ids ) {
		global $wpdb;

		$term_ids = array_filter( array_map( 'absint', $term_ids ) );
		if ( empty( $term_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		// Table names come from $wpdb; $placeholders is a list of %d.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT tr.object_id
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tt.taxonomy = %s AND tt.term_id IN ( {$placeholders} )",
				array_merge( array( $taxonomy ), array_values( $term_ids ) )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return array_map( 'absint', $ids );
	}

	/**
	 * Product ids of a product type.
	 *
	 * @param string $type Product type slug, for example 'variable'.
	 * @return int[]
	 */
	private function products_of_type( $type ) {
		$term = get_term_by( 'slug', sanitize_title( $type ), 'product_type' );

		return $term ? $this->products_in_terms( 'product_type', array( $term->term_id ) ) : array();
	}

	/**
	 * Add every descendant of the given product categories.
	 *
	 * @param int[] $term_ids Category ids.
	 * @return int[]
	 */
	private function with_child_terms( array $term_ids ) {
		$all = array();

		foreach ( array_filter( array_map( 'absint', $term_ids ) ) as $term_id ) {
			$all[]    = $term_id;
			$children = get_term_children( $term_id, 'product_cat' );
			if ( is_array( $children ) ) {
				$all = array_merge( $all, array_map( 'absint', $children ) );
			}
		}

		return array_values( array_unique( $all ) );
	}

	/**
	 * Subquery selecting shop order ids changed before or after a time.
	 *
	 * @param string $storage  'hpos' or 'posts'.
	 * @param string $gmt_date Y-m-d H:i:s in GMT.
	 * @param string $compare  '<' or '>='.
	 * @return string
	 */
	private function orders_table_sql( $storage, $gmt_date, $compare ) {
		global $wpdb;

		$compare = '<' === $compare ? '<' : '>=';

		if ( 'hpos' === $storage ) {
			$table = $wpdb->prefix . 'wc_orders';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and operator are fixed above.
			return $wpdb->prepare( "SELECT id AS order_id FROM {$table} WHERE type = 'shop_order' AND status NOT IN ( 'trash', 'auto-draft', 'wc-checkout-draft' ) AND date_updated_gmt {$compare} %s", $gmt_date );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- operator is fixed above.
		return $wpdb->prepare( "SELECT ID AS order_id FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status NOT IN ( 'trash', 'auto-draft', 'wc-checkout-draft' ) AND post_modified_gmt {$compare} %s", $gmt_date );
	}

	/**
	 * Quote a list of ids as strings for comparison with longtext meta values.
	 *
	 * @param int[] $ids Ids.
	 * @return string
	 */
	private function string_list( array $ids ) {
		return "'" . implode( "','", array_map( 'absint', $ids ) ) . "'";
	}
}
