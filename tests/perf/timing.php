<?php
/**
 * F1: time the orders list query (page 1 of 20 plus the total count) with the
 * 3.5 code and with the 4.0 engine on each match source.
 *
 * Run after seed-large.php: wp eval-file tests/perf/timing.php
 * Runs wc_get_orders() exactly as the orders screen's list table does.
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-wc-search-orders-by-product-engine.php';
require_once __DIR__ . '/load-35.php';

global $wpdb;

// Stop any single SELECT after 60 seconds instead of hanging the run.
$wpdb->query( 'SET SESSION max_execution_time = 60000' );

$storage = 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ? 'hpos' : 'posts';
$engine  = new WC_Search_Orders_By_Product_Engine();
$admin   = new ReflectionClass( 'WC_Search_Orders_By_Product_Admin_35' );
$inst    = $admin->newInstanceWithoutConstructor();
$base    = array(
	'limit'    => 20,
	'page'     => 1,
	'paginate' => true,
	'orderby'  => 'date',
	'order'    => 'DESC',
	'type'     => 'shop_order',
	'status'   => array_keys( wc_get_order_statuses() ),
);

// 4.0 path: the engine's conditions are added to WooCommerce's own query.
$values = null;
add_filter(
	'woocommerce_orders_table_query_clauses',
	function ( $clauses, $query, $args ) use ( &$values, $engine ) {
		if ( $values && ! empty( $args['sobp_test'] ) ) {
			foreach ( $engine->get_conditions( $values, $query->get_table_name( 'orders' ) . '.id', 'hpos' ) as $c ) {
				$clauses['where'] .= " AND {$c}";
			}
		}
		return $clauses;
	},
	10,
	3
);
add_filter(
	'posts_where',
	function ( $where, $query ) use ( &$values, $engine, $wpdb ) {
		if ( $values && $query->get( 'sobp_test' ) ) {
			foreach ( $engine->get_conditions( $values, "{$wpdb->posts}.ID", 'posts' ) as $c ) {
				$where .= " AND {$c}";
			}
		}
		return $where;
	},
	10,
	2
);

$run_new = function ( $v, $source ) use ( &$values, $base ) {
	$force  = function () use ( $source ) {
		return 'lookup' === $source;
	};
	add_filter( 'wc_search_orders_by_product_lookup_available', $force );
	$values = $v;
	$t      = microtime( true );
	$r      = wc_get_orders( $base + array( 'sobp_test' => 1 ) );
	$ms     = ( microtime( true ) - $t ) * 1000;
	$values = null;
	remove_filter( 'wc_search_orders_by_product_lookup_available', $force );
	return array( $ms, (int) $r->total );
};

// 3.5 path: build the id list in PHP, then hand it to the list query.
$run_old = function ( $v ) use ( $admin, $inst, $base, $storage ) {
	$call = function ( $name, ...$args ) use ( $admin, $inst ) {
		$m = $admin->getMethod( $name );
		$m->setAccessible( true );
		return $m->isStatic() ? $m->invoke( null, ...$args ) : $m->invoke( $inst, ...$args );
	};
	$h   = 'hpos' === $storage ? '_hpos' : '';
	$t   = microtime( true );
	$ids = $call( 'hpos' === $storage ? 'get_order_ids_hpos' : 'get_order_ids' );
	if ( ! empty( $v['product_type'] ) ) {
		$ids = $call( 'order_ids_by_product_type' . $h, $v['product_type'] );
	}
	if ( $ids && ! empty( $v['product_ids'] ) ) {
		$ids = $call( 'filter_orders_containing_products' . $h, $ids, $v['product_ids'] );
	}
	if ( $ids && ! empty( $v['category_ids'] ) ) {
		$ids = $call( 'filter_orders_containing_product_categories' . $h, $ids, $v['category_ids'] );
	}
	$ids = $ids ? array_values( array_unique( array_map( 'absint', $ids ) ) ) : array( 0 );
	$r   = wc_get_orders( $base + array( 'id' => $ids ) );
	return array( ( microtime( true ) - $t ) * 1000, (int) $r->total );
};

$median = function ( $fn, ...$args ) {
	$runs = array();
	for ( $i = 0; $i < 3; $i++ ) {
		wp_cache_flush();
		$runs[] = $fn( ...$args );
	}
	usort(
		$runs,
		function ( $a, $b ) {
			return $a[0] <=> $b[0];
		}
	);
	return $runs[1];
};

$clothing = get_term_by( 'slug', 'clothing', 'product_cat' );
$tshirts  = get_term_by( 'slug', 'tshirts', 'product_cat' );
$cases    = array(
	'simple product'          => array( 'product_ids' => array( 10000123 ) ),
	'variable parent'         => array( 'product_ids' => array( 10004600 ) ),
	'one variation'           => array( 'product_ids' => array( 10010401 ) ),
	'unordered product'       => array( 'product_ids' => array( 10004999 + 1 ) ),
	'category (leaf)'         => array( 'category_ids' => array( $tshirts->term_id ) ),
	'category with children'  => array( 'category_ids' => array( $clothing->term_id ) ),
	'type variable'           => array( 'product_type' => 'variable' ),
	'product + category'      => array( 'product_ids' => array( 10000123 ), 'category_ids' => array( $tshirts->term_id ) ),
);

$total = (int) wc_get_orders( $base )->total;
printf( "storage=%s orders in list=%d mysql=%s\n", $storage, $total, $wpdb->db_server_info() );
printf( "%-24s %10s %8s | %10s %8s | %10s %8s\n", 'case', '3.5 ms', 'found', 'lookup ms', 'found', 'items ms', 'found' );
$worst = 0;
foreach ( $cases as $label => $v ) {
	$o = $median( $run_old, $v );
	$l = $median( $run_new, $v, 'lookup' );
	$i = $median( $run_new, $v, 'items' );
	$worst = max( $worst, $l[0], $i[0] );
	printf( "%-24s %10.0f %8d | %10.0f %8d | %10.0f %8d\n", $label, $o[0], $o[1], $l[0], $l[1], $i[0], $i[1] );
}
printf( "worst 4.0 query time: %.0f ms (target < 2000 ms for the whole page)\n", $worst );
