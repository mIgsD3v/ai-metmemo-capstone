<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SidebarLink from '@/Components/SidebarLink.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps({
    title: String,
});

const icons = {
    home: 'M2.25 12 11.204 3.045c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
    contacts: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
    scan: 'M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z',
    cards: 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z',
    profile: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
    logout: 'M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9',
    key: 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z',
};

// One list drives both the desktop sidebar and the phone tab bar.
const nav = [
    { label: 'Home', sidebarLabel: 'Dashboard', route: 'dashboard', match: 'dashboard', icon: icons.home },
    { label: 'Contacts', route: 'contacts.index', match: 'contacts.*', icon: icons.contacts },
    { label: 'Scan', sidebarLabel: 'Scan a card', route: 'scan.create', match: 'scan.*', icon: icons.scan, primary: true },
    { label: 'Cards', sidebarLabel: 'My Cards', route: 'cards.index', match: 'cards.*', icon: icons.cards },
    { label: 'Profile', route: 'profile.show', match: 'profile.show', icon: icons.profile, mobileOnly: true },
];

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div>
        <Head :title="title" />

        <div class="min-h-screen bg-page">
            <!-- Phone top bar -->
            <div class="lg:hidden sticky top-0 z-30 flex items-center justify-between h-14 px-4 bg-page/85 backdrop-blur border-b border-gray-200">
                <Link :href="route('dashboard')" aria-label="MetMemo home">
                    <ApplicationMark class="!text-2xl" />
                </Link>

                <div class="flex items-center gap-1">
                    <ThemeToggle />
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button type="button" class="flex rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Account menu">
                                <img
                                    v-if="$page.props.jetstream.managesProfilePhotos"
                                    class="size-8 rounded-full object-cover"
                                    :src="$page.props.auth.user.profile_photo_url"
                                    :alt="$page.props.auth.user.name"
                                >
                                <span v-else class="size-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm font-semibold">
                                    {{ $page.props.auth.user.name.charAt(0) }}
                                </span>
                            </button>
                        </template>
                        <template #content>
                            <div class="px-4 py-2 border-b border-gray-200">
                                <div class="text-sm font-medium text-gray-800 truncate">{{ $page.props.auth.user.name }}</div>
                                <div class="text-xs text-gray-500 truncate">{{ $page.props.auth.user.email }}</div>
                            </div>
                            <DropdownLink :href="route('profile.show')">Profile</DropdownLink>
                            <form @submit.prevent="logout">
                                <DropdownLink as="button">Log Out</DropdownLink>
                            </form>
                        </template>
                    </Dropdown>
                </div>
            </div>

            <!-- Desktop sidebar -->
            <aside class="hidden lg:flex fixed inset-y-0 start-0 z-40 w-64 flex-col bg-white border-e border-gray-200">
                <div class="flex items-center h-16 px-5 shrink-0">
                    <Link :href="route('dashboard')" aria-label="MetMemo home">
                        <ApplicationMark class="block" />
                    </Link>
                </div>

                <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                    <SidebarLink
                        v-for="item in nav.filter((item) => ! item.mobileOnly)"
                        :key="item.route"
                        :href="route(item.route)"
                        :active="route().current(item.match)"
                    >
                        <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                        {{ item.sidebarLabel ?? item.label }}
                    </SidebarLink>
                </nav>

                <div class="shrink-0 border-t border-gray-200 px-3 py-4 space-y-1">
                    <div class="flex items-center gap-3 px-3 pb-2">
                        <img
                            v-if="$page.props.jetstream.managesProfilePhotos"
                            class="size-9 shrink-0 rounded-full object-cover"
                            :src="$page.props.auth.user.profile_photo_url"
                            :alt="$page.props.auth.user.name"
                        >
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium text-gray-800 truncate">{{ $page.props.auth.user.name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ $page.props.auth.user.email }}</div>
                        </div>
                        <ThemeToggle class="shrink-0" />
                    </div>

                    <SidebarLink :href="route('profile.show')" :active="route().current('profile.show')">
                        <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons.profile" />
                        </svg>
                        Profile
                    </SidebarLink>

                    <SidebarLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')" :active="route().current('api-tokens.index')">
                        <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons.key" />
                        </svg>
                        API Tokens
                    </SidebarLink>

                    <form @submit.prevent="logout">
                        <SidebarLink as="button">
                            <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="icons.logout" />
                            </svg>
                            Log Out
                        </SidebarLink>
                    </form>
                </div>
            </aside>

            <div class="lg:ps-64 pb-24 lg:pb-0">
                <!-- Page heading -->
                <header v-if="$slots.header" class="border-b border-gray-200">
                    <div class="max-w-7xl mx-auto py-5 lg:py-6 px-4 sm:px-6 lg:px-8">
                        <slot name="header" />
                    </div>
                </header>

                <main>
                    <slot />
                </main>
            </div>

            <!-- Phone tab bar -->
            <nav
                class="lg:hidden fixed inset-x-0 bottom-0 z-40 bg-white/95 backdrop-blur border-t border-gray-200"
                style="padding-bottom: env(safe-area-inset-bottom, 0px)"
                aria-label="Main"
            >
                <div class="grid grid-cols-5 h-16 max-w-md mx-auto">
                    <Link
                        v-for="item in nav"
                        :key="item.route"
                        :href="route(item.route)"
                        class="relative flex flex-col items-center justify-center gap-1 text-[11px] font-medium transition"
                        :class="route().current(item.match) ? 'text-indigo-600' : 'text-gray-500 hover:text-gray-800'"
                        :aria-current="route().current(item.match) ? 'page' : undefined"
                    >
                        <template v-if="item.primary">
                            <span class="-mt-7 flex items-center justify-center size-14 rounded-full bg-accent text-accent-fg shadow-lg ring-4 ring-page">
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                                </svg>
                            </span>
                            <span>{{ item.label }}</span>
                        </template>
                        <template v-else>
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                            </svg>
                            <span>{{ item.label }}</span>
                        </template>
                    </Link>
                </div>
            </nav>
        </div>
    </div>
</template>
