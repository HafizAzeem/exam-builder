# Paper Templates Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement task-by-task.

**Goal:** Ship the six approved paper layout choices with save/print parity.
**Architecture:** New named template IDs replace unused legacy headers; reuse Template 1 content rendering and introduce a shared editable alternate header.
**Tech Stack:** Laravel, Vue 3, Vite, Node tests, PHPUnit.
**Spec:** docs/superpowers/specs/2026-10-29-paper-templates-design.md

## Global Constraints
Preserve current user changes and Template 1. No dependencies or fixed board mark schemes. Legacy IDs fall back to 1.

## Review Focus
Edited content survives switching; saved choices survive export; Urdu remains readable; long headers wrap; print columns fit.

### Task 1: Rendering and settings
- [x] Add SSR tests rendering all choices, edited content and legacy fallbacks; observe failure.
- [x] Add template catalogue, alternate header and sidebar controls; remove old header/body variants.
- [x] Keep shared question editing and apply template-specific styles and defaults.

### Task 2: Persistence and verification
- [x] Enable supported choices server-side and normalize saved IDs without forcing Template 1.
- [x] Add regression coverage for saved preview and invalid IDs.
- [x] Run Node tests, PHP tests, production build and browser print/layout checks.
- [x] Review the final diff and record outcomes here.

Ruling: Work in the existing checkout to preserve the user's substantial uncommitted application changes. No commits requested.

Verification: production build passes; 10 Node tests pass; template browser checks pass for selection, edits, metadata, Urdu, two-column defaults, and screen/print widths. Existing print browser test passes single-copy fit, delayed images, double-copy layout and cleanup. Three focused PHP tests pass with 248 assertions including save/reopen/print for all templates. All changed PHP files pass syntax checks; scoped diff has no whitespace errors. Broader PHP suite attempted twice, stopped after several minutes without a result; full-suite status is unverified.

Review: independent reviewer found column preference resets and irrelevant metadata leaking between headers; both fixed and covered by browser assertions. Route regression exposed nested validation stripping edited content; preserve the complete layout parent after validation, with passing route assertions.

Final independent review of these fixes found no remaining concrete important concerns.
