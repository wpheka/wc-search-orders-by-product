<?php
/**
 * Template to display support box in the sidebar of the setting page
 *
 * @package KinstaMUPlugins
 * @subpackage Cache
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}

?>

<div class='aks-box aks-widget'>
	<div class='aks-box-title-bar'>
		<h3><?php esc_html_e( 'Need Help?', wc_search_orders_by_product()->text_domain ); ?></h3>
	</div>
	<div class="aks-box-content aks-flex">
		<img class='mr22' src='<?php echo $WC_Search_Orders_By_Product->plugin_url . 'assets/admin/images/icon-support.svg'; ?>' height='66px'>
		<div>
		<?php
		// Translators: %s '<a href="https://wppluginsmarket.com/" target="_blank">Site</a>.
		$content = sprintf( __( 'If you need some help contact us through our %s', wc_search_orders_by_product()->text_domain ), '<a href="https://wppluginsmarket.com/" target="_blank">' . __( 'Site', wc_search_orders_by_product()->text_domain ) . '</a>' );

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