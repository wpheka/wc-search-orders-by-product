<?php
if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}

?>

<div class='wpheka-widget'>
	<button class='wpheka-save-changes wpheka-button wpheka-button__large wpheka-button__full plugin-loader' data-progressText='<?php echo esc_attr( __( 'Saving Changes...', wc_search_orders_by_product()->text_domain ) ); ?>' data-completedText='<?php echo esc_attr( __( 'Changes Updated', wc_search_orders_by_product()->text_domain ) ); ?>'><?php esc_html_e( 'Save Changes', wc_search_orders_by_product()->text_domain ); ?></button>
</div>
