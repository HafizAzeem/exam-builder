# Shared diagram library implementation plan

Goal: Authenticated institutes and teachers share approved diagrams and create private uploads and drawings. Add diagram snapshots to paper questions and answer options.
Architecture: Laravel Diagram model with structured drawing JSON or private raster image; authenticated media endpoint; approval endpoints; Vue SVG renderer, editor and picker. Built-in catalogue seeded idempotently.
Stack: Existing Laravel/Inertia/Vue, no new dependencies.
Authorization: User requested implementation now; proceed inline in existing checkout without an additional approval gate. Teacher sharing defaults to super-admin approval. No anonymous library access.

- [x] Add regression tests for authentication, cross-user visibility, owner mutations, review, input safety, catalogue idempotency and paper snapshots.
- [x] Implement schema/model/controller, safe structured elements, authenticated uploads, moderation, catalogue and navigation.
- [x] Implement searchable library, editable shape primitives, labels, dimensions, rotation, layering, undo/redo, uploads and sharing.
- [x] Integrate snapshots and image rendering into question/option previews and printing; preserve visuals when rebuilding paper content.
- [x] Run feature tests, Vue compilation and available build/full-suite checks; review authorization and printing.

Review focus: private asset leakage; client-supplied ownership/status; unsafe SVG/URLs; shared originals modified; saved paper visuals lost on rebuild.

Results: 59 built-ins seeded locally; six MySQL feature tests pass (50 assertions); two JavaScript tests pass; production build passes; independent review finding fixed with regression test. Browser checked desktop/mobile, create/save, preview and PDF. Full MySQL suite 128/130, only pre-existing RegistrationTest failures.
