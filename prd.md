# Product Requirements Document: Nano-Floor Designer

Source: consolidated from agents.md

## 1. Executive Summary

### Product Overview
Nano-Floor Designer is a WordPress plugin that enables users to visualize flooring options in their own spaces using AI-powered image generation. Visitors upload photos of their rooms and instantly preview new flooring materials rendered by Google Gemini's nano-banana model.

### Key Value Proposition
- **Instant Visualization**: See how new flooring will look in the actual room.
- **Risk-Free Decisions**: Explore unlimited options before purchasing.
- **Time & Cost Savings**: Eliminate physical samples and showroom trips.
- **Professional Results**: AI-powered, photorealistic renderings.

## 2. Product Goals & Objectives

### Primary Goals
1. Provide an intuitive, seamless flooring visualization experience directly inside WordPress.
2. Increase customer confidence in flooring purchases.
3. Reduce return rates by enabling better pre-purchase decisions.
4. Generate qualified leads for Flooring Superstore.

### Success Metrics
- Average session duration > 5 minutes.
- > 15% of users request a quote or flooring samples.
- NPS > 40.
- Image generation time < 10 seconds.
- 10,000+ monthly active users within 6 months.

## 3. User Personas

### Homeowner Hannah
- Age: 35-55
- Tech savvy: moderate
- Goals: Renovate confidently and stay on budget
- Pain points: Hard to visualize floors, afraid of making the wrong choice

### Professional Pete
- Age: 30-50
- Role: Interior designer or contractor
- Goals: Deliver quick client presentations
- Pain points: Sample ordering delays and client indecision

## 4. Functional Requirements

### 4.1 Core Features
- **Image Upload & Processing**: JPEG/PNG/WEBP (max 10MB), minimum 800x600px, optional reference image.
- **Flooring Presets**: WordPress custom post type containing pricing, specs, imagery, and metadata.
- **Flooring Materials**: Admin-managed list of material types, dimensions, and optional styles that fuel the custom generator.
- **AI Processing**: Server-side integration with Google Gemini nano-banana model, including fallback to the text+image endpoint.
- **Results & Sharing**: Before/after slider, download link, share button (Share API + clipboard), optional WordPress media persistence.

### 4.2 User Flow
1. Landing Page -> Clear value proposition with CTA
2. Upload Room Photo -> Drag & drop or browse interface
3. Select Room Type -> Helps AI understand context
4. Browse Flooring Options -> Filter and search catalog
5. Generate Visualization -> Loading state with spinner overlay
6. View Results -> Interactive before/after comparison
7. Take Action -> Save, share, or request quote/samples

### 4.3 WordPress Admin Experience
- Dedicated Nano-Floor menu with Dashboard, Flooring Presets, Flooring Materials, and Settings.
- Presets CPT with featured image, category, brand, price, SKU, specs.
- Materials CPT storing standard dimensions and styles (e.g., Parquet for Hardwood).
- Settings page for Gemini credentials, rate limiting, and debug logging.
- Capability-aware access: `manage_options` for settings, `edit_posts` for CPTs.

## 5. Technical Specifications

### 5.1 Technology Stack
- **Platform**: WordPress 6.6+ (PHP 8.1+)
- **Backend**: WordPress Settings API, REST API, Transients API, Media Library
- **Frontend**: Vanilla ES6, shortcode + Gutenberg block wrapper
- **Styling**: Tailwind-inspired utility CSS delivered as a static stylesheet
- **External Services**: Google Gemini nano-banana API

### 5.2 Hosting & Infrastructure
- Any WordPress-compatible host (WP Engine, Kinsta, SiteGround, etc.)
- Assets served through WordPress with optional CDN already in place
- Media uploads stored in the WordPress media library
- Caching: WordPress object cache/transients for rate limiting plus page caching/CDN for front-end pages

### 5.3 API Architecture
Namespace `nano-floor/v1`
```
GET  /nano-floor/v1/presets   - Retrieve published flooring presets
POST /nano-floor/v1/process   - Upload room image, optional reference, trigger Gemini processing
```
Authentication via `X-WP-Nonce` (`wp_rest`) with per-minute rate limiting and capability checks.

## 6. Design Specifications
- Brand colors: #171a56 primary, #ff4a4a secondary, white background.
- Typography: Inter (bold headings, light body).
- Components: Buttons, cards, forms, loading overlay with animated spinner.
- Responsive breakpoints: 320-768 (mobile), 768-1024 (tablet), 1024+ (desktop).

## 7. User Experience Considerations
- WCAG 2.1 AA compliance (contrast, keyboard navigation, aria labels).
- Clear error handling for uploads, processing, and rate limiting.
- Optional onboarding copy within the Dashboard.

## 8. Security & Privacy
- Nonce validation and capability checks across admin and REST endpoints.
- File validation (size, mime type, dimensions) before processing.
- TLS/HTTPS assumed for all data in transit.

## 9. Monetization Strategy
- Lead generation for Flooring Superstore.
- Premium tiers (no watermark, higher resolution, unlimited processing).
- Affiliate partnerships and potential white-label licensing.

## 10. Launch / Future Work
- MVP: Upload -> Generate -> Share flow with presets and custom materials.
- Phase 2: Analytics hooks, optional queueing, deeper material metadata.
- Future: AR/VR preview, AI style recommendations, pricing calculators.
