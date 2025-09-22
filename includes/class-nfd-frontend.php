<?php
/**
 * Frontend asset loading, shortcode, and block registration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NFD_Frontend
{
    private $settings;
    private $presets;
    private $materials;

    public function __construct(NFD_Settings $settings, NFD_Presets $presets, NFD_Materials $materials)
    {
        $this->settings = $settings;
        $this->presets = $presets;
        $this->materials = $materials;
    }

    public function init(): void
    {
        add_action('init', [$this, 'register_block']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_shortcode('nano_floor_designer', [$this, 'render_shortcode']);
    }

    private function register_asset_handles(): void
    {
        if (!wp_style_is('nfd-frontend', 'registered')) {
            wp_register_style('nfd-frontend', NFD_PLUGIN_URL . 'assets/css/frontend.css', [], NFD_VERSION);
        }
        if (!wp_script_is('nfd-frontend', 'registered')) {
            wp_register_script('nfd-frontend', NFD_PLUGIN_URL . 'assets/js/frontend.js', [], NFD_VERSION, true);
            wp_localize_script('nfd-frontend', 'nfdFrontendConfig', $this->frontend_config());
        }
    }

    public function enqueue_public_assets(): void
    {
        $this->register_asset_handles();
    }

    public function enqueue_editor_assets(): void
    {
        $this->register_asset_handles();
        wp_enqueue_style('nfd-frontend');
        wp_enqueue_script('nfd-frontend');
    }

    public function enqueue_assets(): void
    {
        $this->register_asset_handles();
        wp_enqueue_style('nfd-frontend');
        wp_enqueue_script('nfd-frontend');
    }

    public function render_shortcode($atts = [], $content = ''): string
    {
        $this->enqueue_assets();
        $instance_id = wp_unique_id('nfd-app-');
        return '<div id="' . esc_attr($instance_id) . '" class="nfd-app" data-nfd-instance="' . esc_attr($instance_id) . '"></div>';
    }

    public function register_block(): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }
        $this->register_asset_handles();
        register_block_type('nano-floor/designer', [
            'render_callback' => [$this, 'render_block'],
            'style' => 'nfd-frontend',
            'editor_script' => 'nfd-frontend',
            'editor_style' => 'nfd-frontend',
        ]);
    }

    public function render_block($attributes = [], $content = ''): string
    {
        return $this->render_shortcode();
    }

    private function frontend_config(): array
    {
        $settings = $this->settings->get_all();
        $presets = $this->presets->get_presets();
        $materials = $this->materials->get_materials();
        return [
            'restUrl' => esc_url_raw(rest_url('nano-floor/v1/')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'maxUploadBytes' => 10 * 1024 * 1024,
            'allowedMimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
            'minDimensions' => ['width' => 800, 'height' => 600],
            'maxDimensions' => 4000,
            'presets' => $presets,
            'materials' => $materials,
            'strings' => [
                'uploadPrompt' => __('Upload your room photo', 'nano-floor-designer'),
                'selectPrompt' => __('Choose your flooring', 'nano-floor-designer'),
                'generateAction' => __('Generate Visualization', 'nano-floor-designer'),
                'processing' => __('Generating preview...', 'nano-floor-designer'),
                'errorGeneric' => __('Something went wrong. Please try again.', 'nano-floor-designer'),
                'rateLimited' => __('Too many requests. Please wait a moment before trying again.', 'nano-floor-designer'),
            ],
            'features' => [
                'useImagesApi' => (bool) $settings['use_images_api'],
            ],
        ];
    }
}
