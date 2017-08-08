<?php
class WC_Search_Orders_By_Product {

	public $plugin_url;

	public $plugin_path;

	public $version;

	public $token;
	
	public $text_domain;
	
	public $library;

	public $admin;

	public $ajax;

	private $file;

	public function __construct($file) {

		$this->file = $file;
		$this->plugin_url = trailingslashit(plugins_url('', $plugin = $file));
		$this->plugin_path = trailingslashit(dirname($file));
		$this->token = WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_TOKEN;
		$this->text_domain = WC_SEARCH_ORDERS_BY_PRODUCT_TEXT_DOMAIN;
		$this->version = WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_VERSION;
		
		add_action('init', array(&$this, 'sobp_init'), 0);
	}
	
	/**
	 * initilize plugin on WP init
	 */
	function sobp_init() {
		
		// Init Text Domain
		$this->sobp_load_plugin_textdomain();
		
		// Init library
		if ( ! class_exists( 'WC_Search_Orders_By_Product_Library' ) ) {
			$this->sobp_load_class('library');
			$this->library = new WC_Search_Orders_By_Product_Library();
		}		

		// Init ajax
		if(defined('DOING_AJAX')) {
			if ( ! class_exists( 'WC_Search_Orders_By_Product_Ajax' ) ) {
				$this->sobp_load_class('ajax');
      			$this->ajax = new  WC_Search_Orders_By_Product_Ajax();
			}      		
    	}

		if (is_admin()) {
			if ( ! class_exists( 'WC_Search_Orders_By_Product_Admin' ) ) {
				$this->sobp_load_class('admin');
				$this->admin = new WC_Search_Orders_By_Product_Admin();
			}			
		}
	}
	
	/**
   * Load Localisation files.
   *
   * Note: the first-loaded translation file overrides any following ones if the same translation is present
   *
   * @access public
   * @return void
   */
  public function sobp_load_plugin_textdomain() {
    $locale = apply_filters( 'plugin_locale', get_locale(), $this->token );

    load_textdomain( $this->text_domain, WP_LANG_DIR . "/wc-search-orders-by-product/wc-search-orders-by-product-$locale.mo" );
    load_textdomain( $this->text_domain, $this->plugin_path . "/languages/wc-search-orders-by-product-$locale.mo" );
  }

	public function sobp_load_class($class_name = '') {
		if ('' != $class_name && '' != $this->token) {
			require_once ('class-' . esc_attr($this->token) . '-' . esc_attr($class_name) . '.php');
		} // End If Statement
	}// End sobp_load_class()
	
	/** Cache Helpers *********************************************************/

	/**
	 * Sets a constant preventing some caching plugins from caching a page. Used on dynamic pages
	 *
	 * @access public
	 * @return void
	 */
	function sobp_nocache() {
		if (!defined('DONOTCACHEPAGE'))
			define("DONOTCACHEPAGE", "true");
		// WP Super Cache constant
	}

}