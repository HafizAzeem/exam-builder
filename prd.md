# Smart Exam Builder — Product Requirements Document

> **Stack:** Laravel + Inertia.js + Vue  + MySQL + Puppeteer (PDF)
> **Version:** 1.0 | **Status:** Ready for Development
> **Audience:** Cursor AI / Developer Reference

---

## Table of Contents

1. [Product Overview](#1-product-overview)
2. [Users, Roles & Permissions](#2-users-roles--permissions)
3. [Functional Requirements](#3-functional-requirements)
   - [FR-01 Authentication & Multi-Tenancy](#fr-01-authentication--multi-tenancy)
   - [FR-02 Institute Profile Management](#fr-02-institute-profile-management)
   - [FR-03 Question Bank & Curriculum Hierarchy](#fr-03-question-bank--curriculum-hierarchy)
   - [FR-04 Paper Generation Wizard](#fr-04-paper-generation-wizard)
   - [FR-05 Paper Layout & Print Editor](#fr-05-paper-layout--print-editor)
   - [FR-06 Printing & PDF Export](#fr-06-printing--pdf-export)
   - [FR-07 Dashboard & Paper History](#fr-07-dashboard--paper-history)
   - [FR-08 Activity Logs & Security](#fr-08-activity-logs--security)
4. [Database Schema](#4-database-schema)
5. [Laravel + Inertia.js Architecture](#5-laravel--livewire-architecture)
6. [Print CSS Strategy](#6-print-css-strategy)
7. [Non-Functional Requirements](#7-non-functional-requirements)
8. [Out of Scope (v1.0)](#8-out-of-scope-v10)
9. [Appendix — Question Type Reference](#9-appendix--question-type-reference)
10. [Screenshots — Reference UI](#10-screenshots--reference-ui)

---

## 1. Product Overview

**Smart Exam Builder** is a multi-tenant, web-based SaaS platform that enables educational institutions to generate, customize, and print professional exam papers for Grades 1–12. The system draws from a structured, curriculum-aligned question bank covering all major subjects, chapters, and question types — including past board papers from provincial boards across Pakistan.

### 1.1 Problem Statement

Educators in Pakistan spend significant manual effort assembling exam papers from textbooks, past papers, and supplementary materials. The process is error-prone, time-consuming, and lacks standardization across teachers within the same institution. There is no centralized, digital-first system tailored to the Pakistani curriculum with bilingual (Urdu/English) support.

### 1.2 Solution

Smart Exam Builder provides:

- A structured, searchable database of 100% curriculum-aligned questions (Grades 1–12)
- A step-by-step paper builder with live preview and print-ready output
- Multi-tenant sub-accounts for institutions to manage multiple teacher logins
- Automated features: OMR bubble sheets, answer keys, watermarks, dual-medium (English/Urdu) layouts
- Full audit logging: paper history, login sessions, and IP tracking

### 1.3 Key Metrics

| Metric | Target (6 Months) | Target (12 Months) |
|---|---|---|
| Active Institutions | 50+ | 200+ |
| Papers Generated / Month | 500+ | 5,000+ |
| Teacher Sub-Accounts | 150+ | 800+ |
| Avg. Paper Generation Time | < 5 minutes | < 3 minutes |
| Uptime SLA | 99% | 99.5% |

---

## 2. Users, Roles & Permissions(spatie roles permission package)

The platform uses a three-tier role model.

| Role | Login Type | Key Capabilities |
|---|---|---|
| **Super Admin** | System-level | Manage all institutions, licensing, global question bank updates |
| **Institution Admin** | Institution account | Manage teachers, set access rights, view all papers & logs, update institute profile/logo |
| **Teacher** | Sub-account (phone/email) | Create & print papers within assigned classes/subjects, view personal paper history |

### 2.1 Teacher Permission Scope

Institution Admins can restrict each teacher's access at a granular level:

- Allowed grade levels (e.g., only Class 9 and 10)
- Allowed question categories (exercise only, or include past papers)
- Allowed subjects per grade
- Maximum papers per day/month (optional rate limit)
- Cannot delete or modify papers created by other teachers

---

## 3. Functional Requirements

---

### FR-01: Authentication & Multi-Tenancy

#### Authentication

- Login via phone number or email + password (bcrypt hashed)
- Laravel Fortify for auth scaffolding; session-based using Inertia.js (server-driven SPA, no separate API layer needed)
- Remember-me cookie, 30-day expiry
- IP address and user-agent logged on every login session
- Failed login attempts logged; account lockout after 5 failures within 15 minutes

#### Multi-Tenancy

- Each institution is a separate tenant with isolated data
- **Single-database multi-tenancy** using `institution_id` foreign keys on all relevant tables
- Middleware pipeline checks on every request:
  1. Valid session (`auth` middleware)
  2. Institution licence not expired (`CheckLicenceExpiry` middleware)
  3. User has permission for the requested resource (`ScopePermissions` middleware)
- Licence expiry blocks paper generation endpoints but allows read-only access to saved papers

---

### FR-02: Institute Profile Management

- Admin can update: institute name, owner name, phone, address, city
- Admin can upload a logo (PNG/JPG, max 2MB) → stored at `storage/app/public/logos/{institution_id}/`
- Logo and institute name appear in the printed paper header
- Profile changes apply to all future papers; previously saved papers retain a snapshot of the logo/name at time of creation

---

### FR-03: Question Bank & Curriculum Hierarchy

#### Curriculum Data Structure

The question bank follows a strict five-level hierarchy:

```
Grade (1–12)
  └── Subject (e.g., Mathematics, Urdu, Physics, Tarjuma-tul-Quran)
        └── Chapter (number + title EN/UR)
              └── Question (text EN/UR, type, source, image)
                    └── MCQ Options / Sub-Parts
```

#### Question Types

| Type | DB Enum | Description |
|---|---|---|
| Multiple Choice | `mcq` | 4 options (A–D), single correct answer |
| Short Answer | `short` | Text response, optional blank lines below |
| Long / Essay | `long` | May have sub-parts: a/b or alif/bay |
| Fill in the Blank | `fill` | Underline blanks auto-inserted |
| True / False | `truefalse` | T/F checkbox column |

#### Question Sources

| Source | DB Enum | Description |
|---|---|---|
| Textbook Exercise | `exercise` | From official textbook exercises |
| Additional | `additional` | Supplementary questions |
| Past Paper | `past_paper` | Linked to board name, year, session |

#### Bi-Directional Text (English + Urdu)

- All question text fields have `_en` and `_ur` variants
- Urdu stored as **UTF-8, Nastaliq-compatible** text (`utf8mb4_unicode_ci` collation)
- RTL rendering applied via CSS: `direction: rtl; font-family: 'Jameel Noori Nastaleeq'`
- Dual-medium questions render both languages side by side or stacked (user-configurable)

---

### FR-04: Paper Generation Wizard

The paper builder is a **multi-step Livewire wizard**. All state is managed client-side via Vue 3 reactive props passed from Laravel controllers via Inertia (no full page refreshes).

---

#### Step 1 — Configuration

1. Select **Grade** (1–12) — dropdown from DB
2. Select **Subject** — filtered by grade
3. Select **Chapter(s)** — multi-select checkboxes, filtered by subject
4. Select **Question Source(s)**: Exercise, Additional, Past Papers (multi-select)
5. Toggle **Dual Medium** (English + Urdu) — enables bilingual rendering
6. If Past Papers selected: toggle **Show Board Name & Year** label alongside each question

---

#### Step 2 — Question Selection

**Manual Mode:**
- Question grid shows all matching questions with text preview
- User clicks to select / deselect individual questions
- Selected question IDs pushed to reactive `$selected_ids[]` Livewire property

**Random Mode:**
- User inputs count per type (e.g., 10 MCQs, 5 Short, 2 Long)
- Backend uses `->inRandomOrder()->limit($n)` per type
- **Re-randomise button** re-queries with a fresh random seed (cache key invalidated)
- Selection counter shows: `X MCQs | Y Short | Z Long` selected

**Additional:**
- Questions with images display a thumbnail preview
- Past-paper questions show board name + year badge when that toggle is on

---

#### Step 3 — Paper Settings

| Setting | Input Type | Notes |
|---|---|---|
| Paper / Exam Title | Text | Appears in header |
| Class, Subject, Time, Total Marks | Text fields | Header metadata |
| Section headings | Auto-generated | Section A: MCQs, Section B: Short Questions, etc. |
| Blank lines per question | Numeric (0–10) | For short/long answer types |
| Questions per line | Select: 1, 2, 3, 5 | For MCQ and short types |
| Enable OMR Bubble Sheet | Toggle | Auto-generates bubbles matching MCQ count |
| Enable Teacher Answer Key | Toggle | Separate printable answer sheet |
| Enable Watermark | Toggle | Text or image watermark |

---

#### Step 4 — Save & Preview

- **Save Paper** — persists to `saved_papers` table with full `config_snapshot` (JSON of all question IDs + settings)
- **Emergency Print** — print without saving; limited layout options available
- Live Vue 3 preview component updates reactively as settings change (via `watch` on layout state)

---

### FR-05: Paper Layout & Print Editor

After saving, the paper enters the **full layout editor** — a Inertia + Vue 3 component with a live-rendered print preview panel on the right and controls panel on the left.

---

#### 5.1 Header Layouts

- **7 pre-designed header templates** to choose from
- All templates support: institute logo, institute name, exam title, class/subject, time, total marks
- Logo rendered at ~80px height in the header area

> 📸 **[SCREENSHOT PLACEHOLDER — Header Layout Selector]**

---

#### 5.2 Typography Controls

| Control | Options |
|---|---|
| Font Family | Jameel Noori Nastaleeq, Noto Nastaliq Urdu, Arial, Times New Roman |
| Font Size | 10pt–18pt (slider + numeric input) |
| Font Colour | Colour picker (hex input + colour wheel) — for colour printers |
| Line Height | 1.0–3.0, decimal steps (e.g. `0.5`, `1.2`); press **Enter** to apply |

---

#### 5.3 Column & Spacing Layout

- **Single-column** (default)
- **Dual-column** side-by-side → CSS: `column-count: 2; column-gap: 20px;`
  - System recommends switching printer to **Landscape** mode when dual-column is enabled
- **Questions per row** (configurable per section):
  - 2 per row → `width: 50%; display: flex; flex-wrap: wrap;`
  - 3 per row → `width: 33.33%`
  - 5 per row → `width: 20%`
- **Page margins** — top / bottom / left / right inputs in mm

> 📸 **[SCREENSHOT PLACEHOLDER — Layout Editor Panel]**

---

#### 5.4 Watermark

- **Text watermark:** input text, opacity (5%–30%), rotation (0°–60°), colour
- **Image watermark:** upload PNG/JPG, control opacity and position
- Rendered as absolutely-positioned overlay: `position: fixed; z-index: -1; opacity: var(--wm-opacity); transform: rotate(var(--wm-angle)); pointer-events: none;`

---

#### 5.5 OMR Bubble Sheet

- Auto-generated based on MCQ count in the paper
- Each row: Question Number + four circles (A, B, C, D)
- Dynamic: if paper has 15 MCQs → 15 rows of bubbles are generated
- Can be printed as part of the paper OR as a separate page

> 📸 **[SCREENSHOT PLACEHOLDER — OMR Bubble Sheet Preview]**

---

#### 5.6 Teacher Answer Key

- Separate printable page listing: `1: A  2: C  3: B  4: D ...`
- Compiled from `mcq_options.correct_option` for all selected MCQs
- Displayed in grid format: 5 answers per row

---

### FR-06: Printing & PDF Export

- **Primary:** Browser print dialog via `window.print()` with dedicated `@media print` stylesheet
- **Paper size:** A4 or Letter
- **Orientation:** Portrait / Landscape toggle (recommended for dual-column)
- **Page scale:** 50%–120% input field for fitting content
- **Server-side PDF** via Puppeteer (headless Chrome):
  1. User clicks "Download PDF" → dispatches `GeneratePdfJob` to queue
  2. Puppeteer loads the paper's print URL (signed, expiring token) in headless Chrome
  3. Captures PDF with print CSS applied
  4. Saves to `storage/app/public/papers/{institution_id}/{paper_id}.pdf`
  5. Job fires `PdfReadyEvent`; Vue component listens via Laravel Echo + broadcasting
  6. Download link served via `Storage::temporaryUrl()` with 1-hour expiry

---

### FR-07: Dashboard & Paper History

#### Paper List

- All saved papers for the institution (Admin view) or teacher (Teacher view)
- Columns: Paper Title | Subject | Grade | Created By | Date/Time | Actions
- Actions: **View · Edit Layout · Reprint · Duplicate · Delete**
- Search/filter: by date range, grade, subject, teacher name, keywords in title
- Admin sees all teachers' papers; teachers see only their own

#### Past Papers Repository

- Dedicated section for all past board exam papers in the DB
- Filter by: Board, Year, Session (Morning/Evening), Grade, Subject
- **Quick Print:** select a past paper and print directly — no wizard required

> 📸 **[SCREENSHOT PLACEHOLDER — Dashboard / Paper History]**

---

### FR-08: Activity Logs & Security

- Every **login event** logged: `user_id`, timestamp, IP address, user-agent, success/failure
- Every **paper generation** logged: `user_id`, `paper_id`, timestamp, configuration snapshot
- Admin can view logs filtered by date, user, or action type
- Logs are **read-only** — cannot be edited or deleted by any user role

> 📸 **[SCREENSHOT PLACEHOLDER — Activity Log / Login History View]**

---

## 4. Database Schema

All tables use:
- Engine: **InnoDB**
- Collation: **utf8mb4_unicode_ci**
- Standard Laravel `created_at` / `updated_at` timestamps on all tables

### 4.1 Tables Overview

```
institutions
users
teacher_permissions
grades
subjects
chapters
questions
mcq_options
past_paper_tags
saved_papers
activity_logs
login_sessions
```

---

### 4.2 Schema Detail

#### `institutions`
```sql
id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
name             VARCHAR(255)
logo_path        VARCHAR(500) NULLABLE
address          TEXT NULLABLE
phone            VARCHAR(20) NULLABLE
owner_name       VARCHAR(255) NULLABLE
city             VARCHAR(100) NULLABLE
expiry_date      DATE
license_type     ENUM('full', 'partial')
created_at, updated_at
```

#### `users`
```sql
id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
institution_id   BIGINT UNSIGNED FK → institutions.id
name             VARCHAR(255)
phone            VARCHAR(20) NULLABLE UNIQUE
email            VARCHAR(255) NULLABLE UNIQUE
password         VARCHAR(255)  -- bcrypt
role             ENUM('admin', 'teacher')
is_active        BOOLEAN DEFAULT TRUE
created_at, updated_at
```

#### `teacher_permissions`
```sql
id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id          BIGINT UNSIGNED FK → users.id
allowed_grades   JSON NULLABLE  -- e.g. [9, 10, 11]
allowed_subjects JSON NULLABLE  -- e.g. [3, 7]
allowed_categories JSON NULLABLE -- e.g. ["exercise", "past_paper"]
created_at, updated_at
```

#### `grades`
```sql
id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
number           TINYINT  -- 1 to 12
label_en         VARCHAR(50)  -- e.g. "Class 9"
label_ur         VARCHAR(50)  -- Urdu label
```

#### `subjects`
```sql
id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
grade_id         BIGINT UNSIGNED FK → grades.id
name_en          VARCHAR(255)
name_ur          VARCHAR(255)
sort_order       TINYINT DEFAULT 0
```

#### `chapters`
```sql
id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
subject_id       BIGINT UNSIGNED FK → subjects.id
number           TINYINT
title_en         VARCHAR(500)
title_ur         VARCHAR(500)
```

#### `questions`
```sql
id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
chapter_id          BIGINT UNSIGNED FK → chapters.id
type                ENUM('mcq', 'short', 'long', 'fill', 'truefalse')
source              ENUM('exercise', 'additional', 'past_paper')
text_en             TEXT NULLABLE
text_ur             TEXT NULLABLE
image_path          VARCHAR(500) NULLABLE
has_parts           BOOLEAN DEFAULT FALSE
parent_question_id  BIGINT UNSIGNED NULLABLE FK → questions.id  -- self-ref for sub-parts
is_active           BOOLEAN DEFAULT TRUE
created_at, updated_at
```

#### `mcq_options`
```sql
id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
question_id      BIGINT UNSIGNED FK → questions.id
option_a_en      TEXT
option_a_ur      TEXT NULLABLE
option_b_en      TEXT
option_b_ur      TEXT NULLABLE
option_c_en      TEXT
option_c_ur      TEXT NULLABLE
option_d_en      TEXT
option_d_ur      TEXT NULLABLE
correct_option   ENUM('a', 'b', 'c', 'd')
```

#### `past_paper_tags`
```sql
id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
question_id      BIGINT UNSIGNED FK → questions.id
board_name       VARCHAR(100)  -- e.g. "Lahore Board"
year             YEAR
session          ENUM('morning', 'evening') NULLABLE
```

#### `saved_papers`
```sql
id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
institution_id    BIGINT UNSIGNED FK → institutions.id
user_id           BIGINT UNSIGNED FK → users.id
title             VARCHAR(500)
config_snapshot   JSON   -- full question IDs + wizard settings
layout_snapshot   JSON   -- layout editor settings
status            ENUM('draft', 'saved', 'archived') DEFAULT 'saved'
created_at, updated_at
```

#### `activity_logs`
```sql
id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
institution_id   BIGINT UNSIGNED FK → institutions.id
user_id          BIGINT UNSIGNED FK → users.id
action           VARCHAR(100)  -- e.g. 'paper.created', 'paper.printed'
meta             JSON NULLABLE  -- additional context
ip_address       VARCHAR(45)
user_agent       VARCHAR(500) NULLABLE
created_at       TIMESTAMP  -- no updated_at; append-only
```

#### `login_sessions`
```sql
id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id          BIGINT UNSIGNED FK → users.id
ip_address       VARCHAR(45)
user_agent       VARCHAR(500) NULLABLE
success          BOOLEAN
logged_in_at     TIMESTAMP
logged_out_at    TIMESTAMP NULLABLE
```

---

## 5. Laravel + Inertia.js Architecture

### 5.1 Directory Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── AuthenticatedSessionController.php
│   │   ├── PaperBuilder/
│   │   │   ├── WizardController.php          # Serves Inertia wizard pages + handles saves
│   │   │   └── QuestionSelectorController.php # API endpoints for question fetch/random
│   │   ├── LayoutEditor/
│   │   │   └── LayoutEditorController.php    # Serves editor page + saves layout_snapshot
│   │   ├── Dashboard/
│   │   │   ├── PaperController.php
│   │   │   └── ActivityLogController.php
│   │   ├── Admin/
│   │   │   ├── TeacherController.php
│   │   │   └── ProfileController.php
│   │   └── PastPaperController.php
│   └── Middleware/
│       ├── TenantResolver.php          # Binds Institution model to request
│       ├── CheckLicenceExpiry.php      # Blocks if institution.expiry_date < today
│       ├── ScopeTeacherPermissions.php # Filters grades/subjects per teacher
│       └── LogActivity.php            # Appends to activity_logs after request
├── Models/
│   ├── Institution.php
│   ├── User.php
│   ├── Grade.php
│   ├── Subject.php
│   ├── Chapter.php
│   ├── Question.php
│   ├── McqOption.php
│   ├── PastPaperTag.php
│   ├── SavedPaper.php
│   ├── ActivityLog.php
│   └── LoginSession.php
├── Services/
│   ├── QuestionBankService.php         # All question fetch/random logic
│   ├── PaperExportService.php          # Paper HTML rendering for print/PDF
│   └── BubbleSheetService.php          # OMR bubble sheet generator
└── Jobs/
    └── GeneratePdfJob.php              # Puppeteer PDF generation queue job

resources/
├── js/
│   ├── app.js                          # Inertia + Vue 3 bootstrap
│   ├── Pages/
│   │   ├── Auth/
│   │   │   └── Login.vue
│   │   ├── PaperBuilder/
│   │   │   ├── Wizard.vue              # Orchestrates 4-step wizard (step state via ref)
│   │   │   ├── StepConfig.vue          # Step 1 — Grade/Subject/Chapter selection
│   │   │   ├── StepQuestions.vue       # Step 2 — Manual + random question selection
│   │   │   ├── StepSettings.vue        # Step 3 — Paper settings (OMR, lines, layout)
│   │   │   └── StepPreview.vue         # Step 4 — Save & live preview
│   │   ├── LayoutEditor/
│   │   │   └── Editor.vue              # Full layout editor (toolbar + live preview panel)
│   │   ├── Dashboard/
│   │   │   ├── Index.vue               # Paper list with filters
│   │   │   └── ActivityLog.vue
│   │   ├── Admin/
│   │   │   ├── Teachers.vue
│   │   │   └── Profile.vue
│   │   └── PastPapers/
│   │       └── Index.vue
│   └── Components/
│       ├── PaperPreview.vue            # Reusable print-preview canvas
│       ├── QuestionGrid.vue            # Question selector grid
│       ├── OmrSheet.vue                # OMR bubble sheet renderer
│       ├── AnswerKey.vue               # Teacher answer key renderer
│       └── Watermark.vue              # Watermark overlay component
├── views/
│   └── app.blade.php                   # Single Inertia root blade template
├── css/
│   ├── app.css
│   └── print.css                       # Dedicated @media print stylesheet

database/
├── migrations/
└── seeders/
    ├── GradeSeeder.php
    ├── SubjectSeeder.php
    └── QuestionBankImportSeeder.php    # CSV/Excel import for question data
```

---

### 5.2 Key Livewire Components

#### `WizardComponent`
- Manages `currentStep (Vue ref)` (1–4) and all wizard state
- Holds `$selectedGrade`, `$selectedSubject`, `$selectedChapters[]`, `$selectedSources[]`
- Dispatches Livewire events to child components on step changes
- On Step 4: calls `SavedPaper::createFromWizard($this->state)`

#### `QuestionSelectorComponent`
- Receives chapter/source filters from parent via `Inertia shared props`
- **Manual mode:** renders question grid, tracks `$selectedIds[]`
- **Random mode:** calls `QuestionBankService::randomFetch()` on load and on `reRandomise()` action
- Emits `questionsSelected` event with final array up to `WizardComponent`

#### `PreviewComponent` (Layout Editor)
- Receives `$savedPaper` and `$layoutSettings`
- Re-renders the paper HTML on every `$layoutSettings` change (debounced 300ms)
- Injects inline CSS variables: `--line-height`, `--font-size`, `--font-family`, `--font-color`, `--wm-opacity`, `--print-margin-*`

#### `ToolbarComponent`
- All layout controls bound to `$layoutSettings` via `v-model`
- Header template selector: 7 options, renders thumbnail previews
- Typography panel, column layout toggles, watermark uploader, OMR/answer key toggles

---

### 5.3 Middleware Pipeline (Route Groups)

```php
// routes/web.php

Route::middleware(['web', 'auth', 'tenant', 'check.licence'])->group(function () {

    // Admin-only routes
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/teachers', ...);
        Route::get('/admin/logs', ...);
        Route::get('/admin/profile', ...);
    });

    // Teacher + Admin routes
    Route::get('/builder', WizardComponent::class);
    Route::get('/editor/{paper}', LayoutEditorComponent::class);
    Route::get('/dashboard', PaperListComponent::class);
    Route::get('/past-papers', PastPapersComponent::class);
});
```

---

### 5.4 QuestionBankService

```php
class QuestionBankService
{
    // Manual fetch: returns paginated question list for the selector grid
    public function manualFetch(array $chapterIds, string $type, array $sources): LengthAwarePaginator

    // Random fetch: e.g. ['mcq' => 10, 'short' => 5, 'long' => 2]
    // Uses ->inRandomOrder()->limit($n) per type
    // Results cached per session key for 10 minutes
    public function randomFetch(array $chapterIds, array $config, string $cacheKey): Collection

    // Invalidate random cache (called on re-randomise button)
    public function invalidateCache(string $cacheKey): void
}
```

---

### 5.5 PDF Generation Flow (Puppeteer via Queue)

```
User clicks "Download PDF"
    → Livewire dispatches GeneratePdfJob to queue
    → Job calls: node puppeteer/generate.js --url="{signed_paper_url}" --output="{path}"
    → Puppeteer loads URL in headless Chrome with print CSS
    → Saves PDF to storage/app/public/papers/{institution_id}/{paper_id}.pdf
    → Job fires PdfReadyEvent
    → Laravel Echo listener catches event → shows download button
    → File served via Storage::temporaryUrl() (1-hour expiry)
```

---

## 6. Print CSS Strategy

File: `resources/css/print.css`

```css
@media print {
    /* Hide all UI chrome */
    .sidebar, .toolbar, .navbar, .btn, .livewire-loading { display: none !important; }

    /* Full-width paper preview */
    .paper-preview { width: 100%; margin: 0; padding: 0; }

    /* Page setup — bound to user's margin settings via CSS vars */
    @page {
        margin: var(--print-margin-top) var(--print-margin-right)
                var(--print-margin-bottom) var(--print-margin-left);
    }

    /* Section page breaks */
    .section-break { page-break-before: always; }

    /* Prevent orphaned questions */
    .question-block { page-break-inside: avoid; orphans: 3; widows: 3; }

    /* Dual-column mode */
    .questions-container.dual-col { column-count: 2; column-gap: 20px; }

    /* Watermark */
    .watermark-layer {
        position: fixed;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%) rotate(var(--wm-angle));
        opacity: var(--wm-opacity);
        z-index: -1;
        pointer-events: none;
    }

    /* OMR sheet on its own page */
    .omr-sheet { page-break-before: always; }

    /* RTL Urdu sections */
    .question-ur { direction: rtl; text-align: right; }

    /* Embed Urdu font */
    @font-face {
        font-family: 'Jameel Noori Nastaleeq';
        src: url('/fonts/JameelNooriNastaleeq.woff2') format('woff2');
    }
}
```

---

## 7. Non-Functional Requirements

| Category | Requirement |
|---|---|
| **Performance** | Question query response < 300ms for up to 50,000 questions; Inertia page transition < 300ms |
| **Scalability** | Support 100 concurrent users per institution; queue workers for PDF jobs |
| **Security** | CSRF on all forms; XSS prevention via Blade auto-escaping; Eloquent ORM (no raw SQL); bcrypt passwords; signed URLs for PDF downloads |
| **Availability** | 99% uptime SLA; DB backups every 6 hours |
| **Browser Support** | Chrome 110+, Firefox 115+, Edge 110+, Safari 16+; mobile-responsive dashboard |
| **Localisation** | UI strings in English and Urdu; RTL layout toggle for Urdu UI mode |
| **Data Retention** | Paper history: 2 years; activity logs: 1 year; auto-purge via Laravel scheduled command |
| **Accessibility** | WCAG 2.1 AA for admin and teacher UI; focus management in Livewire modals |

---

## 8. Out of Scope (v1.0)

| Out of Scope for v1.0 | Planned for v2.0 |
|---|---|
| Online student exam portal | Student login + digital MCQ submission |
| AI-generated questions | GPT-4 integration for question suggestions |
| Automatic OMR scanning | Camera/scanner input + auto-grading |
| Payment gateway / subscription billing | Stripe / JazzCash licence management |
| Mobile native app | Progressive Web App (PWA) wrapper |
| Result analytics / grade reports | Class performance dashboard |

---

## 9. Appendix — Question Type Reference

| Type | DB Enum | Layout Options | Auto Features |
|---|---|---|---|
| Multiple Choice | `mcq` | 1, 2, 4, or 5 per row | OMR bubbles, answer key |
| Short Answer | `short` | 1 or 2 per row, blank lines below | Blank line generator |
| Long / Essay | `long` | 1 per row, sub-parts a/b | Sub-part labels (a/b or alif/bay) |
| Fill in the Blank | `fill` | 1 or 2 per row | Underline spaces auto-inserted |
| True / False | `truefalse` | 1, 2, or 3 per row | T/F checkbox column |

---

## 10. Screenshots — Reference UI

> Add your screenshots below. Each screenshot is linked to a feature described in this PRD.
> Recommended format: save images to a `/screenshots/` folder alongside this `.md` file.

---

### 10.1 Paper Generation — Grade / Subject / Chapter Selection

> 📸 *Add screenshot here*

```md
![Grade & Subject Selection](./screenshots/01_grade_subject_selection.png)
```

**What to show:** The wizard Step 1 — dropdowns for Grade, Subject, Chapter multi-select, source checkboxes, dual-medium toggle.

---

### 10.2 Question Selection Grid (Manual Mode)

> 📸 *Add screenshot here*

```md
![Question Selection Grid](./screenshots/02_question_selection_grid.png)
```

**What to show:** The question list/grid with select/deselect, question type badges, past-paper year labels.

---

### 10.3 Random Question Configuration

> 📸 *Add screenshot here*

```md
![Random Question Config](./screenshots/03_random_question_config.png)
```

**What to show:** Input fields for MCQ count, short count, long count, re-randomise button.

---

### 10.4 Layout Editor — Full View

> 📸 *Add screenshot here*

```md
![Layout Editor](./screenshots/04_layout_editor.png)
```

**What to show:** Left panel (controls) + right panel (live preview). Highlight line height, font controls, header selector.

---

### 10.5 Header Template Selector

> 📸 *Add screenshot here*

```md
![Header Templates](./screenshots/05_header_templates.png)
```

**What to show:** The 7 header layout options with thumbnails.

---

### 10.6 OMR Bubble Sheet

> 📸 *Add screenshot here*

```md
![OMR Bubble Sheet](./screenshots/06_omr_bubble_sheet.png)
```

**What to show:** Auto-generated OMR sheet with A/B/C/D circles per question.

---

### 10.7 Dual-Medium (English + Urdu) Paper Preview

> 📸 *Add screenshot here*

```md
![Dual Medium Preview](./screenshots/07_dual_medium_preview.png)
```

**What to show:** A paper with both English and Urdu columns side by side.

---

### 10.8 Dashboard / Paper History

> 📸 *Idea screen shoot of refrence software for your understanding in this project public directory *
public\refrence-screen-shoot



*Smart Exam Builder PRD — Laravel + Inertia.js — v1.0 — May 2026 — Confidential*