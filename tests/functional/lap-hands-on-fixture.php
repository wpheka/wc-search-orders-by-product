<?php
/**
 * Laptop only (queue 2026-10-22 item 3.5): data for lap-hands-on.cjs. Not part of the suite.
 *
 *   wp eval-file lap-hands-on-fixture.php create   -- prints the ids as JSON
 *   wp eval-file lap-hands-on-fixture.php clean    -- deletes everything named "ZZH"
 *
 * A category with a sub-category, a simple product (SKU ZZH-MUG) and a variable product with
 * two variations (ZZH-SHIRT-S and ZZH-SHIRT-L), then three orders:
 * o1 = mug, o2 = shirt small, o3 = shirt large + mug.
 *
 * @package WC_Search_Orders_By_Product
 */

$zzh_mode = $args[0] ?? '';

if ( 'clean' === $zzh_mode ) {
	$zzh_orders = wc_get_orders(
		array(
			'billing_email' => 'zzh-hands-on@example.invalid',
			'limit'         => -1,
			'status'        => 'any',
		)
	);
	foreach ( $zzh_orders as $zzh_order ) {
		$zzh_order->delete( true );
	}
	$zzh_products = wc_get_products(
		array(
			'limit'  => -1,
			'status' => 'any',
			'sku'    => 'ZZH-',
		)
	);
	foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 's' => 'ZZH' ) ) as $zzh_post ) {
		$zzh_product = wc_get_product( $zzh_post->ID );
		if ( $zzh_product ) {
			$zzh_product->delete( true );
		}
	}
	foreach ( array( 'zzh-travel', 'zzh-mugs' ) as $zzh_slug ) {
		$zzh_term = get_term_by( 'slug', $zzh_slug, 'product_cat' );
		if ( $zzh_term ) {
			wp_delete_term( $zzh_term->term_id, 'product_cat' );
		}
	}
	echo wp_json_encode( array( 'deleted_orders' => count( $zzh_orders ) ) );
	return;
}

$zzh_parent = wp_insert_term( 'ZZH Mugs', 'product_cat', array( 'slug' => 'zzh-mugs' ) );
$zzh_child  = wp_insert_term( 'ZZH Travel', 'product_cat', array( 'slug' => 'zzh-travel', 'parent' => $zzh_parent['term_id'] ) );

$zzh_mug = new WC_Product_Simple();
$zzh_mug->set_name( 'ZZH Mug' );
$zzh_mug->set_sku( 'ZZH-MUG' );
$zzh_mug->set_regular_price( '12' );
$zzh_mug->set_category_ids( array( $zzh_parent['term_id'] ) );
$zzh_mug->set_status( 'publish' );
$zzh_mug->save();

$zzh_shirt = new WC_Product_Variable();
$zzh_shirt->set_name( 'ZZH Shirt' );
$zzh_shirt->set_category_ids( array( $zzh_child['term_id'] ) );
$zzh_shirt->set_status( 'publish' );
$zzh_attr = new WC_Product_Attribute();
$zzh_attr->set_name( 'Size' );
$zzh_attr->set_options( array( 'Small', 'Large' ) );
$zzh_attr->set_visible( true );
$zzh_attr->set_variation( true );
$zzh_shirt->set_attributes( array( $zzh_attr ) );
$zzh_shirt->save();
$zzh_var = array();
foreach ( array( 'Small' => 'ZZH-SHIRT-S', 'Large' => 'ZZH-SHIRT-L' ) as $zzh_size => $zzh_sku ) {
	$zzh_v = new WC_Product_Variation();
	$zzh_v->set_parent_id( $zzh_shirt->get_id() );
	$zzh_v->set_attributes( array( 'size' => $zzh_size ) );
	$zzh_v->set_sku( $zzh_sku );
	$zzh_v->set_regular_price( '20' );
	$zzh_v->save();
	$zzh_var[ $zzh_size ] = $zzh_v->get_id();
}
WC_Product_Variable::sync( $zzh_shirt->get_id() );

$zzh_order = static function ( $lines ) {
	$order = wc_create_order();
	foreach ( $lines as $product_id ) {
		$order->add_product( wc_get_product( $product_id ), 1 );
	}
	$order->set_billing_email( 'zzh-hands-on@example.invalid' );
	$order->set_billing_first_name( 'Zzh' );
	$order->calculate_totals();
	$order->set_status( 'processing' );
	$order->save();
	return $order->get_id();
};

echo wp_json_encode(
	array(
		'mug'   => $zzh_mug->get_id(),
		'shirt' => $zzh_shirt->get_id(),
		'small' => $zzh_var['Small'],
		'large' => $zzh_var['Large'],
		'cat'   => $zzh_parent['term_id'],
		'sub'   => $zzh_child['term_id'],
		'o1'    => $zzh_order( array( $zzh_mug->get_id() ) ),
		'o2'    => $zzh_order( array( $zzh_var['Small'] ) ),
		'o3'    => $zzh_order( array( $zzh_var['Large'], $zzh_mug->get_id() ) ),
	)
);
