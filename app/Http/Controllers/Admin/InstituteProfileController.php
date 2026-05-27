<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class InstituteProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $institution = Institution::findOrFail($request->user()->institution_id);

        return Inertia::render('Admin/Profile', [
            'institution' => $institution,
            'logoUrl' => $institution->logo_path
                ? Storage::disk('public')->url($institution->logo_path)
                : null,
        ]);
    }

    public function update(Request $request)
    {
        $institution = Institution::findOrFail($request->user()->institution_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store(
                "logos/{$institution->id}",
                'public'
            );
            $validated['logo_path'] = $path;
        }

        unset($validated['logo']);
        $institution->update($validated);

        return back()->with('success', 'Institute profile updated.');
    }
}
