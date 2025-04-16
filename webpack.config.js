const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require(
  '@woocommerce/dependency-extraction-webpack-plugin');
const { resolve } = require('path');
const CopyPlugin = require('copy-webpack-plugin');

module.exports = {
    ...defaultConfig, entry: {
        'cdek-checkout-map-block': resolve(process.cwd(), 'src', 'Frontend',
          'CheckoutMapBlock', 'index.js'),
        'cdek-checkout-map-block-frontend': resolve(process.cwd(), 'src',
          'Frontend', 'CheckoutMapBlock', 'frontend.js'),
        'cdek-admin-settings': resolve(process.cwd(), 'src', 'Frontend',
          'AdminSettings', 'index.js'),
        'cdek-checkout-map': resolve(process.cwd(), 'src', 'Frontend',
          'CheckoutMapShortcode', 'index.js'),
        'cdek-create-order': resolve(process.cwd(), 'src', 'Frontend',
          'AdminOrder', 'index.js'),
        'cdek-order-item': resolve(process.cwd(), 'src', 'Frontend',
             'AdminOrderItem', 'index.js'),
    }, plugins: [
        ...defaultConfig.plugins.filter((plugin) => plugin.constructor.name !==
          'DependencyExtractionWebpackPlugin'),
        new WooCommerceDependencyExtractionWebpackPlugin({
            requestToExternal: r => (r === '@cdek-it/widget')
              ? 'CDEKWidget'
              : undefined,
            requestToHandle: r => (r === '@cdek-it/widget')
              ? 'cdek-widget'
              : undefined,
        }),
        new CopyPlugin({
            patterns: [
                {
                    from: resolve(__dirname, 'node_modules', '@cdek-it',
                      'widget/dist/cdek-widget.umd.js'),
                    to: resolve(__dirname, 'build', 'cdek-widget.umd.js'),
                }],
        })],
};
