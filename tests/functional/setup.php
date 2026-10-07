<?php
/**
 * Test catalogue and orders, all prefixed "ZZ SOBP":
 *   cat A: simple product A          cat B: variable product V (S, M), simple C (never ordered)
 *   o1 processing [A]   o2 completed [V-S]   o3 processing [A, V-M]   o4 processing [D]
 *   o5 trashed [A]
 *   cat B Child (under cat B): simple E    o6 processing [E]
 * Product SKUs: A ZZSOBP-A, D ZZSOBP-D, variations ZZSOBP-V-S and ZZSOBP-V-M.
 * Payment / billing country / shipping method:
 *   o1 cod US flat_rate   o2 bacs CA -   o3 cod US free_shipping   o4 bacs GB -   o6 bacs CA -
 * plus an administrator and a shop manager for the browser cases. Settings and the review state
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
$cat_bc       = wp_insert_term( 'ZZ SOBP Cat B Child', 'product_cat', array( 'parent' => $ids['cat_b'] ) );
$ids['cat_bc'] = $cat_bc['term_id'];

$simple = function ( $name, $cat, $sku = '' ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_sku( $sku );
	$p->set_regular_price( '10' );
	$p->set_category_ids( array( $cat ) );
	$p->set_status( 'publish' );
	return $p->save();
};
$ids['A'] = $simple( 'ZZ SOBP Product A', $ids['cat_a'], 'ZZSOBP-A' );
$ids['C'] = $simple( 'ZZ SOBP Product C', $ids['cat_b'] );
$ids['D'] = $simple( 'ZZ SOBP Product D', $ids['cat_a'], 'ZZSOBP-D' );
$ids['E'] = $simple( 'ZZ SOBP Product E', $ids['cat_bc'] );

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
	$var->set_sku( 'ZZSOBP-V-' . $size );
	$var->set_regular_price( '10' );
	$var->set_status( 'publish' );
	$ids[ $key ] = $var->save();
}
WC_Product_Variable::sync( $ids['V'] );

$order = function ( $status, $products, $payment = 'bacs', $country = 'US', $shipping = '' ) use ( &$ids ) {
	$o = wc_create_order();
	$o->set_billing_email( 'zz-sobp@example.invalid' );
	$o->set_billing_first_name( 'Zz' );
	$o->set_billing_country( $country );
	$o->set_payment_method( $payment );
	foreach ( $products as $key ) {
		$o->add_product( wc_get_product( $ids[ $key ] ), 1 );
	}
	if ( $shipping ) {
		$line = new WC_Order_Item_Shipping();
		$line->set_method_title( 'ZZ SOBP ' . $shipping );
		$line->set_method_id( $shipping );
		$line->set_total( 0 );
		$o->add_item( $line );
	}
	$o->calculate_totals();
	$o->set_status( $status );
	$o->save();
	return $o->get_id();
};
$ids['o1'] = $order( 'processing', array( 'A' ), 'cod', 'US', 'flat_rate' );
$ids['o2'] = $order( 'completed', array( 'VS' ), 'bacs', 'CA' );
$ids['o3'] = $order( 'processing', array( 'A', 'VM' ), 'cod', 'US', 'free_shipping' );
$ids['o4'] = $order( 'processing', array( 'D' ), 'bacs', 'GB' );
$ids['o5'] = $order( 'processing', array( 'A' ) );
$ids['o6'] = $order( 'processing', array( 'E' ), 'bacs', 'CA' );
wc_get_order( $ids['o5'] )->delete( false ); // to the trash

// All filters on, whatever the site had. The review counter starts at 0 so
// the HPOS cases prove it increases there (it never did before 4.0).
update_option(
	'sobp_settings',
	array(
		'search_orders_by_product_type'     => 'yes',
		'search_orders_by_product_category' => 'yes',
		'search_orders_by_sku'              => 'yes',
		'search_orders_by_payment_method'   => 'yes',
		'search_orders_by_shipping_method'  => 'yes',
		'search_orders_by_billing_country'  => 'yes',
		'purchased_items_column'            => 'yes',
	)
);
update_option( 'sobp_filtered_search_count', 0, false );

$uid = wp_insert_user( array( 'user_login' => 'zz_sobp_admin', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'zz-sobp-admin@example.invalid', 'role' => 'administrator' ) );
$exp = time() + 2 * HOUR_IN_SECONDS;
$ids['admin']   = $uid;
$ids['cookies'] = array(
	array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $exp, 'auth' ), 'domain' => 'localhost', 'path' => wp_parse_url( admin_url(), PHP_URL_PATH ) ),
	array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $exp, 'logged_in' ), 'domain' => 'localhost', 'path' => wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ),
);
$mid = wp_insert_user( array( 'user_login' => 'zz_sobp_manager', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'zz-sobp-manager@example.invalid', 'role' => 'shop_manager' ) );
$ids['manager']         = $mid;
$ids['manager_cookies'] = array(
	array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $mid, $exp, 'auth' ), 'domain' => 'localhost', 'path' => wp_parse_url( admin_url(), PHP_URL_PATH ) ),
	array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $mid, $exp, 'logged_in' ), 'domain' => 'localhost', 'path' => wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ),
);
$ids['admin_url'] = admin_url();
$ids['wcs']       = class_exists( 'WC_Subscriptions' );
sobp_t_save_ids( $ids );
echo "created catalogue, 6 orders, an administrator and a shop manager\n";
