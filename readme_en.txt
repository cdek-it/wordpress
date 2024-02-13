=== CDEKDelivery ===
Contributors: cdekit
Tags: ecommerce, shipping, delivery, cdek, woocommerce
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 6.4
Stable tag: 3.15.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integration with CDEK delivery for your WooCommerce store.

== Description ==

CDEKDelivery provides integration with CDEK delivery for your WordPress WooCommerce store. This plugin allows you to configure delivery parameters according to your store's requirements and enables customers to choose CDEK delivery when placing orders.

The plugin features:

* Activate test mode for testing functionality without integrating real data.
* Handle international orders and provide corresponding delivery options.
* Automatically send orders to CDEK after placement on the website.
* Choose different tariffs for shipping considering client requirements and product characteristics.
* Customize tariff names to adapt them to your needs.
* Implement multi-package mode to distribute order items across different packages.
* Create a courier call request.
* View order status changes on the order detail page.
* Add additional days to estimated delivery days, considering possible delays.
* Select office and dispatch address conveniently via the plugin settings widget.
* Set default product dimensions for more accurate shipping cost calculation.
* Print order receipts and barcodes for shipment.
* Provide a choice of various additional services such as insurance, fitting, and flexible modification of delivery costs based on selected services and order parameters.

## Plugin Features
* Calculation of shipping cost and delivery time
* Selection of pick-up points on the map
* Easy installation, WooCommerce integration
* Store configuration: address, tariff selection, and shipping type
* Ability to provide accurate packaging data and automatic weight calculation

## Access to Third-party Services

The CDEKDelivery plugin uses the following third-party services to ensure its functionality:

1. **api.cdek.ru**: CDEK API is used for calculating the cost and delivery time of orders. The privacy policy of this service is available at: [CDEK Privacy Policy](https://www.cdek.ru/ru/privacy_policy/)

2. **api.edu.cdek.ru**: CDEK API is used for calculating the cost and delivery time of orders in test mode. The privacy policy of this service is available at: [CDEK Privacy Policy](https://www.cdek.ru/ru/privacy_policy/)

3. **ipecho.net**: The ipecho.net service is used to determine the user's IP address. The privacy policy of this service is available at: [ipecho.net Privacy Policy](https://ipecho.net/developers.html)

### CDEK Personal Account
To use the plugin, you need to obtain access to CDEK services. To do this, contact CDEK support and create an account in the personal account. After that, you will have access to integration keys to work with the CDEK API.

### Yandex.Map
The plugin uses CDEK company's widget, which utilizes Yandex maps. To use the widget, you need to obtain access keys to the Yandex.Map API. The key generation process is described on the page: https://yandex.ru/dev/jsapi-v2-1/doc/ru/#get-api-key. Be sure to set the HTTP Referrer parameter equal to your website's address for the key.

## Interaction with Third-party Services
### CDEK API
The plugin uses the CDEK API to calculate the cost and delivery time of orders, documentation available at the link https://api-docs.cdek.ru. To do this, the plugin sends requests to CDEK servers with order data and receives responses with calculations.

### CDEK Widget
To select a pick-up point for the order, the plugin uses CDEK company's widget https://widget.cdek.ru/

== Installation ==

1. Install the plugin via the "Plugins" menu in WordPress or upload the archive in the admin panel.
2. Activate the plugin.
3. Go to "WooCommerce" -> "Settings" -> "Delivery" and select "CDEKDelivery".
4. Enter the connection data for the CDEK API and configure the delivery parameters.
5. Fill in other plugin settings and save changes.

For more detailed instructions, visit https://www.cdek.ru/storage/source/Integration/WordPress/Instruction.pdf?_t=1697091578

== Frequently Asked Questions ==

= How to configure CDEKDelivery shipping methods? =

After activating the plugin, go to WooCommerce > Settings > Delivery > CDEKDelivery. Here, you can configure shipping tariffs, delivery parameters, and other settings related to CDEK delivery.

= Is a contract with CDEK required to use this plugin? =

Yes, to use CDEK delivery services, you need to sign a contract with CDEK.

= Is there a test mode? =

Yes, the CDEKDelivery plugin supports test mode. You can configure it in the plugin settings section to test its functionality without integration keys.

= Can I use the plugin to send international orders via CDEK? =

Yes, international delivery is available.

= What additional services are available? =

The following additional services are available:
- Insurance
- Free delivery above a certain order amount
- Fixed delivery cost
- Ability to set a percentage surcharge

== Screenshots ==

1. CDEKDelivery settings page.
2. Widget block on the settings page.
3. Checkout page with CDEK delivery method selection.

== Changelog ==

= 3.15.5 =
* Fixed delivery point transfer to the order.
* Fixed selection error of delivery points for media products.

= 3.15.4 =
* Fixed application of delivery rules.
* Removed archived tariff.

= 3.15.3 =
* Fixed transfer of dimensions when automatically creating a waybill.
* Fixed transfer of additional charges to the recipient for delivery.
* Fixed order creation error if only billing fields are filled.

= 3.15.2 =
* Removed unwanted warnings

= 3.15.1 =
* Added error handling when changing the delivery method to CDEK (warning output disabled)

= 3.15.0 =
* Added status display in widget on detailed order
* Added services "No Inspection", "Fitting", and "Partial Delivery"
* Fixed widget positioning

== Upgrade Notice ==

= 3.15.* =
Update that added services "No Inspection", "Fitting", "Partial Delivery". Also added status display in widget on detailed order.
We recommend updating the plugin to get new features and bug fixes.

= 3.14.* =
Added the ability to configure the delivery price depending on the mode. The billing_postcode and billing_last_name fields made optional for checkout.

= 3.13.* =
Added compatibility with the new version of the checkout widget.
