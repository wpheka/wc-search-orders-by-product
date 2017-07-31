<?php
class WC_Search_Orders_By_Product_Library {
  
  public $lib_path;
  
  public $lib_url;
  
  public $jquery_lib_path;
  
  public $jquery_lib_url;

	public function __construct() {
	  global $WC_Search_Orders_By_Product;
	  
		$this->lib_path = $WC_Search_Orders_By_Product->plugin_path . 'lib/';

		$this->lib_url = $WC_Search_Orders_By_Product->plugin_url . 'lib/';

		$this->jquery_lib_path = $this->lib_path . 'jquery/';

		$this->jquery_lib_url = $this->lib_url . 'jquery/';
	}
	
	/**
	 * Jquery select2 library
	 */
	public function load_select2_lib() {
	  global $WC_Search_Orders_By_Product;
	  wp_enqueue_script('select2_js', $this->jquery_lib_url . 'select2/js/select2.full.min.js', array('jquery'), $WC_Search_Orders_By_Product->version, true);
		wp_enqueue_style('select2_css',  $this->jquery_lib_url . 'select2/css/select2.min.css', array(), $WC_Search_Orders_By_Product->version);
	}
}