<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage, useForm } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();
const live = ref(null);
const access = computed(() => live.value ?? page.props.access ?? {});
const form = useForm({});
const message = ref('');
let timer;
const date = (value) => value ? new Date(value).toLocaleString('en-PK', { dateStyle: 'medium', timeStyle: 'short' }) : '';
const resend = () => form.post(route('verification.send'), {
    preserveScroll: true,
    onSuccess: () => { message.value = page.props.flash?.error ? '' : 'Verification link sent. Please check your inbox and spam folder.'; },
    onError: () => { message.value = 'Please wait before requesting another verification email.'; },
});
async function refresh() {
    if (document.hidden) return;
    try {
        const { data } = await axios.get(route('access.status'));
        live.value = data;
        if (data.verification?.blocked && !route().current('profile.*') && !route().current('verification.*')) {
            router.visit(route('verification.notice'));
        } else if (data.subscription?.expired && !route().current('subscription.show') && !route().current('dashboard') && !route().current('saved-papers.*') && !route().current('paper-history.*') && !route().current('editor.*') && !route().current('profile.*') && !route().current('admin.profile')) {
            router.visit(route('subscription.show'));
        }
    } catch { /* Server checks every protected action even when this refresh fails. */ }
}
onMounted(() => { timer = setInterval(refresh, 30000); document.addEventListener('visibilitychange', refresh); });
onUnmounted(() => { clearInterval(timer); document.removeEventListener('visibilitychange', refresh); });
</script>

<template>
    <div v-if="page.props.mailReady === false" class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-950" role="status">
        Email delivery needs configuration. Production email-verification blocking is disabled until a delivery-capable mailer is configured. See the deployment guide.
    </div>
    <div v-if="access.verification" class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-950" role="status">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2">
            <p><strong>Your email is not verified.</strong> Verify {{ page.props.auth.user.email }} to keep using Exam Builder.
                <span v-if="access.verification.enforced"> Please verify by {{ date(access.verification.due_at) }}.</span>
            </p>
            <div class="flex flex-wrap items-center gap-4">
                <Link :href="route('profile.edit')" class="underline">Update email</Link>
                <button :disabled="form.processing" @click="resend" class="font-semibold underline disabled:opacity-50">{{ form.processing ? 'Sending…' : 'Resend verification' }}</button>
            </div>
            <p v-if="message" class="w-full">{{ message }}</p>
        </div>
    </div>
    <div v-if="access.subscription" :class="access.subscription.expired ? 'border-red-200 bg-red-50 text-red-950' : (access.subscription.expiring_soon ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-blue-100 bg-blue-50 text-blue-950')" class="border-b px-5 py-3 text-sm" role="status">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2">
            <p v-if="access.subscription.expired"><strong>Your school access has expired.</strong> Renew to create and edit papers. Saved papers remain available to view and print.</p>
            <p v-else><strong>{{ access.subscription.plan === 'trial' ? 'Free trial' : 'School subscription' }}</strong> · Full access until {{ date(access.subscription.expires_at) }}.<span v-if="access.subscription.expiring_soon"> Renew soon to keep creating papers.</span></p>
            <Link :href="route('subscription.show')" class="shrink-0 font-semibold underline">{{ access.subscription.expired ? 'Renew access' : 'View plans & renewal' }}</Link>
        </div>
    </div>
</template>
