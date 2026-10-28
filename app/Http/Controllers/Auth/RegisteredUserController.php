<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Institution;
use App\Models\User;
use App\Support\Phone;
use App\Support\SubscriptionCatalog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', ['trialDays' => SubscriptionCatalog::trialDays(), 'boards' => Board::active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone')), 'email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'school_name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^03[0-9]{9}$/', 'unique:users,phone'],
            'board_id' => ['required', Rule::exists('boards', 'id')->where('is_active', true)],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $logoPath = null;
        try {
            $user = DB::transaction(function () use ($data, $request, &$logoPath) {
                $school = Institution::create([
                    'name' => $data['school_name'], 'owner_name' => $data['name'], 'phone' => $data['phone'],
                    'board_id' => $data['board_id'], 'license_type' => 'full', 'subscription_plan' => 'trial',
                    'trial_started_at' => now(), 'access_expires_at' => now()->addDays(SubscriptionCatalog::trialDays()), 'expiry_date' => now()->addDays(SubscriptionCatalog::trialDays()),
                ]);
                if ($request->hasFile('logo')) {
                    $logoPath = $request->file('logo')->store("logos/{$school->id}", 'public');
                    $school->update(['logo_path' => $logoPath]);
                }
                $user = User::create([
                    'institution_id' => $school->id, 'name' => $data['name'], 'email' => $data['email'],
                    'phone' => $data['phone'], 'password' => Hash::make($data['password']),
                    'is_active' => true, 'verification_due_at' => now()->addHour(),
                ]);
                Role::findOrCreate('institution_admin', 'web');
                Role::findOrCreate('teacher', 'web');
                $user->assignRole('institution_admin');

                return $user;
            });
        } catch (\Throwable $e) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            throw $e;
        }

        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            Log::error('Signup verification email could not be sent.', ['user_id' => $user->id, 'exception' => $e]);
            session()->flash('error', 'Your school is ready, but the verification email could not be sent. Please use Resend or contact support.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(route('dashboard', absolute: false));
    }
}
