<?php

namespace App\Http\Controllers;

use App\Support\SubscriptionCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function show(Request $request): Response
    {
        $school = $request->user()->institution;
        abort_unless($school, 403);

        return Inertia::render('Subscription/Renew', [
            'school' => ['id' => $school->id, 'name' => $school->name],
            'plans' => SubscriptionCatalog::active(),
            'payment' => [
                'number' => config('subscriptions.payment_number'),
                'name' => config('subscriptions.account_name'),
                'whatsapp' => config('subscriptions.whatsapp'),
            ],
        ]);
    }
}
