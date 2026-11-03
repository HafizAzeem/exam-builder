<?php

namespace App\Http\Controllers;

use App\Support\PublicSite;
use App\Support\SubscriptionCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function show(Request $request, PublicSite $site): View
    {
        $locale = $request->route('locale', 'en');
        $key = $request->route('page', 'home');
        $copy = $site->content($locale);
        $page = $copy['pages'][$key];

        return view($locale === 'en' && $key === 'home' ? 'landing' : 'public.page', [
            'site' => $site, 'copy' => $copy, 'page' => $page, 'pageKey' => $key, 'locale' => $locale,
            'plans' => SubscriptionCatalog::active(), 'trialDays' => SubscriptionCatalog::trialDays(),
            'seo' => $site->metadata($key, $locale, $page),
        ]);
    }
}
