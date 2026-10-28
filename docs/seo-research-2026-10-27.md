# Pakistan school and academy SEO research

Research date: 27 October 2026 (Asia/Karachi).
Audience decision: schools and academies across Pakistan, with English and Urdu public pages.

## Evidence and limits

This is a qualitative search-results and first-party documentation audit, combined with repository inspection and a live homepage fetch. Keyword candidates below reflect visible search language and product fit. They are not measured monthly search volumes, difficulty scores, ranking positions, or guaranteed opportunities. No Google Search Console, Keyword Planner, Bing account data, or paid keyword dataset was available. Search results vary by location and personalization; this was not a Pakistan-geolocated Google rank-tracking run.

The production homepage at https://exambuilder.algorahub.com/ returned HTTP 200. Its body was fetched and inspected. Sitemap/robots/llms observations below refer to repository files unless expressly stated otherwise. No production changes or indexing submissions were made.

## Audit findings

| Priority | Finding | Action |
|---|---|---|
| Critical | Live JSON-LD contains a PHP-code string as its top-level context key. Blade interprets the literal @context key as a directive. | Local fix implemented by separating the key string. Added a rendered-JSON regression test; 3 SEO tests pass. Deploy this fix to correct production output. |
| High | Sitemap contains only the homepage; there is no public content architecture for different buyer questions. | Add a small set of useful, distinct, server-rendered marketing/help pages and include only canonical, indexable URLs. |
| High | Home title leads with Exam Builder; hero does not clearly say online question paper generator or name academies. | Align title/H1/intro with the product category and Pakistani school/academy audience, using natural language. |
| High | Public content is English-only. | Add genuine Urdu translations, RTL layout, language switcher, localized titles, reciprocal en-PK/ur-PK alternate links, and self-canonicals. |
| High | The default board seeder lists nine Punjab boards; curriculum seeders are strongly Punjab-focused. | Distinguish national audience reach from verified curriculum coverage. Do not advertise complete all-board coverage on this evidence. Verify board/book/edition coverage before creating board-specific claims. |
| Medium | SoftwareApplication schema lacks current visible trial/paid-plan details. | Represent real PKR prices and billing duration accurately, matching visible content; avoid invented ratings or eligibility promises. |
| Medium | Canonical and entity URLs are repeated as hard-coded strings. | Centralize the public production SEO origin separately from a developer's APP_URL; derive canonical/schema/sitemap links consistently. |
| Medium | Main promotional PNG is approximately 1.36 MB. | Measure mobile loading first; create optimized image variants and retain correct dimensions/alt text. No measured Core Web Vitals score is available yet. |
| Medium | llms.txt does not reflect the new school trial, pricing, and manual renewal details. | Keep factual summaries current if retained, but do not present this file as a Google ranking lever. |
| Positive | Homepage is server-rendered; canonical, social metadata, large-preview permissions, and descriptive image text exist. | Retain and test these fundamentals. |
| Positive | Application shell uses noindex; school dashboards are authenticated. | Keep account/private application pages out of public sitemaps. Share only deliberately public examples, never school papers or user contact data. |

The live homepage's private/no-cache response is normal for session-backed Laravel pages and is not itself a noindex signal. Investigate caching only as a performance concern, without caching personalized sessions publicly.

## Keyword map

Priorities are editorial recommendations based on fit and search-result observations, not traffic estimates. Avoid separate near-duplicate pages for each spelling.

| Priority | Cluster / English candidates | Urdu / Roman Urdu candidates | Intent and destination |
|---|---|---|---|
| P1 | online paper generator Pakistan; question paper generator Pakistan; paper maker Pakistan | آن لائن پیپر جنریٹر، سوالیہ پرچہ بنانے کا سافٹ ویئر، online paper banane ka software | Product discovery: homepage |
| P1 | paper generator for schools; exam paper software for schools; paper generator for academies | سکول کے لیے پیپر بنانے کا سافٹ ویئر، اکیڈمی کے لیے پیپر جنریٹر | School-owner purchase: schools/academies page |
| P1 | Urdu paper maker; English Urdu paper generator; bilingual question paper maker | اردو پیپر بنانے کا سافٹ ویئر، اردو انگلش پیپر جنریٹر | Language capability: bilingual feature page |
| P1 | AI question paper generator; AI exam paper maker; generate questions from textbook PDF | اے آئی پیپر جنریٹر، کتاب سے سوالات بنائیں | AI workflow: AI feature page, with teacher-review limitations |
| P1 | paper generator price Pakistan; school paper maker subscription; affordable paper generator | پیپر جنریٹر کی قیمت، سکول پیپر سافٹ ویئر کی فیس | Purchase: pricing page showing 7-day trial, Rs 500 monthly, Rs 1,500 for 3 months |
| P2 | how to make question paper online; how to create school exam paper | آن لائن سوالیہ پرچہ کیسے بنائیں، paper kaise banaye | Instruction: original step-by-step product guide |
| P2 | chapter wise test generator; topic wise paper maker; MCQ short long question paper | باب وار ٹیسٹ بنائیں، ایم سی کیوز اور مختصر سوالات کا پیپر | Workflow guidance: guide/features; verify capabilities before publishing |
| P2 | school logo question paper; printable exam paper maker; save and print question paper | سکول کے لوگو کے ساتھ پیپر، پرنٹ کے لیے امتحانی پرچہ | Practical workflow: screenshots/sample output in guide |
| P2 | online question bank for teachers Pakistan; shared question bank schools | اساتذہ کے لیے سوالات کا بینک، سوالیہ بینک | Product feature: explain exact curriculum matching and contribution sharing |
| Conditional | Punjab board paper generator; Lahore board paper maker; PECTAA question paper generator | پنجاب بورڈ پیپر جنریٹر، لاہور بورڈ پیپر میکر | Publish only with verified relevant book/edition coverage; clarify independent product, not board affiliation |
| Conditional | FBISE paper generator; federal board paper maker | فیڈرل بورڈ پیپر جنریٹر | Validate curriculum and formats first |
| Conditional | Sindh board paper generator; Karachi board question paper maker | سندھ بورڈ پیپر جنریٹر، کراچی بورڈ پیپر میکر | Validate curriculum and formats first |
| Conditional | KPK/KP board paper generator; Peshawar board paper maker | خیبر پختونخوا بورڈ پیپر جنریٹر | Validate curriculum and formats first |
| Conditional | Balochistan/Quetta board paper generator; AJK board paper maker; Gilgit Baltistan paper generator | بلوچستان بورڈ پیپر جنریٹر، آزاد کشمیر پیپر میکر، گلگت بلتستان پیپر جنریٹر | Treat regions accurately; do not invent a single board or duplicate city pages |
| Conditional | class 9/10 paper generator; class 11/12 question paper maker; subject-specific English/Physics/Math paper maker | نویں/دسویں جماعت پیپر جنریٹر، گیارہویں/بارہویں جماعت کا پرچہ | Later useful curriculum resources, only where actual coverage and examples exist |

