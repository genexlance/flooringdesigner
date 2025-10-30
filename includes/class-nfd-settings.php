<?php
/**
 * Admin settings management.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NFD_Settings
{
    private $option_name = 'nfd_settings';

    public function init(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_menu(): void
    {
        add_submenu_page(
            'nfd-floor-designer',
            esc_html__('Settings', 'nano-floor-designer'),
            esc_html__('Settings', 'nano-floor-designer'),
            'manage_options',
            'nfd-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('nfd_settings_group', $this->option_name, [$this, 'sanitize']);

        add_settings_section(
            'nfd_api_section',
            esc_html__('Google Gemini Integration', 'nano-floor-designer'),
            function () {
                echo '<p>' . esc_html__('Configure the Google Gemini image generation credentials used for floor rendering.', 'nano-floor-designer') . '</p>';
            },
            'nfd-settings'
        );

        $fields = [
            'gemini_api_key' => ['label' => esc_html__('API Key', 'nano-floor-designer'), 'type' => 'password'],
            'gemini_model' => ['label' => esc_html__('Primary Model', 'nano-floor-designer'), 'type' => 'text', 'placeholder' => 'gemini-1.5-pro-latest'],
            'gemini_image_model' => ['label' => esc_html__('Images API Model', 'nano-floor-designer'), 'type' => 'text', 'placeholder' => 'gemini-2.5-flash-image'],
            'use_images_api' => ['label' => esc_html__('Prefer Images API (faster previews)', 'nano-floor-designer'), 'type' => 'checkbox'],
            'rate_limit_rpm' => ['label' => esc_html__('Rate Limit (requests per minute)', 'nano-floor-designer'), 'type' => 'number', 'min' => 1, 'max' => 60],
        ];
        foreach ($fields as $key => $config) {
            add_settings_field(
                $key,
                $config['label'],
                [$this, 'render_field'],
                'nfd-settings',
                'nfd_api_section',
                array_merge($config, ['key' => $key])
            );
        }

        add_settings_section(
            'nfd_storage_section',
            esc_html__('Storage & Logging', 'nano-floor-designer'),
            function () {
                echo '<p>' . esc_html__('Optional storage credentials for processed images and debug logging.', 'nano-floor-designer') . '</p>';
            },
            'nfd-settings'
        );

        $storage_fields = [
            'enable_debug_logging' => ['label' => esc_html__('Enable Debug Logging', 'nano-floor-designer'), 'type' => 'checkbox'],
        ];
        foreach ($storage_fields as $key => $config) {
            add_settings_field(
                $key,
                $config['label'],
                [$this, 'render_field'],
                'nfd-settings',
                'nfd_storage_section',
                array_merge($config, ['key' => $key])
            );
        }
    }

    public function sanitize(array $input): array
    {
        $defaults = $this->defaults();
        $output = $this->get_all();
        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $input)) {
                if (is_bool($default)) {
                    $output[$key] = false;
                }
                continue;
            }
            $value = $input[$key];
            switch ($key) {
                case 'gemini_api_key':
                case 'gemini_model':
                case 'gemini_image_model':
                    $output[$key] = sanitize_text_field($value);
                    break;
                case 'rate_limit_rpm':
                    $output[$key] = max(1, min(60, (int) $value));
                    break;
                case 'use_images_api':
                case 'enable_debug_logging':
                    $output[$key] = (bool) $value;
                    break;
                default:
                    $output[$key] = $default;
            }
        }
        return $output;
    }

    public function render_field(array $args): void
    {
        $settings = $this->get_all();
        $key = $args['key'];
        $value = $settings[$key] ?? '';
        $type = $args['type'] ?? 'text';
        $name = $this->option_name . '[' . $key . ']';
        $attributes = '';
        if (isset($args['placeholder'])) {
            $attributes .= ' placeholder="' . esc_attr($args['placeholder']) . '"';
        }
        if (isset($args['min'])) {
            $attributes .= ' min="' . intval($args['min']) . '"';
        }
        if (isset($args['max'])) {
            $attributes .= ' max="' . intval($args['max']) . '"';
        }

        if ($type === 'checkbox') {
            printf('<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s/>', esc_attr($key), esc_attr($name), checked(!empty($value), true, false));
        } elseif ($type === 'password') {
            printf('<input type="password" id="%1$s" name="%2$s" value="%3$s" class="regular-text" autocomplete="new-password"%4$s/>', esc_attr($key), esc_attr($name), esc_attr($value), $attributes);
        } else {
            printf('<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text"%5$s/>', esc_attr($type), esc_attr($key), esc_attr($name), esc_attr($value), $attributes);
        }

        if ($key === 'enable_debug_logging') {
            echo '<p class="description">' . esc_html__('Logs API requests/responses to the debug log. Disable in production.', 'nano-floor-designer') . '</p>';
        }
        if ($key === 'gemini_api_key') {
            echo '<p class="description">' . esc_html__('Stored securely in the database. Required for processing images.', 'nano-floor-designer') . '</p>';
        }
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'nano-floor-designer'));
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Nano-Floor Designer', 'nano-floor-designer') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('nfd_settings_group');
        do_settings_sections('nfd-settings');
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function get(string $key, $default = null)
    {
        $settings = $this->get_all();
        return $settings[$key] ?? $default;
    }

    public function get_all(): array
    {
        $stored = get_option($this->option_name, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return array_merge($this->defaults(), $stored);
    }

    public function defaults(): array
    {
        return [
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-1.5-pro-latest',
            'gemini_image_model' => 'gemini-2.5-flash-image',
            'use_images_api' => true,
            'rate_limit_rpm' => 10,
            'enable_debug_logging' => false,
        ];
    }
}
