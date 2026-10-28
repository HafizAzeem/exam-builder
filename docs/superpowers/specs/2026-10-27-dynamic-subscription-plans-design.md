# Dynamic school subscription plans

## Intent
Give super admins one place to manage subscription plans, and keep public pricing, renewals and school activation consistent. Schools supply their own questions; AI is not included in the advertised offer.

## Approaches
- Recommended: database-backed plan records and subscription settings, read through one service by every consumer. Supports admin edits without deployment and avoids duplicated prices.
- Config-only plans: simpler, but cannot satisfy super admin editing without changing deployment files.
- Editable marketing cards only: leaves renewal prices and actual access duration inconsistent; rejected.

## Proposed implementation
- Add a super admin Plans page to create and edit plans, enable or disable them, and set display order. Each plan has a stable identifier, English and Urdu names, price in PKR, and duration in calendar months. Require at least one active plan.
- Store trial duration in subscription settings, initially 14 days. Changes apply to new registrations; existing expiry dates remain unchanged.
- Initial paid plans: Monthly Rs 500 / 1 month; Three months Rs 1,350 / 3 months; Annual Rs 4,500 / 12 months. Existing historical plan identifiers remain valid.
- Use the same active plan source for public English/Urdu pricing, structured metadata, subscription renewal, super admin school activation and payment history amounts. Do not delete historical subscription events when plans change or are disabled.
- Consolidate public pricing into one shared section: trial banner, three paid cards, one shared feature list, own-question-bank explanation, and payment instructions. Use a responsive grid and consistent billing labels. Remove stale hardcoded price summaries.
- All paid plans offer the same core tools: add and organise questions, create/edit/save/print papers, manage teachers and WhatsApp support. Do not advertise unlimited usage or ready-made curriculum content.
- Remove AI promises from the landing page and pricing copy. Keep testing-only AI implementation unchanged in this task; hiding testing controls across the app is separate from managing plans.
- Existing manual JazzCash/Easypaisa payment verification stays in place.

## Validation
Test super admin authorization and validation, admin edits appearing across all pricing consumers, active/inactive plan handling, 14-day registration trials, annual activation duration and recorded price, and preservation of existing subscription history. Run relevant existing feature tests and the frontend build.

## Scope boundaries
No payment gateway, automatic charges, per-feature tiers, question-content subscription or AI entitlement system.

## Approval
Approved by the user with “Yes build”. Implemented and verified; see the corresponding implementation plan for the execution record.
