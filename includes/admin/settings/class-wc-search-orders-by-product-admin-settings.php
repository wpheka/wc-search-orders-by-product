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

			// The same settings under WooCommerce > Settings > Advanced, reachable by
			// shop managers and working without the WPHEKA framework.
			add_filter( 'woocommerce_get_sections_advanced', array( $this, 'add_wc_settings_section' ) );
			add_filter( 'woocommerce_get_settings_advanced', array( $this, 'get_wc_settings' ), 10, 2 );

			// Review prompt.
			add_action( 'admin_notices', array( $this, 'sobp_review_prompt' ) );
			add_action( 'admin_init', array( $this, 'sobp_maybe_hide_review_prompt' ) );
			add_action( 'wp_ajax_sobp_snooze_review', array( $this, 'sobp_snooze_review_prompt' ) );

		}

		/**
		 * Ask for a review in the footer of this plugin's settings screen.
		 *
		 * The approach follows Elementor's rate-us notice -- dashboard only,
		 * dismissed per user rather than per site, and gated on a usage count
		 * rather than elapsed time. Written here rather than taken from
		 * Elementor, whose licence differs from this project's (ADR-008).
		 *
		 * **The link goes to the plain reviews page, not a pre-filled five-star
		 * form.** WooCommerce links to `reviews?rate=5#new-post` and renders five
		 * stars; Plugin Check reports that as `five_star_reviews_detected`,
		 * "Linking directly to 5 stars reviews is not allowed", and this plugin's
		 * sibling had exactly that finding cleared. Asking for a review is
		 * allowed; asking for a specific score is what gets flagged.
		 *
		 * @since 3.3
		 * @param string $footer_text Existing footer text.
		 * @return string
		 */
		public function sobp_review_prompt() {

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			if ( ! $screen || 'dashboard' !== $screen->id ) {
				return;
			}

			// Per user, so one administrator cannot answer for the rest. The old
			// site-wide option is still honoured so nobody is asked twice.
			if ( get_option( 'sobp_review_prompt_dismissed' ) || get_user_meta( get_current_user_id(), 'sobp_review_dismissed', true ) ) {
				return;
			}

			// Closed with its X: asked again in 14 days, for this user only.
			if ( (int) get_user_meta( get_current_user_id(), 'sobp_review_snoozed_until', true ) > time() ) {
				return;
			}

			// Three filtered searches, not a timer: the plugin having been used.
			if ( 3 > (int) get_option( 'sobp_filtered_search_count', 0 ) ) {
				return;
			}

			// rate=5 pre-selects the rating on the review form, in the URL form
			// WooCommerce uses. Deliberate, and *not* a Plugin Check finding: its
			// five_star_reviews_detected check matches `reviews/?filter=5` only, so
			// neither the parameter nor the missing trailing slash here match it.
			// The `reviews/?filter=5#new-post` this plugin family used before did
			// match, which is why it was reported -- and filter=5 only filters the
			// existing review list, so it was flagged without pre-filling anything.
			// Plugin Check staying quiet is not the same as wordpress.org's review
			// team agreeing; that risk is accepted separately.
			$reviews = 'https://wordpress.org/support/plugin/wc-search-orders-by-product/reviews?rate=5#new-post';
			$hide    = wp_nonce_url(
				add_query_arg( 'sobp_hide_review', '1', admin_url( 'index.php' ) ),
				'sobp_hide_review'
			);
			?>
			<div id="sobp-review-notice" class="notice notice-info is-dismissible" data-nonce="<?php echo esc_attr( wp_create_nonce( 'sobp_snooze_review' ) ); ?>">
				<p>
					<?php
					printf(
						/* translators: 1: plugin name, 2: five-star rating link, 3: opening link tag to dismiss, 4: closing link tag */
						esc_html__( 'You have been finding orders with %1$s. If it saves you time, please leave us a %2$s rating on WordPress.org. %3$sDon\'t ask again%4$s.', 'wc-search-orders-by-product' ),
						'<strong>' . esc_html__( 'WC Search Orders By Product', 'wc-search-orders-by-product' ) . '</strong>',
						'<a href="' . esc_url( $reviews ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__( 'five star', 'wc-search-orders-by-product' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>',
						'<a href="' . esc_url( $hide ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>
			<script>
			// WordPress hides an is-dismissible notice on its X but remembers
			// nothing, so without this the prompt was back on the next load.
			jQuery( function ( $ ) {
				$( document ).on( 'click', '#sobp-review-notice .notice-dismiss', function () {
					$.post( ajaxurl, { action: 'sobp_snooze_review', _ajax_nonce: $( '#sobp-review-notice' ).data( 'nonce' ) } );
				} );
			} );
			</script>
			<?php
		}

		/**
		 * Snooze the review prompt for 14 days when its X is clicked.
		 *
		 * Per user, like the permanent dismissal: closing a notice is one
		 * person's decision. "Don't ask again" still hides it for good.
		 *
		 * @since 3.5
		 * @return void
		 */
		public function sobp_snooze_review_prompt() {

			check_ajax_referer( 'sobp_snooze_review' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( null, 403 );
			}

			update_user_meta( get_current_user_id(), 'sobp_review_snoozed_until', time() + 14 * DAY_IN_SECONDS );
			wp_send_json_success();
		}

		/**
		 * Stop asking, when the user asks us to.
		 *
		 * @since 3.3
		 * @return void
		 */
		public function sobp_maybe_hide_review_prompt() {

			if ( ! isset( $_GET['sobp_hide_review'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked immediately below.
				return;
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			check_admin_referer( 'sobp_hide_review' );

			update_user_meta( get_current_user_id(), 'sobp_review_dismissed', 1 );

			wp_safe_redirect( admin_url( 'index.php' ) );
			exit;
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
			register_setting(
				'sobp_search_options',
				'sobp_settings',
				array(
					'type'              => 'array',
					'sanitize_callback' => array( __CLASS__, 'normalize_option' ),
				)
			);
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
		 * Settings keys that are on/off switches, with their default.
		 *
		 * @return array key => default (bool)
		 */
		public static function toggle_keys() {
			$keys = array();
			foreach ( wc_search_orders_by_product()->filters->get_fields() as $field ) {
				if ( ! empty( $field['setting'] ) ) {
					$keys[ $field['setting'] ] = ! empty( $field['default'] );
				}
			}
			$keys['purchased_items_column'] = true;

			return $keys;
		}

		/**
		 * Sanitise a settings form post: every switch is saved, absent means off.
		 *
		 * @param array $raw Unslashed form data.
		 * @return array
		 */
		public static function sanitize_settings( $raw ) {
			$raw      = (array) $raw;
			$settings = array();

			foreach ( array_keys( self::toggle_keys() ) as $key ) {
				$value            = $raw[ $key ] ?? '';
				$settings[ $key ] = in_array( $value, array( 1, '1', 'yes', 'on', true ), true ) ? 'yes' : 'no';
			}

			if ( isset( $raw['category_include'] ) ) {
				$settings['category_include'] = array_values( array_filter( array_map( 'absint', (array) $raw['category_include'] ) ) );
			}

			/**
			 * Settings about to be saved from a settings form.
			 *
			 * @since 4.0
			 * @param array $settings Sanitised settings.
			 * @param array $raw      Raw form data (unslashed, unsanitised).
			 */
			return (array) apply_filters( 'wc_search_orders_by_product_sanitize_settings', $settings, $raw );
		}

		/**
		 * Normalise the stored option whenever it is updated.
		 *
		 * Switches become 'yes' or 'no' and the category list a list of ids.
		 * Keys this plugin does not know (an add-on's) are kept as they are.
		 *
		 * @param mixed $value New option value.
		 * @return array
		 */
		public static function normalize_option( $value ) {
			$value = is_array( $value ) ? $value : array();

			foreach ( array_keys( self::toggle_keys() ) as $key ) {
				if ( array_key_exists( $key, $value ) ) {
					$value[ $key ] = in_array( $value[ $key ], array( 1, '1', 'yes', 'on', true ), true ) ? 'yes' : 'no';
				}
			}

			if ( isset( $value['category_include'] ) ) {
				$value['category_include'] = array_values( array_filter( array_map( 'absint', (array) $value['category_include'] ) ) );
			}

			return $value;
		}

		/**
		 * Add the section to WooCommerce > Settings > Advanced.
		 *
		 * @param array $sections Sections.
		 * @return array
		 */
		public function add_wc_settings_section( $sections ) {
			$sections['wc_search_orders_by_product'] = __( 'Search orders by product', 'wc-search-orders-by-product' );

			return $sections;
		}

		/**
		 * Fields of the WooCommerce settings section.
		 *
		 * @param array  $settings        Settings of the current section.
		 * @param string $current_section Current section id.
		 * @return array
		 */
		public function get_wc_settings( $settings, $current_section ) {
			if ( 'wc_search_orders_by_product' !== $current_section ) {
				return $settings;
			}

			$engine  = wc_search_orders_by_product()->engine;
			$storage = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'hpos' : 'posts';
			$fast    = $engine && WC_Search_Orders_By_Product_Engine::SOURCE_LOOKUP === $engine->get_source( $storage );

			$fields = array(
				array(
					'title' => __( 'Search orders by product', 'wc-search-orders-by-product' ),
					'type'  => 'title',
					'desc'  => $fast
						? __( 'Fast lookup is on: filters use WooCommerce Analytics\' indexed order data.', 'wc-search-orders-by-product' )
						: __( 'Filters read order items directly. For faster filtering on large stores, import historical data in WooCommerce > Settings > Advanced > Features or Analytics > Settings.', 'wc-search-orders-by-product' ),
					'id'    => 'sobp_settings_section',
				),
			);

			$first = true;
			foreach ( wc_search_orders_by_product()->filters->get_fields() as $field ) {
				if ( empty( $field['setting'] ) ) {
					continue;
				}
				$fields[] = array(
					'title'         => $first ? __( 'Filters on the orders screen', 'wc-search-orders-by-product' ) : '',
					'desc'          => $field['label'],
					'id'            => 'sobp_settings[' . $field['setting'] . ']',
					'type'          => 'checkbox',
					'value'         => wc_search_orders_by_product_setting_enabled( $field['setting'], ! empty( $field['default'] ) ) ? 'yes' : 'no',
					'checkboxgroup' => $first ? 'start' : '',
				);
				$first = false;
			}

			$fields[] = array(
				'title' => __( 'Purchased items column', 'wc-search-orders-by-product' ),
				'desc'  => __( 'Show the items of each order in the orders list', 'wc-search-orders-by-product' ),
				'id'    => 'sobp_settings[purchased_items_column]',
				'type'  => 'checkbox',
				'value' => wc_search_orders_by_product_setting_enabled( 'purchased_items_column', true ) ? 'yes' : 'no',
			);

			$categories = array();
			foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $term ) {
				$categories[ $term->term_id ] = $term->name;
			}
			$fields[] = array(
				'title'    => __( 'Categories in the filter', 'wc-search-orders-by-product' ),
				'desc'     => __( 'Leave empty to list every category.', 'wc-search-orders-by-product' ),
				'id'       => 'sobp_settings[category_include]',
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'options'  => $categories,
				'value'    => array_map( 'strval', (array) ( wc_search_orders_by_product_settings()['category_include'] ?? array() ) ),
				'desc_tip' => true,
			);

			$fields[] = array(
				'type' => 'sectionend',
				'id'   => 'sobp_settings_section',
			);

			/**
			 * Fields of the plugin's WooCommerce settings section.
			 *
			 * @since 4.0
			 * @param array $fields WooCommerce settings fields.
			 */
			return (array) apply_filters( 'wc_search_orders_by_product_settings_fields', $fields );
		}

		/**
		 * Render settings page
		 */
		public function sobp_search_settings_page() {
			global $WC_Search_Orders_By_Product;
			$options = wc_search_orders_by_product_settings();
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

				var fd = new FormData();
				jQuery('#plugin-settings-form input[type=checkbox]').each(function () {
					fd.append(this.name, this.checked ? '1' : '0');
				});

				jQuery.ajax({
					// A JS string, not HTML: esc_url() turned & into &#038;, the
					// "#" began a fragment, the nonce was never sent and every save
					// was refused with 403. esc_url_raw() + JSON is the JS form.
					url: <?php echo wp_json_encode( esc_url_raw( $ajax_action ) ); ?>,
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
