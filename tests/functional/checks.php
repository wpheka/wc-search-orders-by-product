<?php
/**
 * Order queries outside the orders screen must not be narrowed by the filter
 * values. The plugin once replaced every HPOS order query's results whenever
 * product_id was in the URL, which broke WooCommerce Subscriptions.
 */
require __DIR__ . '/lib.php';
$ids  = sobp_t_ids();
$mine = array_map( 'intval', array( $ids['o1'], $ids['o2'], $ids['o3'], $ids['o4'], $ids['o6'] ) );
sort( $mine );

$_GET['product_id']          = (string) $ids['C']; // a product no order contains
$_GET['search_product_type'] = 'grouped';
$_GET['search_product_cat']  = (string) $ids['cat_a'];
$_GET['search_sku']          = 'ZZSOBP-NOPE';
$_GET['search_payment_method'] = 'zz-none';
$found = wc_get_orders( array( 'billing_email' => 'zz-sobp@example.invalid', 'limit' => -1, 'return' => 'ids', 'status' => array( 'processing', 'completed' ) ) );
$found = array_map( 'intval', $found );
sort( $found );
unset( $_GET['product_id'], $_GET['search_product_type'], $_GET['search_product_cat'], $_GET['search_sku'], $_GET['search_payment_method'] );
sobp_t_result( ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ? 'hpos' : 'legacy' ) . ': other order queries are not narrowed by filter values in the URL', $found === $mine, 'got ' . wp_json_encode( $found ) . ' want ' . wp_json_encode( $mine ) );

// Both match sources (the Analytics lookup table and order item meta) must
// return the same orders for every filter, on whichever storage is active.
$engine  = wc_search_orders_by_product()->engine ?? new WC_Search_Orders_By_Product_Engine();
$storage = 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ? 'hpos' : 'posts';
$cases   = array(
	array( 'product_ids' => array( (int) $ids['A'] ) ),
	array( 'product_ids' => array( (int) $ids['V'] ) ),
	array( 'product_ids' => array( (int) $ids['VS'] ) ),
	array( 'category_ids' => array( (int) $ids['cat_b'] ) ),
	array( 'product_type' => 'variable' ),
	array( 'sku' => 'ZZSOBP-V' ),
	array( 'product_ids' => array( (int) $ids['A'] ), 'payment_method' => 'cod' ),
);
$diff = array();
foreach ( $cases as $values ) {
	$got = array();
	foreach ( array( true, false ) as $lookup ) {
		$force = $lookup ? '__return_true' : '__return_false';
		add_filter( 'wc_search_orders_by_product_lookup_available', $force );
		global $wpdb;
		$col = 'hpos' === $storage ? "{$wpdb->prefix}wc_orders.id" : "{$wpdb->posts}.ID";
		$sql = 'hpos' === $storage
			? "SELECT id FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND status <> 'trash' AND billing_email = 'zz-sobp@example.invalid'"
			: "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_billing_email' AND pm.meta_value = 'zz-sobp@example.invalid' WHERE p.post_type = 'shop_order' AND p.post_status <> 'trash'";
		if ( 'posts' === $storage ) {
			$col = 'p.ID';
		}
		foreach ( $engine->get_conditions( $values, $col, $storage ) as $c ) {
			$sql .= " AND {$c}";
		}
		$found = array_map( 'intval', $wpdb->get_col( $sql ) );
		sort( $found );
		$got[] = $found;
		remove_filter( 'wc_search_orders_by_product_lookup_available', $force );
	}
	if ( $got[0] !== $got[1] ) {
		$diff[] = wp_json_encode( $values ) . ' lookup ' . wp_json_encode( $got[0] ) . ' items ' . wp_json_encode( $got[1] );
	}
}
sobp_t_result( ( 'posts' === $storage ? 'legacy' : $storage ) . ": lookup table and order item meta give the same orders", empty( $diff ), implode( '; ', $diff ) );

