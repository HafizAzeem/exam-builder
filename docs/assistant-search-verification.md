# Question Assistant search repair

Verified on 2026-11-03 (Asia/Karachi).

The reported Class 9 Mathematics search ("latest syllabus") retrieved video/course indexes and a past-paper download directory instead of question text. A broad retry also matched foreign boards and exam news. Seven preferred domains were already configured; missing configuration was not the original cause.

Retrieval now uses focused chapter queries, a preferred-domain first pass and open-web fallback (at most two search calls). Question-bearing titles outrank syllabus notices and directories; obvious incompatible boards and exam news are excluded. The existing three-page / 1 MB-per-page and 20,000-character aggregate limits remain. Long readable pages contribute bounded excerpts rather than being discarded wholesale.

PrepSure was added locally as an active preferred domain after inspecting its readable 2026 question pages. Two inspected Punjab Class 9 Mathematics pages (Real Numbers and Logarithms) are configured as curriculum-matched source fallbacks in config/question-assistant.php. Fallbacks require the domain to remain active and exact matching grade, subject, region and chapter title. Other curriculum continues to rely on search results. These URLs supply actual source material, never fabricated questions. This source's explicit plain-text question copies preserve mathematical notation and exclude worked solutions before extraction.

A later live failure came from Groq's 8,000-token-per-minute limit on the optional second source. Processing now stops once the requested maximum is verified. If a later source fails after usable questions are verified, the preview keeps them with a warning. Provider HTTP status, reason code and numeric token-limit diagnostics are stored without raw provider responses or credentials. Failure messages distinguish extraction/rate-limit problems from empty material.

Verification: 18 focused PHP tests passed; assistant browser checks passed; production build, Pint and diff whitespace checks passed. A normal budget-accounted live request completed with three source-verified questions, no answer fields and one provider call. Preview: /builder/ai/15/review. Failed requests were refunded; the successful preview consumed one school request (19 remaining at verification). The local queue worker was refreshed. Source/syllabus relevance still needs teacher review; inspected source pages do not guarantee universal coverage or official syllabus approval.
