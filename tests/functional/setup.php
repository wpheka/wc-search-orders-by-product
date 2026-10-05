<?php
/**
 * Test catalogue and orders, all prefixed "ZZ SOBP":
 *   cat A: simple product A          cat B: variable product V (S, M), simple C (never ordered)
 *   o1 processing [A]   o2 completed [V-S]   o3 processing [A, V-M]   o4 processing [D]
 *   o5 trashed [A]
 * plus an administrator for the browser cases. Settings and the review state
 * are saved here and restored by cleanup.php.
 */
require __DIR__ . '/lib.php';

global $wpdb;
$ids = array(
	'max_order'    => (int) $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}wc_orders" ),
	'settings'     => get_option( 'sobp_settings', '__unset__' ),
	'search_count' => get_option( 'sobp_filtered_search_count', '__unset__' ),
);

$cat_a = wp_insert_term( 'ZZ SOBP Cat A', 'product_cat' );
$cat_b = wp_insert_term( 'ZZ SOBP Cat B', 'product_cat' );
$ids['cat_a'] = $cat_a['term_id'];
$ids['cat_b'] = $cat_b['term_id'];

$simple = function ( $name, $cat ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_regular_price( '10' );
	$p->set_category_ids( array( $cat ) );
	$p->set_status( 'publish' );
	return $p->save();
};
$ids['A'] = $simple( 'ZZ SOBP Product A', $ids['cat_a'] );
$ids['C'] = $simple( 'ZZ SOBP Product C', $ids['cat_b'] );
$ids['D'] = $simple( 'ZZ SOBP Product D', $ids['cat_a'] );

$attr = new WC_Product_Attribute();
$attr->set_name( 'Size' );
$attr->set_options( array( 'S', 'M' ) );
$attr->set_visible( true );
$attr->set_variation( true );
$v = new WC_Product_Variable();
$v->set_name( 'ZZ SOBP Variable V' );
$v->set_attributes( array( $attr ) );
$v->set_category_ids( array( $ids['cat_b'] ) );
$v->set_status( 'publish' );
$ids['V'] = $v->save();
foreach ( array( 'S' => 'VS', 'M' => 'VM' ) as $size => $key ) {
	$var = new WC_Product_Variation();
	$var->set_parent_id( $ids['V'] );
	$var->set_attributes( array( 'size' => $size ) );
	$var->set_regular_price( '10' );
	$var->set_status( 'publish' );
	$ids[ $key ] = $var->save();
}
WC_Product_Variable::sync( $ids['V'] );

$order = function ( $status, $products ) use ( &$ids ) {
	$o = wc_create_order();
	$o->set_billing_email( 'zz-sobp@example.invalid' );
	$o->set_billing_first_name( 'Zz' );
	foreach ( $products as $key ) {
		$o->add_product( wc_get_product( $ids[ $key ] ), 1 );
	}
	$o->calculate_totals();
	$o->set_status( $status );
	$o->save();
	return $o->get_id();
};
$ids['o1'] = $order( 'processing', array( 'A' ) );
$ids['o2'] = $order( 'completed', array( 'VS' ) );
$ids['o3'] = $order( 'processing', array( 'A', 'VM' ) );
$ids['o4'] = $order( 'processing', array( 'D' ) );
$ids['o5'] = $order( 'processing', array( 'A' ) );
wc_get_order( $ids['o5'] )->delete( false ); // to the trash

// All filters on, whatever the site had.
update_option( 'sobp_settings', array( 'search_orders_by_product_type' => 1, 'search_orders_by_product_category' => 1 ) );

$uid = wp_insert_user( array( 'user_login' => 'zz_sobp_admin', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'zz-sobp-admin@example.invalid', 'role' => 'administrator' ) );
$exp = time() + 2 * HOUR_IN_SECONDS;
$ids['admin']   = $uid;
$ids['cookies'] = array(
	array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $exp, 'auth' ), 'domain' => 'localhost', 'path' => wp_parse_url( admin_url(), PHP_URL_PATH ) ),
	array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $exp, 'logged_in' ), 'domain' => 'localhost', 'path' => wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ),
);
$ids['admin_url'] = admin_url();
$ids['wcs']       = class_exists( 'WC_Subscriptions' );
sobp_t_save_ids( $ids );
echo "created catalogue, 5 orders and an administrator\n";
