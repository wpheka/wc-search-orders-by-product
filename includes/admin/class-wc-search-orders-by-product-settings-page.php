<?php
/**
 * WPHEKA > Search Orders: the plugin's settings page, with Settings and Help tabs.
 *
 * Replaces both settings screens before 4.1.0: the WPHEKA page saved by AJAX and
 * the WooCommerce > Settings > Advanced section, which now links here.
 *
 * @package WC_Search_Orders_By_Product
 * @since   4.1.0
 */

defined( 'ABSPATH' ) || exit;

use WC_Search_Orders_By_Product\AdminUI\Fields;
use WC_Search_Orders_By_Product\AdminUI\Icons;
use WC_Search_Orders_By_Product\AdminUI\Menu;
use WC_Search_Orders_By_Product\AdminUI\Page;

/**
 * WC_Search_Orders_By_Product_Settings_Page Class.
 *
 * Every field saves into the `sobp_settings` option through the same sanitising and
 * merging the earlier screens used, so nothing is migrated and add-on keys are kept.
 */
class WC_Search_Orders_By_Product_Settings_Page {

	/**
	 * Page slug (the WPHEKA page's slug before 4.1.0, so old links still work).
	 */
	const PAGE = 'wc-search-orders-by-product-settings';

	/**
	 * The admin-post action that saves the form.
	 */
	const SAVE_ACTION = 'wc_search_orders_by_product_save_settings';

	/**
	 * The admin-post action that hides the getting-started card.
	 */
	const DISMISS_ACTION = 'wc_search_orders_by_product_dismiss_start';

	/**
	 * User meta that records the getting-started card was hidden.
	 */
	const DISMISS_META = 'sobp_start_dismissed';

	/**
	 * Transient caching the order count shown in the stats strip.
	 */
	const COUNT_TRANSIENT = 'wc_search_orders_by_product_order_count';

	/**
	 * The wordpress.org slug.
	 */
	const SLUG = 'wc-search-orders-by-product';

	/**
	 * The page hook WordPress returned, for loading assets on this page only.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( __CLASS__, 'handle_dismiss' ) );
	}

	/**
	 * Capability for the page and its save. Shop managers have it.
	 *
	 * @return string
	 */
	public static function capability() {
		return wc_search_orders_by_product_capability( 'manage_woocommerce' );
	}

