<?php
/**
 * F1: compare the 4.0 engine with the 3.5 filter code on this site's real data.
 *
 * Run: wp eval-file tests/perf/compare.php
 * Read-only. For every product, variation, category, type and a set of
 * combinations, both engines must return the same order ids, with the
 * engine forced onto each match source.
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-wc-search-orders-by-product-engine.php';
if ( ! class_exists( 'WC_Search_Orders_By_Product_Admin' ) ) {
	require_once dirname( __DIR__, 2 ) . '/includes/admin/class-wc-search-orders-by-product-admin.php';
}

global $wpdb;

$admin   = new ReflectionClass( 'WC_Search_Orders_By_Product_Admin' );
$call    = function ( $name, ...$args ) use ( $admin ) {
	$m = $admin->getMethod( $name );
	$m->setAccessible( true );
	return $m->isStatic() ? $m->invoke( null, ...$args ) : $m->invoke( $admin->newInstanceWithoutConstructor(), ...$args );
};
$storage = 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ? 'hpos' : 'posts';
$engine  = new WC_Search_Orders_By_Product_Engine();

// Old pipeline (3.5), same order as sobp_filter_orders_hpos().
$old = function ( $v ) use ( $call, $storage ) {
	$h   = 'hpos' === $storage ? '_hpos' : '';
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
	$ids = array_map( 'intval', $ids );
	sort( $ids );
	return array_values( array_unique( $ids ) );
};

// New engine, restricted to the statuses the old code loaded.
$statuses = "'" . implode( "','", array_map( 'esc_sql', array_keys( wc_get_order_statuses() ) ) ) . "'";
$new      = function ( $v, $source ) use ( $engine, $storage, $wpdb, $statuses ) {
	$force = function () use ( $source ) {
		return WC_Search_Orders_By_Product_Engine::SOURCE_LOOKUP === $source;
	};
	add_filter( 'wc_search_orders_by_product_lookup_available', $force );
	if ( 'hpos' === $storage ) {
		$col = "{$wpdb->prefix}wc_orders.id";
		$sql = "SELECT id FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND status IN ( {$statuses} )";
	} else {
		$col = "{$wpdb->posts}.ID";
		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ( {$statuses} )";
	}
	foreach ( $engine->get_conditions( $v, $col, $storage ) as $c ) {
		$sql .= " AND {$c}";
	}
	remove_filter( 'wc_search_orders_by_product_lookup_available', $force );
	$ids = array_map( 'intval', $wpdb->get_col( $sql ) );
	sort( $ids );
	return $ids;
};

$products = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( 'product', 'product_variation' ) AND post_status <> 'trash'" );
$cats     = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'ids' ) );
$types    = array_keys( wc_get_product_types() );

$cases = array();
foreach ( $products as $p ) {
	$cases[] = array( 'product_ids' => array( (int) $p ) );
}
foreach ( $cats as $c ) {
	$cases[] = array( 'category_ids' => array( (int) $c ) );
}
foreach ( $types as $t ) {
	$cases[] = array( 'product_type' => $t );
}
foreach ( array_slice( $products, 0, 8 ) as $p ) {
	foreach ( $cats as $c ) {
		$cases[] = array( 'product_ids' => array( (int) $p ), 'category_ids' => array( (int) $c ) );
	}
	foreach ( $types as $t ) {
		$cases[] = array( 'product_ids' => array( (int) $p ), 'product_type' => $t );
	}
}

$pass = 0;
$fail = array();
$hits = 0;
foreach ( $cases as $v ) {
	$expected = $old( $v );
	$hits    += count( $expected ) ? 1 : 0;
	foreach ( array( 'lookup', 'items' ) as $source ) {
		$got = $new( $v, $source );
		if ( $got === $expected ) {
			++$pass;
		} else {
			$fail[] = array( 'case' => $v, 'source' => $source, 'old' => $expected, 'new' => $got );
		}
	}
}

printf( "storage=%s cases=%d (with matches: %d) comparisons=%d pass=%d fail=%d\n", $storage, count( $cases ), $hits, count( $cases ) * 2, $pass, count( $fail ) );
foreach ( array_slice( $fail, 0, 15 ) as $f ) {
	echo wp_json_encode( $f ), "\n";
}
