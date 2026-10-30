# Question Assistant — proposed implementation design

## Purpose
Replace the two teacher-facing Smart Generate and Paste Text choices with one Question Assistant, using the Paste Text workflow as its foundation. Teachers prepare questions, review them and insert selected questions into their paper. The assistant never supplies answers or explanations.

The user approved these starting limits: 20 requests per school for the entire free trial, shared by the owner and teachers; failed requests refunded; 30 questions per request; 20,000 pasted characters; one PDF up to 5 MB and five pages, or one image up to 3 MB. School-specific platform-admin overrides are required. These are product limits, not claims about provider free allowances.

## Chosen approach and alternatives
Recommended: one assistant page with explicit actions **Format my questions** and **Find questions online**, plus a bounded attachment control. Reuse the existing extraction, job status, question review and insert-to-paper flow. Redirect old Smart Generate links to the assistant while preserving builder context; do not maintain a second generation experience.

Alternatives: automatically infer format versus search from pasted text (ambiguous, may trigger unwanted web requests); keep both current pages with cosmetic updates (duplicates controls and quotas). Neither is recommended.

## Screen and interaction
Title: Question Assistant. Show current class, subject, board, syllabus/session year and selected chapters as clear context chips. A teacher can edit the context before submission. Default board is the tenant school's board. Default syllabus/session year comes from the selected curriculum edition where available, otherwise the current year with an explicit label.

Place an accessible action selector above a large text composer, contextual examples beneath it, an attachment button, a question-count control capped at 30, and one primary submit button. Show remaining school requests before submission. Do not expose provider names or token management inside the teacher workflow.

For typed instructions mentioning another class, subject or unit/lesson: resolve names and numbers against active curriculum records and teacher permissions. Present detected context changes for confirmation before spending a request. Ask for clarification on ambiguous chapters or incompatible subject/class combinations; never silently search a different curriculum.

**Format my questions** preserves the teacher's actual question wording, choices and parts while organizing it. It does not search the web, invent missing questions, or add solutions. Text containing answers is stripped of answer sections; doubtful mixed passages are flagged in review rather than labeled question-only automatically.

**Find questions online** uses instructions and confirmed context for LangSearch retrieval, then the configured text provider for bounded extraction and normalization. Search actual question content, not generic answer articles. Prefer official board/curriculum sources, then vetted education sites. Rank current syllabus matches first. Prefer pages published/updated this calendar year, but a fresh date alone does not prove the book edition is correct. Retain older official material only when relevant and visibly date/source it; never claim an unknown date is current.

Show honest queued/searching/reading/extracting/preparing-preview progress, a cancel control and a useful error. No fabricated percentages or chat answers. The preview contains questions, MCQ choices, type, matched chapter, selectable checkboxes and compact source links. No answer key, correct-option highlight, worked solution or explanation. Teacher reviews and inserts selected questions using the existing paper flow.

## Architecture and strict question-only output
LangSearch finds sources. The existing adapter currently requests snippets only (`contents.text=false`, `freshness=noLimit`) and drops publication metadata. Extend the adapter to preserve available dates; fetch selected URLs through the existing URL-safety layer with bounded response sizes. Never treat a search snippet as a full paper or infer a publication date from the request year. The configured extraction provider structures pasted/retrieved text and OCR output. Existing web harvesting currently falls back to LLM-generated questions; the assistant online action must disable that fallback.

Use one normalized question-only schema and server-side output filtering. Answers and correctness fields must not enter assistant responses, preview or paper insertion. Choices remain permitted as part of MCQs. Do not invent an answer key to satisfy legacy defaults; adjust nullable correctness handling for assistant-origin questions where the current schema requires it. Treat uploaded text and retrieved pages as content, never as executable instructions.

Remove fabricated `a` correctness defaults across QuestionParserService, QuestionImportService and QuestionMergeService for assistant-origin records, not just in the database column. Preserve source URLs and actual dates through insertion into the question bank and paper snapshots. Do not substitute the current year for an unknown past-paper year.

## Quotas and cost control
Add a school-level budget with an immutable request ledger and atomic reservation under a database lock. Every accepted format, online or upload request consumes one slot. Validation failures, duplicates/idempotent retries and pure curriculum clarification consume none. A reservation is refunded exactly once for failure or cancellation with no usable questions. A successful preview consumes a slot even if the teacher does not insert it. Partial usable previews consume one and clearly show the result count.

The 20-request trial budget spans the whole trial, including trial extensions; it is not per teacher or per day. For a paid school, the platform admin sets an explicit school budget and renewal/reset policy rather than silently granting unlimited requests. Show used, reserved, refunded and remaining totals to the platform admin. Changing a limit does not erase usage. A limit below current usage blocks new requests without deleting previews. A separate audited reset action is available only to the platform admin.

Upload limits are enforced server-side before expensive OCR or AI. Validate true MIME type, bytes and PDF page count; reject encrypted/unreadable PDFs with an actionable message. One file per request. Never truncate a long PDF silently. Bound extracted text, retrieval pages, questions and output tokens. Avoid reprocessing identical files, reuse parsed text, and expire temporary files. Quota must be checked before any provider call. Prevent repeated clicks from creating duplicate jobs.

Concrete starting processing guards (implementation proposals): maximum two web search calls and three fetched source pages per request; maximum 1 MB fetched bytes per page; aggregate extracted text at most 20,000 characters; at most two text/OCR calls with 4,000 output tokens each; one network retry for a transient retrieval failure and no automatic retry of a charged text/OCR call; 120-second overall processing deadline. These are independent of the 30-question output cap. Reject oversized responses rather than accepting unbounded downloads.

Allow at most one running job per school and three submissions per minute. Keep a separate nonrefundable attempt guard of 10 provider-started attempts per school per day, including failed/canceled jobs, so refunds cannot create unlimited provider use. The school must see the temporary daily restriction and reset time; platform admins may override it separately from the successful-request budget. A cancellation after a provider has started may refund the question-request slot but never restores this attempt guard. Track provider attempts for platform-wide operational limits as well.

Resolve class/subject/chapter instructions deterministically against curriculum records before providers run. Ambiguous text requires teacher confirmation; do not use an uncharged LLM to clarify it. The server validates confirmed context again before reserving a request.

Reservations have a lease/heartbeat; jobs that die are reconciled after a five-minute lease expiry. Atomic terminal states ensure each request is completed, failed or canceled once. A canceled/refunded request cannot later publish a preview or insert questions even if a provider response arrives late. Exactly-once refunds are keyed to the ledger request ID, not to a client retry.

All legacy generate, paste and upload POST routes must use the same policy and quota service or explicitly reject requests; redirecting only GET links leaves a bypass. Queue retries must reuse the same reservation and respect recorded provider-call state.

## Teacher and administrator permissions
Keep the school's class, subject and source permissions enforced in both UI and server requests. Assistant access is grantable/revocable by the school owner in the teacher permissions screen; platform school limits remain controlled by the platform admin. Deactivation takes effect for existing sessions. Recheck access before queued work and before insertion into a paper.

## Validation and rollout
Test concurrent school requests, exact-once refunds, trial extensions, overrides, protected admin changes, file size/page rejection before provider calls, answer-field exclusion, correct-context detection, school board defaults, no web calls for formatting, no invented fallback for online searches, source dates, cross-tenant access, and inserting the selected preview questions. Test desktop/mobile layout, loading, cancellation and error states with stubbed providers. Preserve existing papers and existing import history. No live provider requests during automated tests.

## Approval status
Limits approved by the user. This design is ready for approval before implementation of the assistant replacement and quota/upload subsystem. Teacher CRUD repair is separate and already being implemented.