// Stalled background sync: an order missing from the lookup table, changed
// three hours ago, with the coverage check last run five hours ago, must still
// be found through order item meta (CodeRabbit finding on 4.0).
$o4_lookup = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wc_order_product_lookup WHERE order_id = %d", $ids['o4'] ), ARRAY_A );
$wpdb->delete( "{$wpdb->prefix}wc_order_product_lookup", array( 'order_id' => $ids['o4'] ) );
$three_hours_ago = gmdate( 'Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS );
$wpdb->update( "{$wpdb->prefix}wc_orders", array( 'date_updated_gmt' => $three_hours_ago ), array( 'id' => $ids['o4'] ) );
$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => $three_hours_ago ), array( 'ID' => $ids['o4'] ) );
set_transient( WC_Search_Orders_By_Product_Engine::COVERAGE_TRANSIENT, array( $storage => 'lookup', $storage . '_at' => time() - 5 * HOUR_IN_SECONDS ), HOUR_IN_SECONDS );
$col   = 'hpos' === $storage ? "{$wpdb->prefix}wc_orders.id" : 'p.ID';
$sql   = 'hpos' === $storage
	? "SELECT id FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND status <> 'trash' AND billing_email = 'zz-sobp@example.invalid'"
	: "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_billing_email' AND pm.meta_value = 'zz-sobp@example.invalid' WHERE p.post_type = 'shop_order' AND p.post_status <> 'trash'";
foreach ( $engine->get_conditions( array( 'product_ids' => array( (int) $ids['D'] ) ), $col, $storage ) as $c ) {
	$sql .= " AND {$c}";
}
$found = array_map( 'intval', $wpdb->get_col( $sql ) );
sobp_t_result( ( 'posts' === $storage ? 'legacy' : $storage ) . ': an order the stalled lookup sync missed is still found', array( (int) $ids['o4'] ) === $found, 'found ' . wp_json_encode( $found ) . ', source ' . $engine->last_source );
foreach ( $o4_lookup as $row ) {
	$wpdb->insert( "{$wpdb->prefix}wc_order_product_lookup", $row );
}
delete_transient( WC_Search_Orders_By_Product_Engine::COVERAGE_TRANSIENT );

// Stale lookup row: WooCommerce refreshes the lookup table in the background, so for an
// order changed a moment ago it can still list a line that was removed. o2 (only the S
// variation) gets a row for product A and is marked as just changed: a lookup search for A
// must not return it, and must give what order item meta gives (Pro review M2).
$wpdb->insert(
	"{$wpdb->prefix}wc_order_product_lookup",
	array( 'order_item_id' => 999999002, 'order_id' => $ids['o2'], 'product_id' => $ids['A'], 'variation_id' => 0, 'customer_id' => 0, 'date_created' => current_time( 'mysql' ), 'product_qty' => 1, 'product_net_revenue' => 10, 'product_gross_revenue' => 10, 'coupon_amount' => 0, 'tax_amount' => 0, 'shipping_amount' => 0, 'shipping_tax_amount' => 0 )
);
$just_now = gmdate( 'Y-m-d H:i:s' );
$wpdb->update( "{$wpdb->prefix}wc_orders", array( 'date_updated_gmt' => $just_now ), array( 'id' => $ids['o2'] ) );
$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => $just_now ), array( 'ID' => $ids['o2'] ) );
set_transient( WC_Search_Orders_By_Product_Engine::COVERAGE_TRANSIENT, array( $storage => 'lookup', $storage . '_at' => time() ), HOUR_IN_SECONDS );
$stale = array();
foreach ( array( '__return_true', '__return_false' ) as $force ) {
	add_filter( 'wc_search_orders_by_product_lookup_available', $force );
	$sql_stale = $sql_base = 'hpos' === $storage
		? "SELECT id FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND status <> 'trash' AND billing_email = 'zz-sobp@example.invalid'"
		: "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_billing_email' AND pm.meta_value = 'zz-sobp@example.invalid' WHERE p.post_type = 'shop_order' AND p.post_status <> 'trash'";
	foreach ( $engine->get_conditions( array( 'product_ids' => array( (int) $ids['A'] ) ), $col, $storage ) as $c ) {
		$sql_stale .= " AND {$c}";
	}
	$found_stale = array_map( 'intval', $wpdb->get_col( $sql_stale ) );
	sort( $found_stale );
	$stale[] = $found_stale;
	remove_filter( 'wc_search_orders_by_product_lookup_available', $force );
}
$wpdb->delete( "{$wpdb->prefix}wc_order_product_lookup", array( 'order_item_id' => 999999002 ) );
delete_transient( WC_Search_Orders_By_Product_Engine::COVERAGE_TRANSIENT );
sobp_t_result( ( 'posts' === $storage ? 'legacy' : $storage ) . ': a stale lookup row for a just-changed order is not a match', ! in_array( (int) $ids['o2'], $stale[0], true ) && $stale[0] === $stale[1], 'lookup ' . wp_json_encode( $stale[0] ) . ' items ' . wp_json_encode( $stale[1] ) );

