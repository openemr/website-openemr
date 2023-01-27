# OpenEMR Website

The OpenEMR Website is build using [Hugo](https://gohugo.io), a static-site
generator. This repo manages the main marketing OpenEMR, the wiki is managed
elsewhere. The recommended approach is to use `manage.sh` to leverage the docker
version of hugo which will ensure builds are stable across dev and production
environments.

# Installation

1. Clone this repo
2. `cd` into the themes/openemr directory
3. Run `npm install`
4. `cd` into base directory of the repo

# Building the Site
From the root directory:
1. Run `manage.sh`
2. Site is built under `public/` on the host machine
3. Docker container is automatically removed once build completes

# Development
From the root directory>
1. Run `manage.sh serve`
2. Navigate to [localhost:1313](http://localhost:1313)
3. Docker container is started in interactive mode with a TTY window and can be 
removed by Ctrl+C

*Note*
You can pass any number of arguments through `manage.sh` to the docker command
that triggers `hugo`, allowing you to specify `manage.sh server -d -f` for draft
and future posts to be built. If no arguments are passed, hugo attempts to build.

# Submitting changes

Fork, change, submit PR.

# Theme changes

The OpenEMR theme is built using webpack - `cd` into `themes/openemr` and run
`webpack` (or `webpack --watch` for auto-generation).

# Images

If you add images, we recommend they go in `static/images`.