	/**
	 * URL of a tab.
	 *
	 * @param string $tab  settings or help.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $tab = 'settings', array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short query args.
	}

	/**
	 * Register the page under the shared WPHEKA menu. Shop managers cannot open the
	 * WPHEKA parent, but still see the menu: WordPress points it at the first page they may open.
	 */
	public static function register_page() {
		$hook = Menu::add_page( __( 'Search Orders By Product', 'wc-search-orders-by-product' ), __( 'Search Orders', 'wc-search-orders-by-product' ), self::capability(), self::PAGE, array( __CLASS__, 'render' ), Icons::menu_icon() );

		self::$hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Load the kit on this page only.
	 *
	 * @param string $hook Page hook.
	 */
	public static function enqueue( $hook ) {
		if ( '' === self::$hook || self::$hook !== $hook ) {
			return;
		}
		Page::enqueue( 'wc-search-orders-by-product', plugins_url( 'assets/wpheka-ui/', WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE ) );
	}

	/**
	 * Save the settings form (admin-post).
	 */
	public static function handle_save() {
		check_admin_referer( self::SAVE_ACTION );
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'wc-search-orders-by-product' ), '', array( 'response' => 403 ) );
		}

		self::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is sanitised in sanitize_settings().

		wp_safe_redirect( self::url( 'settings', array( 'w-saved' => 1 ) ) );
		exit;
	}

	/**
	 * Save the posted form into `sobp_settings`.
	 *
	 * @param array $post Unslashed form values; the settings are under `sobp_settings[...]`.
	 */
	public static function save( array $post ) {
		$raw = isset( $post['sobp_settings'] ) && is_array( $post['sobp_settings'] ) ? $post['sobp_settings'] : array();

		wc_search_orders_by_product_save_settings( WC_Search_Orders_By_Product_Admin_Settings::sanitize_settings( $raw ) );
	}

	/**
	 * Hide the getting-started card for this user (admin-post, also called by fetch()).
	 */
	public static function handle_dismiss() {
		check_admin_referer( self::DISMISS_ACTION );
		if ( current_user_can( self::capability() ) ) {
			update_user_meta( get_current_user_id(), self::DISMISS_META, 1 );
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * The storage the orders screen uses: 'hpos' or 'posts'.
	 *
	 * @return string
	 */
	private static function storage() {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'hpos' : 'posts';
	}

	/**
	 * Whether filters use WooCommerce Analytics' lookup table.
	 *
	 * @return bool
	 */
	public static function fast_lookup() {
		$engine = wc_search_orders_by_product()->engine;

		return $engine && WC_Search_Orders_By_Product_Engine::SOURCE_LOOKUP === $engine->get_source( self::storage() );
	}

	/**
	 * Orders on the store, on the storage in use. Cached for 12 hours.
	 *
	 * @return int
	 */
	public static function order_count() {
		global $wpdb;

		$storage = self::storage();
		$cached  = get_transient( self::COUNT_TRANSIENT );
		if ( is_array( $cached ) && isset( $cached[ $storage ] ) ) {
			return (int) $cached[ $storage ];
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one count, cached in a transient.
		if ( 'hpos' === $storage ) {
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND status NOT IN ( 'trash', 'auto-draft', 'wc-checkout-draft' )" );
		} else {
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status NOT IN ( 'trash', 'auto-draft', 'wc-checkout-draft' )" );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$cached             = is_array( $cached ) ? $cached : array();
		$cached[ $storage ] = (int) $count;
		set_transient( self::COUNT_TRANSIENT, $cached, 12 * HOUR_IN_SECONDS );

		return (int) $count;
	}

	/**
	 * Product categories for the category filter, children labelled with their parents.
	 *
	 * @return array term id => label
	 */
	public static function category_options() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$names = wp_list_pluck( $terms, 'name', 'term_id' );
		$out   = array();
		foreach ( $terms as $term ) {
			$path = array();
			foreach ( array_reverse( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ) as $ancestor ) {
				if ( isset( $names[ $ancestor ] ) ) {
					$path[] = $names[ $ancestor ];
				}
			}
			$path[]                = $term->name;
			$out[ $term->term_id ] = implode( ' › ', $path );
		}
		natcasesort( $out );

		return $out;
	}

	/**
	 * The filters that have a switch, from the filter registry.
	 *
	 * @return array[] Each a registry field with a non-empty `setting`.
	 */
	private static function switch_fields() {
		return array_filter(
			wc_search_orders_by_product()->filters->get_fields(),
			static function ( $field ) {
				return ! empty( $field['setting'] );
			}
		);
	}

	/**
	 * Names of the filters that are on, Product first.
	 *
	 * @return string[]
	 */
	public static function filters_on() {
		$on = array();
		foreach ( wc_search_orders_by_product()->filters->get_fields() as $field ) {
			if ( empty( $field['setting'] ) || wc_search_orders_by_product_setting_enabled( $field['setting'], ! empty( $field['default'] ) ) ) {
				$on[] = $field['label'];
			}
		}

		return $on;
	}

	/**
	 * Links for the header and the help card.
	 *
	 * @return array
	 */
	private static function links() {
		return array(
			'docs'    => 'https://wordpress.org/plugins/' . self::SLUG . '/#faq',
			'support' => 'https://wordpress.org/support/plugin/' . self::SLUG . '/',
			'changes' => 'https://wordpress.org/plugins/' . self::SLUG . '/#developers',
			'review'  => 'https://wordpress.org/support/plugin/' . self::SLUG . '/reviews/#new-post',
			'hooks'   => 'https://github.com/wpheka/wc-search-orders-by-product/blob/master/docs/hooks.md',
			'contact' => 'https://www.wpheka.com/contact/',
		);
	}

	/**
	 * The tab being shown.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which tab to show.

		return 'help' === $tab ? 'help' : 'settings';
	}

	/**
	 * The page.
	 */
	public static function render() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		$tab   = self::current_tab();
		$links = self::links();

		Page::open(
			array(
				'icon'     => plugins_url( 'assets/wpheka-ui/icons/' . self::SLUG . '.png', WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE ),
				'name'     => __( 'Search Orders By Product', 'wc-search-orders-by-product' ),
				'version'  => WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_VERSION,
				'badges'   => array( array( __( 'Free', 'wc-search-orders-by-product' ), 'mint' ) ),
				'subtitle' => __( 'Filter the orders list by product, category, SKU and more.', 'wc-search-orders-by-product' ),
				'crumb'    => __( 'Search Orders', 'wc-search-orders-by-product' ),
				'links'    => array(
					array( __( 'Docs', 'wc-search-orders-by-product' ), $links['docs'], 'book' ),
					array( __( 'Support', 'wc-search-orders-by-product' ), $links['support'], 'chat' ),
					array( __( "What's new", 'wc-search-orders-by-product' ), $links['changes'], 'sparkle' ),
				),
				'cta'      => array( __( 'Rate on WordPress.org', 'wc-search-orders-by-product' ), $links['review'], 'star', 'ghost' ),
				'tabs'     => array(
					array( __( 'Settings', 'wc-search-orders-by-product' ), self::url( 'settings' ), 'settings' === $tab ),
					array( __( 'Help', 'wc-search-orders-by-product' ), self::url( 'help' ), 'help' === $tab ),
				),
			)
		);

		if ( 'settings' === $tab ) {
			self::render_stats();
			self::render_settings( $links );
		} else {
			self::render_help( $links );
		}

		Page::close(
			array(
				'slug'       => self::SLUG,
				'icons_url'  => plugins_url( 'assets/wpheka-ui/icons/', WC_SEARCH_ORDERS_BY_PRODUCT_PLUGIN_FILE ),
				'short_name' => __( 'Search Orders By Product', 'wc-search-orders-by-product' ),
				'review_url' => $links['review'],
				'form'       => 'settings' === $tab ? 'sobp-settings' : '',
				'saved'      => 'settings' === $tab && isset( $_GET['w-saved'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag after our own redirect.
			)
		);
	}

	/**
	 * The stats strip: real numbers only.
	 */
	private static function render_stats() {
		$on         = self::filters_on();
		$total      = count( self::switch_fields() ) + 1;
		$fast       = self::fast_lookup();
		$categories = (array) ( wc_search_orders_by_product_settings()['category_include'] ?? array() );

		Page::stats(
			array(
				array(
					'label' => __( 'Filters on', 'wc-search-orders-by-product' ),
					'icon'  => 'funnel',
					/* translators: %s: number of filters available. */
					'value' => number_format_i18n( count( $on ) ) . ' <small>' . esc_html( sprintf( __( 'of %s', 'wc-search-orders-by-product' ), number_format_i18n( $total ) ) ) . '</small>',
					'meta'  => implode( ', ', $on ),
				),
				array(
					'label' => __( 'Data source', 'wc-search-orders-by-product' ),
					'icon'  => 'bolt',
					'mint'  => true,
					'value' => $fast ? __( 'Fast', 'wc-search-orders-by-product' ) : __( 'Direct', 'wc-search-orders-by-product' ),
					'meta'  => $fast ? __( 'Analytics lookup table', 'wc-search-orders-by-product' ) : __( 'Reads order items', 'wc-search-orders-by-product' ),
					'up'    => $fast,
				),
				array(
					'label' => __( 'Orders on the store', 'wc-search-orders-by-product' ),
					'icon'  => 'box',
					'value' => number_format_i18n( self::order_count() ),
					'meta'  => __( 'Every one can be filtered', 'wc-search-orders-by-product' ),
				),
				array(
					'label' => __( 'Categories listed', 'wc-search-orders-by-product' ),
					'icon'  => 'tag',
					'mint'  => true,
					'value' => $categories ? number_format_i18n( count( $categories ) ) : __( 'All', 'wc-search-orders-by-product' ),
					'meta'  => __( 'In the category filter', 'wc-search-orders-by-product' ),
				),
			)
		);
	}

	/**
	 * Getting-started steps and whether each is done.
	 *
	 * @return array[] Each { label, done }.
	 */
	public static function steps() {
		return array(
			array(
				'label' => __( 'Choose your filters', 'wc-search-orders-by-product' ),
				'done'  => false !== get_option( 'sobp_settings', false ),
			),
			array(
				'label' => __( 'Turn on fast lookup', 'wc-search-orders-by-product' ),
				'done'  => self::fast_lookup(),
			),
			array(
				'label' => __( 'Filter the orders list', 'wc-search-orders-by-product' ),
				'done'  => (int) get_option( 'sobp_filtered_search_count', 0 ) > 0,
			),
		);
	}

	/**
	 * The getting-started card, until every step is done or it is hidden.
	 */
	private static function render_start() {
		if ( get_user_meta( get_current_user_id(), self::DISMISS_META, true ) ) {
			return;
		}
		$steps = self::steps();
		$done  = count( wp_list_filter( $steps, array( 'done' => true ) ) );
		if ( count( $steps ) === $done ) {
			return;
		}
		$dash   = round( 94.25 * $done / count( $steps ), 1 );
		$orders = 'hpos' === self::storage() ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order';

		echo '<div class="w-card w-start"><svg class="w-ring" viewBox="0 0 36 36" aria-hidden="true"><circle cx="18" cy="18" r="15" fill="none" stroke="#ede9fe" stroke-width="4"/>';
		echo '<circle cx="18" cy="18" r="15" fill="none" stroke="#7c3aed" stroke-width="4" stroke-dasharray="' . esc_attr( $dash . ' 94.25' ) . '" stroke-linecap="round" transform="rotate(-90 18 18)"/>';
		echo '<text x="18" y="21.5" text-anchor="middle" font-size="9" font-weight="700" fill="#0f172a">' . esc_html( $done . '/' . count( $steps ) ) . '</text></svg>';
		echo '<div><h3>' . esc_html__( 'Getting started', 'wc-search-orders-by-product' ) . '</h3><ol class="w-steps">';
		foreach ( $steps as $step ) {
			echo '<li' . ( $step['done'] ? ' class="is-done"' : '' ) . '>' . esc_html( $step['label'] ) . '<span class="w-sr"> ' . esc_html( $step['done'] ? __( '(done)', 'wc-search-orders-by-product' ) : __( '(to do)', 'wc-search-orders-by-product' ) ) . '</span></li>';
		}
		echo '</ol></div><div class="w-start-actions">';
		echo '<a class="w-btn w-btn--secondary w-btn--sm" href="' . esc_url( admin_url( $orders ) ) . '">' . esc_html__( 'Open the orders list', 'wc-search-orders-by-product' ) . '</a>';
		echo '<a class="w-close" data-w-dismiss=".w-start" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION ) ) . '" aria-label="' . esc_attr__( 'Hide getting started', 'wc-search-orders-by-product' ) . '">' . Icons::get( 'xcircle' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		echo '</div></div>';
	}

	/**
	 * The Settings tab.
	 *
	 * @param array $links Help links.
	 */
	private static function render_settings( array $links ) {
		$nav = array(
			array( 'filters', 'funnel', __( 'Filters', 'wc-search-orders-by-product' ) ),
			array( 'category-filter', 'tag', __( 'Category filter', 'wc-search-orders-by-product' ) ),
			array( 'orders-list', 'list', __( 'Orders list', 'wc-search-orders-by-product' ) ),
			array( 'performance', 'gauge', __( 'Performance', 'wc-search-orders-by-product' ) ),
		);

		/**
		 * Links in the Settings tab's section nav. An add-on that prints a section
		 * on `wc_search_orders_by_product_settings_sections` adds its link here.
		 *
		 * @since 4.1.0
		 * @param array[] $nav Each [ section id, kit icon name, label ].
		 */
		$nav = (array) apply_filters( 'wc_search_orders_by_product_settings_nav', $nav );

		echo '<div class="w-stack">';
		self::render_start();
		echo '<div class="w-layout"><div>';
		Page::nav( $nav );
		echo '</div><form id="sobp-settings" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-w-form>';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_ACTION ) . '">';
		wp_nonce_field( self::SAVE_ACTION );
		self::render_fields();
		// Without JavaScript the save bar never appears, so the form keeps a plain button.
		echo '<noscript><p><button type="submit" class="w-btn w-btn--primary">' . esc_html__( 'Save changes', 'wc-search-orders-by-product' ) . '</button></p></noscript>';
		echo '</form><aside class="w-rail" aria-label="' . esc_attr__( 'Review and help', 'wc-search-orders-by-product' ) . '">';
		Page::review_card( __( 'Enjoying Search Orders?', 'wc-search-orders-by-product' ), __( 'A quick review helps other store owners find it and keeps it improving.', 'wc-search-orders-by-product' ), $links['review'] );
		Page::help_card(
			array(
				array( __( 'Read the documentation', 'wc-search-orders-by-product' ), $links['docs'], 'book' ),
				array( __( 'Ask in the support forum', 'wc-search-orders-by-product' ), $links['support'], 'chat' ),
				array( __( 'Contact WPHEKA', 'wc-search-orders-by-product' ), $links['contact'], 'mail' ),
			)
		);
		echo '</aside></div></div>';
	}

	/**
	 * Print the settings sections.
	 */
	private static function render_fields() {
		$settings = wc_search_orders_by_product_settings();

		Fields::section_start( 'filters', __( 'Filters on the orders screen', 'wc-search-orders-by-product' ), __( 'Choose which filters appear above the WooCommerce orders list.', 'wc-search-orders-by-product' ) );
		foreach ( wc_search_orders_by_product()->filters->get_fields() as $field ) {
			if ( empty( $field['setting'] ) ) {
				Fields::info_row(
					array(
						'label' => $field['label'],
						'help'  => $field['description'] ?? '',
						'pill'  => __( 'Always on', 'wc-search-orders-by-product' ),
						'kind'  => 'info',
					)
				);
				continue;
			}
			Fields::switch_row(
				array(
					'name'     => 'sobp_settings[' . $field['setting'] . ']',
					'checked'  => wc_search_orders_by_product_setting_enabled( $field['setting'], ! empty( $field['default'] ) ),
					'label'    => $field['label'],
					'help'     => $field['description'] ?? '',
					'controls' => 'search_orders_by_product_category' === $field['setting'] ? 'sobp-category-rows' : '',
				)
			);
		}
		Fields::section_end();

		Fields::section_start( 'category-filter', __( 'Category filter', 'wc-search-orders-by-product' ), __( 'Which categories the category filter lists.', 'wc-search-orders-by-product' ) );
		Fields::sub_start( 'sobp-category-rows' );
		Fields::chips_row(
			array(
				'name'     => 'sobp_settings[category_include]',
				'options'  => self::category_options(),
				'selected' => array_map( 'strval', (array) ( $settings['category_include'] ?? array() ) ),
				'label'    => __( 'Categories in the filter', 'wc-search-orders-by-product' ),
				'help'     => __( 'Leave every category unticked to list them all, or tick the ones your team uses.', 'wc-search-orders-by-product' ),
				'search'   => __( 'Find a category', 'wc-search-orders-by-product' ),
			)
		);
		Fields::sub_end();
		Fields::section_end();

		Fields::section_start( 'orders-list', __( 'Orders list', 'wc-search-orders-by-product' ) );
		Fields::switch_row(
			array(
				'name'    => 'sobp_settings[purchased_items_column]',
				'checked' => wc_search_orders_by_product_setting_enabled( 'purchased_items_column', true ),
				'label'   => __( 'Purchased items column', 'wc-search-orders-by-product' ),
				'help'    => __( 'Shows the items and quantities of each order in the orders list.', 'wc-search-orders-by-product' ),
			)
		);

		/**
		 * After the switches on the settings page, inside the Orders list card.
		 *
		 * @since 4.0
		 * @param array $settings Current settings.
		 */
		do_action( 'wc_search_orders_by_product_settings_after_fields', $settings );
		Fields::section_end();

		$fast = self::fast_lookup();
		Fields::section_start( 'performance', __( 'Performance', 'wc-search-orders-by-product' ), __( 'How filters read your orders.', 'wc-search-orders-by-product' ) );
		Fields::info_row(
			array(
				'label' => __( 'Fast lookup', 'wc-search-orders-by-product' ),
				'help'  => $fast
					? __( 'Filters use WooCommerce Analytics\' indexed order data, so they stay fast on large stores.', 'wc-search-orders-by-product' )
					: __( 'Filters read order items directly. For faster filtering on large stores, import historical data in WooCommerce > Settings > Advanced > Features or Analytics > Settings.', 'wc-search-orders-by-product' ),
				'pill'  => $fast ? __( 'On', 'wc-search-orders-by-product' ) : __( 'Off', 'wc-search-orders-by-product' ),
				'kind'  => $fast ? 'ok' : 'warn',
			)
		);
		Fields::section_end();

		/**
		 * Print an add-on's own sections on the Settings tab, inside the settings form.
		 *
		 * Build them with the bundled kit (WC_Search_Orders_By_Product\AdminUI\Fields)
		 * so they match, and add a nav link on `wc_search_orders_by_product_settings_nav`.
		 * Fields named `sobp_settings[key]` are saved when the add-on also adds `key` on
		 * `wc_search_orders_by_product_sanitize_settings`. A control that posts somewhere
		 * else needs its own form outside this one, joined with the form="" attribute.
		 *
		 * @since 4.1.0
		 * @param array $settings Current settings.
		 */
		do_action( 'wc_search_orders_by_product_settings_sections', $settings );

		Fields::find_empty();
	}

	/**
	 * The Help tab.
	 *
	 * @param array $links Help links.
	 */
	private static function render_help( array $links ) {
		$cards = array(
			array( 'book', __( 'Documentation', 'wc-search-orders-by-product' ), __( 'How each filter works, with answers to common questions.', 'wc-search-orders-by-product' ), $links['docs'] ),
			array( 'chat', __( 'Support forum', 'wc-search-orders-by-product' ), __( 'Ask a question. The developers who built the plugin answer.', 'wc-search-orders-by-product' ), $links['support'] ),
			array( 'sparkle', __( "What's new", 'wc-search-orders-by-product' ), __( 'Every change in each version, in plain words.', 'wc-search-orders-by-product' ), $links['changes'] ),
			array( 'code', __( 'Developer hooks', 'wc-search-orders-by-product' ), __( 'Add your own filters, conditions and settings.', 'wc-search-orders-by-product' ), $links['hooks'] ),
		);
		echo '<div class="w-flow">';
		foreach ( $cards as $card ) {
			echo '<a class="w-card w-flow-step" href="' . esc_url( $card[3] ) . '" target="_blank" rel="noopener"><span class="w-ico w-ico--lg">' . Icons::get( $card[0] ) . '</span><h3>' . esc_html( $card[1] ) . '</h3><p>' . esc_html( $card[2] ) . '<span class="w-sr"> ' . esc_html__( '(opens in a new tab)', 'wc-search-orders-by-product' ) . '</span></p></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '</div>';
	}
}
