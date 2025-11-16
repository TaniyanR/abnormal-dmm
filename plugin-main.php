<?php
/*
Plugin Name: VideoStore Helper (example bootstrap)
Description: Example bootstrap to initialize AdminImageDelete and MutualRss classes. Remove if not needed.
Version: 0.1
Author: Copilot
*/

if (!defined('ABSPATH')) {
    // Not running in WP environment: file acts as a no-op to avoid breaking non-WP projects.
    return;
}

if (!defined('VIDEOSTORE_PLUGIN_DIR')) {
    define('VIDEOSTORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('VIDEOSTORE_PLUGIN_URL')) {
    define('VIDEOSTORE_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once VIDEOSTORE_PLUGIN_DIR . 'src/AdminImageDelete.php';
require_once VIDEOSTORE_PLUGIN_DIR . 'src/ImageDeleteLogger.php';
require_once VIDEOSTORE_PLUGIN_DIR . 'src/MutualRss.php';

add_action('plugins_loaded', function() {
    if (class_exists('AdminImageDelete')) {
        AdminImageDelete::init();
    }
    if (class_exists('MutualRss')) {
        MutualRss::init();
    }
});

register_activation_hook(__FILE__, function() {
    if (class_exists('ImageDeleteLogger')) {
        ImageDeleteLogger::install_table();
    }
});
