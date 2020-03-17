<?php
if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}

?>
<form method="post" id="plugin-settings-form">
	<div class='aks-box'>
		<fieldset class='mb22'>
			<legend class='aks-box-title-bar aks-box-title-bar__small mb22'><h3><?php esc_html_e( 'Search Orders By:', wc_search_orders_by_product()->text_domain ); ?></h3></legend>
			<div id="custom-url-form">
				<h3>Enable</h3>
				<div id="custom-url-form-fields">
					<label for="search_orders_by_product_type">
					    <input name="search_orders_by_product_type" type="checkbox" id="search_orders_by_product_type" value="1"<?php checked('1', $options['search_orders_by_product_type']); ?> />
					    <?php _e('Product Types'); ?>
					</label>
					<label for="search_orders_by_product_category" style="margin-left: 15px;">
					    <input name="search_orders_by_product_category" type="checkbox" id="search_orders_by_product_category" value="1"<?php checked('1', $options['search_orders_by_product_category']); ?> />
					    <?php _e('Product Categories'); ?>
					</label>					
				</div>
			</div>		

		</fieldset>
	</div>
	<input type="hidden" name="action" value='save_plugin_options'>
</form>
