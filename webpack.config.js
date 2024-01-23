const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require(
  '@woocommerce/dependency-extraction-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const { resolve } = require('path');

// Remove SASS rule from the default config so we can define our own.
const defaultRules = defaultConfig.module.rules.filter((rule) => {
    return String(rule.test) !== String(/\.(sc|sa)ss$/);
});

module.exports = {
    ...defaultConfig, entry: {
        'cdek-checkout-map-block': resolve(process.cwd(), 'src',
          'Frontend', 'CheckoutMapBlock', 'index.js'),
        'cdek-checkout-map-block-frontend': resolve(process.cwd(), 'src',
          'Frontend', 'CheckoutMapBlock', 'frontend.js'),
        'cdek-admin-settings': resolve(process.cwd(), 'src', 'Frontend',
          'AdminSettings', 'index.js'),
        'cdek-checkout-map': resolve(process.cwd(), 'src', 'Frontend',
          'CheckoutMapShortcode', 'index.js'),
        'cdek-create-order': resolve(process.cwd(), 'src', 'Frontend',
          'AdminOrder', 'index.js'),
    }, module: {
        ...defaultConfig.module, rules: [
            ...defaultRules, {
                test: /\.(sc|sa)ss$/, exclude: /node_modules/, use: [
                    MiniCssExtractPlugin.loader,
                    { loader: 'css-loader', options: { importLoaders: 1 } },
                    {
                        loader: 'sass-loader', options: {
                            sassOptions: {
                                includePaths: ['src/Frontend/**/style'],
                            },
                        },
                    }],
            }],
    }, plugins: [
        ...defaultConfig.plugins.filter((plugin) => plugin.constructor.name !==
          'DependencyExtractionWebpackPlugin' && plugin.constructor.name !==
          'MiniCSSExtractPlugin'),
        new MiniCssExtractPlugin({
            filename: `[name].css`,
        }),
    ],
};
