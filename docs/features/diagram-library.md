# Shared diagram library

Open **Diagrams** in the navigation. Every signed-in institute administrator, teacher and super administrator can browse approved diagrams. Anonymous visitors cannot browse or fetch uploads.

## Teachers and institutes

- Search the shared library by title or description, or filter by category.
- Choose **Make my copy** to customize a built-in or shared original.
- Choose **Create / upload diagram** to start a private drawing or upload PNG, JPEG or WebP (up to 5 MB and 6000 × 6000 pixels).
- Add rectangles, ellipses, triangles, lines, arrows, labels and freehand strokes. Drag to move; Shift-click multiple objects to move them together. Adjust coordinates, dimensions, colour, rotation and line styles; duplicate, reorder, delete, undo or redo.
- Crop uploaded pictures using percentages of the original image.
- **My diagrams** contains private, pending, approved and returned submissions. Private and returned diagrams can be edited or deleted. Shared originals are immutable; make a copy to revise them.
- **Submit to share** sends a private drawing to super-admin review. Approved submissions become available across all institutes.

## Review

Super administrators open **Awaiting review** in the library selector. Add a review note and approve for everyone or return for changes. Submissions are private to their author and super administrators until approved.

## Papers

In the paper builder, select **Edit**, then **Add diagram** under a question, question part or MCQ answer option. Choose from shared items or your own library. Adjust width and alignment, then apply edits/save the paper.

Inserted drawings are independent snapshots; inserted uploads embed image bytes in the paper snapshot. Later diagram edits or deletion do not alter saved papers. Existing question-bank images now survive conversion into the paper preview too. The same preview component renders saved and printed papers.

## Catalogue and deployment

The initial catalogue contains 59 original editable teaching diagrams in Basic shapes, Solid shapes, Geometry, Fractions, Graphs, Measurement, Science, Maps and Counting. These are schematic teaching illustrations, not a complete catalogue of every curriculum diagram or mathematically scaled constructions.

Run the new migration, then `php artisan db:seed --class=DiagramSeeder`. The default DatabaseSeeder also includes the catalogue. Re-running DiagramSeeder updates built-ins by stable keys without duplicating them or touching teacher diagrams.

No additional packages are required. For Vite use Node 20.19+ or 22.12+. This workstation also has compatible Node at `C:/Users/hafiz/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node.exe`.

## Verification

- DiagramLibraryTest: authentication, private visibility, editing rights, review, copying, protected uploads, invalid drawing input, catalogue reruns, replacing images with drawings, saved question and MCQ-option snapshots surviving library deletion.
- `node --test tests/js/paperDiagrams.test.mjs`: source image preservation and bilingual diagram snapshot retention.
- Browser: teacher login, shared library, studio creation/save and cleanup, mobile overflow, question/option rendering and PDF export.
- Full MySQL suite: 128 passed, 2 existing failures in RegistrationTest (`test_registration_screen_can_be_rendered`, `test_new_users_can_register`).

An additional SQLite full-suite check reproduced existing compatibility failures: `PastPapersRepositoryTest::test_show_renders_all_questions_for_one_paper` uses MySQL FIELD(); `DatabaseFirstPastPaperTest::test_find_reusable_returns_existing_review_collection` and `PastPaperCollectorFeatureTest::test_store_reuses_existing_matching_paper` omit required ai_import_questions.source. The two registration failures also occur there. These are outside the diagram feature.
