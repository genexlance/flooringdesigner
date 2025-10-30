# Nano-Floor Designer (WordPress Plugin)

Nano-Floor Designer brings AI-powered flooring visualizations directly into WordPress. Upload a room image, choose a flooring preset (or craft a custom description), and let Google's Gemini 2.5 Flash Image model generate a realistic preview. The plugin supports both shortcodes and a Gutenberg block so the experience can live anywhere on your site.

## Features
- WordPress-native admin experience with a dedicated menu
- Flooring Presets custom post type (title, description, pricing, specs, featured image)
- Flooring Materials manager for defining per-material dimensions and optional styles
- Settings page for Gemini credentials (defaults to gemini-1.5-pro-latest and gemini-2.5-flash-image), rate limiting, and debug logging
- REST API (`nano-floor/v1`) with nonce protection and per-user/IP throttling
- Frontend app with upload validation, preset catalog, custom prompt workflow, before/after slider, and sharing tools

## Requirements
- WordPress 6.6+
- PHP 8.1+
- Google Gemini API key with image generation enabled

## Installation
1. Copy the plugin folder to your WordPress instance (`wp-content/plugins/nano-floor-designer`).
2. Activate **Nano-Floor Designer** from the Plugins screen.
3. Navigate to **Nano-Floor -> Settings** and add your Gemini API key, model IDs, and desired rate limit.
4. Add Flooring Presets under **Nano-Floor -> Flooring Presets**. Supply metadata and a featured image to enrich the catalog.
5. Define material types under **Nano-Floor -> Flooring Materials**, including standard dimensions and style options.

## Embedding the Experience
- **Shortcode**: `[nano_floor_designer]`
- **Gutenberg Block**: search for "Nano-Floor Designer" in the block inserter.

The plugin automatically enqueues its assets and localises REST configuration (including nonce and preset data) for each page where the app is rendered.

## REST Endpoints
- `GET /wp-json/nano-floor/v1/presets` - returns published presets for the frontend carousel.
- `POST /wp-json/nano-floor/v1/process` - accepts `roomImage`, optional `referenceImage`, and `sample` JSON. Requires `X-WP-Nonce` generated with the `wp_rest` action.

Responses include base64 data URIs and/or media URLs plus attachment IDs when uploads succeed.

## Security Notes
- All administrative surfaces enforce capability checks (`manage_options` or `edit_posts`).
- Meta boxes and REST endpoints include nonces to mitigate CSRF.
- File uploads are capped at 10MB, limited to JPEG/PNG/WEBP, and validated for dimensions before processing.
- Rate limiting uses WordPress transients to prevent abuse (default 10 requests/minute).

## Development Tips
- Autoloaded classes live in `includes/` and follow the `NFD_` prefix convention.
- Frontend assets are plain ES6/CSS; no build step is required.
- Keep individual files under 500 lines-split functionality if you approach the limit.

## Roadmap
- Audio feedback or toast notifications for long-running tasks
- Optional queue/cron integration for high-volume processing
- Accessibility audit pass (aria attributes and focus management)
- Deployment checklist for packaging or distribution
