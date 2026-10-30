# Teacher management repair

User authorized completing the existing tenant teacher CRUD and exposing grant/revoke controls. Root cause: only create/list routes existed; the Vue page omitted permission fields. Empty lists were interpreted as unrestricted, and API payloads bypassed scope checks.

- [x] Add tenant-owned update/delete actions, validated class/subject/source grants, and account status.
- [x] Show permission controls, summaries, edit/cancel/reset, explicit All options and delete confirmation.
- [x] Add tenant navigation entry. Null means All; empty means no access.
- [x] Enforce deactivation and revoked scopes server-side, including omitted source filters, topics, by-ID requests and nested builder payloads.
- [x] Restrict school question read/manage queries, normalized create rows and source-changing updates.
- [x] Protect saved work from cascading user deletion under a row lock; offer deactivation instead.
- [x] Review and verify.

Verification: 28 PHP tests / 341 assertions passed (TeacherManagementTest, SchoolQuestionBankTest, ManageSchoolQuestionsTest, PaperBuilderClassesTest); browser checks passed permission groups, edit/reset, All classes and mobile width; production build passed. Scoped PHP formatting and diff whitespace checks passed. Independent reviewer found and then verified fixes for source/topic/nested-request bypasses and normalized source writes, with no remaining important issues in the final narrow review. Full PHP suite not rerun; the prior full-suite run in this chat did not finish.

The separate question-assistant redesign is documented in specs/2026-10-30-question-assistant-design.md. User approved its starting limits; overall behavior and additional cost guards await design approval. Do not describe that replacement as implemented.
