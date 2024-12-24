=== CDEKDelivery ===
Contributors: cdekit, caelan
Tags: ecommerce, shipping, delivery, woocommerce
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 6.5
Stable tag: 3.22.5
License: GPLv3

Integration with CDEK delivery for your WooCommerce store.

== Description ==

CDEKDelivery provides integration with CDEK delivery for your store on the WordPress WooCommerce platform. This plugin allows you to customize delivery settings according to your store requirements and allow customers to choose CDEK shipping when placing orders.

Main plugin features:

* Test mode for checking operation without real data integration.
* Processing of international orders and providing appropriate delivery options.
* Automatically sending orders to CDEK after checkout on the website.
* Selection of various rates for shipment based on customer requirements and product characteristics.
* You can change standard rate names to adapt them to specific needs.
* Multi-seater mode to distribute order items across different packages.
* Creation of a request for courier pickup.
* Parcel actual status on the admin order page.
* Extra days to the estimated delivery days, considering possible delays.
* Default product dimensions for more accurate shipping cost calculation.
* Printing of order receipts and barcodes for shipping.
* Provide a choice of various additional services, such as insurance and fitting, as well as flexible modification of the shipping cost depending on the selected services and order parameters.

## Plugin functions
* Calculation of cost and delivery time
* Selection of the pickup point via the map
* Easy installation, integration into WooCommerce
* Setting up store data: address, choice of tariff and type of shipment
* Possibility of transferring current data on packaging and automatic calculation of the order weight
* Compatible with [High-Performance Order Storage](https://woocommerce.com/document/high-performance-order-storage/)
* Works well with [Block checkout](https://woocommerce.com/checkout-blocks/) and [Classic checkout](https://woocommerce.com/document/woocommerce-shortcodes/page-shortcodes/#checkout)

## Access to third-party services

The CDEKDelivery plugin uses the following third-party services to provide its functionality:

1. **api.cdek.ru**: The CDEK API is used to calculate the cost and delivery time of an order. The privacy policy of this service is available at [site](https://www.cdek.ru/ru/privacy_policy/)

2. **api.edu.cdek.ru**: The CDEK API is used in test mode to calculate the cost and delivery time of an order. The privacy policy of this service is available at [site](https://www.cdek.ru/ru/privacy_policy/)

== Installation ==

1. Install the plugin via the "Plugins" menu in WordPress or upload the archive in the admin panel.
2. Activate the plugin.
3. Go to "WooCommerce" -> "Settings" -> "Delivery" and select "CDEKDelivery".
4. Enter the data to connect to the CDEK API and configure the delivery parameters.
5. Fill in other plugin settings and save the changes.

More detailed instructions are available at [site](https://cdek-it.github.io/wordpress/)

== Frequently Asked Questions ==

= Where can I ask a question about using the plugin? =

All questions and comments on the use of the plugin can be asked at integrator@cdek.ru

== Changelog ==

= 4.0 =
* WP-40 Replaced the map in the plugin settings with address input fields
* WP-57 Changed used API paths
* WP-55 Changed the logic of automatic order creation (subscription to the payment hook)
* WP-73 Added refresh of security tokens upon request from the API
* WP-74 Added functionality for validating incoming requests from the API
* WP-81 Fixed 500 error when registering an order
* WP-85 Updated the version of the used map to prevent blocking the key from Yandex
* WP-90 Fixed 500 error when sending an order via API
* WP-96 Fixed translations in the plugin
* WP-100 Fixed creation of duplicate tasks from the API
* WP-114 Fixed autocomplete cities in settings
* WP-115 Fixed work with yookassa acquiring
* WP-117 Fixed checkout, then post index empty
* WP-118 Fixed bug with settings save
* WP-124 Auto create order fix

= 3.22 =
* WP-30 Changed the logic of automatic order creation: added a selector for waiting for payment from payment systems
* WP-50 Reworked the method for loading available payment gateways in the settings
* [#22](https://github.com/cdek-it/wordpress/issues/22) Added processing of an empty phone number
* WP-50 Reworked the method for loading available payment gateways in the settings
* WP-60 Added translations for shipping dates
* WP-67 Fixed a bug with a negative shipping amount due to the applied cost calculation rules
* WP-109 Fixed the migration system for the new storage

You can find full version of changelog at [GitHub](https://github.com/cdek-it/wordpress/releases)

== Upgrade Notice ==

= 4.0 =
Plugin has new storage system and migrations for it. Please check the settings after updating the plugin for correct operation.

= 3.7 =
The map has been replaced by our own development and no longer contains erroneous data from OSM

== Screenshots ==

1. CDEKDelivery Settings Page.
2. Checkout page with choice of CDEK delivery method.
