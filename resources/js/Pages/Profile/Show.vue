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
    { id: 'profile', label: 'Profile', hint: 'Photo, name and email' },
    { id: 'security', label: 'Security', hint: 'Password and two-factor' },
    { id: 'devices', label: 'Devices', hint: 'Where you are signed in' },
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
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Account summary -->
                <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 flex items-center gap-4 sm:gap-5">
                    <img
                        v-if="$page.props.jetstream.managesProfilePhotos"
                        :src="user.profile_photo_url"
                        :alt="user.name"
                        class="size-16 sm:size-20 shrink-0 rounded-full object-cover ring-4 ring-indigo-50"
                    >
                    <div class="min-w-0 flex-1">
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

                <!-- Tabs -->
                <div class="grid grid-cols-3 gap-1 rounded-xl bg-gray-100 p-1" role="tablist" aria-label="Account settings">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        role="tab"
                        :aria-selected="active === tab.id"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="active === tab.id ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                        @click="select(tab.id)"
                    >
                        {{ tab.label }}
                        <span class="hidden sm:block text-xs font-normal text-gray-400">{{ tab.hint }}</span>
                    </button>
                </div>

                <!-- v-show keeps unsaved edits when switching tabs -->
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
    </AppLayout>
</template>
