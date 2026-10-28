<section id="pricing" class="border-y border-slate-100 bg-slate-50 px-5 py-14 sm:py-16" aria-labelledby="pricing-heading">
    <div class="mx-auto max-w-6xl">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-sm font-bold uppercase tracking-wider text-blue-700">{{ $copy['pricing_ui']['eyebrow'] }}</p>
            <h2 id="pricing-heading" class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">{{ $copy['pricing_ui']['heading'] }}</h2>
            <p class="mt-4 leading-7 text-slate-600">{{ $copy['pricing_ui']['intro'] }}</p>
        </div>
        <div class="mt-8 flex flex-wrap items-center justify-between gap-5 rounded-2xl border border-blue-200 bg-blue-50 p-6">
            <div><h3 class="text-xl font-bold text-slate-950">{{ $copy['trial'] }}</h3><p class="mt-2 text-sm leading-7 text-slate-600">{{ $copy['pricing_ui']['trial_note'] }}</p></div>
            <a href="/register" class="rounded-xl bg-blue-600 px-5 py-3 font-bold text-white hover:bg-blue-700">{{ $copy['trial_short'] }}</a>
        </div>
        <div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach($plans as $plan)
            <article class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                <h3 class="text-lg font-bold text-slate-900">{{ $locale === 'ur' ? $plan['label_ur'] : $plan['label'] }}</h3>
                <p class="mt-4 text-4xl font-extrabold tracking-tight text-slate-950" dir="ltr">Rs {{ number_format($plan['amount']) }}</p>
                <p class="mt-3 text-sm text-slate-600">{{ str_replace(':months', $plan['months'], $copy['pricing_ui'][$plan['months'] === 1 ? 'duration_one' : 'duration']) }}</p>
                <p class="mt-5 leading-7 text-slate-600">{{ $copy['pricing_ui']['same_tools'] }}</p>
                <a href="/register" class="mt-6 block rounded-xl border border-blue-200 px-5 py-3 text-center font-bold text-blue-700 hover:bg-blue-50">{{ $copy['trial_short'] }}</a>
            </article>
            @endforeach
        </div>
        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-7"><h3 class="text-xl font-bold">{{ $copy['pricing_ui']['features_heading'] }}</h3><ul class="mt-5 space-y-3 text-slate-700">@foreach($copy['pricing_ui']['features'] as $feature)<li class="flex gap-3"><span class="text-emerald-700" aria-hidden="true">✓</span><span>{{ $feature }}</span></li>@endforeach</ul></section>
            <section class="rounded-2xl border border-blue-100 bg-blue-50 p-6 sm:p-7"><h3 class="text-xl font-bold">{{ $copy['pricing_ui']['own_heading'] }}</h3><p class="mt-4 leading-8 text-slate-700">{{ $copy['pricing_ui']['own_text'] }}</p><p class="mt-5 text-sm leading-7 text-slate-600">{{ $copy['pricing_ui']['no_charges'] }}</p></section>
        </div>
        <section class="mt-8 rounded-2xl border border-emerald-200 bg-white p-6 sm:p-7">
            <h3 class="text-xl font-bold">{{ $copy['payment_heading'] }}</h3>
            <div class="mt-4 flex flex-wrap items-start justify-between gap-5"><div><p class="text-lg font-bold" dir="ltr">JazzCash / Easypaisa: {{ config('subscriptions.payment_number') }}</p><p class="mt-1 text-slate-600">{{ config('subscriptions.account_name') }}</p></div><a href="https://wa.me/{{ config('subscriptions.whatsapp') }}" target="_blank" rel="noopener noreferrer" class="rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white">{{ $copy['whatsapp'] }}</a></div>
            <p class="mt-5 max-w-4xl text-sm leading-7 text-slate-600">{{ $copy['payment_instructions'] }}</p>
        </section>
    </div>
</section>
