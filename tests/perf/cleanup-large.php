<?php
/**
 * Remove everything seed-large.php inserted (ids from 10,000,000 upwards).
 *
 * Run: wp eval-file tests/perf/cleanup-large.php
 */

global $wpdb;

$p = $wpdb->prefix;
$n = array();

$n['itemmeta']    = $wpdb->query( "DELETE FROM {$p}woocommerce_order_itemmeta WHERE order_item_id >= 10000000" );
$n['items']       = $wpdb->query( "DELETE FROM {$p}woocommerce_order_items WHERE order_item_id >= 10000000" );
$n['lookup']      = $wpdb->query( "DELETE FROM {$p}wc_order_product_lookup WHERE order_item_id >= 10000000" );
$n['addresses']   = $wpdb->query( "DELETE FROM {$p}wc_order_addresses WHERE order_id >= 10000000" );
$n['operational'] = $wpdb->query( "DELETE FROM {$p}wc_order_operational_data WHERE order_id >= 10000000" );
$n['orders']      = $wpdb->query( "DELETE FROM {$p}wc_orders WHERE id >= 10000000" );
$n['terms']       = $wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id >= 10000000" );
$n['postmeta']    = $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id >= 10000000" );
$n['meta_lookup'] = $wpdb->query( "DELETE FROM {$p}wc_product_meta_lookup WHERE product_id >= 10000000" );
$n['posts']       = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID >= 10000000" );
$wpdb->query( "DROP TABLE IF EXISTS {$p}sobp_perf_seq" );

delete_transient( 'wc_search_orders_by_product_lookup_coverage' );
wp_cache_flush();

echo wp_json_encode( $n ), "\n";
