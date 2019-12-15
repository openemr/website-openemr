# OpenEMR Website

This static-site is generated via [hugo](http://gohugo.io) version 0.52 and uses a custom
theme to manage the main OpenEMR website.

# Installation

0. [Install hugo(extended version)](http://gohugo.io/getting-started/installing/) version 0.52 and install npm
1. Clone this repo
2. `cd` into the themes/openemr directory
3. Run `npm install`
4. `cd` into base directory of the repo
5. Run `hugo server` (or `hugo server -D` to include draft pages)
6. Navigate to [localhost:1313](http://localhost:1313) in your browser

# Submitting changes

Fork, change, submit PR.

# Theme changes

The OpenEMR theme is built using webpack - `cd` into `themes/openemr` and run
`webpack` (or `webpack --watch` for auto-generation).

# Images

If you add images, we recommend they go in `static/images`.
