<?php
/**
 * Filter registry for the orders screen.
 *
 * Every filter is described once here: its URL parameter, its setting, how its
 * value is read and how its control is drawn. The admin class renders and
 * applies whatever this registry returns, so an add-on can add filters through
 * the `wc_search_orders_by_product_filter_fields` filter.
 *
 * @package WC_Search_Orders_By_Product
 * @since   4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Search_Orders_By_Product_Filters class.
 */
final class WC_Search_Orders_By_Product_Filters {

	/**
	 * Cached registry.
	 *
	 * @var array|null
	 */
	private $fields = null;

	/**
	 * The registered filters.
	 *
	 * Each entry: query_var (URL parameter), value_key (key in the values array
	 * the engine reads), label, description (one line for the settings page), setting (sobp_settings key, or '' if always on),
	 * default (on when the setting has never been saved), sanitize (callable
	 * turning the raw URL value into the stored value) and render (callable
	 * printing the control, given the current value).
	 *
	 * @return array
	 */
	public function get_fields() {
		if ( null !== $this->fields ) {
			return $this->fields;
		}

		$fields = array(
			'product'         => array(
				'query_var' => 'product_id',
				'value_key' => 'product_ids',
				'label'     => __( 'Product', 'wc-search-orders-by-product' ),
				'description' => __( 'Search by product name. A variable product also finds orders of its variations.', 'wc-search-orders-by-product' ),
				'setting'   => '',
				'default'   => true,
				'sanitize'  => array( $this, 'sanitize_id_list' ),
				'render'    => array( $this, 'render_product' ),
			),
			'product_type'    => array(
				'query_var' => 'search_product_type',
				'value_key' => 'product_type',
				'label'     => __( 'Product types', 'wc-search-orders-by-product' ),
				'description' => __( 'Simple, variable, grouped and other product types.', 'wc-search-orders-by-product' ),
				'setting'   => 'search_orders_by_product_type',
				'default'   => true,
				'sanitize'  => 'sanitize_title',
				'render'    => array( $this, 'render_product_type' ),
			),
			'product_cat'     => array(
				'query_var' => 'search_product_cat',
				'value_key' => 'category_ids',
				'label'     => __( 'Product categories', 'wc-search-orders-by-product' ),
				'description' => __( 'Shown as a tree. A category also finds orders from its sub-categories.', 'wc-search-orders-by-product' ),
				'setting'   => 'search_orders_by_product_category',
				'default'   => true,
				'sanitize'  => array( $this, 'sanitize_id_list' ),
				'render'    => array( $this, 'render_product_cat' ),
			),
			'sku'             => array(
				'query_var' => 'search_sku',
				'value_key' => 'sku',
				'label'     => __( 'SKU', 'wc-search-orders-by-product' ),
				'description' => __( 'Type a SKU or its first characters. Matches products and variations.', 'wc-search-orders-by-product' ),
				'setting'   => 'search_orders_by_sku',
				'default'   => false,
				'sanitize'  => array( $this, 'sanitize_text' ),
				'render'    => array( $this, 'render_sku' ),
			),
			'payment_method'  => array(
				'query_var' => 'search_payment_method',
				'value_key' => 'payment_method',
				'label'     => __( 'Payment methods', 'wc-search-orders-by-product' ),
				'description' => __( 'Every payment gateway on the store, including ones switched off.', 'wc-search-orders-by-product' ),
				'setting'   => 'search_orders_by_payment_method',
				'default'   => false,
				'sanitize'  => array( $this, 'sanitize_text' ),
				'render'    => array( $this, 'render_payment_method' ),
			),
			'shipping_method' => array(
				'query_var' => 'search_shipping_method',
				'value_key' => 'shipping_method',
				'label'     => __( 'Shipping methods', 'wc-search-orders-by-product' ),
				'description' => __( 'Flat rate, free shipping, local pickup and other methods.', 'wc-search-orders-by-product' ),
				'setting'   => 'search_orders_by_shipping_method',
				'default'   => false,
				'sanitize'  => array( $this, 'sanitize_text' ),
				'render'    => array( $this, 'render_shipping_method' ),
			),
			'billing_country' => array(
				'query_var' => 'search_billing_country',
				'value_key' => 'billing_country',
				'label'     => __( 'Billing countries', 'wc-search-orders-by-product' ),
				'description' => __( 'The country on the order\'s billing address.', 'wc-search-orders-by-product' ),
				'setting'   => 'search_orders_by_billing_country',
				'default'   => false,
				'sanitize'  => array( $this, 'sanitize_country' ),
				'render'    => array( $this, 'render_billing_country' ),
			),
		);

		/**
		 * Filters registered on the orders screen.
		 *
		 * @since 4.0
		 * @param array $fields Filter definitions, see get_fields().
		 */
		$this->fields = (array) apply_filters( 'wc_search_orders_by_product_filter_fields', $fields );

		return $this->fields;
	}

