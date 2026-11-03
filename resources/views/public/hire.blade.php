<section class="flex flex-col items-start justify-between gap-6 rounded-2xl border border-blue-200 bg-blue-50 p-6 sm:p-8 lg:flex-row lg:items-center" aria-labelledby="hire-heading">
    <div class="max-w-2xl">
        <p class="text-sm font-bold text-blue-700">{{ $copy['hire']['eyebrow'] }}</p>
        <h2 id="hire-heading" class="mt-3 text-2xl font-bold text-slate-950 sm:text-3xl">{{ $copy['hire']['heading'] }}</h2>
        <p class="mt-4 leading-8 text-slate-600">{{ $copy['hire']['text'] }}</p>
        <p class="mt-4 text-sm font-medium text-slate-500">{{ $copy['hire']['name'] }}</p>
    </div>
    <a href="https://wa.me/{{ config('subscriptions.whatsapp') }}?text={{ rawurlencode($copy['hire']['message']) }}" target="_blank" rel="noopener noreferrer" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white hover:bg-emerald-700">{{ $copy['hire']['button'] }} <span aria-hidden="true">↗</span></a>
</section>
