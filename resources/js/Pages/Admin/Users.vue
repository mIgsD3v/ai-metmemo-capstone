<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminTabs from '@/Components/AdminTabs.vue';
import Pagination from '@/Components/Pagination.vue';
import { ask } from '@/confirm';
import { can } from '@/permissions';

const props = defineProps({
    users: Object,
    roles: Array,
    filters: Object,
    perPageOptions: Array,
    canGrantSuperAdmin: Boolean,
});

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');

// Changing the search or role filter starts again at page 1; the page size is kept.
const reload = ({ page, perPage } = {}) => {
    const size = perPage ?? props.filters.per_page;
    router.get(route('admin.users.index'), {
        search: search.value.trim() || undefined,
        role: role.value || undefined,
        per_page: size === props.perPageOptions[0] ? undefined : size,
        page: page > 1 ? page : undefined,
    }, { preserveState: true, preserveScroll: ! page, replace: ! page });
};

let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => reload(), 300);
});
watch(role, () => reload());

const day = (value) => {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};

// Super Admin can only be given or taken away by a Super Admin (the server checks this too).
const canChangeRole = (user) => can('users.update') && ! user.is_self && (user.role !== 'Super Admin' || props.canGrantSuperAdmin);
const roleOptions = () => props.roles.filter((r) => r.name !== 'Super Admin' || props.canGrantSuperAdmin);

// A refused change comes back unchanged, so the select is redrawn from the server's answer.
const changeRole = (user, roleId) => {
    if (roleId === user.role_id) return;
    router.patch(route('admin.users.role', user.id), { role_id: roleId }, { preserveScroll: true });
};

const destroy = async (user) => {
    const ok = await ask({
        title: `Delete ${user.name}'s account?`,
        message: `Their ${user.cards_count} ${user.cards_count === 1 ? 'card' : 'cards'} and ${user.contacts_count} ${user.contacts_count === 1 ? 'contact' : 'contacts'} are deleted with it. This can't be undone.`,
        confirmText: 'Delete account',
    });
    if (ok) router.delete(route('admin.users.destroy', user.id), { preserveScroll: true });
};

const roleTone = (name) => ({
    'Super Admin': 'border-indigo-600/25 bg-indigo-50 text-indigo-700',
    Admin: 'border-violet-500/25 bg-violet-500/10 text-violet-700 dark:text-violet-300',
}[name] ?? 'border-gray-300 bg-gray-50 text-gray-600');
</script>

