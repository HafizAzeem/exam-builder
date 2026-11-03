# School onboarding implementation ledger

Approved design: school-owner signup, seven-day full trial, owner verification after one hour, manual WhatsApp payment, subscription management, shared question provenance, and public contact/pricing.

1. Signup and schema: complete. Atomic school/owner/role creation, logo cleanup on failure, active board, exact seven-day expiry, dashboard initialization independent of demo seeds.
2. Verification and subscription enforcement: complete. Owner grace, resend throttling, email-change reset, server checks and active-session refresh; production log mailer guard.
3. Renewal and super-admin management: complete. Calendar-month renewals, added trial weeks, request deduplication, account audit history, manual verification, school filters.
4. Question provenance and sharing verification: complete. Manual and AI contribution attribution; shared past-paper manual rows retain their actual contributor through staging; reuse preserves original provenance. Contributor IDs hidden from shared-bank serialization.
5. Frontend and public page: complete. Signup, renewal, Schools management, banners, public pricing/contact/WhatsApp, account links, logo preview.
6. Validation: full MySQL run passed (158 passed, 1 skipped); the skipped rollout/rollback check passed separately in SQLite. Later expanded MySQL regression group passed all 67 tests. Final saved-paper diagram-image exception is covered by an additional focused regression run. Production frontend build and PHP formatting checks passed. Public signup/pricing checked at 390px and 1440px, without horizontal overflow. Separate read-only code review found no blocking regressions.

Final focused MySQL run: 40/40 passed, including the saved-paper diagram-image exception and shared-import provenance. Final Pint check and git diff whitespace check passed.

Mail update (2026-10-27): the user configured SMTP credentials. Runtime MAIL_MAILER=smtp on port 465; an actual TLS connection and SMTP authentication succeeded without sending email or displaying credentials. Remaining deployment validation: send and open a verification email to confirm inbox delivery. See school-subscriptions-deployment.md.

Validation notes: the initial full SQLite run had three pre-existing MySQL compatibility failures: PastPapersRepositoryTest::test_show_renders_all_questions_for_one_paper (FIELD SQL function), DatabaseFirstPastPaperTest::test_find_reusable_returns_existing_review_collection, and PastPaperCollectorFeatureTest::test_store_reuses_existing_matching_paper (fixtures omit required source). These pass in the configured MySQL environment. Two briefly overlapping MySQL test processes caused test-only schema conflicts; stopped and replaced with a clean sequential full run. Application data was not involved.

Ruling: reuse the current checkout on a feature branch to retain the user's logout fix and local runtime/dependencies. Preserve the pre-existing deleted storage file.
Ruling: production verification enforcement requires a delivery-capable mailer. Provide a readiness check and deployment instructions; do not invent SMTP credentials or claim delivery works with the log mailer.
Ruling: verification restrictions take precedence over expired-school read exceptions, because owners must verify after one hour. Profile, verification, password recovery, and logout remain reachable.
Ruling: expired schools may load authorized diagram image assets for saved-paper viewing/printing; the diagram library and all diagram mutations remain blocked.
Local setup: applied both additive migrations, replaced the verified-empty public/storage directory with a junction to storage/app/public, and aligned local APP_URL with http://127.0.0.1:8000. No production deployment, payment gateway, or external messages were performed.
