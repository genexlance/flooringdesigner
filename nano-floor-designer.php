<?php
/**
 * Plugin Name: Nano-Floor Designer
 * Description: AI-powered flooring visualization directly inside WordPress using the Google Gemini nano-banana model.
 * Version: 0.1.0
 * Author: Genex Marketing Agency Ltd.
 * License: GPL-2.0-or-later
 * Text Domain: nano-floor-designer
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NFD_VERSION', '0.1.0');
if (!defined('NFD_PLUGIN_FILE')) {
    define('NFD_PLUGIN_FILE', __FILE__);
}
if (!defined('NFD_PLUGIN_DIR')) {
    define('NFD_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('NFD_PLUGIN_URL')) {
    define('NFD_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once NFD_PLUGIN_DIR . 'includes/class-nfd-autoloader.php';

NFD_Autoloader::init();

register_activation_hook(NFD_PLUGIN_FILE, 'nfd_activate_plugin');
register_deactivation_hook(NFD_PLUGIN_FILE, 'nfd_deactivate_plugin');

function nfd_activate_plugin(bool $network_wide): void
{
    if ($network_wide && is_multisite()) {
        foreach (get_sites(['fields' => 'ids']) as $blog_id) {
            switch_to_blog($blog_id);
            NFD_Plugin::activate();
            restore_current_blog();
        }
    } else {
        NFD_Plugin::activate();
    }
}

function nfd_deactivate_plugin(bool $network_wide): void
{
    if ($network_wide && is_multisite()) {
        foreach (get_sites(['fields' => 'ids']) as $blog_id) {
            switch_to_blog($blog_id);
            NFD_Plugin::deactivate();
            restore_current_blog();
        }
    } else {
        NFD_Plugin::deactivate();
    }
}

function nfd_run_plugin(): void
{
    $plugin = new NFD_Plugin();
    $plugin->init();
}
add_action('plugins_loaded', 'nfd_run_plugin');