<template>
    <AppLayout title="Users · Admin">
        <template #header>
            <div class="min-w-0">
                <p class="eyebrow">Administration</p>
                <h2 class="mt-1 font-semibold text-xl text-gray-800 leading-tight">Users</h2>
                <p class="text-sm text-gray-500 mt-1">{{ users.total }} {{ users.total === 1 ? 'account' : 'accounts' }}. Change what someone can do by giving them a role.</p>
            </div>
        </template>

        <div class="border-b border-gray-200">
            <div class="page"><AdminTabs /></div>
        </div>

        <div class="py-6 sm:py-8">
            <div class="page space-y-4">
                <!-- Filters -->
                <section class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative sm:w-80">
                        <svg class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <input v-model="search" type="search" placeholder="Search name or email" aria-label="Search users" class="field h-10 bg-white ps-9">
                    </div>
                    <select v-model="role" aria-label="Filter by role" class="h-10 w-full sm:w-auto rounded-[10px] border-gray-200 bg-white text-[13px] font-medium text-gray-600 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Role: All</option>
                        <option v-for="option in roles" :key="option.id" :value="option.id">Role: {{ option.name }}</option>
                    </select>
                </section>

                <!-- Phones: one row per person -->
                <ul class="panel divide-y divide-gray-200 sm:hidden">
                    <li v-for="user in users.data" :key="user.id" class="flex items-start gap-3 p-4">
                        <img :src="user.photo_url" :alt="user.name" class="size-10 shrink-0 rounded-full object-cover">
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 truncate text-[15px] font-semibold text-gray-900">
                                <span class="truncate">{{ user.name }}</span>
                                <span v-if="user.is_self" class="shrink-0 rounded bg-gray-200 px-1.5 text-[11px] font-medium text-gray-600">You</span>
                            </p>
                            <p class="truncate text-[13px] text-gray-500">{{ user.email }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ user.cards_count }} cards · {{ user.contacts_count }} contacts · joined {{ day(user.created_at) }}</p>
                            <div class="mt-2.5 flex items-center gap-2">
                                <select
                                    v-if="canChangeRole(user)"
                                    :value="user.role_id"
                                    :aria-label="`Role for ${user.name}`"
                                    class="h-9 flex-1 rounded-[9px] border-gray-300 bg-gray-50 text-[13px] font-medium text-gray-800 focus:border-indigo-500 focus:ring-indigo-500"
                                    @change="changeRole(user, $event.target.value)"
                                >
                                    <option v-for="option in roleOptions(user)" :key="option.id" :value="option.id">{{ option.name }}</option>
                                </select>
                                <span v-else class="inline-flex h-6 items-center rounded-md border px-2 text-xs font-medium" :class="roleTone(user.role)">{{ user.role ?? 'No role' }}</span>
                                <button v-if="can('users.delete') && ! user.is_self && user.role !== 'Super Admin'" type="button" class="btn-quiet h-9 px-3 text-red-600" @click="destroy(user)">Delete</button>
                            </div>
                        </div>
                    </li>
                    <li v-if="! users.data.length" class="px-4 py-10 text-center text-sm text-gray-500">No users found.</li>
                </ul>

                <!-- Larger screens: table -->
                <div class="panel hidden overflow-hidden sm:block">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] border-collapse text-sm">
                            <thead>
                                <tr class="bg-gray-100 text-xs text-gray-400">
                                    <th class="px-5 py-3 text-start font-medium">User</th>
                                    <th class="px-5 py-3 text-start font-medium">Role</th>
                                    <th class="px-5 py-3 text-start font-medium">Sign-in</th>
                                    <th class="px-5 py-3 text-end font-medium">Cards</th>
                                    <th class="px-5 py-3 text-end font-medium">Contacts</th>
                                    <th class="px-5 py-3 text-start font-medium">Joined</th>
                                    <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 transition">
                                    <td class="border-t border-gray-200 px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <img :src="user.photo_url" :alt="user.name" class="size-8 shrink-0 rounded-full object-cover">
                                            <div class="min-w-0">
                                                <p class="flex items-center gap-2 font-semibold text-gray-900">
                                                    <span class="truncate">{{ user.name }}</span>
                                                    <span v-if="user.is_self" class="rounded bg-gray-200 px-1.5 text-[11px] font-medium text-gray-600">You</span>
                                                </p>
                                                <p class="truncate text-[13px] text-gray-500">{{ user.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="border-t border-gray-200 px-5 py-3">
                                        <select
                                            v-if="canChangeRole(user)"
                                            :value="user.role_id"
                                            :aria-label="`Role for ${user.name}`"
                                            class="h-9 w-40 rounded-[9px] border-gray-300 bg-gray-50 text-[13px] font-medium text-gray-800 focus:border-indigo-500 focus:ring-indigo-500"
                                            @change="changeRole(user, $event.target.value)"
                                        >
                                            <option v-for="option in roleOptions(user)" :key="option.id" :value="option.id">{{ option.name }}</option>
                                        </select>
                                        <span v-else class="inline-flex h-6 items-center rounded-md border px-2 text-xs font-medium" :class="roleTone(user.role)">{{ user.role ?? 'No role' }}</span>
                                    </td>
                                    <td class="border-t border-gray-200 px-5 py-3 text-gray-600">
                                        <span class="inline-flex items-center gap-2">
                                            {{ user.google ? 'Google' : 'Email' }}
                                            <span v-if="user.two_factor" class="inline-flex items-center gap-1 text-xs text-indigo-600" title="Two-factor authentication is on">
                                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                                2FA
                                            </span>
                                        </span>
                                    </td>
                                    <td class="border-t border-gray-200 px-5 py-3 text-end tabular-nums text-gray-600">{{ user.cards_count }}</td>
                                    <td class="border-t border-gray-200 px-5 py-3 text-end tabular-nums text-gray-600">{{ user.contacts_count }}</td>
                                    <td class="border-t border-gray-200 px-5 py-3 tabular-nums text-gray-600">{{ day(user.created_at) }}</td>
                                    <td class="border-t border-gray-200 px-5 py-3 text-end">
                                        <button
                                            v-if="can('users.delete') && ! user.is_self && user.role !== 'Super Admin'"
                                            type="button"
                                            class="rounded-md px-2 py-1 text-[13px] font-medium text-red-600 hover:bg-red-500/10 transition"
                                            @click="destroy(user)"
                                        >Delete</button>
                                    </td>
                                </tr>
                                <tr v-if="! users.data.length">
                                    <td colspan="7" class="border-t border-gray-200 px-5 py-10 text-center text-gray-500">No users found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="users.total" class="panel px-4 py-3 sm:px-5">
                    <Pagination :paginator="users" :per-page-options="perPageOptions" label="users" @change="reload" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
