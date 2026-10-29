<?php
/**
 * The declared requirements agree: the plugin header, the readme header and the
 * readme's "Minimum Requirements" list name the same WordPress, WooCommerce and
 * PHP versions. WooCommerce must be at least 7.9, the first version with
 * woocommerce_orders_table_query_clauses: before it the HPOS screen shows the
 * filters but ignores them (4.1.1 test pass, 2026-10-29).
 */
require __DIR__ . '/lib.php';

$sobp_r_root   = dirname( __DIR__, 2 );
$sobp_r_header = get_file_data(
	$sobp_r_root . '/wc-search-orders-by-product.php',
	array(
		'wp'  => 'Requires at least',
		'php' => 'Requires PHP',
		'wc'  => 'WC requires at least',
	)
);
$sobp_r_readme_file = file_get_contents( $sobp_r_root . '/readme.txt' );
$sobp_r_readme      = get_file_data(
	$sobp_r_root . '/readme.txt',
	array(
		'wp'  => 'Requires at least',
		'php' => 'Requires PHP',
	)
);

// "= Minimum Requirements =" up to the next blank line after the list.
$sobp_r_list = array();
if ( preg_match( '/= Minimum Requirements =\s*\n((?:\* .+\n)+)/', $sobp_r_readme_file, $sobp_r_m ) ) {
	foreach ( array( 'wp' => 'WordPress', 'wc' => 'WooCommerce', 'php' => 'PHP' ) as $sobp_r_key => $sobp_r_name ) {
		if ( preg_match( '/^\* ' . $sobp_r_name . ' ([0-9.]+) or later$/m', $sobp_r_m[1], $sobp_r_v ) ) {
			$sobp_r_list[ $sobp_r_key ] = $sobp_r_v[1];
		}
	}
}

foreach ( array( 'wp' => 'WordPress', 'wc' => 'WooCommerce', 'php' => 'PHP' ) as $sobp_r_key => $sobp_r_name ) {
	$sobp_r_values = array(
		'plugin header' => $sobp_r_header[ $sobp_r_key ],
		'readme list'   => $sobp_r_list[ $sobp_r_key ] ?? '',
	);
	if ( 'wc' !== $sobp_r_key ) {
		$sobp_r_values['readme header'] = $sobp_r_readme[ $sobp_r_key ];
	}
	sobp_t_result(
		"requirements: {$sobp_r_name} minimum is the same in the plugin header and the readme",
		'' !== $sobp_r_header[ $sobp_r_key ] && 1 === count( array_unique( $sobp_r_values ) ),
		wp_json_encode( $sobp_r_values )
	);
}

sobp_t_result(
	'requirements: WooCommerce minimum is 7.9 or later (the HPOS filters need woocommerce_orders_table_query_clauses)',
	version_compare( $sobp_r_header['wc'], '7.9', '>=' ),
	'header says ' . $sobp_r_header['wc']
);
sobp_t_result(
	'requirements: the WooCommerce running the suite meets the declared minimum',
	defined( 'WC_VERSION' ) && version_compare( WC_VERSION, $sobp_r_header['wc'], '>=' ),
	'running ' . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'none' ) . ', declared ' . $sobp_r_header['wc']
);
