<?php
/**
 * Template to display support box in the sidebar of the setting page
 */

if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}

?>

<div class='wpheka-box wpheka-widget'>
	<div class='wpheka-box-title-bar'>
		<h3><?php esc_html_e( 'Need Help?', 'wc-search-orders-by-product' ); ?></h3>
	</div>
	<div class="wpheka-box-content wpheka-flex">
		<img class='mr22' src='<?php echo esc_url( $WC_Search_Orders_By_Product->plugin_url . 'assets/admin/images/wp-heka-logo-settings.svg' ); ?>' height='66px'>
		<div>
		<?php
		// Translators: %s '<a href="https://www.wpheka.com/contact/" target="_blank">Site</a>.
		$content = sprintf( __( 'If you need some help contact us through our %s', 'wc-search-orders-by-product' ), '<a href="https://www.wpheka.com/contact/" target="_blank">' . __( 'Site', 'wc-search-orders-by-product' ) . '</a>' );

		echo wp_kses(
			$content,
			array(
				'a' => array(
					'href' => true,
					'target' => true,
				),
			)
		);
		?>
		</div>
	</div>
</div>
