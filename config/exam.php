<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Use system question bank in paper builder
    |--------------------------------------------------------------------------
    |
    | When false (launch mode), teachers make papers via AI Generate / Paste only.
    | Super Admin web crawl still seeds the global bank. Flip to true later when
    | enough questions exist for pick/random from the system bank.
    |
    */
    'use_system_question_bank' => (bool) env('USE_SYSTEM_QUESTION_BANK', false),

    /*
    |--------------------------------------------------------------------------
    | Teacher AI paper grades
    |--------------------------------------------------------------------------
    */
    'teacher_ai_grade_numbers' => [9, 10, 11, 12],

    /*
    |--------------------------------------------------------------------------
    | Preferred education websites (fallback when DB table empty)
    |--------------------------------------------------------------------------
    |
    | Super Admin manages preferred_question_sites. Env list is only used if
    | the table has no active rows (e.g. before seeder). Empty = AI invents.
    |
    */
    'preferred_question_sites' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PREFERRED_QUESTION_SITES', ''))
    ))),

    'preferred_sites_max_sites' => (int) env('PREFERRED_SITES_MAX_SITES', 4),

    'preferred_sites_max_urls_per_site' => (int) env('PREFERRED_SITES_MAX_URLS_PER_SITE', 2),

];
