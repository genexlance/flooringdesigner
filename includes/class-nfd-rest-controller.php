<?php
/**
 * REST API endpoints for Nano-Floor Designer.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NFD_REST_Controller extends WP_REST_Controller
{
    protected $namespace = 'nano-floor/v1';
    private $settings;
    private $presets;
    private $gemini;
    private $rate_limiter;

    public function __construct(NFD_Settings $settings, NFD_Presets $presets)
    {
        $this->settings = $settings;
        $this->presets = $presets;
        $this->gemini = new NFD_Gemini_Client($settings);
        $this->rate_limiter = new NFD_Rate_Limiter();
    }

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/presets', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => [$this, 'verify_nonce'],
            'callback' => [$this, 'list_presets'],
        ]);

        register_rest_route($this->namespace, '/process', [
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => [$this, 'verify_nonce'],
            'callback' => [$this, 'process_request'],
            'args' => [
                'sample' => ['required' => true],
            ],
        ]);
    }

    public function verify_nonce(): bool
    {
        $nonce = null;
        if (!empty($_SERVER['HTTP_X_WP_NONCE'])) {
            $nonce = $_SERVER['HTTP_X_WP_NONCE'];
        } elseif (isset($_REQUEST['_wpnonce'])) {
            $nonce = $_REQUEST['_wpnonce'];
        }
        if (!$nonce) {
            return false;
        }
        return (bool) wp_verify_nonce($nonce, 'wp_rest');
    }

    public function list_presets(WP_REST_Request $request)
    {
        return rest_ensure_response([
            'presets' => $this->presets->get_presets(),
        ]);
    }

    public function process_request(WP_REST_Request $request)
    {
        $rate_limit = (int) $this->settings->get('rate_limit_rpm', 10);
        $identifier = $this->get_rate_identifier($request);
        if (!$this->rate_limiter->allow($identifier, $rate_limit)) {
            return new WP_REST_Response(['message' => __('Slow down - please wait a few seconds and try again.', 'nano-floor-designer')], 429);
        }

        $sample_param = $request->get_param('sample');
        $sample = is_string($sample_param) ? json_decode($sample_param, true) : $sample_param;

        if (!is_array($sample) || empty($sample)) {
            return new WP_REST_Response(['message' => __('Invalid or missing flooring selection.', 'nano-floor-designer')], 400);
        }

        $files = $request->get_file_params();
        $room_file = $files['roomImage'] ?? null;
        if (!$room_file) {
            return new WP_REST_Response(['message' => __('Room image is required.', 'nano-floor-designer')], 400);
        }

        $validation = $this->validate_room_image($room_file);
        if (is_wp_error($validation)) {
            return new WP_REST_Response(['message' => $validation->get_error_message()], 400);
        }

        $reference_file = $files['referenceImage'] ?? null;
        $reference = null;
        if ($reference_file && !empty($reference_file['tmp_name'])) {
            $ref_validation = $this->validate_reference_image($reference_file);
            if (is_wp_error($ref_validation)) {
                return new WP_REST_Response(['message' => $ref_validation->get_error_message()], 400);
            }
            $reference = $this->encode_image_file($reference_file['tmp_name']);
            $reference['mime'] = $reference_file['type'];
        }

        $room_encoded = $this->encode_image_file($room_file['tmp_name']);
        $room_encoded['mime'] = $room_file['type'];

        $result = $this->gemini->render_flooring(
            $room_encoded['base64'],
            $room_encoded['mime'],
            $this->normalize_sample($sample),
            $reference['base64'] ?? null,
            $reference['mime'] ?? null,
            (int) $validation['width'],
            (int) $validation['height']
        );

        if (!empty($result['error'])) {
            return new WP_REST_Response(['message' => $result['error']], 500);
        }

        $storage = $this->persist_result($result);
        $response = [
            'original_width' => (int) $validation['width'],
            'original_height' => (int) $validation['height'],
        ];
        if (!empty($storage['url'])) {
            $response['processed_url'] = $storage['url'];
        }
        if (!empty($result['base64'])) {
            $response['processed_base64'] = 'data:' . ($result['mime_type'] ?? 'image/png') . ';base64,' . $result['base64'];
        }
        if (!empty($storage['attachment_id'])) {
            $response['attachment_id'] = $storage['attachment_id'];
        }
        return rest_ensure_response($response);
    }

    private function get_rate_identifier(WP_REST_Request $request): string
    {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        $ip = $request->get_header('X-Forwarded-For');
        if ($ip) {
            $ip = explode(',', $ip)[0];
        }
        if (!$ip) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'anon';
        }
        return 'ip_' . sanitize_key($ip);
    }

    private function validate_room_image(array $file)
    {
        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return new WP_Error('nfd_room_missing', __('Missing room image upload.', 'nano-floor-designer'));
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            return new WP_Error('nfd_room_size', __('Room image exceeds 10MB limit.', 'nano-floor-designer'));
        }
        $info = getimagesize($file['tmp_name']);
        if (!$info) {
            return new WP_Error('nfd_room_invalid', __('Unable to read image metadata.', 'nano-floor-designer'));
        }
        $mime = $info['mime'] ?? '';
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            return new WP_Error('nfd_room_type', __('Unsupported room image format.', 'nano-floor-designer'));
        }
        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        if ($width < 800 || $height < 600) {
            return new WP_Error('nfd_room_dimensions', __('Room image must be at least 800x600.', 'nano-floor-designer'));
        }
        if ($width > 4000 || $height > 4000) {
            return new WP_Error('nfd_room_dimensions_max', __('Room image must be 4000px or smaller on each edge.', 'nano-floor-designer'));
        }
        return ['width' => $width, 'height' => $height, 'mime' => $mime];
    }

    private function validate_reference_image(array $file)
    {
        if ($file['size'] > 10 * 1024 * 1024) {
            return new WP_Error('nfd_ref_size', __('Reference image exceeds 10MB limit.', 'nano-floor-designer'));
        }
        $info = getimagesize($file['tmp_name']);
        if (!$info) {
            return new WP_Error('nfd_ref_invalid', __('Unable to read reference image.', 'nano-floor-designer'));
        }
        $mime = $info['mime'] ?? '';
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            return new WP_Error('nfd_ref_type', __('Unsupported reference image format.', 'nano-floor-designer'));
        }
        if (($info[0] ?? 0) < 400 || ($info[1] ?? 0) < 400) {
            return new WP_Error('nfd_ref_dimensions', __('Reference image must be at least 400x400.', 'nano-floor-designer'));
        }
        return true;
    }

    private function normalize_sample(array $sample): array
    {
        $normalized = [
            'id' => sanitize_text_field($sample['id'] ?? ''),
            'name' => sanitize_text_field($sample['name'] ?? ''),
            'prompt' => sanitize_textarea_field($sample['prompt'] ?? ''),
            'material_id' => sanitize_text_field($sample['materialId'] ?? ''),
            'material_name' => sanitize_text_field($sample['materialName'] ?? ''),
            'dimension' => sanitize_text_field($sample['dimension'] ?? ''),
            'style' => sanitize_text_field($sample['style'] ?? ''),
        ];
        if (!empty($normalized['id']) && is_numeric($normalized['id'])) {
            $preset = $this->presets->get_preset((int) $normalized['id']);
            if ($preset) {
                $normalized['name'] = $preset['title'];
                if (!empty($preset['dimension'])) {
                    $normalized['dimension'] = $preset['dimension'];
                }
                if (!empty($preset['style'])) {
                    $normalized['style'] = $preset['style'];
                }
                if (!empty($preset['description'])) {
                    $normalized['prompt'] .= ' ' . $preset['description'];
                }
            }
        }
        return $normalized;
    }

    private function encode_image_file(string $path): array
    {
        $contents = file_get_contents($path);
        return ['base64' => base64_encode($contents)];
    }

    private function persist_result(array $result): array
    {
        if (empty($result['base64'])) {
            return ['url' => $result['remote_url'] ?? '', 'attachment_id' => 0];
        }
        $binary = base64_decode($result['base64']);
        if (!$binary) {
            return ['processed' => null];
        }
        $upload = wp_upload_bits('nano-floor-' . time() . '.png', null, $binary);
        if (!empty($upload['error'])) {
            return ['processed_base64' => $result['base64']];
        }
        $file_path = $upload['file'];
        $url = $upload['url'];
        $attachment = [
            'post_mime_type' => $result['mime_type'] ?? 'image/png',
            'post_title' => 'Nano Floor Result ' . current_time('mysql'),
            'post_content' => '',
            'post_status' => 'inherit',
        ];
        $attachment_id = wp_insert_attachment($attachment, $file_path);
        if (!is_wp_error($attachment_id)) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file_path));
        } else {
            $attachment_id = 0;
        }
        return ['url' => $url, 'attachment_id' => $attachment_id];
    }
}
