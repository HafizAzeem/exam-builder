<script setup>
import { onUnmounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import InputError from '@/Components/InputError.vue';
defineProps({ boards: Array, trialDays: Number });
const preview = ref(null);
const form = useForm({ name: '', school_name: '', email: '', phone: '', board_id: '', logo: null, password: '', password_confirmation: '' });
function chooseLogo(event) {
    if (preview.value) URL.revokeObjectURL(preview.value);
    form.logo = event.target.files[0] ?? null;
    preview.value = form.logo ? URL.createObjectURL(form.logo) : null;
}
onUnmounted(() => { if (preview.value) URL.revokeObjectURL(preview.value); });
const submit = () => form.post(route('register'), { forceFormData: true, onFinish: () => form.reset('password', 'password_confirmation') });
const fields = [
    { key: 'name', label: 'Your name', autocomplete: 'name', type: 'text', placeholder: 'School owner’s full name' },
    { key: 'school_name', label: 'School name', autocomplete: 'organization', type: 'text', placeholder: 'Your school or academy' },
    { key: 'email', label: 'Email address', autocomplete: 'email', type: 'email', placeholder: 'you@example.com' },
    { key: 'phone', label: 'WhatsApp number', autocomplete: 'tel', type: 'tel', placeholder: '03XXXXXXXXX' },
];
</script>
<template>
    <Head title="Start your school’s free trial" />
    <div class="min-h-screen bg-slate-50">
        <header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-6">
            <a href="/" class="flex items-center gap-3 font-bold text-slate-950"><ApplicationLogo class="h-12 w-12" /><span>Exam Builder</span></a>
            <Link :href="route('login')" class="text-sm font-semibold text-blue-700">Log in</Link>
        </header>
        <main class="mx-auto grid max-w-6xl gap-10 px-5 pb-12 pt-5 lg:grid-cols-5 lg:items-start lg:pt-10">
            <section class="lg:col-span-2 lg:pt-8">
                <span class="inline-flex rounded-full border border-blue-100 bg-blue-50 px-4 py-2 text-xs font-bold uppercase tracking-wider text-blue-700">{{ trialDays }} days free · No payment required</span>
                <h1 class="mt-6 text-4xl font-bold leading-tight tracking-tight text-slate-950 lg:text-5xl">Your school’s next great exam starts here.</h1>
                <p class="mt-5 text-lg leading-relaxed text-slate-600">Create papers, explore a shared question bank, and give your teachers more time to teach.</p>
                <ul class="mt-7 space-y-4 text-slate-700"><li>✓ Add and organise your school’s own questions</li><li>✓ Your school name and logo on papers</li><li>✓ Add teachers to your school workspace</li><li>✓ Choose your plan and renew manually</li></ul>
                <p class="mt-8 text-sm leading-relaxed text-slate-500">Made for teachers and schools in Pakistan. Need help? <a href="https://wa.me/923284177318" class="font-semibold text-emerald-700 underline" target="_blank" rel="noopener noreferrer">Talk to us on WhatsApp.</a></p>
            </section>
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/40 sm:p-8 lg:col-span-3">
                <h2 class="text-2xl font-bold text-slate-950">Create your school account</h2>
                <p class="mt-2 text-sm text-slate-500">You’ll be the school administrator. Add teachers after signup.</p>
                <form @submit.prevent="submit" class="mt-7 space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div v-for="field in fields" :key="field.key">
                            <label :for="field.key" class="block text-sm font-semibold text-slate-700">{{ field.label }}</label>
                            <input :id="field.key" v-model="form[field.key]" :type="field.type" :autocomplete="field.autocomplete" :placeholder="field.placeholder" required maxlength="255" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                            <InputError class="mt-1" :message="form.errors[field.key]" />
                        </div>
                    </div>
                    <div>
                        <label for="board_id" class="block text-sm font-semibold text-slate-700">Education board</label>
                        <select id="board_id" v-model="form.board_id" required class="mt-2 w-full rounded-xl border-slate-300 text-sm"><option value="" disabled>Select your board</option><option v-for="board in boards" :key="board.id" :value="board.id">{{ board.name }}</option></select>
                        <p class="mt-1 text-xs text-slate-500">Used to track your school’s contributions to the question bank.</p>
                        <InputError :message="form.errors.board_id" class="mt-1" />
                    </div>
                    <div class="rounded-xl border border-dashed border-slate-300 p-4">
                        <label for="logo" class="block text-sm font-semibold text-slate-700">School logo <span class="font-normal text-slate-400">(optional)</span></label>
                        <div class="mt-3 flex flex-wrap items-center gap-4"><img v-if="preview" :src="preview" alt="School logo preview" class="h-16 w-16 rounded-lg border object-contain p-1" /><input id="logo" type="file" accept="image/png,image/jpeg,image/webp" @change="chooseLogo" class="w-full min-w-0 text-sm text-slate-600" /></div>
                        <p class="mt-2 text-xs text-slate-500">PNG, JPEG or WebP, up to 2 MB. You can add it later.</p><InputError :message="form.errors.logo" class="mt-1" />
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div><label for="password" class="text-sm font-semibold text-slate-700">Password</label><input id="password" v-model="form.password" type="password" autocomplete="new-password" required minlength="8" class="mt-2 w-full rounded-xl border-slate-300" /><InputError :message="form.errors.password" class="mt-1" /></div>
                        <div><label for="password_confirmation" class="text-sm font-semibold text-slate-700">Confirm password</label><input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border-slate-300" /></div>
                    </div>
                    <p class="rounded-xl bg-blue-50 p-4 text-xs leading-relaxed text-blue-950">Questions you and your teachers contribute are immediately shared with other schools using the same class and curriculum book. Your saved papers stay private to your school. We’ll email you a verification link; please verify within one hour.</p>
                    <button :disabled="form.processing" class="w-full rounded-xl bg-blue-600 px-5 py-3.5 font-bold text-white hover:bg-blue-700 disabled:opacity-50">{{ form.processing ? 'Creating your school…' : `Start my ${trialDays}-day free trial` }}</button>
                    <p class="text-center text-xs text-slate-500">No automatic charges. Renew only when you choose.</p>
                </form>
            </section>
        </main>
    </div>
</template>
