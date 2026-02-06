=== Dual Currency Display ===
Contributors: ignatovdesigns
Tags: woocommerce, currency, bgn, eur, conversion
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.7
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your WooCommerce store prices in both Bulgarian Leva (BGN) and Euro (EUR) with flexible conversion tools.

== Description ==

**Dual Currency Display for WooCommerce** provides a seamless way to display prices in both Bulgarian Leva (BGN) and Euro (EUR) throughout your WooCommerce store. Perfect for Bulgarian businesses selling internationally or preparing for Euro adoption.

**Compatibility Notice:**
This plugin is currently compatible only with Bulgarisation for WooCommerce and Stripe payment gateway combination. Known issues exist with other WooCommerce configurations and payment gateways.

### Key Features

* **Dual Currency Display** - Show prices in both BGN and EUR throughout your store
* **Flexible Configuration** - Set either BGN or EUR as your primary currency
* **Automatic Conversion** - All prices are automatically converted using the exchange rate you set
* **Currency Conversion Tools** - Easily convert all your product prices from BGN to EUR or EUR to BGN
* **Data Safety** - Automatic backup of price data before any conversion
* **Easy Restore** - Revert to original prices if needed
* **Modern Admin Interface** - Beautiful, intuitive admin pages

### Where Dual Currency is Displayed

* Product pages
* Cart
* Checkout
* Order details
* Mini cart
* Order emails
* Admin order pages

### Perfect For

* Bulgarian online stores selling to EU customers
* Businesses preparing for Bulgaria's eventual Euro adoption
* Any WooCommerce store needing to display BGN and EUR prices

### Compatibility Requirements

* Works perfectly with Bulgarisation for WooCommerce + Stripe
* Other payment gateways may cause conflicts
* Other localization plugins are not supported at this time

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/dual-currency-display` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the 'Currency Conversion' menu item in the WordPress admin to configure the plugin.

== Frequently Asked Questions ==

= Does this change how customers pay? =

No, customers will still pay in your store's base currency. This plugin only affects how prices are displayed.

= Can I use a different exchange rate than the official one? =

Yes, you can set any exchange rate you want. The official BGN/EUR exchange rate is fixed at 1.95583.

= Will this affect my product prices in the database? =

Not by default. The plugin simply displays the second currency alongside your primary currency. However, if you use the conversion tools, it will modify your product prices.

= Is there a way to revert back if I change my primary currency? =

Yes, the plugin automatically creates a backup before any conversion, and provides a restore tool.

= Does this work with variable products and product variations? =

Yes, all product types are supported.

= What if I'm not using Bulgarisation for WooCommerce and Stripe? =

Currently, this plugin is only compatible with Bulgarisation for WooCommerce and Stripe combination. Other setups may experience issues and are not supported at this time.

== Screenshots ==

1. Product page with dual currency display
2. Cart page showing both currencies
3. Currency conversion admin page
4. Exchange rate settings

== Changelog ==
= 1.0.6 =
* Updated Bulgarian translations

= 1.0.5 =
* Fixed: Rounding errors when displaying secondary currency (e.g., 50.00 BGN now stays 50.00 instead of showing 49.99 or 50.01)

= 1.0.4 =
* Fixed: Rounding errors when displaying secondary currency (e.g., 50.00 BGN now stays 50.00 instead of showing 49.99 or 50.01)
* Added: Storage of original prices during currency conversion to maintain accuracy
* Enhanced: Sale prices now display secondary currency for both regular and sale amounts (e.g., ~~45,00 лв. (23,01 €)~~ 10,00 лв. (5,11 €))
* Improved: More accurate cart and checkout totals using stored original prices
* Updated: Variable product price handling for better accuracy with stored prices

= 1.0.3 =
* Fixed VAT calculation issue in EUR subtotal display
* EUR subtotal now correctly includes tax amount

= 1.0.2 =
* Initial release

= 1.0.0 =
* Initial release