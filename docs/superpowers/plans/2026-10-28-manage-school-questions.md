# Manage School Questions Implementation Plan

> Use superpowers:executing-plans; user approved permissions, permanent deletion and implementation in chat.

**Goal:** Teachers manage their own questions; school admins manage school-added questions without altering saved papers.
**Architecture:** Extend SchoolQuestionBankController with scoped listing/update/delete, reuse school question validation and diagram components, add an Inertia management page. Server-generated snapshots preserve saved question content for new papers and legacy papers before mutation.
**Constraints:** No trash/restore. Require deletion confirmation. Preserve attribution; prevent cross-school and shared-bank mutations. Keep teacher curriculum restrictions on edited destinations. Existing super admin question management remains available.

- [x] Write failing tests for scoped lists, ownership/role enforcement, edits (including MCQ/source changes), permanent confirmed deletion, saved-paper preservation and safe validation.
- [x] Implement server-generated question snapshots on saved-paper creation and freeze legacy papers referencing questions before mutation. Export uses snapshots while preserving paper-specific overrides.
- [x] Add paginated searchable management endpoints, scoped edit/delete authorization, transactional updates and relationship cleanup.
- [x] Add My Questions navigation (School Questions for admins), filters, edit modal, clear permanent-delete confirmation and error handling.
- [x] Run related PHP tests, frontend build and scoped formatting; obtain independent review and address findings.

## Review focus
- Same-school colleague questions are visible only to school admins, never editable by another teacher.
- Other-school and global questions cannot be mutated through school endpoints; school admins retain management of school-attributed questions after an author deletes their account.
- New and legacy saved papers retain MCQ options, answers, diagrams, parts and original question order after edits/deletion.
- Attribution and institution cannot be changed by the submitted payload.
- Permanent deletion removes owned children/options/tags without leaving selectable orphaned parts.

## Execution notes
- Implemented My Questions / School Questions with paginated search, type/chapter filters, bilingual text editing, MCQ options/diagrams, source/board and existing question parts.
- Existing Add My Questions creation stays in the paper builder. Attribution cannot be reassigned through edit payloads.
- Snapshots use existing config JSON; no database migration is needed. New papers capture server-side content; legacy papers are frozen transactionally before managed edits/deletes. Paper-specific overrides still apply.
- New JSON endpoints needed questions/* added to the existing exception JSON predicate; tests exposed redirect responses on validation failure.
- Independent review identified null-on-delete authorship: a school admin must retain access by school attribution. Added a regression test that failed with an empty admin list, then adjusted admin scope.
- Initial ownership routes failed as expected before implementation. Related regression run before the final author-scope fix: 35 tests / 273 assertions passed. Final verification: 36 tests passed with 288 assertions; frontend build passed. Scoped PHP formatting and git diff --check completed.

- Automatic test approval timed out on one attempt; the permitted retry completed successfully. No verification remains blocked.

## Full-page workflow follow-up
- User requested a separate, discoverable full CRUD section with repeatable Add row entries and school-wide teacher browsing.
- Added School Question Bank navigation and dashboard shortcuts, All school questions / My questions / Add questions tabs, a dedicated /questions/create URL, full-page adding and editing. Read-only colleague records expose can_manage=false; server mutation scopes remain unchanged.
- Active entry chapters respect teacher curriculum permissions and are available before the first question exists. Standalone drafts are isolated by school and user.
- Reused the existing bulk entry form inline, keeping the paper-builder modal workflow. Standalone saves return to the author's list; successful saves do not dispatch duplicate navigation.
- Review found text-only deduplication could discard matching text across chapters or question types. Regression failed (1 result instead of 3), then server identity was expanded and standalone rows preserved for server validation.
- Final follow-up verification: 32 tests passed, 282 assertions; frontend build passed; scoped diff whitespace check passed.

## Searchable dependency dropdown follow-up
- Added reusable SearchableSelect and CurriculumDropdowns components for Class → Subject → Chapter in full-page adding, editing and list filtering. Parent changes clear dependent fields; reselecting the same parent preserves children.
- New inline rows copy class, subject, chapter, type, source and board from the preceding row, leaving question/answers blank. Partial selections and restored drafts are supported; original fixed-curriculum paper-builder modal behavior remains intact.
- Added class/subject server filters; teacher entry options continue to respect assigned curriculum.
- Pure selection tests failed before implementation and for same-parent reselection before the fix. Final checks: 4 JS tests and 22 PHP feature tests passed (248 PHP assertions); frontend build passed; scoped whitespace check passed.
- Independent review identified keyboard access to Clear selection; adjusted focus handling and button key routing so Tab can reach Clear and Enter activates it normally.

## Paper-only diagrams and optional Urdu follow-up
- Removed diagram insertion controls from school question forms and paper-builder question entry; removed image upload controls from super admin question creation/editing. Existing stored question diagrams remain preserved and read-only list previews remain available.
- Urdu fields in school and super admin editing are shown only with Add Urdu text checked. Existing Urdu prechecks the checkbox to avoid hiding authored content. Bulk entry retains its existing per-row Urdu checkbox.
- Paper diagram attachments now accept legacy single objects or ordered arrays. Add from library appends; direct multi-file upload supports PNG/JPEG/WebP, up to 10 attachments, per-item width/alignment/removal/reordering, and compressed embedded snapshots independent of the diagram library.
- Upload preparation blocks paper apply/save/print/cancel until complete. Images are limited to 5 MB each and resized to a 1600px maximum edge before embedding.
- Verification: 7 JS tests passed; 29 PHP feature tests passed (329 assertions), including five attachments persisting through save/reopen/print and question parts. Frontend build and scoped whitespace checks passed.
