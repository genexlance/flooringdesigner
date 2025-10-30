# Nano-Floor Designer - WordPress Plugin Tasks

Legend: [ ] pending, [x] complete, [~] in progress

## Phase 0: Transition & Planning
- [x] Audit legacy assets and define WordPress plugin architecture
- [x] Prune Next.js-specific tooling and dependencies
- [x] Update prd.md with WordPress-oriented product notes

## Phase 1: Plugin Scaffold
- [x] Create plugin folder structure and bootstrap file
- [x] Implement autoloader and core plugin loader class
- [x] Register activation/deactivation hooks (options + post type setup)

## Phase 2: Admin & Data Management
- [x] Build admin settings page for API keys and integration toggles
- [x] Register and sanitize plugin options via Settings API
- [x] Register Flooring Preset custom post type + capabilities
- [x] Implement meta boxes or custom fields for preset details (price, specs, assets)
- [x] Add admin list/table enhancements for presets (columns + filtering)
- [x] Register Flooring Materials custom post type with dimensions/styles metadata

## Phase 3: REST API & Processing
- [x] Create REST controller for uploads, processing, and preset retrieval
- [x] Implement secure media handling (validation, temp storage, cleanup)
- [x] Integrate Gemini 2.5 Flash Image API call via wp_remote_post with error handling
- [x] Support optional floor reference images and custom prompts
- [x] Implement rate limiting / throttling per user/IP

## Phase 4: Frontend Experience
- [x] Enqueue modular frontend assets with localized config + nonce
- [x] Provide shortcode to render Nano-Floor Designer UI
- [x] Provide Gutenberg block wrapper for editor usage
- [x] Build interactive UI (upload -> select -> generate -> view) with accessible components
- [x] Include before/after slider, download, share actions, and loading spinner overlay

## Phase 5: QA, Security, and Docs
- [x] Add nonce + capability checks across admin + REST surfaces
- [x] Ensure output escaping and sanitization (admin + frontend)
- [x] Document setup and usage in README/prd.md
- [ ] Final accessibility review for UI + aria labels
- [ ] Prepare deployment/packaging checklist for distribution
- [x] Update Gemini models to latest versions
