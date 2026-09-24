<?php

namespace App\Http\Controllers\PaperBuilder;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Institution;
use App\Models\SavedPaper;
use App\Models\Subject;
use App\Support\ActivityLogger;
use App\Support\CurriculumLookup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WizardController extends Controller
{
    public function classes(Request $request): Response
    {
        $user = $request->user();

        $allowedNumbers = null;
        if ($user->hasRole('teacher')) {
            $allowed = $user->teacherPermission?->allowed_grades;
            if (is_array($allowed) && count($allowed)) {
                $allowedNumbers = $allowed;
            }
        }

        $grades = Grade::query()
            ->whereBetween('number', [5, 12])
            ->orderByDesc('number')
            ->get(['id', 'number', 'label_en', 'label_ur', 'is_active'])
            ->map(function (Grade $grade) use ($allowedNumbers) {
                $contentReady = $grade->is_active;
                $permissionOk = $allowedNumbers === null || in_array($grade->number, $allowedNumbers, true);

                return [
                    'id' => $grade->id,
                    'number' => $grade->number,
                    'label_en' => $grade->label_en,
                    'label_ur' => $grade->label_ur,
                    'active' => $contentReady && $permissionOk,
                    'coming_soon' => ! $contentReady,
                ];
            });

        return Inertia::render('PaperBuilder/Classes', [
            'grades' => $grades,
        ]);
    }

    public function subjects(Request $request): Response
    {
        $user = $request->user();
        $gradeId = $request->integer('grade');

        $grade = Grade::query()->find($gradeId);

        if (! $grade || ! $grade->is_active) {
            return redirect()->route('builder');
        }

        if ($user->hasRole('teacher')) {
            $allowedGrades = $user->teacherPermission?->allowed_grades;
            if (is_array($allowedGrades) && count($allowedGrades) && ! in_array($grade->number, $allowedGrades, true)) {
                abort(403);
            }
        }

        $subjectsQuery = CurriculumLookup::subjects($grade->id);

        if ($user->hasRole('teacher')) {
            $allowedSubjects = $user->teacherPermission?->allowed_subjects;
            if (is_array($allowedSubjects) && count($allowedSubjects)) {
                $subjectsQuery->whereIn('id', $allowedSubjects);
            }
        }

        $subjects = $subjectsQuery->get(['id', 'name_en', 'name_ur', 'grade_id']);

        return Inertia::render('PaperBuilder/Subjects', [
            'grade' => [
                'id' => $grade->id,
                'number' => $grade->number,
                'label_en' => $grade->label_en,
                'label_ur' => $grade->label_ur,
            ],
            'subjects' => $subjects,
        ]);
    }

    public function chapters(Request $request): Response
    {
        $user = $request->user();
        $gradeId = $request->integer('grade');
        $subjectId = $request->integer('subject');

        $grade = Grade::query()->find($gradeId);
        $subject = Subject::query()->where('id', $subjectId)->where('grade_id', $gradeId)->first();

        if (! $grade || ! $subject || ! $grade->is_active || ! $subject->is_active) {
            return redirect()->route('builder');
        }

        if ($user->hasRole('teacher')) {
            $allowedGrades = $user->teacherPermission?->allowed_grades;
            if (is_array($allowedGrades) && count($allowedGrades) && ! in_array($grade->number, $allowedGrades, true)) {
                abort(403);
            }

            $allowedSubjects = $user->teacherPermission?->allowed_subjects;
            if (is_array($allowedSubjects) && count($allowedSubjects) && ! in_array($subject->id, $allowedSubjects, true)) {
                abort(403);
            }
        }

        $chapters = CurriculumLookup::chapters($subject->id)
            ->with(['topics' => fn ($q) => $q->active()->orderBy('sort_order')->orderBy('code')])
            ->get(['id', 'number', 'title_en', 'title_ur', 'subject_id']);

        return Inertia::render('PaperBuilder/Chapters', [
            'grade' => [
                'id' => $grade->id,
                'number' => $grade->number,
                'label_en' => $grade->label_en,
            ],
            'subject' => [
                'id' => $subject->id,
                'name_en' => $subject->name_en,
                'name_ur' => $subject->name_ur,
            ],
            'chapters' => $chapters,
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $gradesQuery = CurriculumLookup::grades();

        if ($user->hasRole('teacher')) {
            $perms = $user->teacherPermission;
            $allowed = $perms?->allowed_grades ?? null;
            if (is_array($allowed) && count($allowed)) {
                $gradesQuery->whereIn('number', $allowed);
            }
        }

        $grades = $gradesQuery->get(['id', 'number', 'label_en', 'label_ur']);

        $selectedGradeId = $request->integer('grade') ?: null;
        if ($selectedGradeId && ! $grades->contains('id', $selectedGradeId)) {
            $selectedGradeId = null;
        }

        if (! $selectedGradeId && $grades->count() === 1) {
            $selectedGradeId = $grades->first()->id;
        }

        if (! $selectedGradeId) {
            return redirect()->route('builder');
        }

        $subjectsQuery = CurriculumLookup::subjects($selectedGradeId);

        if ($user->hasRole('teacher')) {
            $allowedSubjects = $user->teacherPermission?->allowed_subjects;
            if (is_array($allowedSubjects) && count($allowedSubjects)) {
                $subjectsQuery->whereIn('id', $allowedSubjects);
            }
        }

        $subjects = $subjectsQuery->get(['id', 'name_en', 'name_ur', 'grade_id']);

        $selectedSubjectId = $request->integer('subject') ?: null;
        if ($selectedSubjectId && ! $subjects->contains('id', $selectedSubjectId)) {
            $selectedSubjectId = null;
        }

        if (! $selectedSubjectId) {
            return redirect()->route('builder.subjects', ['grade' => $selectedGradeId]);
        }

        $chapterIds = collect(explode(',', (string) $request->query('chapters', '')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $topicIds = collect(explode(',', (string) $request->query('topics', '')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $validChapterIds = CurriculumLookup::chapters($selectedSubjectId)
            ->whereIn('id', $chapterIds ?: [0])
            ->pluck('id')
            ->all();

        if (! $validChapterIds) {
            return redirect()->route('builder.chapters', [
                'grade' => $selectedGradeId,
                'subject' => $selectedSubjectId,
            ]);
        }

        $topicsExist = CurriculumLookup::topics()->whereIn('chapter_id', $validChapterIds)->exists();

        // Topics UI is hidden on the institute side; auto-include all topics for selected chapters.
        $validTopicIds = [];
        if ($topicIds) {
            $validTopicIds = CurriculumLookup::topics()
                ->whereIn('chapter_id', $validChapterIds)
                ->whereIn('id', $topicIds)
                ->pluck('id')
                ->all();
        }

        if ($topicsExist && ! $validTopicIds) {
            $validTopicIds = CurriculumLookup::topics()
                ->whereIn('chapter_id', $validChapterIds)
                ->pluck('id')
                ->all();
        }

        $institution = $user->institution_id
            ? Institution::find($user->institution_id)
            : null;

        $chapters = CurriculumLookup::chapters($selectedSubjectId)
            ->whereIn('id', $validChapterIds)
            ->with(['topics' => fn ($q) => $q->active()->orderBy('sort_order')->orderBy('code')])
            ->get(['id', 'number', 'title_en', 'title_ur', 'subject_id']);

        $selectedGrade = $grades->firstWhere('id', $selectedGradeId);

        return Inertia::render('PaperBuilder/Compose', [
            'grades' => $grades,
            'subjects' => $subjects,
            'chapters' => $chapters,
            'selectedGradeId' => $selectedGradeId,
            'selectedSubjectId' => $selectedSubjectId,
            'selectedChapterIds' => $validChapterIds,
            'selectedTopicIds' => $validTopicIds,
            'teacherPermissions' => $user->hasRole('teacher') ? ($user->teacherPermission?->toArray() ?? null) : null,
            'institution' => $institution,
            'useSystemQuestionBank' => (bool) config('exam.use_system_question_bank'),
            'pendingQuestionIds' => session()->pull('builder_pending_question_ids', []),
            'aiToolsAllowed' => in_array(
                (int) ($selectedGrade->number ?? 0),
                config('exam.teacher_ai_grade_numbers', [9, 10, 11, 12]),
                true
            ),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'config' => ['required', 'array'],
            'config.question_ids' => ['required', 'array', 'min:1'],
        ]);

        // Use the full request config — validated() only keeps explicitly ruled keys
        // (e.g. question_ids) and would strip exam_meta / layout / grade_id.
        $config = $request->input('config', []);
        $config = $this->enrichPaperConfig($config);

        $institution = Institution::find($request->user()->institution_id);

        $paper = SavedPaper::create([
            'institution_id' => $request->user()->institution_id,
            'user_id' => $request->user()->id,
            'title' => $request->input('title'),
            'institute_snapshot' => $institution ? [
                'name' => $institution->name,
                'logo_path' => $institution->logo_path,
                'address' => $institution->address,
                'city' => $institution->city,
                'phone' => $institution->phone,
            ] : null,
            'config_snapshot' => $config,
            'layout_snapshot' => $config['layout'] ?? null,
            'status' => 'saved',
        ]);

        ActivityLogger::log($request, 'paper.created', ['paper_id' => $paper->id]);

        return redirect()->route('editor.show', $paper);
    }

    /**
     * Ensure exam_meta and paper_content header always have Class / Subject / Marks.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function enrichPaperConfig(array $config): array
    {
        $examMeta = is_array($config['exam_meta'] ?? null) ? $config['exam_meta'] : [];

        if ($this->isBlank($examMeta['class'] ?? null) && ! empty($config['grade_id'])) {
            $grade = Grade::query()->find($config['grade_id']);
            if ($grade) {
                $examMeta['class'] = $grade->label_en ?: ('Class '.$grade->number);
            }
        }

        if ($this->isBlank($examMeta['subject'] ?? null) && ! empty($config['subject_id'])) {
            $subject = Subject::query()->find($config['subject_id']);
            if ($subject) {
                $examMeta['subject'] = $subject->name_en;
            }
        }

        $config['exam_meta'] = $examMeta;

        if (! empty($config['layout']) && is_array($config['layout'])) {
            $header = is_array($config['layout']['paper_content']['header'] ?? null)
                ? $config['layout']['paper_content']['header']
                : [];

            if ($this->isBlank($header['class'] ?? null)) {
                $header['class'] = $examMeta['class'] ?? '';
            }
            if ($this->isBlank($header['subject'] ?? null)) {
                $header['subject'] = $examMeta['subject'] ?? '';
            }
            if ($this->isBlank($header['marks'] ?? null)) {
                $header['marks'] = $examMeta['marks'] ?? '';
            }
            if ($this->isBlank($header['paper_time'] ?? null)) {
                $header['paper_time'] = $examMeta['time'] ?? '';
            }
            if ($this->isBlank($header['paper_type'] ?? null)) {
                $header['paper_type'] = $examMeta['paper_type'] ?? '';
            }

            $config['layout']['paper_content']['header'] = $header;
            $config['layout']['header_template'] = 1;
        }

        return $config;
    }

    protected function isBlank(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
