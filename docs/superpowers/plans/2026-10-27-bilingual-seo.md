# Bilingual public SEO implementation plan

Goal: help Pakistani school and academy owners discover and understand Exam Builder in English and Urdu, using the completed keyword research.

Architecture: preserve the English landing page, add an Urdu home and paired server-rendered product, school/academy, pricing, guide, about and contact pages. Centralize production SEO URLs, schema, language alternates and discovery-file generation. Keep authenticated application pages private.

Decision: implement the recommended focused public site now; board-specific resources follow verified curriculum additions. Do not claim complete all-board alignment. User authorized starting SEO after the research and this phased approach.

1. Write failing tests for public URLs, localized canonical/alternate tags, Urdu RTL, real subscription offers, sitemap coverage, unknown-page handling and private-page indexing boundaries.
2. Implement centralized page metadata and bilingual content, explicit routes, shared public head/layout, and homepage integration. Preserve signup, pricing and WhatsApp flows.
3. Generate public sitemap, factual llms summary and crawler discovery from the same URL configuration. Document deployment/measurement and webmaster-account requirements.
4. Run public SEO/access regression tests, full MySQL suite sequentially, formatting checks, production build, and responsive English/Urdu browser checks. Review final changes using the code-review skill.

Acceptance: sixteen distinct canonical language URLs (eight purposes), reciprocal hreflang, valid parsed JSON-LD, readable public HTML, accurate PKR plans, no invented ratings/board coverage/search volumes, no sitemap entries for account/private routes, and no new app-access permissions.

Progress: implementation complete locally. Sixteen public URLs render in English/Urdu, with valid structured data, language links, canonical production URLs, public discovery files and original bilingual copy. English homepage image has 55 KB / 24 KB WebP variants, with PNG fallback/social image preserved.

Validation: full MySQL suite: 166 passed, 1 skipped (SQLite-only migration rollback). After review, final focused MySQL SEO/access suite: 19 passed, 302 assertions. Independent public-page checks: 9 passed. Production build, Pint and whitespace checks passed. Browser checks at 390px and 1440px found no horizontal overflow in English or Urdu; actual responsive WebP loading was confirmed.

Review: separate read-only review found a hard-coded English homepage pricing inconsistency. Added a failing config-price override test, switched visible amounts to subscription config, and verified both language homes and pricing pages now agree with structured data. No other actionable findings were reported.

Ruling: pre-existing Laravel config/route caches hid the new SEO config/routes and caused the first verification run to fail. Cleared stale config, route and compiled-view caches, regenerated discovery files, and reran tests successfully. Cache rebuild requirements are documented in docs/seo-deployment.md.

Deployment and owner Search Console/Bing verification/submission remain outside the local implementation. No board-specific pages or unsupported curriculum claims were added. Existing onboarding/logout changes and deleted storage file were preserved. Research: docs/seo-research-2026-10-27.md.
