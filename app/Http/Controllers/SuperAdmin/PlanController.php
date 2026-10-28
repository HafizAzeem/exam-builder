<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\SubscriptionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index()
    {
        return Inertia::render('SuperAdmin/Plans/Index', [
            'plans' => array_values(SubscriptionCatalog::all()), 'trialDays' => SubscriptionCatalog::trialDays(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'trial_days' => ['required', 'integer', 'min:1', 'max:90'],
            'plans' => ['required', 'array', 'min:1', 'max:30'],
            'plans.*' => ['required', 'array:key,label,label_ur,amount,months,sort_order,is_active'],
            'plans.*.key' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_-]*$/', 'distinct', 'not_in:trial,legacy'],
            'plans.*.label' => ['required', 'string', 'max:100'],
            'plans.*.label_ur' => ['required', 'string', 'max:150'],
            'plans.*.amount' => ['required', 'integer', 'min:1', 'max:10000000'],
            'plans.*.months' => ['required', 'integer', 'min:1', 'max:60'],
            'plans.*.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'plans.*.is_active' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($data) {
            // Serialize catalog edits and activation against this singleton row.
            DB::table('subscription_settings')->where('id', 1)->lockForUpdate()->first();
            $existing = DB::table('subscription_plans')->pluck('key')->all();
            $submitted = array_column($data['plans'], 'key');
            if (array_diff($existing, $submitted)) {
                throw ValidationException::withMessages(['plans' => 'Existing plans cannot be removed or renamed. Disable them instead.']);
            }
            if (! collect($data['plans'])->contains(fn ($plan) => (bool) $plan['is_active'])) {
                throw ValidationException::withMessages(['plans' => 'Keep at least one paid plan active.']);
            }
            foreach ($data['plans'] as $plan) {
                $existingPlan = DB::table('subscription_plans')->where('key', $plan['key'])->first();
                if ($existingPlan) {
                    DB::table('subscription_plans')->where('id', $existingPlan->id)->update($plan + ['updated_at' => now()]);
                } else {
                    DB::table('subscription_plans')->insert($plan + ['created_at' => now(), 'updated_at' => now()]);
                }
            }
            DB::table('subscription_settings')->where('id', 1)->update(['trial_days' => $data['trial_days'], 'updated_at' => now()]);
        });

        return back()->with('success', 'Plans and trial settings saved. Existing school expiry dates are unchanged.');
    }
}
