<?php
/**
 * Order queries outside the orders screen must not be narrowed by the filter
 * values. The plugin once replaced every HPOS order query's results whenever
 * product_id was in the URL, which broke WooCommerce Subscriptions.
 */
require __DIR__ . '/lib.php';
$ids  = sobp_t_ids();
$mine = array_map( 'intval', array( $ids['o1'], $ids['o2'], $ids['o3'], $ids['o4'] ) );
sort( $mine );

$_GET['product_id']          = (string) $ids['C']; // a product no order contains
$_GET['search_product_type'] = 'grouped';
$_GET['search_product_cat']  = (string) $ids['cat_a'];
$found = wc_get_orders( array( 'billing_email' => 'zz-sobp@example.invalid', 'limit' => -1, 'return' => 'ids', 'status' => array( 'processing', 'completed' ) ) );
$found = array_map( 'intval', $found );
sort( $found );
unset( $_GET['product_id'], $_GET['search_product_type'], $_GET['search_product_cat'] );
sobp_t_result( 'other order queries are not narrowed by filter values in the URL', $found === $mine, 'got ' . wp_json_encode( $found ) . ' want ' . wp_json_encode( $mine ) );
