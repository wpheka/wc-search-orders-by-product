<?php
if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}

?>

<div class='wpheka-widget'>
	<button class='wpheka-save-changes wpheka-button wpheka-button__large wpheka-button__full plugin-loader' data-progressText='<?php echo esc_attr( __( 'Saving Changes...', 'wc-search-orders-by-product' ) ); ?>' data-completedText='<?php echo esc_attr( __( 'Changes Updated', 'wc-search-orders-by-product' ) ); ?>'><?php esc_html_e( 'Save Changes', 'wc-search-orders-by-product' ); ?></button>
</div>
