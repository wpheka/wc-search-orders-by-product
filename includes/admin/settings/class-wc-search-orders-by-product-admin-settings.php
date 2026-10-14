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

			// The settings page is WC_Search_Orders_By_Product_Settings_Page (4.1.0).
			add_action( 'admin_init', array( $this, 'sobp_search_settings_init' ) );
			add_action( 'admin_enqueue_scripts', array( &$this, 'sobp_enqueue_admin_scripts_styles' ) );

			// The old WooCommerce > Settings > Advanced section stays, as a link to the new page.
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
		 * @return void
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
		 * The orders screen styles, on every admin page (the WPHEKA menu icon rule is in there too).
		 */
		public function sobp_enqueue_admin_scripts_styles() {
			wp_enqueue_style( 'sobp_admin_css', plugins_url( 'assets/admin/css/admin.css', WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE ), array(), WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_VERSION );
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
		 * The WooCommerce settings section: since 4.1.0 only a link to the new page.
		 *
		 * The settings moved to WPHEKA > Search Orders. The section stays so bookmarks
		 * still lead somewhere. A title with a link is all WooCommerce prints here, so
		 * there is nothing to save.
		 *
		 * @param array  $settings        Settings of the current section.
		 * @param string $current_section Current section id.
		 * @return array
		 */
		public function get_wc_settings( $settings, $current_section ) {
			if ( 'wc_search_orders_by_product' !== $current_section ) {
				return $settings;
			}

			// Nothing to save here, so WooCommerce's Save button would only show disabled.
			$GLOBALS['hide_save_button'] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's own switch for its Save button; the name is WooCommerce's.

			return array(
				array(
					'title' => __( 'Search Orders By Product settings have moved', 'wc-search-orders-by-product' ),
					'type'  => 'title',
					'desc'  => esc_html__( 'Find them under WPHEKA > Search Orders.', 'wc-search-orders-by-product' ) . "\n\n" . '<a class="button button-primary" href="' . esc_url( WC_Search_Orders_By_Product_Settings_Page::url() ) . '">' . esc_html__( 'Open Search Orders settings', 'wc-search-orders-by-product' ) . '</a>',
					'id'    => 'sobp_settings_section',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'sobp_settings_section',
				),
			);
		}
	}

endif;

new WC_Search_Orders_By_Product_Admin_Settings();
