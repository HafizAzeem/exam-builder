# Dynamic Subscription Plans Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Centralise school plans and trial settings under super admin control.
**Architecture:** Database plan records and one subscription catalog feed public pricing, metadata, registration, renewal and manual activation. Shared Blade pricing keeps public layouts consistent.
**Tech Stack:** Laravel, MySQL, Inertia, Vue, Blade, Tailwind.
**Spec:** docs/superpowers/specs/2026-10-27-dynamic-subscription-plans-design.md (approved by user).

## Global Constraints
- Initial prices: 500 / 1 month, 1350 / 3 months, 4500 / 12 months. Initial trial: 14 days.
- Preserve existing expiry dates, historical identifiers and payment events.
- No AI entitlement or payment gateway work. Schools add their own questions.
- Work in the existing checkout to preserve the user's ongoing changes; do not commit unrelated work.

## Review Focus
- Disabling the last active plan must fail.
- Disabled plans must not be offered or accepted for new renewals.
- Changing prices must not rewrite recorded payment events.
- Custom names must render safely in JSON metadata and English/Urdu HTML.
- Trial changes affect future registrations only.

### Task 1: Catalog and admin editing
Files: migration for subscription_plans/settings; app/Support/SubscriptionCatalog.php; app/Http/Controllers/SuperAdmin/PlanController.php; routes/web.php; tests/Feature/SubscriptionPlansTest.php.
- [x] Write feature tests for defaults, role authorization, editing/creation/validation, last-active guard and unchanged expiry/history.
- [x] Run tests and confirm failure before implementation.
- [x] Implement catalog active(): array, trialDays(): int; transactional validated plan/settings updates and seeded defaults.
- [x] Run feature tests.

### Task 2: Consumers and public pricing
Files: PublicSite, PublicPageController, SubscriptionController, RegisteredUserController, SchoolController; public/pricing.blade.php; landing/page templates; English/Urdu content; Renew.vue, Schools/Index.vue, Register.vue.
- [x] Add tests for all public pricing/schema consumers, edited trial registration, annual calendar renewal and disabled plan rejection.
- [x] Run tests to confirm failure.
- [x] Connect every consumer to catalog; share one public pricing section; remove landing/pricing AI promises; update old expected prices/trial values.
- [x] Run related public/subscription/onboarding tests.

### Task 3: Admin UI and verification
Files: resources/js/Pages/SuperAdmin/Plans/Index.vue; AuthenticatedLayout.vue; deployment notes.
- [x] Add responsive admin forms with errors, labels and editable bilingual names, PKR amount, months, order, active state and trial days.
- [x] Add navigation and dynamic school activation/renewal selection.
- [x] Run frontend build, relevant feature tests and PHP formatting checks.
- [x] Review final scoped changes and document migration/deployment requirements.

## Execution ledger
- User explicitly requested build after design approval; execute inline with no additional approval round.

- Initial red run: 5 tests failed because catalog tables/routes and annual plan did not exist.
- Catalog green run: 5 tests, 92 assertions passed.
- Validation hardening: regression for internal database fields failed before adding an explicit allowed-key list, then passed in the final suite.
- Final verification: 38 public pricing/subscription/onboarding/access tests passed, 451 assertions; npm run build passed; scoped PHP formatting applied; git diff --check passed.
- Independent scoped review found no actionable defects. Desktop browser check confirmed aligned trial and paid cards on the local landing page.
- Applied only the additive catalog migration to the local application database and regenerated public discovery files.
- Ruling: discovery summaries link readers to live pricing instead of storing amounts or trial duration, so later admin edits cannot leave stale published prices there.
- Ruling: testing-only application AI controls remain unchanged, as specified. Landing/pricing AI promises and public AI navigation promotion were removed.
- Work remains in the existing checkout without commits, merges or pushes, preserving the user's ongoing work.
