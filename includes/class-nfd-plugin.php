<?php
/**
 * Core plugin bootstrapper.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NFD_Plugin
{
    private $settings;
    private $presets;
    private $materials;
    private $rest;
    private $frontend;

    public function init(): void
    {
        $this->settings = new NFD_Settings();
        $this->presets = new NFD_Presets();
        $this->materials = new NFD_Materials();
        $this->frontend = new NFD_Frontend($this->settings, $this->presets, $this->materials);
        $this->rest = new NFD_REST_Controller($this->settings, $this->presets);

        if (is_admin()) {
            add_action('admin_menu', [$this, 'register_admin_menu']);
            add_action('admin_menu', [$this->settings, 'register_menu']);
            add_action('add_meta_boxes', [$this->presets, 'register_meta_boxes']);
            add_action('save_post', [$this->presets, 'save_post'], 10, 2);
            add_filter('manage_nfd_flooring_posts_columns', [$this->presets, 'admin_columns']);
            add_action('manage_nfd_flooring_posts_custom_column', [$this->presets, 'render_admin_columns'], 10, 2);
            add_action('add_meta_boxes', [$this->materials, 'register_meta_boxes']);
            add_action('save_post', [$this->materials, 'save_post'], 10, 2);
            add_filter('plugin_action_links_' . plugin_basename(NFD_PLUGIN_FILE), [$this, 'settings_link']);
        }

        add_action('init', [$this->presets, 'register']);
        add_action('init', [$this->materials, 'register']);
        add_action('rest_api_init', [$this->rest, 'register_routes']);

        $this->settings->init();
        $this->frontend->init();
    }

    public function register_admin_menu(): void
    {
        add_menu_page(
            esc_html__('Nano-Floor Designer', 'nano-floor-designer'),
            esc_html__('Nano-Floor', 'nano-floor-designer'),
            'manage_options',
            'nfd-floor-designer',
            [$this, 'render_dashboard'],
            'dashicons-art',
            25
        );

        add_submenu_page(
            'nfd-floor-designer',
            esc_html__('Dashboard', 'nano-floor-designer'),
            esc_html__('Dashboard', 'nano-floor-designer'),
            'manage_options',
            'nfd-floor-designer',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'nfd-floor-designer',
            esc_html__('Flooring Presets', 'nano-floor-designer'),
            esc_html__('Flooring Presets', 'nano-floor-designer'),
            'edit_posts',
            'edit.php?post_type=nfd_flooring'
        );

        add_submenu_page(
            'nfd-floor-designer',
            esc_html__('Flooring Materials', 'nano-floor-designer'),
            esc_html__('Flooring Materials', 'nano-floor-designer'),
            'edit_posts',
            'edit.php?post_type=nfd_material'
        );
    }

    public function render_dashboard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'nano-floor-designer'));
        }

        $settings_url = admin_url('admin.php?page=nfd-settings');
        $presets_url = admin_url('edit.php?post_type=nfd_flooring');
        $materials_url = admin_url('edit.php?post_type=nfd_material');

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Nano-Floor Designer Dashboard', 'nano-floor-designer') . '</h1>';
        echo '<p>' . esc_html__('Quick links to configure the experience and manage catalog data.', 'nano-floor-designer') . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url($presets_url) . '">' . esc_html__('Manage Flooring Presets', 'nano-floor-designer') . '</a> ';
        echo '<a class="button" href="' . esc_url($materials_url) . '">' . esc_html__('Manage Flooring Materials', 'nano-floor-designer') . '</a> ';
        echo '<a class="button" href="' . esc_url($settings_url) . '">' . esc_html__('Open Settings', 'nano-floor-designer') . '</a></p>';
        echo '<h2>' . esc_html__('Getting Started', 'nano-floor-designer') . '</h2>';
        echo '<ol>';
        echo '<li>' . esc_html__('Add preset flooring options under Flooring Presets.', 'nano-floor-designer') . '</li>';
        echo '<li>' . esc_html__('Define material types and dimensions under Flooring Materials.', 'nano-floor-designer') . '</li>';
        echo '<li>' . esc_html__('Configure your Google Gemini API credentials under Settings.', 'nano-floor-designer') . '</li>';
        echo '<li>' . esc_html__('Insert the Nano-Floor Designer block or shortcode into a page.', 'nano-floor-designer') . '</li>';
        echo '</ol>';
        echo '</div>';
    }

    public function settings_link(array $links): array
    {
        $url = admin_url('admin.php?page=nfd-settings');
        $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'nano-floor-designer') . '</a>';
        return $links;
    }

    public static function activate(): void
    {
        (new NFD_Presets())->register();
        (new NFD_Materials())->register();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
