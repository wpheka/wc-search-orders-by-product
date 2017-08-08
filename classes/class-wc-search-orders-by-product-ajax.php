<?php
class WC_Search_Orders_By_Product_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_search_woo_products', array(&$this,'sobp_search_woo_products'));
		add_action( 'wp_ajax_nopriv_search_woo_products', array(&$this,'sobp_search_woo_products'));
	}

	public function sobp_search_woo_products( $term = '', $include_variations = false ) {
		check_ajax_referer( 'search-woo-products', 'security' );

		$term = wc_clean( empty( $term ) ? stripslashes( $_GET['term'] ) : $term );

		if ( empty( $term ) ) {
		wp_die();
		}

		$data_store = WC_Data_Store::load( 'product' );
		$ids = $data_store->search_products( $term, '', (bool) $include_variations );

		$product_objects = array_filter( array_map( 'wc_get_product', $ids ), 'wc_products_array_filter_editable' );
		$products = array();

		foreach ( $product_objects as $product_object ) {
		$products[ $product_object->get_id() ] = rawurldecode( $product_object->get_formatted_name() );
		}

		wp_send_json($products);
	}

}
