# Oct 30 Patch Playbook

This guide documents the precise code updates required to (1) accept portrait and landscape photos that meet the 800x600 minimum resolution and (2) migrate Gemini image generation to the supported `gemini-2.5-flash-image` model. Follow the steps in order for each app that still shows the original validation error.

## 1. Verify REST upload validation
- Open the main REST controller (the class that processes `POST /.../process`). Locate the function that validates the uploaded room image. It should call `getimagesize()` to inspect width/height.
- Replace any direct `width < 800 || height < 600` checks with the orientation-aware logic:
  - Accept when `(width >= 800 && height >= 600)` **OR** `(width >= 600 && height >= 800)`.
  - When rejecting, return an error that includes the detected dimensions. This makes field debugging easier for future runs.
- Leave the existing max-dimension guard (`<= 4000` or equivalent) unchanged.

```php
$meets_primary = ($width >= 800 && $height >= 600);
$meets_rotated = ($width >= 600 && $height >= 800);
if (!$meets_primary && !$meets_rotated) {
    return new WP_Error(
        'nfd_room_dimensions',
        sprintf(
            __('Room image must be at least 800px on the longer side and 600px on the shorter side. Detected size: %1$dx%2$d.', 'text-domain'),
            $width,
            $height
        )
    );
}
```

## 2. Default to Gemini 2.5 Flash Image
- In the Gemini client class, update the default model ID to `gemini-2.5-flash-image`.
- Add a safeguard that rewrites deprecated models (`gemini-2.0-flash-preview`, `gemini-2.5-flash-image-preview`, or any legacy alias) to the new ID before making the API call.
- Ensure requests still use the Images API endpoint (`.../models/{model_id}:generateContent`) with inline data.

```php
$model_id = $settings->get('gemini_image_model', 'gemini-2.5-flash-image');
if (in_array($model_id, ['gemini-2.0-flash-preview', 'gemini-2.5-flash-image-preview'], true)) {
    $model_id = 'gemini-2.5-flash-image';
}
```

## 3. Update Settings Defaults and UI Copy
- In the plugin/app settings definition, change any placeholder/default for the image model to `gemini-2.5-flash-image`.
- Replace references to the old “nano-banana” model in admin copy, README, PRD notes, etc., so non-dev users know which model is current.

## 4. Regenerate Documentation (optional but recommended)
- Confirm the README or internal docs now list the new minimum image requirement wording (explicit mention of longer vs. shorter side).
- Note the Gemini upgrade in release notes or change log so downstream deploy scripts pick it up.

## 5. Deployment Checklist
- Redeploy the updated PHP files to each WordPress (or PHP-based) site.
- Clear opcode/object caches or restart PHP-FPM so the new validation logic loads.
- Re-test with:
  - Landscape sample ≥ 1200x800.
  - Portrait sample ≥ 800x1200.
- If errors persist, the enhanced message will show what dimensions the server is detecting—use that to troubleshoot user uploads (look for EXIF rotation issues or CDN resizing).
