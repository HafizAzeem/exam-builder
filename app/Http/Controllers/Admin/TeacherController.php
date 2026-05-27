<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
            'allowed_grades' => ['nullable', 'array'],
            'allowed_subjects' => ['nullable', 'array'],
            'allowed_categories' => ['nullable', 'array'],
        ]);

        $teacher = User::create([
            'institution_id' => $request->user()->institution_id,
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $teacher->assignRole('teacher');

        TeacherPermission::create([
            'user_id' => $teacher->id,
            'allowed_grades' => $validated['allowed_grades'] ?? null,
            'allowed_subjects' => $validated['allowed_subjects'] ?? null,
            'allowed_categories' => $validated['allowed_categories'] ?? null,
        ]);

        return back()->with('success', 'Teacher account created.');
    }
}
