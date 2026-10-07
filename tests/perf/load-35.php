<?php
/**
 * Load the 3.5 filter code from git as WC_Search_Orders_By_Product_Admin_35,
 * so the 4.0 engine can be compared with the release it replaces.
 */
if ( ! class_exists( 'WC_Search_Orders_By_Product_Admin_35' ) ) {
	$sobp_repo = dirname( __DIR__, 2 );
	$sobp_code = shell_exec( 'git -C ' . escapeshellarg( $sobp_repo ) . ' show 3.5:includes/admin/class-wc-search-orders-by-product-admin.php 2>/dev/null' );
	if ( ! $sobp_code ) {
		$sobp_code = shell_exec( 'git -C ' . escapeshellarg( $sobp_repo ) . ' show cb2c706:includes/admin/class-wc-search-orders-by-product-admin.php' );
	}
	$sobp_code = str_replace( 'class WC_Search_Orders_By_Product_Admin', 'class WC_Search_Orders_By_Product_Admin_35', $sobp_code );
	$sobp_code = preg_replace( '/^new WC_Search_Orders_By_Product_Admin\(\);\s*$/m', '', $sobp_code );
	$sobp_file = tempnam( sys_get_temp_dir(), 'sobp35' ) . '.php';
	file_put_contents( $sobp_file, $sobp_code );
	require $sobp_file;
	unlink( $sobp_file );
}
