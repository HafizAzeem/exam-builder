<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AIImport;
use App\Models\TeacherPermission;
use App\Models\User;
use App\Support\CurriculumLookup;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function index(Request $request): Response
    {
        $teachers = User::role('teacher')
            ->where('institution_id', $request->user()->institution_id)
            ->with('teacherPermission')
            ->latest()
            ->get();

        return Inertia::render('Admin/Teachers', [
            'teachers' => $teachers,
            'grades' => CurriculumLookup::grades()->get(['id', 'number', 'label_en']),
            'subjects' => CurriculumLookup::subjects()->with('grade:id,number,label_en')->get(['id', 'grade_id', 'name_en']),
            'categories' => ['exercise', 'additional', 'past_paper'],
        ]);
    }

    public function store(Request $request)
    {
        if ($request->filled('phone')) {
            $request->merge([
                'phone' => Phone::normalize($request->string('phone')->toString()) ?? $request->string('phone')->toString(),
            ]);
        }

        $validated = $this->validateTeacher($request);

        return DB::transaction(function () use ($request, $validated) {
            $teacher = User::create([
                'institution_id' => $request->user()->institution_id,
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $teacher->assignRole('teacher');

            TeacherPermission::create([
                'user_id' => $teacher->id,
                'can_use_assistant' => $validated['can_use_assistant'] ?? true,
                'allowed_grades' => $validated['allowed_grades'] ?? null,
                'allowed_subjects' => $validated['allowed_subjects'] ?? null,
                'allowed_categories' => $validated['allowed_categories'] ?? null,
            ]);

            return back()->with('success', 'Teacher account created.');
        });
    }

    public function update(Request $request, User $teacher)
    {
        $this->assertOwned($request, $teacher);
        $data = $this->validateTeacher($request, $teacher);
        DB::transaction(function () use ($teacher, $data) {
            $teacher->fill(collect($data)->only(['name', 'email', 'phone', 'is_active'])->all());
            if (! empty($data['password'])) {
                $teacher->password = $data['password'];
            }
            $teacher->save();
            $teacher->teacherPermission()->updateOrCreate([], collect($data)->only(['allowed_grades', 'allowed_subjects', 'allowed_categories', 'can_use_assistant'])->all());
        });

        return back()->with('success', 'Teacher and permissions updated.');
    }

    public function destroy(Request $request, User $teacher)
    {
        $this->assertOwned($request, $teacher);
        DB::transaction(function () use ($teacher) {
            $teacher = User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            // Deleting a user cascades to saved papers and imports. Keep their work intact.
            if ($teacher->savedPapers()->exists() || AIImport::where('user_id', $teacher->id)->exists()) {
                throw ValidationException::withMessages(['teacher' => 'This teacher has saved work. Deactivate the account to revoke access while keeping their papers.']);
            }
            $teacher->delete();
        });

        return back()->with('success', 'Teacher deleted.');
    }

    private function assertOwned(Request $request, User $teacher): void
    {
        abort_unless($request->user()->institution_id && (int) $teacher->institution_id === (int) $request->user()->institution_id && $teacher->hasRole('teacher') && ! $teacher->hasRole('institution_admin'), 404);
    }

    private function validateTeacher(Request $request, ?User $teacher = null): array
    {
        abort_unless($request->user()->institution_id, 403);
        if ($request->filled('phone')) {
            $request->merge(['phone' => Phone::normalize($request->string('phone')->toString()) ?? $request->input('phone')]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($teacher?->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($teacher?->id)],
            'password' => [$teacher ? 'nullable' : 'required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'can_use_assistant' => ['sometimes', 'boolean'],
            'allowed_grades' => ['present', 'nullable', 'array'],
            'allowed_grades.*' => ['integer', 'distinct', Rule::exists('grades', 'number')->where('is_active', true)],
            'allowed_subjects' => ['present', 'nullable', 'array'],
            'allowed_subjects.*' => ['integer', 'distinct', Rule::exists('subjects', 'id')->where('is_active', true)],
            'allowed_categories' => ['present', 'nullable', 'array'],
            'allowed_categories.*' => [Rule::in(['exercise', 'additional', 'past_paper']), 'distinct'],
        ]);
        if (empty($data['email']) && empty($data['phone'])) {
            throw ValidationException::withMessages(['email' => 'Enter an email address or phone number for login.']);
        }
        foreach (['allowed_grades', 'allowed_subjects'] as $field) {
            if (is_array($data[$field])) {
                $data[$field] = array_values(array_map('intval', $data[$field]));
            }
        }

        return $data;
    }
}
