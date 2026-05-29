<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\QuestionBankImport;
use App\Models\Chapter;
use App\Services\QuestionImageImportService;
use App\Models\Grade;
use App\Models\McqOption;
use App\Models\PastPaperTag;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class QuestionBankController extends Controller
{
    public function index(Request $request): Response
    {
        $grades = Grade::query()->orderBy('number')->get(['id', 'number', 'label_en']);
        $subjects = Subject::query()->orderBy('name_en')->get(['id', 'name_en', 'grade_id']);

        $chaptersQuery = Chapter::query()->orderBy('number');
        if ($request->filled('subject_id')) {
            $chaptersQuery->where('subject_id', $request->integer('subject_id'));
        }
        $chapters = $chaptersQuery->get(['id', 'number', 'title_en', 'subject_id']);
        $allChapters = Chapter::query()
            ->with('subject:id,name_en,grade_id')
            ->orderBy('subject_id')
            ->orderBy('number')
            ->get(['id', 'number', 'title_en', 'subject_id']);

        $q = Question::query()
            ->with(['chapter.subject.grade', 'mcqOptions', 'pastPaperTag', 'parts'])
            ->whereNull('parent_question_id')
            ->when($request->filled('grade_id'), function ($query) use ($request) {
                $query->whereHas('chapter.subject', fn ($s) => $s->where('grade_id', $request->integer('grade_id')));
            })
            ->when($request->filled('subject_id'), function ($query) use ($request) {
                $query->whereHas('chapter', fn ($c) => $c->where('subject_id', $request->integer('subject_id')));
            })
            ->when($request->filled('chapter_id'), fn ($query) => $query->where('chapter_id', $request->integer('chapter_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $s = trim($request->string('search')->toString());
                $query->where(fn ($qq) => $qq->where('text_en', 'like', "%{$s}%")->orWhere('text_ur', 'like', "%{$s}%"));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/QuestionBank/Index', [
            'grades' => $grades,
            'subjects' => $subjects,
            'chapters' => $chapters,
            'allChapters' => $allChapters,
            'questions' => $q,
            'filters' => $request->only(['grade_id', 'subject_id', 'chapter_id', 'type', 'source', 'search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'chapter_id' => ['required', 'integer', 'exists:chapters,id'],
            'type' => ['required', 'in:mcq,short,long,fill,truefalse'],
            'source' => ['required', 'in:exercise,additional,past_paper'],
            'text_en' => ['nullable', 'string'],
            'text_ur' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'is_active' => ['boolean'],

            'mcq' => ['array'],
            'mcq.option_a_en' => ['nullable', 'string'],
            'mcq.option_b_en' => ['nullable', 'string'],
            'mcq.option_c_en' => ['nullable', 'string'],
            'mcq.option_d_en' => ['nullable', 'string'],
            'mcq.correct_option' => ['nullable', 'in:a,b,c,d'],

            'past' => ['array'],
            'past.board_name' => ['nullable', 'string', 'max:100'],
            'past.year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'past.session' => ['nullable', 'in:morning,evening'],

            'parts' => ['array'],
            'parts.*.text_en' => ['nullable', 'string'],
            'parts.*.text_ur' => ['nullable', 'string'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('questions/images', 'public');
        }

        $question = Question::create([
            'chapter_id' => $validated['chapter_id'],
            'type' => $validated['type'],
            'source' => $validated['source'],
            'text_en' => $validated['text_en'] ?? null,
            'text_ur' => $validated['text_ur'] ?? null,
            'image_path' => $imagePath,
            'has_parts' => ! empty($validated['parts']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($question->type === 'mcq') {
            McqOption::updateOrCreate(
                ['question_id' => $question->id],
                [
                    'option_a_en' => $validated['mcq']['option_a_en'] ?? '',
                    'option_b_en' => $validated['mcq']['option_b_en'] ?? '',
                    'option_c_en' => $validated['mcq']['option_c_en'] ?? '',
                    'option_d_en' => $validated['mcq']['option_d_en'] ?? '',
                    'correct_option' => $validated['mcq']['correct_option'] ?? 'a',
                ]
            );
        }

        if ($question->source === 'past_paper') {
            PastPaperTag::updateOrCreate(
                ['question_id' => $question->id],
                [
                    'board_name' => $validated['past']['board_name'] ?? 'Unknown Board',
                    'year' => $validated['past']['year'] ?? (int) date('Y'),
                    'session' => $validated['past']['session'] ?? null,
                ]
            );
        }

        if (! empty($validated['parts'])) {
            foreach ($validated['parts'] as $part) {
                Question::create([
                    'chapter_id' => $question->chapter_id,
                    'type' => $question->type,
                    'source' => $question->source,
                    'text_en' => $part['text_en'] ?? null,
                    'text_ur' => $part['text_ur'] ?? null,
                    'parent_question_id' => $question->id,
                    'is_active' => true,
                ]);
            }
        }

        return back()->with('success', 'Question created.');
    }

    public function update(Request $request, Question $question)
    {
        abort_if($question->parent_question_id, 400);

        $validated = $request->validate([
            'chapter_id' => ['required', 'integer', 'exists:chapters,id'],
            'type' => ['required', 'in:mcq,short,long,fill,truefalse'],
            'source' => ['required', 'in:exercise,additional,past_paper'],
            'text_en' => ['nullable', 'string'],
            'text_ur' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['boolean'],
            'is_active' => ['boolean'],

            'mcq' => ['array'],
            'mcq.option_a_en' => ['nullable', 'string'],
            'mcq.option_b_en' => ['nullable', 'string'],
            'mcq.option_c_en' => ['nullable', 'string'],
            'mcq.option_d_en' => ['nullable', 'string'],
            'mcq.correct_option' => ['nullable', 'in:a,b,c,d'],

            'past' => ['array'],
            'past.board_name' => ['nullable', 'string', 'max:100'],
            'past.year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'past.session' => ['nullable', 'in:morning,evening'],
        ]);

        if (($validated['remove_image'] ?? false) && $question->image_path) {
            Storage::disk('public')->delete($question->image_path);
            $question->image_path = null;
        }

        if ($request->hasFile('image')) {
            if ($question->image_path) {
                Storage::disk('public')->delete($question->image_path);
            }
            $question->image_path = $request->file('image')->store('questions/images', 'public');
        }

        $question->fill([
            'chapter_id' => $validated['chapter_id'],
            'type' => $validated['type'],
            'source' => $validated['source'],
            'text_en' => $validated['text_en'] ?? null,
            'text_ur' => $validated['text_ur'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ])->save();

        if ($question->type === 'mcq') {
            McqOption::updateOrCreate(
                ['question_id' => $question->id],
                [
                    'option_a_en' => $validated['mcq']['option_a_en'] ?? '',
                    'option_b_en' => $validated['mcq']['option_b_en'] ?? '',
                    'option_c_en' => $validated['mcq']['option_c_en'] ?? '',
                    'option_d_en' => $validated['mcq']['option_d_en'] ?? '',
                    'correct_option' => $validated['mcq']['correct_option'] ?? 'a',
                ]
            );
        } else {
            McqOption::query()->where('question_id', $question->id)->delete();
        }

        if ($question->source === 'past_paper') {
            PastPaperTag::updateOrCreate(
                ['question_id' => $question->id],
                [
                    'board_name' => $validated['past']['board_name'] ?? 'Unknown Board',
                    'year' => $validated['past']['year'] ?? (int) date('Y'),
                    'session' => $validated['past']['session'] ?? null,
                ]
            );
        } else {
            PastPaperTag::query()->where('question_id', $question->id)->delete();
        }

        return back()->with('success', 'Question updated.');
    }

    public function destroy(Request $request, Question $question)
    {
        abort_if($question->parent_question_id, 400);

        $this->deleteQuestion($question);

        return back()->with('success', 'Question deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:questions,id'],
        ]);

        $deleted = 0;

        foreach ($validated['ids'] as $id) {
            $question = Question::query()->whereNull('parent_question_id')->find($id);
            if ($question) {
                $this->deleteQuestion($question);
                $deleted++;
            }
        }

        return back()->with('success', "{$deleted} question(s) deleted.");
    }

    protected function deleteQuestion(Question $question): void
    {
        Question::query()->where('parent_question_id', $question->id)->delete();
        McqOption::query()->where('question_id', $question->id)->delete();
        PastPaperTag::query()->where('question_id', $question->id)->delete();

        if ($question->image_path) {
            Storage::disk('public')->delete($question->image_path);
        }

        $question->delete();
    }

    public function importForm(): Response
    {
        return Inertia::render('Admin/QuestionBank/Import');
    }

    public function import(Request $request, QuestionImageImportService $imageImport)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx,xls'],
            'images_zip' => ['nullable', 'file', 'max:51200', 'mimes:zip'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:4096'],
        ]);

        $imageMap = [];

        if ($request->hasFile('images_zip')) {
            $imageMap = array_merge($imageMap, $imageImport->extractZip($request->file('images_zip')));
        }

        if ($request->hasFile('images')) {
            $batchDir = 'questions/images/import-'.now()->format('YmdHis');
            Storage::disk('public')->makeDirectory($batchDir);

            foreach ($request->file('images') as $file) {
                $original = strtolower($file->getClientOriginalName());
                $path = $file->store($batchDir, 'public');
                $imageMap[$original] = $path;
                $imageMap[pathinfo($original, PATHINFO_FILENAME)] = $path;
            }
        }

        Excel::import(new QuestionBankImport($imageMap, $imageImport), $validated['file']);

        $imageCount = count($imageMap);

        return redirect()
            ->route('admin.question-bank.index')
            ->with('success', 'Import completed.'.($imageCount ? " ({$imageCount} images mapped)" : ''));
    }
}

