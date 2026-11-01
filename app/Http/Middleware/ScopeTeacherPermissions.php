<?php

namespace App\Http\Middleware;

use App\Models\Chapter;
use App\Models\Question;
use App\Models\Topic;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScopeTeacherPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && ! $user->hasRole('institution_admin')) {
            abort_if($user->is_active === false, 403, 'Your teacher account has been deactivated. Contact your school administrator.');
            $actions = match ($request->route()?->getName()) {
                'builder', 'builder.subjects', 'builder.chapters', 'builder.create', 'builder.store', 'papers.duplicate' => ['can_generate_paper'],
                'editor.update', 'editor.watermark-image' => ['can_edit_paper'],
                'papers.destroy' => ['can_delete_paper'],
                'questions.create', 'questions.store', 'builder.ai.manual-question' => ['can_add_question'],
                'questions.update', 'builder.ai.questions.update' => ['can_edit_question'],
                'questions.destroy' => ['can_delete_question'],
                'builder.ai.add-to-paper' => ['can_generate_paper', 'can_add_question'],
                default => [],
            };
            foreach ($actions as $action) {
                abort_if($user->teacherPermission?->$action === false, 403, 'Your school administrator has disabled this action for your account.');
            }
            $request->attributes->set('teacher_permissions', $user->teacherPermission);
            if ($request->is('builder', 'builder/*', 'api/builder/*', 'questions', 'questions/*')) {
                foreach (['allowed_grades', 'allowed_subjects', 'allowed_categories'] as $field) {
                    abort_if($user->teacherPermission?->$field === [], 403, 'Your school has revoked question-builder access.');
                }
                $permissions = $user->teacherPermission;
                if (is_array($permissions?->allowed_categories)) {
                    $sources = $request->input('sources', []);
                    if (is_array($sources) && ! $sources) {
                        $request->merge(['sources' => $permissions->allowed_categories]);
                    }
                    foreach (is_array($sources) ? $sources : [] as $source) {
                        abort_unless(in_array($source, $permissions->allowed_categories, true), 403);
                    }
                    $bookSource = ['text_book' => 'exercise', 'past_paper' => 'past_paper', 'additional_questions' => 'additional'][$request->input('book_type', '')] ?? null;
                    if ($bookSource) {
                        abort_unless(in_array($bookSource, $permissions->allowed_categories, true), 403);
                    }
                    if ($request->isMethod('POST') && $request->is('questions')) {
                        foreach ($request->input('questions', []) as $row) {
                            abort_unless(in_array($row['source'] ?? 'additional', $permissions->allowed_categories, true), 403);
                        }
                    }
                }
                $arrays = fn ($value) => is_array($value) ? $value : [];
                $chapterIds = array_merge($arrays($request->input('chapter_ids')), $arrays($request->input('config.chapter_ids')));
                $topicIds = array_merge($arrays($request->input('topic_ids')), $arrays($request->input('config.topic_ids')));
                if ($topicIds) {
                    $chapterIds = array_merge($chapterIds, Topic::whereIn('id', $topicIds)->pluck('chapter_id')->all());
                }
                $questionIds = array_merge($arrays($request->input('ids')), $arrays($request->input('config.question_ids')));
                if (is_array($questionIds) && $questionIds) {
                    $questions = Question::whereIn('id', $questionIds)->get(['chapter_id', 'source']);
                    $chapterIds = array_merge(is_array($chapterIds) ? $chapterIds : [], $questions->pluck('chapter_id')->all());
                    if (is_array($permissions?->allowed_categories)) {
                        abort_if($questions->contains(fn ($q) => ! in_array($q->source, $permissions->allowed_categories, true)), 403);
                    }
                }
                if (is_array($chapterIds) && $chapterIds) {
                    $chapters = Chapter::with('subject.grade')->whereIn('id', $chapterIds)->get();
                    foreach ($chapters as $chapter) {
                        if (is_array($permissions?->allowed_grades)) {
                            abort_unless(in_array($chapter->subject?->grade?->number, $permissions->allowed_grades, true), 403);
                        }
                        if (is_array($permissions?->allowed_subjects)) {
                            abort_unless(in_array($chapter->subject_id, $permissions->allowed_subjects, true), 403);
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
