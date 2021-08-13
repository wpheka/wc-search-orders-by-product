<?php
if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}

?>
<form method="post" id="plugin-settings-form">
	<div class='wpheka-box'>
		<fieldset class='mb22'>
			<legend class='wpheka-box-title-bar wpheka-box-title-bar__small mb22'><h3><?php esc_html_e( 'Search Orders By:', wc_search_orders_by_product()->text_domain ); ?></h3></legend>
			<div id="wpheka-custom-form">
				<h3>Enable</h3>
				<div id="wpheka-custom-form-fields">
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
