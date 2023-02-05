# OpenEMR Website

This static-site is generated via [hugo](http://gohugo.io) version 0.52 and uses a custom
theme to manage the main OpenEMR website.

# Installation

0. [Install hugo(extended version)](http://gohugo.io/getting-started/installing/) version 0.52 and install npm
1. Clone this repo
2. `cd` into the themes/openemr directory
3. Run `npm install`
4. `cd` into base directory of the repo
5. Run `hugo server -F` (need the -F parameter to include future entries, for example, for future lectures) (or `hugo server -F -D` to include draft pages)
6. Navigate to [localhost:1313](http://localhost:1313) in your browser

# Submitting changes

Fork, change, submit PR.

# Theme changes

## TLDR

Use this gist to make a temp directory, clone the required repos, and spin up
a hugo server for rapid development of the theme.

```
GIST HERE (pending)
```

## Details
The OpenEMR Theme is a Hugo module found at (github.com/openemr/website-theme).
To work on the theme, use the following steps:

```
cd <your/path>
git clone github.com/openemr/website-openemr site
git clone github.com/openemr/website-theme theme
cd site
HUGO_THEMESDIR="$(pwd)/../"
HUGO_MODULE_REPLACEMENTS="github.com/openemr/website-theme -> ../theme"
hugo serve -D
```

Some key steps here include setting the themes directory and replacing the github 
module with your local copy. This will allow you to make changes in the theme 
repo and have it reflect when `hugo serve` is running.

Once you have made changes to the theme, you can submit a PR to the theme repo.

@TODO - Create an `exampleSite` in the website-theme repo to allow development
without needing the actual content. This does have the downside of not using
production data, but would allow more compartmentalized development. This only
becomes useful as the OpenEMR Theme is refactored to a more content-agnostic
theme.

# Images

If you add images, we recommend they go in `static/images`.
