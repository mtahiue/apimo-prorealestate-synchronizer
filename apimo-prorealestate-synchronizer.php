<?php
/**
 * Plugin Name: Apimo API & WP Pro Real Estate 7 synchronizer
 * Version: 1.0
 * Author: Florent DAQUET <flodaq@linkthis.fr>
 * Author URI: http://linkthis.fr
 * Description: The plugin is used to synchronize Apimo estates entries with WP Pro Real Estate plugin through Apimo JSON API
 */

// Includes plugin components
include_once dirname(__FILE__) . '/apimo-prorealestate-synchronizer-options.php';
include_once dirname(__FILE__) . '/apimo-prorealestate-synchronizer-main.php';

// Register the cron job
register_activation_hook(__FILE__, array('ApimoProrealestateSynchronizer', 'install'));

// Unregister the cron job
register_deactivation_hook(__FILE__, array('ApimoProrealestateSynchronizer', 'uninstall'));

// Trigger the plugin
ApimoProrealestateSynchronizer::getInstance();

// Trigger the settings page
if (is_admin()) {
    $apimo_prorealestate_synchronizer_settings_page = new ApimoProrealestateSynchronizerSettingsPage();
}