Do not target permanently free software, free unlimited papers, official board affiliation, guaranteed exam questions, or complete all-board syllabus support unless those statements become true. The current free offer is a seven-day trial. Avoid claiming online student testing, auto-marking, or examination results management merely because competitors offer them.

## Search-result observations

Competitor pages repeatedly foreground the product category, medium, class/chapter selection, board applicability, sample output, and pricing. These observations show vocabulary and content patterns, not proof that those practices caused rankings:

- [PaperMaker](https://papermaker.pk/): category positioning and chapter/topic/medium workflows.
- [Paper Point curriculum](https://paperpoint.pk/curriculum.php): board/curriculum discovery.
- [Rising Stars paper generator](https://risingstarspakistan.com/paper-generator/): class, board, workflow explanation and demo.
- [ILMSAAZ](https://www.ilmsaaz.com/): AI textbook upload workflow and explicit pricing.
- [TestUstad academies](https://testustad.com/for-academies): dedicated academy audience page; its entrance-test focus differs from this product.
- [PaperCreator FAQ](https://papercreator.pk/faq): detailed buyer questions about language, school use, and workflow.

Use original app screenshots, authentic sample papers, and actual product instructions. Do not copy competitors' wording, counts, testimonials, or claims.

## What AI search actually requires

- Google's [generative AI search guide](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide) emphasizes useful original content, discoverability, indexable public pages, and page experience. It explicitly says Google ignores llms.txt for search ranking/visibility and requires no special AI schema. Meeting requirements does not guarantee indexing or citations.
- [Bing's guidelines](https://www.bing.com/webmasters/help/webmaster-guidelines-30fba23a) connect ordinary crawling/indexing/content quality to Bing/Copilot discovery and warn against keyword stuffing, scaled low-value content, and AI manipulation.
- Bing supports sitemap discovery and IndexNow; [its AI Performance announcement](https://blogs.bing.com/webmaster/2026/2/Introducing-AI-Performance-in-Bing-Webmaster-Tools-Public-Preview/) describes visibility measurement. A submission is a discovery notification, not a ranking guarantee.
- Keep visible FAQs genuinely useful. Do not promise FAQ rich results; Google's [documentation updates](https://developers.google.com/search/updates) describe removal of that rich-result feature in 2026.
- Search crawler permission and model-training permission are different choices. Existing permissive bot entries are not a substitute for Google/Bing indexing, and training access is not required to rank on Google.

## Recommended initial release

Build a focused bilingual public site first: home, school/academy use, AI question preparation, Urdu/English paper creation, pricing, and one genuinely useful how-to guide, with clear about/contact information. Give each page a distinct purpose. Use paired English/Urdu routes and a shared public layout, while keeping the authenticated Vue app private.

Publish a truthful coverage explanation for all Pakistani audiences. Add board- and class-specific resources only after verifying the corresponding content. Do not generate hundreds of thin board/city/subject pages.

Before launch: validate rendered JSON-LD and visible-price consistency; sitemap every public canonical language URL; check redirects, HTTP status, noindex boundaries, internal links, RTL mobile layouts, and real mobile performance. After deployment, verify ownership in Google Search Console/Bing Webmaster Tools and submit the sitemap. Measure non-brand impressions, relevant queries, organic signups, and trial conversions; use that evidence to prioritize the next content pages. Credentials and site verification require the owner's connected accounts or supplied verification tokens.

## Status

Completed: initial keyword/competitor research, repository audit, live homepage availability/body inspection, and local JSON-LD bug fix with failing-then-passing regression test (3 tests, 17 assertions).

Implementation follow-up: the recommended focused bilingual site is now implemented locally, with eight page purposes in English and Urdu, shared metadata/schema/canonicals, a 16-URL sitemap, current discovery summaries, public/private access tests and optimized homepage imagery. See docs/superpowers/plans/2026-10-27-bilingual-seo.md for verification evidence and docs/seo-deployment.md for release steps.

Remaining: actual search-volume/account-data research, production deployment, and webmaster-account verification/submission. Board-specific pages follow verified curriculum additions. No ranking, traffic, indexing, or AI-citation outcome is claimed.
