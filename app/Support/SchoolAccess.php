<?php

namespace App\Support;

use App\Models\User;

final class SchoolAccess
{
    public static function mailReady(): bool
    {
        $mailer = config('mail.default');

        return ! in_array($mailer, ['log', 'array', null], true)
            && ! in_array(config("mail.mailers.{$mailer}.transport"), ['log', 'array', 'failover', 'roundrobin', null], true);
    }

    public static function verificationEnforced(): bool
    {
        return (bool) config('subscriptions.verification_enforced')
            && (! app()->environment('production') || self::mailReady());
    }

    public static function status(?User $user): array
    {
        if (! $user || $user->hasRole('super_admin')) {
            return ['verification' => null, 'subscription' => null];
        }
        $owner = $user->hasRole('institution_admin');
        $due = $user->verification_due_at ?? $user->created_at?->copy()->addHour();
        $verification = $owner && ! $user->hasVerifiedEmail() ? [
            'due_at' => $due?->toIso8601String(),
            'blocked' => self::verificationEnforced() && ($due?->lessThanOrEqualTo(now()) ?? true),
            'enforced' => self::verificationEnforced(),
        ] : null;
        $school = $user->institution;
        $expiry = $school?->accessExpiry();

        return [
            'verification' => $verification,
            'subscription' => $school ? [
                'school_id' => $school->id, 'school_name' => $school->name,
                'plan' => $school->subscription_plan, 'expires_at' => $expiry?->toIso8601String(),
                'expired' => ! $school->hasAccess(),
                'expiring_soon' => $school->hasAccess() && $expiry->lessThanOrEqualTo(now()->addDays(3)),
            ] : null,
        ];
    }
}
