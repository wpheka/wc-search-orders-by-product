<?php
/**
 * Settings-set.php KEY JSON: set one key of sobp_settings to a JSON value, or "unset" to remove it.
 *
 * @package WC_Search_Orders_By_Product
 */

$settings = (array) get_option( 'sobp_settings', array() );
if ( 'unset' === ( $args[1] ?? '' ) ) {
	unset( $settings[ $args[0] ] );
} else {
	$settings[ $args[0] ] = json_decode( $args[1] ?? 'null', true );
}
update_option( 'sobp_settings', $settings );
echo 'ok';
