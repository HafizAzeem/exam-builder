# English and Urdu public SEO

## Included

Eight public page purposes, each in English and Urdu (16 canonical URLs): home, schools/academies, AI question preparation, Urdu/English papers, pricing, practical paper-creation guide, about and contact. English uses `/` and the existing readable slugs; Urdu uses `/ur` plus the corresponding slug.

Pages render as public HTML without requiring JavaScript. Metadata includes unique titles/descriptions, self-canonical URLs, reciprocal en-PK/ur-PK/x-default alternates, social previews, organization/site/page/application structured data and child-page breadcrumbs. Visible prices and schema offers use the actual subscription settings. Private account/application pages retain noindex and authentication.

The homepage provides responsive WebP variants (approximately 55 KB and 24 KB) with the original 1.36 MB PNG retained as fallback/social image. These are transfer-size improvements, not a measured Core Web Vitals score.

## Deployment

1. Deploy the source, both content JSON files and image variants. Configure `SEO_PUBLIC_URL=https://exambuilder.algorahub.com` (no trailing slash). This public canonical origin is deliberately separate from local `APP_URL`.
2. Clear stale caches before checking new routes: `php artisan config:clear`, `php artisan route:clear`, and `php artisan view:clear`.
3. Run `php artisan seo:build-public` and `npm run build`. The SEO command writes `public/sitemap.xml`, updates its absolute URL in robots.txt, and regenerates the factual llms.txt summary. Run it again after canonical-origin or plan-price changes.
4. Verify `/`, `/ur`, `/pricing`, `/ur/pricing`, `/sitemap.xml` and `/robots.txt` on the deployed HTTPS host. Confirm no host/CDN authentication challenge blocks ordinary public crawling. Private application pages must remain excluded from the sitemap and non-indexable.
5. If the deployment uses cached configuration/routes, rebuild them only after the preceding steps using `php artisan config:cache`, `php artisan route:cache` and `php artisan view:cache`. Never leave a cache from an earlier release in place.

No production deployment or search-engine submission was performed by the local implementation.

## Search-engine ownership and measurement

The owner should verify the production domain in Google Search Console and Bing Webmaster Tools, then submit the sitemap URL. Use URL Inspection on the public English and Urdu pages to check the fetched HTML and indexing status. Account access or verification tokens must come from the owner; none were invented or added.

Track non-brand impressions/clicks for paper-generator, school/academy and Urdu/English intents, then organic trial signups and conversions. Bing's AI-performance reporting, where available in the owner's account, can add evidence about citations. IndexNow can be considered after domain ownership and the deployment workflow are settled; no unrequested outbound submission or scheduled monitoring was created.

`llms.txt` is maintained as a factual summary for services that choose to use it. It is not a Google ranking mechanism. Public pages do not guarantee rankings, indexing or AI citations.

## Board-content expansion

The audience is Pakistan-wide. Current pages explicitly distinguish audience reach from verified syllabus availability. Add substantive board/class/book pages only after corresponding content and editions are verified. Do not generate empty city or board pages, imply official affiliation, or publish private school papers as SEO samples.

Keep the practical guide and sample questions original and reviewed. Update both language files together when a real product capability, price or coverage statement changes.