// More recently changed orders than the limit (a mass update): the request is decided through
// order item meta, so the SQL carries no id list at all and stays small, and the result is the
// same. The limit is lowered to 2; every test order was changed just now (review F1).
set_transient( WC_Search_Orders_By_Product_Engine::COVERAGE_TRANSIENT, array( $storage => 'lookup', $storage . '_at' => time() ), HOUR_IN_SECONDS );
$sobp_limit = static function () {
	return 2;
};
$over       = array();
foreach ( array( '__return_true', '__return_false' ) as $force ) {
	add_filter( 'wc_search_orders_by_product_lookup_available', $force );
	add_filter( 'wc_search_orders_by_product_recent_limit', $sobp_limit );
	$sql_over   = 'hpos' === $storage
		? "SELECT id FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND status <> 'trash' AND billing_email = 'zz-sobp@example.invalid'"
		: "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_billing_email' AND pm.meta_value = 'zz-sobp@example.invalid' WHERE p.post_type = 'shop_order' AND p.post_status <> 'trash'";
	$conditions = $engine->get_conditions( array( 'product_ids' => array( (int) $ids['A'] ), 'category_ids' => array( (int) $ids['cat_a'] ) ), $col, $storage );
	foreach ( $conditions as $c ) {
		$sql_over .= " AND {$c}";
	}
	$found_over = array_map( 'intval', $wpdb->get_col( $sql_over ) );
	sort( $found_over );
	$over[] = array(
		'source' => $engine->last_source,
		'sql'    => implode( ' AND ', $conditions ),
		'found'  => $found_over,
	);
	remove_filter( 'wc_search_orders_by_product_recent_limit', $sobp_limit );
	remove_filter( 'wc_search_orders_by_product_lookup_available', $force );
}
delete_transient( WC_Search_Orders_By_Product_Engine::COVERAGE_TRANSIENT );
$recent_count = (int) $wpdb->get_var(
	'hpos' === $storage
		? $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND date_updated_gmt >= %s", gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS ) )
		: $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_modified_gmt >= %s", gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS ) )
);
sobp_t_result(
	( 'posts' === $storage ? 'legacy' : $storage ) . ': more recent orders than the limit: item meta decides, the SQL has no id list and stays small',
	$recent_count > 2 && 'items' === $over[0]['source'] && $over[0]['sql'] === $over[1]['sql'] && strlen( $over[0]['sql'] ) < 2000 && false === strpos( $over[0]['sql'], 'NOT IN' ) && $over[0]['found'] === $over[1]['found'] && in_array( (int) $ids['o1'], $over[0]['found'], true ),
	'recent ' . $recent_count . ', source ' . $over[0]['source'] . ', sql length ' . strlen( $over[0]['sql'] ) . ' vs ' . strlen( $over[1]['sql'] ) . ', found ' . wp_json_encode( $over[0]['found'] ) . ' vs ' . wp_json_encode( $over[1]['found'] )
);
