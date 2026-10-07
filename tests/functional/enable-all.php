<?php
// enable-all.php -- switch every filter and the column back on, as setup.php does.
$settings = (array) get_option( 'sobp_settings', array() );
foreach ( array( 'search_orders_by_product_type', 'search_orders_by_product_category', 'search_orders_by_sku', 'search_orders_by_payment_method', 'search_orders_by_shipping_method', 'search_orders_by_billing_country', 'purchased_items_column' ) as $key ) {
	$settings[ $key ] = 'yes';
}
update_option( 'sobp_settings', $settings );
echo 'ok';
