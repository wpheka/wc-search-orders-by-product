<?php
/**
 * F1 timing store: 5,000 products (500 variable with 4 variations each) and
 * 100,000 orders with 2 line items each, inserted with plain SQL.
 *
 * Run: wp eval-file tests/perf/seed-large.php
 * Remove: wp eval-file tests/perf/cleanup-large.php
 *
 * Every row uses ids from 10,000,000 upwards, so cleanup is exact. Both the
 * posts tables and the HPOS tables get identical dates, so WooCommerce's
 * data sync sees nothing to do.
 */

global $wpdb;

$p       = $wpdb->prefix;
$orders  = (int) ( getenv( 'SOBP_PERF_ORDERS' ) ? getenv( 'SOBP_PERF_ORDERS' ) : 100000 );
$prod0   = 10000000; // Products 10,000,000 .. 10,004,999 (last 500 variable).
$var0    = 10010000; // Variations 10,010,000 + v*4 + k.
$order0  = 10100000; // Orders.
$item0   = 10000000; // Order items: item0 + n*2 + k.
$start   = microtime( true );
$q       = function ( $sql ) use ( $wpdb ) {
	if ( false === $wpdb->query( $sql ) ) {
		WP_CLI::error( $wpdb->last_error . "\n" . substr( $sql, 0, 300 ) );
	}
};

if ( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID >= {$prod0}" ) ) {
	WP_CLI::error( 'Seed rows already present. Run cleanup-large.php first.' );
}

