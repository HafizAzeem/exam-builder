<?php

namespace App\Services\QuestionAssistant;

use App\Models\AIImport;
use App\Models\AssistantRequest;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssistantBudgetService
{
    public function summary(Institution $institution): array
    {
        return DB::transaction(function () use ($institution) {
            $school = Institution::whereKey($institution->id)->lockForUpdate()->firstOrFail();
            $this->reconcile($school);
            return $this->totals($school);
        });
    }

    public function reserve(User $user, string $requestKey, string $mode): AssistantRequest
    {
        if (! Str::isUuid($requestKey) || ! in_array($mode, ['format', 'search', 'upload'], true)) {
            throw ValidationException::withMessages(['request_key' => 'Submit a valid assistant request.']);
        }
        // Reconciliation commits even when a later quota check rejects the submission.
        $institution = Institution::findOrFail($user->institution_id);
        $this->summary($institution);
        return DB::transaction(function () use ($user, $requestKey, $mode, $institution) {
            $school = Institution::whereKey($institution->id)->lockForUpdate()->firstOrFail();
            $this->assertAccess($user->id, $school);
            $existing = AssistantRequest::where('institution_id', $school->id)->where('request_key', $requestKey)->first();
            if ($existing) {
                abort_unless((int) $existing->user_id === (int) $user->id && $existing->mode === $mode, 409, 'This request reference has already been used.');
                return $existing;
            }
            $totals = $this->totals($school);
            $this->guard($totals['remaining'] > 0, 'This school has no assistant requests remaining.');
            $this->guard($totals['daily_used'] < $totals['daily_limit'], 'The daily assistant attempt limit has been reached. It resets at '.$totals['daily_reset_at'].'.');
            $this->guard(! $this->active($school)->exists(), 'Another assistant request is already queued or running for this school.');
            $this->guard(AssistantRequest::where('institution_id', $school->id)->where('reserved_at', '>', now()->subMinute())->count() < 3, 'Please wait a minute before submitting another assistant request.');
            return AssistantRequest::create(['institution_id' => $school->id, 'user_id' => $user->id, 'request_key' => $requestKey, 'mode' => $mode, 'status' => 'reserved', 'reserved_at' => now(), 'expires_at' => now()->addMinutes(5)]);
        });
    }

    public function attachImport(AssistantRequest $request, AIImport $import): void
    {
        $this->locked($request, function ($row) use ($import) {
            abort_unless((int) $row->institution_id === (int) $import->institution_id && (int) $row->user_id === (int) $import->user_id, 403);
            if ($row->status === 'reserved' && ! $row->ai_import_id) { $row->update(['ai_import_id' => $import->id]); }
            else { abort_unless((int) $row->ai_import_id === (int) $import->id, 409); }
        });
    }

    public function start(AssistantRequest $request): bool
    {
        return $this->locked($request, function ($row, $school) {
            if (! $this->usable($row, $school) || $row->status !== 'reserved') { return false; }
            $totals = $this->totals($school);
            if ($totals['daily_used'] >= $totals['daily_limit']) { $this->finish($row, 'failed', 'Daily assistant attempt limit reached.'); return false; }
            $row->update(['status' => 'processing', 'provider_started_at' => now(), 'expires_at' => now()->addMinutes(5)]);
            return true;
        });
    }

    public function complete(AssistantRequest $request, callable $publish): bool
    {
        return $this->locked($request, function ($row, $school) use ($publish) {
            if (! $this->usable($row, $school) || $row->status !== 'processing') { return false; }
            if ($row->provider_started_at->copy()->addSeconds(120)->isPast()) { $this->finish($row, 'failed', 'Assistant processing deadline exceeded.'); return false; }
            $publish();
            $this->finish($row, 'completed');
            return true;
        });
    }

    public function fail(AssistantRequest $request, string $error): void
    {
        $this->locked($request, fn ($row) => $this->finish($row, 'failed', $error));
    }
    public function cancel(AssistantRequest $request): void
    {
        $this->locked($request, fn ($row) => $this->finish($row, 'canceled', 'Canceled by the teacher.'));
    }
    private function locked(AssistantRequest $request, callable $callback): mixed
    {
        return DB::transaction(function () use ($request, $callback) {
            $school = Institution::whereKey($request->institution_id)->lockForUpdate()->firstOrFail();
            $row = AssistantRequest::whereKey($request->id)->where('institution_id', $school->id)->lockForUpdate()->firstOrFail();
            return $callback($row, $school);
        });
    }
    private function active(Institution $school)
    {
        return AssistantRequest::where('institution_id', $school->id)->whereIn('status', ['reserved', 'processing']);
    }
    private function reconcile(Institution $school): void
    {
        foreach ($this->active($school)->where('expires_at', '<=', now())->lockForUpdate()->get() as $row) {
            $this->finish($row, 'failed', 'The assistant request expired. Please submit again.');
        }
    }
    private function totals(Institution $school): array
    {
        $query = AssistantRequest::where('institution_id', $school->id);
        if ($school->assistant_budget_started_at) { $query->where('reserved_at', '>=', $school->assistant_budget_started_at); }
        $used = (clone $query)->where('status', 'completed')->count();
        $reserved = (clone $query)->whereIn('status', ['reserved', 'processing'])->count();
        return ['limit' => $school->assistant_request_limit, 'used' => $used, 'reserved' => $reserved, 'refunded' => (clone $query)->whereIn('status', ['failed', 'canceled'])->count(), 'remaining' => max(0, $school->assistant_request_limit - $used - $reserved), 'daily_limit' => $school->assistant_daily_limit, 'daily_used' => AssistantRequest::where('institution_id', $school->id)->where('provider_started_at', '>=', now()->startOfDay())->count(), 'daily_reset_at' => now()->addDay()->startOfDay()->toIso8601String()];
    }
    private function usable(AssistantRequest $row, Institution $school): bool
    {
        if (! in_array($row->status, ['reserved', 'processing'], true)) { return false; }
        if ($row->expires_at->isPast()) { $this->finish($row, 'failed', 'The assistant request expired.'); return false; }
        try { $this->assertAccess($row->user_id, $school); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->finish($row, 'failed', 'Assistant access has been revoked.'); return false; }
        return true;
    }
    private function assertAccess(?int $userId, Institution $school): void
    {
        $user = User::find($userId);
        abort_unless($user && $user->is_active && (int) $user->institution_id === (int) $school->id && $school->hasAccess() && ($user->hasRole('institution_admin') || ($user->hasRole('teacher') && ($user->teacherPermission?->can_use_assistant ?? true))), 403, 'Assistant access is unavailable.');
    }
    private function finish(AssistantRequest $row, string $status, ?string $error = null): void
    {
        if (in_array($row->status, ['reserved', 'processing'], true)) { $row->update(['status' => $status, 'finished_at' => now(), 'error_message' => $error ? mb_substr($error, 0, 2000) : null]); }
    }
    private function guard(bool $condition, string $message): void
    {
        if (! $condition) { throw ValidationException::withMessages(['assistant' => $message]); }
    }
}
