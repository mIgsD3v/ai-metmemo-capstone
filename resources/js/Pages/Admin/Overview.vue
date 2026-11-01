<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminTabs from '@/Components/AdminTabs.vue';
import BarList from '@/Components/Charts/BarList.vue';
import StackedColumns from '@/Components/Charts/StackedColumns.vue';
import { can } from '@/permissions';

const props = defineProps({
    stats: Object,
    days: Number,
});

const users = computed(() => props.stats.users);
const percent = (part, whole) => (whole ? Math.round((part / whole) * 100) : 0);

// Dates arrive as YYYY-MM-DD; read them as local days.
const day = (value, options = { month: 'short', day: 'numeric' }) => {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString(undefined, options);
};

const growth = computed(() => {
    const diff = users.value.new30 - users.value.newBefore;
    if (diff === 0) return { text: `Same as the ${props.days} days before`, tone: 'flat' };
    return { text: `${diff > 0 ? '+' : '−'}${Math.abs(diff)} vs the ${props.days} days before`, tone: diff > 0 ? 'up' : 'down' };
});

const tiles = computed(() => [
    { label: 'Total users', value: users.value.total, note: `${users.value.new7} joined this week` },
    { label: `New in ${props.days} days`, value: users.value.new30, delta: growth.value },
    { label: `Active in ${props.days} days`, value: users.value.active30, note: `${percent(users.value.active30, users.value.total)}% of all users` },
    { label: 'Two-factor on', value: users.value.twoFactor, note: `${percent(users.value.twoFactor, users.value.total)}% of accounts are protected` },
]);

const signupSeries = [{ key: 'count', label: 'Sign-ups', color: 'var(--series-1)' }];
const signupRows = computed(() => props.stats.signups.map((row) => ({
    label: day(row.date),
    title: day(row.date, { weekday: 'short', month: 'short', day: 'numeric' }),
    values: { count: row.count },
})));

const signInSplit = computed(() => [
    { label: 'Continue with Google', value: users.value.google },
    { label: 'Email and password', value: users.value.total - users.value.google },
]);

const sourceRows = computed(() => props.stats.sources.map((source) => ({ label: source.label, value: source.count })));

const content = computed(() => [
    { label: 'Cards', value: props.stats.content.cards, note: `${props.stats.content.publicCards} public` },
    { label: 'Contacts', value: props.stats.content.contacts, note: 'Across every account' },
    { label: 'Notes', value: props.stats.content.notes, note: 'On contacts' },
    { label: 'Assistant questions', value: props.stats.content.questions, note: `${props.stats.content.conversations} chats` },
]);

const roleColors = ['bg-indigo-600', 'bg-violet-500', 'bg-sky-500', 'bg-amber-500', 'bg-gray-400'];
</script>

