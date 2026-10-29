<!DOCTYPE html>
<html lang="{{ $locale }}-PK" dir="{{ $locale === 'ur' ? 'rtl' : 'ltr' }}">
<head>@include('public.head')</head>
<body class="bg-white font-sans text-slate-900 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4">{{ $copy['skip'] }}</a>
    @include('public.header')
    <main id="main-content">
        <section class="border-b border-blue-100 bg-gradient-to-br from-blue-50 via-slate-50 to-white px-5 py-12 sm:py-16">
            <div class="mx-auto grid max-w-6xl items-start gap-8 lg:grid-cols-3">
                <div class="lg:col-span-2"><p class="text-sm font-bold text-blue-700">{{ $copy['eyebrow'] }}</p><h1 class="mt-4 text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">{{ $page['heading'] }}</h1><p class="mt-5 max-w-3xl text-lg leading-8 text-slate-600">{{ $page['intro'] }}</p>
                    @if($pageKey === 'contact')
                    <a href="https://wa.me/{{ config('subscriptions.whatsapp') }}" target="_blank" rel="noopener noreferrer" class="mt-7 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white hover:bg-emerald-700">{{ $copy['whatsapp'] }} <span aria-hidden="true">↗</span></a>
                    @else
                    <a href="/register" class="mt-7 inline-flex rounded-xl bg-blue-600 px-5 py-3 font-bold text-white hover:bg-blue-700">{{ $copy['trial'] }}</a>
                    @endif
                </div>
                <aside class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm"><p class="font-bold text-slate-950">{{ $copy['trial_summary'] }}</p><p class="mt-3 text-sm leading-7 text-slate-600">{{ $copy['plan_summary'] }}</p><a href="{{ $site->path('pricing', $locale) }}" class="mt-4 inline-block text-sm font-bold text-blue-700 underline">{{ $copy['view_pricing'] }}</a></aside>
            </div>
        </section>
        <article class="mx-auto max-w-6xl px-5 py-10 sm:py-14">
            <div class="grid gap-6 md:grid-cols-2">
                @foreach($pageKey === 'pricing' ? [] : $page['sections'] as $section)
                <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-7"><h2 class="text-xl font-bold text-slate-950">{{ $section['heading'] }}</h2><p class="mt-4 leading-8 text-slate-600">{{ $section['text'] }}</p>@if(!empty($section['items']))<ul class="mt-4 list-inside list-disc space-y-3 text-slate-700">@foreach($section['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif</section>
                @endforeach
            </div>
            @if(in_array($pageKey, ['contact', 'home'], true))
            <div class="mt-10 border-t border-slate-200 pt-10">@include('public.hire')</div>
            @endif
            @if(in_array($pageKey, ['pricing', 'home'], true))
            @include('public.pricing')
            @endif
            @if($pageKey === 'guide')
            <section class="mt-8 rounded-2xl border bg-slate-50 p-6"><h2 class="text-xl font-bold">{{ $copy['sample_heading'] }}</h2><p class="mt-3 leading-8 text-slate-600">{{ $copy['sample_note'] }}</p><div class="mt-5 rounded-xl border bg-white p-5" lang="en" dir="ltr"><p class="font-bold">Example Academy · English Classroom Test</p><p class="mt-2 text-sm text-slate-500">Illustrative layout · 10 marks · 15 minutes</p><ol class="mt-5 list-inside list-decimal space-y-4 text-sm"><li>Choose the noun: quickly / school / blue / write. (1)</li><li>Choose the correct verb: She ___ every day. read / reads. (1)</li><li>Write a definition of a pronoun with one example. (2)</li><li>Write two sentences in the present continuous tense. (2)</li><li>Write a short paragraph about a day at your school. (4)</li></ol></div></section>
            @endif
            @if($pageKey !== 'contact')
            <section class="mt-10 rounded-2xl bg-slate-950 p-6 text-white sm:p-8"><h2 class="text-xl font-bold">{{ $copy['coverage_heading'] }}</h2><p class="mt-4 leading-8 text-slate-300">{{ $copy['coverage'] }}</p></section>
            @endif
            @if(!empty($page['questions']))
            <section class="mt-10"><h2 class="text-2xl font-bold">{{ $copy['questions_heading'] }}</h2><div class="mt-5 divide-y rounded-2xl border px-5">@foreach($page['questions'] as $question)<details class="py-5"><summary class="cursor-pointer font-bold text-slate-800">{{ $question['q'] }}</summary><p class="mt-4 leading-8 text-slate-600">{{ $question['a'] }}</p></details>@endforeach</div></section>
            @endif
        </article>
    </main>
    <footer class="border-t bg-slate-50 px-5 pb-24 pt-8"><div class="mx-auto max-w-6xl"><p class="mb-5 font-bold">{{ $copy['explore'] }}</p>@include('public.links')<p class="mt-6 text-sm text-slate-500">© {{ date('Y') }} Exam Builder · {{ $copy['eyebrow'] }}</p></div></footer>
    <a href="https://wa.me/{{ config('subscriptions.whatsapp') }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $copy['whatsapp'] }}" class="fixed bottom-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-600 text-2xl text-white shadow-lg ring-4 ring-white {{ $locale === 'ur' ? 'left-5' : 'right-5' }}"><span aria-hidden="true">✆</span></a>
</body>
</html>
