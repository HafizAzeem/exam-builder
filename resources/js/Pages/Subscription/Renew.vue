<script setup>
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
const props = defineProps({ school: Object, plans: Object, payment: Object });
const page = usePage();
const selected = ref(Object.keys(props.plans)[0]);
const link = computed(() => {
    const plan = props.plans[selected.value];
    const message = `Assalam-o-Alaikum! I have sent payment for Exam Builder.\nSchool: ${props.school.name}\nSchool reference: EB-${props.school.id}\nAccount email: ${page.props.auth.user.email ?? ''}\nPlan: ${plan.label} — PKR ${plan.amount}\nPayment sent to: ${props.payment.number} (${props.payment.name})\nI will attach my payment screenshot here. Please review and activate our school.`;
    return `https://wa.me/${props.payment.whatsapp}?text=${encodeURIComponent(message)}`;
});
</script>

<template>
    <Head title="School subscription" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
            <p class="text-sm font-bold uppercase tracking-widest text-blue-600">Simple plans. Full access.</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Keep your school creating.</h1>
            <p class="mt-3 max-w-2xl text-slate-600">Choose your plan, pay with JazzCash or Easypaisa, and send your screenshot on WhatsApp. We will verify your payment and activate your school’s subscription.</p>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <button v-for="(plan, key) in plans" :key="key" @click="selected = key" :aria-pressed="selected === key" class="rounded-2xl border-2 bg-white p-6 text-left shadow-sm transition" :class="selected === key ? 'border-blue-600 ring-4 ring-blue-50' : 'border-slate-200 hover:border-blue-300'">
                    <span class="text-sm font-semibold text-slate-600">{{ plan.label }}</span>
                    <div class="mt-3 text-3xl font-bold text-slate-950">Rs {{ plan.amount.toLocaleString() }}</div>
                    <p class="mt-3 text-sm text-slate-600">{{ plan.months }} calendar months · All core paper-building tools</p>
                    <span class="mt-4 inline-block text-sm font-bold text-blue-700">{{ selected === key ? '✓ Selected plan' : 'Choose this plan' }}</span>
                </button>
            </div>
            <p class="mt-6 leading-7 text-slate-600">Your school adds its own questions. A complete ready-made question bank is currently not included.</p>
            <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
                <h2 class="text-xl font-bold text-slate-950">Activate in three steps</h2>
                <ol class="mt-6 space-y-5 text-slate-700">
                    <li><strong>1. Send Rs {{ plans[selected].amount.toLocaleString() }}</strong> through JazzCash or Easypaisa.
                        <div class="mt-3 rounded-xl bg-slate-50 p-4"><p class="text-2xl font-bold tracking-wide text-slate-950">{{ payment.number }}</p><p class="mt-1 font-medium">{{ payment.name }}</p><p class="mt-1 text-sm">Same number for JazzCash and Easypaisa.</p></div>
                    </li>
                    <li><strong>2. Share the screenshot on WhatsApp.</strong> Include your school reference <strong>EB-{{ school.id }}</strong>, account email, and chosen plan.</li>
                    <li><strong>3. Wait for manual activation.</strong> Sending a message does not automatically renew your subscription. Your updated expiry will appear here after approval.</li>
                </ol>
                <a :href="link" target="_blank" rel="noopener noreferrer" class="mt-7 inline-flex rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white hover:bg-emerald-700">Share payment proof on WhatsApp</a>
                <p class="mt-3 text-sm text-slate-500">WhatsApp support: 03284177318 · School: {{ school.name }}</p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
