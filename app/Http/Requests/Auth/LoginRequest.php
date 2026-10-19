<?php

namespace App\Http\Requests\Auth;

use App\Models\LoginSession;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim($this->string('login')->toString());

        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? User::query()->where('email', $login)->first()
            : $this->findByPhone($login);

        $success = $user
            && $user->is_active
            && Hash::check($this->string('password')->toString(), $user->password);

        if ($user) {
            LoginSession::create([
                'user_id' => $user->id,
                'ip_address' => $this->ip() ?? '0.0.0.0',
                'user_agent' => $this->userAgent(),
                'success' => $success,
                'logged_in_at' => now(),
            ]);
        }

        if (! $success) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        Auth::login($user, $this->boolean('remember'));
        RateLimiter::clear($this->throttleKey());
    }

    protected function findByPhone(string $login): ?User
    {
        $normalized = Phone::normalize($login);
        $candidates = array_values(array_unique(array_filter([$login, $normalized])));

        return User::query()
            ->whereIn('phone', $candidates)
            ->first();
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
