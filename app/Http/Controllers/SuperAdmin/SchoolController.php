<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\User;
use App\Services\QuestionAssistant\AssistantBudgetService;
use App\Support\SubscriptionCatalog;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SchoolController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:150', 'status' => 'nullable|in:trial,active,expired,unverified']);
        $query = Institution::query()->with(['board:id,name', 'users' => fn ($q) => $q->role('institution_admin')->select('users.id', 'institution_id', 'name', 'email', 'phone', 'email_verified_at')]);
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('phone', 'like', $search)
                ->orWhereHas('users', fn ($u) => $u->where('email', 'like', $search)->orWhere('name', 'like', $search)));
        }
        match ($request->input('status')) {
            'trial' => $query->where('subscription_plan', 'trial')->where('access_expires_at', '>', now()),
            'active' => $query->where('access_expires_at', '>', now()),
            'expired' => $query->where('access_expires_at', '<=', now()),
            'unverified' => $query->whereHas('users', fn ($q) => $q->role('institution_admin')->whereNull('email_verified_at')),
            default => null,
        };
        $schools = $query->latest('id')->paginate(20)->withQueryString();
        $schools->through(fn ($school) => [
            'id' => $school->id, 'name' => $school->name, 'board' => $school->board?->name,
            'logo_url' => $school->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
            'plan' => $school->subscription_plan, 'expires_at' => $school->accessExpiry()?->toIso8601String(),
            'expired' => ! $school->hasAccess(), 'owners' => $school->users,
            'assistantUsage' => app(AssistantBudgetService::class)->summary($school),
            'assistantHistory' => DB::table('assistant_budget_events')->where('institution_id', $school->id)->latest('id')->limit(10)->get(),
            'history' => DB::table('school_account_events')->leftJoin('users', 'administrator_id', '=', 'users.id')
                ->where('school_account_events.institution_id', $school->id)->latest('school_account_events.id')->limit(10)
                ->get(['school_account_events.*', 'users.name as administrator_name'])
                ->map(function ($event) {
                    foreach (['created_at', 'previous_expires_at', 'new_expires_at'] as $field) {
                        $event->$field = $event->$field ? Carbon::parse($event->$field)->toIso8601String() : null;
                    }

                    return $event;
                }),
        ]);

        return Inertia::render('SuperAdmin/Schools/Index', ['plans' => SubscriptionCatalog::active(), 'schools' => $schools, 'filters' => $request->only('search', 'status')]);
    }

    public function adjust(Request $request, Institution $institution)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['renew', 'extend_trial'])],
            'confirmed' => ['required', 'accepted'], 'request_id' => ['required', 'uuid'],
            'plan' => ['required_if:action,renew', 'nullable', Rule::in(array_keys(SubscriptionCatalog::active()))],
            'weeks' => ['required_if:action,extend_trial', 'nullable', 'integer', 'min:1', 'max:520'],
            'payment_reference' => ['nullable', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $institution, $data) {
            DB::table('subscription_settings')->where('id', 1)->lockForUpdate()->first();
            $school = Institution::whereKey($institution->id)->lockForUpdate()->firstOrFail();
            if ($this->alreadyApplied($data['request_id'], $school->id, $data['action'])) {
                return;
            }
            $previous = $school->accessExpiry();
            $base = $previous && $previous->isFuture() ? $previous->copy() : now();
            $plan = $data['action'] === 'renew' ? $data['plan'] : 'trial';
            $selectedPlan = $data['action'] === 'renew' ? (SubscriptionCatalog::active()[$plan] ?? null) : null;
            if ($data['action'] === 'renew' && ! $selectedPlan) {
                throw ValidationException::withMessages(['plan' => 'This plan is no longer available.']);
            }
            $amount = $selectedPlan['amount'] ?? 0;
            $expiry = $data['action'] === 'renew'
                ? $base->addMonthsNoOverflow($selectedPlan['months'])
                : $base->addWeeks((int) $data['weeks']);
            $school->update(['subscription_plan' => $plan, 'license_type' => 'full', 'access_expires_at' => $expiry, 'expiry_date' => $expiry]);
            DB::table('school_account_events')->insert([
                'institution_id' => $school->id, 'administrator_id' => $request->user()->id,
                'request_id' => $data['request_id'], 'action' => $data['action'], 'plan' => $plan, 'amount' => $amount,
                'previous_expires_at' => $previous, 'new_expires_at' => $expiry,
                'payment_reference' => $data['payment_reference'] ?? null,
                'note' => $data['note'] ?? ($data['action'] === 'extend_trial' ? 'Added '.$data['weeks'].' trial weeks.' : null),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'School access updated. Repeated submissions are applied only once.');
    }

    public function verifyOwner(Request $request, Institution $institution)
    {
        $data = $request->validate(['user_id' => ['required', 'integer'], 'request_id' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted']]);
        DB::transaction(function () use ($request, $institution, $data) {
            Institution::whereKey($institution->id)->lockForUpdate()->firstOrFail();
            $owner = User::role('institution_admin')->where('institution_id', $institution->id)->whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            if ($this->alreadyApplied($data['request_id'], $institution->id, 'verify_email') || $owner->hasVerifiedEmail()) {
                return;
            }
            $owner->markEmailAsVerified();
            DB::table('school_account_events')->insert([
                'institution_id' => $institution->id, 'administrator_id' => $request->user()->id, 'user_id' => $owner->id,
                'request_id' => $data['request_id'], 'action' => 'verify_email', 'amount' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::afterCommit(fn () => event(new Verified($owner)));
        });

        return back()->with('success', 'Owner email verified.');
    }

    public function assistantBudget(Request $request, Institution $institution)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['override', 'reset'])],
            'request_id' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted'],
            'assistant_request_limit' => ['required_if:action,override', 'integer', 'min:0', 'max:100000'],
            'assistant_daily_limit' => ['required_if:action,override', 'integer', 'min:0', 'max:10000'],
            'note' => ['required', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $institution, $data) {
            $school = Institution::whereKey($institution->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('assistant_budget_events')->where('request_id', $data['request_id'])->first();
            if ($existing) {
                abort_unless((int) $existing->institution_id === (int) $school->id && $existing->action === $data['action'], 422);
                return;
            }
            $fields = ['assistant_request_limit', 'assistant_daily_limit', 'assistant_budget_started_at'];
            $previous = $school->only($fields);
            if ($data['action'] === 'reset') {
                // Old workers cannot publish into a freshly reset budget.
                \App\Models\AssistantRequest::where('institution_id', $school->id)->whereIn('status', ['reserved', 'processing'])->update(['status' => 'canceled', 'finished_at' => now(), 'error_message' => 'School budget reset by platform administrator.']);
                // Requests and daily attempts remain in the ledger; reset only request usage.
                $school->assistant_budget_started_at = now()->addSecond();
            } else {
                $school->assistant_request_limit = $data['assistant_request_limit'];
                $school->assistant_daily_limit = $data['assistant_daily_limit'];
            }
            $school->save();
            DB::table('assistant_budget_events')->insert([
                'institution_id' => $school->id, 'administrator_id' => $request->user()->id,
                'request_id' => $data['request_id'], 'action' => $data['action'],
                'previous_values' => json_encode($previous), 'new_values' => json_encode($school->only($fields)),
                'note' => $data['note'], 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return back()->with('success', 'Assistant budget updated and recorded in the audit history.');
    }

    private function alreadyApplied(string $requestId, int $schoolId, string $action): bool
    {
        $event = DB::table('school_account_events')->where('request_id', $requestId)->first();
        abort_if($event && ((int) $event->institution_id !== $schoolId || $event->action !== $action), 422, 'This request reference has already been used.');

        return $event !== null;
    }
}
