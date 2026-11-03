# Question Assistant Implementation Plan

> Use superpowers:executing-plans for integration and superpowers:dispatching-parallel-agents for independent modules.

**Goal:** Ship the approved Paste-based question-only assistant with bounded source retrieval/uploads and school-level budgets.
**Architecture:** New AssistantRequest ledger + AssistantBudgetService reserves school budgets atomically. TeacherAIController submits imports to ProcessQuestionAssistantJob; a bounded QuestionAssistantService extracts only sourced/pasted questions and publishes through the ledger's atomic completion callback. Existing review and insertion are reused with assistant-specific answer stripping and provenance. UI replaces Smart Generate and Paste text with one assistant entry.
**Tech Stack:** Laravel, Vue 3, existing QuestionAiService and LangSearch provider, queues, Smalot PDF parser, PHP/Node/browser tests.
**Spec:** docs/superpowers/specs/2026-10-30-question-assistant-design.md (approved by user).

## Global constraints
20 requests for the whole school trial; admin configurable; 10 provider attempts/day; one running request/school; 3 submissions/minute. 30 questions, 20,000 chars, one PDF <=5MB/5pages or image <=3MB. Two search calls, three <=1MB pages, <=2 AI calls with 4000 output tokens each; 120s job; 5min lease. No generated fallback or answers. Legacy POST routes guarded. Preserve user changes and existing import history.

## Interfaces
Budget module produces App\Models\AssistantRequest with institution_id,user_id,ai_import_id,request_key,mode,status,reserved_at,provider_started_at,expires_at,finished_at,error_message. Status: reserved/processing/completed/failed/canceled. School attributes assistant_request_limit=20, assistant_daily_limit=10, assistant_budget_started_at nullable. TeacherPermission attribute can_use_assistant=true.
App\Services\QuestionAssistant\AssistantBudgetService methods: summary(Institution):array (limit,used,reserved,refunded,remaining,daily_limit,daily_used); reserve(User,string requestKey,string mode):AssistantRequest; attachImport(AssistantRequest,AIImport):void; start(AssistantRequest):bool; complete(AssistantRequest,callable publish):bool (publish runs transactionally exactly once, no publication after cancellation); fail(AssistantRequest,string error):void; cancel(AssistantRequest):void. start counts a provider attempt before any provider call and rejects revoked/deactivated actors. summary/reserve reconcile stale leases. Locks always school then request.
Root produces controller POST fields mode=format/search/upload, request_key UUID, raw_text, file optional, question_count 1..30, existing grade/subject/chapter/book_type/board/year/language, confirm_context bool optional. Paste page receives current context plus assistantUsage, defaultBoard, curriculumYear. Status/review receive assistantRequest object when applicable; cancellation route builder.ai.cancel.
UI module edits Paste.vue, Status.vue, Review.vue, Compose.vue entry links and teacher Assistant checkbox only. Root owns TeacherAIController/routes, extraction/parser/import/provenance services and tests. Budget module owns migration/models/budget service, SuperAdmin SchoolController and school page plus quota route. Only root runs database tests (shared testing DB); others write tests but don't execute PHP suites concurrently.

## Task 1 — budget/admin module
- [x] Test and implement atomic reservation/idempotency, exhausted limits, daily/rate/concurrency guards, exact-once refunds, stale leases and cancellation.
- [x] Add school admin quota override/reset with audit, and teacher assistant grant/revoke field validation.

## Task 2 — bounded processing + integration
- [x] Write failing tests for question-only filtering, context validation, upload limits and no generated fallback.
- [x] Implement bounded retrieval/extraction job and atomically publish questions through budget service.
- [x] Guard old endpoints, review/cancel/insert and preserve source metadata without fabricated answers/dates.

## Task 3 — teacher interface
- [x] Build one accessible composer, context controls, format/search actions, upload/count/limit controls, meaningful loading/cancel/error states.
- [x] Replace dual entry points and show question-only review with source links.

## Task 4 — verification
- [x] Run focused PHP, all Node tests, browser workflows, production build and independent final review; apply safe migration after checking pending migrations.

## Review focus
Cross-tenant quota use; race between cancellation/publication; stale worker publishing after refund; free retries causing provider cost; answer keys/default-a leaking through legacy import/merge.


Completion verification (2026-11-01): 42 focused PHP tests passed (assistant, budgets, question-only payload, free search, parser, legacy review and teacher management); 13 Node tests passed; assistant browser checks passed; production build passed; scoped Pint and git diff whitespace checks passed. Both assistant migrations applied locally and assistant:reconcile executed successfully. Independent pipeline and UI reviews from the implementation session were addressed, including cancellation races, mathematical text fidelity, source attribution, quota reset boundaries and local HTTP UUID support. The full 222-test suite was started but interrupted after slow progress; it is not recorded as passing. Provider calls were mocked; no live provider credits were used. The scheduled cleanup command requires the deployment scheduler to run each minute.
