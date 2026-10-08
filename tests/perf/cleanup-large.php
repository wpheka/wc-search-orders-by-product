<?php
/**
 * Remove exactly what seed-large.php inserted, and nothing else.
 *
 * Run: wp eval-file tests/perf/cleanup-large.php
 *
 * The seed uses fixed id ranges, which raises MySQL's id counters. Anything
 * another process creates meanwhile (another session's test, a renewal order)
 * gets an id above those ranges. An earlier version deleted every id from
 * 10,000,000 up and so removed another session's test orders (2026-10-08).
 * Now only the seed's exact ranges are touched, and only rows that also carry
 * the seed's markers.
 */

global $wpdb;

$p = $wpdb->prefix;
$n = array();

// The seed's ranges (see seed-large.php).
$products   = 'BETWEEN 10000000 AND 10004999';
$variations = 'BETWEEN 10010000 AND 10011999';
$orders     = 'BETWEEN 10100000 AND 10199999';
$items      = 'BETWEEN 10000000 AND 10199999';

// Orders and items: in range and marked (post name "order-perf-N", item name "SOBP Perf Product N").
$order_ids = "SELECT ID FROM {$wpdb->posts} WHERE ID {$orders} AND post_type = 'shop_order' AND post_name LIKE 'order-perf-%'";
$item_ids  = "SELECT order_item_id FROM {$p}woocommerce_order_items WHERE order_item_id {$items} AND order_item_name LIKE 'SOBP Perf Product %' AND order_id {$orders}";

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed ranges and markers.
$item_list  = implode( ',', array_map( 'absint', $wpdb->get_col( $item_ids ) ) );
$order_list = implode( ',', array_map( 'absint', $wpdb->get_col( $order_ids ) ) );

if ( '' !== $item_list ) {
	$n['itemmeta'] = $wpdb->query( "DELETE FROM {$p}woocommerce_order_itemmeta WHERE order_item_id IN ( {$item_list} )" );
	$n['lookup']   = $wpdb->query( "DELETE FROM {$p}wc_order_product_lookup WHERE order_item_id IN ( {$item_list} )" );
	$n['items']    = $wpdb->query( "DELETE FROM {$p}woocommerce_order_items WHERE order_item_id IN ( {$item_list} )" );
}
if ( '' !== $order_list ) {
	$n['addresses']   = $wpdb->query( "DELETE FROM {$p}wc_order_addresses WHERE order_id IN ( {$order_list} ) AND email LIKE 'perf%@example.com'" );
	$n['operational'] = $wpdb->query( "DELETE FROM {$p}wc_order_operational_data WHERE order_id IN ( {$order_list} ) AND order_key LIKE 'wc_order_perf%'" );
	$n['orders']      = $wpdb->query( "DELETE FROM {$p}wc_orders WHERE id IN ( {$order_list} ) AND billing_email LIKE 'perf%@example.com'" );
	$n['order_posts'] = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ( {$order_list} )" );
}

// Products and variations: in range and marked (post name "sobp-perf-...").
$product_ids = "SELECT ID FROM {$wpdb->posts} WHERE ( ID {$products} OR ID {$variations} ) AND post_type IN ( 'product', 'product_variation' ) AND post_name LIKE 'sobp-perf-%'";
$product_list = implode( ',', array_map( 'absint', $wpdb->get_col( $product_ids ) ) );
if ( '' !== $product_list ) {
	$n['terms']         = $wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ( {$product_list} )" );
	$n['postmeta']      = $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ( {$product_list} )" );
	$n['meta_lookup']   = $wpdb->query( "DELETE FROM {$p}wc_product_meta_lookup WHERE product_id IN ( {$product_list} ) AND sku LIKE 'PERF-%'" );
	$n['product_posts'] = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ( {$product_list} )" );
}
$wpdb->query( "DROP TABLE IF EXISTS {$p}sobp_perf_seq" );
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

delete_transient( 'wc_search_orders_by_product_lookup_coverage' );
wp_cache_flush();

echo wp_json_encode( $n ), "\n";
