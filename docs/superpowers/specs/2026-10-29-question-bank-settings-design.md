# Question Bank and paper Details

User requests moving paper settings out of Question Bank into the existing Details dialog, plus a board filter in the main Question Bank row, defaulting to the tenant school's board. User delegates single versus multi selection: choose single selection with All boards, matching the existing API and keeping the row compact.

Keep Question Bank controls to type/source/chapter/board/quantity and past-paper year. Put language in Details (already exists); section marks, attempt count, answer lines, MCQ table and question-parts controls belong in a section settings component within Details. Preserve settings on repeat adds; changing settings updates preview without losing question edits. Attempts cannot exceed selected count, zero means all; marks use attempted count.

Board applies to past-paper tags and explicit contributed-board attribution for ordinary questions. Keep common unassigned questions available. Year applies only to past-paper results, never erasing ordinary selected-source results. Default comes from authenticated user's institution; no board means All boards. No multi-select complexity or schema changes.