<template>
    <AppLayout title="Overview · Admin">
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <p class="eyebrow">Administration</p>
                    <h2 class="mt-1 font-semibold text-xl text-gray-800 leading-tight">Overview</h2>
                    <p class="text-sm text-gray-500 mt-1">How MetMemo is being used, across every account. Last {{ days }} days.</p>
                </div>
                <Link v-if="can('users.view')" :href="route('admin.users.index')" class="btn-quiet h-10 px-3.5 text-sm">
                    Manage users
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </Link>
            </div>
        </template>

        <div class="border-b border-gray-200">
            <div class="page"><AdminTabs /></div>
        </div>

        <div class="py-6 sm:py-8">
            <div class="page space-y-6">
                <!-- Stat tiles -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div v-for="tile in tiles" :key="tile.label" class="panel p-4 sm:p-5">
                        <p class="text-sm text-gray-500">{{ tile.label }}</p>
                        <p class="mt-1 text-2xl sm:text-3xl font-semibold text-gray-900 tabular-nums">{{ tile.value }}</p>
                        <p v-if="tile.delta" class="mt-1 flex items-center gap-1 text-xs" :class="tile.delta.tone === 'up' ? 'text-indigo-600' : 'text-gray-500'">
                            <svg v-if="tile.delta.tone !== 'flat'" class="size-3.5 shrink-0" :class="{ 'rotate-180': tile.delta.tone === 'down' }" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5v-15m0 0-6.75 6.75M12 4.5l6.75 6.75" />
                            </svg>
                            {{ tile.delta.text }}
                        </p>
                        <p v-else class="mt-1 text-xs text-gray-500">{{ tile.note }}</p>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <!-- Sign-ups per day -->
                    <section class="panel p-5 sm:p-6 xl:col-span-2">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-gray-900">New sign-ups per day</h3>
                                <p class="text-sm text-gray-500 mt-1">{{ users.new30 }} new {{ users.new30 === 1 ? 'account' : 'accounts' }} in the last {{ days }} days.</p>
                            </div>
                        </div>
                        <StackedColumns class="mt-6" :rows="signupRows" :series="signupSeries" :height="220" />
                    </section>

                    <!-- How people sign in -->
                    <section class="panel p-5 sm:p-6">
                        <h3 class="font-semibold text-gray-900">How people sign in</h3>
                        <p class="text-sm text-gray-500 mt-1">All {{ users.total }} accounts.</p>
                        <BarList class="mt-5" :rows="signInSplit" unit="accounts" />

                        <h3 class="mt-8 font-semibold text-gray-900">Roles</h3>
                        <ul class="mt-3 space-y-2.5">
                            <li v-for="(role, i) in stats.roles" :key="role.name" class="flex items-center gap-3 text-sm">
                                <span class="size-2 shrink-0 rounded-full" :class="roleColors[i % roleColors.length]" aria-hidden="true" />
                                <span class="flex-1 truncate text-gray-700">{{ role.name }}</span>
                                <span class="font-semibold tabular-nums text-gray-900">{{ role.users }}</span>
                            </li>
                        </ul>
                        <Link v-if="can('roles.view')" :href="route('admin.roles.index')" class="mt-4 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-700">Edit roles and permissions</Link>
                    </section>
                </div>

                <!-- What people keep in MetMemo: totals only -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div v-for="item in content" :key="item.label" class="panel p-4 sm:p-5">
                        <p class="text-sm text-gray-500">{{ item.label }}</p>
                        <p class="mt-1 text-xl sm:text-2xl font-semibold text-gray-900 tabular-nums">{{ item.value }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ item.note }}</p>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <!-- Recent sign-ups -->
                    <section class="panel xl:col-span-2">
                        <div class="flex items-center justify-between gap-3 px-5 pt-5 sm:px-6">
                            <h3 class="font-semibold text-gray-900">Newest accounts</h3>
                            <Link v-if="can('users.view')" :href="route('admin.users.index')" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">View all users</Link>
                        </div>
                        <ul class="mt-3">
                            <li v-for="user in stats.recent" :key="user.id" class="flex items-center gap-3 border-t border-gray-200 px-5 py-3 sm:px-6">
                                <img :src="user.photo_url" :alt="user.name" class="size-9 shrink-0 rounded-full object-cover">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ user.name }}</p>
                                    <p class="truncate text-[13px] text-gray-500">{{ user.email }}</p>
                                </div>
                                <span class="hidden sm:inline-flex h-6 items-center rounded-md border border-gray-300 bg-gray-50 px-2 text-xs text-gray-600">{{ user.google ? 'Google' : 'Email' }}</span>
                                <span v-if="user.role" class="hidden sm:inline text-[13px] text-gray-600 w-24 truncate">{{ user.role }}</span>
                                <span class="shrink-0 text-[13px] text-gray-500 tabular-nums">{{ day(user.created_at) }}</span>
                            </li>
                            <li v-if="! stats.recent.length" class="border-t border-gray-200 px-6 py-8 text-center text-sm text-gray-500">No accounts yet.</li>
                        </ul>
                    </section>

                    <!-- Contacts by source -->
                    <section class="panel p-5 sm:p-6">
                        <h3 class="font-semibold text-gray-900">How contacts come in</h3>
                        <p class="text-sm text-gray-500 mt-1">Every contact saved, by where it came from.</p>
                        <BarList class="mt-5" :rows="sourceRows" />
                    </section>
                </div>

                <p class="flex items-start gap-2 text-xs text-gray-500">
                    <svg class="mt-px size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    Counts only. No role can open another account's contacts, notes or assistant chats.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
