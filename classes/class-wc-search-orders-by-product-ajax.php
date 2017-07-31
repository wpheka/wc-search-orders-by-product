<?php
class WC_Search_Orders_By_Product_Ajax {

	public function __construct() {
		add_action('wp', array(&$this, 'demo_ajax_method'));
	}

	public function demo_ajax_method() {
	  // Do your ajx job here
	  
	}

}
