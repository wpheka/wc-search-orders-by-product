<?php
/**
 * Settings form of the WPHEKA settings page.
 *
 * @package WC_Search_Orders_By_Product
 */

defined( 'ABSPATH' ) || exit;
?>
<form method="post" id="plugin-settings-form">
	<div class='wpheka-box'>
		<fieldset class='mb22'>
			<legend class='wpheka-box-title-bar wpheka-box-title-bar__small mb22'><h3><?php esc_html_e( 'Search Orders By:', 'wc-search-orders-by-product' ); ?></h3></legend>
			<div id="wpheka-custom-form">
				<h3><?php esc_html_e( 'Enable', 'wc-search-orders-by-product' ); ?></h3>
				<div id="wpheka-custom-form-fields">
					<?php
					foreach ( wc_search_orders_by_product()->filters->get_fields() as $wc_search_orders_by_product_field ) {
						if ( empty( $wc_search_orders_by_product_field['setting'] ) ) {
							continue;
						}
						?>
						<label for="<?php echo esc_attr( $wc_search_orders_by_product_field['setting'] ); ?>" style="margin-right: 15px; display: inline-block;">
							<input name="<?php echo esc_attr( $wc_search_orders_by_product_field['setting'] ); ?>" type="checkbox" id="<?php echo esc_attr( $wc_search_orders_by_product_field['setting'] ); ?>" value="1" <?php checked( wc_search_orders_by_product_setting_enabled( $wc_search_orders_by_product_field['setting'], ! empty( $wc_search_orders_by_product_field['default'] ) ) ); ?> />
							<?php echo esc_html( $wc_search_orders_by_product_field['label'] ); ?>
						</label>
						<?php
					}
					?>
					<label for="purchased_items_column" style="margin-right: 15px; display: inline-block;">
						<input name="purchased_items_column" type="checkbox" id="purchased_items_column" value="1" <?php checked( wc_search_orders_by_product_setting_enabled( 'purchased_items_column', true ) ); ?> />
						<?php esc_html_e( 'Purchased items column', 'wc-search-orders-by-product' ); ?>
					</label>
					<?php
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
		</fieldset>
	</div>
</form>
