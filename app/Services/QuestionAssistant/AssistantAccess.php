<?php

namespace App\Services\QuestionAssistant;

use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use App\Support\CurriculumLookup;
use Illuminate\Validation\ValidationException;

class AssistantAccess
{
    public function assert(User $user, array $data): void
    {
        abort_unless($user->is_active !== false && $user->institution?->hasAccess() && $user->hasAnyRole(['institution_admin', 'teacher']), 403, 'Question Assistant is unavailable for this account.');
        $permission = $user->teacherPermission;
        if (! $user->hasRole('institution_admin')) {
            abort_unless($permission?->can_use_assistant ?? true, 403, 'Your school has disabled assistant access.');
        }
        $grade = Grade::find($data['grade_id']);
        $subject = Subject::find($data['subject_id']);
        $chapterIds = array_values(array_unique(array_map('intval', $data['chapter_ids'] ?? [])));
        if (! $grade?->is_active || ! in_array($grade->number, config('exam.teacher_ai_grade_numbers', [5, 6, 7, 8, 9, 10, 11, 12]), true) || ! $subject?->is_active || (int) $subject->grade_id !== (int) $grade->id || ! $chapterIds || CurriculumLookup::chapters($subject->id)->whereIn('id', $chapterIds)->count() !== count($chapterIds)) {
            throw ValidationException::withMessages(['chapter_ids' => 'Choose a valid class, subject and chapters for the assistant.']);
        }
        if ($user->hasRole('institution_admin')) {
            return;
        }
        if (is_array($permission?->allowed_grades)) {
            abort_unless(in_array($grade->number, $permission->allowed_grades, true), 403);
        }
        if (is_array($permission?->allowed_subjects)) {
            abort_unless(in_array((int) $subject->id, $permission->allowed_subjects, true), 403);
        }
        $source = ['text_book' => 'exercise', 'past_paper' => 'past_paper', 'additional_questions' => 'additional'][$data['book_type'] ?? 'additional_questions'];
        if (is_array($permission?->allowed_categories)) {
            abort_unless(in_array($source, $permission->allowed_categories, true), 403);
        }
    }
}
