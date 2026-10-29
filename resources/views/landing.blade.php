<!DOCTYPE html>
<html lang="en-PK">
<head>
    @include('public.head')
</head>
<body class="min-h-screen bg-white font-sans text-slate-900 antialiased">
    @include('public.header')

    <main>
        <section class="overflow-hidden bg-gradient-to-br from-blue-50 via-white to-cyan-50">
            <div class="mx-auto grid max-w-7xl items-center gap-12 px-6 py-16 sm:py-20 lg:grid-cols-2 lg:px-8 lg:py-24">
                <div>
                    <p class="mb-5 inline-flex rounded-full border border-blue-100 bg-white px-4 py-1.5 text-sm font-semibold text-blue-700 shadow-sm">Made for teachers and schools in Pakistan</p>
                    <h1 class="max-w-2xl text-4xl font-extrabold leading-tight tracking-tight text-slate-950 sm:text-5xl lg:text-6xl">Online paper generator for schools and academies in Pakistan.</h1>
                    <p class="mt-6 max-w-xl text-lg leading-8 text-slate-600">Add your school’s questions, organise them in a reusable question bank, and create papers for your classes. Edit, save and print your papers in one place.</p>
                    <div class="mt-9 flex flex-wrap gap-4">
                        <a href="/register" class="rounded-xl bg-blue-600 px-6 py-3.5 text-base font-bold text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">{{ $copy['trial'] }}</a>
                        <a href="#how-it-works" class="rounded-xl border border-slate-200 bg-white px-6 py-3.5 text-base font-bold text-slate-700 hover:border-blue-200 hover:text-blue-700">See how it works</a>
                    </div>
                    <p class="mt-4 text-sm text-slate-500">For Pakistani school teachers and education teams.</p>
                </div>
                <div class="relative">
                    <div class="absolute -inset-5 rounded-[2rem] bg-blue-200/40 blur-2xl" aria-hidden="true"></div>
                    <picture>
                        <source type="image/webp" srcset="/exam-builder-dashboard-small.webp 836w, /exam-builder-dashboard.webp 1672w" sizes="(max-width: 1023px) 100vw, 50vw">
                        <img src="/Exam%20Builder%20Education%20Dashboard.png" width="1672" height="940" alt="Exam Builder product illustration showing tools for preparing school question papers" fetchpriority="high" decoding="async" class="relative w-full rounded-2xl border border-white shadow-2xl shadow-blue-900/15">
                    </picture>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-6 py-16 lg:px-8 lg:py-20" aria-labelledby="features-heading">
            <div class="mx-auto max-w-3xl text-center">
                <p class="text-sm font-bold uppercase tracking-wider text-blue-700">A simpler assessment workflow</p>
                <h2 id="features-heading" class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">Everything you need to prepare a paper</h2>
                <p class="mt-4 text-lg leading-8 text-slate-600">Spend less time switching between tools and more time preparing useful assessments for your class.</p>
            </div>
            <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-xl" aria-hidden="true">✎</div>
                    <h3 class="mt-5 text-lg font-bold text-slate-900">Build and format exams</h3>
                    <p class="mt-2 leading-7 text-slate-600">Put together question papers for your classes and subjects, then prepare them for printing.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-50 text-xl" aria-hidden="true">▤</div>
                    <h3 class="mt-5 text-lg font-bold text-slate-900">Keep questions organized</h3>
                    <p class="mt-2 leading-7 text-slate-600">Work with a question bank to find and reuse questions when preparing future assessments.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl" aria-hidden="true">✦</div>
                    <h3 class="mt-5 text-lg font-bold text-slate-900">Add your own questions</h3>
                    <p class="mt-2 leading-7 text-slate-600">Build your question bank with questions added by your school, ready to reuse in future papers.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-xl" aria-hidden="true">▧</div>
                    <h3 class="mt-5 text-lg font-bold text-slate-900">Explore past papers</h3>
                    <p class="mt-2 leading-7 text-slate-600">Use the past-paper tools to support lesson preparation and assessment planning.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-xl" aria-hidden="true">✓</div>
                    <h3 class="mt-5 text-lg font-bold text-slate-900">Review before you print</h3>
                    <p class="mt-2 leading-7 text-slate-600">Check and refine your selected questions and paper layout before sharing an exam with students.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-50 text-xl" aria-hidden="true">▣</div>
                    <h3 class="mt-5 text-lg font-bold text-slate-900">Return to saved papers</h3>
                    <p class="mt-2 leading-7 text-slate-600">Keep your work together in your account and revisit saved papers and exam history when needed.</p>
                </article>
            </div>
        </section>

        <section id="how-it-works" class="bg-slate-50">
            <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8 lg:py-20">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="text-sm font-bold uppercase tracking-wider text-blue-700">How it works</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">From question selection to classroom-ready paper</h2>
                </div>
                <ol class="mx-auto mt-12 grid max-w-5xl gap-8 md:grid-cols-3">
                    <li class="rounded-2xl bg-white p-7 shadow-sm">
                        <span class="text-sm font-extrabold text-blue-700">STEP 01</span>
                        <h3 class="mt-3 text-lg font-bold">Choose your questions</h3>
                        <p class="mt-2 leading-7 text-slate-600">Choose questions your school has added to its question bank.</p>
                    </li>
                    <li class="rounded-2xl bg-white p-7 shadow-sm">
                        <span class="text-sm font-extrabold text-blue-700">STEP 02</span>
                        <h3 class="mt-3 text-lg font-bold">Review and arrange</h3>
                        <p class="mt-2 leading-7 text-slate-600">Check the questions, make edits where needed and organize the paper for your class.</p>
                    </li>
                    <li class="rounded-2xl bg-white p-7 shadow-sm">
                        <span class="text-sm font-extrabold text-blue-700">STEP 03</span>
                        <h3 class="mt-3 text-lg font-bold">Save and prepare to print</h3>
                        <p class="mt-2 leading-7 text-slate-600">Keep the paper in your account and use the editor to get it ready for classroom use.</p>
                    </li>
                </ol>
            </div>
        </section>

        <section class="mx-auto max-w-4xl px-6 py-16 lg:py-20" aria-labelledby="faq-heading">
            <div class="text-center">
                <p class="text-sm font-bold uppercase tracking-wider text-blue-700">Frequently asked questions</p>
                <h2 id="faq-heading" class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">About Exam Builder</h2>
            </div>
            <div class="mt-10 divide-y divide-slate-200 border-y border-slate-200">
                <details class="group py-5" open>
                    <summary class="cursor-pointer list-none pr-8 text-lg font-bold text-slate-900 marker:hidden">What is Exam Builder?</summary>
                    <p class="mt-3 leading-7 text-slate-600">Exam Builder is an online workspace for teachers and schools to create, organize, review and print exams and question papers.</p>
                </details>
                <details class="group py-5">
                    <summary class="cursor-pointer list-none pr-8 text-lg font-bold text-slate-900 marker:hidden">Who is Exam Builder designed for?</summary>
                    <p class="mt-3 leading-7 text-slate-600">It is designed for teachers and schools in Pakistan who need a focused way to prepare and manage school assessments.</p>
                </details>
                <details class="group py-5">
                    <summary class="cursor-pointer list-none pr-8 text-lg font-bold text-slate-900 marker:hidden">Does Exam Builder include a ready-made question bank?</summary>
                    <p class="mt-3 leading-7 text-slate-600">Your school adds its own questions to build a reusable question bank. A complete ready-made syllabus question bank is currently not included.</p>
                </details>
                <details class="group py-5">
                    <summary class="cursor-pointer list-none pr-8 text-lg font-bold text-slate-900 marker:hidden">Can I save and print my papers?</summary>
                    <p class="mt-3 leading-7 text-slate-600">Yes. You can save and manage papers in your account and use the paper tools to prepare print-ready exams.</p>
                </details>
            </div>
        </section>

        @include('public.pricing')
        <section id="contact" class="mx-auto max-w-6xl px-6 py-16 lg:py-20" aria-labelledby="contact-heading">
            <div class="flex flex-col items-start justify-between gap-7 rounded-3xl bg-slate-950 p-7 text-white sm:p-10 md:flex-row md:items-center">
                <div class="max-w-xl">
                    <p class="text-sm font-bold uppercase tracking-wider text-blue-300">We're here to help</p>
                    <h2 id="contact-heading" class="mt-3 text-3xl font-bold">A little help goes a long way.</h2>
                    <p class="mt-4 leading-7 text-slate-300">Having an issue with Exam Builder or have an idea for a new feature? Send us a message on WhatsApp. We’d love to hear your feedback and suggestions.</p>
                </div>
                <a href="https://wa.me/923284177318" target="_blank" rel="noopener noreferrer" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-emerald-500 px-6 py-3.5 font-bold text-slate-950 hover:bg-emerald-400">Chat on WhatsApp <span aria-hidden="true">↗</span></a>
            </div>
        </section>
        <div class="mx-auto max-w-6xl px-6 pb-16">@include('public.hire')</div>
        <section class="bg-blue-700">
            <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-6 px-6 py-12 sm:flex-row sm:items-center lg:px-8">
                <div>
                    <h2 class="text-2xl font-extrabold text-white sm:text-3xl">Make your next exam easier to prepare.</h2>
                    <p class="mt-2 text-blue-100">Bring your paper-building workflow together with Exam Builder.</p>
                </div>
                <a href="/register" class="shrink-0 rounded-xl bg-white px-6 py-3.5 font-bold text-blue-700 shadow-sm hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-700">Start your free trial</a>
            </div>
        </section>
    </main>

    <section class="mx-auto max-w-6xl px-6 py-10"><h2 class="mb-4 text-xl font-bold text-slate-950">Explore paper preparation tools and guidance</h2>@include('public.links')<div class="mt-6 rounded-xl bg-slate-50 p-5"><h3 class="font-bold">Check curriculum coverage before you start</h3><p class="mt-3 leading-7 text-slate-600">{{ $copy['coverage'] }}</p></div></section>
    <footer class="border-t border-slate-100 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-7 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between lg:px-8">
            <p>&copy; {{ date('Y') }} Exam Builder. Built for teachers and schools in Pakistan.</p>
            <nav class="flex flex-wrap gap-5" aria-label="Footer navigation">
                <a href="/register" class="hover:text-blue-700">Start free</a>
                <a href="#pricing" class="hover:text-blue-700">Pricing</a>
                <a href="#contact" class="hover:text-blue-700">Contact</a>
                <a href="/login" class="hover:text-blue-700">Log in</a>
            </nav>
        </div>
    </footer>
    <a href="https://wa.me/923284177318" target="_blank" rel="noopener noreferrer" aria-label="Contact on WhatsApp" class="fixed bottom-5 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-600 text-white shadow-lg ring-4 ring-white hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-300" style="bottom: max(1.25rem, env(safe-area-inset-bottom));">
        <svg viewBox="0 0 32 32" class="h-8 w-8" fill="currentColor" aria-hidden="true"><path d="M16 .8A15 15 0 0 0 3 23.3L.9 31l7.9-2.1A15.2 15.2 0 1 0 16 .8Zm0 27.5c-2.3 0-4.6-.6-6.5-1.8l-.5-.3-4.7 1.3 1.3-4.6-.3-.5A12.5 12.5 0 1 1 16 28.3Zm6.9-9.4c-.4-.2-2.2-1.1-2.6-1.2-.3-.1-.6-.2-.8.2-.3.4-1 1.2-1.2 1.4-.2.2-.4.3-.8.1-.4-.2-1.6-.6-3.1-1.9-1.1-1-1.9-2.2-2.1-2.6-.2-.4 0-.6.2-.8l.6-.7.4-.6c.1-.2.1-.5 0-.7l-1.2-2.8c-.3-.7-.6-.6-.8-.6h-.7c-.3 0-.7.1-1 .5s-1.3 1.3-1.3 3.1 1.4 3.5 1.6 3.7c.2.2 2.7 4.1 6.5 5.7.9.4 1.6.6 2.2.8.9.3 1.8.2 2.4.1.7-.1 2.2-.9 2.5-1.8.3-.9.3-1.7.2-1.8-.1-.2-.4-.3-.8-.5Z" /></svg>
    </a>
</body>
</html>
