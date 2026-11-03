<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class SubscriptionCatalog
{
    public static function trialDays(): int
    {
        return (int) DB::table('subscription_settings')->where('id', 1)->value('trial_days');
    }

    public static function all(bool $activeOnly = false): array
    {
        return DB::table('subscription_plans')->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')->orderBy('id')->get()->mapWithKeys(fn ($plan) => [$plan->key => [
                'key' => $plan->key, 'label' => $plan->label, 'label_ur' => $plan->label_ur,
                'amount' => (int) $plan->amount, 'months' => (int) $plan->months,
                'sort_order' => (int) $plan->sort_order, 'is_active' => (bool) $plan->is_active,
            ]])->all();
    }

    public static function active(): array
    {
        return self::all(true);
    }
}
