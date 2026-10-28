<nav aria-label="{{ $copy['explore'] }}" class="flex flex-wrap items-center gap-x-5 gap-y-3 text-sm">
    @foreach(\App\Support\PublicSite::PATHS as $key => $slug)
        @continue($key === 'ai')
        <a href="{{ $site->path($key, $locale) }}" @if($pageKey === $key) aria-current="page" @endif class="font-medium {{ $pageKey === $key ? 'text-blue-700 underline underline-offset-4' : 'text-slate-600 hover:text-blue-700' }}">{{ $copy['pages'][$key]['nav'] }}</a>
    @endforeach
</nav>
