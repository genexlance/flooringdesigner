# Nano-Painter Slider Smoothness Fix

Use this playbook to bring Nano-Painter-style before/after slider behavior to another project that suffers from a “sticky” or hard-to-drag overlay slider.

---

## 1. Revisit State & Utilities
- Ensure the frontend controller stores the slider position (0–100). Add helpers to clamp values and to update the DOM and state together.
- Track drag status (`sliderDragActive`, `sliderDragPointerId`) so pointer captures can be released correctly even if the pointer leaves the component.

### Example Helpers
1. `normalizeSliderValue(value)` → clamp between 0–100.
2. `setSliderValue(value, { force })` → update state, sync `<input type="range">`, and re-render overlays.
3. `updateSliderFromPointer(event)` → translate pointer `clientX` to percentage of component width.

---

## 2. Enhance `renderPreviews`
- Always sync the slider input’s `value` with state.
- Animate the processed-image wrapper to match the percentage; disable the animation while actively dragging.
- Toggle CSS classes (e.g., `np-before-after--ready`, `np-before-after--dragging`) so styles can respond instantly.
- When clearing results, release any pointer capture to avoid locking the cursor.
- Optional: expose a slider indicator element, and gray it out when results are unavailable.

---

## 3. Upgrade the Markup Structure
Inside the before/after container:

1. `div.np-before-after`
2. `img.np-image-before`
3. `div.np-after-wrap > img.np-image-after`
4. `div.np-slider-indicator` (new): a vertical marker that mirrors the slider position.
5. `input.np-slider[type="range"]`

Record references (`elements.beforeAfter`, `elements.sliderIndicator`, etc.) so render helpers can update them.

---

## 4. Add Pointer/A11y Behavior
- Attach `pointerdown/move/up/cancel/leave` listeners to the main wrapper.
- On pointer down:
  - Ignore non-left mouse buttons.
  - Set the dragging flags, focus the slider (for keyboard users), capture the pointer, and immediately update the slider position from the pointer location.
- On pointer move: if dragging, update the slider from the pointer.
- On pointer up/cancel/leave: clear the dragging flags, release pointer capture, and remove the dragging class.
- Keep the slider’s native `input` listener; call `setSliderValue` to share logic with the drag handler.

---

## 5. Refresh Slider Styles
- Add base styles for the indicator and tweak cursor feedback:
  - Default container cursor → `default`, but `ew-resize` once results exist.
  - Dragging cursor → `grabbing`.
  - Touch handling → `touch-action: pan-y` so horizontal drags feel natural on mobile.
- Style the range track/thumb for better affordance (rounded track, red thumb, focus scaling).
- Disable `np-after-wrap` transitions while dragging to keep the overlay perfectly synced with the pointer.

---

## 6. Test the Interaction
1. Load the front-end and generate an image.
2. Drag the overlay by the range input and by grabbing anywhere along the image. Confirm the indicator, cursor changes, and smooth motion.
3. Try fast back-and-forth motions—there should be no lag or “snap back”.
4. Confirm behavior on touch devices or emulators (no vertical scroll hijacking, slider moves under your finger).
5. Reset the state (upload new photo or clear results) and ensure pointer capture never locks the cursor.

---

## 7. Optional Enhancements
- Show a tooltip or percentage readout at the indicator.
- Persist the slider position between renders if that benefits UX.
- Add keyboard shortcuts (left/right arrows) for accessibility—they already work if focus remains on the range input.

Follow these steps and the second application’s before/after slider will match the smooth, intuitive experience delivered in Nano-Painter.

