<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    stats: Object,
    institution: Object,
    message: String,
});

const page = usePage();

const isAdminUser = computed(() =>
    page.props.auth.user?.roles?.includes('institution_admin')
);

const profileHref = computed(() =>
    isAdminUser.value ? route('admin.profile') : route('profile.edit')
);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
                    <p class="mt-1 text-sm text-gray-500">Manage papers, teachers, and institution settings</p>
                </div>
                <Link
                    :href="route('builder')"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Generate Paper
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div v-if="message" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">
                    {{ message }}
                </div>

                <div class="flex flex-col gap-8 lg:flex-row">
                    <aside v-if="institution" class="lg:w-72 lg:shrink-0">
                        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                            <div class="bg-gradient-to-br from-slate-800 to-slate-900 px-6 py-5 text-white">
                                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Account</p>
                                <p class="mt-1 text-lg font-semibold">{{ institution.name }}</p>
                            </div>
                            <div class="space-y-4 p-6">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Status</span>
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="institution.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="institution.is_active ? 'bg-emerald-500' : 'bg-red-500'" />
                                        {{ institution.license_label }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Expiry</span>
                                    <span class="text-sm font-medium text-gray-900">{{ institution.expiry_date }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Package</span>
                                    <span class="text-sm font-medium capitalize text-gray-900">{{ institution.license_type }}</span>
                                </div>
                                <div v-if="institution.logo_url" class="flex justify-center pt-2">
                                    <img
                                        :src="institution.logo_url"
                                        :alt="institution.name"
                                        class="h-20 w-20 rounded-full border-2 border-gray-100 object-contain p-1"
                                    />
                                </div>
                                <Link
                                    :href="profileHref"
                                    class="mt-2 block w-full rounded-lg border border-gray-200 px-4 py-2 text-center text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                                >
                                    Profile Settings
                                </Link>
                            </div>
                        </div>
                    </aside>

                    <div class="min-w-0 flex-1">
                        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                            <StatCard
                                title="Generate Paper"
                                subtitle="Total Papers Generated"
                                :count="stats.papersGenerated"
                                :href="route('builder')"
                                gradient="from-blue-500 via-indigo-500 to-indigo-600"
                                icon="document"
                            />
                            <StatCard
                                title="Saved Papers"
                                subtitle="Total Saved Papers"
                                :count="stats.savedPapers"
                                :href="route('saved-papers.index')"
                                gradient="from-emerald-500 to-teal-600"
                                icon="folder"
                            />
                            <StatCard
                                title="Past Papers"
                                subtitle="Board exam archive"
                                :count="stats.pastPapers"
                                :href="route('past-papers.index')"
                                gradient="from-violet-600 to-purple-700"
                                icon="archive"
                            />
                            <StatCard
                                v-if="isAdminUser"
                                title="Teachers"
                                subtitle="Total Teachers"
                                :count="stats.teachers"
                                :href="route('admin.teachers')"
                                gradient="from-sky-600 to-blue-700"
                                icon="users"
                            />
                            <StatCard
                                title="Paper History"
                                subtitle="Total Papers Created"
                                :count="stats.paperHistory"
                                :href="route('paper-history.index')"
                                gradient="from-slate-600 to-slate-800"
                                icon="clock"
                            />
                            <StatCard
                                v-if="isAdminUser"
                                title="Login History"
                                subtitle="Total Logins"
                                :count="stats.loginHistory"
                                :href="route('admin.login-history')"
                                gradient="from-teal-600 to-emerald-700"
                                icon="shield"
                            />
                            <StatCard
                                title="Profile Settings"
                                subtitle="Institution & print header"
                                :count="null"
                                :href="profileHref"
                                gradient="from-rose-500 to-pink-600"
                                icon="settings"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
