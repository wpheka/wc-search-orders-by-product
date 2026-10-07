<?php
/** Deletes the test catalogue, orders and user; restores settings and counters. */
require __DIR__ . '/lib.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$ids = sobp_t_ids();
if ( ! $ids ) {
	echo "nothing to clean up\n";
	return;
}
global $wpdb;
$deleted = 0;
foreach ( $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND billing_email = 'zz-sobp@example.invalid'" ) as $oid ) {
	$o = wc_get_order( $oid );
	if ( $o ) {
		$o->delete( true );
		$deleted++;
	}
}
foreach ( array( 'VS', 'VM', 'V', 'A', 'C', 'D', 'E' ) as $key ) {
	if ( ! empty( $ids[ $key ] ) && ( $p = wc_get_product( $ids[ $key ] ) ) ) {
		$p->delete( true );
	}
}
foreach ( array( 'cat_bc', 'cat_a', 'cat_b' ) as $key ) {
	if ( ! empty( $ids[ $key ] ) ) {
		wp_delete_term( $ids[ $key ], 'product_cat' );
	}
}
foreach ( array( 'admin', 'manager' ) as $key ) {
	if ( empty( $ids[ $key ] ) ) {
		continue;
	}
	// Visiting the Dashboard makes WordPress create a Quick Draft auto-draft
	// for the user; wp_delete_user() would move it to the trash, not delete it.
	foreach ( get_posts( array( 'author' => $ids[ $key ], 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	wp_delete_user( $ids[ $key ] );
}
delete_transient( 'wc_search_orders_by_product_lookup_coverage' );
foreach ( array( 'settings' => 'sobp_settings', 'search_count' => 'sobp_filtered_search_count' ) as $key => $option ) {
	if ( '__unset__' === ( $ids[ $key ] ?? '__unset__' ) ) {
		delete_option( $option );
	} else {
		update_option( $option, $ids[ $key ] );
	}
}
$left = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE 'ZZ SOBP%'" );
echo "cleanup: deleted $deleted orders; leftover test posts: $left\n";
