<script setup>
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const memberSince = computed(() => user.value.created_at
    ? new Date(user.value.created_at).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
    : null);

const tabs = [
    { id: 'profile', label: 'Profile', hint: 'Photo, name and email', icon: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z' },
    { id: 'security', label: 'Security', hint: 'Password and two-factor', icon: 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z' },
    { id: 'devices', label: 'Devices', hint: 'Where you are signed in', icon: 'M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25' },
];

// The tab can be linked to directly, e.g. /user/profile#security.
const active = ref('profile');
const select = (id) => {
    active.value = id;
    history.replaceState(null, '', `#${id}`);
};

onMounted(() => {
    const fromHash = window.location.hash.slice(1);
    if (tabs.some((tab) => tab.id === fromHash)) active.value = fromHash;
});
</script>

<template>
    <AppLayout title="Profile">
        <div class="py-6 sm:py-8">
            <!-- Settings layout: on wide screens, who you are and the section menu on the left (sticky),
                 the section itself on the right. Phones stack them with a tab strip. -->
            <div class="page grid gap-6 lg:grid-cols-12 items-start">
                <div class="space-y-6 lg:col-span-4 xl:col-span-3 lg:sticky lg:top-24">
                <!-- Account summary -->
                <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 flex items-center gap-4 sm:gap-5 lg:flex-col lg:items-start">
                    <img
                        v-if="$page.props.jetstream.managesProfilePhotos"
                        :src="user.profile_photo_url"
                        :alt="user.name"
                        class="size-16 sm:size-20 shrink-0 rounded-full object-cover"
                    >
                    <div class="min-w-0 flex-1 lg:w-full">
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 truncate">{{ user.name }}</h1>
                        <p class="text-sm text-gray-500 truncate">{{ user.email }}</p>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <span v-if="memberSince" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-gray-600">Member since {{ memberSince }}</span>
                            <span
                                v-if="$page.props.jetstream.canManageTwoFactorAuthentication"
                                class="rounded-full px-2.5 py-0.5 font-medium"
                                :class="user.two_factor_enabled
                                    ? 'bg-indigo-50 text-indigo-600'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200'"
                            >
                                {{ user.two_factor_enabled ? 'Two-factor on' : 'Two-factor off' }}
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Sections: a tab strip on phones and tablets, a vertical menu beside the content on wide screens. -->
                <div class="grid grid-cols-3 gap-1 rounded-xl bg-gray-100 p-1 lg:grid-cols-1 lg:bg-white lg:border lg:border-gray-200 lg:rounded-2xl lg:p-2" role="tablist" aria-label="Account settings" aria-orientation="vertical">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        role="tab"
                        :aria-selected="active === tab.id"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition lg:flex lg:items-center lg:gap-3 lg:px-3 lg:py-2.5 lg:text-start"
                        :class="active === tab.id
                            ? 'bg-white text-gray-900 shadow-sm lg:bg-indigo-50 lg:text-indigo-600 lg:shadow-none'
                            : 'text-gray-500 hover:text-gray-800 lg:hover:bg-gray-50'"
                        @click="select(tab.id)"
                    >
                        <svg class="hidden lg:block size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
                        </svg>
                        <span>
                            {{ tab.label }}
                            <span class="hidden sm:block text-xs font-normal text-gray-400">{{ tab.hint }}</span>
                        </span>
                    </button>
                </div>
                </div>

                <!-- v-show keeps unsaved edits when switching tabs -->
                <div class="lg:col-span-8 xl:col-span-9 min-w-0">
                <div v-show="active === 'profile'" class="space-y-6" role="tabpanel">
                    <UpdateProfileInformationForm v-if="$page.props.jetstream.canUpdateProfileInformation" :user="user" />
                </div>

                <div v-show="active === 'security'" class="space-y-6" role="tabpanel">
                    <UpdatePasswordForm v-if="$page.props.jetstream.canUpdatePassword" />
                    <TwoFactorAuthenticationForm
                        v-if="$page.props.jetstream.canManageTwoFactorAuthentication"
                        :requires-confirmation="confirmsTwoFactorAuthentication"
                    />
                </div>

                <div v-show="active === 'devices'" class="space-y-6" role="tabpanel">
                    <LogoutOtherBrowserSessionsForm :sessions="sessions" />
                </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
