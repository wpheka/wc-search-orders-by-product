<?php
/**
 * WC_Search_Orders_By_Product
 *
 * @package WC_Search_Orders_By_Product
 * @author      WPHEKA
 * @link        https://wpheka.com/
 * @since       1.0
 * @version     1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Search_Orders_By_Product_Admin_Settings', false ) ) :

	/**
	 * WC_Search_Orders_By_Product_Admin_Settings Class.
	 */
	class WC_Search_Orders_By_Product_Admin_Settings {

		/**
		 * WC_Search_Orders_By_Product_Admin_Settings Constructor.
		 */
		public function __construct() {

			// Search orders Settings
			add_action( 'admin_init', array( $this, 'sobp_search_settings_init' ) );
			add_action( 'admin_menu', array( $this, 'sobp_search_settings_menu' ), 20 );
			add_action( 'admin_enqueue_scripts', array( &$this, 'sobp_enqueue_admin_scripts_styles' ) );

		}

		/**
		 * Admin Scripts
		 */
		public function sobp_enqueue_admin_scripts_styles() {
			global $WC_Search_Orders_By_Product;
			$screen       = get_current_screen();
			$screen_id    = $screen ? $screen->id : '';
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_style( 'sobp_admin_css', $WC_Search_Orders_By_Product->plugin_url . 'assets/admin/css/admin.css', array(), $WC_Search_Orders_By_Product->version );
			if ( $screen_id == 'wpheka_page_wc-search-orders-by-product-settings' ) {
				wp_enqueue_style( 'sobp_common_css', $WC_Search_Orders_By_Product->plugin_url . 'assets/admin/css/common.css', array(), $WC_Search_Orders_By_Product->version );
				wp_enqueue_script( 'sobp_plugin_loader_js', $WC_Search_Orders_By_Product->plugin_url . 'assets/admin/js/plugin-loader.js', array( 'jquery' ), $WC_Search_Orders_By_Product->version, true );
			}

		}

		/**
		 * Register search settings
		 */
		public function sobp_search_settings_init() {
			register_setting( 'sobp_search_options', 'sobp_settings', array( $this, 'sobp_search_options_validate' ) );
		}

		/**
		 * Add menu items.
		 */
		public function sobp_search_settings_menu() {
			global $WC_Search_Orders_By_Product;

			if ( ! wc_search_orders_by_product_framework_ready() ) {
				return;
			}

			/*
			 * The shared WPHEKA parent is coordinated by the framework (ADR-028),
			 * which also removes the parent's mirrored submenu -- the "Duplicate
			 * Items Hack" that used to sit at the end of this method.
			 *
			 * `manage_woocommerce` is passed explicitly rather than inherited,
			 * because that is what this page has always required and changing who
			 * can use it is not part of adopting the framework.
			 *
			 * **It does not follow that a shop manager can reach this page.** The
			 * parent requires `manage_options`, which shop managers do not have,
			 * and WordPress nests submenus inside the parent -- so the whole menu
			 * is hidden from exactly the role this capability was chosen for. The
			 * page stays reachable by direct URL, which makes it undiscoverable
			 * rather than forbidden. Pre-existing, unchanged here, and worth a
			 * decision: it is fixed either by lowering the shared parent's
			 * capability, which affects every WPHEKA plugin, or by accepting that
			 * this page is for administrators.
			 *
			 * The literal text domain is deliberate. This previously passed
			 * `$WC_Search_Orders_By_Product->text_domain`, a variable, and the
			 * i18n tooling only extracts literals -- so neither string was ever
			 * in the .pot file and neither could be translated.
			 */
			$menu = new \WPHEKA\Framework\V1\Admin\Menu(
				$WC_Search_Orders_By_Product->plugin_url . 'assets/admin/images/wp-heka-menu-icon-22.svg'
			);

			$menu->add_page(
				__( 'WC Search Orders By Product', 'wc-search-orders-by-product' ),
				__( 'WC Search Orders By Product', 'wc-search-orders-by-product' ),
				'wc-search-orders-by-product-settings',
				array( $this, 'sobp_search_settings_page' ),
				'manage_woocommerce'
			);
		}

		/**
		 * Render settings page
		 */
		public function sobp_search_settings_page() {
			global $WC_Search_Orders_By_Product;
			$options = get_option( 'sobp_settings' );
			$ajax_action = add_query_arg(
				array(
					'action' => 'save_sobp_plugin_data',
					'sobp_nonce' => wp_create_nonce( 'save-plugin-data' ),
				),
				admin_url( 'admin-ajax.php' )
			);
			?>
			<div class="wrap">
				<div class='wpheka-page-bar'>
					<img class='logo' src='<?php echo esc_url( $WC_Search_Orders_By_Product->plugin_url . 'assets/admin/images/control-panel-icon.png' ); ?>' height='32px'>
					<h3><?php esc_html_e( 'WC Search Orders By Product', 'wc-search-orders-by-product' ); ?></h3>
				</div>
				<hr class="wp-header-end" />
				<div class='wpheka-page-wrapper'>
					<div class='wpheka-sidebar'>
						<?php
						include plugin_dir_path( WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE ) . 'templates/admin/settings/settings-form-submit.php';
							include plugin_dir_path( WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE ) . 'templates/admin/settings/sidebar-support.php';
						?>
					</div>
					<div class='wpheka-main-content'>
						<div class='wpheka-box'>
							<div class='wpheka-box-title-bar'>
								<h3><?php esc_html_e( 'Settings', 'wc-search-orders-by-product' ); ?></h3>
							</div>
							<div class='wpheka-box-content'>
								<div class='content mb22'>
									<p><?php esc_html_e( 'This WooCommerce extension automatically adds product search, product type and product category filter dropdown in WooCommerce Orders screen. You can find orders by typing just a few characters of your product name. As you start typing in the search input, you will see instant results popping up inside the dropdown menu. The auto listing of the matching products with same characters inside the dropdown will help you in typo tolerance or if you misspell the product name.', 'wc-search-orders-by-product' ); ?>
									</p>
								</div>
								<?php require plugin_dir_path( WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE ) . 'templates/admin/settings/settings-form.php'; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
			<script>
			jQuery(document).on('click', '.wpheka-save-changes', function() {
				var element = jQuery(this);

				var fd = new FormData(); // Currently empty

				if(jQuery('input#search_orders_by_product_type').prop("checked") == true){
					fd.append( 'search_orders_by_product_type', '1');
				} else {
					fd.append( 'search_orders_by_product_type', '0');
				}

				if(jQuery('input#search_orders_by_product_category').prop("checked") == true){
					fd.append( 'search_orders_by_product_category', '1');
				} else {
					fd.append( 'search_orders_by_product_category', '0');
				}

				console.log(jQuery('#plugin-settings-form').serialize());  

				jQuery.ajax({
					url: "<?php echo esc_url( $ajax_action ); ?>",
					type: 'post',
					cache: false,
					processData: false,
					contentType: false,
					data: fd,
					success: function (response) {
						if(response.success) {
						location.reload(true);
					}
					},
				});
				return false;
			});
			</script>
			<?php
		}
	}

endif;

new WC_Search_Orders_By_Product_Admin_Settings();
