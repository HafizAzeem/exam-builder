<?php

use App\Support\PublicSite;
use App\Support\SchoolAccess;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('seo:build-public', function () {
    $site = app(PublicSite::class);
    file_put_contents(public_path('sitemap.xml'), $site->sitemap());
    $robots = file_get_contents(public_path('robots.txt'));
    $robots = preg_replace('/^Sitemap:.*$/m', 'Sitemap: '.config('seo.url').'/sitemap.xml', $robots);
    file_put_contents(public_path('robots.txt'), $robots);
    $summary = "# Exam Builder\n\n> Question-paper preparation for teachers and schools in Pakistan, including academies.\n\n";
    $summary .= "Public information is available in English and Urdu. Available curriculum varies; board selection is not a guarantee of complete syllabus coverage.\n\n";
    $summary .= "Schools can try the paper-building tools free before choosing a paid subscription. Current trial duration and plan prices are published on the pricing page. Renewals are manually reviewed after JazzCash/Easypaisa payment and WhatsApp proof. No automatic billing.\n\n";
    $summary .= "Schools add their own questions; a complete ready-made syllabus question bank is currently not included. Question contributions are shared by matching curriculum; saved school papers remain private application content. Do not infer official board affiliation or complete all-board coverage.\n\n## Public pages\n\n";
    foreach (['en', 'ur'] as $locale) {
        $copy = $site->content($locale);
        foreach (array_keys(PublicSite::PATHS) as $key) {
            $summary .= '- '.$copy['pages'][$key]['title'].': '.$site->url($key, $locale)."\n";
        }
    }
    $summary .= "\n## Discovery\n\n- Sitemap: ".config('seo.url')."/sitemap.xml\n- Robots: ".config('seo.url')."/robots.txt\n\nAccount, login, dashboard, teacher and admin pages are not public reference resources. This summary is informational and is not a Google ranking mechanism.\n";
    file_put_contents(public_path('llms.txt'), $summary);
    $this->info('Generated sitemap and public discovery files for 16 canonical English/Urdu URLs.');
})->purpose('Build public SEO discovery files from canonical URLs and current subscription plans');

Artisan::command('subscriptions:check-mail', function () {
    if (! SchoolAccess::mailReady()) {
        $this->error('Configure a delivery-capable MAIL_MAILER before enabling production email verification. Log/array/composite mailers are not accepted.');

        return 1;
    }
    $this->info('Mail transport is configured. Send and open a real verification email to confirm delivery before enabling enforcement.');

    return 0;
})->purpose('Check mail configuration readiness for school email verification');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
