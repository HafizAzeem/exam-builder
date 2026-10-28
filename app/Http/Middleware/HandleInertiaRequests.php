<?php

namespace App\Http\Middleware;

use App\Support\SchoolAccess;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'access' => fn () => SchoolAccess::status($request->user()),
            'mailReady' => fn () => $request->user()?->hasRole('super_admin') ? SchoolAccess::mailReady() : null,
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->toArray(),
                    'roles' => $request->user()->getRoleNames()->values()->all(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'watermark_upload' => fn () => $request->session()->get('watermark_upload'),
                'past_paper_reused' => fn () => $request->session()->get('past_paper_reused'),
            ],
            'pusher' => [
                'key' => config('broadcasting.default') === 'pusher'
                    ? config('broadcasting.connections.pusher.key')
                    : null,
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            ],
        ];
    }
}
