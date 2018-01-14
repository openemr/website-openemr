'use strict';

const webpack = require("webpack");
const ExtractTextPlugin = require("extract-text-webpack-plugin");
const path = require("path");

const extractSass = new ExtractTextPlugin({
  filename: "style.css",
  disable: process.env.NODE_ENV === "development"
});

module.exports = {
  entry: './src/app.js',
  output: {
    path: path.resolve(__dirname + '/static/'),
    filename: 'bundle.js'
  },
  module: {
    rules: [
      {
        test: /\.scss$/,
        use: ExtractTextPlugin.extract({
          use: ["css-loader", "sass-loader"]
        })
      }
    ],
  },
  plugins: [
    extractSass,
    new webpack.ProvidePlugin({
      $: "jquery",
      jQuery: "jquery"
    })
  ]
};
