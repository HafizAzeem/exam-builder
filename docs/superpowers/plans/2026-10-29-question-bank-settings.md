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

- [ ] Write failing regression tests for board filtering/default and section options persistence.
- [ ] Move controls into Details and rebuild preview while preserving text edits.
- [ ] Add defaulted board dropdown, apply service filtering and avoid stale search results.
- [ ] Run focused PHP, Node, browser and build verification; request final review.
