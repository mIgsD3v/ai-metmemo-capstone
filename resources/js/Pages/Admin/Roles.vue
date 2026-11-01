<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminTabs from '@/Components/AdminTabs.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { ask } from '@/confirm';
import { can } from '@/permissions';

const props = defineProps({
    roles: Array,
    modules: Array,
});

const editable = can('roles.update');

// Ticks are shown straight away and saved in the background; the server's answer replaces them.
const granted = ref({});
watch(() => props.roles, (roles) => {
    granted.value = Object.fromEntries(roles.map((role) => [role.id, new Set(role.permissions)]));
}, { immediate: true });

const has = (role, permission) => granted.value[role.id]?.has(permission) ?? false;

const save = (role, permissions, on) => {
    const set = granted.value[role.id];
    permissions.forEach((name) => (on ? set.add(name) : set.delete(name)));
    granted.value = { ...granted.value };
    router.put(route('admin.roles.permission', role.id), { permissions, granted: on }, { preserveScroll: true, preserveState: true });
};

// --- Search -------------------------------------------------------------------------------------
const search = ref('');
const visibleModules = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.modules
        .map((module) => ({ ...module, permissions: term ? module.permissions.filter((p) => p.label.toLowerCase().includes(term) || module.name.toLowerCase().includes(term)) : module.permissions }))
        .filter((module) => module.permissions.length);
});

// A module row's box: ticked when the role has all of it, a dash when only some.
const moduleState = (role, module) => {
    const count = module.permissions.filter((p) => has(role, p.name)).length;
    return { count, all: count === module.permissions.length, some: count > 0 && count < module.permissions.length };
};
const toggleModule = (role, module) => save(role, module.permissions.map((p) => p.name), ! moduleState(role, module).all);

// --- Create -------------------------------------------------------------------------------------
const creating = ref(false);
const createForm = useForm({ name: '', permissions: [] });

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    creating.value = true;
};

const moduleNames = (module) => module.permissions.map((p) => p.name);
const allPicked = (module) => moduleNames(module).every((name) => createForm.permissions.includes(name));
const pickModule = (module) => {
    const names = moduleNames(module);
    createForm.permissions = allPicked(module)
        ? createForm.permissions.filter((name) => ! names.includes(name))
        : [...new Set([...createForm.permissions, ...names])];
};

const create = () => {
    createForm.post(route('admin.roles.store'), {
        preserveScroll: true,
        onSuccess: () => { creating.value = false; },
    });
};

// --- Rename / delete ----------------------------------------------------------------------------
const editing = ref(null);
const renameForm = useForm({ name: '' });

const openEdit = (role) => {
    editing.value = role;
    renameForm.name = role.name;
    renameForm.clearErrors();
};

