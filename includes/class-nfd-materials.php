<?php
/**
 * Flooring material definitions (type + dimensions + styles).
 */

if (!defined('ABSPATH')) {
    exit;
}

class NFD_Materials
{
    private $meta_fields = [
        'dimensions' => '_nfd_dimensions',
        'styles' => '_nfd_styles',
    ];

    public function register(): void
    {
        $labels = [
            'name' => esc_html__('Flooring Materials', 'nano-floor-designer'),
            'singular_name' => esc_html__('Flooring Material', 'nano-floor-designer'),
            'add_new_item' => esc_html__('Add New Flooring Material', 'nano-floor-designer'),
            'edit_item' => esc_html__('Edit Flooring Material', 'nano-floor-designer'),
            'new_item' => esc_html__('New Flooring Material', 'nano-floor-designer'),
            'view_item' => esc_html__('View Flooring Material', 'nano-floor-designer'),
            'search_items' => esc_html__('Search Flooring Materials', 'nano-floor-designer'),
        ];

        register_post_type('nfd_material', [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
        ]);
    }

    public function register_meta_boxes(): void
    {
        add_meta_box(
            'nfd_material_details',
            esc_html__('Material Details', 'nano-floor-designer'),
            [$this, 'render_meta_box'],
            'nfd_material',
            'normal',
            'default'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field('nfd_save_material', 'nfd_material_nonce');
        $values = $this->get_meta_values($post->ID);
        echo '<p><label for="nfd_dimensions">' . esc_html__('Dimensions (one per line)', 'nano-floor-designer') . '</label>';
        echo '<textarea id="nfd_dimensions" name="nfd_dimensions" class="widefat" rows="6" placeholder="6 in x 48 in\n8 in x 60 in">' . esc_textarea($values['dimensions']) . '</textarea></p>';

        echo '<p><label for="nfd_styles">' . esc_html__('Styles (optional, one per line)', 'nano-floor-designer') . '</label>';
        echo '<textarea id="nfd_styles" name="nfd_styles" class="widefat" rows="4" placeholder="Straight plank\nHerringbone">' . esc_textarea($values['styles']) . '</textarea></p>';

        echo '<p class="description">' . esc_html__('Dimensions feed the custom selector. Styles appear only when provided (e.g., Parquet for Hardwood).', 'nano-floor-designer') . '</p>';
    }

    public function save_post(int $post_id, WP_Post $post): void
    {
        if ($post->post_type !== 'nfd_material') {
            return;
        }
        if (!isset($_POST['nfd_material_nonce']) || !wp_verify_nonce($_POST['nfd_material_nonce'], 'nfd_save_material')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $dimensions = isset($_POST['nfd_dimensions']) ? wp_kses_post($_POST['nfd_dimensions']) : '';
        $styles = isset($_POST['nfd_styles']) ? wp_kses_post($_POST['nfd_styles']) : '';
        $this->update_meta($post_id, 'dimensions', $dimensions);
        $this->update_meta($post_id, 'styles', $styles);
    }

    private function update_meta(int $post_id, string $key, string $value): void
    {
        $meta_key = $this->meta_fields[$key];
        $clean = trim((string) $value);
        if ($clean === '') {
            delete_post_meta($post_id, $meta_key);
            return;
        }
        update_post_meta($post_id, $meta_key, $clean);
    }

    private function get_meta_values(int $post_id): array
    {
        $values = [];
        foreach ($this->meta_fields as $key => $meta_key) {
            $values[$key] = get_post_meta($post_id, $meta_key, true) ?: '';
        }
        return $values;
    }

    public function get_materials(): array
    {
        $query = new WP_Query([
            'post_type' => 'nfd_material',
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'posts_per_page' => -1,
        ]);
        $items = [];
        foreach ($query->posts as $post) {
            $items[] = $this->format_material($post);
        }
        wp_reset_postdata();
        return $items;
    }

    public function format_material(WP_Post $post): array
    {
        $meta = $this->get_meta_values($post->ID);
        return [
            'id' => (string) $post->ID,
            'name' => get_the_title($post),
            'dimensions' => $this->split_lines($meta['dimensions']),
            'styles' => $this->split_lines($meta['styles']),
        ];
    }

    private function split_lines(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $lines = preg_split('/\r?\n/', $value);
        $clean = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $clean[] = sanitize_text_field($trimmed);
            }
        }
        return $clean;
    }
}
