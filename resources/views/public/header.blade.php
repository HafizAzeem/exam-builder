<header class="relative z-30 border-b border-slate-200/80 bg-white shadow-sm">
    <div class="mx-auto flex max-w-screen-2xl items-center justify-between gap-5 px-4 py-4 sm:px-6 xl:px-8">
        <a href="{{ $site->path('home', $locale) }}" class="flex shrink-0 items-center gap-2.5">
            <img src="/logo.png?v=3" width="44" height="44" alt="" class="h-11 w-11 object-contain">
            <span class="whitespace-nowrap text-lg font-extrabold tracking-tight" dir="ltr">Exam Builder</span>
        </a>
        <div class="hidden min-w-0 xl:block">@include('public.links', ['headerNav' => true])</div>
        <div class="flex shrink-0 items-center gap-2 text-sm font-semibold sm:gap-3">
            <a href="{{ $site->path($pageKey, $locale === 'en' ? 'ur' : 'en') }}" lang="{{ $locale === 'en' ? 'ur' : 'en' }}" class="hidden rounded-lg px-2 py-2 text-blue-700 hover:bg-blue-50 sm:block">{{ $locale === 'en' ? 'اردو' : 'English' }}</a>
            <a href="/login" class="hidden rounded-lg px-2 py-2 text-slate-600 hover:bg-slate-50 sm:block">{{ $copy['login'] }}</a>
            <a href="/register" class="whitespace-nowrap rounded-xl bg-blue-600 px-3 py-2.5 text-white shadow-sm transition hover:bg-blue-700 sm:px-4">{{ $copy['trial_short'] }}</a>
            <details class="xl:hidden">
                <summary class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 [&::-webkit-details-marker]:hidden" aria-label="{{ $locale === 'ur' ? 'مینو' : 'Menu' }}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg></summary>
                <div class="absolute inset-x-0 top-full border-b border-slate-200 bg-white p-5 shadow-lg">
                    @include('public.links')
                    <div class="mt-5 flex items-center gap-5 border-t border-slate-100 pt-4 sm:hidden">
                        <a href="/login" class="text-slate-700">{{ $copy['login'] }}</a>
                        <a href="{{ $site->path($pageKey, $locale === 'en' ? 'ur' : 'en') }}" class="text-blue-700" lang="{{ $locale === 'en' ? 'ur' : 'en' }}">{{ $locale === 'en' ? 'اردو' : 'English' }}</a>
                    </div>
                </div>
            </details>
        </div>
    </div>
</header>
