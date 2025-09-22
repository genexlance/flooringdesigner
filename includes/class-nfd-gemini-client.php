<?php
/**
 * Google Gemini API integration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NFD_Gemini_Client
{
    private $settings;
    private $base = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(NFD_Settings $settings)
    {
        $this->settings = $settings;
    }

    public function render_flooring(string $room_base64, string $room_mime, array $sample, ?string $reference_base64, ?string $reference_mime, int $width, int $height): array
    {
        $api_key = $this->settings->get('gemini_api_key');
        if (!$api_key) {
            return ['error' => __('Gemini API key missing. Add it under Settings.', 'nano-floor-designer')];
        }

        $model_id = $this->settings->get('gemini_image_model', 'gemini-2.5-flash-image-preview');
        if (!$model_id) {
            return ['error' => __('Gemini Image Model is not configured in settings.', 'nano-floor-designer')];
        }

        $prompt = $this->build_prompt($sample, $reference_base64, $width, $height);

        $url = $this->get_model_endpoint($model_id, true) . '?key=' . rawurlencode($api_key);
        $body = $this->build_body($model_id, true, $prompt, $room_base64, $room_mime, $reference_base64, $reference_mime);
        $response = $this->request($url, $body);

        if (is_wp_error($response)) {
            return ['error' => '[' . $model_id . '] ' . $response->get_error_message()];
        }

        $parsed = $this->parse_response($response);
        if (!empty($parsed) && empty($parsed['error'])) {
            return $parsed; // Success, we have an image
        }

        // Failure, let's get a detailed error message.
        $error_detail = '';
        if (!empty($parsed['error'])) {
            $error_detail = $parsed['error'];
        } else {
            $response_body = wp_remote_retrieve_body($response);
            $json = json_decode($response_body, true);

            if (!is_array($json)) {
                $error_detail = 'Malformed API response: ' . substr($response_body, 0, 500);
            } elseif (!empty($json['error']['message'])) {
                $error_detail = 'API Error: ' . $json['error']['message'];
            } elseif (empty($json['candidates'])) {
                $error_detail = 'API returned no candidates. Response: ' . substr($response_body, 0, 500);
            } else {
                $error_detail = __('No image returned', 'nano-floor-designer');
            }
        }
        return ['error' => '[' . $model_id . '] ' . $error_detail];
    }

    private function build_prompt(array $sample, ?string $reference_base64, int $width, int $height): string
    {
        $has_prompt = !empty($sample['prompt']);
        $pieces = [];
        if ($width && $height) {
            $pieces[] = sprintf('IMPORTANT: Output image must be exactly %1$dx%2$d pixels.', $width, $height);
        }
        $pieces[] = 'You are an expert interior photo editor. Your primary task is to realistically replace the floor in the provided image.';
        $pieces[] = 'CRITICAL INSTRUCTION: You MUST replace 100% of the visible floor area. Remove any rugs or other objects on the floor, and replace the area beneath them with the new flooring. The new material must extend to all walls and edges, leaving no gaps or original flooring visible.';
        $pieces[] = 'Preserve the original lighting, shadows, and perspective of the room. Do not change walls, furniture that is not on the floor, or any other decor.';

        if ($reference_base64) {
            $pieces[] = 'The provided reference image is the absolute source of truth for the new flooring\'s appearance. You MUST replicate its color, texture, and pattern exactly. All other text descriptions are secondary.';
            $details_from_prompt = [];
            if (!empty($sample['dimension'])) {
                $details_from_prompt[] = 'plank/tile dimensions: ' . $sample['dimension'];
            }
            if (!empty($sample['style'])) {
                $details_from_prompt[] = 'style: ' . $sample['style'];
            }
            if (!empty($details_from_prompt)) {
                $pieces[] = 'Use these hints for scale and layout: ' . implode('; ', $details_from_prompt) . '.';
            }
            if ($has_prompt) {
                $pieces[] = 'Also consider the user\'s secondary request: "' . $sample['prompt'] . '".';
            }
        } else {
            if ($has_prompt) {
                $pieces[] = 'Use this description for the new flooring: ' . $sample['prompt'] . '.';
            } else {
                $details = $sample['name'] ?? '';
                $pieces[] = 'Use sample: ' . trim($details) . '.';
            }
            if (!empty($sample['dimension'])) {
                $pieces[] = 'Plank/tile dimensions: ' . $sample['dimension'] . '.';
            }
            if (!empty($sample['style'])) {
                $pieces[] = 'Style: ' . $sample['style'] . '.';
            }
        }

        $pieces[] = 'Maintain a realistic scale and orientation for the new flooring. Match the output resolution to the input image.';
        $pieces[] = 'Output the edited image directly, with no transparency.';
        return implode(' ', array_filter($pieces));
    }

    private function build_body(string $model_id, bool $via_images_api, string $prompt, string $room_base64, string $room_mime, ?string $reference_base64, ?string $reference_mime): array
    {
        $room_part = ['inlineData' => ['mimeType' => $room_mime, 'data' => $room_base64]];
        $reference_part = $reference_base64 && $reference_mime ? ['inlineData' => ['mimeType' => $reference_mime, 'data' => $reference_base64]] : null;

        $parts = [['text' => $prompt]];
        if ($reference_part) {
            $parts[] = $reference_part;
        }
        $parts[] = $room_part; // Room image last to ensure output matches its dimensions

        if ($via_images_api) {
            return [
                'contents' => [
                    ['role' => 'user', 'parts' => $parts],
                ],
            ];
        }

        return [
            'contents' => [
                ['role' => 'user', 'parts' => $parts],
            ],
            'generationConfig' => ['temperature' => 0.2],
        ];
    }

    private function get_model_endpoint(string $model_id, bool $via_images_api): string
    {
        if (strpos($model_id, 'models/') === 0) {
            return 'https://generativelanguage.googleapis.com/v1beta/' . $model_id . ':generateContent';
        }
        if ($via_images_api) {
            return 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model_id) . ':generateContent';
        }
        return $this->base . '/models/' . rawurlencode($model_id) . ':generateContent';
    }

    private function request(string $url, array $body)
    {
        $response = wp_remote_post($url, [
            'timeout' => 120,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
        ]);
        if ($this->settings->get('enable_debug_logging')) {
            error_log('[NFD] Gemini request to model: ' . $url);
            if (is_wp_error($response)) {
                error_log('[NFD] Gemini WP_Error: ' . $response->get_error_message());
            } else {
                $code = wp_remote_retrieve_response_code($response);
                error_log('[NFD] Gemini response code: ' . $code);
                if ($code >= 400) {
                    error_log('[NFD] Gemini error response body: ' . wp_remote_retrieve_body($response));
                }
            }
        }
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            $message = wp_remote_retrieve_body($response);
            return new WP_Error('nfd_gemini_error', 'HTTP ' . $code . ': ' . $message);
        }
        return $response;
    }

    private function parse_response($response): array
    {
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return [];
        }
        $candidates = $json['candidates'] ?? [];
        foreach ($candidates as $candidate) {
            $parts = $candidate['content']['parts'] ?? [];
            foreach ($parts as $part) {
                if (!empty($part['inlineData']['data'])) {
                    return [
                        'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png',
                        'base64' => $part['inlineData']['data'],
                    ];
                }
                if (!empty($part['fileData']['fileUri'])) {
                    return [
                        'mime_type' => $part['fileData']['mimeType'] ?? 'image/png',
                        'remote_url' => $part['fileData']['fileUri'],
                    ];
                }
            }
        }
        if (!empty($json['promptFeedback']['blockReason'])) {
            return ['error' => 'Gemini blocked request: ' . $json['promptFeedback']['blockReason']];
        }
        return [];
    }
}