const rename = () => {
    renameForm.patch(route('admin.roles.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
};

const destroy = async () => {
    const role = editing.value;
    editing.value = null;
    const ok = await ask({
        title: `Delete the ${role.name} role?`,
        message: role.users
            ? `${role.users} ${role.users === 1 ? 'person has' : 'people have'} this role. They'll become Members.`
            : 'No one has this role.',
        confirmText: 'Delete role',
    });
    if (ok) router.delete(route('admin.roles.destroy', role.id), { preserveScroll: true });
};

const checkbox = 'size-[18px] rounded-[5px] border-gray-300 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0 transition disabled:cursor-not-allowed disabled:opacity-60';
</script>

<template>
    <AppLayout title="Roles · Admin">
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <p class="eyebrow">Administration</p>
                    <h2 class="mt-1 font-semibold text-xl text-gray-800 leading-tight">Roles &amp; permissions</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ editable ? 'Tick a box to give a role that permission. Changes save straight away.' : 'What each role can do.' }}
                    </p>
                </div>
                <button v-if="can('roles.create')" type="button" class="btn-accent h-10 gap-1.5 px-4" @click="openCreate">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Add role
                </button>
            </div>
        </template>

        <div class="border-b border-gray-200">
            <div class="page"><AdminTabs /></div>
        </div>

        <div class="py-6 sm:py-8">
            <div class="page space-y-4">
                <div class="relative sm:w-80">
                    <svg class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input v-model="search" type="search" placeholder="Search permissions" aria-label="Search permissions" class="field h-10 bg-white ps-9">
                </div>

                <!-- The matrix: permissions down, roles across. The header row and the first column stay put while it scrolls. -->
                <div class="panel overflow-hidden">
                    <div class="max-h-[calc(100dvh-17rem)] min-h-[20rem] overflow-auto overscroll-contain">
                        <table class="w-full min-w-max border-separate border-spacing-0 text-sm">
                            <thead>
                                <tr>
                                    <th class="sticky start-0 top-0 z-30 w-[16rem] min-w-[12rem] border-b border-e border-gray-200 bg-gray-100 px-4 py-3 text-start text-xs font-medium text-gray-500 sm:min-w-[16rem] sm:px-5">
                                        Permission
                                    </th>
                                    <th v-for="role in roles" :key="role.id" class="sticky top-0 z-20 min-w-[9.5rem] border-b border-gray-200 bg-gray-100 px-3 py-2.5 text-center font-medium">
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="truncate text-[13px] font-semibold text-gray-900">{{ role.name }}</span>
                                            <svg v-if="role.locked" class="size-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-label="Locked: always has every permission"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                            <button
                                                v-if="! role.builtIn && (can('roles.update') || can('roles.delete'))"
                                                type="button"
                                                class="-me-1 flex size-7 shrink-0 items-center justify-center rounded-md text-gray-400 hover:bg-gray-200 hover:text-gray-800 transition"
                                                :aria-label="`Rename or delete ${role.name}`"
                                                @click="openEdit(role)"
                                            >
                                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" /></svg>
                                            </button>
                                        </div>
                                        <p class="mt-0.5 text-[11px] font-normal text-gray-500">
                                            {{ role.users }} {{ role.users === 1 ? 'user' : 'users' }}<template v-if="role.builtIn"> · Built-in</template>
                                        </p>
                                    </th>
                                </tr>
                            </thead>
                            <tbody v-for="module in visibleModules" :key="module.name">
                                <!-- Module row: its name, and a box per role that ticks the whole module -->
                                <tr>
                                    <th scope="rowgroup" class="sticky start-0 z-10 border-b border-e border-gray-200 bg-gray-50 px-4 py-2.5 text-start sm:px-5">
                                        <span class="eyebrow !text-gray-500">{{ module.name }}</span>
                                    </th>
                                    <td v-for="role in roles" :key="role.id" class="border-b border-gray-200 bg-gray-50 px-3 py-2.5 text-center">
                                        <label class="inline-flex items-center gap-2 text-[11px] font-medium text-gray-500 tabular-nums" :class="{ 'cursor-pointer': editable && ! role.locked }">
                                            <input
                                                type="checkbox"
                                                :class="checkbox"
                                                :checked="moduleState(role, module).all"
                                                :indeterminate="moduleState(role, module).some"
                                                :disabled="! editable || role.locked"
                                                :aria-label="`${role.name}: all of ${module.name}`"
                                                @change="toggleModule(role, module)"
                                            >
                                            {{ moduleState(role, module).count }}/{{ module.permissions.length }}
                                        </label>
                                    </td>
                                </tr>
                                <tr v-for="permission in module.permissions" :key="permission.name" class="group">
                                    <th scope="row" class="sticky start-0 z-10 border-b border-e border-gray-200 bg-white px-4 py-3 text-start font-normal text-gray-700 group-hover:bg-gray-50 sm:px-5">
                                        {{ permission.label }}
                                    </th>
                                    <td v-for="role in roles" :key="role.id" class="border-b border-gray-200 px-3 py-3 text-center group-hover:bg-gray-50">
                                        <input
                                            type="checkbox"
                                            :class="checkbox"
                                            :checked="has(role, permission.name)"
                                            :disabled="! editable || role.locked"
                                            :aria-label="`${role.name}: ${permission.label}`"
                                            @change="save(role, [permission.name], $event.target.checked)"
                                        >
                                    </td>
                                </tr>
                            </tbody>
                            <tbody v-if="! visibleModules.length">
                                <tr>
                                    <td :colspan="roles.length + 1" class="px-5 py-10 text-center text-gray-500">No permissions match “{{ search }}”.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="text-xs text-gray-500">
                    Super Admin always has every permission. Every new account starts as a Member. No role can open another account's contacts, notes or chats.
                </p>
            </div>
        </div>

        <!-- Add role -->
        <Modal :show="creating" max-width="xl" @close="creating = false">
            <form class="flex max-h-[calc(100dvh-3rem)] flex-col" @submit.prevent="create">
                <div class="px-6 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900">Add role</h3>
                    <p class="mt-1 text-sm text-gray-500">Name it, then pick what people with this role can do. You can change it later.</p>

                    <label for="role-name" class="mt-5 block text-[13px] font-medium text-gray-700">Role name</label>
                    <input id="role-name" v-model="createForm.name" type="text" maxlength="40" placeholder="e.g. Support" class="field mt-1.5" required autofocus>
                    <InputError class="mt-1.5" :message="createForm.errors.name" />
                </div>

                <div class="mt-5 min-h-0 flex-1 overflow-y-auto border-y border-gray-200 bg-gray-50 px-6 py-4">
                    <section v-for="module in modules" :key="module.name" class="py-2">
                        <div class="flex items-center justify-between">
                            <h4 class="eyebrow !text-gray-500">{{ module.name }}</h4>
                            <button type="button" class="text-[13px] font-medium text-indigo-600 hover:text-indigo-700" @click="pickModule(module)">
                                {{ allPicked(module) ? 'Clear all' : 'Select all' }}
                            </button>
                        </div>
                        <div class="mt-2 grid gap-x-4 gap-y-1 sm:grid-cols-2">
                            <label v-for="permission in module.permissions" :key="permission.name" class="flex cursor-pointer items-center gap-2.5 rounded-md px-1.5 py-1.5 text-sm text-gray-700 hover:bg-white">
                                <input v-model="createForm.permissions" type="checkbox" :value="permission.name" :class="checkbox">
                                {{ permission.label }}
                            </label>
                        </div>
                    </section>
                </div>

                <div class="flex items-center justify-between gap-3 px-6 py-4">
                    <span class="text-[13px] text-gray-500 tabular-nums">{{ createForm.permissions.length }} selected</span>
                    <div class="flex gap-2">
                        <button type="button" class="btn-quiet h-10 px-4 text-sm" @click="creating = false">Cancel</button>
                        <button type="submit" class="btn-accent h-10 px-4 disabled:opacity-50" :disabled="createForm.processing || ! createForm.name.trim()">Create role</button>
                    </div>
                </div>
            </form>
        </Modal>

        <!-- Rename / delete a role people added -->
        <Modal :show="editing !== null" max-width="md" @close="editing = null">
            <form v-if="editing" class="p-6" @submit.prevent="rename">
                <h3 class="text-lg font-semibold text-gray-900">Edit role</h3>
                <p class="mt-1 text-sm text-gray-500">{{ editing.users }} {{ editing.users === 1 ? 'person has' : 'people have' }} this role.</p>

                <template v-if="can('roles.update')">
                    <label for="rename-role" class="mt-5 block text-[13px] font-medium text-gray-700">Role name</label>
                    <input id="rename-role" v-model="renameForm.name" type="text" maxlength="40" class="field mt-1.5" required>
                    <InputError class="mt-1.5" :message="renameForm.errors.name" />
                </template>

                <div class="mt-6 flex items-center justify-between gap-3">
                    <button v-if="can('roles.delete')" type="button" class="rounded-md px-2 py-1.5 text-sm font-medium text-red-600 hover:bg-red-500/10 transition" @click="destroy">Delete role</button>
                    <span v-else />
                    <div class="flex gap-2">
                        <button type="button" class="btn-quiet h-10 px-4 text-sm" @click="editing = null">Cancel</button>
                        <button v-if="can('roles.update')" type="submit" class="btn-accent h-10 px-4 disabled:opacity-50" :disabled="renameForm.processing || ! renameForm.name.trim()">Save</button>
                    </div>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
