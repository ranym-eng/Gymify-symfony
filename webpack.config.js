const Encore = require('@symfony/webpack-encore');
const path = require('path');

Encore
    .setOutputPath('public/build')
    .setPublicPath('/build')
    .addEntry('app', path.resolve(__dirname, 'assets/app.js'))
    .addEntry('chart-init', path.resolve(__dirname, 'assets/chart-init.js'))
    .addStyleEntry('statistics', path.resolve(__dirname, 'assets/styles/statistics.css'))
    .enableSingleRuntimeChunk()
    .enableSassLoader() // Si vous utilisez SCSS
    .enablePostCssLoader() // Si vous utilisez PostCSS
    .cleanupOutputBeforeBuild()
    .enableVersioning() // Si vous voulez activer le cache-busting
    .configureImageRule({
        filename: 'images/[name].[hash:8].[ext]'
    })
    .configureFontRule({
        filename: 'fonts/[name].[hash:8].[ext]'
    });

module.exports = Encore.getWebpackConfig();
