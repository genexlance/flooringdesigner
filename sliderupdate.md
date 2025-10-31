# Slider Overlay Modernization (Nano-Painter Port)

This checklist captures what we shipped for the Nano-Floor Designer slider so you can bring the same reveal experience into Nano-Painter (`np-` namespace).

---

## 1. Align Markup & Naming
- Wrap the before/after pair in `div.np-before-after`.
- Keep the original photo as `img.np-image-before` (regular document flow).
- Place the generated render inside `div.np-after-wrap > img.np-image-after`.
- Add a handle element `div.np-slider-handle > div.np-slider-handle-line`.
- Keep the range input (`input.np-slider[type=range]`) for keyboard accessibility.

> Tip: Start by cloning the updated markup from Nano-Floor Designer and swap `.nfd-` for `.np-`.

---

## 2. Update CSS Reveal Layer
- Apply a CSS custom property (`--np-slider`) on `.np-before-after` to drive the reveal.
- Use clip-path to reveal the generated image without shifting it:
  ```css
  .np-after-wrap {
    position: absolute;
    inset: 0;
    overflow: hidden;
    z-index: 2;
    --np-slider: 50%;
    clip-path: inset(0 calc(100% - var(--np-slider)) 0 0);
  }
  ```
- Style the handle/line similar to the new Nano-Floor design (centered on `--np-slider`, circle thumb, vertical divider).
- Add `@supports not (clip-path: …)` fallback that adjusts `width: var(--np-slider)` for legacy browsers.
- Ensure `.np-before-after img` defaults to `width: 100%; height: auto;` so portrait images are respected.
- Let `.np-before-after` declare `aspect-ratio: var(--np-aspect-ratio)`; JS will supply the value.

---

## 3. JavaScript Sync
- Track `supportsClipPath` and `supportsAspectRatio` once when the app boots.
- When the before image loads, grab `naturalWidth` & `naturalHeight`, set `--np-aspect-ratio`, and (if needed) compute height manually for legacy browsers.
- Expose helpers:
  - `applyAspectFromImage(img)`
  - `resetAspectRatio()`
  - `updateFallbackHeight()`
- On slider updates, set:
  ```js
  container.style.setProperty('--np-slider', sliderPercent);
  afterWrap.style.setProperty('--np-slider', sliderPercent);
  ```
  and only fall back to `style.width` if clip-path is unsupported.
- Prefer the hosted `processed_url` for the after image; normalize base64 strings to `data:image/png;base64,` when necessary so the preview always renders.
- Keep pointer handlers identical to Nano-Floor Designer (drag anywhere, release pointer capture on up/cancel/leave).

---

## 4. QA Checklist
1. Upload tall, square, and wide rooms—confirm no cropping.
2. Generate overlays; ensure the handle reveals without the image sliding underneath.
3. Test in Safari/Firefox/Chromium; verify fallback width logic works if clip-path support is missing.
4. Drag rapidly, try touch input, and use keyboard arrows while the range input is focused.

Follow the steps above and Nano-Painter’s before/after slider will match the polished Nano-Floor experience. Rename classes from `.nfd-` → `.np-`, and mirror the state helpers so both apps stay in sync going forward.