	/**
	 * Whether a filter is switched on in settings.
	 *
	 * @param array $field Filter definition.
	 * @return bool
	 */
	public function is_enabled( array $field ) {
		if ( empty( $field['setting'] ) ) {
			return true;
		}

		return wc_search_orders_by_product_setting_enabled( $field['setting'], ! empty( $field['default'] ) );
	}

	/**
	 * Read the active filter values from the request.
	 *
	 * Display and filtering only: these values narrow a query and change
	 * nothing, and the orders screen is reached by a GET link, so there is no
	 * nonce to check. WooCommerce reads its own list filters the same way.
	 *
	 * @return array value_key => sanitised value, for filters that have a value.
	 */
	public function get_values() {
		$values = array();

		foreach ( $this->get_fields() as $field ) {
			if ( ! $this->is_enabled( $field ) || empty( $field['query_var'] ) ) {
				continue;
			}

			$raw = isset( $_GET[ $field['query_var'] ] ) ? wp_unslash( $_GET[ $field['query_var'] ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- list filter, no state change; sanitised by the field's callback below.
			if ( '' === $raw || array() === $raw ) {
				continue;
			}

			$value = is_callable( $field['sanitize'] ) ? call_user_func( $field['sanitize'], $raw ) : sanitize_text_field( (string) $raw );
			if ( '' !== $value && array() !== $value && null !== $value ) {
				$values[ $field['value_key'] ] = $value;
			}
		}

		/**
		 * Sanitised filter values read from the request.
		 *
		 * @since 4.0
		 * @param array $values Values by value_key.
		 */
		return (array) apply_filters( 'wc_search_orders_by_product_request', $values );
	}

	/**
	 * Whether any filter is active.
	 *
	 * @param array $values Values from get_values().
	 * @return bool
	 */
	public function is_filtering( array $values ) {
		/**
		 * Whether the orders screen is being filtered by this plugin.
		 *
		 * @since 4.0
		 * @param bool  $filtering Whether any value is set.
		 * @param array $values    Values by value_key.
		 */
		return (bool) apply_filters( 'wc_search_orders_by_product_is_filtering', ! empty( $values ), $values );
	}

	/**
	 * Print every enabled filter control.
	 *
	 * @param string $storage 'hpos' or 'posts'.
	 * @return void
	 */
	public function render( $storage ) {
		$values = $this->get_values();

		/**
		 * Before the filter controls on the orders screen.
		 *
		 * @since 4.0
		 * @param string $storage 'hpos' or 'posts'.
		 */
		do_action( 'wc_search_orders_by_product_before_filters', $storage );

		foreach ( $this->get_fields() as $field ) {
			if ( $this->is_enabled( $field ) && is_callable( $field['render'] ) ) {
				call_user_func( $field['render'], $values[ $field['value_key'] ] ?? null, $field );
			}
		}

		/**
		 * After the filter controls on the orders screen.
		 *
		 * @since 4.0
		 * @param string $storage 'hpos' or 'posts'.
		 */
		do_action( 'wc_search_orders_by_product_after_filters', $storage );
	}

	/**
	 * Product search box: WooCommerce's own select, searching products and variations.
	 *
	 * @param int[]|null $value Selected ids.
	 * @return void
	 */
	public function render_product( $value ) {
		$selected = array();
		foreach ( (array) $value as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$selected[ $id ] = $product->get_formatted_name();
			}
		}

		/**
		 * Attributes of the product search box.
		 *
		 * @since 4.0
		 * @param array $attributes HTML attributes.
		 */
		$attributes = (array) apply_filters(
			'wc_search_orders_by_product_product_select_attributes',
			array(
				'class'            => 'wc-product-search',
				'id'               => 'product_id',
				'name'             => 'product_id',
				'data-placeholder' => __( 'Search for a product&hellip;', 'wc-search-orders-by-product' ),
				'data-action'      => 'woocommerce_json_search_products_and_variations',
				'data-allow_clear' => 'true',
				'style'            => 'width: 240px;',
			)
		);

		echo '<select';
		foreach ( $attributes as $name => $attr_value ) {
			echo ' ' . esc_attr( $name ) . '="' . esc_attr( $attr_value ) . '"';
		}
		echo '>';
		foreach ( $selected as $id => $label ) {
			// wp_strip_all_tags: the formatted name may contain HTML; selectWoo shows it as text.
			echo '<option value="' . esc_attr( $id ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $label ) ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Product type dropdown.
	 *
	 * @param string|null $value Selected type.
	 * @return void
	 */
	public function render_product_type( $value ) {
		/**
		 * Product types offered in the filter.
		 *
		 * @since 4.0
		 * @param array $types Slug => label.
		 */
		$types = (array) apply_filters( 'wc_search_orders_by_product_product_types', wc_get_product_types() );

		echo '<select name="search_product_type" id="dropdown_product_type">';
		echo '<option value="">' . esc_html__( 'Filter by product type', 'wc-search-orders-by-product' ) . '</option>';
		foreach ( $types as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( (string) $value, (string) $slug, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Hierarchical category dropdown.
	 *
	 * @param int[]|null $value Selected category ids.
	 * @return void
	 */
	public function render_product_cat( $value ) {
		$include = array_filter( array_map( 'absint', (array) ( wc_search_orders_by_product_settings()['category_include'] ?? array() ) ) );

		/**
		 * Arguments for the category dropdown.
		 *
		 * @since 4.0
		 * @param array $args wp_dropdown_categories() arguments.
		 */
		$args = (array) apply_filters(
			'wc_search_orders_by_product_category_terms_args',
			array(
				'taxonomy'          => 'product_cat',
				'name'              => 'search_product_cat',
				'class'             => 'dropdown_product_cat',
				'id'                => 'dropdown_product_cat',
				'hierarchical'      => true,
				'hide_empty'        => false,
				'show_option_none'  => __( 'Filter by product category', 'wc-search-orders-by-product' ),
				'option_none_value' => '',
				'value_field'       => 'term_id',
				'selected'          => $value ? absint( current( (array) $value ) ) : 0,
				'include'           => $include,
				'orderby'           => 'name',
				'echo'              => true,
			)
		);

		wp_dropdown_categories( $args );
	}

	/**
	 * SKU text box.
	 *
	 * @param string|null $value Current text.
	 * @return void
	 */
	public function render_sku( $value ) {
		echo '<input type="search" name="search_sku" id="search_sku" value="' . esc_attr( (string) $value ) . '" placeholder="' . esc_attr__( 'SKU', 'wc-search-orders-by-product' ) . '" style="width: 120px;" />';
	}

	/**
	 * Payment method dropdown.
	 *
	 * @param string|null $value Selected gateway id.
	 * @return void
	 */
	public function render_payment_method( $value ) {
		$options = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
				$options[ $id ] = wp_strip_all_tags( $gateway->get_method_title() ? $gateway->get_method_title() : $gateway->get_title() );
			}
		}

		$this->print_select( 'search_payment_method', __( 'Filter by payment method', 'wc-search-orders-by-product' ), $options, $value );
	}

	/**
	 * Shipping method dropdown.
	 *
	 * @param string|null $value Selected method id.
	 * @return void
	 */
	public function render_shipping_method( $value ) {
		$options = array();
		if ( function_exists( 'WC' ) && WC()->shipping() ) {
			foreach ( WC()->shipping()->get_shipping_methods() as $id => $method ) {
				$options[ $id ] = wp_strip_all_tags( $method->get_method_title() );
			}
		}

		$this->print_select( 'search_shipping_method', __( 'Filter by shipping method', 'wc-search-orders-by-product' ), $options, $value );
	}

	/**
	 * Billing country dropdown.
	 *
	 * @param string|null $value Selected country code.
	 * @return void
	 */
	public function render_billing_country( $value ) {
		$options = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : array();

		$this->print_select( 'search_billing_country', __( 'Filter by billing country', 'wc-search-orders-by-product' ), $options, $value );
	}

	/**
	 * Print a plain dropdown.
	 *
	 * @param string      $name        Field name and id.
	 * @param string      $placeholder First, empty option.
	 * @param array       $options     Value => label.
	 * @param string|null $value       Selected value.
	 * @return void
	 */
	private function print_select( $name, $placeholder, array $options, $value ) {
		echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
		echo '<option value="">' . esc_html( $placeholder ) . '</option>';
		foreach ( $options as $option_value => $label ) {
			echo '<option value="' . esc_attr( $option_value ) . '"' . selected( (string) $value, (string) $option_value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * A comma-separated list or array of ids.
	 *
	 * @param mixed $raw Raw value.
	 * @return int[]
	 */
	public function sanitize_id_list( $raw ) {
		$parts = is_array( $raw ) ? $raw : explode( ',', (string) $raw );

		return array_values( array_unique( array_filter( array_map( 'absint', $parts ) ) ) );
	}

	/**
	 * Plain text.
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	public function sanitize_text( $raw ) {
		return is_scalar( $raw ) ? trim( sanitize_text_field( (string) $raw ) ) : '';
	}

	/**
	 * A two-letter country code.
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	public function sanitize_country( $raw ) {
		$code = is_scalar( $raw ) ? strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $raw ), 0, 2 ) ) : '';

		return 2 === strlen( $code ) ? $code : '';
	}
}
