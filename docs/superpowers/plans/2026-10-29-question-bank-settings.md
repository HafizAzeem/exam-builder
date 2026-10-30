# Question Bank Settings Implementation Plan

> Use superpowers:executing-plans for inline execution.

**Goal:** Put paper controls in Details and default a single board filter to the school board.
**Architecture:** Shared section-settings utility/component; modal emits question selection only; service scopes board-specific data without hiding common questions.
**Tech Stack:** Vue, Laravel, Node tests, PHPUnit, browser checks.
**Spec:** docs/superpowers/specs/2026-10-29-question-bank-settings-design.md

## Constraints
Preserve existing user changes and paper edits; no dependencies, commits or migrations.

## Review Focus
Repeat additions preserve settings; preview reflects settings; school board default precedes search; clearing board stays cleared; mixed-source filtering preserves shared questions.

- [x] Write failing regression tests for board filtering/default and section options persistence.
- [x] Move controls into Details and rebuild preview while preserving text edits.
- [x] Add defaulted board dropdown, apply service filtering and avoid stale search results.
- [x] Run focused PHP, Node, browser and build verification; request final review.

Completed 2026-10-30 after resuming the interrupted session. Review identified a section-level MCQ layout precedence bug and loss of edited parts when hidden. Both reproduced with failing tests and fixed: section MCQ setting takes precedence over legacy global settings, and paper content always retains parts while the renderer handles visibility.

Verification: all 13 Node tests pass; actual Compose browser test passes school board default, All boards, controls in Details, preview marks, repeat additions, and edited long parts hidden/shown without loss. Production build passes. Focused PHP regression group passes 17 tests / 355 assertions (board filters, school question bank, builder classes, and template save/reopen/print). Full PHP suite was not rerun; the previous session's full-suite attempt did not finish.
