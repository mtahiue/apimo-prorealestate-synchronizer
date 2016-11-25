# Apimo API & WP Pro Real Estate 7 synchronizer
The plugin is used to synchronize Apimo estates entries with WP Pro Real Estate plugin through Apimo JSON API.

## Requirements
* PHP >= 5.6
* WordPress ~3.9|~4.6
* WP Pro Real Estate 7 http://contempothemes.com/wp-real-estate-7/

## Optional Requirements
* WMPL https://wpml.org/: this plugin allows to translate listing posts. It is natively supported so translated listing retrieved through Apimo Api are automatically translated.

## Setup
After the plugin installation and activation, just go to Settings > Apimo API to configure the API settings.

## Synchronization
The plugin uses the WordPress internal scheduler system. A synchronization with Apimo API is automatically made every hour.

## Pull Requests
I'm open to pull requests for additional features and/or improvements. The plugin currently only supports WMPL for automatic translations but can be easily improved to support other translation plugins.