// Number table 0..199999.
$q( "DROP TABLE IF EXISTS {$p}sobp_perf_seq" );
$q( "CREATE TABLE {$p}sobp_perf_seq ( n INT UNSIGNED NOT NULL PRIMARY KEY )" );
$q( "INSERT INTO {$p}sobp_perf_seq (n) SELECT a.d + b.d*10 + c.d*100 + d.d*1000 + e.d*10000 + f.d*100000
	FROM (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
	(SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b,
	(SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c,
	(SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) d,
	(SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) e,
	(SELECT 0 d UNION SELECT 1) f" );

$post_cols = '(ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count)';

// Products and variations.
$q( "INSERT INTO {$wpdb->posts} {$post_cols}
	SELECT {$prod0} + n, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00', '', CONCAT('SOBP Perf Product ', n), '', 'publish', 'closed', 'closed', '', CONCAT('sobp-perf-product-', n), '', '', '2024-01-01 00:00:00', '2024-01-01 00:00:00', '', 0, '', 0, 'product', '', 0
	FROM {$p}sobp_perf_seq WHERE n < 5000" );
$q( "INSERT INTO {$wpdb->posts} {$post_cols}
	SELECT {$var0} + n, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00', '', CONCAT('SOBP Perf Variation ', n), '', 'publish', 'closed', 'closed', '', CONCAT('sobp-perf-variation-', n), '', '', '2024-01-01 00:00:00', '2024-01-01 00:00:00', '', {$prod0} + 4500 + FLOOR(n / 4), '', 0, 'product_variation', '', 0
	FROM {$p}sobp_perf_seq WHERE n < 2000" );
$q( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT {$prod0} + n, '_sku', CONCAT('PERF-', n) FROM {$p}sobp_perf_seq WHERE n < 5000" );
$q( "INSERT INTO {$p}wc_product_meta_lookup (product_id, sku, `virtual`, downloadable, min_price, max_price, onsale, stock_quantity, stock_status, rating_count, average_rating, total_sales, tax_status, tax_class)
	SELECT {$prod0} + n, CONCAT('PERF-', n), 0, 0, 10, 10, 0, NULL, 'instock', 0, 0, 0, 'taxable', '' FROM {$p}sobp_perf_seq WHERE n < 5000" );

// Product type and category (round robin over existing categories).
$simple   = (int) $wpdb->get_var( "SELECT tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t USING (term_id) WHERE tt.taxonomy = 'product_type' AND t.slug = 'simple'" );
$variable = (int) $wpdb->get_var( "SELECT tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t USING (term_id) WHERE tt.taxonomy = 'product_type' AND t.slug = 'variable'" );
$cats     = array_map( 'intval', $wpdb->get_col( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'product_cat' ORDER BY term_taxonomy_id" ) );
$q( "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order) SELECT {$prod0} + n, IF(n < 4500, {$simple}, {$variable}), 0 FROM {$p}sobp_perf_seq WHERE n < 5000" );
$case = 'CASE MOD(n, ' . count( $cats ) . ')';
foreach ( $cats as $i => $tt ) {
	$case .= " WHEN {$i} THEN {$tt}";
}
$case .= ' END';
$q( "INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order) SELECT {$prod0} + n, {$case}, 0 FROM {$p}sobp_perf_seq WHERE n < 5000" );

// Orders: posts rows and HPOS rows with identical dates.
$date   = "DATE_SUB('2026-10-01 00:00:00', INTERVAL n * 15 MINUTE)";
$status = "CASE MOD(n, 10) WHEN 0 THEN 'wc-on-hold' WHEN 1 THEN 'wc-processing' WHEN 2 THEN 'wc-processing' WHEN 3 THEN 'wc-processing' ELSE 'wc-completed' END";
$q( "INSERT INTO {$wpdb->posts} {$post_cols}
	SELECT {$order0} + n, 1, {$date}, {$date}, '', CONCAT('Order perf ', n), '', {$status}, 'closed', 'closed', '', CONCAT('order-perf-', n), '', '', {$date}, {$date}, '', 0, '', 0, 'shop_order', '', 0
	FROM {$p}sobp_perf_seq WHERE n < {$orders}" );
$q( "INSERT INTO {$p}wc_orders (id, status, currency, type, tax_amount, total_amount, customer_id, billing_email, date_created_gmt, date_updated_gmt, parent_order_id, payment_method, payment_method_title, transaction_id, ip_address, user_agent, customer_note)
	SELECT {$order0} + n, {$status}, 'USD', 'shop_order', 0, 20, 0, CONCAT('perf', n, '@example.com'), {$date}, {$date}, 0, IF(MOD(n, 3) = 0, 'cod', 'bacs'), '', '', '127.0.0.1', '', ''
	FROM {$p}sobp_perf_seq WHERE n < {$orders}" );
$q( "INSERT INTO {$p}wc_order_operational_data (order_id, created_via, woocommerce_version, prices_include_tax, order_key, order_stock_reduced, date_paid_gmt, recorded_sales)
	SELECT {$order0} + n, 'checkout', '11.1.2', 0, CONCAT('wc_order_perf', n), 1, {$date}, 1 FROM {$p}sobp_perf_seq WHERE n < {$orders}" );
$q( "INSERT INTO {$p}wc_order_addresses (order_id, address_type, first_name, last_name, country, email)
	SELECT {$order0} + n, 'billing', 'Perf', CONCAT('Customer ', n), IF(MOD(n, 2) = 0, 'US', 'CA'), CONCAT('perf', n, '@example.com') FROM {$p}sobp_perf_seq WHERE n < {$orders}" );

// Two line items per order. Product index spread over all 5,000; the last
// 500 are variable, so their lines also carry a variation.
$idx = "MOD(s.n * 37 + k.k * 1009, 5000)";
$q( "INSERT INTO {$p}woocommerce_order_items (order_item_id, order_item_name, order_item_type, order_id)
	SELECT {$item0} + s.n * 2 + k.k, CONCAT('SOBP Perf Product ', {$idx}), 'line_item', {$order0} + s.n
	FROM {$p}sobp_perf_seq s, (SELECT 0 k UNION SELECT 1) k WHERE s.n < {$orders}" );

$meta = array(
	'_product_id'        => "{$prod0} + {$idx}",
	'_variation_id'      => "IF({$idx} >= 4500, {$var0} + ({$idx} - 4500) * 4 + MOD(s.n, 4), 0)",
	'_qty'               => '1 + MOD(s.n, 3)',
	'_tax_class'         => "''",
	'_line_subtotal'     => "'10'",
	'_line_subtotal_tax' => "'0'",
	'_line_total'        => "'10'",
	'_line_tax'          => "'0'",
	'_line_tax_data'     => "'a:2:{s:5:\"total\";a:0:{}s:8:\"subtotal\";a:0:{}}'",
	'_reduced_stock'     => '1 + MOD(s.n, 3)',
);
foreach ( $meta as $key => $value ) {
	$q( "INSERT INTO {$p}woocommerce_order_itemmeta (order_item_id, meta_key, meta_value)
		SELECT {$item0} + s.n * 2 + k.k, '{$key}', {$value}
		FROM {$p}sobp_perf_seq s, (SELECT 0 k UNION SELECT 1) k WHERE s.n < {$orders}" );
}

$q( "INSERT INTO {$p}wc_order_product_lookup (order_item_id, order_id, product_id, variation_id, customer_id, date_created, product_qty, product_net_revenue, product_gross_revenue)
	SELECT {$item0} + s.n * 2 + k.k, {$order0} + s.n, {$prod0} + {$idx}, IF({$idx} >= 4500, {$var0} + ({$idx} - 4500) * 4 + MOD(s.n, 4), 0), 0, DATE_SUB('2026-10-01 00:00:00', INTERVAL s.n * 15 MINUTE), 1 + MOD(s.n, 3), 10, 10
	FROM {$p}sobp_perf_seq s, (SELECT 0 k UNION SELECT 1) k WHERE s.n < {$orders}" );

delete_transient( 'wc_search_orders_by_product_lookup_coverage' );
wp_cache_flush();

printf( "Seeded %d orders, %d items, 5000 products, 2000 variations in %.1fs\n", $orders, $orders * 2, microtime( true ) - $start );
