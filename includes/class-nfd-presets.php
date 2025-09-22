<?php
/**
 * Flooring preset post type & metadata management.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NFD_Presets
{
    private $meta_fields = [
        'dimension' => '_nfd_dimension',
        'style' => '_nfd_style',
    ];

    public function register(): void
    {
        $labels = [
            'name' => esc_html__('Flooring Presets', 'nano-floor-designer'),
            'singular_name' => esc_html__('Flooring Preset', 'nano-floor-designer'),
            'add_new' => esc_html__('Add New Preset', 'nano-floor-designer'),
            'add_new_item' => esc_html__('Add New Flooring Preset', 'nano-floor-designer'),
            'edit_item' => esc_html__('Edit Flooring Preset', 'nano-floor-designer'),
            'new_item' => esc_html__('New Flooring Preset', 'nano-floor-designer'),
            'view_item' => esc_html__('View Flooring Preset', 'nano-floor-designer'),
            'search_items' => esc_html__('Search Flooring Presets', 'nano-floor-designer'),
            'not_found' => esc_html__('No flooring presets found.', 'nano-floor-designer'),
            'menu_name' => esc_html__('Flooring Presets', 'nano-floor-designer'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'thumbnail'],
            'capability_type' => 'post',
        ];

        register_post_type('nfd_flooring', $args);
    }

    public function register_meta_boxes(): void
    {
        add_meta_box(
            'nfd_flooring_details',
            esc_html__('Flooring Details', 'nano-floor-designer'),
            [$this, 'render_meta_box'],
            'nfd_flooring',
            'normal',
            'high'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field('nfd_save_preset', 'nfd_preset_nonce');
        $values = $this->get_meta_values($post->ID);

        echo '<p><label for="nfd_dimension">' . esc_html__('Dimension', 'nano-floor-designer') . '</label> ';
        echo '<input type="text" id="nfd_dimension" name="nfd_dimension" class="widefat" value="' . esc_attr($values['dimension']) . '" placeholder="' . esc_attr__('e.g., 7" x 48"', 'nano-floor-designer') . '"/></p>';

        echo '<p><label for="nfd_style">' . esc_html__('Style', 'nano-floor-designer') . '</label> ';
        echo '<input type="text" id="nfd_style" name="nfd_style" class="widefat" value="' . esc_attr($values['style']) . '" placeholder="' . esc_attr__('e.g., Rustic Oak, Modern Concrete', 'nano-floor-designer') . '"/></p>';

        echo '<p class="description">' . esc_html__('Use the editor for a detailed description. Add a featured image for the swatch.', 'nano-floor-designer') . '</p>';
    }

    public function save_post(int $post_id, WP_Post $post): void
    {
        if ($post->post_type !== 'nfd_flooring') {
            return;
        }
        if (!isset($_POST['nfd_preset_nonce']) || !wp_verify_nonce($_POST['nfd_preset_nonce'], 'nfd_save_preset')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $updates = [
            'dimension' => isset($_POST['nfd_dimension']) ? sanitize_text_field($_POST['nfd_dimension']) : '',
            'style' => isset($_POST['nfd_style']) ? sanitize_text_field($_POST['nfd_style']) : '',
        ];

        foreach ($updates as $key => $value) {
            $meta_key = $this->meta_fields[$key];
            if ($value === '') {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }
    }

    private function get_meta_values(int $post_id): array
    {
        $values = [];
        foreach ($this->meta_fields as $key => $meta_key) {
            $values[$key] = get_post_meta($post_id, $meta_key, true) ?: '';
        }
        return $values;
    }

    public function admin_columns(array $columns): array
    {
        $columns['nfd_style'] = esc_html__('Style', 'nano-floor-designer');
        return $columns;
    }

    public function render_admin_columns(string $column, int $post_id): void
    {
        switch ($column) {
            case 'nfd_style':
                echo esc_html(get_post_meta($post_id, $this->meta_fields['style'], true));
                break;
        }
    }

    public function get_presets(): array
    {
        $query = new WP_Query([
            'post_type' => 'nfd_flooring',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $items = [];
        foreach ($query->posts as $post) {
            $items[] = $this->format_preset($post);
        }
        wp_reset_postdata();
        return $items;
    }

    public function format_preset(WP_Post $post): array
    {
        $meta = $this->get_meta_values($post->ID);
        $thumbnail = get_the_post_thumbnail_url($post, 'medium');
        return [
            'id' => (string) $post->ID,
            'title' => get_the_title($post),
            'description' => wp_strip_all_tags($post->post_content),
            'dimension' => $meta['dimension'],
            'style' => $meta['style'],
            'thumbnail' => $thumbnail ?: '',
        ];
    }

    public function get_preset($id): ?array
    {
        $post = get_post((int) $id);
        if (!$post || $post->post_type !== 'nfd_flooring') {
            return null;
        }
        return $this->format_preset($post);
    }
}
