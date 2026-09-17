<?php

namespace App\Http\Controllers\PaperBuilder;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Institution;
use App\Models\SavedPaper;
use App\Models\Subject;
use App\Models\Topic;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WizardController extends Controller
{
    /** Grade numbers currently available for paper generation. */
    private const ACTIVE_GRADE_NUMBERS = [9];

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
            ->get(['id', 'number', 'label_en', 'label_ur'])
            ->map(function (Grade $grade) use ($allowedNumbers) {
                $contentReady = in_array($grade->number, self::ACTIVE_GRADE_NUMBERS, true);
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

        if (! $grade || ! in_array($grade->number, self::ACTIVE_GRADE_NUMBERS, true)) {
            return redirect()->route('builder');
        }

        if ($user->hasRole('teacher')) {
            $allowedGrades = $user->teacherPermission?->allowed_grades;
            if (is_array($allowedGrades) && count($allowedGrades) && ! in_array($grade->number, $allowedGrades, true)) {
                abort(403);
            }
        }

        $subjectsQuery = Subject::query()
            ->where('grade_id', $grade->id)
            ->orderBy('sort_order');

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

        if (! $grade || ! $subject || ! in_array($grade->number, self::ACTIVE_GRADE_NUMBERS, true)) {
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

        $chapters = Chapter::query()
            ->where('subject_id', $subject->id)
            ->with(['topics' => fn ($q) => $q->orderBy('sort_order')->orderBy('code')])
            ->orderBy('number')
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
        $gradesQuery = Grade::query()->orderBy('number');

        if ($user->hasRole('teacher')) {
            $perms = $user->teacherPermission;
            $allowed = $perms?->allowed_grades ?? null;
            if (is_array($allowed) && count($allowed)) {
                $gradesQuery->whereIn('number', $allowed);
            }
        }

        $gradesQuery->whereIn('number', self::ACTIVE_GRADE_NUMBERS);

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

        $subjectsQuery = Subject::query()
            ->where('grade_id', $selectedGradeId)
            ->orderBy('sort_order');

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

        $validChapterIds = Chapter::query()
            ->where('subject_id', $selectedSubjectId)
            ->whereIn('id', $chapterIds ?: [0])
            ->pluck('id')
            ->all();

        if (! $validChapterIds) {
            return redirect()->route('builder.chapters', [
                'grade' => $selectedGradeId,
                'subject' => $selectedSubjectId,
            ]);
        }

        $topicsExist = Topic::query()->whereIn('chapter_id', $validChapterIds)->exists();

        // Topics UI is hidden on the institute side; auto-include all topics for selected chapters.
        $validTopicIds = [];
        if ($topicIds) {
            $validTopicIds = Topic::query()
                ->whereIn('chapter_id', $validChapterIds)
                ->whereIn('id', $topicIds)
                ->pluck('id')
                ->all();
        }

        if ($topicsExist && ! $validTopicIds) {
            $validTopicIds = Topic::query()
                ->whereIn('chapter_id', $validChapterIds)
                ->pluck('id')
                ->all();
        }

        $institution = $user->institution_id
            ? Institution::find($user->institution_id)
            : null;

        $chapters = Chapter::query()
            ->where('subject_id', $selectedSubjectId)
            ->whereIn('id', $validChapterIds)
            ->with(['topics' => fn ($q) => $q->orderBy('sort_order')->orderBy('code')])
            ->orderBy('number')
            ->get(['id', 'number', 'title_en', 'title_ur', 'subject_id']);

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
