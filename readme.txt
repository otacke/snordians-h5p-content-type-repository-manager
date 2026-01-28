=== SNORDIAN's H5P Content Type Repository Manager ===
Contributors: otacke
Tags: h5p, catharsis
Requires at least: 6.2
Tested up to: 6.9
Stable tag: 1.0.11
License: MIT
License URI: https://github.com/otacke/snordians-h5p-content-type-repository-manager/blob/master/LICENSE

Allows to use alternative H5P Content Type Hub Servers.

== Description ==
The "SNORDIAN's H5P Content Type Repository Manager" plugin for WordPress allows to set an alternative H5P Content Type Hub Server to get H5P contents from. It also offers additional functionality related to the server that is used.

== Install ==

=== Upload ZIP file ===
1. Go to https://github.com/otacke/snordians-h5p-content-type-repository-manager/releases.
2. Pick the latest release (or the one that you want to use) and download the `snordians-h5p-content-type-repository-manager.zip` file.
3. Log in to your WordPress site as an admin and go to _Plugins > Add New Plugin_.
4. Click on the _Upload Plugin_ button.
5. Upload the ZIP file with the plugin code.
6. Activate the plugin.

== Configure ==
The settings of this plugin are available by going to `Settings -> H5P Content Type Repository Manager`.

=== URL ===
By default, H5P's core library will use the base URL `hub-api.h5p.org/v1` which points towards H5P Group's official H5P Content Type Hub. If you want to get content types from a different H5P Content Type Hub, you can change the `URL` option to the base URL of the alternative hub.
Note: You may want to set up your alternative hub yourself. There's the node.js based server software called [Catharsis](https://github.com/otacke/catharsis) for this purpose.

=== Schedule automated updates ===
By default, H5P requires an admin to update content types to newer versions manually by either using the Hub client or by uploading content type files with newer libraries. If you want to automate this process, you can change the `Schedule automated updates` option:

- Never: No automated content type update
- Daily: Update the content types once a day
- Weekly: Update the content types once a week

Please note that only content types that were already installed will be updated. Content types that have not been installed yet will not be installed.
Please note that only the libraries will be updated. An admin will still need to upgrade existing contents to use the newer libraries.

== Sponsor note ==
The plugin was developed by [Sustainum](https://www.sustainum.de/) within the [XR Energy project](https://xr-energy.eu/). Development work was carried out by [SNODRIAN](https://snordian.de) as a contractor.

"Funded by the European Union. Views and opinions expressed are however those of the author(s) only and do not necessarily reflect those of the European Union or the European Education and Culture Executive Agency (EACEA). Neither the European Union nor EACEA can be held responsible for them.”
