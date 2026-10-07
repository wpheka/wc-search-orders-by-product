# WC Search Orders By Product: developer hooks

Available from version 4.0. All hooks are prefixed `wc_search_orders_by_product_`.

## Loading

| Hook | Type | Arguments | Use |
|---|---|---|---|
| `wc_search_orders_by_product_ready` | action | `WC_Search_Orders_By_Product $plugin` | Fires on `plugins_loaded` (priority 20). Add-ons should start here. |
| `wc_search_orders_by_product_loaded` | action | none | Kept for compatibility. Fires while the plugin file loads, before most plugins, so add-ons usually miss it. |

The main object, `wc_search_orders_by_product()`, exposes:

| Property | Contents |
|---|---|
| `->engine` | the query engine (`WC_Search_Orders_By_Product_Engine`) |
| `->filters` | the filter registry (`WC_Search_Orders_By_Product_Filters`) |
| `->admin` | the orders screen handler; set in admin requests only |

## Filters on the orders screen

| Hook | Type | Arguments | Use |
|---|---|---|---|
| `wc_search_orders_by_product_filter_fields` | filter | `array $fields` | Add, remove or reorder filters. Each entry has the keys listed below the table. |
| `wc_search_orders_by_product_request` | filter | `array $values` | Sanitised values read from the URL, keyed by `value_key`. |
| `wc_search_orders_by_product_is_filtering` | filter | `bool $filtering, array $values` | Whether the list is being filtered. |
| `wc_search_orders_by_product_conditions` | filter | `string[] $conditions, array $values, string $storage, string $id_column` | SQL conditions ANDed into the list query and its count. `$storage` is `hpos` or `posts`; `$id_column` is the order id column to compare against. |
| `wc_search_orders_by_product_query_args` | filter | `array $query_args, array $values` | `wc_get_orders()` arguments of the filtered HPOS list. |
| `wc_search_orders_by_product_filtered` | action | `array $values, string $storage` | After the conditions are built. |
| `wc_search_orders_by_product_before_filters` / `_after_filters` | action | `string $storage` | Print markup before or after the controls. |
| `wc_search_orders_by_product_product_select_attributes` | filter | `array $attributes` | HTML attributes of the product search box (for example `multiple`). |
| `wc_search_orders_by_product_category_terms_args` | filter | `array $args` | `wp_dropdown_categories()` arguments of the category filter. |
| `wc_search_orders_by_product_product_types` | filter | `array $types` | Product types offered (slug => label). |
| `wc_search_orders_by_product_lookup_available` | filter | `bool $available, string $storage` | Force product matching through WooCommerce's order product lookup table (`true`) or order item meta (`false`). |

**Keys of each `filter_fields` entry:**

| Key | Meaning |
|---|---|
| `query_var` | URL parameter |
| `value_key` | key in the values array |
| `label` | text shown for the filter |
| `setting` | settings key; empty means the filter is always on |
| `default` | on or off before the setting is first saved |
| `sanitize` | callable that cleans the value |
| `render` | callable that prints the control |

**Writing a condition:** return it in the form `"{$id_column} IN ( SELECT order_id FROM ... )"`. That form is fast on MySQL 5.7 and later. Avoid nesting one `IN ( SELECT ... )` inside another, because MySQL 5.7 runs the inner one once for every row.

## Settings

| Hook | Type | Arguments | Use |
|---|---|---|---|
| `wc_search_orders_by_product_settings_fields` | filter | `array $fields` | Fields of the WooCommerce > Settings > Advanced > Search orders by product section. |
| `wc_search_orders_by_product_settings_after_fields` | action | `array $settings` | Markup after the switches on the WPHEKA settings page. |
| `wc_search_orders_by_product_sanitize_settings` | filter | `array $settings, array $raw` | Settings about to be saved from a settings form. Add-on keys must be added here, or they are dropped. |
| `wc_search_orders_by_product_capability` | filter | `string $capability` | Capability for the filters (`edit_shop_orders`) and the settings (`manage_woocommerce`). |

All settings are stored in the `sobp_settings` option. Switches are stored as `yes` or `no`. Read them with `wc_search_orders_by_product_setting_enabled( $key, $default )`.
