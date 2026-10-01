=== WC Search Orders By Product ===
Contributors: akshayaswaroop, wpheka
Tags: search orders, woocommerce, order, product, filter
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 3.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://www.paypal.me/AKSHAYASWAROOP
A simple plugin that helps you search your WooCommerce orders by product.

== Description ==
WC Search Orders By Product does exactly what it says - This WooCommerce extension **automatically** adds product search, product type and product category filter dropdown in WooCommerce Orders screen. You can find orders by typing just a few characters of your product name. As you start typing in the search input, you will see instant results popping up inside the dropdown menu. The auto listing of the matching products with same characters inside the dropdown will help you in typo tolerance or if you misspell the product name.

= Search woocommerce orders: =

* By product **name**
* By product **type**
* By product **category**

If you enjoyed this plugin then please put a review, that will encourage me to bring some more …

== Installation ==

= Minimum Requirements =

* WooCommerce 3.0 or later

1. Upload 'wc-Search-orders-by-product' to the '/wp-content/plugins/' directory or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Admin area -> WPHEKA -> WC Search Orders By Product Settings
4. Go to Admin area -> WooCommerce -> Orders
5. Done!

== Frequently Asked Questions ==

= How It Works? =
*check installation*

== Screenshots ==

1. Plugin settings link.
2. Plugin settings screen

== Changelog ==

= 3.4 - 2026-10-01 =
* Fix - The plugin no longer interferes with WooCommerce Subscriptions. The subscriptions list could come up empty, and Subscriptions could be handed orders where it expected subscriptions.
* Fix - On stores using High-Performance Order Storage, a filtered orders list now respects the selected status tab and pagination, and trashed orders show in the Trash view.
* Fix - The orders list is left untouched when no product filter is selected.
* Enhancement - WooCommerce 11.1.2 compatibility.

= 3.3 - 2026-08-22 =
* Feature - Asks for a review on the Dashboard once the plugin has been used, with a link that pre-selects a five-star rating. Shown only after three filtered order searches, dismissed per user rather than for the whole site, and never shown again once dismissed.
* Security - The deactivation feedback handler required neither a nonce nor a capability. Both are now checked, and the nonce is compared correctly.
* Security - Escaped remaining admin output, including the settings screen and the deactivation dialog's inline script.
* Fix - Order filtering no longer produces an SQL error when a filter matches no products.
* Fix - Fourteen admin strings are translatable again. Twelve passed a variable as their text domain, which translation tools cannot extract, and two had no text domain at all.
* Fix - The deactivation dialog no longer leaves its button stuck on "Processing" when the request fails. Deactivation proceeds either way.
* Fix - The settings check returns a boolean and no longer warns when asked about an unknown setting.
* Enhancement - The settings screen now appears under the shared WPHEKA menu.
* Enhancement - Adopted the WPHEKA framework for the HPOS declaration, settings storage and the shared menu.
* Enhancement - Requires PHP 8.1 and WordPress 6.5. WordPress 7.1 and WooCommerce 11.0.1 compatibility added.

= 3.2 - 2026-02-12 =
* Security - Fixed SQL injection vulnerabilities in product type filtering.
* Security - Added proper input sanitization for all $_GET parameters.
* Security - Added proper input sanitization for AJAX form submissions.
* Security - Improved output escaping in product category dropdown.
* Fix - Fixed HPOS filters to use woocommerce_order_items table instead of empty analytics table.
* Fix - Corrected all HPOS filter methods (product, category, type) to query correct tables.
* Fix - Fixed unclosed HTML option tag in product search dropdown.
* Fix - Fixed TypeError when filtering orders by product (sanitization compatibility issue).
* Enhancement - WooCommerce version 10.5.1 compatibility added.
* Enhancement - WordPress version 6.9.1 compatibility added.

= 3.1 - 2025-05-14 =
* Enhancement - WooCommerce HPOS compatibility added.
* Enhancement - WooCommerce version 9.8.5 compatibility added.

= 3.0 - 2024-11-30 =
* Enhancement - WooCommerce version 9.4.2 compatibility added.

= 2.0 - 2023-08-24 =
* Enhancement - WordPress version 6.3 compatibility added.

= 1.9 - 2022-10-21 =
* Fix - woocommerce trashed orders visibility.

= 1.8 - 2022-05-25 =
* Enhancement - WordPress version 6.0 compatibility added.

= 1.7 - 2022-05-24 =
* Fix - Trying to access array offset on value of type bool on plugin settings page.

= 1.6 - 2021-08-13 =
* Enhancement - WooCommerce version 5.5.2 compatibility added.
* Enhancement - WordPress version 5.8 compatibility added.

= 1.5 - 2020-08-20 =
* Enhancement - WooCommerce version 4.0 compatibility added.
* Enhancement - Search functionality updated.
* Enhancement - Deactivation feedback form updated.

= 1.4 - 2020-04-20 =
* Enhancement - Deactivation feedback form added.

= 1.3 - 2020-03-15 =
* Fix - Plugin structure updated.

= 1.2 - 2019-11-19 =
* Fix - WooCommerce Version 3.8.0 compatibility added.

= 1.1 =
* Dependency fixes
* Search orders by product category functionality added.

= 1.0 =
* Initial release

== Upgrade Notice ==

= 1.0 =
* Initial release