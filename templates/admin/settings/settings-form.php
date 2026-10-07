<?php
/**
 * Settings form of the WPHEKA settings page.
 *
 * Each option is a real checkbox styled as a switch, so keyboard use, screen
 * readers and the save script work as before.
 *
 * @package WC_Search_Orders_By_Product
 */

defined( 'ABSPATH' ) || exit;

$wc_search_orders_by_product_option = static function ( $key, $label, $description, $checked, $locked = false ) {
	?>
	<label class="sobp-option<?php echo $locked ? ' sobp-option--locked' : ''; ?>" for="<?php echo esc_attr( $key ); ?>">
		<input class="sobp-switch" name="<?php echo esc_attr( $key ); ?>" type="checkbox" id="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $checked ); ?> <?php disabled( $locked ); ?> />
		<span class="sobp-option__text">
			<span class="sobp-option__label"><?php echo esc_html( $label ); ?></span>
			<span class="sobp-option__description"><?php echo esc_html( $description ); ?></span>
		</span>
	</label>
	<?php
};
?>
<form method="post" id="plugin-settings-form" class="sobp-settings">
	<div class="sobp-group">
		<h3 class="sobp-group__title"><?php esc_html_e( 'Filters on the orders screen', 'wc-search-orders-by-product' ); ?></h3>
		<p class="sobp-group__intro"><?php esc_html_e( 'Choose which filters appear above the WooCommerce orders list.', 'wc-search-orders-by-product' ); ?></p>
		<div class="sobp-options">
			<?php
			foreach ( wc_search_orders_by_product()->filters->get_fields() as $wc_search_orders_by_product_field ) {
				if ( empty( $wc_search_orders_by_product_field['setting'] ) ) {
					// Always on, shown for completeness. Disabled inputs are not
					// posted, so the save script never sends it.
					$wc_search_orders_by_product_option( 'sobp_always_' . sanitize_key( $wc_search_orders_by_product_field['query_var'] ), $wc_search_orders_by_product_field['label'] . ' ' . __( '(always on)', 'wc-search-orders-by-product' ), $wc_search_orders_by_product_field['description'] ?? '', true, true );
					continue;
				}
				$wc_search_orders_by_product_option(
					$wc_search_orders_by_product_field['setting'],
					$wc_search_orders_by_product_field['label'],
					$wc_search_orders_by_product_field['description'] ?? '',
					wc_search_orders_by_product_setting_enabled( $wc_search_orders_by_product_field['setting'], ! empty( $wc_search_orders_by_product_field['default'] ) )
				);
			}
			?>
		</div>
	</div>

	<div class="sobp-group">
		<h3 class="sobp-group__title"><?php esc_html_e( 'Orders list', 'wc-search-orders-by-product' ); ?></h3>
		<div class="sobp-options">
			<?php
			$wc_search_orders_by_product_option(
				'purchased_items_column',
				__( 'Purchased items column', 'wc-search-orders-by-product' ),
				__( 'Shows the items and quantities of each order in the orders list.', 'wc-search-orders-by-product' ),
				wc_search_orders_by_product_setting_enabled( 'purchased_items_column', true )
			);

			/**
			 * After the switches on the WPHEKA settings page.
			 *
			 * @since 4.0
			 * @param array $options Current settings.
			 */
			do_action( 'wc_search_orders_by_product_settings_after_fields', wc_search_orders_by_product_settings() );
			?>
		</div>
	</div>
</form>
