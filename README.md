# OpenEMR Website

This static-site is generated via [hugo](http://gohugo.io) and uses a custom
theme to manage the main OpenEMR website.

# Installation

0. [Install hugo](http://gohugo.io/getting-started/installing/)
1. Clone this repo
2. `cd` into the repo
3. Run `hugo server` (or `hugo server -D` to include draft pages)
4. Navigate to [localhost:1313](http://localhost:1313) in your browser

# Submitting changes

Fork, change, submit PR.

# Theme changes

The OpenEMR theme is built using webpack - `cd` into `themes/openemr` and run
`webpack` (or `webpack --watch` for auto-generation).

# Images

If you add images, we recommend they go in `static/images`.
